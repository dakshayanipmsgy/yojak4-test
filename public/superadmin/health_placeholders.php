<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_role('superadmin');
    $didMigrate = false;
    $migratedCount = 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'auto_migrate') {
        require_csrf();
        $didMigrate = true;
    }

    $results = [];
    $total = 0;
    $registry = placeholder_registry();

    $templates = load_global_templates();
    foreach ($templates as $template) {
        $body = (string)($template['body'] ?? '');
        $stats = [];
        if ($didMigrate) {
            $body = migrate_placeholders_to_canonical($body, $stats);
            if ($body !== (string)($template['body'] ?? '')) {
                $template['body'] = $body;
                save_global_template($template);
                $migratedCount++;
            }
        }
        $validation = validate_placeholders($body, $registry);
        $results[] = [
            'label' => $template['title'] ?? ($template['id'] ?? 'Global Template'),
            'type' => 'global_template',
            'link' => '/superadmin/template_edit.php?id=' . urlencode((string)($template['id'] ?? '')),
            'invalidTokens' => $validation['invalidTokens'],
            'unknownKeys' => $validation['unknownKeys'],
            'deprecatedTokens' => $validation['deprecatedTokens'],
        ];
        $total++;
    }

    $globalPacks = load_global_packs();
    foreach ($globalPacks as $pack) {
        foreach ((array)($pack['templates'] ?? $pack['annexureTemplates'] ?? []) as $tpl) {
            if (!is_array($tpl)) {
                continue;
            }
            $body = (string)($tpl['body'] ?? ($tpl['bodyHtml'] ?? ($tpl['templateBody'] ?? '')));
            if ($body === '') {
                continue;
            }
            $stats = [];
            if ($didMigrate) {
                $body = migrate_placeholders_to_canonical($body, $stats);
            }
            $validation = validate_placeholders($body, $registry);
            $results[] = [
                'label' => ($tpl['title'] ?? $tpl['name'] ?? 'Global Pack Template') . ' (Global Pack)',
                'type' => 'global_pack',
                'link' => '/superadmin/packs.php',
                'invalidTokens' => $validation['invalidTokens'],
                'unknownKeys' => $validation['unknownKeys'],
                'deprecatedTokens' => $validation['deprecatedTokens'],
            ];
            $total++;
        }
    }

    foreach (glob(DATA_PATH . '/schemes/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $schemeCode = basename($dir);
        $draft = readJson($dir . '/draft.json');
        if (is_array($draft)) {
            $schemeRegistry = placeholder_registry(['scheme' => $draft]);
            foreach ((array)($draft['documents'] ?? []) as $doc) {
                if (!is_array($doc)) {
                    continue;
                }
                $body = (string)($doc['templateBody'] ?? '');
                $stats = [];
                if ($didMigrate) {
                    $body = migrate_placeholders_to_canonical($body, $stats);
                    if ($body !== (string)($doc['templateBody'] ?? '')) {
                        $doc['templateBody'] = $body;
                    }
                }
                $validation = validate_placeholders($body, $schemeRegistry);
                $results[] = [
                    'label' => ($doc['label'] ?? $doc['docId'] ?? 'Scheme Doc') . ' (Draft)',
                    'type' => 'scheme_doc_draft',
                    'link' => '/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=documents',
                    'invalidTokens' => $validation['invalidTokens'],
                    'unknownKeys' => $validation['unknownKeys'],
                    'deprecatedTokens' => $validation['deprecatedTokens'],
                ];
                $total++;
            }
            if ($didMigrate) {
                save_scheme_draft($schemeCode, $draft);
            }
        }

        $versionsDir = $dir . '/versions';
        if (is_dir($versionsDir)) {
            $versionFiles = glob($versionsDir . '/*.json') ?: [];
            usort($versionFiles, static fn($a, $b) => filemtime($b) <=> filemtime($a));
            $latest = $versionFiles[0] ?? null;
            if ($latest && file_exists($latest)) {
                $version = readJson($latest);
                if (is_array($version)) {
                    $schemeRegistry = placeholder_registry(['scheme' => $version]);
                    $updatedDocs = [];
                    foreach ((array)($version['documents'] ?? []) as $doc) {
                        if (!is_array($doc)) {
                            continue;
                        }
                        $body = (string)($doc['templateBody'] ?? '');
                        $stats = [];
                        if ($didMigrate) {
                            $body = migrate_placeholders_to_canonical($body, $stats);
                            if ($body !== (string)($doc['templateBody'] ?? '')) {
                                $doc['templateBody'] = $body;
                            }
                        }
                        $validation = validate_placeholders($body, $schemeRegistry);
                        $results[] = [
                            'label' => ($doc['label'] ?? $doc['docId'] ?? 'Scheme Doc') . ' (Latest)',
                            'type' => 'scheme_doc_latest',
                            'link' => '/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=latest&tab=documents',
                            'invalidTokens' => $validation['invalidTokens'],
                            'unknownKeys' => $validation['unknownKeys'],
                            'deprecatedTokens' => $validation['deprecatedTokens'],
                        ];
                        $updatedDocs[] = $doc;
                        $total++;
                    }
                    if ($didMigrate && $updatedDocs) {
                        $version['documents'] = $updatedDocs;
                        writeJsonAtomic($latest, $version);
                        $migratedCount++;
                    }
                }
            }
        }
    }

    $yojId = trim((string)($_GET['yojId'] ?? ''));
    if ($yojId !== '') {
        foreach (load_contractor_templates_full($yojId) as $template) {
            $body = (string)($template['body'] ?? '');
            $stats = [];
            if ($didMigrate) {
                $body = migrate_placeholders_to_canonical($body, $stats);
                if ($body !== (string)($template['body'] ?? '')) {
                    $template['body'] = $body;
                    save_contractor_template($yojId, $template);
                    $migratedCount++;
                }
            }
            $contractor = load_contractor($yojId) ?? [];
            $memory = load_profile_memory($yojId);
            $contractorRegistry = placeholder_registry([
                'contractor' => $contractor,
                'memory' => $memory,
            ]);
            $validation = validate_placeholders($body, $contractorRegistry);
            $results[] = [
                'label' => ($template['title'] ?? $template['name'] ?? 'Contractor Template') . ' (Contractor)',
                'type' => 'contractor_template',
                'link' => '',
                'invalidTokens' => $validation['invalidTokens'],
                'unknownKeys' => $validation['unknownKeys'],
                'deprecatedTokens' => $validation['deprecatedTokens'],
            ];
            $total++;
        }
    }

    render_layout('Placeholder Health Check', function () use ($results, $total, $didMigrate, $migratedCount, $yojId) {
        ?>
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Placeholder Health Check</h2>
                    <p class="muted" style="margin:4px 0 0;">Scan templates for invalid or unknown placeholders.</p>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="action" value="auto_migrate">
                    <button class="btn secondary" type="submit">Auto-migrate placeholders</button>
                </form>
            </div>
            <div style="margin-top:12px;display:flex;gap:12px;flex-wrap:wrap;">
                <span class="pill">Templates scanned: <?= (int)$total; ?></span>
                <?php if ($didMigrate): ?>
                    <span class="pill">Migrated: <?= (int)$migratedCount; ?></span>
                <?php endif; ?>
            </div>
            <form method="get" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
                <input type="text" name="yojId" value="<?= sanitize($yojId); ?>" placeholder="YOJ-XXXXX (optional)">
                <button class="btn secondary" type="submit">Scan Contractor Templates</button>
            </form>
        </div>

        <div class="card" style="margin-top:12px;">
            <table>
                <thead>
                    <tr>
                        <th>Template</th>
                        <th>Invalid Tokens</th>
                        <th>Unknown Keys</th>
                        <th>Deprecated Tokens</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$results): ?>
                    <tr><td colspan="5" class="muted">No templates found.</td></tr>
                <?php endif; ?>
                <?php foreach ($results as $entry): ?>
                    <tr>
                        <td><?= sanitize($entry['label']); ?></td>
                        <td>
                            <?php if ($entry['invalidTokens']): ?>
                                <?= sanitize(implode(', ', $entry['invalidTokens'])); ?>
                            <?php else: ?>
                                <span class="muted">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($entry['unknownKeys']): ?>
                                <?= sanitize(implode(', ', $entry['unknownKeys'])); ?>
                            <?php else: ?>
                                <span class="muted">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($entry['deprecatedTokens']): ?>
                                <?= sanitize(implode(', ', $entry['deprecatedTokens'])); ?>
                            <?php else: ?>
                                <span class="muted">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($entry['link'])): ?>
                                <a class="btn secondary" href="<?= sanitize($entry['link']); ?>">Open template</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
