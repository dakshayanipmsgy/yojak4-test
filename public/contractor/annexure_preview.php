<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packId = trim($_GET['packId'] ?? '');
    $annexId = trim($_GET['annexId'] ?? '');
    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);

    if ($packId === '' || $annexId === '') {
        render_error_page('Invalid annexure preview request.');
        return;
    }

    $pack = load_pack($yojId, $packId, $context);
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    $annexureTemplates = load_pack_annexures($yojId, $packId, $context);
    $matches = array_values(array_filter($annexureTemplates, static function (array $tpl) use ($annexId) {
        return ($tpl['annexId'] ?? '') === $annexId || ($tpl['annexureCode'] ?? '') === $annexId;
    }));
    if (!$matches) {
        render_error_page('Annexure template not found.');
        return;
    }

    $contractor = load_contractor($yojId) ?? [];
    $letterhead = ($_GET['letterhead'] ?? '1') !== '0';
    $options = [
        'includeSnippets' => true,
        'includeRestricted' => true,
        'pendingOnly' => false,
        'useLetterhead' => $letterhead,
        'annexureId' => $annexId,
        'annexurePreview' => true,
    ];

    $html = pack_print_html($pack, $contractor, 'annexures', $options, [], $matches);

    logEvent(PACK_PRINT_LOG, [
        'event' => 'PACK_PRINT',
        'yojId' => $yojId,
        'packId' => $packId,
        'doc' => 'annexure_preview',
        'letterhead' => $letterhead,
        'annexureId' => $annexId,
    ]);

    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
});
