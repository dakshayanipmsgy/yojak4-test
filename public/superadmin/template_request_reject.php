<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/template_requests.php');
    }
    require_csrf();

    $actor = require_superadmin_or_permission('template_manager');
    $requestId = trim((string)($_POST['requestId'] ?? ''));
    if ($requestId === '') {
        render_error_page('Request ID missing.');
        return;
    }

    $notes = trim((string)($_POST['rejectionNotes'] ?? ''));
    $updated = update_template_request_status($requestId, [
        'status' => 'rejected',
        'rejectionNotes' => $notes,
    ]);

    if ($updated) {
        logEvent(DATA_PATH . '/logs/template_requests.log', [
            'event' => 'request_rejected',
            'requestId' => $requestId,
            'notes' => $notes,
            'actor' => $actor['username'] ?? ($actor['empId'] ?? 'staff'),
        ]);
        set_flash('success', 'Request rejected.');
    }

    redirect('/superadmin/template_request_view.php?requestId=' . urlencode($requestId));
});
