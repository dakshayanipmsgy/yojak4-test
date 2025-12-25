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

    $action = $_POST['action'] ?? 'status';

    if ($action === 'status') {
        $newStatus = trim($_POST['status'] ?? '');
        $statuses = allowed_bill_statuses();
        if (!in_array($newStatus, $statuses, true)) {
            set_flash('error', 'Invalid status.');
            redirect('/contractor/bill_view.php?id=' . urlencode($billId));
        }

        if (!validate_status_transition($bill['status'], $newStatus)) {
            set_flash('error', 'Status change not allowed.');
            redirect('/contractor/bill_view.php?id=' . urlencode($billId));
        }

        $isRollback = array_search($newStatus, $statuses, true) < array_search($bill['status'], $statuses, true);
        if ($isRollback && ($_POST['confirmRollback'] ?? '') !== '1') {
            set_flash('error', 'Confirm rollback before updating status.');
            redirect('/contractor/bill_view.php?id=' . urlencode($billId));
        }

        $fromStatus = $bill['status'];
        $bill = apply_status_change($bill, $newStatus, 'contractor', $isRollback);
        save_contractor_bill($user['yojId'], $bill);

        logEvent(bills_log_path(), [
            'event' => 'bill_status_changed',
            'yojId' => $user['yojId'],
            'billId' => $billId,
            'from' => $fromStatus,
            'to' => $newStatus,
            'rollback' => $isRollback,
        ]);

        set_flash('success', 'Status updated.');
        redirect('/contractor/bill_view.php?id=' . urlencode($billId));
    }

    if ($action === 'metadata') {
        $title = trim($_POST['title'] ?? '');
        $workorderRef = trim($_POST['workorderRef'] ?? '');
        $amountText = trim($_POST['amountText'] ?? '');

        if ($title === '') {
            set_flash('error', 'Title is required.');
            redirect('/contractor/bill_view.php?id=' . urlencode($billId));
        }
        if (mb_strlen($amountText) > 30) {
            set_flash('error', 'Amount text must be 30 characters or less.');
            redirect('/contractor/bill_view.php?id=' . urlencode($billId));
        }

        $bill['title'] = $title;
        $bill['workorderRef'] = $workorderRef;
        $bill['amountText'] = $amountText;
        $bill['updatedAt'] = now_kolkata()->format(DateTime::ATOM);

        save_contractor_bill($user['yojId'], $bill);
        set_flash('success', 'Details updated.');
        redirect('/contractor/bill_view.php?id=' . urlencode($billId));
    }

    set_flash('error', 'Unknown action.');
    redirect('/contractor/bill_view.php?id=' . urlencode($billId));
});
