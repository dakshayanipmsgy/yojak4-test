<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('scheme_builder');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/schemes/index.php');
    }
    require_csrf();
    $schemeCode = strtoupper(trim($_POST['schemeCode'] ?? ''));
    $section = trim($_POST['section'] ?? '');
    $payloadRaw = trim($_POST['payload'] ?? '');
    if ($schemeCode === '' || $section === '') {
        redirect('/superadmin/schemes/index.php');
    }

    $draft = load_scheme_draft($schemeCode);
    if (!$draft) {
        render_error_page('Scheme draft not found.');
    }

    $decoded = json_decode($payloadRaw, true);
    if (!is_array($decoded)) {
        $errors = ['Invalid JSON. How to fix: ensure valid JSON without trailing commas.'];
        redirect('/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=' . urlencode(section_to_tab($section)) . '&section=' . urlencode($section) . '&validated=0&errors=' . urlencode(json_encode($errors)));
    }

    $errors = scheme_validate_section_json($section, $decoded, $draft);
    $validated = empty($errors) ? '1' : '0';
    $errorParam = $errors ? urlencode(json_encode($errors)) : '';
    redirect('/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=' . urlencode(section_to_tab($section)) . '&section=' . urlencode($section) . '&validated=' . $validated . ($errorParam ? '&errors=' . $errorParam : ''));
});
