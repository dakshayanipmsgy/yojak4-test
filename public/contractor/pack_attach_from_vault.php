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

    if ($packId === '' || $itemId === '' || $vaultDocId === '') {
        set_flash('error', 'Select a checklist item and vault document.');
        redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
        return;
    }

    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);
    $pack = load_pack($yojId, $packId, $context);
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    $itemExists = false;
    foreach ($pack['items'] ?? [] as $item) {
        if (($item['itemId'] ?? '') === $itemId || ($item['id'] ?? '') === $itemId) {
            $itemExists = true;
            break;
        }
    }
    if (!$itemExists) {
        set_flash('error', 'Checklist item not found.');
        redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
        return;
    }

    $vaultFiles = contractor_vault_index($yojId);
    $match = null;
    foreach ($vaultFiles as $file) {
        if (($file['fileId'] ?? '') === $vaultDocId && empty($file['deletedAt'])) {
            $match = $file;
            break;
        }
    }
    if (!$match) {
        set_flash('error', 'Vault document not found or deleted.');
        redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
        return;
    }

    $attachments = $pack['attachmentsPlan'] ?? [];
    $updated = [];
    foreach ($attachments as $entry) {
        if (($entry['checklistItemId'] ?? '') === $itemId) {
            continue;
        }
        $updated[] = $entry;
    }
    $updated[] = [
        'checklistItemId' => $itemId,
        'vaultDocId' => $vaultDocId,
        'fileName' => $match['title'] ?? 'Vault document',
        'attachedAt' => now_kolkata()->format(DateTime::ATOM),
    ];
    $pack['attachmentsPlan'] = $updated;
    $pack['missingChecklistItemIds'] = pack_missing_checklist_item_ids($pack, pack_attachment_map($pack, $vaultFiles));
    $pack['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    save_pack($pack, $context);

    upsert_missing_docs_reminder($yojId, $pack, count($pack['missingChecklistItemIds'] ?? []));

    logEvent(DATA_PATH . '/logs/vault.log', [
        'event' => 'pack_attach_from_vault',
        'yojId' => $yojId,
        'packId' => $packId,
        'itemId' => $itemId,
        'vaultDocId' => $vaultDocId,
    ]);

    set_flash('success', 'Vault document attached to checklist item.');
    redirect('/contractor/pack_view.php?packId=' . urlencode($packId) . '#missing-docs');
});
