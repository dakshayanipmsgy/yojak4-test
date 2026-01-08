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
    $vaultDocId = trim($_POST['vaultDocId'] ?? '');

    if ($packId === '' || $itemId === '') {
        set_flash('error', 'Missing pack or checklist item.');
        redirect('/contractor/packs.php');
        return;
    }

    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);
    $pack = load_pack($yojId, $packId, $context);
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    $updated = [];
    $removed = false;
    foreach ($pack['attachmentsPlan'] ?? [] as $entry) {
        if (($entry['checklistItemId'] ?? '') === $itemId
            && ($vaultDocId === '' || ($entry['vaultDocId'] ?? '') === $vaultDocId)) {
            $removed = true;
            continue;
        }
        $updated[] = $entry;
    }

    if (!$removed) {
        set_flash('error', 'Attachment not found.');
        redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
        return;
    }

    $pack['attachmentsPlan'] = $updated;
    $vaultFiles = contractor_vault_index($yojId);
    $pack['missingChecklistItemIds'] = pack_missing_checklist_item_ids($pack, pack_attachment_map($pack, $vaultFiles));
    $pack['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    save_pack($pack, $context);

    upsert_missing_docs_reminder($yojId, $pack, count($pack['missingChecklistItemIds'] ?? []));

    logEvent(DATA_PATH . '/logs/vault.log', [
        'event' => 'pack_detach_vault',
        'yojId' => $yojId,
        'packId' => $packId,
        'itemId' => $itemId,
        'vaultDocId' => $vaultDocId,
    ]);

    set_flash('success', 'Vault attachment removed.');
    redirect('/contractor/pack_view.php?packId=' . urlencode($packId) . '#missing-docs');
});
