<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/offline_tenders.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_offline_tender_env($yojId);
    ensure_packs_env($yojId);

    $offtdId = trim($_POST['offtdId'] ?? '');
    if ($offtdId === '') {
        render_error_page('Missing tender id.');
        return;
    }

    $tender = load_offline_tender($yojId, $offtdId);
    if (!$tender || ($tender['yojId'] ?? '') !== $yojId) {
        render_error_page('Tender not found.');
        return;
    }

    $existingPack = find_pack_by_source($yojId, 'OFFTD', $offtdId);
    if ($existingPack) {
        set_flash('success', 'Pack already exists for this tender.');
        redirect('/contractor/pack_view.php?packId=' . urlencode($existingPack['packId']));
        return;
    }

    $packId = generate_pack_id($yojId);
    $now = now_kolkata()->format(DateTime::ATOM);
    $pack = [
        'packId' => $packId,
        'yojId' => $yojId,
        'title' => $tender['title'] ?? 'Tender Pack',
        'sourceTender' => [
            'type' => 'OFFTD',
            'id' => $offtdId,
        ],
        'createdAt' => $now,
        'updatedAt' => $now,
        'status' => 'Pending',
        'items' => pack_items_from_checklist($tender['checklist'] ?? []),
        'generatedDocs' => [],
    ];

    save_pack($pack);
    pack_log([
        'event' => 'pack_created',
        'yojId' => $yojId,
        'packId' => $packId,
        'sourceType' => 'OFFTD',
        'sourceId' => $offtdId,
    ]);

    set_flash('success', 'Tender pack created.');
    redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
});
