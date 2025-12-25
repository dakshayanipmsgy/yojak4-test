<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_tender_archive_env($yojId);

    $archives = tender_archive_index($yojId);

    $query = trim($_GET['q'] ?? '');
    $filterYear = trim($_GET['year'] ?? '');
    $filterOutcome = trim($_GET['outcome'] ?? '');

    $archives = array_values(array_filter($archives, function ($item) use ($query, $filterYear, $filterOutcome) {
        if ($filterYear !== '' && (string)($item['year'] ?? '') !== $filterYear) {
            return false;
        }
        if ($filterOutcome !== '' && (string)($item['outcome'] ?? '') !== $filterOutcome) {
            return false;
        }
        if ($query !== '') {
            $haystack = strtolower(($item['title'] ?? '') . ' ' . ($item['departmentName'] ?? '') . ' ' . ($item['id'] ?? ''));
            if (!str_contains($haystack, strtolower($query))) {
                return false;
            }
        }
        return true;
    }));

    usort($archives, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

    $title = get_app_config()['appName'] . ' | Tender Archive';
    $currentYear = (int)now_kolkata()->format('Y');

    render_layout($title, function () use ($archives, $query, $filterYear, $filterOutcome, $currentYear) {
        ?>
        <div class="card">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize('Tender Archive'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Store past tenders, record outcomes, and reuse AI summaries & checklists.'); ?></p>
                </div>
                <a class="btn" href="/contractor/tender_archive_add.php"><?= sanitize('Add archive'); ?></a>
            </div>
        </div>

        <div class="card" style="margin-top:12px;">
            <form method="get" style="display:grid; gap:10px;">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px;">
                    <input name="q" placeholder="<?= sanitize('Search title/department'); ?>" value="<?= sanitize($query); ?>">
                    <select name="year">
                        <option value=""><?= sanitize('All years'); ?></option>
                        <?php for ($year = $currentYear; $year >= 2000; $year--): ?>
                            <option value="<?= $year; ?>" <?= $filterYear === (string)$year ? 'selected' : ''; ?>><?= $year; ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="outcome">
                        <option value=""><?= sanitize('All outcomes'); ?></option>
                        <?php foreach (['participated' => 'Participated', 'won' => 'Won', 'lost' => 'Lost'] as $key => $label): ?>
                            <option value="<?= sanitize($key); ?>" <?= $filterOutcome === $key ? 'selected' : ''; ?>><?= sanitize($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="buttons" style="margin-top:0;">
                    <button class="btn" type="submit"><?= sanitize('Filter'); ?></button>
                    <a class="btn secondary" href="/contractor/tender_archive.php"><?= sanitize('Reset'); ?></a>
                </div>
            </form>
        </div>

        <div style="display:grid; gap:12px; margin-top:12px;">
            <?php if (!$archives): ?>
                <div class="card">
                    <p class="muted" style="margin:0;"><?= sanitize('No archived tenders yet. Start by adding an entry.'); ?></p>
                </div>
            <?php endif; ?>
            <?php foreach ($archives as $archive): ?>
                <div class="card" style="display:grid; gap:8px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
                        <div>
                            <h3 style="margin:0;"><?= sanitize($archive['title'] ?? 'Archived Tender'); ?></h3>
                            <p class="muted" style="margin:4px 0 0;">
                                <?= sanitize($archive['id'] ?? ''); ?>
                                <?php if (!empty($archive['year'])): ?> • <?= sanitize('Year ' . $archive['year']); ?><?php endif; ?>
                                <?php if (!empty($archive['departmentName'])): ?> • <?= sanitize($archive['departmentName']); ?><?php endif; ?>
                                <?php if (!empty($archive['outcome'])): ?>
                                    • <span class="pill"><?= sanitize(ucfirst($archive['outcome'])); ?></span>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($archive['updatedAt'])): ?>
                                <p class="muted" style="margin:4px 0 0; font-size:12px;"><?= sanitize('Updated: ' . $archive['updatedAt']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <a class="btn secondary" href="/contractor/tender_archive_view.php?id=<?= sanitize($archive['id']); ?>"><?= sanitize('Open'); ?></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    });
});
