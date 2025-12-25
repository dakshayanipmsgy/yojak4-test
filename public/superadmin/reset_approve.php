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

    $deptId = $request['deptId'] ?? '';
    $fullUserId = $request['fullUserId'] ?? '';
    $tempPassword = 'Temp' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)) . '!';

    try {
        ensure_department_env($deptId);
        update_department_user_password($deptId, $fullUserId, $tempPassword, true);
    } catch (Throwable $e) {
        set_flash('error', 'Unable to reset password: ' . $e->getMessage());
        redirect('/superadmin/reset_requests.php');
    }

    update_password_reset_status($requestId, 'approved', $actor['username'] ?? ($actor['empId'] ?? 'system'));
    append_department_audit($deptId, [
        'by' => $actor['username'] ?? ($actor['empId'] ?? 'system'),
        'action' => 'password_reset_approved',
        'meta' => ['requestId' => $requestId, 'fullUserId' => $fullUserId],
    ]);

    logEvent(DATA_PATH . '/logs/reset.log', [
        'event' => 'password_reset_approved',
        'deptId' => $deptId,
        'fullUserId' => $fullUserId,
        'requestId' => $requestId,
        'decidedBy' => $actor['username'] ?? ($actor['empId'] ?? 'system'),
    ]);

    set_flash('success', 'Reset approved. Temporary password: ' . $tempPassword . ' (force change on next login).');
    redirect('/superadmin/reset_requests.php');
});
