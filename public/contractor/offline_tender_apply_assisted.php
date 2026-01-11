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
    
    // Handle V2 Structure
    $tData = $normalized['tender'] ?? [];
    $lData = $normalized['lists'] ?? [];
    
    $tender['extracted']['submissionDeadline'] = $tData['submissionDeadline'] ?? ($normalized['submissionDeadline'] ?? null);
    $tender['extracted']['openingDate'] = $tData['openingDate'] ?? ($normalized['openingDate'] ?? null);
    $tender['extracted']['completionMonths'] = $tData['completionMonths'] ?? ($normalized['completionMonths'] ?? null);
    $tender['extracted']['bidValidityDays'] = $tData['validityDays'] ?? ($normalized['bidValidityDays'] ?? null);
    $fees = $normalized['fees'] ?? [];
    $existingFees = $tender['extracted']['fees'] ?? ['tenderFee' => '', 'emd' => '', 'other' => ''];
    $tender['extracted']['fees'] = [
        'tenderFee' => $fees['tenderFeeText'] ?? $existingFees['tenderFee'] ?? '',
        'emd' => $fees['emdText'] ?? $existingFees['emd'] ?? '',
        'other' => $existingFees['other'] ?? '',
    ];
    $otherFees = [];
    if (!empty($fees['sdText'])) {
        $otherFees[] = 'SD: ' . $fees['sdText'];
    }
    if (!empty($fees['pgText'])) {
        $otherFees[] = 'PG: ' . $fees['pgText'];
    }
    if ($otherFees) {
        $tender['extracted']['fees']['other'] = trim(implode(' | ', array_filter([$tender['extracted']['fees']['other'] ?? '', implode(' | ', $otherFees)])));
    }
    
    $tender['extracted']['eligibilityDocs'] = $lData['eligibilityDocs'] ?? ($normalized['eligibilityDocs'] ?? []);
    $tender['extracted']['annexures'] = $lData['annexures'] ?? ($normalized['annexures'] ?? []);
    $tender['extracted']['restrictedAnnexures'] = $lData['restricted'] ?? ($normalized['restrictedAnnexures'] ?? []);
    $tender['extracted']['formats'] = $lData['formats'] ?? ($normalized['formats'] ?? []);

    $tender['checklist'] = [];
    foreach ($normalized['checklist'] as $item) {
        $tender['checklist'][] = offline_tender_checklist_item(
            $item['title'] ?? '',
            $item['description'] ?? '',
            (bool)($item['required'] ?? true),
            'assisted'
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
    ];
    $tender['updatedAt'] = now_kolkata()->format(DateTime::ATOM);

    $contractor = load_contractor($yojId) ?? [];
    $pack = pack_upsert_offline_tender($tender, $normalized, $contractor);
    if ($pack) {
        $tender['assistedExtraction']['packId'] = $pack['packId'] ?? null;
    }

    save_offline_tender($tender);

    assisted_append_audit($request, $yojId, 'applied_to_tender', null);
    assisted_save_request($request);

    logEvent(ASSISTED_EXTRACTION_LOG, [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => 'ASSISTED_APPLY',
        'ok' => true,
        'reqId' => $reqId,
        'yojId' => $yojId,
        'offtdId' => $offtdId,
        'packId' => $pack['packId'] ?? null,
    ]);

    set_flash('success', 'Assisted checklist applied to your tender. Annexures and print pack are ready.');
    redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
});
