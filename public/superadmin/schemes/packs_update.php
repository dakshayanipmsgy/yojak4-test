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

    $states = array_filter(array_map('trim', explode(',', $_POST['workflowStates'] ?? '')));
    $newPackId = trim($_POST['newPackId'] ?? '') ?: $packId;
    $existingTransitions = [];
    foreach ($draft['packs'] ?? [] as $pack) {
        if (($pack['packId'] ?? '') === $packId) {
            $existingTransitions = $pack['workflow']['transitions'] ?? [];
            break;
        }
    }
    $draft = scheme_update_pack($draft, $packId, [
        'packId' => $newPackId,
        'label' => trim($_POST['label'] ?? $packId),
        'moduleId' => trim($_POST['moduleId'] ?? ''),
        'requiredFieldKeys' => $_POST['requiredFieldKeys'] ?? [],
        'documentIds' => $_POST['documentIds'] ?? [],
        'workflowEnabled' => isset($_POST['workflowEnabled']),
        'workflowStates' => $states,
        'workflowTransitions' => $existingTransitions,
        'workflowDefaultState' => trim($_POST['workflowDefaultState'] ?? ''),
    ]);

    save_scheme_draft($schemeCode, $draft);
    scheme_log_audit($schemeCode, 'update_pack', $actor['type'] ?? 'actor', ['packId' => $packId]);
    redirect('/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=packs');
});
