<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $tplId = trim((string)($_POST['tplId'] ?? ''));
    if ($tplId === '') {
        render_error_page('Template not found.');
        return;
    }

    $template = load_contractor_template($yojId, $tplId);
    if (!$template) {
        render_error_page('Template not found.');
        return;
    }

    $template['deletedAt'] = now_kolkata()->format(DateTime::ATOM);
    save_contractor_template($yojId, $template);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_deleted',
        'scope' => 'contractor',
        'yojId' => $yojId,
        'tplId' => $tplId,
    ]);

    set_flash('success', 'Template deleted.');
    redirect('/contractor/templates.php?tab=mine');
});
