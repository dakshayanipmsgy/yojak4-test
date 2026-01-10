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
    ensure_assisted_extraction_env();

    $offtdId = trim($_POST['id'] ?? '');
    $tender = $offtdId !== '' ? load_offline_tender($yojId, $offtdId) : null;
    if (!$tender || ($tender['yojId'] ?? '') !== $yojId) {
        render_error_page('Tender not found.');
        return;
    }

    $active = assisted_active_task_for_tender($yojId, $offtdId);
    if ($active) {
        set_flash('info', 'An assisted extraction task is already queued or in progress for this tender.');
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        return;
    }

    $pdfPath = assisted_pick_tender_pdf($tender);
    if (!$pdfPath) {
        set_flash('error', 'Please upload a tender PDF before requesting assisted extraction.');
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        return;
    }

    $contractor = load_contractor($yojId) ?? ['yojId' => $yojId];
    $task = assisted_task_template($contractor, $tender);
    assisted_save_task($task);

    assisted_task_log([
        'event' => 'TASK_CREATED',
        'taskId' => $task['taskId'],
        'yojId' => $yojId,
        'offtdId' => $offtdId,
    ]);

    set_flash('success', 'Assisted extraction requested. We will notify you when it is delivered.');
    redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
});
