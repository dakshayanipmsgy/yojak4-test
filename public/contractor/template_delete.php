<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/templates.php?tab=mine');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $templateId = trim((string)($_POST['id'] ?? ''));

    $existing = load_template_record_by_scope('contractor', $templateId, $yojId);
    if (!$existing || (($existing['owner']['yojId'] ?? '') !== $yojId)) {
        render_error_page('Template not found.');
        return;
    }

    delete_template_record('contractor', $templateId, $yojId);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_deleted',
        'yojId' => $yojId,
        'templateId' => $templateId,
    ]);

    set_flash('success', 'Template deleted.');
    redirect('/contractor/templates.php?tab=mine');
});
