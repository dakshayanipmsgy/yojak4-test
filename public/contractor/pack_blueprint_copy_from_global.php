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

    $global = load_global_pack($packId);
    if (!$global) {
        render_error_page('Pack blueprint not found.');
        return;
    }

    $newId = generate_contractor_pack_id_v2($yojId);
    $pack = [
        'id' => $newId,
        'scope' => 'contractor',
        'owner' => ['yojId' => $yojId],
        'title' => $global['title'] ?? 'Pack Blueprint',
        'description' => $global['description'] ?? '',
        'items' => $global['items'] ?? [],
        'published' => true,
    ];

    save_contractor_pack_blueprint($yojId, $pack);

    logEvent(DATA_PATH . '/logs/packs.log', [
        'event' => 'pack_blueprint_copied_from_global',
        'scope' => 'contractor',
        'yojId' => $yojId,
        'packId' => $newId,
        'sourcePackId' => $packId,
    ]);

    set_flash('success', 'Pack blueprint copied. You can now customize it.');
    redirect('/contractor/pack_blueprint_edit.php?id=' . urlencode($newId));
});
