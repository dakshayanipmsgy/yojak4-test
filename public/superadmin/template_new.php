<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/template_editor.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('template_manager');
    $scope = trim((string)($_GET['scope'] ?? 'global'));
    if (!in_array($scope, ['global', 'contractor'], true)) {
        $scope = 'global';
    }
    $yojId = trim((string)($_GET['yojId'] ?? ''));
    if ($scope === 'contractor' && $yojId === '') {
        render_error_page('Contractor YOJ ID is required.');
        return;
    }

    $canAdvanced = ($actor['type'] ?? '') === 'superadmin' || employee_has_permission($actor, 'template_manager_advanced');
    $template = [
        'scope' => $scope,
        'title' => '',
        'category' => 'Tender',
        'description' => '',
        'editorType' => 'simple_html',
        'body' => '',
        'fieldCatalog' => [],
        'rules' => [
            'allowManualEditBeforePrint' => true,
            'lockAfterGenerate' => false,
        ],
        'status' => 'active',
        'owner' => $scope === 'contractor' ? ['yojId' => $yojId] : null,
    ];

    $title = get_app_config()['appName'] . ' | New Template';
    render_layout($title, function () use ($template, $scope, $yojId, $canAdvanced) {
        ?>
        <div class="card" style="display:grid;gap:14px;">
            <div>
                <h2 style="margin:0;">Create Template (<?= sanitize($scope); ?>)</h2>
                <p class="muted" style="margin:4px 0 0;">Use guided editor or advanced JSON (staff only).</p>
            </div>
        </div>
        <?php
        render_template_editor([
            'template' => $template,
            'action' => '/superadmin/template_create.php' . ($yojId ? '?yojId=' . urlencode($yojId) . '&scope=' . urlencode($scope) : '?scope=' . urlencode($scope)),
            'submitLabel' => 'Create Template',
            'cancelUrl' => '/superadmin/templates.php',
            'isStaff' => true,
            'showAdvanced' => $canAdvanced,
            'scope' => $scope,
        ]);
    });
});
