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

    $startedAt = now_kolkata()->format(DateTime::ATOM);
    $decider = $actor['username'] ?? ($actor['empId'] ?? 'system');
    $now = $startedAt;

    if (($request['userType'] ?? 'dept_admin') === 'contractor') {
        $contractor = null;
        if (!empty($request['yojId'])) {
            $contractor = load_contractor($request['yojId']);
        }
        if (!$contractor) {
            $contractor = find_contractor_by_mobile($request['mobile'] ?? '');
        }
        if (!$contractor) {
            set_flash('error', 'Contractor not found.');
            redirect('/superadmin/reset_requests.php');
        }
        $tempPassword = generate_temp_password(12);
        $contractor['passwordHash'] = password_hash($tempPassword, PASSWORD_DEFAULT);
        $contractor['mustResetPassword'] = true;
        $contractor['lastPasswordResetAt'] = $now;
        $contractor['passwordResetBy'] = 'superadmin';
        save_contractor($contractor);

        $request['status'] = 'approved';
        $request['decidedAt'] = $now;
        $request['decidedBy'] = $decider;
        $request['updatedAt'] = $now;
        $request['tempPasswordHash'] = $contractor['passwordHash'];
        $request['tempPasswordIssuedAt'] = $now;
        $request['tempPasswordDelivery'] = 'show_once';
        save_password_reset_request($request);

        logEvent(DATA_PATH . '/logs/superadmin.log', [
            'event' => 'contractor_reset_approved',
            'requestId' => $requestId,
            'yojId' => $contractor['yojId'] ?? null,
            'decidedBy' => $decider,
        ]);
        logEvent(DATA_PATH . '/logs/auth.log', [
            'event' => 'contractor_temp_password_issued',
            'requestId' => $requestId,
            'mobile' => $contractor['mobile'] ?? null,
            'decidedBy' => $decider,
        ]);
        logEvent(DATA_PATH . '/logs/reset.log', [
            'event' => 'contractor_reset_approved',
            'requestId' => $requestId,
            'mobile' => $contractor['mobile'] ?? null,
            'decidedBy' => $decider,
        ]);
        $_SESSION['temp_password_once'] = [
            'requestId' => $requestId,
            'password' => $tempPassword,
            'mobile' => $contractor['mobile'] ?? '',
        ];
        set_flash('success', 'Reset approved. Temporary password generated.');
        redirect('/superadmin/reset_requests.php');
    }

    $deptId = $request['deptId'] ?? '';
    $attemptLog = [
        'at' => $startedAt,
        'event' => 'DEPT_ADMIN_RESET_APPROVE',
        'requestId' => $requestId,
        'deptId' => $deptId,
        'decidedBy' => $decider,
    ];
    $fail = function (string $message, string $reasonCode) use (&$attemptLog) {
        $attempt = $attemptLog;
        $attempt['result'] = 'fail';
        $attempt['reasonCode'] = $reasonCode;
        logEvent(DATA_PATH . '/logs/superadmin.log', $attempt);
        set_flash('error', $message);
        redirect('/superadmin/reset_requests.php');
    };

    $department = $deptId ? load_department($deptId) : null;
    if (!$department) {
        $fail('Department not found or not configured.', 'department_missing');
    }

    $resolvedAdminUserId = strtolower($department['activeAdminUserId'] ?? '');
    $attemptLog['resolvedAdminUserId'] = $resolvedAdminUserId ?: null;

    if (!$resolvedAdminUserId) {
        $fail('Active admin not configured for this department.', 'active_admin_missing');
    }

    $parsed = parse_department_login_identifier($resolvedAdminUserId);
    $expectedPattern = '/^[a-z0-9]{3,12}\.admin\.' . preg_quote($deptId, '/') . '$/';
    if (!$parsed || !preg_match($expectedPattern, $resolvedAdminUserId) || ($parsed['roleId'] ?? '') !== 'admin') {
        $fail('Active admin not configured for this department.', 'active_admin_invalid');
    }

    $adminPath = department_user_path($deptId, $resolvedAdminUserId, false);
    if (!file_exists($adminPath)) {
        $fail('Active admin user record missing; please recreate admin or run health check.', 'admin_file_missing');
    }

    $record = load_active_department_user($resolvedAdminUserId);
    if (!$record || ($record['type'] ?? '') !== 'department' || ($record['roleId'] ?? '') !== 'admin') {
        $fail('Active admin user record missing; please recreate admin or run health check.', 'admin_record_invalid');
    }
    if (($record['status'] ?? '') !== 'active') {
        $fail('Admin account inactive; cannot reset.', 'admin_inactive');
    }

    $tempPassword = generate_temp_password(12);

    try {
        ensure_department_env($deptId);
        update_department_user_password($deptId, $resolvedAdminUserId, $tempPassword, true, 'superadmin');
    } catch (Throwable $e) {
        $fail('Unable to reset password: ' . $e->getMessage(), 'password_update_failed');
    }
    $updatedRecord = load_active_department_user($resolvedAdminUserId);
    if (!$updatedRecord) {
        $fail('Active admin user record missing; please recreate admin or run health check.', 'admin_update_verify_failed');
    }

    $decidedAt = now_kolkata()->format(DateTime::ATOM);
    $request['status'] = 'approved';
    $request['decidedAt'] = $decidedAt;
    $request['decidedBy'] = $decider;
    $request['updatedAt'] = $decidedAt;
    $request['tempPasswordHash'] = $updatedRecord['passwordHash'] ?? null;
    $request['tempPasswordIssuedAt'] = $decidedAt;
    $request['tempPasswordDelivery'] = 'show_once';
    $request['resolvedAdminUserId'] = $resolvedAdminUserId;
    save_password_reset_request($request);

    append_department_audit($deptId, [
        'by' => $decider,
        'action' => 'password_reset_approved',
        'meta' => ['requestId' => $requestId, 'fullUserId' => $resolvedAdminUserId],
    ]);

    logEvent(DATA_PATH . '/logs/reset.log', [
        'event' => 'password_reset_approved',
        'deptId' => $deptId,
        'fullUserId' => $resolvedAdminUserId,
        'requestId' => $requestId,
        'decidedBy' => $decider,
    ]);
    logEvent(DATA_PATH . '/logs/auth.log', [
        'event' => 'dept_admin_temp_password_issued',
        'deptId' => $deptId,
        'adminUserId' => $resolvedAdminUserId,
        'requestId' => $requestId,
        'issuedBy' => $decider,
    ]);
    logEvent(DATA_PATH . '/logs/superadmin.log', [
        'event' => 'dept_admin_reset_approved',
        'deptId' => $deptId,
        'adminUserId' => $resolvedAdminUserId,
        'requestId' => $requestId,
        'decidedBy' => $decider,
    ]);
    logEvent(DATA_PATH . '/logs/superadmin.log', [
        'at' => $decidedAt,
        'event' => 'DEPT_ADMIN_RESET_APPROVE',
        'requestId' => $requestId,
        'deptId' => $deptId,
        'resolvedAdminUserId' => $resolvedAdminUserId,
        'result' => 'success',
        'reasonCode' => 'ok',
        'decidedBy' => $decider,
    ]);

    $_SESSION['temp_password_once'] = [
        'requestId' => $requestId,
        'password' => $tempPassword,
        'user' => $resolvedAdminUserId,
    ];
    set_flash('success', 'Reset approved. Temporary password generated and shown once.');
    redirect('/superadmin/reset_requests.php');
});
