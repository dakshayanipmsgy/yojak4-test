<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_csrf();
    require_role('superadmin');

    $requestId = trim((string)($_POST['requestId'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'new'));
    $allowed = ['new', 'in_progress', 'delivered', 'rejected'];
    if (!in_array($status, $allowed, true)) {
        $status = 'new';
    }

    if ($requestId === '') {
        render_error_page('Request not found.');
        return;
    }

    $request = load_request($requestId);
    if (!$request) {
        render_error_page('Request not found.');
        return;
    }

    $request['status'] = $status;
    save_request($request);

    logEvent(DATA_PATH . '/logs/requests.log', [
        'event' => 'request_status_updated',
        'requestId' => $requestId,
        'status' => $status,
    ]);

    set_flash('success', 'Request updated.');
    redirect('/superadmin/request_view.php?id=' . urlencode($requestId));
});
