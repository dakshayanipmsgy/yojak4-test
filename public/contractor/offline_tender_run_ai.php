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

    $offtdId = trim($_POST['id'] ?? '');
    $tender = $offtdId !== '' ? load_offline_tender($yojId, $offtdId) : null;
    if (!$tender || ($tender['yojId'] ?? '') !== $yojId) {
        render_error_page('Tender not found.');
        return;
    }

    $config = load_ai_config();
    if (($config['provider'] ?? '') === '' || ($config['textModel'] ?? '') === '' || empty($config['hasApiKey'])) {
        set_flash('error', 'AI is not configured. Please contact support. Superadmin can configure this in AI Studio (/superadmin/ai_studio.php).');
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        return;
    }

    [$systemPrompt, $userPrompt] = offline_tender_ai_prompt($tender);
    $aiResult = ai_call([
        'systemPrompt' => $systemPrompt,
        'userPrompt' => $userPrompt,
        'expectJson' => true,
        'purpose' => 'offline_tender_extract',
    ]);

    $now = now_kolkata()->format(DateTime::ATOM);
    $ai = [
        'lastRunAt' => $now,
        'rawText' => $aiResult['rawText'] ?? '',
        'parsedOk' => (bool)($aiResult['ok'] ?? false),
        'errors' => $aiResult['errors'] ?? [],
    ];

    $newExtracted = offline_tender_defaults();
    $newChecklist = $tender['checklist'] ?? [];

    if (!empty($aiResult['json']) && is_array($aiResult['json'])) {
        $data = $aiResult['json'];
        $newExtracted['publishDate'] = isset($data['publishDate']) && $data['publishDate'] !== '' ? (string)$data['publishDate'] : null;
        $newExtracted['submissionDeadline'] = isset($data['submissionDeadline']) && $data['submissionDeadline'] !== '' ? (string)$data['submissionDeadline'] : null;
        $newExtracted['openingDate'] = isset($data['openingDate']) && $data['openingDate'] !== '' ? (string)$data['openingDate'] : null;
        $fees = $data['fees'] ?? [];
        $newExtracted['fees'] = [
            'tenderFee' => trim((string)($fees['tenderFee'] ?? '')),
            'emd' => trim((string)($fees['emd'] ?? '')),
            'other' => trim((string)($fees['other'] ?? '')),
        ];
        $newExtracted['completionMonths'] = isset($data['completionMonths']) && is_numeric($data['completionMonths']) ? (int)$data['completionMonths'] : null;
        $newExtracted['bidValidityDays'] = isset($data['bidValidityDays']) && is_numeric($data['bidValidityDays']) ? (int)$data['bidValidityDays'] : null;
        $newExtracted['eligibilityDocs'] = normalize_string_list($data['eligibilityDocs'] ?? []);
        $newExtracted['annexures'] = normalize_string_list($data['annexures'] ?? []);
        $newExtracted['formats'] = normalize_formats($data['formats'] ?? []);

        $incomingChecklist = [];
        if (isset($data['checklist']) && is_array($data['checklist'])) {
            $incomingChecklist = $data['checklist'];
        }
        $newChecklist = merge_checklist($newChecklist, $incomingChecklist);
    }

    if (!$ai['parsedOk']) {
        $newExtracted = offline_tender_defaults();
    }

    $tender['ai'] = $ai;
    $tender['extracted'] = $newExtracted;
    $tender['checklist'] = $newChecklist;
    $tender['status'] = $ai['parsedOk'] ? 'ai_extracted' : 'editing';
    $tender['updatedAt'] = $now;

    save_offline_tender($tender);

    offline_tender_log([
        'event' => 'ai_run',
        'yojId' => $yojId,
        'offtdId' => $offtdId,
        'parsedOk' => $ai['parsedOk'],
        'errorCount' => count($ai['errors']),
    ]);

    if ($ai['parsedOk']) {
        set_flash('success', 'AI extraction completed. Review and save any changes.');
    } else {
        set_flash('error', 'AI response could not be parsed. Fields reset to manual entry; please review the AI text below.');
    }

    redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
});
