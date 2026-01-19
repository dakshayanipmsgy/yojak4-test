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
        redirect('/superadmin/packs.php');
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        set_flash('error', 'Invalid JSON.');
        redirect('/superadmin/packs.php');
    }

    set_flash('success', 'JSON is valid.');
    $packTemplateId = $decoded['packTemplateId'] ?? '';
    redirect('/superadmin/packs.php' . ($packTemplateId ? '?packTemplateId=' . urlencode((string)$packTemplateId) : ''));
});
