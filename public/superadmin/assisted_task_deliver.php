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

    $form = assisted_tasks_build_form($_POST);
    $errors = assisted_tasks_validate_minimum($form);
    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/superadmin/assisted_task_edit.php?taskId=' . urlencode($taskId));
        return;
    }

    $form['checklist'] = assisted_tasks_minimum_checklist($form['checklist'] ?? []);
    $task['extractForm'] = $form;

    $yojId = $task['yojId'] ?? '';
    $offtdId = $task['offtdId'] ?? '';
    $tender = load_offline_tender($yojId, $offtdId);
    if (!$tender) {
        render_error_page('Tender not found for delivery.');
        return;
    }
    $contractor = load_contractor($yojId) ?? [];

    $packId = $task['packId'] ?? '';
    if ($packId === '') {
        $packId = assisted_tasks_ensure_pack($tender);
        $task['packId'] = $packId;
    }
    $pack = load_pack($yojId, $packId);
    if (!$pack) {
        render_error_page('Pack not found for delivery.');
        return;
    }

    $pack = assisted_tasks_apply_extract_to_pack($pack, $form, $tender);
    $pack = pack_generate_annexures($pack, $contractor, 'tender');
    save_pack($pack);

    $nowIso = now_kolkata()->format(DateTime::ATOM);
    $tender['assistedExtractV2'] = [
        'deliveredAt' => $nowIso,
        'extractForm' => $form,
    ];
    $tender['updatedAt'] = $nowIso;
    save_offline_tender($tender);

    $task['status'] = 'delivered';
    $task['deliveredAt'] = $nowIso;
    $task['assignedTo'] = $task['assignedTo'] ?? $actorLabel;
    assisted_tasks_append_history($task, $actorLabel, 'delivered');
    assisted_tasks_save_task($task);

    assisted_tasks_log([
        'event' => 'task_delivered',
        'taskId' => $taskId,
        'yojId' => $yojId,
        'offtdId' => $offtdId,
        'packId' => $packId,
        'actor' => $actorLabel,
    ]);

    set_flash('success', 'Assisted extraction delivered. Contractor can print immediately.');
    redirect('/superadmin/assisted_task_edit.php?taskId=' . urlencode($taskId));
});
