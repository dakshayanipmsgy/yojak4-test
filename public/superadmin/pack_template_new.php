<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/pack_template_editor.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('pack_manager');
    $scope = trim((string)($_GET['scope'] ?? 'global'));
    if (!in_array($scope, ['global', 'contractor'], true)) {
        $scope = 'global';
    }
    $yojId = trim((string)($_GET['yojId'] ?? ''));
    if ($scope === 'contractor' && $yojId === '') {
        render_error_page('Contractor YOJ ID is required.');
        return;
    }

    $templates = $scope === 'contractor'
        ? array_merge(list_template_library_records('global', null), list_template_library_records('contractor', $yojId))
        : list_template_library_records('global', null);
    $templates = array_values(array_map(fn($tpl) => [
        'templateId' => $tpl['templateId'] ?? '',
        'title' => ($tpl['title'] ?? 'Template') . ' (' . ($tpl['scope'] ?? 'global') . ')',
    ], $templates));

    $canAdvanced = ($actor['type'] ?? '') === 'superadmin' || employee_has_permission($actor, 'pack_manager_advanced');
    $pack = [
        'scope' => $scope,
        'title' => '',
        'description' => '',
        'items' => [],
        'fieldCatalog' => [],
        'status' => 'active',
        'owner' => $scope === 'contractor' ? ['yojId' => $yojId] : null,
    ];

    $title = get_app_config()['appName'] . ' | New Pack Template';
    render_layout($title, function () use ($pack, $templates, $scope, $yojId, $canAdvanced) {
        ?>
        <div class="card" style="display:grid;gap:14px;">
            <div>
                <h2 style="margin:0;">Create Pack Template (<?= sanitize($scope); ?>)</h2>
                <p class="muted" style="margin:4px 0 0;">Build reusable tender pack blueprints.</p>
            </div>
        </div>
        <?php
        render_pack_template_editor([
            'pack' => $pack,
            'templates' => $templates,
            'action' => '/superadmin/pack_template_create.php' . ($yojId ? '?yojId=' . urlencode($yojId) . '&scope=' . urlencode($scope) : '?scope=' . urlencode($scope)),
            'submitLabel' => 'Create Pack Template',
            'cancelUrl' => '/superadmin/packs.php',
            'isStaff' => true,
            'showAdvanced' => $canAdvanced,
            'scope' => $scope,
        ]);
    });
});
