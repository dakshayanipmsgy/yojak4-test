<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/templates.php');
    }

    require_csrf();
    require_staff_actor();

    $templateId = trim((string)($_POST['id'] ?? ''));
    $existing = load_template_record_by_scope('global', $templateId);
    if (!$existing) {
        render_error_page('Template not found.');
        return;
    }

    delete_template_record('global', $templateId);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'global_template_deleted',
        'templateId' => $templateId,
    ]);

    set_flash('success', 'Global template deleted.');
    redirect('/superadmin/templates.php');
});
