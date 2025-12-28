<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'] ?? '';
    ensure_tender_discovery_env();
    ensure_packs_env($yojId);
    ensure_contractor_links_env($yojId);

    $keyword = trim((string)($_GET['q'] ?? ''));
    $tab = $_GET['tab'] ?? 'dept';
    $tab = in_array($tab, ['dept', 'discovered'], true) ? $tab : 'dept';

    // Discovered tenders
    $sources = tender_discovery_sources();
    $sourceNames = [];
    foreach ($sources as $src) {
        $sourceNames[$src['sourceId']] = $src['name'] ?? $src['sourceId'];
    }
    $discoveredList = [];
    foreach (tender_discovery_index() as $entry) {
        if (!empty($entry['deletedAt'])) {
            continue;
        }
        $discId = $entry['discId'] ?? '';
        if ($discId === '') {
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
        $record['sourceName'] = $sourceNames[$record['sourceId'] ?? ''] ?? ($record['sourceId'] ?? '');
        $discoveredList[] = $record;
    }
    usort($discoveredList, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));

    // Department published tenders
    $departmentEntries = departments_index();
    $published = [];
    foreach ($departmentEntries as $deptEntry) {
        $deptId = $deptEntry['deptId'] ?? '';
        if ($deptId === '') {
            continue;
        }
        $department = load_department($deptId);
        if (!$department || ($department['status'] ?? '') !== 'active') {
            continue;
        }
        $link = load_contractor_link($yojId, $deptId);
        $linked = $link && ($link['status'] ?? '') === 'active';
        foreach (public_tender_index($deptId) as $entry) {
            $snapshot = load_public_tender_snapshot($deptId, $entry['ytdId'] ?? '');
            if (!$snapshot) {
                continue;
            }
            if ($keyword !== '') {
                $haystack = strtolower(($snapshot['title'] ?? '') . ' ' . ($snapshot['summaryPublic'] ?? ''));
                if (!str_contains($haystack, strtolower($keyword))) {
                    continue;
                }
            }
            $published[] = [
                'deptId' => $deptId,
                'deptName' => $department['nameEn'] ?? $deptId,
                'district' => $department['district'] ?? '',
                'ytdId' => $snapshot['ytdId'] ?? ($entry['ytdId'] ?? ''),
                'title' => $snapshot['title'] ?? ($entry['title'] ?? ''),
                'submissionDeadline' => $snapshot['submissionDeadline'] ?? ($entry['submissionDeadline'] ?? ''),
                'publishedAt' => $snapshot['publishedAt'] ?? ($entry['publishedAt'] ?? ''),
                'summaryPublic' => $snapshot['summaryPublic'] ?? '',
                'requirementSetId' => $snapshot['requirementSetId'] ?? null,
                'linked' => $linked,
            ];
        }
    }
    usort($published, fn($a, $b) => strcmp($b['publishedAt'] ?? '', $a['publishedAt'] ?? ''));

    $title = get_app_config()['appName'] . ' | Tenders';

    render_layout($title, function () use ($published, $discoveredList, $keyword, $tab) {
        ?>
        <div class="card" style="display:grid;gap:10px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0 0 6px 0;"><?= sanitize('Browse Tenders'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Discovery and department-published tenders.'); ?></p>
                </div>
                <a class="btn secondary" href="/contractor/packs.php"><?= sanitize('My Packs'); ?></a>
            </div>
            <form method="get" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;">
                <input type="hidden" name="tab" value="<?= sanitize($tab); ?>">
                <input name="q" placeholder="Keyword" value="<?= sanitize($keyword); ?>">
                <button class="btn secondary" type="submit"><?= sanitize('Search'); ?></button>
            </form>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
                <a class="pill <?= $tab === 'dept' ? 'success' : ''; ?>" href="/contractor/tenders.php?tab=dept<?= $keyword !== '' ? '&q=' . urlencode($keyword) : ''; ?>"><?= sanitize('Department Published'); ?></a>
                <a class="pill <?= $tab === 'discovered' ? 'success' : ''; ?>" href="/contractor/tenders.php?tab=discovered<?= $keyword !== '' ? '&q=' . urlencode($keyword) : ''; ?>"><?= sanitize('Discovered (Jharkhand)'); ?></a>
            </div>
        </div>

        <?php if ($tab === 'dept'): ?>
            <div style="display:grid;gap:12px;margin-top:12px;">
                <?php if (!$published): ?>
                    <div class="card"><p class="muted" style="margin:0;"><?= sanitize('No department tenders published yet.'); ?></p></div>
                <?php endif; ?>
                <?php foreach ($published as $tender): ?>
                    <div class="card" style="display:grid;gap:10px;">
                        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:flex-start;">
                            <div>
                                <h3 style="margin:0;"><?= sanitize($tender['title'] ?? 'Tender'); ?></h3>
                                <p class="muted" style="margin:4px 0 0;">
                                    <?= sanitize(strtoupper($tender['deptId'] ?? '')); ?> • <?= sanitize($tender['deptName'] ?? ''); ?>
                                    <?php if (!empty($tender['district'])): ?> • <?= sanitize($tender['district']); ?><?php endif; ?>
                                </p>
                            </div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <a class="btn secondary" href="/contractor/tender_view.php?src=dept&deptId=<?= urlencode($tender['deptId'] ?? ''); ?>&id=<?= urlencode($tender['ytdId'] ?? ''); ?>"><?= sanitize('View'); ?></a>
                                <form method="post" action="/contractor/start_pack.php">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                    <input type="hidden" name="src" value="dept">
                                    <input type="hidden" name="deptId" value="<?= sanitize($tender['deptId'] ?? ''); ?>">
                                    <input type="hidden" name="ytdId" value="<?= sanitize($tender['ytdId'] ?? ''); ?>">
                                    <button class="btn" type="submit"><?= sanitize('Start Pack'); ?></button>
                                </form>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            <?php if (!empty($tender['submissionDeadline'])): ?>
                                <span class="pill"><?= sanitize('Deadline: ' . $tender['submissionDeadline']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($tender['publishedAt'])): ?>
                                <span class="pill"><?= sanitize('Published: ' . $tender['publishedAt']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($tender['requirementSetId'])): ?>
                                <span class="pill"><?= sanitize('Official checklist ready'); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($tender['linked'])): ?>
                                <span class="pill success"><?= sanitize('Linked'); ?></span>
                            <?php else: ?>
                                <span class="pill"><?= sanitize('Link for faster prep'); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($tender['summaryPublic'])): ?>
                            <p class="muted" style="margin:0;"><?= sanitize($tender['summaryPublic']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="display:grid;gap:12px;margin-top:12px;">
                <?php if (!$discoveredList): ?>
                    <div class="card"><p class="muted" style="margin:0;"><?= sanitize('No discovered tenders match the filters.'); ?></p></div>
                <?php endif; ?>
                <?php foreach ($discoveredList as $tender): ?>
                    <div class="card" style="display:grid;gap:8px;">
                        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:flex-start;">
                            <div>
                                <h3 style="margin:0;"><?= sanitize($tender['title'] ?? 'Tender'); ?></h3>
                                <p class="muted" style="margin:4px 0 0;">
                                    <?= sanitize($tender['discId'] ?? ''); ?>
                                    <?php if (!empty($tender['sourceName'])): ?> • <span class="pill"><?= sanitize($tender['sourceName']); ?></span><?php endif; ?>
                                    <?php if (!empty($tender['dept'])): ?> • <?= sanitize($tender['dept']); ?><?php endif; ?>
                                    <?php if (!empty($tender['location'])): ?> • <?= sanitize($tender['location']); ?><?php endif; ?>
                                </p>
                            </div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <a class="btn secondary" href="/contractor/discovered_tender_view.php?id=<?= sanitize(urlencode($tender['discId'] ?? '')); ?>"><?= sanitize('View'); ?></a>
                                <?php if (!empty($tender['originalUrl'])): ?>
                                    <a class="btn secondary" href="<?= sanitize($tender['originalUrl']); ?>" target="_blank" rel="noopener"><?= sanitize('Open source'); ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <?php if (!empty($tender['deadlineAt'])): ?>
                                <span class="pill"><?= sanitize('Deadline: ' . $tender['deadlineAt']); ?></span>
                            <?php else: ?>
                                <span class="pill"><?= sanitize('Deadline: Not provided'); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($tender['publishedAt'])): ?>
                                <span class="pill"><?= sanitize('Published: ' . $tender['publishedAt']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php
    });
});
