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

    $title = trim($_POST['title'] ?? '');
    $dueAtInput = trim($_POST['dueAt'] ?? '');
    $packId = trim($_POST['packId'] ?? '');

    if ($title === '' || $dueAtInput === '') {
        set_flash('error', 'Title and due date are required.');
        redirect('/contractor/reminders.php');
        return;
    }

    $dueAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $dueAtInput, new DateTimeZone('Asia/Kolkata'));
    if (!$dueAt) {
        set_flash('error', 'Invalid due date.');
        redirect('/contractor/reminders.php');
        return;
    }

    if ($packId !== '') {
        $context = detect_pack_context($packId);
        $pack = load_pack($yojId, $packId, $context);
        if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
            set_flash('error', 'Pack not found.');
            redirect('/contractor/reminders.php');
            return;
        }
    }

    $entries = reminder_index_entries($yojId);
    $remId = generate_reminder_id();
    $reminder = [
        'remId' => $remId,
        'type' => 'custom',
        'title' => $title,
        'dueAt' => $dueAt->format(DateTime::ATOM),
        'packId' => $packId !== '' ? $packId : null,
        'status' => 'open',
        'createdAt' => now_kolkata()->format(DateTime::ATOM),
        'doneAt' => null,
    ];
    $entries[] = $reminder;
    save_reminder_index_entries($yojId, $entries);
    save_reminder_record($yojId, $reminder);

    logEvent(REMINDERS_LOG, [
        'event' => 'reminder_created',
        'yojId' => $yojId,
        'remId' => $remId,
        'type' => 'custom',
        'packId' => $packId !== '' ? $packId : null,
    ]);

    set_flash('success', 'Reminder created.');
    redirect('/contractor/reminders.php');
});
