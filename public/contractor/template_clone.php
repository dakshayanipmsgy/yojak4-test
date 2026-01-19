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

    $source = load_template_library_record('global', null, $templateId);
    if (!$source) {
        render_error_page('Template not found.');
        return;
    }

    $newTemplate = $source;
    $newTemplate['templateId'] = generate_template_library_id();
    $newTemplate['scope'] = 'contractor';
    $newTemplate['owner'] = ['yojId' => $yojId];
    $newTemplate['status'] = 'active';

    save_template_library_record($newTemplate, 'contractor', $yojId);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_cloned',
        'templateId' => $newTemplate['templateId'],
        'sourceTemplateId' => $templateId,
        'scope' => 'contractor',
        'yojId' => $yojId,
        'ip' => mask_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    ]);

    set_flash('success', 'Template cloned to My Templates.');
    redirect('/contractor/templates.php');
});
