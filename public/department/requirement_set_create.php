<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/department/requirements.php');
    }
    require_csrf();
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $deptId = $user['deptId'] ?? '';
    ensure_department_env($deptId);
    require_department_permission($user, 'manage_requirements');

    $title = trim($_POST['title'] ?? '');
    $items = array_filter(array_map('trim', preg_split('/\r?\n/', (string)($_POST['items'] ?? '')) ?: []));
    if ($title === '') {
        set_flash('error', 'Title required.');
        redirect('/department/requirements.php');
    }
    $set = create_requirement_set($deptId, $title, $items);
    append_department_audit($deptId, [
        'by' => $user['username'] ?? '',
        'action' => 'requirement_set_created',
        'meta' => ['setId' => $set['setId']],
    ]);
    set_flash('success', 'Requirement set created.');
    redirect('/department/requirements.php');
});
