<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $requests = list_template_requests($yojId);

    $title = get_app_config()['appName'] . ' | Template Requests';
    render_layout($title, function () use ($requests) {
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Template Requests</h2>
                    <p class="muted" style="margin:4px 0 0;">Track your template and pack requests submitted to staff.</p>
                </div>
                <a class="btn" href="/contractor/templates.php">Back to Templates</a>
            </div>
            <?php if (!$requests): ?>
                <div class="card" style="border:1px dashed var(--border);background:var(--surface-2);">
                    <p class="muted" style="margin:0;">No requests submitted yet.</p>
                </div>
            <?php else: ?>
                <div style="display:grid;gap:12px;">
                    <?php foreach ($requests as $request): ?>
                        <div class="card" style="background:var(--surface-2);border:1px solid var(--border);">
                            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                                <div>
                                    <h3 style="margin:0 0 6px 0;"><?= sanitize($request['title'] ?? 'Request'); ?></h3>
                                    <p class="muted" style="margin:0;">Type: <?= sanitize($request['type'] ?? 'template'); ?> • ID: <?= sanitize($request['requestId'] ?? ''); ?></p>
                                </div>
                                <span class="pill" style="border-color:#334155;color:#334155;"><?= sanitize($request['status'] ?? 'new'); ?></span>
                            </div>
                            <p class="muted" style="margin:8px 0 0;white-space:pre-wrap;"><?= sanitize($request['notes'] ?? ''); ?></p>
                            <p class="muted" style="margin:8px 0 0;">Updated: <?= sanitize($request['updatedAt'] ?? ''); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    });
});
