<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/assisted_tasks.php');
    }

    require_csrf();
    $actor = assisted_tasks_require_staff();
    $actorLabel = assisted_tasks_actor_label($actor);

    $taskId = trim($_POST['taskId'] ?? '');
    $task = $taskId !== '' ? assisted_tasks_load_task($taskId) : null;
    if (!$task) {
        render_error_page('Assisted task not found.');
        return;
    }

    $action = trim($_POST['action'] ?? 'save');
    $status = $task['status'] ?? 'requested';
    if (!in_array($status, ['delivered', 'closed'], true)) {
        $task['status'] = 'in_progress';
    }

    if ($action === 'assign') {
        $task['assignedTo'] = $actorLabel;
        assisted_tasks_append_history($task, $actorLabel, 'assigned');
        assisted_tasks_save_task($task);
        assisted_tasks_log([
            'event' => 'task_assigned',
            'taskId' => $taskId,
            'assignedTo' => $actorLabel,
        ]);
        set_flash('success', 'Task assigned to you.');
        redirect('/superadmin/assisted_tasks.php');
        return;
    }

    $form = assisted_tasks_build_form($_POST);
    $task['extractForm'] = $form;
    if (empty($task['assignedTo'])) {
        $task['assignedTo'] = $actorLabel;
    }
    if ($action === 'needs_info') {
        $task['needsInfo'] = true;
        $task['needsInfoAt'] = now_kolkata()->format(DateTime::ATOM);
        assisted_tasks_append_history($task, $actorLabel, 'needs_info');
    } else {
        assisted_tasks_append_history($task, $actorLabel, 'saved');
    }
    assisted_tasks_save_task($task);
    assisted_tasks_log([
        'event' => 'task_saved',
        'taskId' => $taskId,
        'actor' => $actorLabel,
        'needsInfo' => !empty($task['needsInfo']),
    ]);

    set_flash('success', $action === 'needs_info' ? 'Marked as needs contractor info.' : 'Draft saved.');
    redirect('/superadmin/assisted_task_edit.php?taskId=' . urlencode($taskId));
});
