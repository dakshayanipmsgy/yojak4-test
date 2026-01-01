<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/offline_tenders.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_offline_tender_env($yojId);
    ensure_assisted_extraction_env();

    $offtdId = trim($_POST['id'] ?? '');
    $reqId = trim($_POST['reqId'] ?? '');
    $tender = $offtdId !== '' ? load_offline_tender($yojId, $offtdId) : null;
    $request = $reqId !== '' ? assisted_load_request($reqId) : null;

    if (!$tender || ($tender['yojId'] ?? '') !== $yojId) {
        render_error_page('Tender not found.');
        return;
    }
    if (!$request || ($request['yojId'] ?? '') !== $yojId || ($request['offtdId'] ?? '') !== $offtdId) {
        set_flash('error', 'Assisted extraction request not found.');
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        return;
    }
    if (($request['status'] ?? '') !== 'delivered') {
        set_flash('error', 'Assisted extraction is not delivered yet.');
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        return;
    }

    $draft = $request['assistantDraft'] ?? [];
    if (!is_array($draft)) {
        set_flash('error', 'Delivered draft is missing or invalid.');
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        return;
    }

    $validation = assisted_validate_payload($draft);
    $errors = $validation['errors'] ?? [];
    if ($errors) {
        $forbidden = $validation['forbiddenFindings'] ?? [];
        if (!empty($forbidden)) {
            $findingsToLog = [];
            foreach ($forbidden as $finding) {
                $findingsToLog[] = [
                    'path' => $finding['path'] ?? '',
                    'reasonCode' => $finding['reasonCode'] ?? '',
                ];
            }
            logEvent(ASSISTED_EXTRACTION_LOG, [
                'at' => now_kolkata()->format(DateTime::ATOM),
                'event' => 'ASSISTED_VALIDATE_BLOCK',
                'reqId' => $reqId,
                'actor' => $yojId,
                'findings' => $findingsToLog,
                'restrictedAnnexuresCount' => $validation['restrictedAnnexuresCount'] ?? 0,
            ]);
        }
        set_flash('error', implode(' ', $errors));
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        return;
    }

    $normalized = $validation['normalized'] ?? assisted_normalize_payload($draft);
    $previous = $tender['checklist'] ?? [];
    if (!isset($tender['previousChecklists']) || !is_array($tender['previousChecklists'])) {
        $tender['previousChecklists'] = [];
    }
    if ($previous) {
        $tender['previousChecklists'][] = [
            'savedAt' => now_kolkata()->format(DateTime::ATOM),
            'source' => 'assisted_apply',
            'items' => $previous,
        ];
    }

    $tender['extracted'] = array_merge(offline_tender_defaults(), $tender['extracted'] ?? []);
    $tenderDetails = $normalized['tender'] ?? [];
    $listDetails = $normalized['lists'] ?? [];

    $tender['title'] = $tenderDetails['tenderTitle'] ?: ($tender['title'] ?? null);
    $tender['tenderNumber'] = $tenderDetails['tenderNumber'] ?? ($tender['tenderNumber'] ?? null);
    $tender['departmentName'] = $tenderDetails['departmentName'] ?? ($tender['departmentName'] ?? null);
    $tender['location'] = $tenderDetails['location'] ?? ($tender['location'] ?? null);

    $tender['extracted']['submissionDeadline'] = $tenderDetails['submissionDeadline'] ?? null;
    $tender['extracted']['openingDate'] = $tenderDetails['openingDate'] ?? null;
    $tender['extracted']['completionMonths'] = $tenderDetails['completionMonths'] ?? null;
    $tender['extracted']['validityDays'] = $tenderDetails['validityDays'] ?? null;
    $tender['extracted']['bidValidityDays'] = $tenderDetails['validityDays'] ?? null;
    $tender['extracted']['eligibilityDocs'] = $listDetails['eligibilityDocs'] ?? [];
    $tender['extracted']['annexures'] = $listDetails['annexures'] ?? [];
    $tender['extracted']['restrictedAnnexures'] = $listDetails['restricted'] ?? [];
    $tender['extracted']['formats'] = $listDetails['formats'] ?? [];
    $tender['assistedSnippets'] = $normalized['snippets'] ?? [];
    $tender['assistedTemplates'] = $normalized['templates'] ?? [];

    $tender['checklist'] = [];
    foreach ($normalized['checklist'] as $item) {
        $tender['checklist'][] = offline_tender_checklist_item(
            $item['title'] ?? '',
            $item['notes'] ?? ($item['description'] ?? ''),
            (bool)($item['required'] ?? true),
            'assisted',
            $item['category'] ?? 'Other',
            $item['sourceSnippet'] ?? '',
            $item['notes'] ?? ($item['description'] ?? '')
        );
    }

    $tender['status'] = 'assisted_applied';
    if (!isset($tender['assistedExtractHistory']) || !is_array($tender['assistedExtractHistory'])) {
        $tender['assistedExtractHistory'] = [];
    }
    if (!empty($tender['assistedExtract'])) {
        $tender['assistedExtractHistory'][] = [
            'savedAt' => now_kolkata()->format(DateTime::ATOM),
            'payload' => $tender['assistedExtract'],
        ];
    }
    $tender['assistedExtract'] = $normalized;
    $tender['assistedExtractDeliveredAt'] = $request['deliveredAt'] ?? null;
    $tender['assistedExtraction'] = [
        'reqId' => $reqId,
        'status' => 'applied',
        'deliveredAt' => $request['deliveredAt'] ?? null,
        'assignedTo' => $request['assignedTo'] ?? null,
        'appliedAt' => now_kolkata()->format(DateTime::ATOM),
        'payloadPath' => $request['deliveredPayloadPath'] ?? ($tender['assisted']['payloadPath'] ?? null),
    ];
    $tender['updatedAt'] = now_kolkata()->format(DateTime::ATOM);

    save_offline_tender($tender);
    ensure_packs_env($yojId);
    $existingPack = find_pack_by_source($yojId, 'OFFTD', $offtdId);
    if ($existingPack && !empty($existingPack['packId'])) {
        $pack = load_pack($yojId, $existingPack['packId']);
        if ($pack) {
            if (empty($pack['annexures'])) {
                $pack['annexures'] = $listDetails['annexures'] ?? [];
            }
            if (empty($pack['formats'])) {
                $pack['formats'] = $listDetails['formats'] ?? [];
            }
            $pack['restrictedAnnexures'] = array_values(array_unique(array_merge($pack['restrictedAnnexures'] ?? [], $listDetails['restricted'] ?? [])));
            if (!empty($tenderDetails['tenderTitle'])) {
                $pack['tenderTitle'] = $tenderDetails['tenderTitle'];
            }
            if (!empty($tenderDetails['tenderNumber'])) {
                $pack['tenderNumber'] = $tenderDetails['tenderNumber'];
            }
            if (!empty($tenderDetails['departmentName'])) {
                $pack['deptName'] = $tenderDetails['departmentName'];
            }
            if (!empty($tenderDetails['submissionDeadline'])) {
                $pack['dates']['submission'] = $tenderDetails['submissionDeadline'];
            }
            if (!empty($tenderDetails['openingDate'])) {
                $pack['dates']['opening'] = $tenderDetails['openingDate'];
            }
            $pack['assisted'] = [
                'reqId' => $reqId,
                'appliedAt' => now_kolkata()->format(DateTime::ATOM),
                'deliveredAt' => $request['deliveredAt'] ?? null,
                'payloadPath' => $request['deliveredPayloadPath'] ?? ($tender['assisted']['payloadPath'] ?? null),
            ];
            $pack['assistedTemplates'] = $normalized['templates'] ?? [];
            $pack['assistedSnippets'] = $normalized['snippets'] ?? [];
            if (empty($pack['items']) || count($pack['items']) <= 2) {
                $pack['items'] = pack_items_from_checklist($tender['checklist']);
            }
            save_pack($pack);
        }
    }

    assisted_append_audit($request, $yojId, 'applied_to_tender', null);
    assisted_save_request($request);

    logEvent(ASSISTED_EXTRACTION_LOG, [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => 'ASSISTED_APPLY',
        'ok' => true,
        'reqId' => $reqId,
        'yojId' => $yojId,
        'offtdId' => $offtdId,
        'restrictedCount' => count($listDetails['restricted'] ?? []),
    ]);

    set_flash('success', 'Assisted checklist applied to your tender. You can still edit details below.');
    redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
});
