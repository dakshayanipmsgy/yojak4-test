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

    if (!isset($_FILES['attachment'])) {
        set_flash('error', 'No file uploaded.');
        redirect('/contractor/bill_view.php?id=' . urlencode($billId));
    }

    $file = $_FILES['attachment'];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        set_flash('error', 'Upload failed.');
        redirect('/contractor/bill_view.php?id=' . urlencode($billId));
    }

    try {
        $bill = add_bill_attachment($user['yojId'], $bill, $file);
        save_contractor_bill($user['yojId'], $bill);
        set_flash('success', 'Attachment uploaded.');
    } catch (Throwable $e) {
        logEvent(bills_log_path(), [
            'event' => 'bill_upload_failed',
            'yojId' => $user['yojId'],
            'billId' => $billId,
            'message' => $e->getMessage(),
        ]);
        set_flash('error', 'Unable to upload attachment.');
    }

    redirect('/contractor/bill_view.php?id=' . urlencode($billId));
});
