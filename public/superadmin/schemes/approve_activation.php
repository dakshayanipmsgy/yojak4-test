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
    $yojId = (string)($request['yojId'] ?? '');
    $schemeCode = strtoupper((string)($request['schemeCode'] ?? ''));
    $version = (string)($request['requestedVersion'] ?? '');
    if ($yojId === '' || $schemeCode === '' || $version === '') {
        set_flash('error', 'Activation request is missing required data.');
        redirect('/superadmin/schemes/activation_requests.php');
    }
    $request['status'] = 'approved';
    $request['decisionAt'] = now_kolkata()->format(DateTime::ATOM);
    $request['decisionBy'] = $user['username'] ?? 'superadmin';
    update_activation_request($path, $request);

    contractor_set_enabled_scheme($yojId, $schemeCode, $version);
    logEvent(DATA_PATH . '/logs/schemes.log', [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => 'ACT_REQ_APPROVE',
        'requestId' => $request['requestId'] ?? '',
        'yojId' => $yojId,
        'schemeCode' => $schemeCode,
        'version' => $version,
        'by' => $request['decisionBy'],
    ]);

    set_flash('success', 'Activation approved.');
    redirect('/superadmin/schemes/activation_requests.php');
});
