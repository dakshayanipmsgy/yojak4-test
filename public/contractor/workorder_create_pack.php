<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/workorders.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_workorder_env($yojId);
    ensure_packs_env($yojId, 'workorder');

    $woId = trim($_POST['id'] ?? '');
    if ($woId === '') {
        render_error_page('Missing workorder id.');
        return;
    }

    $workorder = load_workorder($yojId, $woId);
    if (!$workorder || ($workorder['yojId'] ?? '') !== $yojId) {
        render_error_page('Workorder not found.');
        return;
    }

    $existingPack = $workorder['linkedPackId'] ? load_pack($yojId, $workorder['linkedPackId'], 'workorder') : find_pack_by_source($yojId, 'WORKORDER', $woId, 'workorder');
    if ($existingPack) {
        set_flash('success', 'Pack already exists for this workorder.');
        redirect('/contractor/pack_view.php?packId=' . urlencode($existingPack['packId']));
        return;
    }

    $packId = generate_pack_id($yojId, 'workorder');
    $now = now_kolkata()->format(DateTime::ATOM);

    $items = [];
    foreach ($workorder['obligationsChecklist'] ?? [] as $ob) {
        $title = trim((string)($ob['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $items[] = [
            'itemId' => $ob['itemId'] ?? generate_pack_item_id(),
            'title' => $title,
            'description' => trim((string)($ob['description'] ?? '')),
            'required' => true,
            'status' => in_array($ob['status'] ?? '', ['pending','uploaded','generated','done'], true) ? $ob['status'] : 'pending',
            'fileRefs' => [],
        ];
    }

    foreach ($workorder['requiredDocs'] ?? [] as $doc) {
        $name = trim((string)($doc['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $items[] = [
            'itemId' => generate_pack_item_id(),
            'title' => $name,
            'description' => trim((string)($doc['notes'] ?? '')),
            'required' => true,
            'status' => 'pending',
            'fileRefs' => [],
        ];
    }

    if (!$items) {
        $items = pack_items_from_checklist([]);
    }

    $pack = [
        'packId' => $packId,
        'yojId' => $yojId,
        'title' => $workorder['title'] ?? 'Workorder Pack',
        'sourceTender' => [
            'type' => 'WORKORDER',
            'id' => $woId,
        ],
        'createdAt' => $now,
        'updatedAt' => $now,
        'status' => 'Pending',
        'items' => $items,
        'generatedDocs' => [],
    ];

    save_pack($pack, 'workorder');

    $workorder['linkedPackId'] = $packId;
    $workorder['updatedAt'] = $now;
    save_workorder($workorder);

    workorder_log([
        'event' => 'pack_created',
        'yojId' => $yojId,
        'woId' => $woId,
        'packId' => $packId,
    ]);

    set_flash('success', 'Workorder pack created.');
    redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
});
