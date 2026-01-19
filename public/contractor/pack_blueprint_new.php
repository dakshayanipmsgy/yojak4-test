<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $templates = templates_available_for_contractor($yojId);
    $title = get_app_config()['appName'] . ' | New Pack Blueprint';

    render_layout($title, function () use ($templates) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div>
                <h2 style="margin:0;"><?= sanitize('Create Pack Blueprint'); ?></h2>
                <p class="muted" style="margin:4px 0 0;"><?= sanitize('Define checklist items, required fields, and included templates.'); ?></p>
            </div>
            <form method="post" action="/contractor/pack_blueprint_create.php" style="display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <label>
                    <?= sanitize('Title') ?>
                    <input type="text" name="title" required>
                </label>
                <label>
                    <?= sanitize('Description') ?>
                    <textarea name="description" rows="2"></textarea>
                </label>
                <label>
                    <?= sanitize('Checklist items (one per line: Title | required(optional) | category(optional))') ?>
                    <textarea name="checklist" rows="6" placeholder="GST certificate | required | Eligibility"></textarea>
                </label>
                <label>
                    <?= sanitize('Required field keys (comma-separated)') ?>
                    <input type="text" name="requiredFieldKeys" placeholder="firm.name,tax.pan">
                    <span class="muted"><?= sanitize('Examples: firm.name, firm.address, tax.gst, tax.pan, bank.ifsc'); ?></span>
                </label>
                <div>
                    <strong><?= sanitize('Include templates') ?></strong>
                    <div style="display:grid;gap:6px;margin-top:6px;">
                        <?php if (!$templates): ?>
                            <span class="muted"><?= sanitize('No templates available yet.'); ?></span>
                        <?php endif; ?>
                        <?php foreach ($templates as $tpl): ?>
                            <label>
                                <input type="checkbox" name="templates[]" value="<?= sanitize($tpl['id'] ?? ''); ?>">
                                <?= sanitize($tpl['title'] ?? 'Template'); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <strong><?= sanitize('Print structure') ?></strong>
                    <div style="display:grid;gap:6px;margin-top:6px;">
                        <label><input type="checkbox" name="print_include_index" value="1" checked> <?= sanitize('Include Index') ?></label>
                        <label><input type="checkbox" name="print_include_checklist" value="1" checked> <?= sanitize('Include Checklist') ?></label>
                        <label><input type="checkbox" name="print_include_templates" value="1" checked> <?= sanitize('Include Templates') ?></label>
                    </div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="btn" type="submit"><?= sanitize('Save Blueprint'); ?></button>
                    <a class="btn secondary" href="/contractor/tender_pack_blueprints.php"><?= sanitize('Back'); ?></a>
                </div>
            </form>
        </div>
        <?php
    });
});
