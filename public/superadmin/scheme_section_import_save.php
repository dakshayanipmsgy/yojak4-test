<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_scheme_builder();
    require_csrf();

    $schemeId = trim($_POST['schemeId'] ?? '');
    $jsonPayload = trim($_POST['section_json'] ?? '');
    $existingSectionId = trim($_POST['existing_section_id'] ?? '');
    $saveMode = trim($_POST['save_mode'] ?? 'update');

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
        scheme_log_import($schemeId, 'SECTION_IMPORT_FAIL', ['Invalid JSON payload.']);
        render_error_page('Invalid JSON payload.');
        return;
    }

    $sectionId = (string)($decoded['sectionId'] ?? '');
    if ($sectionId === '') {
        render_error_page('Section ID missing in payload.');
        return;
    }

    $entries = scheme_sections_index($schemeId);
    $existingEntry = null;
    foreach ($entries as $entry) {
        if (($entry['sectionId'] ?? '') === $sectionId) {
            $existingEntry = $entry;
            break;
        }
    }

    if ($existingSectionId !== '') {
        if ($saveMode === 'update' && $existingSectionId !== $sectionId) {
            render_error_page('Section ID mismatch for update.');
            return;
        }
    }

    if ($saveMode === 'new' && $existingEntry) {
        render_error_page('Section ID already exists. Update the existing section or change the sectionId.');
        return;
    }

    $availableKeys = scheme_collect_section_component_keys(
        scheme_sections_payloads($schemeId, $existingSectionId !== '' ? $existingSectionId : null, true)
    );
    $normalized = [];
    $warnings = [];
    $errors = scheme_validate_section($decoded, $schemeId, $availableKeys, $normalized, $warnings);
    if ($errors) {
        scheme_log_import($schemeId, 'SECTION_IMPORT_FAIL', $errors);
        render_error_page('Validation failed.');
        return;
    }

    $now = now_kolkata()->format(DateTime::ATOM);
    if ($existingEntry && $saveMode !== 'new') {
        foreach ($entries as &$entry) {
            if (($entry['sectionId'] ?? '') === $sectionId) {
                $entry['title'] = $normalized['title'] ?? $entry['title'] ?? '';
                $entry['description'] = $normalized['description'] ?? $entry['description'] ?? '';
                $entry['updatedAt'] = $now;
                $existingEntry = $entry;
                break;
            }
        }
        unset($entry);
    } else {
        $order = scheme_sections_next_order($entries);
        $filename = scheme_section_filename($order, $sectionId, (string)($normalized['title'] ?? 'section'));
        $existingEntry = [
            'sectionId' => $sectionId,
            'title' => $normalized['title'] ?? '',
            'description' => $normalized['description'] ?? '',
            'order' => $order,
            'enabled' => true,
            'status' => 'draft',
            'file' => $filename,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
        $entries[] = $existingEntry;
    }

    $filename = $existingEntry['file'] ?? '';
    if ($filename === '') {
        render_error_page('Section filename missing.');
        return;
    }

    writeJsonAtomic(scheme_section_path($schemeId, $filename), $normalized);
    scheme_sections_write_index($schemeId, $entries);

    $compileErrors = [];
    $compileWarnings = [];
    $compiled = scheme_compile_definition($schemeId, $compileErrors, $compileWarnings);
    if ($compiled) {
        writeJsonAtomic(scheme_compiled_definition_path($schemeId), $compiled);
    }

    $newVersion = (int)($scheme['version'] ?? 1) + 1;
    scheme_update_metadata($schemeId, ['version' => $newVersion]);

    scheme_log_import($schemeId, $compiled ? 'SECTION_IMPORT_OK' : 'SECTION_COMPILE_FAIL', $compileErrors);
    set_flash('success', 'Section imported successfully.' . ($compiled ? '' : ' Compilation pending.'));
    redirect('/superadmin/scheme_sections.php?schemeId=' . urlencode($schemeId));
});
