<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/schemes/index.php');
    }
    require_csrf();
    $schemeCode = strtoupper(trim($_POST['schemeCode'] ?? ''));
    if ($schemeCode === '') {
        redirect('/superadmin/schemes/index.php');
    }
    $draft = load_scheme_draft($schemeCode);
    if (!$draft) {
        set_flash('error', 'Scheme draft not found.');
        redirect('/superadmin/schemes/index.php');
    }
    $version = publish_scheme_version($schemeCode, $draft, $user['username'] ?? 'superadmin');
    set_flash('success', 'Published ' . $schemeCode . ' as ' . $version . '.');
    redirect('/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=publish');
});
