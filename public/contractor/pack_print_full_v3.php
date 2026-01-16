<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packId = trim((string)($_GET['packId'] ?? ''));
    $density = trim((string)($_GET['density'] ?? 'normal'));
    $autoprint = ($_GET['autoprint'] ?? '') === '1';

    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);

    $pack = $packId !== '' ? load_pack($yojId, $packId, $context) : null;
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        logEvent(DATA_PATH . '/logs/print_v3.log', [
            'event' => 'PACK_PRINT_V3_ERROR',
            'at' => now_kolkata()->format(DateTime::ATOM),
            'yojId' => $yojId,
            'packId' => $packId,
            'density' => $density,
            'autoprint' => $autoprint ? 1 : 0,
        ]);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Pack not found</title>'
            . '<style>body{font-family:Arial,sans-serif;margin:24px;color:#000;background:#fff;}</style></head>'
            . '<body><h1>Pack not found</h1><p>The requested tender pack could not be located.</p></body></html>';
        return;
    }

    if (!in_array($density, ['normal', 'compact', 'dense'], true)) {
        $density = 'normal';
    }

    $contractor = load_contractor($yojId) ?? [];
    $annexureTemplates = load_pack_annexures($yojId, $packId, $context);
    $html = pack_print_full_v3_html($pack, $contractor, [
        'density' => $density,
        'autoprint' => $autoprint,
    ], $annexureTemplates);

    logEvent(DATA_PATH . '/logs/print_v3.log', [
        'event' => 'PACK_PRINT_V3',
        'at' => now_kolkata()->format(DateTime::ATOM),
        'yojId' => $yojId,
        'packId' => $packId,
        'density' => $density,
        'autoprint' => $autoprint ? 1 : 0,
    ]);

    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
});
