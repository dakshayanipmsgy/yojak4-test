<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/templates.php');
    }

    require_csrf();
    require_staff_actor();

    $contractor = [];
    $template = null;
    $errors = [];

    if (!empty($_POST['apply_json']) && !empty($_POST['advanced_json'])) {
        $raw = json_decode((string)$_POST['advanced_json'], true);
        if (!is_array($raw)) {
            $errors[] = 'Invalid JSON.';
        } else {
            $validation = template_validate_advanced_json($raw, $contractor, null);
            $errors = $validation['errors'];
            $template = $validation['template'];
        }
    } else {
        $payload = [
            'title' => $_POST['title'] ?? '',
            'category' => $_POST['category'] ?? 'Other',
            'description' => $_POST['description'] ?? '',
            'bodyHtml' => $_POST['bodyHtml'] ?? '',
        ];
        $validation = template_validate_payload($payload, $contractor, null, true);
        $errors = $validation['errors'];
        $template = $validation['template'];
        $template['templateId'] = generate_template_id('global');
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/superadmin/template_new.php');
    }

    $templateId = (string)($template['templateId'] ?? '');
    if ($templateId === '' || file_exists(template_record_path('global', $templateId))) {
        $templateId = generate_template_id('global');
    }

    $template['templateId'] = $templateId;
    $template['scope'] = 'global';
    $template['owner'] = ['yojId' => 'YOJAK'];
    $template['visibility'] = ['contractorEditable' => false];

    save_template_record('global', $template);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'global_template_created',
        'templateId' => $templateId,
    ]);

    set_flash('success', 'Global template created.');
    redirect('/superadmin/templates.php');
});
