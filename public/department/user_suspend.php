<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/department/users.php');
    }
    require_csrf();
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    require_department_permission($user, 'manage_users');

    $fullUserId = strtolower(trim($_POST['fullUserId'] ?? ''));
    if ($fullUserId === '') {
        set_flash('error', 'User ID missing.');
        redirect('/department/users.php');
    }
    $deptId = $user['deptId'] ?? '';
    if (suspend_department_user($deptId, $fullUserId)) {
        append_department_audit($deptId, [
            'by' => $user['username'] ?? '',
            'action' => 'user_suspended',
            'meta' => ['userId' => $fullUserId],
        ]);
        set_flash('success', 'User suspended.');
    } else {
        set_flash('error', 'Unable to suspend user.');
    }
    redirect('/department/users.php');
});
