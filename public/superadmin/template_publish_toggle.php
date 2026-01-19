<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/templates.php');
    }

    require_csrf();
    require_role('superadmin');
    $id = trim((string)($_POST['id'] ?? ''));
    if ($id === '') {
        render_error_page('Missing template id.');
        return;
    }
    $template = template_load('global', $id);
    if (!$template) {
        render_error_page('Template not found.');
        return;
    }
    $template['published'] = empty($template['published']);
    template_save($template);
    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_publish_toggle',
        'scope' => 'global',
        'templateId' => $id,
        'published' => $template['published'],
    ]);
    set_flash('success', $template['published'] ? 'Template published.' : 'Template unpublished.');
    redirect('/superadmin/templates.php');
});
