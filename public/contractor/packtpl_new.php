<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $globalTemplates = load_template_index('global');
    $contractorTemplates = load_template_index('contractor', $yojId);

    $title = get_app_config()['appName'] . ' | Create Pack Preset';
    render_layout($title, function () use ($globalTemplates, $contractorTemplates) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div>
                <h2 style="margin:0;">Create Pack Preset</h2>
                <p class="muted" style="margin:4px 0 0;">Build a reusable checklist + templates bundle.</p>
            </div>
            <form method="post" action="/contractor/packtpl_create.php" style="display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <label>Title
                    <input name="title" required minlength="3" maxlength="80" placeholder="e.g., Standard Tender Submission Pack">
                </label>
                <label>Description
                    <input name="description" maxlength="180" placeholder="Short description">
                </label>
                <label>Checklist Items (one per line, use "Category | Item". Add (optional) to mark optional)
                    <textarea name="checklist_items" rows="6" placeholder="Eligibility | GST registration (required)&#10;Financial | PAN card (optional)"></textarea>
                </label>
                <label>Templates Section (select templates)
                    <select name="template_ids[]" multiple size="6">
                        <?php if ($globalTemplates): ?>
                            <optgroup label="YOJAK Templates">
                                <?php foreach ($globalTemplates as $tpl): ?>
                                    <option value="<?= sanitize($tpl['templateId'] ?? ''); ?>"><?= sanitize($tpl['title'] ?? 'Template'); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if ($contractorTemplates): ?>
                            <optgroup label="My Templates">
                                <?php foreach ($contractorTemplates as $tpl): ?>
                                    <option value="<?= sanitize($tpl['templateId'] ?? ''); ?>"><?= sanitize($tpl['title'] ?? 'Template'); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </label>
                <label>Attachment Tags (comma-separated)
                    <input name="attachment_tags" placeholder="GST, PAN, ITR">
                </label>
                <label>Custom Section Label (optional)
                    <input name="custom_label" placeholder="Additional Notes">
                </label>
                <label>Custom Section Items (one per line)
                    <textarea name="custom_items" rows="4" placeholder="Any extra instructions"></textarea>
                </label>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button class="btn" type="submit">Save Preset</button>
                    <a class="btn secondary" href="/contractor/packs_blueprints.php?tab=mine">Cancel</a>
                </div>
            </form>
        </div>
        <?php
    });
});
