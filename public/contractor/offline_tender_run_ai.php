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
    $aiState = $tender['ai'] ?? [];
    if (!$tender || ($tender['yojId'] ?? '') !== $yojId) {
        render_error_page('Tender not found.');
        return;
    }

    $now = now_kolkata();
    $recentRuns = [];
    foreach ((array)($aiState['runHistory'] ?? []) as $runAt) {
        $ts = strtotime((string)$runAt);
        if ($ts !== false && ($now->getTimestamp() - $ts) <= 3600) {
            $recentRuns[] = $runAt;
        }
    }
    if (count($recentRuns) >= 5) {
        set_flash('error', 'AI rerun limit reached (5 per hour). Please try again later.');
        offline_tender_log([
            'event' => 'ai_run_rate_limited',
            'yojId' => $yojId,
            'offtdId' => $offtdId,
            'recentCount' => count($recentRuns),
        ]);
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        return;
    }

    $configResult = ai_get_config();
    $config = $configResult['config'] ?? [];
    $resolvedModels = ai_resolve_purpose_models($config, 'offline_tender_extract');
    $structuredEnabled = ($config['provider'] ?? '') === 'gemini' && !empty($resolvedModels['useStructuredJson']);
    if (!$configResult['ok'] || (($config['textModel'] ?? '') === '' && (($resolvedModels['primaryModel'] ?? '') === '')) || empty($config['hasApiKey'])) {
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
    $aiResult = ai_call_text(
        'offline_tender_extract',
        $systemPrompt,
        $userPrompt,
        [
            'expectJson' => true,
            'runMode' => $runMode,
        ]
    );

    $nowIso = $now->format(DateTime::ATOM);
    $aiState['lastRunAt'] = $nowIso;
    $aiState['provider'] = $config['provider'] ?? '';
    $aiState['httpStatus'] = $aiResult['httpStatus'] ?? null;
    $aiState['requestId'] = $aiResult['requestId'] ?? null;
    $aiState['responseId'] = $aiResult['responseId'] ?? ($aiResult['requestId'] ?? null);
    $aiState['rawText'] = $aiResult['rawText'] ?? '';
    $aiState['parsedOk'] = (bool)($aiResult['parsedOk'] ?? false);
    $aiState['parseStage'] = $aiResult['parseStage'] ?? 'fallback_manual';
    $aiState['errors'] = $aiResult['errors'] ?? [];
    $aiState['providerError'] = $aiResult['providerError'] ?? null;
    $aiState['providerOk'] = (bool)($aiResult['providerOk'] ?? false);
    $aiState['runMode'] = $runMode;
    $aiState['finishReason'] = $aiResult['finishReason'] ?? null;
    $aiState['promptBlockReason'] = $aiResult['promptBlockReason'] ?? null;
    $aiState['safetyRatingsSummary'] = $aiResult['safetyRatingsSummary'] ?? '';
    $aiState['retryCount'] = (int)($aiResult['retryCount'] ?? 0);
    $aiState['fallbackUsed'] = (bool)($aiResult['fallbackUsed'] ?? false);
    $aiState['schemaValidation'] = $aiResult['schemaValidation'] ?? ['enabled' => false, 'passed' => true, 'errors' => []];
    if (!empty($aiResult['attempts']) && is_array($aiResult['attempts'])) {
        $aiState['attempts'] = $aiResult['attempts'];
    }
    if (!empty($aiResult['rawEnvelope']) && is_array($aiResult['rawEnvelope'])) {
        $aiState['rawEnvelope'] = $aiResult['rawEnvelope'];
    }
    $emptyContentError = false;
    foreach ((array)($aiState['errors'] ?? []) as $err) {
        if (stripos((string)$err, 'empty content') !== false) {
            $emptyContentError = true;
            break;
        }
    }
    if ($emptyContentError && ($aiState['provider'] ?? '') === 'gemini') {
        $aiState['emptyContentEvents'] = (int)($aiState['emptyContentEvents'] ?? 0) + 1;
        $aiState['emptyContentLastSeenAt'] = $nowIso;
    } else {
        $aiState['emptyContentEvents'] = (int)($aiState['emptyContentEvents'] ?? 0);
    }
    $runHistory = array_values(array_filter((array)($aiState['runHistory'] ?? []), static function ($runAt) use ($now) {
        $ts = strtotime((string)$runAt);
        return $ts !== false && ($now->getTimestamp() - $ts) <= 86400;
    }));
    $runHistory[] = $nowIso;
    $aiState['runHistory'] = array_slice($runHistory, -20);

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
        $newExtracted['checklist'] = $incomingChecklist;
        $newChecklist = merge_checklist($newChecklist, $incomingChecklist);
    }

    if ($aiState['parsedOk']) {
        $aiState['candidateExtracted'] = $newExtracted;
        $aiState['candidateChecklist'] = $newChecklist;
        $aiState['candidateReadyAt'] = $nowIso;
    }

    $tender['ai'] = $aiState;
    $tender['status'] = $aiState['parsedOk'] ? 'ai_ready' : 'editing';
    $tender['updatedAt'] = $nowIso;

    save_offline_tender($tender);

    offline_tender_log([
        'event' => 'ai_run',
        'yojId' => $yojId,
        'offtdId' => $offtdId,
        'parsedOk' => $aiState['parsedOk'],
        'providerOk' => $aiState['providerOk'] ?? false,
        'httpStatus' => $aiState['httpStatus'] ?? null,
        'errorCount' => count($aiState['errors']),
        'retryCount' => $aiState['retryCount'] ?? 0,
        'fallbackUsed' => $aiState['fallbackUsed'] ?? false,
        'finishReason' => $aiState['finishReason'] ?? null,
        'blockReason' => $aiState['promptBlockReason'] ?? null,
        'structuredOutputs' => $structuredEnabled,
        'schemaValidationPassed' => $aiState['schemaValidation']['passed'] ?? true,
        'schemaErrors' => array_slice((array)($aiState['schemaValidation']['errors'] ?? []), 0, 5),
    ]);

    if ($aiState['parsedOk']) {
        set_flash('success', 'AI responded successfully. Review the extracted values and apply them when ready.');
    } elseif (!empty($aiState['providerOk'])) {
        if (($aiState['schemaValidation']['enabled'] ?? false) && !($aiState['schemaValidation']['passed'] ?? true)) {
            set_flash('error', 'AI responded, but the structured JSON was missing required keys. Automatic retries/fallbacks have run; please review the debug details.');
        } elseif (trim((string)($aiState['rawText'] ?? '')) === '') {
            set_flash('error', 'Gemini returned an empty final response. Streaming/retry/fallback have been attempted automatically. Consider switching to a fallback model in AI Studio.');
        } else {
            set_flash('error', 'AI responded, but the output was not in JSON format. Review the debug details below and try again.');
        }
    } else {
        set_flash('error', 'The AI provider returned an error. Please review the debug details and retry.');
    }

    redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
});
