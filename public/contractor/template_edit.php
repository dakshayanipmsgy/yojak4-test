<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/template_editor.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $templateId = trim((string)($_GET['templateId'] ?? ''));
    if ($templateId === '') {
        render_error_page('Template ID is required.');
        return;
    }
    $template = load_template_library_record('contractor', $yojId, $templateId);
    if (!$template) {
        render_error_page('Template not found.');
        return;
    }

    $title = get_app_config()['appName'] . ' | Edit Template';
    render_layout($title, function () use ($template) {
        ?>
        <div class="card" style="display:grid;gap:14px;">
            <div>
                <h2 style="margin:0;">Edit Template</h2>
                <p class="muted" style="margin:4px 0 0;">Update the template body and fields using placeholder chips.</p>
            </div>
        </div>
        <?php
        render_template_editor([
            'template' => $template,
            'action' => '/contractor/template_update.php',
            'submitLabel' => 'Save Changes',
            'cancelUrl' => '/contractor/templates.php',
        ]);
    });
});
