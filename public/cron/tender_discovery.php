<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');

$token = trim($_GET['token'] ?? '');
ensure_tender_discovery_env();
$state = tender_discovery_state();
$config = get_app_config();

$validTokens = [];
if (!empty($state['cronToken'])) {
    $validTokens[] = $state['cronToken'];
}
if (!empty($config['tenderDiscoveryCronToken'])) {
    $validTokens[] = $config['tenderDiscoveryCronToken'];
}
if (!empty($config['cronToken'])) {
    $validTokens[] = $config['cronToken'];
}

if ($token === '' || !in_array($token, $validTokens, true)) {
    http_response_code(403);
    tender_discovery_log([
        'event' => 'cron_denied',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    ]);
    echo json_encode(['ok' => false, 'error' => 'invalid_token']);
    exit;
}

tender_discovery_log([
    'event' => 'cron_start',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
]);

try {
    $summary = tender_discovery_run(tender_discovery_sources());
    echo json_encode([
        'ok' => true,
        'summary' => $summary,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    tender_discovery_log([
        'event' => 'cron_error',
        'message' => $e->getMessage(),
    ]);
    echo json_encode(['ok' => false, 'error' => 'run_failed']);
}
