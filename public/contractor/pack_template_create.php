<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/packs_library.php');
    }
    require_csrf();

    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $itemsRaw = (string)($_POST['items'] ?? '[]');
    $items = json_decode($itemsRaw, true);
    if (!is_array($items)) {
        $items = [];
    }
    $fieldCatalogRaw = (string)($_POST['field_catalog'] ?? '[]');
    $fieldCatalog = json_decode($fieldCatalogRaw, true);
    if (!is_array($fieldCatalog)) {
        $fieldCatalog = [];
    }

    if ($title === '') {
        set_flash('error', 'Pack title is required.');
        redirect('/contractor/pack_template_new.php');
    }

    $pack = [
        'packTemplateId' => generate_pack_template_id(),
        'scope' => 'contractor',
        'owner' => ['yojId' => $yojId],
        'title' => $title,
        'description' => $description,
        'items' => $items,
        'fieldCatalog' => $fieldCatalog,
        'status' => 'active',
    ];

    if (pack_template_payload_has_forbidden_terms($pack)) {
        set_flash('error', 'Pack contains restricted bid/rate fields. Remove pricing references.');
        redirect('/contractor/pack_template_new.php');
    }

    save_pack_template_record($pack, 'contractor', $yojId);

    logEvent(DATA_PATH . '/logs/packs.log', [
        'event' => 'pack_template_created',
        'packTemplateId' => $pack['packTemplateId'] ?? '',
        'scope' => 'contractor',
        'yojId' => $yojId,
        'ip' => mask_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    ]);

    set_flash('success', 'Pack template created successfully.');
    redirect('/contractor/packs_library.php');
});
