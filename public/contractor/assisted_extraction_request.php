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
    ensure_assisted_tasks_env();

    $offtdId = trim($_POST['id'] ?? '');
    if ($offtdId === '') {
        set_flash('error', 'Missing tender id.');
        redirect('/contractor/offline_tenders.php');
        return;
    }

    $tender = load_offline_tender($yojId, $offtdId);
    if (!$tender || ($tender['yojId'] ?? '') !== $yojId) {
        render_error_page('Tender not found.');
        return;
    }

    $existing = assisted_tasks_active_for_tender($yojId, $offtdId);
    if ($existing) {
        set_flash('error', 'Assisted extraction is already requested for this tender.');
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        return;
    }

    $task = assisted_tasks_create($yojId, $offtdId, [
        'type' => 'contractor',
        'yojId' => $yojId,
    ]);

    set_flash('success', 'Assisted extraction requested. Our team will update you when delivered.');
    assisted_tasks_log([
        'event' => 'contractor_request',
        'taskId' => $task['taskId'],
        'yojId' => $yojId,
        'offtdId' => $offtdId,
    ]);

    redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
});
