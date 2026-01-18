<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('scheme_builder');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/schemes/index.php');
    }
    require_csrf();
    $schemeCode = strtoupper(trim($_POST['schemeCode'] ?? ''));
    $docId = trim($_POST['docId'] ?? '');
    if ($schemeCode === '' || $docId === '') {
        redirect('/superadmin/schemes/index.php');
    }
    $draft = load_scheme_draft($schemeCode);
    if (!$draft) {
        render_error_page('Scheme draft not found.');
    }

    $draft = scheme_delete_document($draft, $docId);
    save_scheme_draft($schemeCode, $draft);
    scheme_log_audit($schemeCode, 'delete_document', $actor['type'] ?? 'actor', ['docId' => $docId]);

    redirect('/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=documents');
});
