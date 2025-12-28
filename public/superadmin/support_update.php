<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/support_dashboard.php');
    }
    require_role('superadmin');
    require_csrf();

    $ticketId = $_POST['ticketId'] ?? '';
    $status = $_POST['status'] ?? '';
    $note = $_POST['note'] ?? null;

    try {
        support_update_ticket($ticketId, $status, $note);
        set_flash('success', 'Ticket updated');
    } catch (Throwable $e) {
        set_flash('error', 'Update failed: ' . $e->getMessage());
    }

    redirect('/superadmin/support_ticket.php?ticketId=' . urlencode($ticketId));
});
