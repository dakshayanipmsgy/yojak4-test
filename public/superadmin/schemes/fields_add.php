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

    $label = trim($_POST['label'] ?? '');
    if ($label !== '') {
        $options = array_values(array_filter(array_map('trim', explode(',', $_POST['options'] ?? ''))));
        $draft = scheme_add_field($draft, [
            'label' => $label,
            'type' => $_POST['type'] ?? 'text',
            'required' => isset($_POST['required']),
            'minLen' => (int)($_POST['minLen'] ?? 0) ?: null,
            'maxLen' => (int)($_POST['maxLen'] ?? 0) ?: null,
            'pattern' => trim($_POST['pattern'] ?? '') ?: null,
            'min' => $_POST['min'] !== '' ? (float)($_POST['min']) : null,
            'max' => $_POST['max'] !== '' ? (float)($_POST['max']) : null,
            'dateMin' => trim($_POST['dateMin'] ?? '') ?: null,
            'dateMax' => trim($_POST['dateMax'] ?? '') ?: null,
            'options' => $options,
            'moduleId' => trim($_POST['moduleId'] ?? ''),
            'viewRoles' => $_POST['viewRoles'] ?? [],
            'editRoles' => $_POST['editRoles'] ?? [],
            'unique' => isset($_POST['unique']),
        ]);
        scheme_log_audit($schemeCode, 'add_field', $actor['type'] ?? 'actor', ['label' => $label]);
    }

    save_scheme_draft($schemeCode, $draft);
    redirect('/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=dictionary');
});
