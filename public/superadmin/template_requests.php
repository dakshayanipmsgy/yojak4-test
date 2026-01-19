<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('template_manager');
    $status = trim((string)($_GET['status'] ?? 'all'));
    $requests = list_template_requests();
    if ($status !== 'all') {
        $requests = array_values(array_filter($requests, fn($req) => ($req['status'] ?? 'new') === $status));
    }

    $title = get_app_config()['appName'] . ' | Template Requests';
    render_layout($title, function () use ($requests, $status) {
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Template Requests</h2>
                    <p class="muted" style="margin:4px 0 0;">Track contractor requests and deliver templates or packs.</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn secondary" href="/superadmin/template_requests.php?status=new">New</a>
                    <a class="btn secondary" href="/superadmin/template_requests.php?status=in_progress">In Progress</a>
                    <a class="btn secondary" href="/superadmin/template_requests.php?status=delivered">Delivered</a>
                    <a class="btn secondary" href="/superadmin/template_requests.php?status=rejected">Rejected</a>
                    <a class="btn" href="/superadmin/template_requests.php">All</a>
                </div>
            </div>
            <?php if (!$requests): ?>
                <div class="card" style="border:1px dashed var(--border);background:var(--surface-2);">
                    <p class="muted" style="margin:0;">No requests found for this filter.</p>
                </div>
            <?php else: ?>
                <div style="display:grid;gap:12px;">
                    <?php foreach ($requests as $request): ?>
                        <div class="card" style="border:1px solid var(--border);background:var(--surface-2);">
                            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                                <div>
                                    <h3 style="margin:0 0 6px 0;"><?= sanitize($request['title'] ?? 'Request'); ?></h3>
                                    <p class="muted" style="margin:0;">ID: <?= sanitize($request['requestId'] ?? ''); ?> • Type: <?= sanitize($request['type'] ?? 'template'); ?> • Contractor: <?= sanitize($request['yojId'] ?? ''); ?></p>
                                </div>
                                <span class="pill" style="border-color:#334155;color:#334155;"><?= sanitize($request['status'] ?? 'new'); ?></span>
                            </div>
                            <p class="muted" style="margin:8px 0 0;white-space:pre-wrap;"><?= sanitize($request['notes'] ?? ''); ?></p>
                            <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                                <a class="btn secondary" href="/superadmin/template_request_view.php?requestId=<?= sanitize($request['requestId'] ?? ''); ?>">Open</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    });
});
