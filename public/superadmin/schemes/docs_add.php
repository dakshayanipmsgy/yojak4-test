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

    $docId = trim($_POST['docId'] ?? '');
    $label = trim($_POST['label'] ?? '');
    $templateBody = (string)($_POST['templateBody'] ?? '');
    $stats = [];
    $templateBody = migrate_placeholders_to_canonical($templateBody, $stats);
    $registry = placeholder_registry(['scheme' => $draft]);
    $validation = validate_placeholders($templateBody, $registry);
    if (!empty($validation['invalidTokens']) || !empty($validation['unknownKeys'])) {
        $errors = array_merge(
            array_map(static fn($token) => 'Invalid placeholder: ' . $token, $validation['invalidTokens']),
            array_map(static fn($token) => 'Unknown field: ' . $token, $validation['unknownKeys'])
        );
        set_flash('error', implode(' ', $errors));
        redirect('/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=documents');
    }
    if ($docId !== '') {
        $draft = scheme_add_document($draft, [
            'docId' => $docId,
            'label' => $label ?: $docId,
            'templateBody' => $templateBody,
            'autoGenerate' => isset($_POST['autoGenerate']),
            'allowManual' => isset($_POST['allowManual']),
            'allowRegen' => isset($_POST['allowRegen']),
            'lockAfterGen' => isset($_POST['lockAfterGen']),
            'vendorVisible' => isset($_POST['vendorVisible']),
            'customerDownload' => isset($_POST['customerDownload']),
            'authorityOnly' => isset($_POST['authorityOnly']),
        ]);
        scheme_log_audit($schemeCode, 'add_document', $actor['type'] ?? 'actor', ['docId' => $docId]);
    }

    save_scheme_draft($schemeCode, $draft);
    redirect('/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=documents');
});
