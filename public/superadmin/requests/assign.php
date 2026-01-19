<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/requests.php');
    }

    require_csrf();
    require_superadmin_or_permission('requests_manage');
    $type = ($_POST['type'] ?? 'template') === 'pack' ? 'pack' : 'template';
    $reqId = trim((string)($_POST['id'] ?? ''));
    $staffId = trim((string)($_POST['staffId'] ?? ''));
    if ($reqId === '') {
        render_error_page('Missing request id.');
        return;
    }
    $request = request_load($type, $reqId);
    if (!$request) {
        render_error_page('Request not found.');
        return;
    }

    $request['status'] = 'assigned';
    $request['assignedTo'] = ['staffId' => $staffId];
    $request['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    request_save($type, $request);
    logEvent(DATA_PATH . '/logs/requests.log', [
        'event' => 'request_assigned',
        'type' => $type,
        'requestId' => $reqId,
        'assignedTo' => $staffId,
    ]);

    set_flash('success', 'Request assigned.');
    redirect('/superadmin/requests.php?type=' . ($type === 'pack' ? 'packs' : 'templates'));
});
