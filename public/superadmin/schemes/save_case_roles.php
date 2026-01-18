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
    $action = $_POST['action'] ?? '';
    $draft = load_scheme_draft($schemeCode);
    if (!$draft) {
        render_error_page('Scheme draft not found.');
    }

    if ($action === 'add_role') {
        $roleId = trim($_POST['roleId'] ?? '');
        $label = trim($_POST['label'] ?? '');
        if ($roleId !== '') {
            $draft['roles'][] = ['roleId' => $roleId, 'label' => $label ?: $roleId];
            scheme_log_audit($schemeCode, 'add_role', $actor['type'] ?? 'actor', ['roleId' => $roleId]);
        }
    }

    if ($action === 'add_module') {
        $moduleId = trim($_POST['moduleId'] ?? '');
        $label = trim($_POST['label'] ?? '');
        if ($moduleId !== '') {
            $draft['modules'][] = ['moduleId' => $moduleId, 'label' => $label ?: $moduleId];
            scheme_log_audit($schemeCode, 'add_module', $actor['type'] ?? 'actor', ['moduleId' => $moduleId]);
        }
    }

    if ($action === 'delete_role') {
        $roleId = trim($_POST['roleId'] ?? '');
        if ($roleId !== '') {
            $draft['roles'] = array_values(array_filter($draft['roles'] ?? [], fn($role) => ($role['roleId'] ?? '') !== $roleId));
            scheme_log_audit($schemeCode, 'delete_role', $actor['type'] ?? 'actor', ['roleId' => $roleId]);
        }
    }

    if ($action === 'delete_module') {
        $moduleId = trim($_POST['moduleId'] ?? '');
        if ($moduleId !== '') {
            $draft['modules'] = array_values(array_filter($draft['modules'] ?? [], fn($module) => ($module['moduleId'] ?? '') !== $moduleId));
            scheme_log_audit($schemeCode, 'delete_module', $actor['type'] ?? 'actor', ['moduleId' => $moduleId]);
        }
    }

    save_scheme_draft($schemeCode, $draft);
    redirect('/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=case_roles');
});
