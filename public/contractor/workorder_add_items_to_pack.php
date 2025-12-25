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
    $workorder = $woId !== '' ? load_workorder($yojId, $woId) : null;
    if (!$workorder || ($workorder['yojId'] ?? '') !== $yojId) {
        render_error_page('Workorder not found.');
        return;
    }

    $pack = null;
    $packId = $workorder['linkedPackId'] ?? null;
    if ($packId) {
        $pack = load_pack($yojId, $packId, 'workorder');
    }
    if (!$pack) {
        $packId = generate_pack_id($yojId, 'workorder');
        $pack = [
            'packId' => $packId,
            'yojId' => $yojId,
            'title' => $workorder['title'] ?? 'Workorder Pack',
            'sourceTender' => [
                'type' => 'WORKORDER',
                'id' => $woId,
            ],
            'createdAt' => now_kolkata()->format(DateTime::ATOM),
            'updatedAt' => now_kolkata()->format(DateTime::ATOM),
            'status' => 'Pending',
            'items' => [],
            'generatedDocs' => [],
        ];
    }

    $existingTitles = [];
    foreach ($pack['items'] ?? [] as $item) {
        $existingTitles[strtolower($item['title'] ?? '')] = true;
    }

    $newItems = [];
    foreach ($workorder['obligationsChecklist'] ?? [] as $ob) {
        $title = trim((string)($ob['title'] ?? ''));
        if ($title === '' || isset($existingTitles[strtolower($title)])) {
            continue;
        }
        $existingTitles[strtolower($title)] = true;
        $newItems[] = [
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
        if ($name === '' || isset($existingTitles[strtolower($name)])) {
            continue;
        }
        $existingTitles[strtolower($name)] = true;
        $newItems[] = [
            'itemId' => generate_pack_item_id(),
            'title' => $name,
            'description' => trim((string)($doc['notes'] ?? '')),
            'required' => true,
            'status' => 'pending',
            'fileRefs' => [],
        ];
    }

    if ($newItems) {
        $pack['items'] = array_merge($pack['items'] ?? [], $newItems);
        $pack['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
        save_pack($pack, 'workorder');
        $workorder['linkedPackId'] = $pack['packId'];
        $workorder['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
        save_workorder($workorder);
        workorder_log([
            'event' => 'pack_items_added',
            'yojId' => $yojId,
            'woId' => $woId,
            'packId' => $pack['packId'],
            'count' => count($newItems),
        ]);
        set_flash('success', count($newItems) . ' item(s) added to pack.');
    } else {
        set_flash('error', 'No new items to add to the pack.');
    }

    redirect('/contractor/pack_view.php?packId=' . urlencode($pack['packId']));
});
