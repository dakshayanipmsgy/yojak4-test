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

    $canEditAdvanced = ($actor['type'] ?? '') === 'superadmin' || employee_has_permission($actor, 'scheme_builder_advanced');
    if (!$canEditAdvanced) {
        render_error_page('You do not have permission to apply JSON to draft.');
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
    if (!empty($errors)) {
        redirect('/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=' . urlencode(section_to_tab($section)) . '&section=' . urlencode($section) . '&validated=0&errors=' . urlencode(json_encode($errors)));
    }

    $snapshotPath = scheme_snapshot_draft($schemeCode, $draft);
    $draft = scheme_apply_section_payload($section, $decoded, $draft);
    save_scheme_draft($schemeCode, $draft);
    scheme_log_audit($schemeCode, 'apply_section_json', $actor['type'] ?? 'actor', [
        'section' => $section,
        'snapshot' => $snapshotPath,
    ]);

    redirect('/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=' . urlencode(section_to_tab($section)) . '&section=' . urlencode($section) . '&applied=1');
});
