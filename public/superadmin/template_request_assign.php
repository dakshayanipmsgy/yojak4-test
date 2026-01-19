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
    $assignedTo = trim((string)($_POST['assignedTo'] ?? ''));
    if ($requestId === '') {
        render_error_page('Request ID missing.');
        return;
    }

    $updates = [
        'assignedTo' => $assignedTo !== '' ? $assignedTo : null,
    ];
    if ($assignedTo !== '') {
        $updates['status'] = 'in_progress';
    }

    $updated = update_template_request_status($requestId, $updates);
    if ($updated) {
        logEvent(DATA_PATH . '/logs/template_requests.log', [
            'event' => 'request_assigned',
            'requestId' => $requestId,
            'assignedTo' => $assignedTo,
            'actor' => $actor['username'] ?? ($actor['empId'] ?? 'staff'),
        ]);
        set_flash('success', 'Assignment updated.');
    }

    redirect('/superadmin/template_request_view.php?requestId=' . urlencode($requestId));
});
