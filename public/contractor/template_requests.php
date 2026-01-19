<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $requests = array_merge(request_list('template'), request_list('pack'));
    $requests = array_values(array_filter($requests, static function (array $req) use ($yojId): bool {
        return ($req['from']['yojId'] ?? '') === $yojId;
    }));
    usort($requests, static function (array $a, array $b): int {
        return strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? '');
    });

    $title = get_app_config()['appName'] . ' | Template Requests';
    render_layout($title, function () use ($requests) {
        ?>
        <div class="card">
            <h2 style="margin-top:0;"><?= sanitize('Template & Pack Requests'); ?></h2>
            <p class="muted"><?= sanitize('Track staff responses for template and pack requests.'); ?></p>
            <div style="display:grid;gap:10px;">
                <?php if (!$requests): ?>
                    <p class="muted"><?= sanitize('No requests yet.'); ?></p>
                <?php endif; ?>
                <?php foreach ($requests as $req): ?>
                    <div style="border:1px solid var(--border);border-radius:10px;padding:10px;display:grid;gap:4px;">
                        <strong><?= sanitize($req['title'] ?? 'Request'); ?></strong>
                        <span class="muted"><?= sanitize('Type: ' . strtoupper((string)($req['type'] ?? 'template'))); ?></span>
                        <span class="muted"><?= sanitize('Status: ' . request_status_label((string)($req['status'] ?? 'new'))); ?></span>
                        <span class="muted"><?= sanitize('Updated: ' . ($req['updatedAt'] ?? '')); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
