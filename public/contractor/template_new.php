<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/template_editor.php';

safe_page(function () {
    $user = require_role('contractor');
    $template = [
        'scope' => 'contractor',
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
    ];
    $title = get_app_config()['appName'] . ' | New Template';

    render_layout($title, function () use ($template) {
        ?>
        <div class="card" style="display:grid;gap:14px;">
            <div>
                <h2 style="margin:0;">Create Template</h2>
                <p class="muted" style="margin:4px 0 0;">Use the guided editor to insert placeholders without typing raw JSON.</p>
            </div>
        </div>
        <?php
        render_template_editor([
            'template' => $template,
            'action' => '/contractor/template_create.php',
            'submitLabel' => 'Create Template',
            'cancelUrl' => '/contractor/templates.php',
        ]);
    });
});
