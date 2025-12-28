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
    $yojId = trim($_POST['yojId'] ?? '');
    $action = trim($_POST['action'] ?? '');

    $link = load_department_contractor_link($deptId, $yojId);
    if (!$link) {
        set_flash('error', 'Link not found.');
        redirect('/department/contractors.php');
    }

    $newStatus = $link['status'] ?? 'active';
    $event = '';
    if ($action === 'suspend') {
        $newStatus = 'suspended';
        $event = 'LINK_SUSPENDED';
    } elseif ($action === 'revoke') {
        $newStatus = 'revoked';
        $event = 'LINK_REVOKED';
    } elseif ($action === 'activate') {
        $newStatus = 'active';
        $event = 'LINK_REACTIVATED';
    } else {
        set_flash('error', 'Invalid action.');
        redirect('/department/contractors.php');
    }

    update_department_contractor_link_status($deptId, $yojId, $newStatus);

    $notificationType = $newStatus === 'active' ? 'dept_link_approved' : ($newStatus === 'suspended' ? 'dept_link_suspended' : 'dept_link_revoked');
    create_contractor_notification($yojId, [
        'type' => $notificationType,
        'title' => 'Link status updated',
        'message' => 'Your link with ' . $deptId . ' is now ' . $newStatus,
        'deptId' => $deptId,
    ]);

    log_link_event([
        'event' => $event,
        'deptId' => $deptId,
        'yojId' => $yojId,
        'requestId' => null,
        'actorType' => 'department_admin',
        'actorId' => $user['fullUserId'] ?? '',
        'result' => $newStatus,
    ]);

    set_flash('success', 'Link status updated.');
    redirect('/department/contractors.php');
});
