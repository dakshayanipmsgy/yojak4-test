<?php
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/schemes.php');
    }
    require_csrf();
    $schemeCode = strtoupper(trim($_POST['schemeCode'] ?? ''));
    $caseId = trim($_POST['caseId'] ?? '');
    $fieldsInput = $_POST['fields'] ?? [];
    if ($schemeCode === '' || $caseId === '') {
        redirect('/contractor/schemes.php');
    }
    $case = readJson(scheme_case_core_path($schemeCode, $user['yojId'] ?? '', $caseId));
    if (!$case) {
        render_error_page('Case not found.');
    }
    $scheme = load_scheme_version($schemeCode, $case['schemeVersion'] ?? '');
    $values = scheme_case_values($schemeCode, $user['yojId'] ?? '', $caseId);

    foreach ($scheme['fieldDictionary'] ?? [] as $field) {
        $key = $field['key'] ?? '';
        if ($key === '') {
            continue;
        }
        if (!array_key_exists($key, $fieldsInput)) {
            continue;
        }
        $values[$key] = trim((string)$fieldsInput[$key]);
    }

    writeJsonAtomic(scheme_case_fields_path($schemeCode, $user['yojId'] ?? '', $caseId), [
        'values' => $values,
        'updatedAt' => now_kolkata()->format(DateTime::ATOM),
    ]);

    foreach ($scheme['packs'] ?? [] as $pack) {
        scheme_update_pack_runtime($schemeCode, $user['yojId'] ?? '', $caseId, $pack, $values);
    }

    set_flash('success', 'Fields saved.');
    redirect('/contractor/scheme_case.php?schemeCode=' . urlencode($schemeCode) . '&caseId=' . urlencode($caseId));
});
