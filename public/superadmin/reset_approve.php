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

    $now = now_kolkata()->format(DateTime::ATOM);
    $decider = $actor['username'] ?? ($actor['empId'] ?? 'system');

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

    if (($request['userType'] ?? 'dept_admin') !== 'dept_admin') {
        set_flash('error', 'Unsupported reset request type.');
        redirect('/superadmin/reset_requests.php');
    }

    $deptId = $request['deptId'] ?? '';
    $resolved = resolve_department_admin_account($deptId);
    $logContext = [
        'at' => $now,
        'event' => 'DEPT_ADMIN_RESET_APPROVE',
        'deptId' => $deptId,
        'requestId' => $requestId,
        'decidedBy' => $decider,
        'resolvedAdminUserId' => $resolved['activeAdminUserId'] ?? null,
        'result' => 'failed',
        'reasonCode' => $resolved['reason'] ?? null,
    ];

    if (!$resolved['ok']) {
        $message = 'Invalid department admin account.';
        $reason = $resolved['reason'] ?? '';
        if (in_array($reason, ['department_missing', 'active_admin_missing', 'active_admin_invalid'], true)) {
            $message = 'Department admin not configured for this department.';
        } elseif (in_array($reason, ['admin_record_missing', 'admin_record_mismatch'], true)) {
            $message = 'Admin user file not found; department setup inconsistent.';
        }
        logEvent(DATA_PATH . '/logs/superadmin.log', $logContext);
        set_flash('error', $message);
        redirect('/superadmin/reset_requests.php');
    }

    $tempPassword = generate_temp_password(12);

    try {
        ensure_department_env($deptId);
        update_department_user_password($deptId, $resolved['activeAdminUserId'], $tempPassword, true, $decider);
    } catch (Throwable $e) {
        set_flash('error', 'Unable to reset password: ' . $e->getMessage());
        redirect('/superadmin/reset_requests.php');
    }
    $updatedRecord = load_active_department_user($resolved['activeAdminUserId']);

    $now = now_kolkata()->format(DateTime::ATOM);
    $request['status'] = 'approved';
    $request['decidedAt'] = $now;
    $request['decidedBy'] = $decider;
    $request['updatedAt'] = $now;
    $request['tempPasswordHash'] = $updatedRecord['passwordHash'] ?? null;
    $request['tempPasswordIssuedAt'] = $now;
    $request['tempPasswordDelivery'] = 'show_once';
    $request['resolvedAdminUserId'] = $resolved['activeAdminUserId'];
    save_password_reset_request($request);

    append_department_audit($deptId, [
        'by' => $decider,
        'action' => 'password_reset_approved',
        'meta' => ['requestId' => $requestId, 'fullUserId' => $resolved['activeAdminUserId']],
    ]);

    logEvent(DATA_PATH . '/logs/reset.log', [
        'event' => 'password_reset_approved',
        'deptId' => $deptId,
        'fullUserId' => $resolved['activeAdminUserId'],
        'requestId' => $requestId,
        'decidedBy' => $decider,
    ]);
    logEvent(DATA_PATH . '/logs/auth.log', [
        'event' => 'dept_admin_temp_password_issued',
        'deptId' => $deptId,
        'adminUserId' => $resolved['activeAdminUserId'],
        'requestId' => $requestId,
        'issuedBy' => $decider,
    ]);
    logEvent(DATA_PATH . '/logs/superadmin.log', [
        'event' => 'dept_admin_reset_approved',
        'deptId' => $deptId,
        'adminUserId' => $resolved['activeAdminUserId'],
        'requestId' => $requestId,
        'decidedBy' => $decider,
    ]);
    logEvent(DATA_PATH . '/logs/superadmin.log', array_merge($logContext, [
        'result' => 'approved',
        'reasonCode' => 'ok',
    ]));

    $_SESSION['temp_password_once'] = [
        'requestId' => $requestId,
        'password' => $tempPassword,
        'user' => $resolved['activeAdminUserId'],
    ];
    set_flash('success', 'Reset approved. Temporary password generated and shown once.');
    redirect('/superadmin/reset_requests.php');
});
