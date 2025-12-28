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
    $fileId = trim($_POST['fileId'] ?? '');
    $reason = trim($_POST['reason'] ?? 'Manually linked');
    if (strlen($reason) > 240) {
        $reason = substr($reason, 0, 240);
    }
    $confidence = (float)($_POST['confidence'] ?? 1);

    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);

    $pack = $packId !== '' ? load_pack($yojId, $packId, $context) : null;
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    if ($itemId === '' || $fileId === '') {
        set_flash('error', 'Select an item and vault document to map.');
        redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
        return;
    }

    $vaultFiles = contractor_vault_index($yojId);
    $match = null;
    foreach ($vaultFiles as $file) {
        if (($file['fileId'] ?? '') === $fileId && empty($file['deletedAt'])) {
            $match = $file;
            break;
        }
    }
    if (!$match) {
        set_flash('error', 'Vault document not found or deleted.');
        redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
        return;
    }

    $mappings = $pack['vaultMappings'] ?? [];
    $updated = [];
    foreach ($mappings as $map) {
        if (($map['checklistItemId'] ?? '') === $itemId) {
            continue;
        }
        $updated[] = $map;
    }
    $updated[] = [
        'checklistItemId' => $itemId,
        'suggestedVaultDocId' => $fileId,
        'confidence' => max(0, min(1, $confidence)),
        'reason' => $reason !== '' ? $reason : 'Manually linked',
        'fileTitle' => $match['title'] ?? 'Vault document',
    ];

    $pack['vaultMappings'] = $updated;
    $pack['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    save_pack($pack, $context);

    pack_log([
        'event' => 'vault_mapping_updated',
        'yojId' => $yojId,
        'packId' => $packId,
        'itemId' => $itemId,
        'fileId' => $fileId,
    ]);

    set_flash('success', 'Vault document mapped to checklist item.');
    redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
});
