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

    $errors = [];
    foreach ($scheme['fieldDictionary'] ?? [] as $field) {
        $key = $field['key'] ?? '';
        if ($key === '') {
            continue;
        }
        if (!array_key_exists($key, $fieldsInput)) {
            continue;
        }
        $value = trim((string)$fieldsInput[$key]);
        $type = $field['type'] ?? 'text';
        $validation = array_merge([
            'minLen' => null,
            'maxLen' => null,
            'pattern' => null,
            'min' => null,
            'max' => null,
            'dateMin' => null,
            'dateMax' => null,
            'options' => [],
        ], $field['validation'] ?? []);
        if (($field['required'] ?? false) && $value === '') {
            $errors[] = $field['label'] . ' is required.';
            continue;
        }
        if ($value !== '') {
            if ($type === 'number' && !is_numeric($value)) {
                $errors[] = $field['label'] . ' must be a number.';
            }
            if (!empty($validation['minLen']) && strlen($value) < (int)$validation['minLen']) {
                $errors[] = $field['label'] . ' must be at least ' . (int)$validation['minLen'] . ' characters.';
            }
            if (!empty($validation['maxLen']) && strlen($value) > (int)$validation['maxLen']) {
                $errors[] = $field['label'] . ' must be at most ' . (int)$validation['maxLen'] . ' characters.';
            }
            if (!empty($validation['pattern']) && !preg_match('/' . $validation['pattern'] . '/', $value)) {
                $errors[] = $field['label'] . ' format is invalid.';
            }
            if ($type === 'number') {
                $num = (float)$value;
                if ($validation['min'] !== null && $num < (float)$validation['min']) {
                    $errors[] = $field['label'] . ' must be at least ' . $validation['min'] . '.';
                }
                if ($validation['max'] !== null && $num > (float)$validation['max']) {
                    $errors[] = $field['label'] . ' must be at most ' . $validation['max'] . '.';
                }
            }
            if ($type === 'date') {
                if (!empty($validation['dateMin']) && $value < $validation['dateMin']) {
                    $errors[] = $field['label'] . ' must be on/after ' . $validation['dateMin'] . '.';
                }
                if (!empty($validation['dateMax']) && $value > $validation['dateMax']) {
                    $errors[] = $field['label'] . ' must be on/before ' . $validation['dateMax'] . '.';
                }
            }
            if ($type === 'dropdown' && !empty($validation['options']) && !in_array($value, $validation['options'], true)) {
                $errors[] = $field['label'] . ' must be a valid option.';
            }
            if ($type === 'yesno' && !in_array($value, ['Yes', 'No'], true)) {
                $errors[] = $field['label'] . ' must be Yes or No.';
            }
        }
        $values[$key] = $value;
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/contractor/scheme_fields.php?schemeCode=' . urlencode($schemeCode) . '&caseId=' . urlencode($caseId));
    }

    foreach ($scheme['fieldDictionary'] ?? [] as $field) {
        if (!empty($field['unique'])) {
            $key = $field['key'] ?? '';
            $value = trim((string)($values[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $cases = list_scheme_cases($schemeCode, $user['yojId'] ?? '');
            foreach ($cases as $existingCase) {
                if (($existingCase['caseId'] ?? '') === $caseId) {
                    continue;
                }
                $existingValues = scheme_case_values($schemeCode, $user['yojId'] ?? '', $existingCase['caseId'] ?? '');
                if (($existingValues[$key] ?? '') === $value) {
                    set_flash('error', $field['label'] . ' must be unique. Duplicate found in ' . ($existingCase['caseId'] ?? '') . '.');
                    redirect('/contractor/scheme_fields.php?schemeCode=' . urlencode($schemeCode) . '&caseId=' . urlencode($caseId));
                }
            }
        }
    }

    writeJsonAtomic(scheme_case_fields_path($schemeCode, $user['yojId'] ?? '', $caseId), [
        'values' => $values,
        'updatedAt' => now_kolkata()->format(DateTime::ATOM),
    ]);

    foreach ($scheme['packs'] ?? [] as $pack) {
        scheme_update_pack_runtime($schemeCode, $user['yojId'] ?? '', $caseId, $pack, $values);
    }

    scheme_append_timeline($schemeCode, $user['yojId'] ?? '', $caseId, ['event' => 'FIELDS_UPDATED', 'caseId' => $caseId]);

    set_flash('success', 'Fields saved.');
    redirect('/contractor/scheme_case.php?schemeCode=' . urlencode($schemeCode) . '&caseId=' . urlencode($caseId));
});
