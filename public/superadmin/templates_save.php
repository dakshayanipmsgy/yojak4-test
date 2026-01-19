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

    $templateId = trim((string)($_POST['templateId'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'tender'));
    $description = trim((string)($_POST['description'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $tablesJson = (string)($_POST['tables_json'] ?? '[]');

    if ($title === '' || strlen($title) < 3) {
        set_flash('error', 'Title must be at least 3 characters.');
        redirect('/superadmin/templates.php' . ($templateId !== '' ? '?templateId=' . urlencode($templateId) : ''));
    }

    $tables = [];
    $decoded = json_decode($tablesJson, true);
    if (is_array($decoded)) {
        foreach ($decoded as $table) {
            if (!is_array($table)) {
                continue;
            }
            $tableId = trim((string)($table['tableId'] ?? ''));
            if ($tableId === '') {
                continue;
            }
            $columns = [];
            foreach ((array)($table['columns'] ?? []) as $column) {
                if (!is_array($column)) {
                    continue;
                }
                $key = trim((string)($column['key'] ?? ''));
                if ($key === '') {
                    continue;
                }
                $columns[] = [
                    'key' => $key,
                    'label' => trim((string)($column['label'] ?? $key)),
                    'type' => $column['type'] ?? 'text',
                    'allowManual' => (bool)($column['allowManual'] ?? false),
                    'defaultBlank' => (bool)($column['defaultBlank'] ?? false),
                    'formula' => $column['formula'] ?? null,
                ];
            }
            if ($columns) {
                $tables[] = [
                    'tableId' => $tableId,
                    'title' => trim((string)($table['title'] ?? '')),
                    'columns' => $columns,
                ];
            }
        }
    }

    $existing = null;
    if ($templateId !== '') {
        $existing = load_global_template($templateId);
    }

    $payload = [
        'templateId' => $templateId !== '' ? $templateId : generate_template_id('TPL'),
        'scope' => 'global',
        'title' => $title,
        'category' => $category,
        'description' => $description,
        'bodyType' => 'simple_html',
        'body' => $body,
        'tables' => $tables,
        'rules' => template_default_rules(),
        'createdAt' => $existing['createdAt'] ?? null,
        'status' => $existing['status'] ?? 'active',
    ];

    save_global_template($payload);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => $existing ? 'global_template_updated' : 'global_template_created',
        'actor' => $actor['type'] ?? 'staff',
        'templateId' => $payload['templateId'],
    ]);

    set_flash('success', 'Global template saved.');
    redirect('/superadmin/templates.php?templateId=' . urlencode($payload['templateId']));
});
