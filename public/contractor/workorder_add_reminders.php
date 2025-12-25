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

    $dates = [];
    foreach ($workorder['obligationsChecklist'] ?? [] as $item) {
        if (!empty($item['dueAt'])) {
            $dates[] = [
                'title' => 'Obligation due: ' . ($item['title'] ?? $workorder['woId']),
                'dueAt' => $item['dueAt'],
            ];
        }
    }
    foreach ($workorder['timeline'] ?? [] as $entry) {
        if (!empty($entry['dueAt'])) {
            $dates[] = [
                'title' => 'Timeline: ' . ($entry['milestone'] ?? $workorder['woId']),
                'dueAt' => $entry['dueAt'],
            ];
        }
    }

    $created = 0;
    foreach ($dates as $date) {
        if (add_workorder_reminder($yojId, $workorder['woId'], $date['title'], $date['dueAt'])) {
            $created++;
        }
    }

    if ($created > 0) {
        set_flash('success', $created . ' reminder(s) created.');
    } else {
        set_flash('error', 'No new reminders were created (possible duplicates or missing dates).');
    }

    workorder_log([
        'event' => 'reminders_created',
        'yojId' => $yojId,
        'woId' => $woId,
        'count' => $created,
    ]);

    redirect('/contractor/workorder_view.php?id=' . urlencode($woId));
});
