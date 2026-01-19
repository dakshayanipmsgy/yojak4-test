<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packTplId = trim((string)($_GET['id'] ?? ''));
    if ($packTplId === '') {
        render_error_page('Pack preset not found.');
        return;
    }

    $preset = load_packtpl_record('contractor', $packTplId, $yojId);
    if (!$preset || (($preset['owner']['yojId'] ?? '') !== $yojId)) {
        render_error_page('Pack preset not found.');
        return;
    }

    $globalTemplates = load_template_index('global');
    $contractorTemplates = load_template_index('contractor', $yojId);

    $checklistLines = [];
    $templateIds = [];
    $attachmentTags = [];
    $customLabel = '';
    $customItems = [];

    foreach ($preset['sections'] ?? [] as $section) {
        $sectionId = $section['sectionId'] ?? '';
        if ($sectionId === 'checklist') {
            foreach ($section['items'] ?? [] as $item) {
                $label = (string)($item['label'] ?? '');
                $category = trim((string)($item['category'] ?? ''));
                $required = (bool)($item['required'] ?? true);
                $line = $category !== '' ? $category . ' | ' . $label : $label;
                if (!$required) {
                    $line .= ' (optional)';
                }
                $checklistLines[] = $line;
            }
        }
        if ($sectionId === 'templates') {
            $templateIds = array_values(array_unique(array_merge($templateIds, $section['templateIds'] ?? [])));
        }
        if ($sectionId === 'attachments') {
            $attachmentTags = array_values(array_unique(array_merge($attachmentTags, $section['allowedTags'] ?? [])));
        }
        if (str_starts_with($sectionId, 'custom')) {
            $customLabel = (string)($section['label'] ?? 'Custom');
            $customItems = array_values(array_filter(array_map('strval', $section['items'] ?? [])));
        }
    }

    $title = get_app_config()['appName'] . ' | Edit Pack Preset';
    render_layout($title, function () use ($preset, $globalTemplates, $contractorTemplates, $checklistLines, $templateIds, $attachmentTags, $customLabel, $customItems) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div>
                <h2 style="margin:0;">Edit Pack Preset</h2>
                <p class="muted" style="margin:4px 0 0;">Update sections below.</p>
            </div>
            <form method="post" action="/contractor/packtpl_update.php" style="display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="id" value="<?= sanitize($preset['packTplId'] ?? ''); ?>">
                <label>Title
                    <input name="title" required minlength="3" maxlength="80" value="<?= sanitize($preset['title'] ?? ''); ?>">
                </label>
                <label>Description
                    <input name="description" maxlength="180" value="<?= sanitize($preset['description'] ?? ''); ?>">
                </label>
                <label>Checklist Items (one per line, use "Category | Item". Add (optional) to mark optional)
                    <textarea name="checklist_items" rows="6"><?= sanitize(implode("\n", $checklistLines)); ?></textarea>
                </label>
                <label>Templates Section (select templates)
                    <select name="template_ids[]" multiple size="6">
                        <?php if ($globalTemplates): ?>
                            <optgroup label="YOJAK Templates">
                                <?php foreach ($globalTemplates as $tpl): ?>
                                    <option value="<?= sanitize($tpl['templateId'] ?? ''); ?>" <?= in_array($tpl['templateId'] ?? '', $templateIds, true) ? 'selected' : ''; ?>><?= sanitize($tpl['title'] ?? 'Template'); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if ($contractorTemplates): ?>
                            <optgroup label="My Templates">
                                <?php foreach ($contractorTemplates as $tpl): ?>
                                    <option value="<?= sanitize($tpl['templateId'] ?? ''); ?>" <?= in_array($tpl['templateId'] ?? '', $templateIds, true) ? 'selected' : ''; ?>><?= sanitize($tpl['title'] ?? 'Template'); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </label>
                <label>Attachment Tags (comma-separated)
                    <input name="attachment_tags" value="<?= sanitize(implode(', ', $attachmentTags)); ?>">
                </label>
                <label>Custom Section Label (optional)
                    <input name="custom_label" value="<?= sanitize($customLabel); ?>">
                </label>
                <label>Custom Section Items (one per line)
                    <textarea name="custom_items" rows="4"><?= sanitize(implode("\n", $customItems)); ?></textarea>
                </label>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button class="btn" type="submit">Update Preset</button>
                    <a class="btn secondary" href="/contractor/packs_blueprints.php?tab=mine">Cancel</a>
                </div>
            </form>
        </div>
        <?php
    });
});
