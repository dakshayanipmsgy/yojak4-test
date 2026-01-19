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

    $pack['deletedAt'] = now_kolkata()->format(DateTime::ATOM);
    save_contractor_pack_blueprint($yojId, $pack);

    logEvent(DATA_PATH . '/logs/packs.log', [
        'event' => 'pack_blueprint_deleted',
        'scope' => 'contractor',
        'yojId' => $yojId,
        'packId' => $packId,
    ]);

    set_flash('success', 'Pack blueprint deleted.');
    redirect('/contractor/packs_library.php?tab=mine');
});
