<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('scheme_builder');
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
        render_error_page('Scheme draft not found.');
    }

    $draft['name'] = trim($_POST['name'] ?? $draft['name']);
    $draft['description'] = trim($_POST['description'] ?? $draft['description']);
    $draft['caseLabel'] = trim($_POST['caseLabel'] ?? $draft['caseLabel']);

    save_scheme_draft($schemeCode, $draft);
    scheme_log_audit($schemeCode, 'update_overview', $actor['type'] ?? 'actor');

    redirect('/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=overview');
});
