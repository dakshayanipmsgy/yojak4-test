<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/assisted_queue.php');
    }

    require_csrf();
    $actor = assisted_require_staff_access();
    $taskId = trim($_POST['taskId'] ?? '');
    $task = $taskId !== '' ? assisted_load_task($taskId) : null;
    if (!$task) {
        render_error_page('Assisted task not found.');
        return;
    }

    $identity = assisted_actor_identity($actor);
    $nowIso = now_kolkata()->format(DateTime::ATOM);
    $aiState = $task['ai'] ?? [];

    $configResult = ai_get_config(true);
    $config = $configResult['config'] ?? [];
    if (!$configResult['ok'] || empty($config['hasApiKey'])) {
        $aiState['lastRunAt'] = $nowIso;
        $aiState['status'] = 'failed';
        $aiState['error'] = 'AI is not configured.';
        $task['ai'] = $aiState;
        assisted_save_task($task);
        set_flash('error', 'AI is not configured. Please contact a superadmin to set up AI Studio.');
        redirect('/superadmin/assisted_task.php?taskId=' . urlencode($task['taskId']));
        return;
    }

    [$systemPrompt, $userPrompt] = assisted_task_ai_prompt($task);
    $aiResult = ai_call_text(
        'assisted_task_extract',
        $systemPrompt,
        $userPrompt,
        ['expectJson' => true]
    );

    $aiState['lastRunAt'] = $nowIso;
    $aiState['provider'] = $config['provider'] ?? '';
    $rawEnvelope = $aiResult['rawEnvelope'] ?? [];
    if (!is_array($rawEnvelope)) {
        $rawEnvelope = [];
    }
    $aiState['model'] = $aiResult['modelUsed'] ?? ($rawEnvelope['model'] ?? null);
    $aiState['requestId'] = $aiResult['requestId'] ?? null;
    $aiState['status'] = !empty($aiResult['parsedOk']) ? 'ok' : 'failed';
    $aiState['error'] = $aiResult['providerError'] ?? (implode('; ', $aiResult['errors'] ?? []) ?: null);

    if (!empty($aiResult['json']) && is_array($aiResult['json'])) {
        $task['form'] = assisted_task_apply_ai_payload($task['form'] ?? [], $aiResult['json']);
    }

    $task['ai'] = $aiState;
    $task['updatedAt'] = $nowIso;
    $task['history'][] = [
        'at' => $nowIso,
        'by' => $identity['name'] ?? ($identity['userId'] ?? ''),
        'event' => 'AI_AUTOFILL',
    ];
    assisted_save_task($task);

    ai_log([
        'event' => 'ASSISTED_TASK_AI',
        'at' => $nowIso,
        'provider' => $aiState['provider'] ?? '',
        'model' => $aiState['model'] ?? '',
        'requestId' => $aiState['requestId'] ?? null,
        'parsed' => $aiState['status'] === 'ok',
        'taskId' => $task['taskId'],
        'errorsCount' => count($aiResult['errors'] ?? []),
    ]);

    assisted_task_log([
        'event' => 'AI_AUTOFILL',
        'taskId' => $task['taskId'],
        'actor' => $identity,
        'status' => $aiState['status'] ?? 'failed',
    ]);

    if (!empty($aiResult['parsedOk'])) {
        set_flash('success', 'AI auto-fill completed. Please review the form.');
    } else {
        set_flash('error', 'AI failed to parse. You can continue editing manually.');
    }

    redirect('/superadmin/assisted_task.php?taskId=' . urlencode($task['taskId']));
});
