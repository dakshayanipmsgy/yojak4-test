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

    if (!assisted_task_can_deliver($task)) {
        set_flash('error', 'Add a deadline or at least 5 checklist items before delivery.');
        redirect('/superadmin/assisted_task.php?taskId=' . urlencode($task['taskId']));
        return;
    }

    $nowIso = now_kolkata()->format(DateTime::ATOM);
    $task['status'] = 'delivered';
    $task['delivered'] = [
        'deliveredAt' => $nowIso,
        'deliveredBy' => $identity['name'] ?? ($identity['userId'] ?? ''),
        'snapshotPath' => assisted_task_snapshot_path($task['taskId']),
    ];
    $task['updatedAt'] = $nowIso;
    $task['history'][] = [
        'at' => $nowIso,
        'by' => $identity['name'] ?? ($identity['userId'] ?? ''),
        'event' => 'DELIVERED',
    ];

    $snapshot = [
        'taskId' => $task['taskId'],
        'deliveredAt' => $nowIso,
        'contractor' => $task['contractor'] ?? [],
        'tender' => $task['tender'] ?? [],
        'form' => $task['form'] ?? assisted_task_form_defaults(),
    ];
    writeJsonAtomic(assisted_task_snapshot_path($task['taskId']), $snapshot);

    assisted_save_task($task);

    assisted_task_log([
        'event' => 'TASK_DELIVERED',
        'taskId' => $task['taskId'],
        'actor' => $identity,
    ]);

    set_flash('success', 'Task delivered to contractor.');
    redirect('/superadmin/assisted_task.php?taskId=' . urlencode($task['taskId']));
});
