<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/department/contractor_requests.php');
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
    $action = trim($_POST['action'] ?? '');
    $requestId = trim($_POST['requestId'] ?? '');
    $note = trim($_POST['note'] ?? '');

    $request = load_department_contractor_request($deptId, $requestId);
    if (!$request || ($request['status'] ?? '') !== 'pending') {
        set_flash('error', 'Request not found or already processed.');
        redirect('/department/contractor_requests.php');
    }

    $yojId = $request['yojId'] ?? '';
    $contractor = $yojId !== '' ? load_contractor($yojId) : null;
    if (!$contractor || ($contractor['status'] ?? '') !== 'approved') {
        set_flash('error', 'Contractor not available.');
        redirect('/department/contractor_requests.php');
    }

    $request['decidedAt'] = now_kolkata()->format(DateTime::ATOM);
    $request['decidedBy'] = $user['fullUserId'] ?? ($user['username'] ?? '');
    $request['decisionNote'] = $note !== '' ? $note : null;

    if ($action === 'approve') {
        ensure_department_contractor_link($deptId, $yojId, 'contractor_request');
        $request['status'] = 'approved';
        save_department_contractor_request($deptId, $request);
        create_contractor_notification($yojId, [
            'type' => 'dept_link_approved',
            'title' => 'Department approved your link',
            'message' => 'You can now access ' . ($request['deptId'] ?? ''),
            'deptId' => $deptId,
        ]);
        log_link_event([
            'event' => 'LINK_REQUEST_APPROVED',
            'deptId' => $deptId,
            'yojId' => $yojId,
            'requestId' => $requestId,
            'actorType' => 'department_admin',
            'actorId' => $user['fullUserId'] ?? '',
            'result' => 'approved',
        ]);
        set_flash('success', 'Request approved and contractor linked.');
    } elseif ($action === 'reject') {
        $request['status'] = 'rejected';
        save_department_contractor_request($deptId, $request);
        create_contractor_notification($yojId, [
            'type' => 'dept_link_rejected',
            'title' => 'Department rejected your request',
            'message' => $note !== '' ? $note : 'Request was rejected.',
            'deptId' => $deptId,
        ]);
        log_link_event([
            'event' => 'LINK_REQUEST_REJECTED',
            'deptId' => $deptId,
            'yojId' => $yojId,
            'requestId' => $requestId,
            'actorType' => 'department_admin',
            'actorId' => $user['fullUserId'] ?? '',
            'result' => 'rejected',
            'reason' => $note,
        ]);
        set_flash('success', 'Request rejected.');
    } else {
        set_flash('error', 'Invalid action.');
    }

    redirect('/department/contractor_requests.php');
});
