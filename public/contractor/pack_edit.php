<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_contractor_library_env($yojId);

    $packId = trim((string)($_GET['id'] ?? ''));
    if ($packId === '') {
        render_error_page('Pack not found.');
        return;
    }

    $scope = ($_GET['scope'] ?? 'contractor') === 'global' ? 'global' : 'contractor';
    $pack = pack_library_load_by_id($yojId, $packId, $scope);
    if (!$pack && $scope === 'contractor') {
        $pack = pack_library_load_by_id($yojId, $packId, 'global');
        $scope = $pack ? 'global' : 'contractor';
    }

    if (!$pack) {
        render_error_page('Pack not found.');
        return;
    }

    $readonly = ($scope === 'global');
    $templates = array_merge(template_library_load_global(), template_library_load_contractor($yojId));
    $templateOptions = [];
    foreach ($templates as $tpl) {
        $templateOptions[$tpl['id']] = ($tpl['title'] ?? 'Template') . ' (' . ($tpl['scope'] ?? 'global') . ')';
    }

    $errors = [];
    $titleValue = (string)($pack['title'] ?? '');
    $descriptionValue = (string)($pack['description'] ?? '');
    $itemsValue = is_array($pack['items'] ?? null) ? $pack['items'] : [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        if ($readonly) {
            set_flash('error', 'Global packs are read-only.');
            redirect('/contractor/packs_library.php?tab=global');
        }

        $titleValue = trim((string)($_POST['title'] ?? ''));
        $descriptionValue = trim((string)($_POST['description'] ?? ''));
        $itemsValue = pack_library_normalize_items($_POST['items'] ?? []);

        if ($titleValue === '' || mb_strlen($titleValue) < 3 || mb_strlen($titleValue) > 120) {
            $errors[] = 'Title must be between 3 and 120 characters.';
        }
        if (mb_strlen($descriptionValue) > 500) {
            $errors[] = 'Description must be under 500 characters.';
        }
        if (!$itemsValue) {
            $errors[] = 'Add at least one pack item.';
        }
        foreach ($itemsValue as $item) {
            if (($item['type'] ?? '') === 'template_ref' && !isset($templateOptions[$item['templateId'] ?? ''])) {
                $errors[] = 'One or more selected templates are no longer available.';
                break;
            }
        }

        if (!$errors) {
            $pack['title'] = $titleValue;
            $pack['description'] = $descriptionValue;
            $pack['items'] = $itemsValue;

            pack_library_save_contractor($yojId, $pack);
            logEvent(DATA_PATH . '/logs/contractor_packs.log', [
                'event' => 'PACK_UPDATE',
                'yojId' => $yojId,
                'packId' => $pack['id'],
                'title' => $pack['title'],
            ]);
            set_flash('success', 'Pack updated.');
            redirect('/contractor/pack_edit.php?id=' . urlencode($pack['id']) . '&scope=contractor');
        } else {
            logEvent(DATA_PATH . '/logs/contractor_packs.log', [
                'event' => 'PACK_UPDATE_FAILED',
                'yojId' => $yojId,
                'packId' => $pack['id'],
                'errors' => $errors,
            ]);
            set_flash('error', 'Please fix the highlighted issues.');
        }
    }

    $title = get_app_config()['appName'] . ' | Pack';
    render_layout($title, function () use ($readonly, $pack, $templateOptions, $errors, $titleValue, $descriptionValue, $itemsValue) {
        $templateOptionsHtml = '';
        foreach ($templateOptions as $id => $label) {
            $templateOptionsHtml .= '<option value="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize($pack['title'] ?? 'Pack'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;"><?= $readonly ? 'YOJAK global pack (read-only).' : 'Edit your pack below.'; ?></p>
                </div>
                <a class="btn secondary" href="/contractor/packs_library.php">Back to Packs Library</a>
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

            <form method="post" id="pack-form" style="display:grid;gap:14px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div style="display:grid;gap:8px;">
                    <label for="title"><strong>Pack Title</strong></label>
                    <input class="input" id="title" name="title" value="<?= sanitize($titleValue); ?>" <?= $readonly ? 'readonly' : ''; ?> maxlength="120">
                </div>
                <div style="display:grid;gap:8px;">
                    <label for="description"><strong>Description</strong></label>
                    <textarea class="input" id="description" name="description" rows="2" maxlength="500" <?= $readonly ? 'readonly' : ''; ?>><?= sanitize($descriptionValue); ?></textarea>
                </div>

                <div style="display:grid;gap:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                        <strong>Pack Items</strong>
                        <?php if (!$readonly): ?>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <button class="btn secondary" type="button" data-add="checklist_item">Add Checklist Item</button>
                                <button class="btn secondary" type="button" data-add="vault_doc_tag">Add Vault Doc Tag</button>
                                <button class="btn secondary" type="button" data-add="template_ref">Add Template</button>
                                <button class="btn secondary" type="button" data-add="upload_slot">Add Upload Slot</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div id="items-list" style="display:grid;gap:10px;"></div>
                </div>

                <div style="display:grid;gap:8px;">
                    <strong>What this pack creates</strong>
                    <div id="pack-summary" class="muted">No items yet.</div>
                </div>

                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <?php if (!$readonly): ?>
                        <button class="btn" type="submit">Save Changes</button>
                    <?php endif; ?>
                    <a class="btn secondary" href="/contractor/packs_library.php">Back</a>
                </div>
            </form>
        </div>

        <template id="item-template-checklist">
            <div class="card pack-item" style="display:grid;gap:8px;">
                <input type="hidden" name="items[][type]" value="checklist_item">
                <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
                    <strong>Checklist Item</strong>
                    <div style="display:flex;gap:6px;">
                        <button type="button" class="btn secondary item-up">↑</button>
                        <button type="button" class="btn secondary item-down">↓</button>
                        <button type="button" class="btn secondary item-remove">Remove</button>
                    </div>
                </div>
                <input class="input" name="items[][title]" placeholder="Checklist title" required>
                <label><input type="checkbox" name="items[][required]" value="1"> Required</label>
            </div>
        </template>

        <template id="item-template-vault">
            <div class="card pack-item" style="display:grid;gap:8px;">
                <input type="hidden" name="items[][type]" value="vault_doc_tag">
                <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
                    <strong>Vault Document Tag</strong>
                    <div style="display:flex;gap:6px;">
                        <button type="button" class="btn secondary item-up">↑</button>
                        <button type="button" class="btn secondary item-down">↓</button>
                        <button type="button" class="btn secondary item-remove">Remove</button>
                    </div>
                </div>
                <input class="input" name="items[][tag]" placeholder="Tag (e.g., GST, PAN)" required>
                <label><input type="checkbox" name="items[][required]" value="1"> Required</label>
            </div>
        </template>

        <template id="item-template-template">
            <div class="card pack-item" style="display:grid;gap:8px;">
                <input type="hidden" name="items[][type]" value="template_ref">
                <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
                    <strong>Template</strong>
                    <div style="display:flex;gap:6px;">
                        <button type="button" class="btn secondary item-up">↑</button>
                        <button type="button" class="btn secondary item-down">↓</button>
                        <button type="button" class="btn secondary item-remove">Remove</button>
                    </div>
                </div>
                <select class="input" name="items[][templateId]" required>
                    <option value="">Select a template</option>
                    <?= $templateOptionsHtml; ?>
                </select>
                <label><input type="checkbox" name="items[][required]" value="1"> Required</label>
            </div>
        </template>

        <template id="item-template-upload">
            <div class="card pack-item" style="display:grid;gap:8px;">
                <input type="hidden" name="items[][type]" value="upload_slot">
                <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
                    <strong>Upload Slot</strong>
                    <div style="display:flex;gap:6px;">
                        <button type="button" class="btn secondary item-up">↑</button>
                        <button type="button" class="btn secondary item-down">↓</button>
                        <button type="button" class="btn secondary item-remove">Remove</button>
                    </div>
                </div>
                <input class="input" name="items[][title]" placeholder="Upload title" required>
                <label><input type="checkbox" name="items[][required]" value="1"> Required</label>
            </div>
        </template>

        <script>
            const itemsList = document.getElementById('items-list');
            const summary = document.getElementById('pack-summary');
            const isReadonly = <?= $readonly ? 'true' : 'false'; ?>;

            function updateSummary() {
                const items = itemsList.querySelectorAll('.pack-item');
                const counts = { checklist_item: 0, vault_doc_tag: 0, template_ref: 0, upload_slot: 0 };
                items.forEach(item => {
                    const typeInput = item.querySelector('input[name="items[][type]"]');
                    if (typeInput && counts.hasOwnProperty(typeInput.value)) {
                        counts[typeInput.value]++;
                    }
                });
                const total = items.length;
                summary.textContent = total === 0
                    ? 'No items yet.'
                    : `${counts.checklist_item} checklist • ${counts.vault_doc_tag} vault tags • ${counts.template_ref} templates • ${counts.upload_slot} uploads`;
            }

            function attachItemHandlers(item) {
                if (!isReadonly) {
                    item.querySelector('.item-remove')?.addEventListener('click', () => {
                        item.remove();
                        updateSummary();
                    });
                    item.querySelector('.item-up')?.addEventListener('click', () => {
                        const prev = item.previousElementSibling;
                        if (prev) {
                            itemsList.insertBefore(item, prev);
                            updateSummary();
                        }
                    });
                    item.querySelector('.item-down')?.addEventListener('click', () => {
                        const next = item.nextElementSibling;
                        if (next) {
                            itemsList.insertBefore(next, item);
                            updateSummary();
                        }
                    });
                } else {
                    item.querySelectorAll('input, select, button').forEach(element => {
                        if (element.tagName === 'BUTTON') {
                            element.style.display = 'none';
                        } else {
                            element.setAttribute('disabled', 'disabled');
                        }
                    });
                }
                item.querySelectorAll('input, select').forEach(input => {
                    input.addEventListener('change', updateSummary);
                });
            }

            function addItem(type, values = {}) {
                const template = document.getElementById(`item-template-${type}`);
                if (!template) return;
                const clone = template.content.firstElementChild.cloneNode(true);
                if (values.title) {
                    const input = clone.querySelector('input[name="items[][title]"]');
                    if (input) input.value = values.title;
                }
                if (values.tag) {
                    const input = clone.querySelector('input[name="items[][tag]"]');
                    if (input) input.value = values.tag;
                }
                if (values.templateId) {
                    const select = clone.querySelector('select[name="items[][templateId]"]');
                    if (select) select.value = values.templateId;
                }
                if (values.required) {
                    const checkbox = clone.querySelector('input[name="items[][required]"]');
                    if (checkbox) checkbox.checked = true;
                }
                itemsList.appendChild(clone);
                attachItemHandlers(clone);
                updateSummary();
            }

            if (!isReadonly) {
                document.querySelectorAll('[data-add]').forEach(button => {
                    button.addEventListener('click', () => addItem(button.dataset.add));
                });
            }

            const initialItems = <?= json_encode($itemsValue, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            if (initialItems.length) {
                initialItems.forEach(item => addItem(item.type, item));
            }
            updateSummary();
        </script>
        <?php
    });
});
