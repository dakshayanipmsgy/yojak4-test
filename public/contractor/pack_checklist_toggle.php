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
    $itemId = trim($_POST['itemId'] ?? '');
    $status = trim($_POST['status'] ?? 'pending');

    $allowedStatuses = ['pending', 'done'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'pending';
    }

    if ($packId === '' || $itemId === '') {
        render_error_page('Invalid checklist update request.');
        return;
    }

    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);
    $pack = load_pack($yojId, $packId, $context);
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    $updated = false;
    foreach ($pack['checklist'] ?? [] as &$entry) {
        if (($entry['itemId'] ?? ($entry['id'] ?? '')) === $itemId) {
            $entry['status'] = $status;
            $updated = true;
            break;
        }
    }
    unset($entry);

    foreach ($pack['items'] ?? [] as &$entry) {
        if (($entry['itemId'] ?? '') === $itemId) {
            $entry['status'] = $status;
        }
    }
    unset($entry);

    if (!$updated) {
        set_flash('error', 'Checklist item not found.');
        redirect('/contractor/pack_view.php?packId=' . urlencode($packId) . '#checklist-toggle');
        return;
    }

    $pack['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    save_pack($pack, $context);

    set_flash('success', 'Checklist updated.');
    redirect('/contractor/pack_view.php?packId=' . urlencode($packId) . '#checklist-toggle');
});
