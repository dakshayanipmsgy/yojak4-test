<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $schemes = scheme_list_published();
    $requests = array_filter(scheme_requests_all(), fn($req) => ($req['yojId'] ?? '') === $yojId && ($req['status'] ?? '') === 'pending');
    $pendingSchemeIds = array_column($requests, 'schemeId');

    $title = get_app_config()['appName'] . ' | Schemes';
    render_layout($title, function () use ($schemes, $pendingSchemeIds, $yojId) {
        ?>
        <div class="card" style="display:grid;gap:14px;">
            <div>
                <h2 style="margin:0;">Scheme Access</h2>
                <p class="muted" style="margin:6px 0 0;">Request and manage vendor workflows for supported schemes.</p>
            </div>

            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
                <?php if (!$schemes): ?>
                    <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                        <p class="muted" style="margin:0;">No schemes are published yet.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($schemes as $scheme): ?>
                    <?php
                    $schemeId = (string)($scheme['schemeId'] ?? '');
                    $access = scheme_access_record($yojId, $schemeId);
                    $isEnabled = !empty($access) && ($access['enabled'] ?? false);
                    $isPending = in_array($schemeId, $pendingSchemeIds, true);
                    ?>
                    <div style="border:1px solid var(--border);border-radius:12px;padding:12px;background:var(--surface-2);display:grid;gap:8px;">
                        <div>
                            <h3 style="margin:0 0 4px 0;"><?= sanitize($scheme['name'] ?? 'Scheme'); ?></h3>
                            <p class="muted" style="margin:0;"><?= sanitize($schemeId); ?> • <?= sanitize($scheme['category'] ?? ''); ?></p>
                        </div>
                        <p class="muted" style="margin:0;"><?= sanitize($scheme['shortDescription'] ?? ''); ?></p>
                        <?php if ($isEnabled): ?>
                            <a class="btn" href="/contractor/scheme.php?schemeId=<?= urlencode($schemeId); ?>">Open Scheme</a>
                        <?php elseif ($isPending): ?>
                            <span class="pill" style="border-color:#f59f00;color:#f59f00;">Request Pending</span>
                        <?php else: ?>
                            <form method="post" action="/contractor/scheme_request_access.php">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="schemeId" value="<?= sanitize($schemeId); ?>">
                                <button class="btn secondary" type="submit">Request Access</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
