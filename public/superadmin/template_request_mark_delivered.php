<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/template_requests.php');
    }
    require_csrf();

    $actor = require_superadmin_or_permission('template_manager');
    $requestId = trim((string)($_POST['requestId'] ?? ''));
    if ($requestId === '') {
        render_error_page('Request ID missing.');
        return;
    }

    $templateIds = array_filter(array_map('trim', explode(',', (string)($_POST['createdTemplateIds'] ?? ''))));
    $packIds = array_filter(array_map('trim', explode(',', (string)($_POST['createdPackTemplateIds'] ?? ''))));

    $updated = update_template_request_status($requestId, [
        'status' => 'delivered',
        'result' => [
            'createdTemplateIds' => array_values($templateIds),
            'createdPackTemplateIds' => array_values($packIds),
        ],
    ]);

    if ($updated) {
        logEvent(DATA_PATH . '/logs/template_requests.log', [
            'event' => 'request_delivered',
            'requestId' => $requestId,
            'createdTemplateIds' => $templateIds,
            'createdPackTemplateIds' => $packIds,
            'actor' => $actor['username'] ?? ($actor['empId'] ?? 'staff'),
        ]);
        set_flash('success', 'Request marked as delivered.');
    }

    redirect('/superadmin/template_request_view.php?requestId=' . urlencode($requestId));
});
