<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packId = trim($_GET['packId'] ?? '');
    $doc = trim($_GET['doc'] ?? 'index');
    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);

    $pack = $packId !== '' ? load_pack($yojId, $packId, $context) : null;
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    $contractor = load_contractor($yojId) ?? [];
    $vaultFiles = contractor_vault_index($yojId);
    $options = [
        'includeSnippets' => ($_GET['snippets'] ?? '1') !== '0',
        'includeRestricted' => ($_GET['restricted'] ?? '1') !== '0',
        'pendingOnly' => ($_GET['pendingOnly'] ?? '') === '1',
        'letterheadMode' => $_GET['letterheadMode'] ?? 'yojak',
    ];
    $annexureTemplates = load_pack_annexures($yojId, $packId, $context);
    $html = pack_print_html($pack, $contractor, $doc, $options, $vaultFiles, $annexureTemplates);
    logEvent(PACK_PRINT_LOG, [
        'event' => 'PACK_PRINT',
        'yojId' => $yojId,
        'packId' => $packId,
        'doc' => $doc,
    ]);
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
});
