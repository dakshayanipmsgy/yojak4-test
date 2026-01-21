<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $deptId = $user['deptId'] ?? '';
    ensure_department_env($deptId);
    require_department_permission($user, 'generate_docs');

    $docId = trim((string)($_GET['docId'] ?? ''));
    if ($docId === '') {
        render_error_page('Document not found.');
        return;
    }

    $path = department_generated_docs_path($deptId) . '/' . $docId . '.json';
    if (!file_exists($path)) {
        render_error_page('Document not found.');
        return;
    }

    $doc = readJson($path);
    if (!$doc || ($doc['deptId'] ?? '') !== $deptId) {
        render_error_page('Unauthorized access.');
        return;
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo $doc['renderedHtml'] ?? '';
});
