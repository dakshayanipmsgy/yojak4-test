<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_offline_tender_env($yojId);
    ensure_tender_discovery_env();

    $discId = trim($_GET['id'] ?? '');
    $record = $discId !== '' ? tender_discovery_load_discovered($discId) : null;
    if (!$record || !empty($record['deletedAt'])) {
        render_error_page('Discovered tender not available.');
        return;
    }

    $sources = tender_discovery_sources();
    $sourceNames = [];
    foreach ($sources as $src) {
        $sourceNames[$src['sourceId']] = $src['name'] ?? $src['sourceId'];
    }
    $existing = find_offline_tender_by_discovery($yojId, $discId);

    $title = get_app_config()['appName'] . ' | Discovered Tender';

    render_layout($title, function () use ($record, $sourceNames, $existing) {
        ?>
        <div class="card" style="display:grid; gap:10px;">
            <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:center;">
                <div>
                    <h2 style="margin:0;"><?= sanitize($record['title'] ?? 'Discovered Tender'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;">
                        <?= sanitize($record['discId'] ?? ''); ?>
                        <?php if (!empty($record['sourceId'])): ?>
                            • <span class="pill"><?= sanitize($sourceNames[$record['sourceId']] ?? $record['sourceId']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($record['location'])): ?>
                            • <?= sanitize($record['location']); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="buttons" style="gap:8px;">
                    <a class="btn secondary" href="/contractor/discovered_tenders.php">Back</a>
                    <?php if (!empty($record['originalUrl'])): ?>
                        <a class="btn secondary" href="<?= sanitize($record['originalUrl']); ?>" target="_blank" rel="noopener">Open source</a>
                    <?php endif; ?>
                    <?php if ($existing): ?>
                        <a class="btn" href="/contractor/offline_tender_view.php?id=<?= sanitize(urlencode($existing['id'])); ?>">Open Offline Prep</a>
                    <?php else: ?>
                        <form method="post" action="/contractor/discovered_tender_start_offline.php">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <input type="hidden" name="discId" value="<?= sanitize($record['discId'] ?? ''); ?>">
                            <button class="btn" type="submit">Start Offline Prep</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <?php if (!empty($record['publishedAt'])): ?>
                    <span class="pill">Published: <?= sanitize($record['publishedAt']); ?></span>
                <?php endif; ?>
                <?php if (!empty($record['deadlineAt'])): ?>
                    <span class="pill">Deadline: <?= sanitize($record['deadlineAt']); ?></span>
                <?php else: ?>
                    <span class="pill">Deadline not provided</span>
                <?php endif; ?>
                <?php if (!empty($record['dept'])): ?>
                    <span class="pill"><?= sanitize($record['dept']); ?></span>
                <?php endif; ?>
            </div>
            <div style="border:1px solid #30363d; border-radius:10px; padding:10px;">
                <h4 style="margin-top:0;">Details</h4>
                <p style="margin:0; white-space:pre-wrap;"><?= sanitize(print_r($record['raw'] ?? [], true)); ?></p>
            </div>
        </div>
        <?php
    });
});
