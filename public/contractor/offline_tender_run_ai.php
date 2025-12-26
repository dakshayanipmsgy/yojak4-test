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
    $runMode = trim($_POST['run_mode'] ?? 'strict');
    $lenient = $runMode === 'lenient';
    $tender = $offtdId !== '' ? load_offline_tender($yojId, $offtdId) : null;
    if (!$tender || ($tender['yojId'] ?? '') !== $yojId) {
        render_error_page('Tender not found.');
        return;
    }

    $config = load_ai_config();
    if (($config['provider'] ?? '') === '' || ($config['textModel'] ?? '') === '' || empty($config['hasApiKey'])) {
        set_flash('error', 'AI is not configured. Please contact support. Superadmin can configure this in AI Studio (/superadmin/ai_studio.php).');
        ai_log([
            'event' => 'ai_missing_config',
            'purpose' => 'offline_tender_extract',
            'yojId' => $yojId,
            'offtdId' => $offtdId,
        ]);
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        return;
    }

    [$systemPrompt, $userPrompt] = offline_tender_ai_prompt($tender, $lenient);
    $aiResult = ai_call([
        'systemPrompt' => $systemPrompt,
        'userPrompt' => $userPrompt,
        'expectJson' => true,
        'purpose' => 'offline_tender_extract',
        'runMode' => $runMode,
    ]);

    $now = now_kolkata()->format(DateTime::ATOM);
    $aiState = $tender['ai'] ?? [];
    $aiState['lastRunAt'] = $now;
    $aiState['provider'] = $config['provider'] ?? '';
    $aiState['httpStatus'] = $aiResult['httpStatus'] ?? null;
    $aiState['requestId'] = $aiResult['requestId'] ?? null;
    $aiState['rawText'] = $aiResult['rawText'] ?? '';
    $aiState['parsedOk'] = (bool)($aiResult['parsedOk'] ?? false);
    $aiState['parseStage'] = $aiResult['parseStage'] ?? 'fallback_manual';
    $aiState['errors'] = $aiResult['errors'] ?? [];
    $aiState['providerError'] = $aiResult['providerError'] ?? null;
    $aiState['providerOk'] = (bool)($aiResult['providerOk'] ?? false);
    $aiState['runMode'] = $runMode;
    if (!empty($aiResult['rawEnvelope']) && is_array($aiResult['rawEnvelope'])) {
        $aiState['rawEnvelope'] = $aiResult['rawEnvelope'];
    }

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

    if ($aiState['parsedOk']) {
        $aiState['candidateExtracted'] = $newExtracted;
        $aiState['candidateChecklist'] = $newChecklist;
        $aiState['candidateReadyAt'] = $now;
    }

    $tender['ai'] = $aiState;
    $tender['status'] = $aiState['parsedOk'] ? 'ai_ready' : 'editing';
    $tender['updatedAt'] = $now;

    save_offline_tender($tender);

    offline_tender_log([
        'event' => 'ai_run',
        'yojId' => $yojId,
        'offtdId' => $offtdId,
        'parsedOk' => $aiState['parsedOk'],
        'providerOk' => $aiState['providerOk'] ?? false,
        'httpStatus' => $aiState['httpStatus'] ?? null,
        'errorCount' => count($aiState['errors']),
    ]);

    if ($aiState['parsedOk']) {
        set_flash('success', 'AI responded successfully. Review the extracted values and apply them when ready.');
    } elseif (!empty($aiState['providerOk'])) {
        set_flash('error', 'AI responded, but the output was not in JSON format. Review the debug details below and try again.');
    } else {
        set_flash('error', 'The AI provider returned an error. Please review the debug details and retry.');
    }

    redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
});
