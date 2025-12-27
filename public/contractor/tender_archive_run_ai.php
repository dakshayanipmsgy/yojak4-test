<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/tender_archive.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_tender_archive_env($yojId);

    $archId = trim($_POST['id'] ?? '');
    $archive = $archId !== '' ? load_tender_archive($yojId, $archId) : null;
    if (!$archive || ($archive['yojId'] ?? '') !== $yojId) {
        render_error_page('Archive not found.');
        return;
    }

    $configResult = ai_get_config();
    $config = $configResult['config'] ?? [];
    if (!$configResult['ok'] || ($config['textModel'] ?? '') === '' || empty($config['hasApiKey'])) {
        set_flash('error', 'AI is not configured. Please contact support.');
        redirect('/contractor/tender_archive_view.php?id=' . urlencode($archId));
        return;
    }

    [$systemPrompt, $userPrompt] = tender_archive_ai_prompt($archive);
    $aiResult = ai_call_text(
        'tender_archive_summary',
        $systemPrompt,
        $userPrompt,
        [
            'expectJson' => true,
        ]
    );

    $now = now_kolkata()->format(DateTime::ATOM);
    $aiSummary = array_merge(tender_archive_ai_defaults(), $archive['aiSummary'] ?? []);
    $aiSummary['lastRunAt'] = $now;
    $aiSummary['rawText'] = $aiResult['rawText'] ?? '';
    $aiSummary['parsedOk'] = (bool)($aiResult['ok'] ?? false);

    if (!empty($aiResult['json']) && is_array($aiResult['json'])) {
        $data = $aiResult['json'];
        $aiSummary['summaryText'] = trim((string)($data['summaryText'] ?? ($data['summary'] ?? '')));
        $aiSummary['keyLearnings'] = normalize_archive_learnings($data['keyLearnings'] ?? ($data['learnings'] ?? []));
        $aiSummary['suggestedChecklist'] = normalize_archive_checklist($data['suggestedChecklist'] ?? ($data['checklist'] ?? []));
    }

    $archive['aiSummary'] = $aiSummary;
    $archive['updatedAt'] = $now;

    save_tender_archive($archive);

    tender_archive_log([
        'event' => 'ai_run',
        'yojId' => $yojId,
        'archId' => $archId,
        'parsedOk' => $aiSummary['parsedOk'],
        'errorCount' => count($aiResult['errors'] ?? []),
    ]);

    if ($aiSummary['parsedOk']) {
        set_flash('success', 'AI summary generated. Review and edit as needed.');
    } else {
        set_flash('error', 'AI response could not be parsed. Manual edits are still available below.');
    }

    redirect('/contractor/tender_archive_view.php?id=' . urlencode($archId));
});
