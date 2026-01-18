<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('scheme_builder');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/schemes/index.php');
    }
    require_csrf();
    $schemeCode = strtoupper(trim($_POST['schemeCode'] ?? ''));
    $packId = trim($_POST['packId'] ?? '');
    if ($schemeCode === '' || $packId === '') {
        redirect('/superadmin/schemes/index.php');
    }
    $draft = load_scheme_draft($schemeCode);
    if (!$draft) {
        render_error_page('Scheme draft not found.');
    }

    $draft = scheme_delete_pack($draft, $packId);
    save_scheme_draft($schemeCode, $draft);
    scheme_log_audit($schemeCode, 'delete_pack', $actor['type'] ?? 'actor', ['packId' => $packId]);

    redirect('/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=packs');
});
