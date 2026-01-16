<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packId = trim((string)($_GET['packId'] ?? ''));
    $density = trim((string)($_GET['density'] ?? 'normal'));
    $autoprint = ($_GET['autoprint'] ?? '') === '1';

    if (!in_array($density, ['normal', 'compact', 'dense'], true)) {
        $density = 'normal';
    }

    $url = '/contractor/pack_print_full_v3.php?packId=' . urlencode($packId)
        . '&density=' . urlencode($density)
        . ($autoprint ? '&autoprint=1' : '');

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Launching print</title>'
        . '<style>body{font-family:Arial,sans-serif;background:#fff;color:#000;margin:24px;}</style></head>'
        . '<body><p>Opening print document…</p>'
        . '<script>(()=>{const url=' . json_encode($url) . ';window.open(url,\"_blank\",\"noopener\");})();</script>'
        . '<p>If the print tab did not open, <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">click here</a>.</p>'
        . '</body></html>';
});
