<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_csrf();
    require_role('superadmin');

    $tplId = trim((string)($_POST['tplId'] ?? ''));
    $requestId = trim((string)($_POST['requestId'] ?? ''));
    $scope = trim((string)($_POST['scope'] ?? 'global'));
    $ownerYoj = trim((string)($_POST['owner_yoj'] ?? ''));

    $isNew = $tplId === '';
    $template = $tplId !== '' ? load_global_template($tplId) : null;

    $payload = [
        'title' => trim((string)($_POST['title'] ?? '')),
        'category' => trim((string)($_POST['category'] ?? 'General')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'body' => trim((string)($_POST['body'] ?? '')),
        'published' => ($_POST['published'] ?? '1') === '1',
    ];

    if (!empty($_POST['apply_json']) && !empty($_POST['json_payload'])) {
        $decoded = json_decode((string)$_POST['json_payload'], true);
        if (!is_array($decoded)) {
            set_flash('error', 'Invalid JSON payload.');
            redirect('/superadmin/template_edit.php' . ($tplId ? '?id=' . urlencode($tplId) : ''));
        }
        $tokens = template_placeholder_tokens((string)($decoded['body'] ?? ''));
        $payload = array_merge($payload, [
            'title' => trim((string)($decoded['title'] ?? '')),
            'category' => trim((string)($decoded['category'] ?? 'General')),
            'description' => trim((string)($decoded['description'] ?? '')),
            'body' => trim((string)($decoded['body'] ?? '')),
            'templateType' => $decoded['templateType'] ?? 'simple_html',
            'fieldRefs' => $decoded['fieldRefs'] ?? array_values(array_filter($tokens, static fn($key) => !str_starts_with($key, 'table:'))),
            'published' => ($decoded['published'] ?? true) ? true : false,
        ]);
    }

    if ($payload['title'] === '' || $payload['body'] === '') {
        set_flash('error', 'Title and body are required.');
        redirect('/superadmin/template_edit.php' . ($tplId ? '?id=' . urlencode($tplId) : ''));
    }

    $stats = [];
    $payload['body'] = migrate_placeholders_to_canonical($payload['body'], $stats);
    $errors = template_body_errors($payload['body']);
    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/superadmin/template_edit.php' . ($tplId ? '?id=' . urlencode($tplId) : ''));
    }

    $registry = placeholder_registry();
    $validation = validate_placeholders($payload['body'], $registry);
    if (!empty($validation['invalidTokens'])) {
        set_flash('error', 'Invalid placeholders: ' . implode(', ', $validation['invalidTokens']));
        redirect('/superadmin/template_edit.php' . ($tplId ? '?id=' . urlencode($tplId) : ''));
    }
    if (!empty($validation['unknownKeys'])) {
        set_flash('warning', 'Unknown fields: ' . implode(', ', $validation['unknownKeys']) . '. Add them to the Field Registry before publishing.');
    }

    if ($isNew) {
        if ($scope === 'contractor') {
            if ($ownerYoj === '') {
                set_flash('error', 'Contractor YOJ ID is required for contractor scope.');
                redirect('/superadmin/template_edit.php');
            }
            $tplId = generate_contractor_template_id_v2($ownerYoj);
            $template = [
                'id' => $tplId,
                'tplId' => $tplId,
                'scope' => 'contractor',
                'owner' => ['yojId' => $ownerYoj],
            ];
        } else {
            $tplId = generate_global_template_id();
            $template = [
                'id' => $tplId,
                'scope' => 'global',
                'owner' => ['yojId' => $ownerYoj],
            ];
        }
    }

    $template['title'] = $payload['title'];
    $template['category'] = $payload['category'];
    $template['description'] = $payload['description'];
    $template['templateType'] = $payload['templateType'] ?? ($template['templateType'] ?? 'simple_html');
    $template['body'] = $payload['body'];
    $tokens = template_placeholder_tokens($payload['body']);
    $template['fieldRefs'] = $payload['fieldRefs'] ?? array_values(array_filter($tokens, static fn($key) => !str_starts_with($key, 'table:')));
    $template['published'] = $payload['published'];

    if (($template['scope'] ?? 'global') === 'contractor') {
        $owner = $template['owner']['yojId'] ?? $ownerYoj;
        if ($owner === '') {
            set_flash('error', 'Contractor YOJ ID missing.');
            redirect('/superadmin/template_edit.php');
        }
        $template['tplId'] = $template['tplId'] ?? $tplId;
        $template['name'] = $template['title'];
        save_contractor_template($owner, $template);
        logEvent(DATA_PATH . '/logs/templates.log', [
            'event' => $isNew ? 'template_created' : 'template_updated',
            'scope' => 'contractor',
            'yojId' => $owner,
            'tplId' => $tplId,
        ]);
    } else {
        save_global_template($template);
        logEvent(DATA_PATH . '/logs/templates.log', [
            'event' => $isNew ? 'template_created' : 'template_updated',
            'scope' => 'global',
            'tplId' => $tplId,
        ]);
    }

    if ($requestId !== '') {
        $request = load_request($requestId);
        if ($request) {
            $request['status'] = 'delivered';
            $request['delivered'] = [
                'scope' => $template['scope'] ?? 'global',
                'entityId' => $tplId,
            ];
            save_request($request);
            logEvent(DATA_PATH . '/logs/requests.log', [
                'event' => 'request_delivered',
                'requestId' => $requestId,
                'entityId' => $tplId,
                'scope' => $template['scope'] ?? 'global',
            ]);
        }
    }

    set_flash('success', 'Template saved.');
    if (($template['scope'] ?? 'global') === 'contractor') {
        redirect('/superadmin/templates.php');
    }
    redirect('/superadmin/template_edit.php?id=' . urlencode($tplId));
});
