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
    $key = trim((string)($_POST['key'] ?? ''));
    $value = trim((string)($_POST['value'] ?? ''));

    if ($packId === '' || $key === '') {
        render_error_page('Invalid toggle request.');
        return;
    }

    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);
    $pack = load_pack($yojId, $packId, $context);
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    $annexureTemplates = load_pack_annexures($yojId, $packId, $context);
    $contractorTemplates = load_contractor_templates_full($yojId);
    $catalog = pack_field_meta_catalog($pack, $annexureTemplates, $contractorTemplates);
    $normalized = pack_normalize_placeholder_key($key);
    if (!isset($catalog[$normalized]) || ($catalog[$normalized]['type'] ?? '') !== 'choice') {
        render_error_page('Invalid field toggle.');
        return;
    }

    $choices = array_map('strtolower', $catalog[$normalized]['choices'] ?? []);
    $value = strtolower($value);
    if (!in_array($value, $choices, true)) {
        render_error_page('Invalid choice value.');
        return;
    }

    $existing = trim((string)($pack['fieldRegistry'][$normalized] ?? ''));
    if ($existing !== $value) {
        $pack['fieldRegistry'][$normalized] = $value;
        $pack['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
        $pack['audit'][] = [
            'at' => now_kolkata()->format(DateTime::ATOM),
            'event' => 'PACK_FIELDS_UPDATED',
            'yojId' => $yojId,
            'fieldsUpdatedCount' => 1,
        ];
        save_pack($pack, $context);
    }

    pack_log([
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => 'PACK_FIELDS_UPDATED',
        'yojId' => $yojId,
        'packId' => $packId,
        'fieldsUpdatedCount' => 1,
    ]);

    redirect('/contractor/pack_view.php?packId=' . urlencode($packId) . '#field-registry');
});
