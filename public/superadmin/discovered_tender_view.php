<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    ensure_tender_discovery_env();

    $discId = trim((string)($_GET['id'] ?? ''));
    if ($discId === '') {
        render_error_page('Discovered tender not found.');
        return;
    }

    $record = tender_discovery_load_discovered($discId);
    if (!$record) {
        render_error_page('Discovered tender not found.');
        return;
    }

    $sources = tender_discovery_sources();
    $sourceNames = [];
    foreach ($sources as $src) {
        $sourceNames[$src['sourceId']] = $src['name'] ?? $src['sourceId'];
    }

    if (empty($record['seenByAdminAt'])) {
        $seenAt = now_kolkata()->format(DateTime::ATOM);
        tender_discovery_mark_seen_by_admin($discId);
        $record['seenByAdminAt'] = $seenAt;
    }

    tender_discovery_log([
        'event' => 'admin_view_discovered',
        'discId' => $discId,
        'username' => $user['username'] ?? 'superadmin',
    ]);

    $isDeleted = !empty($record['deletedAt']);
    $title = get_app_config()['appName'] . ' | Discovered Tender';

    $seedText = "Title: " . ($record['title'] ?? '') . PHP_EOL .
        "Deadline: " . ($record['deadlineAt'] ?? 'Not provided') . PHP_EOL .
        "Published: " . ($record['publishedAt'] ?? 'Not provided') . PHP_EOL .
        "Location: " . ($record['location'] ?? 'Jharkhand') . PHP_EOL .
        "Department: " . ($record['dept'] ?? 'Not provided') . PHP_EOL .
        "Source: " . ($record['sourceId'] ?? '') . PHP_EOL .
        "URL: " . ($record['originalUrl'] ?? '');

    render_layout($title, function () use ($record, $sourceNames, $isDeleted, $seedText) {
        ?>
        <div class="card" style="display:grid; gap:12px;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
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
                        <?php if (!empty($record['dedupeKey'])): ?>
                            • <span class="pill">Dedupe: <?= sanitize(substr((string)$record['dedupeKey'], 0, 12)); ?>…</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="buttons" style="gap:8px;">
                    <a class="btn secondary" href="/superadmin/discovered_tenders.php">Back</a>
                    <?php if (!empty($record['originalUrl'])): ?>
                        <a class="btn secondary" href="<?= sanitize($record['originalUrl']); ?>" target="_blank" rel="noopener">Open source</a>
                    <?php endif; ?>
                    <?php if (!$isDeleted): ?>
                        <form method="post" action="/superadmin/discovered_tender_delete.php" onsubmit="return confirm('Soft delete this tender?');">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <input type="hidden" name="discId" value="<?= sanitize($record['discId'] ?? ''); ?>">
                            <button class="btn danger" type="submit">Soft delete</button>
                        </form>
                    <?php else: ?>
                        <span class="pill" style="border-color: var(--danger); color: #f77676;">Deleted <?= sanitize($record['deletedAt']); ?></span>
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
                <?php if (!empty($record['createdAt'])): ?>
                    <span class="pill">Discovered: <?= sanitize($record['createdAt']); ?></span>
                <?php endif; ?>
                <?php if (!empty($record['seenByAdminAt'])): ?>
                    <span class="pill">Seen: <?= sanitize($record['seenByAdminAt']); ?></span>
                <?php endif; ?>
            </div>
            <div class="card" style="background:#0d1117; border-style:dashed; display:grid; gap:8px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                    <h4 style="margin:0;">OFFTD seed text</h4>
                    <button class="btn secondary" type="button" onclick="copySeed()">Copy</button>
                </div>
                <textarea id="seed_text" readonly style="min-height:120px; width:100%; resize:vertical;"><?= sanitize($seedText); ?></textarea>
            </div>
            <div class="card" style="border-style:dashed; display:grid; gap:6px;">
                <h4 style="margin:0;">Raw snippet</h4>
                <pre style="margin:0; white-space:pre-wrap; background:#0d1117; padding:10px; border-radius:10px; border:1px solid #30363d;"><?= sanitize(json_encode($record['raw'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
            </div>
        </div>
        <script>
            function copySeed() {
                const el = document.getElementById('seed_text');
                if (!el) return;
                el.select();
                el.setSelectionRange(0, 99999);
                document.execCommand('copy');
            }
        </script>
        <?php
    });
});
