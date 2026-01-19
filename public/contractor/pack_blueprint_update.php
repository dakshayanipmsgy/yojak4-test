<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $packId = trim((string)($_POST['packId'] ?? ''));
    if ($packId === '') {
        render_error_page('Pack blueprint not found.');
        return;
    }

    $pack = load_contractor_pack_blueprint($yojId, $packId);
    if (!$pack) {
        render_error_page('Pack blueprint not found.');
        return;
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $items = pack_blueprint_items_from_post($_POST);

    if ($title === '' || !$items) {
        set_flash('error', 'Title and at least one item are required.');
        redirect('/contractor/pack_blueprint_edit.php?id=' . urlencode($packId));
    }

    $pack['title'] = $title;
    $pack['description'] = $description;
    $pack['items'] = $items;

    save_contractor_pack_blueprint($yojId, $pack);

    logEvent(DATA_PATH . '/logs/packs.log', [
        'event' => 'pack_blueprint_updated',
        'scope' => 'contractor',
        'yojId' => $yojId,
        'packId' => $packId,
    ]);

    set_flash('success', 'Pack blueprint updated.');
    redirect('/contractor/pack_blueprint_edit.php?id=' . urlencode($packId));
});
