<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    require_superadmin_or_permission('templates_manage');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        render_error_page('Invalid request.');
        return;
    }
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $json = trim((string)($_POST['json_payload'] ?? ''));
    if ($json === '') {
        set_flash('error', 'JSON payload is empty.');
        redirect('/superadmin/templates.php');
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        set_flash('error', 'Invalid JSON.');
        redirect('/superadmin/templates.php');
    }

    set_flash('success', 'JSON is valid.');
    $templateId = $decoded['templateId'] ?? '';
    redirect('/superadmin/templates.php' . ($templateId ? '?templateId=' . urlencode((string)$templateId) : ''));
});
