<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/department/contractors.php');
    }
    require_csrf();
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    if (($user['roleId'] ?? '') !== 'admin') {
        render_error_page('Admin access required.');
        return;
    }

    $deptId = $user['deptId'] ?? '';
    $yojId = strtoupper(trim($_POST['yojId'] ?? ''));
    if (!is_valid_yoj_id($yojId)) {
        set_flash('error', 'Invalid YOJ ID.');
        redirect('/department/contractors.php');
    }

    $contractor = load_contractor($yojId);
    if (!$contractor || ($contractor['status'] ?? '') !== 'approved') {
        set_flash('error', 'Contractor not found or not approved.');
        redirect('/department/contractors.php');
    }

    $link = ensure_department_contractor_link($deptId, $yojId, 'admin_link');
    create_contractor_notification($yojId, [
        'type' => 'dept_link_admin_linked',
        'title' => 'Linked to department',
        'message' => 'You have been linked to department ' . $deptId,
        'deptId' => $deptId,
    ]);
    log_link_event([
        'event' => 'LINK_ADMIN_LINKED',
        'deptId' => $deptId,
        'yojId' => $yojId,
        'requestId' => null,
        'actorType' => 'department_admin',
        'actorId' => $user['fullUserId'] ?? '',
        'result' => 'active',
    ]);

    set_flash('success', 'Contractor linked with ID ' . ($link['deptContractorId'] ?? ''));
    redirect('/department/contractors.php');
});
