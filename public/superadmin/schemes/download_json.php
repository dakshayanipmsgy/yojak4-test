<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('scheme_builder');
    $schemeCode = strtoupper(trim($_GET['schemeCode'] ?? ''));
    if ($schemeCode === '') {
        redirect('/superadmin/schemes/index.php');
    }
    $draft = load_scheme_draft($schemeCode);
    if (!$draft) {
        render_error_page('Scheme draft not found.');
    }
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $schemeCode . '-draft.json"');
    echo json_encode($draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});
