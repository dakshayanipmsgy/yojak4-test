<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/bills.php');
    }
    require_csrf();

    $billId = trim($_POST['billId'] ?? '');
    if ($billId === '') {
        set_flash('error', 'Bill id missing.');
        redirect('/contractor/bills.php');
    }

    $bill = load_contractor_bill($user['yojId'], $billId);
    if (!$bill) {
        set_flash('error', 'Bill not found.');
        redirect('/contractor/bills.php');
    }

    $note = trim($_POST['note'] ?? '');
    $statusRef = trim($_POST['statusRef'] ?? '');
    $remindAtRaw = trim($_POST['remindAt'] ?? '');

    if ($note === '') {
        set_flash('error', 'Reminder note is required.');
        redirect('/contractor/bill_view.php?id=' . urlencode($billId));
    }

    $statuses = allowed_bill_statuses();
    if ($statusRef !== '' && !in_array($statusRef, $statuses, true)) {
        set_flash('error', 'Invalid status reference.');
        redirect('/contractor/bill_view.php?id=' . urlencode($billId));
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $remindAtRaw, new DateTimeZone('Asia/Kolkata'));
    if (!$dt) {
        set_flash('error', 'Invalid reminder time.');
        redirect('/contractor/bill_view.php?id=' . urlencode($billId));
    }

    $bill = add_bill_reminder($bill, $note, $dt->format(DateTime::ATOM), $statusRef);
    save_contractor_bill($user['yojId'], $bill);

    set_flash('success', 'Reminder added.');
    redirect('/contractor/bill_view.php?id=' . urlencode($billId));
});
