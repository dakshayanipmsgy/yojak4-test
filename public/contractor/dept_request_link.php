<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/departments.php');
    }
    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'] ?? '';
    $deptId = normalize_dept_id(trim($_POST['deptId'] ?? ''));
    $message = trim($_POST['message'] ?? '');

    if (!is_valid_dept_id($deptId)) {
        set_flash('error', 'Invalid department.');
        redirect('/contractor/departments.php');
    }
    $department = load_department($deptId);
    if (!$department || ($department['status'] ?? '') !== 'active') {
        set_flash('error', 'Department not available.');
        redirect('/contractor/departments.php');
    }
    if (empty($department['visibleToContractors']) || empty($department['acceptingLinkRequests'])) {
        set_flash('error', 'This department is not accepting contractor links.');
        redirect('/contractor/departments.php');
    }
    $contractor = $yojId !== '' ? load_contractor($yojId) : null;
    if (!$contractor || ($contractor['status'] ?? '') !== 'approved') {
        set_flash('error', 'Contractor account not approved.');
        redirect('/contractor/departments.php');
    }

    if (strlen($message) > 500) {
        $message = substr($message, 0, 500);
    }

    if (contractor_has_pending_request($yojId, $deptId)) {
        set_flash('error', 'Request already pending for this department.');
        redirect('/contractor/departments.php');
    }
    if (contractor_pending_requests_count($yojId) >= 5) {
        set_flash('error', 'You have reached the pending request limit. Please wait for decisions.');
        redirect('/contractor/departments.php');
    }
    if (load_department_contractor_link($deptId, $yojId)) {
        set_flash('error', 'Already linked to this department.');
        redirect('/contractor/departments.php');
    }

    $requestId = generate_contractor_request_id($deptId);
    $now = now_kolkata()->format(DateTime::ATOM);
    $request = [
        'requestId' => $requestId,
        'deptId' => $deptId,
        'yojId' => $yojId,
        'contractorMobileMasked' => mask_mobile($contractor['mobile'] ?? ''),
        'message' => $message,
        'status' => 'pending',
        'createdAt' => $now,
        'decidedAt' => null,
        'decidedBy' => null,
        'decisionNote' => null,
    ];
    save_department_contractor_request($deptId, $request);

    create_contractor_notification($yojId, [
        'type' => 'dept_link_request_submitted',
        'title' => 'Link request submitted',
        'message' => 'Request sent to ' . ($department['nameEn'] ?? $deptId),
        'deptId' => $deptId,
    ]);

    log_link_event([
        'event' => 'LINK_REQUEST_CREATED',
        'deptId' => $deptId,
        'yojId' => $yojId,
        'requestId' => $requestId,
        'actorType' => 'contractor',
        'actorId' => $yojId,
        'result' => 'pending',
    ]);

    set_flash('success', 'Request submitted to department.');
    redirect('/contractor/departments.php');
});
