<?php
require_once __DIR__ . '/../../bootstrap.php';

safePage(function () use ($lang, $config) {
    $health = [
        'dataWritable' => is_writable(dataPath()),
        'logsWritable' => is_writable(dataPath('logs')),
        'timezone' => date_default_timezone_get(),
        'configLoad' => null,
        'jsonRead' => null,
    ];

    try {
        $health['configLoad'] = loadConfig()['appName'] ?? null;
    } catch (Throwable $e) {
        $health['configLoad'] = 'error';
    }

    try {
        $health['jsonRead'] = readJson(dataPath('users/superadmin.json'))['username'] ?? null;
    } catch (Throwable $e) {
        $health['jsonRead'] = 'error';
    }

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'health' => $health,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}, $lang, $config);
