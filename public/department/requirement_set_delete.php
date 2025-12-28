<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/department/requirement_sets.php');
    }
    require_csrf();
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $deptId = $user['deptId'] ?? '';
    ensure_department_env($deptId);
    require_department_permission($user, 'manage_requirements');

    $setId = trim($_POST['setId'] ?? '');
    if ($setId === '') {
        set_flash('error', 'Missing set id.');
        redirect('/department/requirement_sets.php');
    }

    $deleted = delete_requirement_set($deptId, $setId);
    if ($deleted) {
        append_department_audit($deptId, [
            'by' => $user['username'] ?? '',
            'action' => 'requirement_set_deleted',
            'meta' => ['setId' => $setId],
        ]);
        set_flash('success', 'Requirement set deleted.');
    } else {
        set_flash('error', 'Unable to delete requirement set.');
    }

    redirect('/department/requirement_sets.php');
});
