<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/templates.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $tplId = trim((string)($_POST['id'] ?? ''));
    if ($tplId === '') {
        render_error_page('Missing template id.');
        return;
    }
    $template = template_load('contractor', $tplId, $yojId);
    if (!$template) {
        render_error_page('Template not found.');
        return;
    }
    $template['archived'] = true;
    template_save($template, $yojId);
    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_archived',
        'scope' => 'contractor',
        'yojId' => $yojId,
        'templateId' => $tplId,
    ]);

    set_flash('success', 'Template archived.');
    redirect('/contractor/templates.php');
});
