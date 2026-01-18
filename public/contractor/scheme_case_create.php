<?php
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/schemes.php');
    }
    require_csrf();
    $schemeCode = strtoupper(trim($_POST['schemeCode'] ?? ''));
    $title = trim($_POST['title'] ?? '');
    if ($schemeCode === '') {
        redirect('/contractor/schemes.php');
    }
    $enabled = contractor_enabled_schemes($user['yojId'] ?? '');
    $version = $enabled[$schemeCode] ?? '';
    if (!$version) {
        set_flash('error', 'Scheme not enabled yet.');
        redirect('/contractor/schemes.php');
    }
    $scheme = load_scheme_version($schemeCode, $version);
    $case = scheme_case_create($schemeCode, $version, $user['yojId'] ?? '', $scheme['caseLabel'] ?? 'Case', $title);
    set_flash('success', 'Case created.');
    redirect('/contractor/scheme_case.php?schemeCode=' . urlencode($schemeCode) . '&caseId=' . urlencode($case['caseId'] ?? ''));
});
