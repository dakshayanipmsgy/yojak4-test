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
    $packId = trim($_POST['packId'] ?? '');
    if ($label !== '' && $packId !== '') {
        $states = array_filter(array_map('trim', explode(',', $_POST['workflowStates'] ?? '')));
        $draft = scheme_add_pack($draft, [
            'packId' => $packId,
            'label' => $label,
            'moduleId' => trim($_POST['moduleId'] ?? ''),
            'requiredFieldKeys' => $_POST['requiredFieldKeys'] ?? [],
            'documentIds' => $_POST['documentIds'] ?? [],
            'workflowEnabled' => isset($_POST['workflowEnabled']),
            'workflowStates' => $states,
            'workflowTransitions' => [],
            'workflowDefaultState' => trim($_POST['workflowDefaultState'] ?? ''),
        ]);
        scheme_log_audit($schemeCode, 'add_pack', $actor['type'] ?? 'actor', ['packId' => $packId]);
    }

    save_scheme_draft($schemeCode, $draft);
    redirect('/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=packs');
});
