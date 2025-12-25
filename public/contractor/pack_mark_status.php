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
    ensure_packs_env($yojId);

    $packId = trim($_POST['packId'] ?? '');
    $pack = $packId !== '' ? load_pack($yojId, $packId) : null;
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    $statuses = $_POST['statuses'] ?? [];
    $allowedStatuses = ['pending', 'uploaded', 'generated', 'done'];
    $items = $pack['items'] ?? [];
    foreach ($items as &$item) {
        $itemId = $item['itemId'] ?? '';
        if ($itemId === '' || !isset($statuses[$itemId])) {
            continue;
        }
        $newStatus = trim((string)$statuses[$itemId]);
        if (in_array($newStatus, $allowedStatuses, true)) {
            $item['status'] = $newStatus;
        }
    }
    unset($item);

    $pack['items'] = $items;
    $pack['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    save_pack($pack);

    pack_log([
        'event' => 'statuses_updated',
        'yojId' => $yojId,
        'packId' => $packId,
    ]);

    set_flash('success', 'Statuses updated.');
    redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
});
