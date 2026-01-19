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
    $status = trim((string)($_POST['status'] ?? 'in_progress'));
    if (!in_array($status, ['in_progress', 'delivered', 'rejected'], true)) {
        $status = 'in_progress';
    }
    if ($reqId === '') {
        render_error_page('Missing request id.');
        return;
    }
    $request = request_load($type, $reqId);
    if (!$request) {
        render_error_page('Request not found.');
        return;
    }
    $linkedId = trim((string)($_POST['linkedId'] ?? ''));

    $request['status'] = $status;
    if ($linkedId !== '') {
        $request['linkedId'] = $linkedId;
    }
    $request['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    request_save($type, $request);
    logEvent(DATA_PATH . '/logs/requests.log', [
        'event' => 'request_resolved',
        'type' => $type,
        'requestId' => $reqId,
        'status' => $status,
        'linkedId' => $linkedId,
    ]);

    set_flash('success', 'Request updated.');
    redirect('/superadmin/requests.php?type=' . ($type === 'pack' ? 'packs' : 'templates'));
});
