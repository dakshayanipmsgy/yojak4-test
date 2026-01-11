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
    $packId = trim((string)($_POST['packId'] ?? ''));
    if ($packId === '') {
        render_error_page('Invalid pack update request.');
        return;
    }

    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);
    $pack = load_pack($yojId, $packId, $context);
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    $fields = $_POST['fields'] ?? [];
    if (!is_array($fields)) {
        render_error_page('Invalid fields payload.');
        return;
    }

    $catalog = pack_editable_field_catalog();
    $updatedCount = 0;
    foreach ($fields as $key => $value) {
        $normalized = pack_normalize_placeholder_key((string)$key);
        if (!isset($catalog[$normalized])) {
            continue;
        }
        $max = (int)$catalog[$normalized]['max'];
        $clean = trim(strip_tags((string)$value));
        if ($max > 0 && function_exists('mb_substr')) {
            $clean = mb_substr($clean, 0, $max);
        } elseif ($max > 0) {
            $clean = substr($clean, 0, $max);
        }
        $existing = trim((string)($pack['fieldOverrides'][$normalized] ?? ''));
        if ($clean === '') {
            if ($existing !== '') {
                unset($pack['fieldOverrides'][$normalized]);
                $updatedCount++;
            }
            continue;
        }
        if ($existing !== $clean) {
            $pack['fieldOverrides'][$normalized] = $clean;
            $updatedCount++;
        }
    }

    if ($updatedCount > 0) {
        $pack['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
        $pack['audit'][] = [
            'at' => now_kolkata()->format(DateTime::ATOM),
            'event' => 'FIELDS_UPDATED',
            'yojId' => $yojId,
            'countUpdated' => $updatedCount,
        ];
        save_pack($pack, $context);
    }

    pack_log([
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => 'PACK_FIELDS_SAVE',
        'yojId' => $yojId,
        'packId' => $packId,
        'countUpdated' => $updatedCount,
    ]);

    set_flash('success', $updatedCount > 0 ? 'Pack fields saved.' : 'No changes applied.');
    redirect('/contractor/pack_view.php?packId=' . urlencode($packId) . '#fill-missing');
});
