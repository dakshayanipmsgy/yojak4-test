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

    $offtdId = trim($_POST['id'] ?? '');
    $tender = $offtdId !== '' ? load_offline_tender($yojId, $offtdId) : null;
    if (!$tender || ($tender['yojId'] ?? '') !== $yojId) {
        render_error_page('Tender not found.');
        return;
    }

    $dates = [];
    $extracted = $tender['extracted'] ?? [];
    if (!empty($extracted['submissionDeadline'])) {
        $dates[] = [
            'title' => 'Submission deadline for ' . ($tender['title'] ?? $tender['id']),
            'dueAt' => $extracted['submissionDeadline'],
        ];
    }
    if (!empty($extracted['openingDate'])) {
        $dates[] = [
            'title' => 'Opening date for ' . ($tender['title'] ?? $tender['id']),
            'dueAt' => $extracted['openingDate'],
        ];
    }

    $created = 0;
    foreach ($dates as $date) {
        if (add_tender_reminder($yojId, $tender['id'], $date['title'], $date['dueAt'])) {
            $created++;
        }
    }

    if ($created > 0) {
        set_flash('success', $created . ' reminder(s) created.');
    } else {
        set_flash('error', 'No new reminders were created (possible duplicates or missing dates).');
    }

    redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
});
