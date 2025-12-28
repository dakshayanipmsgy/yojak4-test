<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('reset_approvals');
    if (($actor['type'] ?? '') === 'superadmin' && !empty($actor['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        render_error_page('Unsupported request.');
        return;
    }
    require_csrf();

    $requestId = trim($_POST['requestId'] ?? '');
    $request = $requestId ? find_password_reset_request($requestId) : null;
    if (!$request) {
        set_flash('error', 'Request not found.');
        redirect('/superadmin/reset_requests.php');
    }
    if (($request['status'] ?? '') !== 'pending') {
        set_flash('error', 'Request already processed.');
        redirect('/superadmin/reset_requests.php');
    }

    $decider = $actor['username'] ?? ($actor['empId'] ?? 'system');
    update_password_reset_status($requestId, 'rejected', $decider);
    logEvent(DATA_PATH . '/logs/reset.log', [
        'event' => 'password_reset_rejected',
        'deptId' => $request['deptId'] ?? '',
        'fullUserId' => $request['fullUserId'] ?? '',
        'requestId' => $requestId,
        'decidedBy' => $decider,
    ]);
    logEvent(DATA_PATH . '/logs/superadmin.log', [
        'event' => 'password_reset_rejected',
        'userType' => $request['userType'] ?? 'dept_admin',
        'deptId' => $request['deptId'] ?? null,
        'fullUserId' => $request['fullUserId'] ?? null,
        'requestId' => $requestId,
        'decidedBy' => $decider,
    ]);

    set_flash('success', 'Reset request rejected.');
    redirect('/superadmin/reset_requests.php');
});
