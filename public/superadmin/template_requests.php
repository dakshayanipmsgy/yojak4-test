<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = require_staff_actor();
    $requestsIndex = load_template_requests_index();

    usort($requestsIndex, static function ($a, $b) {
        return strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? '');
    });

    $title = get_app_config()['appName'] . ' | Template Requests';
    render_layout($title, function () use ($requestsIndex, $actor) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Template Requests</h2>
                    <p class="muted" style="margin:4px 0 0;">Review contractor requests and deliver templates.</p>
                </div>
                <?php if (($actor['type'] ?? '') === 'superadmin'): ?>
                    <a class="btn secondary" href="/superadmin/dashboard.php">Back</a>
                <?php else: ?>
                    <a class="btn secondary" href="/staff/dashboard.php">Back</a>
                <?php endif; ?>
            </div>
            <div style="display:grid;gap:10px;">
                <?php if (!$requestsIndex): ?>
                    <p class="muted" style="margin:0;">No requests yet.</p>
                <?php endif; ?>
                <?php foreach ($requestsIndex as $request): ?>
                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;border:1px solid var(--border);border-radius:10px;padding:10px;background:var(--surface-2);">
                        <div>
                            <div style="font-weight:600;"><?= sanitize($request['title'] ?? 'Template request'); ?></div>
                            <div class="muted" style="font-size:12px;">Status: <?= sanitize($request['status'] ?? 'pending'); ?> • Updated: <?= sanitize($request['updatedAt'] ?? ''); ?></div>
                        </div>
                        <a class="btn" href="/superadmin/template_request_view.php?id=<?= sanitize($request['requestId'] ?? ''); ?>">Open</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
