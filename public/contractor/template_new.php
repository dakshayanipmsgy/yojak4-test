<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_contractor_library_env($yojId);

    $contractor = load_contractor($yojId) ?? [];
    $memory = load_profile_memory($yojId);
    $fieldGroups = template_library_field_groups($contractor, $memory);

    $fieldsMap = [];
    $sampleMap = [];
    foreach ($fieldGroups as $group) {
        foreach ($group['fields'] as $field) {
            $fieldsMap[$field['key']] = [
                'label' => $field['label'],
                'type' => $field['type'] ?? 'text',
            ];
            $sampleMap[$field['key']] = $field['sample'] ?? '';
        }
    }

    $errors = [];
    $titleValue = '';
    $categoryValue = 'general';
    $descriptionValue = '';
    $bodyValue = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $titleValue = trim((string)($_POST['title'] ?? ''));
        $categoryValue = (string)($_POST['category'] ?? 'general');
        $descriptionValue = trim((string)($_POST['description'] ?? ''));
        $bodyValue = trim((string)($_POST['body'] ?? ''));

        if ($titleValue === '' || mb_strlen($titleValue) < 3 || mb_strlen($titleValue) > 120) {
            $errors[] = 'Title must be between 3 and 120 characters.';
        }
        if (!in_array($categoryValue, ['tender', 'workorder', 'general'], true)) {
            $errors[] = 'Select a valid category.';
        }
        if (mb_strlen($descriptionValue) > 500) {
            $errors[] = 'Description must be under 500 characters.';
        }
        if ($bodyValue === '') {
            $errors[] = 'Template body cannot be empty.';
        }
        if (mb_strlen($bodyValue) > 50000) {
            $errors[] = 'Template body exceeds 50,000 characters.';
        }

        $matches = [];
        preg_match_all('/\{\{\s*field:([a-zA-Z0-9._-]+)\s*}}/', $bodyValue, $matches);
        $placeholders = array_values(array_unique($matches[1] ?? []));
        foreach ($placeholders as $key) {
            if (preg_match('/(rate|amount|price)/i', $key)) {
                $errors[] = 'Placeholders cannot include rate/amount/price fields.';
                break;
            }
        }

        if (!$errors) {
            $fieldCatalog = [];
            foreach ($placeholders as $key) {
                $meta = $fieldsMap[$key] ?? ['label' => $key, 'type' => 'text'];
                $fieldCatalog[] = [
                    'key' => $key,
                    'label' => $meta['label'],
                    'type' => $meta['type'] ?? 'text',
                    'required' => false,
                ];
            }

            $template = [
                'id' => template_library_generate_id(),
                'scope' => 'contractor',
                'ownerYojId' => $yojId,
                'title' => $titleValue,
                'category' => $categoryValue,
                'description' => $descriptionValue,
                'templateType' => 'simple_html',
                'body' => $bodyValue,
                'fieldCatalog' => $fieldCatalog,
            ];

            template_library_save_contractor($yojId, $template);
            logEvent(DATA_PATH . '/logs/contractor_templates.log', [
                'event' => 'TEMPLATE_CREATE',
                'yojId' => $yojId,
                'templateId' => $template['id'],
                'title' => $template['title'],
            ]);
            set_flash('success', 'Template created successfully.');
            redirect('/contractor/template_edit.php?id=' . urlencode($template['id']) . '&scope=contractor');
        } else {
            logEvent(DATA_PATH . '/logs/contractor_templates.log', [
                'event' => 'TEMPLATE_CREATE_FAILED',
                'yojId' => $yojId,
                'errors' => $errors,
            ]);
            set_flash('error', 'Please fix the highlighted issues.');
        }
    }

    $title = get_app_config()['appName'] . ' | New Template';
    render_layout($title, function () use ($fieldGroups, $fieldsMap, $sampleMap, $errors, $titleValue, $categoryValue, $descriptionValue, $bodyValue) {
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Create Template</h2>
                    <p class="muted" style="margin:4px 0 0;">Build templates using guided placeholders. No rate/amount placeholders allowed.</p>
                </div>
                <a class="btn secondary" href="/contractor/templates.php">Back to Templates</a>
            </div>

            <?php if ($errors): ?>
                <div class="card" style="border-color:var(--danger);">
                    <ul style="margin:0;padding-left:18px;color:var(--danger);">
                        <?php foreach ($errors as $error): ?>
                            <li><?= sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" style="display:grid;gap:14px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div style="display:grid;gap:8px;">
                    <label for="title"><strong>Title</strong></label>
                    <input class="input" id="title" name="title" value="<?= sanitize($titleValue); ?>" required maxlength="120">
                </div>

                <div style="display:grid;gap:8px;">
                    <label for="category"><strong>Category</strong></label>
                    <select class="input" id="category" name="category">
                        <option value="general" <?= $categoryValue === 'general' ? 'selected' : ''; ?>>General</option>
                        <option value="tender" <?= $categoryValue === 'tender' ? 'selected' : ''; ?>>Tender</option>
                        <option value="workorder" <?= $categoryValue === 'workorder' ? 'selected' : ''; ?>>Workorder</option>
                    </select>
                </div>

                <div style="display:grid;gap:8px;">
                    <label for="description"><strong>Description</strong></label>
                    <textarea class="input" id="description" name="description" rows="2" maxlength="500"><?= sanitize($descriptionValue); ?></textarea>
                </div>

                <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));align-items:start;">
                    <div style="display:grid;gap:8px;">
                        <label for="body"><strong>Simple Document Editor</strong></label>
                        <textarea class="input" id="body" name="body" rows="14" maxlength="50000" style="font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, \"Liberation Mono\", \"Courier New\", monospace;"><?= sanitize($bodyValue); ?></textarea>
                        <div class="muted">Use placeholders like <code>{{field:firm.name}}</code>. Max 50,000 characters.</div>
                    </div>

                    <div style="border:1px solid var(--border);border-radius:12px;padding:12px;background:var(--surface-2);display:grid;gap:10px;">
                        <strong>Placeholder Guidance</strong>
                        <p class="muted" style="margin:0;">Click a field to insert it at your cursor.</p>
                        <?php foreach ($fieldGroups as $group): ?>
                            <div>
                                <div style="font-weight:600;margin-bottom:6px;"><?= sanitize($group['label']); ?></div>
                                <?php if (empty($group['fields'])): ?>
                                    <div class="muted" style="font-size:13px;">No saved fields yet.</div>
                                <?php else: ?>
                                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                        <?php foreach ($group['fields'] as $field): ?>
                                            <button type="button" class="tag insert-placeholder" data-key="<?= sanitize($field['key']); ?>"><?= sanitize($field['label']); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="display:grid;gap:8px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                        <strong>Preview</strong>
                        <button class="btn secondary" type="button" id="preview-btn">Refresh Preview</button>
                    </div>
                    <div id="preview-box" style="border:1px solid var(--border);border-radius:12px;padding:16px;background:#fff;color:#0f172a;min-height:160px;white-space:pre-wrap;"></div>
                </div>

                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button class="btn" type="submit">Save Template</button>
                    <a class="btn secondary" href="/contractor/templates.php">Cancel</a>
                </div>
            </form>
        </div>

        <script>
            const placeholderButtons = document.querySelectorAll('.insert-placeholder');
            const bodyInput = document.getElementById('body');
            const previewBtn = document.getElementById('preview-btn');
            const previewBox = document.getElementById('preview-box');
            const sampleMap = <?= json_encode($sampleMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

            function insertAtCursor(textArea, text) {
                const start = textArea.selectionStart ?? textArea.value.length;
                const end = textArea.selectionEnd ?? textArea.value.length;
                textArea.value = textArea.value.substring(0, start) + text + textArea.value.substring(end);
                textArea.selectionStart = textArea.selectionEnd = start + text.length;
                textArea.focus();
                updatePreview();
            }

            function updatePreview() {
                let content = bodyInput.value || '';
                content = content.replace(/\{\{\s*field:([a-zA-Z0-9._-]+)\s*}}/g, function (match, key) {
                    return sampleMap[key] ?? '________';
                });
                previewBox.textContent = content || 'Preview will appear here.';
            }

            placeholderButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const key = button.dataset.key;
                    insertAtCursor(bodyInput, `{{field:${key}}}`);
                });
            });

            bodyInput.addEventListener('input', updatePreview);
            previewBtn.addEventListener('click', updatePreview);
            updatePreview();
        </script>
        <?php
    });
});
