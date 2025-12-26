<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_offline_tender_env($yojId);
    ensure_tender_discovery_env();

    $keyword = trim((string)($_GET['q'] ?? ''));
    $deadlineFilter = $_GET['deadline'] ?? 'upcoming';
    $now = now_kolkata();

    $sources = tender_discovery_sources();
    $sourceNames = [];
    foreach ($sources as $src) {
        $sourceNames[$src['sourceId']] = $src['name'] ?? $src['sourceId'];
    }

    $index = tender_discovery_index();
    $tenders = [];
    foreach ($index as $entry) {
        $discId = $entry['discId'] ?? '';
        if ($discId === '') {
            continue;
        }
        if (!empty($entry['deletedAt'])) {
            continue;
        }
        $record = tender_discovery_load_discovered($discId);
        if (!$record || !empty($record['deletedAt'])) {
            continue;
        }

        if ($keyword !== '') {
            $haystack = strtolower(($record['title'] ?? '') . ' ' . ($record['dept'] ?? '') . ' ' . ($record['location'] ?? ''));
            if (strpos($haystack, strtolower($keyword)) === false) {
                continue;
            }
        }

        $deadlineAt = $record['deadlineAt'] ?? null;
        if ($deadlineFilter === 'upcoming' && $deadlineAt) {
            try {
                $deadlineDt = new DateTimeImmutable($deadlineAt);
                if ($deadlineDt < $now) {
                    continue;
                }
            } catch (Throwable $e) {
                // ignore parse failure
            }
        } elseif ($deadlineFilter === 'next30' && $deadlineAt) {
            try {
                $deadlineDt = new DateTimeImmutable($deadlineAt);
                $limit = $now->add(new DateInterval('P30D'));
                if ($deadlineDt < $now || $deadlineDt > $limit) {
                    continue;
                }
            } catch (Throwable $e) {
                // ignore parse failure
            }
        }

        $record['sourceName'] = $sourceNames[$record['sourceId'] ?? ''] ?? ($record['sourceId'] ?? '');
        $existing = find_offline_tender_by_discovery($yojId, $discId);
        $record['existingOfftdId'] = $existing['id'] ?? null;
        $tenders[] = $record;
    }

    usort($tenders, function ($a, $b) {
        $aDeadline = $a['deadlineAt'] ?? '';
        $bDeadline = $b['deadlineAt'] ?? '';
        if ($aDeadline && $bDeadline) {
            return strcmp($aDeadline, $bDeadline);
        }
        if ($aDeadline) {
            return -1;
        }
        if ($bDeadline) {
            return 1;
        }
        return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
    });

    $title = get_app_config()['appName'] . ' | Discovered Tenders';

    render_layout($title, function () use ($tenders, $keyword, $deadlineFilter) {
        ?>
        <div class="card" style="display:grid; gap:12px;">
            <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; align-items:center;">
                <div>
                    <h2 style="margin:0;">Discovered Tenders</h2>
                    <p class="muted" style="margin:4px 0 0;">Browse public tenders and start offline prep.</p>
                </div>
                <a class="btn secondary" href="/contractor/offline_tenders.php">My Offline Tenders</a>
            </div>
            <form method="get" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:10px;">
                <input name="q" placeholder="Keyword or department" value="<?= sanitize($keyword); ?>">
                <select name="deadline">
                    <option value="upcoming" <?= $deadlineFilter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="next30" <?= $deadlineFilter === 'next30' ? 'selected' : ''; ?>>Due in next 30 days</option>
                    <option value="all" <?= $deadlineFilter === 'all' ? 'selected' : ''; ?>>All</option>
                </select>
                <button class="btn" type="submit">Filter</button>
            </form>
        </div>

        <div style="display:grid; gap:12px; margin-top:12px;">
            <?php if (!$tenders): ?>
                <div class="card">
                    <p class="muted" style="margin:0;">No tenders match the filters.</p>
                </div>
            <?php endif; ?>
            <?php foreach ($tenders as $tender): ?>
                <div class="card" style="display:grid; gap:10px;">
                    <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:flex-start;">
                        <div>
                            <h3 style="margin:0;"><?= sanitize($tender['title'] ?? ''); ?></h3>
                            <p class="muted" style="margin:4px 0 0;">
                                <?= sanitize($tender['discId'] ?? ''); ?>
                                <?php if (!empty($tender['sourceName'])): ?>
                                    • <span class="pill"><?= sanitize($tender['sourceName']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($tender['dept'])): ?>
                                    • <?= sanitize($tender['dept']); ?>
                                <?php endif; ?>
                                <?php if (!empty($tender['location'])): ?>
                                    • <?= sanitize($tender['location']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <a class="btn secondary" href="/contractor/discovered_tender_view.php?id=<?= sanitize(urlencode($tender['discId'] ?? '')); ?>">View</a>
                            <?php if (!empty($tender['originalUrl'])): ?>
                                <a class="btn secondary" href="<?= sanitize($tender['originalUrl']); ?>" target="_blank" rel="noopener">Open source</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <?php if (!empty($tender['deadlineAt'])): ?>
                            <span class="pill">Deadline: <?= sanitize($tender['deadlineAt']); ?></span>
                        <?php else: ?>
                            <span class="pill">Deadline: Not provided</span>
                        <?php endif; ?>
                        <?php if (!empty($tender['publishedAt'])): ?>
                            <span class="pill">Published: <?= sanitize($tender['publishedAt']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <?php if (!empty($tender['existingOfftdId'])): ?>
                            <span class="pill" style="border-color: var(--success); color: #8ce99a;">Offline prep started</span>
                            <a class="btn secondary" href="/contractor/offline_tender_view.php?id=<?= sanitize(urlencode($tender['existingOfftdId'])); ?>">Open Offline Tender</a>
                        <?php else: ?>
                            <form method="post" action="/contractor/discovered_tender_start_offline.php">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="discId" value="<?= sanitize($tender['discId'] ?? ''); ?>">
                                <button class="btn" type="submit">Start Offline Prep</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    });
});
