<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/template_requests.php');
    }

    require_csrf();
    require_staff_actor();

    $requestId = trim((string)($_POST['id'] ?? ''));
    $status = trim((string)($_POST['status'] ?? ''));
    $allowed = ['pending', 'in_progress', 'delivered', 'rejected'];
    if ($requestId === '' || !in_array($status, $allowed, true)) {
        render_error_page('Invalid request.');
        return;
    }

    $request = load_template_request($requestId);
    if (!$request) {
        render_error_page('Request not found.');
        return;
    }

    $request['status'] = $status;
    save_template_request($request);

    logEvent(DATA_PATH . '/logs/template_requests.log', [
        'event' => 'request_status_updated',
        'requestId' => $requestId,
        'status' => $status,
    ]);

    set_flash('success', 'Status updated.');
    redirect('/superadmin/template_request_view.php?id=' . urlencode($requestId));
});
