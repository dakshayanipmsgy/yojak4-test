<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $actor = require_superadmin_or_permission('templates_manage');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        render_error_page('Invalid request.');
        return;
    }
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $requestId = trim((string)($_POST['requestId'] ?? ''));
    $scope = $_POST['scope'] ?? 'contractor';
    if (!in_array($scope, ['contractor', 'global'], true)) {
        $scope = 'contractor';
    }
    if ($scope === 'global' && ($actor['type'] ?? '') !== 'superadmin') {
        set_flash('error', 'Only superadmin can publish globally.');
        redirect('/superadmin/template_requests.php?requestId=' . urlencode($requestId));
    }

    $request = load_template_request($requestId);
    if (!$request) {
        render_error_page('Request not found.');
        return;
    }

    $yojId = $request['yojId'] ?? '';
    if ($yojId === '') {
        set_flash('error', 'Invalid contractor.');
        redirect('/superadmin/template_requests.php');
    }

    $templateIds = array_filter(array_map('trim', (array)($_POST['templateIds'] ?? [])));
    $packTemplateIds = array_filter(array_map('trim', (array)($_POST['packTemplateIds'] ?? [])));

    $deliveredTemplates = [];
    $deliveredPacks = [];

    foreach ($templateIds as $templateId) {
        $template = load_global_template($templateId);
        if (!$template) {
            continue;
        }
        if ($scope === 'global') {
            $deliveredTemplates[] = $templateId;
            continue;
        }
        $copyId = generate_contractor_template_id($yojId);
        $template['templateId'] = $copyId;
        $template['scope'] = 'contractor';
        $template['owner'] = ['yojId' => $yojId];
        $template['status'] = 'active';
        $template['createdAt'] = now_kolkata()->format(DateTime::ATOM);
        $template['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
        save_contractor_template($yojId, $template);
        $deliveredTemplates[] = $copyId;
    }

    foreach ($packTemplateIds as $packTemplateId) {
        $template = load_global_pack_template($packTemplateId);
        if (!$template) {
            continue;
        }
        if ($scope === 'global') {
            $deliveredPacks[] = $packTemplateId;
            continue;
        }
        $copyId = generate_pack_template_id();
        $template['packTemplateId'] = $copyId;
        $template['scope'] = 'contractor';
        $template['owner'] = ['yojId' => $yojId];
        $template['status'] = 'active';
        $template['createdAt'] = now_kolkata()->format(DateTime::ATOM);
        $template['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
        save_contractor_pack_template($yojId, $template);
        $deliveredPacks[] = $copyId;
    }

    $request['status'] = 'delivered';
    $request['deliverables'] = [
        'templateIds' => $deliveredTemplates,
        'packTemplateIds' => $deliveredPacks,
        'scope' => $scope,
    ];
    save_template_request($request);

    logEvent(TEMPLATE_REQUESTS_LOG, [
        'event' => 'template_request_delivered',
        'requestId' => $requestId,
        'yojId' => $yojId,
        'scope' => $scope,
        'templateCount' => count($deliveredTemplates),
        'packTemplateCount' => count($deliveredPacks),
    ]);

    set_flash('success', 'Request delivered.');
    redirect('/superadmin/template_requests.php?requestId=' . urlencode($requestId));
});
