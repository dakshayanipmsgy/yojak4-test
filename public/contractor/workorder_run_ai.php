<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/workorders.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_workorder_env($yojId);

    $woId = trim($_POST['id'] ?? '');
    $workorder = $woId !== '' ? load_workorder($yojId, $woId) : null;
    if (!$workorder || ($workorder['yojId'] ?? '') !== $yojId) {
        render_error_page('Workorder not found.');
        return;
    }

    $configResult = ai_get_config();
    $config = $configResult['config'] ?? [];
    if (!$configResult['ok'] || ($config['textModel'] ?? '') === '' || empty($config['hasApiKey'])) {
        set_flash('error', 'AI is not configured. Please contact support. Superadmin can configure this in AI Studio (/superadmin/ai_studio.php).');
        redirect('/contractor/workorder_view.php?id=' . urlencode($woId));
        return;
    }

    [$systemPrompt, $userPrompt] = workorder_ai_prompt($workorder);
    $aiResult = ai_call_text(
        'workorder_extract',
        $systemPrompt,
        $userPrompt,
        [
            'expectJson' => true,
        ]
    );

    $now = now_kolkata()->format(DateTime::ATOM);
    $ai = [
        'lastRunAt' => $now,
        'rawText' => $aiResult['rawText'] ?? '',
        'parsedOk' => (bool)($aiResult['ok'] ?? false),
        'errors' => $aiResult['errors'] ?? [],
    ];

    $newObligations = $workorder['obligationsChecklist'] ?? [];
    $newDocs = $workorder['requiredDocs'] ?? [];
    $newTimeline = $workorder['timeline'] ?? [];
    $title = $workorder['title'] ?? '';
    $dept = $workorder['deptName'] ?? '';
    $location = $workorder['projectLocation'] ?? '';

    if (!empty($aiResult['json']) && is_array($aiResult['json'])) {
        $data = $aiResult['json'];
        $title = trim((string)($data['title'] ?? $title));
        $dept = trim((string)($data['deptName'] ?? $dept));
        $location = trim((string)($data['projectLocation'] ?? $location));

        $incomingObligations = is_array($data['obligationsChecklist'] ?? null) ? $data['obligationsChecklist'] : [];
        $newObligations = merge_workorder_obligations($newObligations, $incomingObligations);

        $newDocs = normalize_required_docs($data['requiredDocs'] ?? []);
        $newTimeline = normalize_timeline($data['timeline'] ?? []);
    }

    if (!$ai['parsedOk']) {
        $newObligations = [];
        $newDocs = [];
        $newTimeline = [];
    }

    $workorder['ai'] = $ai;
    $workorder['title'] = $title !== '' ? $title : ($workorder['title'] ?? 'Workorder');
    $workorder['deptName'] = $dept;
    $workorder['projectLocation'] = $location;
    $workorder['obligationsChecklist'] = $newObligations;
    $workorder['requiredDocs'] = $newDocs;
    $workorder['timeline'] = $newTimeline;
    $workorder['updatedAt'] = $now;

    save_workorder($workorder);

    workorder_log([
        'event' => 'ai_run',
        'yojId' => $yojId,
        'woId' => $woId,
        'parsedOk' => $ai['parsedOk'],
        'errorCount' => count($ai['errors']),
    ]);

    if ($ai['parsedOk']) {
        set_flash('success', 'AI extraction completed. Review and save any changes.');
    } else {
        set_flash('error', 'AI response could not be parsed. Fields reset to manual entry; please review the AI text below.');
    }

    redirect('/contractor/workorder_view.php?id=' . urlencode($woId));
});
