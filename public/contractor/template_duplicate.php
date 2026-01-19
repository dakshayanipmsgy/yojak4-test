<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $user = require_role('contractor');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        render_error_page('Invalid request.');
        return;
    }
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $yojId = $user['yojId'];
    $templateId = trim((string)($_POST['templateId'] ?? ''));
    if ($templateId === '') {
        set_flash('error', 'Template not found.');
        redirect('/contractor/templates.php');
    }

    $template = load_global_template($templateId);
    if (!$template) {
        set_flash('error', 'Template not found.');
        redirect('/contractor/templates.php');
    }

    $copyId = generate_contractor_template_id($yojId);
    $template['templateId'] = $copyId;
    $template['scope'] = 'contractor';
    $template['owner'] = ['yojId' => $yojId];
    $template['status'] = 'active';
    $template['createdAt'] = now_kolkata()->format(DateTime::ATOM);
    $template['updatedAt'] = now_kolkata()->format(DateTime::ATOM);

    save_contractor_template($yojId, $template);
    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_duplicated',
        'yojId' => $yojId,
        'sourceTemplateId' => $templateId,
        'templateId' => $copyId,
    ]);

    set_flash('success', 'Template duplicated into My Templates.');
    redirect('/contractor/templates.php#my-templates');
});
