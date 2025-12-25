<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/department/dak.php');
    }
    require_csrf();
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $deptId = $user['deptId'] ?? '';
    ensure_department_env($deptId);
    require_department_permission($user, 'manage_dak');

    $fileRef = trim($_POST['fileRef'] ?? '');
    $location = trim($_POST['location'] ?? '');
    if ($fileRef === '' || $location === '') {
        set_flash('error', 'File reference and location required.');
        redirect('/department/dak.php');
    }
    $item = add_dak_item($deptId, $fileRef, $location);
    append_department_audit($deptId, [
        'by' => $user['username'] ?? '',
        'action' => 'dak_created',
        'meta' => ['dakId' => $item['dakId']],
    ]);
    set_flash('success', 'DAK entry added.');
    redirect('/department/dak.php');
});
