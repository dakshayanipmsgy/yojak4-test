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
    $request = readJson($path);
    if (!$request) {
        redirect('/superadmin/schemes/activation_requests.php');
    }
    $request['status'] = 'rejected';
    $request['decisionAt'] = now_kolkata()->format(DateTime::ATOM);
    $request['decisionBy'] = $user['username'] ?? 'superadmin';
    update_activation_request($path, $request);
    set_flash('success', 'Activation rejected.');
    redirect('/superadmin/schemes/activation_requests.php');
});
