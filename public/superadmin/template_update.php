<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/templates.php');
    }
    require_csrf();

    $actor = require_superadmin_or_permission('template_manager');
    $scope = trim((string)($_POST['scope'] ?? ($_GET['scope'] ?? 'global')));
    if (!in_array($scope, ['global', 'contractor'], true)) {
        $scope = 'global';
    }
    $yojId = trim((string)($_GET['yojId'] ?? ''));
    if ($scope === 'contractor' && $yojId === '') {
        render_error_page('Contractor YOJ ID is required.');
        return;
    }
    $templateId = trim((string)($_POST['templateId'] ?? ''));
    if ($templateId === '') {
        render_error_page('Template ID missing.');
        return;
    }

    $existing = load_template_library_record($scope, $yojId !== '' ? $yojId : null, $templateId);
    if (!$existing) {
        render_error_page('Template not found.');
        return;
    }

    $canAdvanced = ($actor['type'] ?? '') === 'superadmin' || employee_has_permission($actor, 'template_manager_advanced');
    $applyJson = isset($_POST['apply_json']) && $canAdvanced;

    if ($applyJson) {
        $raw = trim((string)($_POST['advanced_json'] ?? ''));
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            set_flash('error', 'Invalid JSON payload.');
            redirect('/superadmin/template_edit.php?scope=' . urlencode($scope) . '&templateId=' . urlencode($templateId) . ($yojId ? '&yojId=' . urlencode($yojId) : ''));
        }
        if (template_library_payload_has_forbidden_terms($payload)) {
            set_flash('error', 'JSON payload contains restricted bid/rate fields.');
            redirect('/superadmin/template_edit.php?scope=' . urlencode($scope) . '&templateId=' . urlencode($templateId) . ($yojId ? '&yojId=' . urlencode($yojId) : ''));
        }
        $payload['templateId'] = $templateId;
        $payload['scope'] = $scope;
        if ($scope === 'contractor') {
            $payload['owner'] = ['yojId' => $yojId];
        }
        $payload['createdAt'] = $existing['createdAt'] ?? null;
        save_template_library_record($payload, $scope, $yojId !== '' ? $yojId : null);
        logEvent(DATA_PATH . '/logs/templates.log', [
            'event' => 'template_updated_json',
            'templateId' => $templateId,
            'scope' => $scope,
            'yojId' => $yojId,
            'actor' => $actor['username'] ?? ($actor['empId'] ?? 'staff'),
        ]);
        set_flash('success', 'Template updated from JSON.');
        redirect('/superadmin/templates.php');
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'Tender'));
    $description = trim((string)($_POST['description'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'active'));
    $fieldCatalogRaw = (string)($_POST['field_catalog'] ?? '[]');
    $fieldCatalog = json_decode($fieldCatalogRaw, true);
    if (!is_array($fieldCatalog)) {
        $fieldCatalog = [];
    }

    if ($title === '' || $body === '') {
        set_flash('error', 'Title and body are required.');
        redirect('/superadmin/template_edit.php?scope=' . urlencode($scope) . '&templateId=' . urlencode($templateId) . ($yojId ? '&yojId=' . urlencode($yojId) : ''));
    }

    $template = array_merge($existing, [
        'title' => $title,
        'category' => $category,
        'description' => $description,
        'body' => $body,
        'fieldCatalog' => $fieldCatalog,
        'status' => $status === 'archived' ? 'archived' : 'active',
    ]);

    if (template_library_payload_has_forbidden_terms($template)) {
        set_flash('error', 'Template contains restricted bid/rate fields.');
        redirect('/superadmin/template_edit.php?scope=' . urlencode($scope) . '&templateId=' . urlencode($templateId) . ($yojId ? '&yojId=' . urlencode($yojId) : ''));
    }

    save_template_library_record($template, $scope, $yojId !== '' ? $yojId : null);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_updated',
        'templateId' => $templateId,
        'scope' => $scope,
        'yojId' => $yojId,
        'actor' => $actor['username'] ?? ($actor['empId'] ?? 'staff'),
    ]);

    set_flash('success', 'Template updated successfully.');
    redirect('/superadmin/templates.php');
});
