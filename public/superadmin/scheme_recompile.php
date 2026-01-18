<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_scheme_builder();
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

    $compileErrors = [];
    $compileWarnings = [];
    $compiled = scheme_compile_definition($schemeId, $compileErrors, $compileWarnings);
    if ($compiled) {
        writeJsonAtomic(scheme_compiled_definition_path($schemeId), $compiled);
        scheme_log_import($schemeId, 'COMPILE_OK', $compileWarnings);
        set_flash('success', 'Scheme recompiled successfully.');
    } else {
        scheme_log_import($schemeId, 'COMPILE_FAIL', $compileErrors);
        set_flash('error', 'Compilation failed: ' . implode(' ', $compileErrors));
    }

    $newVersion = (int)($scheme['version'] ?? 1) + 1;
    scheme_update_metadata($schemeId, ['version' => $newVersion]);

    redirect('/superadmin/scheme_sections.php?schemeId=' . urlencode($schemeId));
});
