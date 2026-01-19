<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_csrf();
    require_role('superadmin');

    $packId = trim((string)($_POST['packId'] ?? ''));
    $requestId = trim((string)($_POST['requestId'] ?? ''));
    $scope = trim((string)($_POST['scope'] ?? 'global'));
    $ownerYoj = trim((string)($_POST['owner_yoj'] ?? ''));

    $isNew = $packId === '';
    $pack = $packId !== '' ? load_global_pack($packId) : null;

    $payload = [
        'title' => trim((string)($_POST['title'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'items' => pack_blueprint_items_from_post($_POST),
    ];

    if (!empty($_POST['apply_json']) && !empty($_POST['json_payload'])) {
        $decoded = json_decode((string)$_POST['json_payload'], true);
        if (!is_array($decoded)) {
            set_flash('error', 'Invalid JSON payload.');
            redirect('/superadmin/pack_edit.php' . ($packId ? '?id=' . urlencode($packId) : ''));
        }
        $payload = array_merge($payload, [
            'title' => trim((string)($decoded['title'] ?? '')),
            'description' => trim((string)($decoded['description'] ?? '')),
            'items' => $decoded['items'] ?? [],
            'published' => ($decoded['published'] ?? true) ? true : false,
        ]);
    }

    if ($payload['title'] === '' || !$payload['items']) {
        set_flash('error', 'Title and at least one item are required.');
        redirect('/superadmin/pack_edit.php' . ($packId ? '?id=' . urlencode($packId) : ''));
    }

    if ($isNew) {
        if ($scope === 'contractor') {
            if ($ownerYoj === '') {
                set_flash('error', 'Contractor YOJ ID is required for contractor scope.');
                redirect('/superadmin/pack_edit.php');
            }
            $packId = generate_contractor_pack_id_v2($ownerYoj);
            $pack = [
                'id' => $packId,
                'scope' => 'contractor',
                'owner' => ['yojId' => $ownerYoj],
            ];
        } else {
            $packId = generate_global_pack_id();
            $pack = [
                'id' => $packId,
                'scope' => 'global',
                'owner' => ['yojId' => $ownerYoj],
            ];
        }
    }

    $pack['title'] = $payload['title'];
    $pack['description'] = $payload['description'];
    $pack['items'] = $payload['items'];
    $pack['published'] = $payload['published'] ?? ($pack['published'] ?? true);

    if (($pack['scope'] ?? 'global') === 'contractor') {
        $owner = $pack['owner']['yojId'] ?? $ownerYoj;
        if ($owner === '') {
            set_flash('error', 'Contractor YOJ ID missing.');
            redirect('/superadmin/pack_edit.php');
        }
        save_contractor_pack_blueprint($owner, $pack);
        logEvent(DATA_PATH . '/logs/packs.log', [
            'event' => $isNew ? 'pack_blueprint_created' : 'pack_blueprint_updated',
            'scope' => 'contractor',
            'yojId' => $owner,
            'packId' => $packId,
        ]);
    } else {
        save_global_pack($pack);
        logEvent(DATA_PATH . '/logs/packs.log', [
            'event' => $isNew ? 'pack_blueprint_created' : 'pack_blueprint_updated',
            'scope' => 'global',
            'packId' => $packId,
        ]);
    }

    if ($requestId !== '') {
        $request = load_request($requestId);
        if ($request) {
            $request['status'] = 'delivered';
            $request['delivered'] = [
                'scope' => $pack['scope'] ?? 'global',
                'entityId' => $packId,
            ];
            save_request($request);
            logEvent(DATA_PATH . '/logs/requests.log', [
                'event' => 'request_delivered',
                'requestId' => $requestId,
                'entityId' => $packId,
                'scope' => $pack['scope'] ?? 'global',
            ]);
        }
    }

    set_flash('success', 'Pack blueprint saved.');
    if (($pack['scope'] ?? 'global') === 'contractor') {
        redirect('/superadmin/packs.php');
    }
    redirect('/superadmin/pack_edit.php?id=' . urlencode($packId));
});
