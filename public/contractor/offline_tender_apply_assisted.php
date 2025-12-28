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
    $tender['extracted']['submissionDeadline'] = $normalized['submissionDeadline'];
    $tender['extracted']['openingDate'] = $normalized['openingDate'];
    $tender['extracted']['completionMonths'] = $normalized['completionMonths'];
    $tender['extracted']['bidValidityDays'] = $normalized['bidValidityDays'];
    $tender['extracted']['eligibilityDocs'] = $normalized['eligibilityDocs'];
    $tender['extracted']['annexures'] = $normalized['annexures'];
    $tender['extracted']['formats'] = $normalized['formats'];

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
    ]);

    set_flash('success', 'Assisted checklist applied to your tender. You can still edit details below.');
    redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
});
