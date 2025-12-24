<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

safe_page(function () {
    $checks = [];
    try {
        $dataWritable = is_dir(DATA_PATH) && is_writable(DATA_PATH);
        $checks[] = ['label' => 'Data directory writable', 'status' => $dataWritable];

        $logWritable = is_writable(DATA_PATH . '/logs') || (!file_exists(DATA_PATH . '/logs/app.log') && is_writable(DATA_PATH . '/logs'));
        $checks[] = ['label' => 'Log directory writable', 'status' => $logWritable];

        $config = get_app_config();
        $checks[] = ['label' => 'Config readable', 'status' => !empty($config)];

        $jsonTestPath = DATA_PATH . '/locks/health.json';
        writeJsonAtomic($jsonTestPath, ['checkedAt' => now_kolkata()->format(DateTime::ATOM)]);
        $checks[] = ['label' => 'JSON read/write', 'status' => file_exists($jsonTestPath)];
        $statusOk = array_reduce($checks, fn($carry, $item) => $carry && $item['status'], true);
    } catch (Throwable $e) {
        $statusOk = false;
        logEvent(DATA_PATH . '/logs/php_errors.log', [
            'event' => 'health_error',
            'message' => $e->getMessage(),
        ]);
    }

    $title = 'YOJAK | Health';
    render_layout($title, function () use ($checks, $statusOk) {
        ?>
        <div class="card">
            <h2>Health Check</h2>
            <p class="muted"><?= $statusOk ? 'All systems responsive.' : 'Issues detected.'; ?></p>
            <ul>
                <?php foreach ($checks as $check): ?>
                    <li><?= sanitize($check['label']); ?>: <strong style="color: <?= $check['status'] ? '#2ea043' : '#f85149'; ?>"><?= $check['status'] ? 'OK' : 'Check'; ?></strong></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    });
});
