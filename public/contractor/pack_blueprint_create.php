<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $items = pack_blueprint_items_from_post($_POST);

    if ($title === '' || !$items) {
        set_flash('error', 'Title and at least one item are required.');
        redirect('/contractor/pack_blueprint_new.php');
    }

    $packId = generate_contractor_pack_id_v2($yojId);
    $pack = [
        'id' => $packId,
        'scope' => 'contractor',
        'owner' => ['yojId' => $yojId],
        'title' => $title,
        'description' => $description,
        'items' => $items,
        'published' => true,
    ];

    save_contractor_pack_blueprint($yojId, $pack);

    logEvent(DATA_PATH . '/logs/packs.log', [
        'event' => 'pack_blueprint_created',
        'scope' => 'contractor',
        'yojId' => $yojId,
        'packId' => $packId,
    ]);

    set_flash('success', 'Pack blueprint created.');
    redirect('/contractor/pack_blueprint_edit.php?id=' . urlencode($packId));
});
