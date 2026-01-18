<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/schemes.php');
    }
    require_csrf();
    $schemeCode = strtoupper(trim($_POST['schemeCode'] ?? ''));
    $version = trim($_POST['version'] ?? '');
    if ($schemeCode === '' || $version === '') {
        redirect('/contractor/schemes.php');
    }
    create_activation_request($user['yojId'] ?? '', $schemeCode, $version);
    set_flash('success', 'Activation request submitted.');
    redirect('/contractor/schemes.php');
});
