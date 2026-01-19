<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_contractor_library_env($yojId);

    $requests = template_request_list($yojId);
    $title = get_app_config()['appName'] . ' | Template Requests';

    render_layout($title, function () use ($requests) {
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Template/Pack Requests</h2>
                    <p class="muted" style="margin:4px 0 0;">Track requests sent to the YOJAK team.</p>
                </div>
                <a class="btn" href="/contractor/template_request_new.php">Request Template/Pack</a>
            </div>

            <div style="display:grid;gap:12px;">
                <?php if (!$requests): ?>
                    <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                        <p class="muted" style="margin:0;">No requests yet. Submit your first request to get help from YOJAK staff.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($requests as $request): ?>
                    <?php
                    $status = (string)($request['status'] ?? 'pending');
                    $statusLabel = ucfirst(str_replace('_', ' ', $status));
                    ?>
                    <div class="card" style="display:grid;gap:8px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                            <div>
                                <strong><?= sanitize($request['title'] ?? 'Request'); ?></strong>
                                <div class="muted"><?= sanitize(($request['type'] ?? 'template') === 'pack' ? 'Pack' : 'Template'); ?></div>
                            </div>
                            <span class="pill"><?= sanitize($statusLabel); ?></span>
                        </div>
                        <div class="muted"><?= sanitize(mb_substr((string)($request['notes'] ?? ''), 0, 240)); ?></div>
                        <div class="muted">Requested: <?= sanitize($request['createdAt'] ?? ''); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
