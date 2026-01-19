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
    $templateId = trim((string)($_POST['templateId'] ?? ''));
    if ($templateId === '') {
        render_error_page('Template ID missing.');
        return;
    }

    if (archive_template_library_record('contractor', $yojId, $templateId)) {
        logEvent(DATA_PATH . '/logs/templates.log', [
            'event' => 'template_archived',
            'templateId' => $templateId,
            'scope' => 'contractor',
            'yojId' => $yojId,
            'ip' => mask_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        ]);
        set_flash('success', 'Template archived.');
    }

    redirect('/contractor/templates.php');
});
