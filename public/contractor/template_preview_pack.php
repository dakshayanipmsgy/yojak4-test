<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packId = trim($_GET['packId'] ?? '');
    $tplId = trim($_GET['tplId'] ?? '');
    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);

    if ($packId === '' || $tplId === '') {
        render_error_page('Invalid template preview request.');
        return;
    }

    $pack = load_pack($yojId, $packId, $context);
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    $contractor = load_contractor($yojId) ?? [];
    $letterhead = ($_GET['letterhead'] ?? '1') !== '0';
    $options = [
        'includeSnippets' => true,
        'includeRestricted' => true,
        'pendingOnly' => false,
        'useLetterhead' => $letterhead,
        'templateId' => $tplId,
    ];

    $html = pack_print_html($pack, $contractor, 'templates', $options);

    logEvent(PACK_PRINT_LOG, [
        'event' => 'PACK_PRINT',
        'at' => now_kolkata()->format(DateTime::ATOM),
        'yojId' => $yojId,
        'packId' => $packId,
        'doc' => 'template_preview',
        'mode' => 'preview',
        'autoprint' => 0,
        'letterhead' => $letterhead,
        'tplId' => $tplId,
    ]);

    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
});
