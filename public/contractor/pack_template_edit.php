<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/pack_template_editor.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packTemplateId = trim((string)($_GET['packTemplateId'] ?? ''));
    if ($packTemplateId === '') {
        render_error_page('Pack template ID is required.');
        return;
    }

    $pack = load_pack_template_record('contractor', $yojId, $packTemplateId);
    if (!$pack) {
        render_error_page('Pack template not found.');
        return;
    }

    $templates = array_merge(
        list_template_library_records('global', null),
        list_template_library_records('contractor', $yojId)
    );
    $templates = array_values(array_map(fn($tpl) => [
        'templateId' => $tpl['templateId'] ?? '',
        'title' => ($tpl['title'] ?? 'Template') . ' (' . ($tpl['scope'] ?? 'global') . ')',
    ], $templates));

    $title = get_app_config()['appName'] . ' | Edit Pack Template';
    render_layout($title, function () use ($pack, $templates) {
        ?>
        <div class="card" style="display:grid;gap:14px;">
            <div>
                <h2 style="margin:0;">Edit Pack Template</h2>
                <p class="muted" style="margin:4px 0 0;">Update pack items and fields.</p>
            </div>
        </div>
        <?php
        render_pack_template_editor([
            'pack' => $pack,
            'templates' => $templates,
            'action' => '/contractor/pack_template_update.php',
            'submitLabel' => 'Save Changes',
            'cancelUrl' => '/contractor/packs_library.php',
        ]);
    });
});
