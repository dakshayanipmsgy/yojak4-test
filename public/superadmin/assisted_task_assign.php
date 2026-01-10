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
    $action = trim($_POST['action'] ?? 'claim');
    $task = $taskId !== '' ? assisted_load_task($taskId) : null;
    if (!$task) {
        render_error_page('Assisted task not found.');
        return;
    }

    if ($action !== 'claim') {
        set_flash('error', 'Invalid assignment action.');
        redirect('/superadmin/assisted_queue.php');
        return;
    }

    $identity = assisted_actor_identity($actor);
    if (($task['assignedTo']['userId'] ?? '') === '') {
        $task['assignedTo'] = $identity;
        if (($task['status'] ?? '') === 'queued') {
            $task['status'] = 'in_progress';
        }
        $task['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
        $task['history'][] = [
            'at' => $task['updatedAt'],
            'by' => $identity['name'] ?? ($identity['userId'] ?? ''),
            'event' => 'CLAIMED',
        ];
        assisted_save_task($task);
        assisted_task_log([
            'event' => 'TASK_CLAIMED',
            'taskId' => $task['taskId'],
            'actor' => $identity,
        ]);
    }

    redirect('/superadmin/assisted_task.php?taskId=' . urlencode($task['taskId']));
});
