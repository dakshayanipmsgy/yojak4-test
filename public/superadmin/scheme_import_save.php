<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_scheme_builder();
    require_csrf();

    $schemeId = trim($_POST['schemeId'] ?? '');
    $jsonPayload = trim($_POST['definition_json'] ?? '');
    if ($schemeId === '' || $jsonPayload === '') {
        render_error_page('Scheme ID and JSON are required.');
        return;
    }

    $scheme = scheme_load_metadata($schemeId);
    if (!$scheme) {
        render_error_page('Scheme not found.');
        return;
    }

    $decoded = json_decode($jsonPayload, true);
    if (!is_array($decoded)) {
        scheme_log_import($schemeId, 'IMPORT_FAIL', ['Invalid JSON payload.']);
        render_error_page('Invalid JSON payload.');
        return;
    }

    $normalized = [];
    $warnings = [];
    $errors = scheme_validate_definition($decoded, $normalized, $warnings);
    if (!$errors && (($normalized['schemeId'] ?? '') !== $schemeId)) {
        $errors[] = 'Scheme ID mismatch in payload.';
    }
    if ($errors) {
        scheme_log_import($schemeId, 'IMPORT_FAIL', $errors);
        render_error_page('Validation failed.');
        return;
    }

    writeJsonAtomic(scheme_definition_path($schemeId), $normalized);

    $newVersion = (int)($scheme['version'] ?? 1) + 1;
    $updates = [
        'version' => $newVersion,
        'updatedAt' => now_kolkata()->format(DateTime::ATOM),
    ];
    if (!empty($_POST['publish_now'])) {
        $updates['status'] = 'published';
    }
    scheme_update_metadata($schemeId, $updates);

    scheme_log_import($schemeId, 'IMPORT_OK', $warnings);
    set_flash('success', 'Scheme definition imported successfully.');
    redirect('/superadmin/schemes.php');
});
