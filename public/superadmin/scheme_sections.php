<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_scheme_builder();
    $schemeId = trim($_GET['schemeId'] ?? '');
    if ($schemeId === '') {
        render_error_page('Scheme ID missing.');
        return;
    }

    $scheme = scheme_load_metadata($schemeId);
    if (!$scheme) {
        render_error_page('Scheme not found.');
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $action = trim($_POST['action'] ?? '');
        $sectionId = trim($_POST['sectionId'] ?? '');
        $entries = scheme_sections_index($schemeId);
        $updated = false;

        if ($action === 'toggle_enabled' && $sectionId !== '') {
            foreach ($entries as &$entry) {
                if (($entry['sectionId'] ?? '') === $sectionId) {
                    $entry['enabled'] = !($entry['enabled'] ?? true);
                    $entry['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
                    $updated = true;
                    break;
                }
            }
            unset($entry);
        }

        if (($action === 'move_up' || $action === 'move_down') && $sectionId !== '') {
            $entries = scheme_sections_sorted($entries);
            foreach ($entries as $index => $entry) {
                if (($entry['sectionId'] ?? '') === $sectionId) {
                    $swapIndex = $action === 'move_up' ? $index - 1 : $index + 1;
                    if (isset($entries[$swapIndex])) {
                        $currentOrder = $entries[$index]['order'] ?? '';
                        $entries[$index]['order'] = $entries[$swapIndex]['order'] ?? '';
                        $entries[$swapIndex]['order'] = $currentOrder;
                        $entries[$index]['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
                        $entries[$swapIndex]['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
                        $updated = true;
                    }
                    break;
                }
            }
        }

        if ($action === 'delete' && $sectionId !== '') {
            $filtered = [];
            foreach ($entries as $entry) {
                if (($entry['sectionId'] ?? '') === $sectionId) {
                    $filename = $entry['file'] ?? '';
                    if ($filename) {
                        $path = scheme_section_path($schemeId, $filename);
                        if (file_exists($path)) {
                            unlink($path);
                        }
                    }
                    $updated = true;
                    continue;
                }
                $filtered[] = $entry;
            }
            $entries = $filtered;
        }

        if ($updated) {
            scheme_sections_write_index($schemeId, $entries);
            $compileErrors = [];
            $compileWarnings = [];
            $compiled = scheme_compile_definition($schemeId, $compileErrors, $compileWarnings);
            if ($compiled) {
                writeJsonAtomic(scheme_compiled_definition_path($schemeId), $compiled);
            }
            scheme_log_import($schemeId, $compiled ? 'COMPILE_OK' : 'COMPILE_FAIL', $compileErrors);
            $newVersion = (int)($scheme['version'] ?? 1) + 1;
            scheme_update_metadata($schemeId, ['version' => $newVersion]);
            set_flash('success', 'Scheme sections updated.' . ($compiled ? ' Compiled successfully.' : ' Compilation pending.'));
        }

        redirect('/superadmin/scheme_sections.php?schemeId=' . urlencode($schemeId));
    }

    $entries = scheme_sections_sorted(scheme_sections_index($schemeId));
    $enabledPayloads = scheme_sections_payloads($schemeId, null, true);
    $availableKeys = scheme_collect_section_component_keys($enabledPayloads);

    $title = get_app_config()['appName'] . ' | Scheme Sections';
    render_layout($title, function () use ($schemeId, $scheme, $entries, $availableKeys) {
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Scheme Sections</h2>
                    <p class="muted" style="margin:6px 0 0;">Scheme: <?= sanitize($scheme['name'] ?? ''); ?> (<?= sanitize($schemeId); ?>)</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn secondary" href="/superadmin/schemes.php">Back to Schemes</a>
                    <a class="btn" href="/superadmin/scheme_section_import.php?schemeId=<?= urlencode($schemeId); ?>">Import Section</a>
                    <form method="post" action="/superadmin/scheme_recompile.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeId" value="<?= sanitize($schemeId); ?>">
                        <button class="btn secondary" type="submit">Recompile Scheme</button>
                    </form>
                    <form method="post" action="/superadmin/scheme_publish.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeId" value="<?= sanitize($schemeId); ?>">
                        <button class="btn secondary" type="submit">Publish Scheme</button>
                    </form>
                </div>
            </div>

            <?php if (!$entries): ?>
                <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                    <p class="muted" style="margin:0;">No sections imported yet. Start by importing a base section.</p>
                </div>
            <?php endif; ?>

            <div style="display:grid;gap:12px;">
                <?php foreach ($entries as $index => $entry): ?>
                    <?php
                    $sectionPayload = [];
                    $filename = $entry['file'] ?? '';
                    if ($filename) {
                        $sectionPayload = readJson(scheme_section_path($schemeId, $filename));
                    }
                    $components = $sectionPayload['components'] ?? [];
                    $entitiesCount = is_array($components['entities'] ?? null) ? count($components['entities']) : 0;
                    $docsCount = is_array($components['documents'] ?? null) ? count($components['documents']) : 0;
                    $fieldsCount = is_array($components['fieldCatalog'] ?? null) ? count($components['fieldCatalog']) : 0;
                    $tablesCount = 0;
                    $recordTemplates = $components['recordTemplates'] ?? [];
                    if (is_array($recordTemplates)) {
                        $tablesCount = array_keys($recordTemplates) === range(0, count($recordTemplates) - 1)
                            ? count($recordTemplates)
                            : count(array_keys($recordTemplates));
                    }
                    $transitionsCount = is_array($components['workflow']['transitions'] ?? null) ? count($components['workflow']['transitions']) : 0;
                    $normalized = $sectionPayload;
                    $warnings = [];
                    $errors = $sectionPayload ? scheme_validate_section($sectionPayload, $schemeId, $availableKeys, $normalized, $warnings) : ['Section file missing or invalid.'];
                    ?>
                    <div class="card" style="background:var(--surface-2);display:grid;gap:10px;">
                        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start;">
                            <div>
                                <h3 style="margin:0 0 4px 0;"><?= sanitize($entry['title'] ?? $sectionPayload['title'] ?? 'Section'); ?></h3>
                                <p class="muted" style="margin:0;">Order: <?= sanitize($entry['order'] ?? ''); ?> • ID: <?= sanitize($entry['sectionId'] ?? ''); ?></p>
                            </div>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <span class="pill" style="border-color:var(--border);color:var(--text);">
                                    <?= sanitize(ucfirst((string)($entry['status'] ?? 'draft'))); ?>
                                </span>
                                <span class="pill" style="border-color:<?= $errors ? '#f08c00' : '#16a34a'; ?>;color:<?= $errors ? '#f08c00' : '#16a34a'; ?>;">
                                    <?= $errors ? 'Has errors' : 'Valid'; ?>
                                </span>
                                <span class="pill" style="border-color:var(--border);color:var(--text);">
                                    <?= ($entry['enabled'] ?? true) ? 'Enabled' : 'Disabled'; ?>
                                </span>
                            </div>
                        </div>

                        <p class="muted" style="margin:0;"><?= sanitize($entry['description'] ?? $sectionPayload['description'] ?? ''); ?></p>

                        <div style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));">
                            <div class="card" style="background:var(--surface);padding:10px;">
                                <p class="muted" style="margin:0 0 4px 0;">Entities</p>
                                <strong><?= sanitize((string)$entitiesCount); ?></strong>
                            </div>
                            <div class="card" style="background:var(--surface);padding:10px;">
                                <p class="muted" style="margin:0 0 4px 0;">Documents</p>
                                <strong><?= sanitize((string)$docsCount); ?></strong>
                            </div>
                            <div class="card" style="background:var(--surface);padding:10px;">
                                <p class="muted" style="margin:0 0 4px 0;">Fields</p>
                                <strong><?= sanitize((string)$fieldsCount); ?></strong>
                            </div>
                            <div class="card" style="background:var(--surface);padding:10px;">
                                <p class="muted" style="margin:0 0 4px 0;">Tables</p>
                                <strong><?= sanitize((string)$tablesCount); ?></strong>
                            </div>
                            <div class="card" style="background:var(--surface);padding:10px;">
                                <p class="muted" style="margin:0 0 4px 0;">Transitions</p>
                                <strong><?= sanitize((string)$transitionsCount); ?></strong>
                            </div>
                        </div>

                        <?php if ($errors): ?>
                            <div class="pill" style="border-color:#f08c00;color:#f08c00;">
                                <?= sanitize(implode(' ', array_slice($errors, 0, 2))); ?>
                            </div>
                        <?php endif; ?>

                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a class="btn secondary" href="/superadmin/scheme_section_import.php?schemeId=<?= urlencode($schemeId); ?>&sectionId=<?= urlencode((string)($entry['sectionId'] ?? '')); ?>&mode=view">View JSON</a>
                            <a class="btn secondary" href="/superadmin/scheme_section_import.php?schemeId=<?= urlencode($schemeId); ?>&sectionId=<?= urlencode((string)($entry['sectionId'] ?? '')); ?>">Re-import</a>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="schemeId" value="<?= sanitize($schemeId); ?>">
                                <input type="hidden" name="sectionId" value="<?= sanitize((string)($entry['sectionId'] ?? '')); ?>">
                                <input type="hidden" name="action" value="toggle_enabled">
                                <button class="btn secondary" type="submit"><?= ($entry['enabled'] ?? true) ? 'Disable' : 'Enable'; ?></button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="schemeId" value="<?= sanitize($schemeId); ?>">
                                <input type="hidden" name="sectionId" value="<?= sanitize((string)($entry['sectionId'] ?? '')); ?>">
                                <input type="hidden" name="action" value="move_up">
                                <button class="btn secondary" type="submit" <?= $index === 0 ? 'disabled' : ''; ?>>Move Up</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="schemeId" value="<?= sanitize($schemeId); ?>">
                                <input type="hidden" name="sectionId" value="<?= sanitize((string)($entry['sectionId'] ?? '')); ?>">
                                <input type="hidden" name="action" value="move_down">
                                <button class="btn secondary" type="submit" <?= $index === (count($entries) - 1) ? 'disabled' : ''; ?>>Move Down</button>
                            </form>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this section? This cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="schemeId" value="<?= sanitize($schemeId); ?>">
                                <input type="hidden" name="sectionId" value="<?= sanitize((string)($entry['sectionId'] ?? '')); ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="btn danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
