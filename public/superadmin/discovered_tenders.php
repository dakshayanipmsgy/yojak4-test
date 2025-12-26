<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_role('superadmin');
    ensure_tender_discovery_env();

    $keyword = trim((string)($_GET['q'] ?? ''));
    $sourceFilter = trim((string)($_GET['source'] ?? ''));
    $deadlineFilter = $_GET['deadline'] ?? 'upcoming';
    $allowedDeadlineFilters = ['all', 'upcoming', 'expired'];
    if (!in_array($deadlineFilter, $allowedDeadlineFilters, true)) {
        $deadlineFilter = 'upcoming';
    }
    $upcomingOnly = ($_GET['upcoming'] ?? '1') === '1';
    $publishedFrom = trim((string)($_GET['published_from'] ?? ''));
    $publishedTo = trim((string)($_GET['published_to'] ?? ''));
    $createdFrom = trim((string)($_GET['created_from'] ?? ''));
    $createdTo = trim((string)($_GET['created_to'] ?? ''));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 15;

    if ($upcomingOnly) {
        $deadlineFilter = 'upcoming';
    }

    $parseDate = function (?string $value): ?DateTimeImmutable {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value, new DateTimeZone('Asia/Kolkata'));
        } catch (Throwable $e) {
            return null;
        }
    };

    $publishedFromDt = $parseDate($publishedFrom);
    $publishedToDt = $parseDate($publishedTo);
    $createdFromDt = $parseDate($createdFrom);
    $createdToDt = $parseDate($createdTo);

    $sources = tender_discovery_sources();
    $sourceNames = [];
    foreach ($sources as $src) {
        $sourceNames[$src['sourceId']] = $src['name'] ?? $src['sourceId'];
    }

    $now = now_kolkata();
    $index = tender_discovery_index();
    $tenders = [];

    foreach ($index as $entry) {
        $discId = $entry['discId'] ?? '';
        if ($discId === '' || !empty($entry['deletedAt'])) {
            continue;
        }
        $record = tender_discovery_load_discovered($discId);
        if (!$record || !empty($record['deletedAt'])) {
            continue;
        }

        if ($keyword !== '' && stripos($record['title'] ?? '', $keyword) === false) {
            continue;
        }
        if ($sourceFilter !== '' && ($record['sourceId'] ?? '') !== $sourceFilter) {
            continue;
        }

        $deadlineAt = $record['deadlineAt'] ?? null;
        if ($deadlineFilter === 'upcoming' && $deadlineAt) {
            $deadlineDt = $parseDate($deadlineAt);
            if ($deadlineDt && $deadlineDt < $now) {
                continue;
            }
        } elseif ($deadlineFilter === 'expired') {
            $deadlineDt = $parseDate($deadlineAt);
            if (!$deadlineDt || $deadlineDt >= $now) {
                continue;
            }
        }

        $publishedDt = $parseDate($record['publishedAt'] ?? null);
        if ($publishedFromDt && (!$publishedDt || $publishedDt < $publishedFromDt)) {
            continue;
        }
        if ($publishedToDt) {
            $publishedUpper = $publishedToDt->setTime(23, 59, 59);
            if (!$publishedDt || $publishedDt > $publishedUpper) {
                continue;
            }
        }

        $createdDt = $parseDate($record['createdAt'] ?? null);
        if ($createdFromDt && (!$createdDt || $createdDt < $createdFromDt)) {
            continue;
        }
        if ($createdToDt) {
            $createdUpper = $createdToDt->setTime(23, 59, 59);
            if (!$createdDt || $createdDt > $createdUpper) {
                continue;
            }
        }

        $record['sourceName'] = $sourceNames[$record['sourceId'] ?? ''] ?? ($record['sourceId'] ?? '');
        $record['createdTs'] = $createdDt ? $createdDt->getTimestamp() : null;
        $record['deadlineTs'] = $deadlineAt ? ($parseDate($deadlineAt)?->getTimestamp() ?? null) : null;
        $tenders[] = $record;
    }

    usort($tenders, function ($a, $b) {
        $aDeadline = $a['deadlineTs'] ?? null;
        $bDeadline = $b['deadlineTs'] ?? null;
        if ($aDeadline !== null && $bDeadline !== null && $aDeadline !== $bDeadline) {
            return $aDeadline <=> $bDeadline;
        }
        if ($aDeadline !== null) {
            return -1;
        }
        if ($bDeadline !== null) {
            return 1;
        }
        $aCreated = $a['createdTs'] ?? 0;
        $bCreated = $b['createdTs'] ?? 0;
        return $bCreated <=> $aCreated;
    });

    $total = count($tenders);
    $pages = max(1, (int)ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = ($page - 1) * $perPage;
    $pageTenders = array_slice($tenders, $offset, $perPage);

    $title = get_app_config()['appName'] . ' | Discovered Tenders';

    $queryBase = [
        'q' => $keyword,
        'source' => $sourceFilter,
        'deadline' => $deadlineFilter,
        'upcoming' => $upcomingOnly ? '1' : '0',
        'published_from' => $publishedFrom,
        'published_to' => $publishedTo,
        'created_from' => $createdFrom,
        'created_to' => $createdTo,
    ];

    render_layout($title, function () use ($pageTenders, $keyword, $sourceFilter, $sources, $deadlineFilter, $upcomingOnly, $publishedFrom, $publishedTo, $createdFrom, $createdTo, $page, $pages, $queryBase) {
        ?>
        <div class="card" style="display:grid; gap:12px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div>
                    <h2 style="margin:0;">Discovered Tenders</h2>
                    <p class="muted" style="margin:4px 0 0;">Full list of tenders discovered by the crawler.</p>
                </div>
                <a class="btn secondary" href="/superadmin/tender_discovery.php">Back to discovery</a>
            </div>
            <form method="get" style="display:grid; gap:10px;">
                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:10px;">
                    <input name="q" placeholder="Search title keyword" value="<?= sanitize($keyword); ?>">
                    <select name="source">
                        <option value="">All sources</option>
                        <?php foreach ($sources as $src): ?>
                            <option value="<?= sanitize($src['sourceId'] ?? ''); ?>" <?= ($sourceFilter === ($src['sourceId'] ?? '')) ? 'selected' : ''; ?>>
                                <?= sanitize(($src['name'] ?? '') . ' (' . ($src['sourceId'] ?? '') . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <input type="hidden" name="upcoming" value="0">
                        <input type="checkbox" id="upcoming_only" name="upcoming" value="1" <?= $upcomingOnly ? 'checked' : ''; ?>>
                        <label for="upcoming_only" style="margin:0;">Upcoming only</label>
                    </div>
                    <select id="deadline_filter" name="deadline" <?= $upcomingOnly ? 'disabled' : ''; ?>>
                        <option value="all" <?= $deadlineFilter === 'all' ? 'selected' : ''; ?>>All deadlines</option>
                        <option value="upcoming" <?= $deadlineFilter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="expired" <?= $deadlineFilter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    </select>
                </div>
                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:10px;">
                    <input name="published_from" type="date" value="<?= sanitize($publishedFrom); ?>" placeholder="Published from">
                    <input name="published_to" type="date" value="<?= sanitize($publishedTo); ?>" placeholder="Published to">
                    <input name="created_from" type="date" value="<?= sanitize($createdFrom); ?>" placeholder="Created from">
                    <input name="created_to" type="date" value="<?= sanitize($createdTo); ?>" placeholder="Created to">
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button class="btn" type="submit">Apply filters</button>
                    <a class="btn secondary" href="/superadmin/discovered_tenders.php">Reset</a>
                </div>
            </form>
        </div>

        <div style="margin-top:12px; display:grid; gap:12px;">
            <?php if (!$pageTenders): ?>
                <div class="card">
                    <p class="muted" style="margin:0;">No tenders match the filters right now.</p>
                </div>
            <?php endif; ?>
            <?php foreach ($pageTenders as $tender): ?>
                <div class="card" style="display:grid; gap:10px;">
                    <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:flex-start;">
                        <div style="display:grid; gap:6px;">
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                <span class="pill"><?= sanitize($tender['discId'] ?? ''); ?></span>
                                <?php if (!empty($tender['sourceId'])): ?>
                                    <span class="pill"><?= sanitize($tender['sourceId']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($tender['location'])): ?>
                                    <span class="pill"><?= sanitize($tender['location']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3 style="margin:0;"><?= sanitize($tender['title'] ?? 'Discovered Tender'); ?></h3>
                                <p class="muted" style="margin:4px 0 0;"><?= sanitize($tender['sourceName'] ?? ''); ?></p>
                            </div>
                        </div>
                        <div class="buttons" style="gap:8px;">
                            <a class="btn secondary" href="/superadmin/discovered_tender_view.php?id=<?= sanitize(urlencode($tender['discId'] ?? '')); ?>">View</a>
                            <form method="post" action="/superadmin/discovered_tender_delete.php" onsubmit="return confirm('Soft delete this tender?');">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="discId" value="<?= sanitize($tender['discId'] ?? ''); ?>">
                                <button class="btn danger" type="submit">Soft delete</button>
                            </form>
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
                        <?php if (!empty($tender['createdAt'])): ?>
                            <span class="pill">Discovered: <?= sanitize($tender['createdAt']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <div class="card" style="margin-top:12px; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                <div class="muted">Page <?= sanitize((string)$page); ?> of <?= sanitize((string)$pages); ?></div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a class="btn secondary" href="/superadmin/discovered_tenders.php?<?= sanitize(http_build_query(array_merge($queryBase, ['page' => $page - 1]))); ?>">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $pages): ?>
                        <a class="btn secondary" href="/superadmin/discovered_tenders.php?<?= sanitize(http_build_query(array_merge($queryBase, ['page' => $page + 1]))); ?>">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <script>
            const upcomingCheckbox = document.getElementById('upcoming_only');
            const deadlineSelect = document.getElementById('deadline_filter');
            function toggleDeadlineSelect() {
                if (!upcomingCheckbox || !deadlineSelect) return;
                const isUpcoming = upcomingCheckbox.checked;
                deadlineSelect.disabled = isUpcoming;
                if (isUpcoming) {
                    deadlineSelect.value = 'upcoming';
                }
            }
            toggleDeadlineSelect();
            if (upcomingCheckbox) {
                upcomingCheckbox.addEventListener('change', toggleDeadlineSelect);
            }
        </script>
        <?php
    });
});
