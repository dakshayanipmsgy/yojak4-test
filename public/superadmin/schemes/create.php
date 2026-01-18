<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('scheme_builder');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/schemes/new.php');
    }
    require_csrf();

    $schemeCode = strtoupper(trim($_POST['schemeCode'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $caseLabel = trim($_POST['caseLabel'] ?? 'Beneficiary');

    if (!$schemeCode || !preg_match('/^[A-Z0-9\-]{3,30}$/', $schemeCode)) {
        set_flash('error', 'Invalid scheme code.');
        redirect('/superadmin/schemes/new.php');
    }

    $base = scheme_base_path($schemeCode);
    if (file_exists($base)) {
        set_flash('error', 'Scheme code already exists.');
        redirect('/superadmin/schemes/new.php');
    }

    $rolesInput = trim($_POST['roles'] ?? '');
    $modulesInput = trim($_POST['modules'] ?? '');

    $parseLines = function (string $input): array {
        $items = [];
        foreach (preg_split('/\r\n|\r|\n/', $input) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$id, $label] = array_map('trim', array_pad(explode(':', $line, 2), 2, ''));
            if ($id === '') {
                continue;
            }
            $items[] = ['id' => $id, 'label' => $label ?: $id];
        }
        return $items;
    };

    $roles = $parseLines($rolesInput);
    $modules = $parseLines($modulesInput);

    $meta = [
        'schemeCode' => $schemeCode,
        'name' => $name,
        'description' => $description,
        'caseLabel' => $caseLabel,
        'createdAt' => now_kolkata()->format(DateTime::ATOM),
        'updatedAt' => now_kolkata()->format(DateTime::ATOM),
    ];

    if (!is_dir($base)) {
        mkdir($base, 0775, true);
    }
    if (!is_dir(scheme_versions_path($schemeCode))) {
        mkdir(scheme_versions_path($schemeCode), 0775, true);
    }

    save_scheme_meta($schemeCode, $meta);

    $draft = scheme_default_draft($schemeCode, $meta);
    if ($roles) {
        $draft['roles'] = array_map(fn($role) => ['roleId' => $role['id'], 'label' => $role['label']], $roles);
    }
    if ($modules) {
        $draft['modules'] = array_map(fn($module) => ['moduleId' => $module['id'], 'label' => $module['label']], $modules);
    }
    save_scheme_draft($schemeCode, $draft);

    scheme_log_audit($schemeCode, 'create_scheme', $actor['type'] ?? 'actor', [
        'schemeCode' => $schemeCode,
    ]);

    set_flash('success', 'Scheme draft created.');
    redirect('/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft');
});
