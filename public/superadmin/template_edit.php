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
    $templateId = trim((string)($_GET['templateId'] ?? ''));
    if ($templateId === '') {
        render_error_page('Template ID is required.');
        return;
    }
    if ($scope === 'contractor' && $yojId === '') {
        render_error_page('Contractor YOJ ID is required.');
        return;
    }

    $template = load_template_library_record($scope, $yojId !== '' ? $yojId : null, $templateId);
    if (!$template) {
        render_error_page('Template not found.');
        return;
    }

    $canAdvanced = ($actor['type'] ?? '') === 'superadmin' || employee_has_permission($actor, 'template_manager_advanced');

    $title = get_app_config()['appName'] . ' | Edit Template';
    render_layout($title, function () use ($template, $scope, $yojId, $canAdvanced) {
        ?>
        <div class="card" style="display:grid;gap:14px;">
            <div>
                <h2 style="margin:0;">Edit Template (<?= sanitize($scope); ?>)</h2>
                <p class="muted" style="margin:4px 0 0;">Update template content and fields.</p>
            </div>
        </div>
        <?php
        render_template_editor([
            'template' => $template,
            'action' => '/superadmin/template_update.php?scope=' . urlencode($scope) . ($yojId ? '&yojId=' . urlencode($yojId) : ''),
            'submitLabel' => 'Save Changes',
            'cancelUrl' => '/superadmin/templates.php',
            'isStaff' => true,
            'showAdvanced' => $canAdvanced,
            'scope' => $scope,
        ]);
    });
});
