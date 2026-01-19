<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/packs_blueprints.php');
    }

    require_csrf();
    require_staff_actor();

    $packTplId = trim((string)($_POST['id'] ?? ''));
    $existing = load_packtpl_record('global', $packTplId);
    if (!$existing) {
        render_error_page('Pack preset not found.');
        return;
    }

    $packTpl = null;
    $errors = [];

    if (!empty($_POST['apply_json']) && !empty($_POST['advanced_json'])) {
        $raw = json_decode((string)$_POST['advanced_json'], true);
        if (!is_array($raw)) {
            $errors[] = 'Invalid JSON.';
        } else {
            $validation = packtpl_validate_advanced_json($raw);
            $errors = $validation['errors'];
            $packTpl = $validation['packTpl'];
        }
    } else {
        $sections = [];
        $checklistItems = packtpl_parse_checklist_lines((string)($_POST['checklist_items'] ?? ''));
        if ($checklistItems) {
            $sections[] = [
                'sectionId' => 'checklist',
                'label' => 'Checklist',
                'items' => $checklistItems,
            ];
        }

        $templateIds = array_values(array_filter(array_map('strval', $_POST['template_ids'] ?? [])));
        if ($templateIds) {
            $sections[] = [
                'sectionId' => 'templates',
                'label' => 'Templates',
                'templateIds' => $templateIds,
            ];
        }

        $attachmentTags = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['attachment_tags'] ?? '')))));
        if ($attachmentTags) {
            $sections[] = [
                'sectionId' => 'attachments',
                'label' => 'Attachments',
                'allowedTags' => $attachmentTags,
            ];
        }

        $customLabel = trim((string)($_POST['custom_label'] ?? ''));
        $customItemsRaw = trim((string)($_POST['custom_items'] ?? ''));
        if ($customLabel !== '' || $customItemsRaw !== '') {
            $items = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $customItemsRaw))));
            $sections[] = [
                'sectionId' => 'custom',
                'label' => $customLabel !== '' ? $customLabel : 'Custom',
                'items' => $items,
            ];
        }

        $payload = [
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'sections' => $sections,
        ];
        $validation = packtpl_validate_payload($payload);
        $errors = $validation['errors'];
        $packTpl = $validation['packTpl'];
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/superadmin/packtpl_edit.php?id=' . urlencode($packTplId));
    }

    $packTpl['packTplId'] = $packTplId;
    $packTpl['scope'] = 'global';
    $packTpl['owner'] = ['yojId' => 'YOJAK'];

    save_packtpl_record('global', array_merge($existing, $packTpl));

    logEvent(DATA_PATH . '/logs/packs_blueprints.log', [
        'event' => 'global_packtpl_updated',
        'packTplId' => $packTplId,
    ]);

    set_flash('success', 'Global pack preset updated.');
    redirect('/superadmin/packs_blueprints.php');
});
