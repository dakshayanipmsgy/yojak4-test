<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/template_requests.php');
    }

    require_csrf();
    require_staff_actor();

    $requestId = trim((string)($_POST['requestId'] ?? ''));
    $deliverScope = trim((string)($_POST['deliver_scope'] ?? 'delivered'));
    if (!in_array($deliverScope, ['delivered', 'global'], true)) {
        $deliverScope = 'delivered';
    }

    $request = load_template_request($requestId);
    if (!$request) {
        render_error_page('Request not found.');
        return;
    }

    $yojId = $request['yojId'] ?? '';
    $contractor = $yojId !== '' ? (load_contractor($yojId) ?? []) : [];

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
        $template['templateId'] = generate_template_id($deliverScope === 'global' ? 'global' : 'contractor', $yojId);
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/superadmin/template_request_view.php?id=' . urlencode($requestId));
    }

    $scope = $deliverScope === 'global' ? 'global' : 'delivered';
    $templateId = (string)($template['templateId'] ?? '');
    if ($templateId === '') {
        $templateId = generate_template_id($scope === 'global' ? 'global' : 'contractor', $yojId);
        $template['templateId'] = $templateId;
    }

    $template['scope'] = $scope;
    $template['owner'] = ['yojId' => $scope === 'global' ? 'YOJAK' : $yojId];
    $template['visibility'] = ['contractorEditable' => $scope !== 'global'];

    if ($scope === 'global') {
        if (file_exists(template_record_path('global', $templateId))) {
            set_flash('error', 'Template ID already exists.');
            redirect('/superadmin/template_request_view.php?id=' . urlencode($requestId));
        }
        save_template_record('global', $template);
    } else {
        if (file_exists(template_record_path('contractor', $templateId, $yojId))) {
            set_flash('error', 'Template ID already exists for contractor.');
            redirect('/superadmin/template_request_view.php?id=' . urlencode($requestId));
        }
        save_template_record('contractor', $template, $yojId);
    }

    $request['status'] = 'delivered';
    $request['deliveredTemplateId'] = $templateId;
    $request['deliveredScope'] = $scope;
    save_template_request($request);

    logEvent(DATA_PATH . '/logs/template_requests.log', [
        'event' => 'request_delivered',
        'requestId' => $requestId,
        'scope' => $scope,
        'templateId' => $templateId,
    ]);

    set_flash('success', 'Template delivered.');
    redirect('/superadmin/template_request_view.php?id=' . urlencode($requestId));
});
