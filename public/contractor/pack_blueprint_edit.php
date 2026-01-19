<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $id = trim((string)($_GET['id'] ?? ''));
    if ($id === '') {
        render_error_page('Missing blueprint id.');
        return;
    }
    $blueprint = pack_blueprint_load('contractor', $id, $yojId);
    if (!$blueprint || (($blueprint['owner']['yojId'] ?? '') !== $yojId)) {
        render_error_page('Blueprint not found.');
        return;
    }
    $templates = templates_available_for_contractor($yojId);

    $checklistText = '';
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

    $requiredFieldKeys = implode(',', $blueprint['items']['requiredFieldKeys'] ?? []);
    $selectedTemplates = $blueprint['items']['templates'] ?? [];
    $title = get_app_config()['appName'] . ' | Edit Pack Blueprint';

    render_layout($title, function () use ($blueprint, $templates, $checklistText, $requiredFieldKeys, $selectedTemplates) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div>
                <h2 style="margin:0;"><?= sanitize('Edit Pack Blueprint'); ?></h2>
                <p class="muted" style="margin:4px 0 0;"><?= sanitize('Update your checklist, required fields, and included templates.'); ?></p>
            </div>
            <form method="post" action="/contractor/pack_blueprint_update.php" style="display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="id" value="<?= sanitize($blueprint['id'] ?? ''); ?>">
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
                            <span class="muted"><?= sanitize('No templates available yet.'); ?></span>
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
                    <button class="btn" type="submit"><?= sanitize('Save Changes'); ?></button>
                    <a class="btn secondary" href="/contractor/tender_pack_blueprints.php"><?= sanitize('Back'); ?></a>
                </div>
            </form>
        </div>
        <?php
    });
});
