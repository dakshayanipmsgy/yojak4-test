<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_scheme_approver();
    $requests = scheme_pending_requests();
    usort($requests, fn($a, $b) => strcmp($a['requestedAt'] ?? '', $b['requestedAt'] ?? ''));

    $title = get_app_config()['appName'] . ' | Scheme Activation Requests';
    render_layout($title, function () use ($requests) {
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div>
                <h2 style="margin:0;">Scheme Activation Requests</h2>
                <p class="muted" style="margin:6px 0 0;">Approve vendor access to published schemes.</p>
            </div>

            <?php if (!$requests): ?>
                <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                    <p class="muted" style="margin:0;">No pending requests.</p>
                </div>
            <?php endif; ?>

            <div style="display:grid;gap:12px;">
                <?php foreach ($requests as $request): ?>
                    <?php $contractor = load_contractor($request['yojId'] ?? '') ?? []; ?>
                    <div style="border:1px solid var(--border);border-radius:12px;padding:12px;background:var(--surface-2);display:grid;gap:8px;">
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;">
                            <div>
                                <h3 style="margin:0 0 4px 0;"><?= sanitize($request['schemeId'] ?? ''); ?></h3>
                                <p class="muted" style="margin:0;">Request ID: <?= sanitize($request['requestId'] ?? ''); ?></p>
                            </div>
                            <span class="pill" style="border-color:#f59f00;color:#f59f00;">Pending</span>
                        </div>
                        <div style="display:grid;gap:4px;">
                            <span><strong>Vendor:</strong> <?= sanitize($contractor['name'] ?? ''); ?> (<?= sanitize($request['yojId'] ?? ''); ?>)</span>
                            <span class="muted">Requested: <?= sanitize($request['requestedAt'] ?? ''); ?></span>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <form method="post" action="/superadmin/scheme_activation_approve.php" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="requestId" value="<?= sanitize($request['requestId'] ?? ''); ?>">
                                <button class="btn" type="submit">Approve</button>
                            </form>
                            <form method="post" action="/superadmin/scheme_activation_reject.php" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="requestId" value="<?= sanitize($request['requestId'] ?? ''); ?>">
                                <button class="btn secondary" type="submit">Reject</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
