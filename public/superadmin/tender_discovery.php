<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    ensure_tender_discovery_env();

    $sources = tender_discovery_sources();
    $state = tender_discovery_state();
    $lastSummary = $state['lastSummary'] ?? null;
    $index = tender_discovery_index();

    $sourceNames = [];
    foreach ($sources as $src) {
        $sourceNames[$src['sourceId']] = $src['name'] ?? $src['sourceId'];
    }

    $latestDiscovered = [];
    foreach ($index as $entry) {
        $discId = $entry['discId'] ?? '';
        if ($discId === '' || !empty($entry['deletedAt'])) {
            continue;
        }
        $latestDiscovered[] = [
            'discId' => $discId,
            'title' => $entry['title'] ?? '',
            'deadlineAt' => $entry['deadlineAt'] ?? null,
            'createdAt' => $entry['createdAt'] ?? '',
            'sourceName' => $sourceNames[$entry['sourceId'] ?? ''] ?? ($entry['sourceId'] ?? ''),
        ];
    }

    usort($latestDiscovered, function ($a, $b) {
        return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
    });
    $latestDiscovered = array_slice($latestDiscovered, 0, 20);

    $title = get_app_config()['appName'] . ' | Tender Discovery';

    render_layout($title, function () use ($sources, $state, $lastSummary, $latestDiscovered) {
        $cronToken = $state['cronToken'] ?? '';
        ?>
        <div class="card" style="display:grid; gap:14px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div>
                    <h2 style="margin:0;">Tender Discovery</h2>
                    <p class="muted" style="margin:4px 0 0;">Configure public sources (Jharkhand) and run discovery.</p>
                </div>
                <div class="buttons" style="gap:8px;">
                    <form method="post" action="/superadmin/tender_discovery_run.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <button class="btn" type="submit">Run now</button>
                    </form>
                    <div class="pill">Last run: <?= sanitize($state['lastRunAt'] ?? 'Never'); ?></div>
                </div>
            </div>
            <div style="display:grid; gap:6px;">
                <div class="pill">Cron token: <?= sanitize($cronToken); ?></div>
                <div class="muted">Cron endpoint: /cron/tender_discovery.php?token=<?= sanitize(urlencode($cronToken)); ?></div>
            </div>
        </div>

        <div class="card" style="margin-top:12px;">
            <h3 style="margin-top:0;">Sources</h3>
            <form method="post" action="/superadmin/tender_discovery_save_sources.php" style="display:grid; gap:10px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div style="overflow:auto;">
                    <table>
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>URL</th>
                            <th>Parse hints (JSON)</th>
                            <th>Active</th>
                        </tr>
                        </thead>
                        <?php $rows = $sources ?: [['sourceId' => '', 'type' => 'rss', 'active' => true]]; ?>
                        <tbody id="source-rows" data-next-index="<?= count($rows); ?>">
                        <?php foreach ($rows as $idx => $src): ?>
                            <tr>
                                <td>
                                    <input type="hidden" name="sources[sourceId][<?= $idx; ?>]" value="<?= sanitize($src['sourceId'] ?? ''); ?>">
                                    <input name="sources[name][<?= $idx; ?>]" value="<?= sanitize($src['name'] ?? ''); ?>" placeholder="Source name" required>
                                </td>
                                <td>
                                    <select name="sources[type][<?= $idx; ?>]" required>
                                        <?php foreach (['rss','html','json'] as $type): ?>
                                            <option value="<?= sanitize($type); ?>" <?= ($src['type'] ?? 'rss') === $type ? 'selected' : ''; ?>><?= strtoupper(sanitize($type)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input name="sources[url][<?= $idx; ?>]" value="<?= sanitize($src['url'] ?? ''); ?>" placeholder="https://" required></td>
                                <td><textarea name="sources[parseHints][<?= $idx; ?>]" rows="2" placeholder='{"xpath":"//a"}'><?= sanitize(json_encode($src['parseHints'] ?? [], JSON_UNESCAPED_SLASHES)); ?></textarea></td>
                                <td style="text-align:center;">
                                    <input type="hidden" name="sources[active][<?= $idx; ?>]" value="0">
                                    <input type="checkbox" name="sources[active][<?= $idx; ?>]" value="1" <?= !empty($src['active']) ? 'checked' : ''; ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="buttons" style="justify-content:space-between;">
                    <div>
                        <button class="btn secondary" type="button" onclick="addSourceRow()">Add source</button>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="btn" type="submit">Save sources</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card" style="margin-top:12px;">
            <h3 style="margin-top:0;">Last run summary</h3>
            <?php if (!$lastSummary): ?>
                <p class="muted" style="margin:0;">No runs yet.</p>
            <?php else: ?>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <div class="pill">Fetched: <?= sanitize((string)($lastSummary['totalFetched'] ?? 0)); ?></div>
                    <div class="pill">New: <?= sanitize((string)($lastSummary['newCount'] ?? 0)); ?></div>
                    <div class="pill">Started: <?= sanitize($lastSummary['startedAt'] ?? ''); ?></div>
                    <div class="pill">Finished: <?= sanitize($lastSummary['finishedAt'] ?? ''); ?></div>
                </div>
                <div style="margin-top:10px; display:grid; gap:8px;">
                    <?php foreach ($lastSummary['perSource'] ?? [] as $row): ?>
                        <div style="border:1px solid var(--border); border-radius:10px; padding:10px;">
                            <strong><?= sanitize($row['name'] ?? $row['sourceId'] ?? 'Source'); ?></strong>
                            <p class="muted" style="margin:4px 0 0;">
                                Fetched <?= sanitize((string)($row['fetched'] ?? 0)); ?> • New <?= sanitize((string)($row['new'] ?? 0)); ?>
                                <?php if (!empty($row['errors'])): ?>
                                    • Errors: <?= sanitize(implode('; ', $row['errors'])); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($lastSummary['errors'])): ?>
                        <div class="flash error">
                            <?= sanitize('Errors encountered'); ?>
                            <ul style="margin:6px 0 0 16px;">
                                <?php foreach ($lastSummary['errors'] as $err): ?>
                                    <li><?= sanitize(($err['sourceId'] ?? '') . ': ' . ($err['message'] ?? '')); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card" style="margin-top:12px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <h3 style="margin:0;">Discovered Tenders (latest)</h3>
                <a class="btn secondary" href="/superadmin/discovered_tenders.php">View all discovered tenders</a>
            </div>
            <div style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <th>Title</th>
                        <th>Deadline</th>
                        <th>Source</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$latestDiscovered): ?>
                        <tr>
                            <td colspan="5">
                                <p class="muted" style="margin:0;">No discovered tenders yet.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($latestDiscovered as $row): ?>
                        <tr>
                            <td><?= sanitize($row['title'] ?? ''); ?></td>
                            <td><?= sanitize($row['deadlineAt'] ?? 'Not provided'); ?></td>
                            <td><?= sanitize($row['sourceName'] ?? ''); ?></td>
                            <td><?= sanitize($row['createdAt'] ?? ''); ?></td>
                            <td>
                                <a class="btn secondary" href="/superadmin/discovered_tender_view.php?id=<?= sanitize(urlencode($row['discId'] ?? '')); ?>">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
            function addSourceRow() {
                const tbody = document.getElementById('source-rows');
                if (!tbody) return;
                const nextIndex = parseInt(tbody.dataset.nextIndex || tbody.children.length, 10) || 0;
                tbody.dataset.nextIndex = String(nextIndex + 1);
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <input type="hidden" name="sources[sourceId][${nextIndex}]" value="">
                        <input name="sources[name][${nextIndex}]" placeholder="Source name" required>
                    </td>
                    <td>
                        <select name="sources[type][${nextIndex}]" required>
                            <option value="rss">RSS</option>
                            <option value="html">HTML</option>
                            <option value="json">JSON</option>
                        </select>
                    </td>
                    <td><input name="sources[url][${nextIndex}]" placeholder="https://" required></td>
                    <td><textarea name="sources[parseHints][${nextIndex}]" rows="2" placeholder='{"xpath":"//a"}'></textarea></td>
                    <td style="text-align:center;">
                        <input type="hidden" name="sources[active][${nextIndex}]" value="0">
                        <input type="checkbox" name="sources[active][${nextIndex}]" value="1" checked>
                    </td>
                `;
                tbody.appendChild(tr);
            }
        </script>
        <?php
    });
});
