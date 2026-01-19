<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $actor = require_superadmin_or_permission('templates_manage');
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

    $decoded['scope'] = 'global';
    $template = normalize_template_schema($decoded, 'global');
    save_global_template($template);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'global_template_json_applied',
        'actor' => $actor['type'] ?? 'staff',
        'templateId' => $template['templateId'],
    ]);

    set_flash('success', 'Global template JSON applied.');
    redirect('/superadmin/templates.php?templateId=' . urlencode($template['templateId']));
});
