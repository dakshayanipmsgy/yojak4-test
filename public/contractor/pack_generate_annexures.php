<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/packs.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packId = trim($_POST['packId'] ?? '');
    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);

    $pack = $packId !== '' ? load_pack($yojId, $packId, $context) : null;
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    $contractor = load_contractor($yojId);
    if (!$contractor) {
        render_error_page('Contractor profile not found.');
        return;
    }

    $annexureList = $pack['annexureList'] ?? [];
    if (!$annexureList && !empty($pack['annexures'])) {
        $annexureList = $pack['annexures'];
    }
    if (!$annexureList) {
        set_flash('error', 'No annexures detected from NIB to generate.');
        redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
        return;
    }

    $pack = pack_generate_annexures($pack, $contractor, $context);
    $pack['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    save_pack($pack, $context);

    pack_log([
        'event' => 'annexures_generated',
        'yojId' => $yojId,
        'packId' => $packId,
        'count' => count($pack['generatedAnnexures'] ?? []),
    ]);

    set_flash('success', 'Annexure formats generated and pre-filled where possible.');
    redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
});
