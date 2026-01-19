<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/packs.php');
    }
    require_csrf();

    $actor = require_superadmin_or_permission('pack_manager');
    $scope = trim((string)($_POST['scope'] ?? ($_GET['scope'] ?? 'global')));
    if (!in_array($scope, ['global', 'contractor'], true)) {
        $scope = 'global';
    }
    $yojId = trim((string)($_GET['yojId'] ?? ''));
    if ($scope === 'contractor' && $yojId === '') {
        render_error_page('Contractor YOJ ID is required.');
        return;
    }

    $canAdvanced = ($actor['type'] ?? '') === 'superadmin' || employee_has_permission($actor, 'pack_manager_advanced');
    $applyJson = isset($_POST['apply_json']) && $canAdvanced;

    if ($applyJson) {
        $raw = trim((string)($_POST['advanced_json'] ?? ''));
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            set_flash('error', 'Invalid JSON payload.');
            redirect('/superadmin/pack_template_new.php?scope=' . urlencode($scope) . ($yojId ? '&yojId=' . urlencode($yojId) : ''));
        }
        if (pack_template_payload_has_forbidden_terms($payload)) {
            set_flash('error', 'JSON payload contains restricted bid/rate fields.');
            redirect('/superadmin/pack_template_new.php?scope=' . urlencode($scope) . ($yojId ? '&yojId=' . urlencode($yojId) : ''));
        }
        $payload['packTemplateId'] = $payload['packTemplateId'] ?? generate_pack_template_id();
        $payload['scope'] = $scope;
        if ($scope === 'contractor') {
            $payload['owner'] = ['yojId' => $yojId];
        }
        save_pack_template_record($payload, $scope, $yojId !== '' ? $yojId : null);
        logEvent(DATA_PATH . '/logs/packs.log', [
            'event' => 'pack_template_created_json',
            'packTemplateId' => $payload['packTemplateId'],
            'scope' => $scope,
            'yojId' => $yojId,
            'actor' => $actor['username'] ?? ($actor['empId'] ?? 'staff'),
        ]);
        set_flash('success', 'Pack template created from JSON.');
        redirect('/superadmin/packs.php');
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'active'));
    $itemsRaw = (string)($_POST['items'] ?? '[]');
    $items = json_decode($itemsRaw, true);
    if (!is_array($items)) {
        $items = [];
    }
    $fieldCatalogRaw = (string)($_POST['field_catalog'] ?? '[]');
    $fieldCatalog = json_decode($fieldCatalogRaw, true);
    if (!is_array($fieldCatalog)) {
        $fieldCatalog = [];
    }

    if ($title === '') {
        set_flash('error', 'Pack title is required.');
        redirect('/superadmin/pack_template_new.php?scope=' . urlencode($scope) . ($yojId ? '&yojId=' . urlencode($yojId) : ''));
    }

    $pack = [
        'packTemplateId' => generate_pack_template_id(),
        'scope' => $scope,
        'owner' => $scope === 'contractor' ? ['yojId' => $yojId] : null,
        'title' => $title,
        'description' => $description,
        'items' => $items,
        'fieldCatalog' => $fieldCatalog,
        'status' => $status === 'archived' ? 'archived' : 'active',
    ];

    if (pack_template_payload_has_forbidden_terms($pack)) {
        set_flash('error', 'Pack template contains restricted bid/rate fields.');
        redirect('/superadmin/pack_template_new.php?scope=' . urlencode($scope) . ($yojId ? '&yojId=' . urlencode($yojId) : ''));
    }

    save_pack_template_record($pack, $scope, $yojId !== '' ? $yojId : null);

    logEvent(DATA_PATH . '/logs/packs.log', [
        'event' => 'pack_template_created',
        'packTemplateId' => $pack['packTemplateId'] ?? '',
        'scope' => $scope,
        'yojId' => $yojId,
        'actor' => $actor['username'] ?? ($actor['empId'] ?? 'staff'),
    ]);

    set_flash('success', 'Pack template created successfully.');
    redirect('/superadmin/packs.php');
});
