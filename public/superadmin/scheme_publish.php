<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_scheme_approver();
    require_csrf();

    $schemeId = trim($_POST['schemeId'] ?? '');
    if ($schemeId === '') {
        render_error_page('Scheme ID missing.');
        return;
    }

    $scheme = scheme_load_metadata($schemeId);
    if (!$scheme) {
        render_error_page('Scheme not found.');
        return;
    }

    $entries = scheme_sections_index($schemeId);
    foreach ($entries as &$entry) {
        $entry['status'] = 'published';
        $entry['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    }
    unset($entry);
    if ($entries) {
        scheme_sections_write_index($schemeId, $entries);
    }

    $compileErrors = [];
    $compileWarnings = [];
    $compiled = scheme_compile_definition($schemeId, $compileErrors, $compileWarnings);
    if ($compiled) {
        writeJsonAtomic(scheme_compiled_definition_path($schemeId), $compiled);
    }

    $newVersion = (int)($scheme['version'] ?? 1) + 1;
    scheme_update_metadata($schemeId, [
        'version' => $newVersion,
        'status' => 'published',
    ]);

    scheme_log_import($schemeId, $compiled ? 'PUBLISH_OK' : 'PUBLISH_FAIL', $compileErrors);
    set_flash('success', 'Scheme published.' . ($compiled ? '' : ' Compilation pending.'));
    redirect('/superadmin/scheme_sections.php?schemeId=' . urlencode($schemeId));
});
