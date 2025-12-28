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

    $setId = trim($_POST['setId'] ?? '');
    $tenderId = trim($_POST['tenderId'] ?? '');

    $tender = load_department_tender($deptId, $tenderId);
    if (!$tender) {
        set_flash('error', 'Tender not found.');
        redirect('/department/requirements.php');
    }
    $sets = load_requirement_sets($deptId);
    $exists = false;
    foreach ($sets as $set) {
        if (($set['setId'] ?? '') === $setId) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        set_flash('error', 'Requirement set not found.');
        redirect('/department/requirements.php');
    }

    $tender['requirementSetId'] = $setId;
    $tender['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    save_department_tender($deptId, $tender);
    append_department_audit($deptId, [
        'by' => $user['username'] ?? '',
        'action' => 'requirement_attached',
        'meta' => ['tenderId' => $tenderId, 'setId' => $setId],
    ]);
    set_flash('success', 'Requirement set attached to tender.');
    redirect('/department/requirements.php');
});
