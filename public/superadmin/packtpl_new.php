<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_staff_actor();
    $globalTemplates = load_template_index('global');

    $defaultPack = [
        'packTplId' => generate_packtpl_id('global'),
        'scope' => 'global',
        'owner' => ['yojId' => 'YOJAK'],
        'title' => 'Standard Tender Submission Pack',
        'description' => '',
        'sections' => [
            ['sectionId' => 'checklist', 'label' => 'Checklist', 'items' => []],
            ['sectionId' => 'templates', 'label' => 'Templates', 'templateIds' => []],
            ['sectionId' => 'attachments', 'label' => 'Attachments', 'allowedTags' => []],
        ],
    ];

    $title = get_app_config()['appName'] . ' | Create Pack Preset';
    render_layout($title, function () use ($globalTemplates, $defaultPack) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div>
                <h2 style="margin:0;">Create Global Pack Preset</h2>
                <p class="muted" style="margin:4px 0 0;">Guided editor with optional advanced JSON.</p>
            </div>
            <form method="post" action="/superadmin/packtpl_create.php" style="display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <label>Title
                    <input name="title" required minlength="3" maxlength="80" value="<?= sanitize($defaultPack['title']); ?>">
                </label>
                <label>Description
                    <input name="description" maxlength="180">
                </label>
                <label>Checklist Items (one per line, use "Category | Item". Add (optional) to mark optional)
                    <textarea name="checklist_items" rows="6"></textarea>
                </label>
                <label>Templates Section (select templates)
                    <select name="template_ids[]" multiple size="6">
                        <?php foreach ($globalTemplates as $tpl): ?>
                            <option value="<?= sanitize($tpl['templateId'] ?? ''); ?>"><?= sanitize($tpl['title'] ?? 'Template'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Attachment Tags (comma-separated)
                    <input name="attachment_tags" placeholder="GST, PAN, ITR">
                </label>
                <label>Custom Section Label (optional)
                    <input name="custom_label" placeholder="Additional Notes">
                </label>
                <label>Custom Section Items (one per line)
                    <textarea name="custom_items" rows="4"></textarea>
                </label>
                <details>
                    <summary>Advanced JSON (staff only)</summary>
                    <p class="muted">Paste validated JSON. IDs must be unique.</p>
                    <textarea name="advanced_json" rows="10" style="width:100%;"><?= sanitize(json_encode($defaultPack, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea>
                    <label class="pill" style="display:inline-flex;gap:6px;align-items:center;margin-top:6px;">
                        <input type="checkbox" name="apply_json" value="1"> Apply Advanced JSON
                    </label>
                </details>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button class="btn" type="submit">Save Preset</button>
                    <a class="btn secondary" href="/superadmin/packs_blueprints.php">Cancel</a>
                </div>
            </form>
        </div>
        <?php
    });
});
