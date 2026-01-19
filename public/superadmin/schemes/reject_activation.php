<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/schemes/activation_requests.php');
    }
    require_csrf();
    $path = $_POST['path'] ?? '';
    if (!$path || !file_exists($path)) {
        redirect('/superadmin/schemes/activation_requests.php');
    }
    $request = read_activation_request_file($path);
    if (!$request) {
        set_flash('error', 'Unable to load activation request.');
        redirect('/superadmin/schemes/activation_requests.php');
    }
    if (($request['status'] ?? '') !== 'pending') {
        set_flash('warning', 'Activation request already processed.');
        redirect('/superadmin/schemes/activation_requests.php');
    }
    $notes = trim((string)($_POST['notes'] ?? ''));
    $request['status'] = 'rejected';
    $request['decisionAt'] = now_kolkata()->format(DateTime::ATOM);
    $request['decisionBy'] = $user['username'] ?? 'superadmin';
    if ($notes !== '') {
        $request['notes'] = $notes;
    }
    update_activation_request($path, $request);
    logEvent(DATA_PATH . '/logs/schemes.log', [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => 'ACT_REQ_REJECT',
        'requestId' => $request['requestId'] ?? '',
        'yojId' => $request['yojId'] ?? '',
        'schemeCode' => $request['schemeCode'] ?? '',
        'by' => $request['decisionBy'],
    ]);
    set_flash('success', 'Activation rejected.');
    redirect('/superadmin/schemes/activation_requests.php');
});
