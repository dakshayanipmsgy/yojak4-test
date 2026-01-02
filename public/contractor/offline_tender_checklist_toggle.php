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

    $offtdId = trim($_POST['id'] ?? '');
    $itemId = trim($_POST['itemId'] ?? '');
    $status = trim($_POST['status'] ?? 'pending');
    $allowedStatuses = ['pending', 'done'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'pending';
    }

    $tender = $offtdId !== '' ? load_offline_tender($yojId, $offtdId) : null;
    if (!$tender || ($tender['yojId'] ?? '') !== $yojId) {
        render_error_page('Tender not found.');
        return;
    }

    $updated = false;
    foreach ($tender['checklist'] ?? [] as &$item) {
        if (($item['itemId'] ?? '') === $itemId) {
            $item['status'] = $status;
            $updated = true;
            break;
        }
    }
    unset($item);

    if (!$updated) {
        set_flash('error', 'Checklist item not found.');
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId) . '#assisted-checklist');
        return;
    }

    $tender['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    save_offline_tender($tender);

    $pack = find_pack_by_source($yojId, 'OFFTD', $offtdId);
    if ($pack) {
        $context = detect_pack_context($pack['packId']);
        foreach ($pack['checklist'] ?? [] as &$entry) {
            if (($entry['itemId'] ?? '') === $itemId) {
                $entry['status'] = $status;
            }
        }
        unset($entry);
        foreach ($pack['items'] ?? [] as &$entry) {
            if (($entry['itemId'] ?? '') === $itemId) {
                $entry['status'] = $status;
            }
        }
        unset($entry);
        $pack['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
        save_pack($pack, $context);
    }

    set_flash('success', 'Checklist updated.');
    redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId) . '#assisted-checklist');
});
