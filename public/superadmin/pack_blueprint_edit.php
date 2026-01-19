<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_superadmin_or_permission('pack_blueprints_manage');
    $id = trim((string)($_GET['id'] ?? ''));
    $download = ($_GET['download'] ?? '') === '1';
    $blueprint = null;
    if ($id !== '') {
        $blueprint = pack_blueprint_load('global', $id);
        if (!$blueprint) {
            render_error_page('Blueprint not found.');
            return;
        }
    }

    if ($download) {
        if (!$blueprint) {
            render_error_page('Blueprint not found.');
            return;
        }
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . basename($blueprint['id'] ?? 'blueprint') . '.json"');
        echo json_encode($blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return;
    }

    $templates = template_list('global');
    $checklistText = '';
    if ($blueprint) {
        foreach ($blueprint['items']['checklist'] ?? [] as $item) {
            $line = trim((string)($item['title'] ?? ''));
            if ($line === '') {
                continue;
            }
            $required = !empty($item['required']) ? 'required' : 'optional';
            $category = trim((string)($item['category'] ?? ''));
            $checklistText .= $line . ' | ' . $required;
            if ($category !== '') {
                $checklistText .= ' | ' . $category;
            }
            $checklistText .= PHP_EOL;
        }
    }

    $requiredFieldKeys = $blueprint ? implode(',', $blueprint['items']['requiredFieldKeys'] ?? []) : '';
    $selectedTemplates = $blueprint['items']['templates'] ?? [];
    $title = get_app_config()['appName'] . ' | Pack Blueprint Editor';

    render_layout($title, function () use ($blueprint, $templates, $checklistText, $requiredFieldKeys, $selectedTemplates) {
        $jsonValue = $blueprint ? json_encode($blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div>
                <h2 style="margin:0;"><?= sanitize($blueprint ? 'Edit Global Pack Blueprint' : 'Create Global Pack Blueprint'); ?></h2>
                <p class="muted" style="margin:4px 0 0;"><?= sanitize('Staff can edit via form or Advanced JSON.'); ?></p>
            </div>
            <div class="tabs">
                <button class="tab active" data-tab="editor"><?= sanitize('Editor'); ?></button>
                <button class="tab" data-tab="json"><?= sanitize('Advanced JSON'); ?></button>
            </div>

            <div class="tab-content active" id="tab-editor">
                <form method="post" action="/superadmin/pack_blueprint_save.php" style="display:grid;gap:12px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <?php if ($blueprint): ?>
                        <input type="hidden" name="id" value="<?= sanitize($blueprint['id'] ?? ''); ?>">
                    <?php endif; ?>
                    <label>
                        <?= sanitize('Title') ?>
                        <input type="text" name="title" required value="<?= sanitize($blueprint['title'] ?? ''); ?>">
                    </label>
                    <label>
                        <?= sanitize('Description') ?>
                        <textarea name="description" rows="2"><?= sanitize($blueprint['description'] ?? ''); ?></textarea>
                    </label>
                    <label>
                        <?= sanitize('Checklist items (one per line: Title | required(optional) | category(optional))') ?>
                        <textarea name="checklist" rows="6"><?= sanitize($checklistText); ?></textarea>
                    </label>
                    <label>
                        <?= sanitize('Required field keys (comma-separated)') ?>
                        <input type="text" name="requiredFieldKeys" value="<?= sanitize($requiredFieldKeys); ?>">
                        <span class="muted"><?= sanitize('Examples: firm.name, firm.address, tax.gst, tax.pan, bank.ifsc'); ?></span>
                    </label>
                    <div>
                        <strong><?= sanitize('Include templates') ?></strong>
                        <div style="display:grid;gap:6px;margin-top:6px;">
                            <?php if (!$templates): ?>
                                <span class="muted"><?= sanitize('No global templates available yet.'); ?></span>
                            <?php endif; ?>
                            <?php foreach ($templates as $tpl): ?>
                                <?php $tplId = $tpl['id'] ?? ''; ?>
                                <label>
                                    <input type="checkbox" name="templates[]" value="<?= sanitize($tplId); ?>" <?= in_array($tplId, $selectedTemplates, true) ? 'checked' : ''; ?>>
                                    <?= sanitize($tpl['title'] ?? 'Template'); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <strong><?= sanitize('Print structure') ?></strong>
                        <div style="display:grid;gap:6px;margin-top:6px;">
                            <label><input type="checkbox" name="print_include_index" value="1" <?= !empty($blueprint['printStructure']['includeIndex']) ? 'checked' : ''; ?>> <?= sanitize('Include Index') ?></label>
                            <label><input type="checkbox" name="print_include_checklist" value="1" <?= !empty($blueprint['printStructure']['includeChecklist']) ? 'checked' : ''; ?>> <?= sanitize('Include Checklist') ?></label>
                            <label><input type="checkbox" name="print_include_templates" value="1" <?= !empty($blueprint['printStructure']['includeTemplates']) ? 'checked' : ''; ?>> <?= sanitize('Include Templates') ?></label>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button class="btn" type="submit"><?= sanitize('Save Blueprint'); ?></button>
                        <?php if ($blueprint): ?>
                            <a class="btn secondary" href="/superadmin/pack_blueprint_edit.php?id=<?= sanitize($blueprint['id'] ?? ''); ?>&download=1"><?= sanitize('Export JSON'); ?></a>
                        <?php endif; ?>
                        <a class="btn secondary" href="/superadmin/pack_blueprints.php"><?= sanitize('Back'); ?></a>
                    </div>
                </form>
            </div>

            <div class="tab-content" id="tab-json">
                <form method="post" action="/superadmin/pack_blueprint_import_json.php" style="display:grid;gap:12px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <?php if ($blueprint): ?>
                        <input type="hidden" name="id" value="<?= sanitize($blueprint['id'] ?? ''); ?>">
                    <?php endif; ?>
                    <label>
                        <?= sanitize('Paste blueprint JSON') ?>
                        <textarea name="json" rows="14" required><?= sanitize($jsonValue); ?></textarea>
                    </label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button class="btn" type="submit"><?= sanitize('Validate & Apply JSON'); ?></button>
                        <a class="btn secondary" href="/superadmin/pack_blueprints.php"><?= sanitize('Back'); ?></a>
                    </div>
                    <p class="muted"><?= sanitize('JSON must match the pack blueprint schema.'); ?></p>
                </form>
            </div>
        </div>
        <style>
            .tabs{display:flex;gap:8px;flex-wrap:wrap;}
            .tab{border:1px solid var(--border);background:var(--surface-2);padding:6px 12px;border-radius:999px;cursor:pointer;color:var(--text);}
            .tab.active{border-color:#1f6feb;background:#0b1f3a;color:#fff;}
            .tab-content{display:none;}
            .tab-content.active{display:block;}
        </style>
        <script>
            document.querySelectorAll('.tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    tab.classList.add('active');
                    const target = document.getElementById('tab-' + tab.dataset.tab);
                    if (target) {
                        target.classList.add('active');
                    }
                });
            });
        </script>
        <?php
    });
});
