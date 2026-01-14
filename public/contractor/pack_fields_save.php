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

    $annexureTemplates = load_pack_annexures($yojId, $packId, $context);
    $contractorTemplates = load_contractor_templates_full($yojId);
    $catalog = pack_field_meta_catalog($pack, $annexureTemplates, $contractorTemplates);
    $financialBlocked = array_flip(pack_financial_manual_field_keys($annexureTemplates));
    $updatedCount = 0;
    $errors = [];
    foreach ($fields as $key => $value) {
        $normalized = pack_normalize_placeholder_key((string)$key);
        if (!isset($catalog[$normalized])) {
            continue;
        }
        if (isset($financialBlocked[$normalized])) {
            continue;
        }
        if (!empty($catalog[$normalized]['readOnly'])) {
            continue;
        }
        $max = (int)($catalog[$normalized]['max'] ?? 0);
        $clean = trim(strip_tags((string)$value));
        if ($max > 0 && function_exists('mb_substr')) {
            $clean = mb_substr($clean, 0, $max);
        } elseif ($max > 0) {
            $clean = substr($clean, 0, $max);
        }
        if ($clean !== '' && $normalized === 'contact.email' && !filter_var($clean, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email for ' . $catalog[$normalized]['label'];
            continue;
        }
        if ($clean !== '' && $normalized === 'bank.ifsc' && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/i', $clean)) {
            $errors[] = 'Invalid IFSC format';
            continue;
        }
        if (($catalog[$normalized]['type'] ?? '') === 'choice') {
            $choices = array_map('strtolower', $catalog[$normalized]['choices'] ?? []);
            $choice = strtolower($clean);
            if ($clean !== '' && !in_array($choice, $choices, true)) {
                $errors[] = 'Invalid choice for ' . $catalog[$normalized]['label'];
                continue;
            }
            $clean = $choice;
        }

        $existing = trim((string)($pack['fieldRegistry'][$normalized] ?? ''));
        if ($clean === '') {
            if ($existing !== '') {
                unset($pack['fieldRegistry'][$normalized]);
                $updatedCount++;
            }
            continue;
        }
        if ($existing !== $clean) {
            $pack['fieldRegistry'][$normalized] = $clean;
            $updatedCount++;
        }
    }

    if ($errors) {
        set_flash('error', implode(' ', array_slice($errors, 0, 3)));
        redirect('/contractor/pack_view.php?packId=' . urlencode($packId) . '#field-registry');
        return;
    }

    if ($updatedCount > 0) {
        $pack['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
        $pack['audit'][] = [
            'at' => now_kolkata()->format(DateTime::ATOM),
            'event' => 'PACK_FIELDS_UPDATED',
            'yojId' => $yojId,
            'fieldsUpdatedCount' => $updatedCount,
        ];
        save_pack($pack, $context);
    }

    pack_log([
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => 'PACK_FIELDS_UPDATED',
        'yojId' => $yojId,
        'packId' => $packId,
        'fieldsUpdatedCount' => $updatedCount,
    ]);

    set_flash('success', $updatedCount > 0 ? 'Pack fields saved.' : 'No changes applied.');
    if (($_POST['after'] ?? '') === 'print') {
        redirect('/contractor/pack_print.php?packId=' . urlencode($packId) . '&doc=full&mode=print&autoprint=1&letterhead=1');
        return;
    }
    redirect('/contractor/pack_view.php?packId=' . urlencode($packId) . '#field-registry');
});
