<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/reminders.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_reminders_env($yojId);

    $remId = trim($_POST['remId'] ?? '');
    if ($remId === '') {
        set_flash('error', 'Reminder not found.');
        redirect('/contractor/reminders.php');
        return;
    }

    $entries = reminder_index_entries($yojId);
    $found = false;
    foreach ($entries as $idx => $entry) {
        $entryId = $entry['remId'] ?? ($entry['reminderId'] ?? '');
        if ($entryId !== $remId) {
            continue;
        }
        $entry = normalize_reminder_entry($entry);
        $entry['status'] = 'done';
        $entry['doneAt'] = now_kolkata()->format(DateTime::ATOM);
        $entries[$idx] = $entry;
        save_reminder_index_entries($yojId, $entries);
        if (!empty($entry['remId'])) {
            save_reminder_record($yojId, $entry);
        }
        logEvent(REMINDERS_LOG, [
            'event' => 'reminder_done',
            'yojId' => $yojId,
            'remId' => $remId,
            'type' => $entry['type'] ?? 'unknown',
        ]);
        $found = true;
        break;
    }

    if (!$found) {
        set_flash('error', 'Reminder not found.');
        redirect('/contractor/reminders.php');
        return;
    }

    set_flash('success', 'Reminder marked as done.');
    redirect('/contractor/reminders.php');
});
