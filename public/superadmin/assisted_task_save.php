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
    $isEmployee = ($actor['type'] ?? '') === 'employee';
    $assignedTo = $task['assignedTo']['userId'] ?? '';
    if ($isEmployee && $assignedTo !== '' && $assignedTo !== ($actor['empId'] ?? '')) {
        render_error_page('You are not assigned to this task.');
        return;
    }

    if ($assignedTo === '' && $isEmployee) {
        $task['assignedTo'] = $identity;
        $task['status'] = 'in_progress';
    }

    $task['form'] = assisted_task_form_from_post($_POST, $task['form'] ?? []);
    $task['updatedAt'] = now_kolkata()->format(DateTime::ATOM);

    if (($task['status'] ?? '') === 'queued') {
        $task['status'] = 'in_progress';
    }
    $task['history'][] = [
        'at' => $task['updatedAt'],
        'by' => $identity['name'] ?? ($identity['userId'] ?? ''),
        'event' => 'SAVED',
    ];

    assisted_save_task($task);

    assisted_task_log([
        'event' => 'TASK_SAVED',
        'taskId' => $task['taskId'],
        'actor' => $identity,
    ]);

    set_flash('success', 'Task draft saved.');
    redirect('/superadmin/assisted_task.php?taskId=' . urlencode($task['taskId']));
});
