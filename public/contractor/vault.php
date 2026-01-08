<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $contractor = load_contractor($user['yojId']);
    if (!$contractor) {
        render_error_page('Contractor not found.');
        return;
    }

    $query = trim($_GET['q'] ?? '');
    $docTypeFilter = trim($_GET['docType'] ?? '');
    $showDeleted = isset($_GET['showDeleted']) && $_GET['showDeleted'] === '1';

    $files = contractor_vault_index($contractor['yojId']);
    $filtered = array_filter($files, function ($file) use ($query, $docTypeFilter, $showDeleted) {
        if (!$showDeleted && !empty($file['deletedAt'])) {
            return false;
        }
        if ($docTypeFilter !== '' && ($file['docType'] ?? ($file['category'] ?? '')) !== $docTypeFilter) {
            return false;
        }
        if ($query === '') {
            return true;
        }
        $haystack = strtolower(($file['title'] ?? '') . ' ' . implode(' ', $file['tags'] ?? []));
        return str_contains($haystack, strtolower($query));
    });

    $title = get_app_config()['appName'] . ' | Vault';

    render_layout($title, function () use ($filtered, $query, $docTypeFilter, $showDeleted) {
        $docTypes = ['All', 'GST', 'PAN', 'ITR', 'BalanceSheet', 'Affidavit', 'Undertaking', 'ExperienceCert', 'Other'];
        ?>
        <div class="card">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize('Digital Vault'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;">Save, tag, and manage your documents.</p>
                </div>
                <a class="btn" href="/contractor/vault_upload.php"><?= sanitize('Upload'); ?></a>
            </div>
            <form method="get" action="/contractor/vault.php" style="margin-top:12px;">
                <input type="text" name="q" placeholder="<?= sanitize('Search title or tags...'); ?>" value="<?= sanitize($query); ?>" style="width:100%; margin-bottom:10px;">
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <?php foreach ($docTypes as $type): ?>
                        <?php $value = $type === 'All' ? '' : $type; ?>
                        <button name="docType" value="<?= sanitize($value); ?>" class="pill" style="cursor:pointer; border-color: <?= $docTypeFilter === $value ? 'var(--primary)' : '#30363d'; ?>; color: <?= $docTypeFilter === $value ? '#fff' : 'var(--muted)'; ?>; background: <?= $docTypeFilter === $value ? 'var(--primary)' : '#111820'; ?>;">
                            <?= sanitize($type); ?>
                        </button>
                    <?php endforeach; ?>
                    <label class="pill" style="display:inline-flex; align-items:center; gap:6px;">
                        <input type="checkbox" name="showDeleted" value="1" <?= $showDeleted ? 'checked' : ''; ?>> <?= sanitize('Show deleted'); ?>
                    </label>
                </div>
            </form>
        </div>
        <div style="display:grid; gap:12px; margin-top:12px;">
            <?php if (!$filtered): ?>
                <div class="card">
                    <p class="muted"><?= sanitize('No files match your search.'); ?></p>
                </div>
            <?php endif; ?>
            <?php foreach ($filtered as $file): ?>
                <div class="card">
                    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                        <div>
                            <h3 style="margin:0;"><?= sanitize($file['title'] ?? 'Untitled'); ?></h3>
                            <p class="muted" style="margin:4px 0 0;"><?= sanitize($file['docId'] ?? ($file['fileId'] ?? '')); ?> • <?= sanitize(($file['docType'] ?? ($file['category'] ?? 'Other'))); ?> • <?= sanitize(format_bytes((int)($file['sizeBytes'] ?? 0))); ?></p>
                            <div style="margin-top:6px; display:flex; gap:6px; flex-wrap:wrap;">
                                <?php foreach (($file['tags'] ?? []) as $tag): ?>
                                    <span class="tag"><?= sanitize($tag); ?></span>
                                <?php endforeach; ?>
                                <?php if (!($file['tags'] ?? [])): ?>
                                    <span class="tag"><?= sanitize('No tags'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <?php if (empty($file['deletedAt'])): ?>
                                <a class="btn secondary" href="<?= sanitize($file['storedPath'] ?? '#'); ?>" target="_blank" rel="noopener">Open</a>
                            <?php else: ?>
                                <span class="pill" style="border-color: var(--danger); color: #f77676;"><?= sanitize('Deleted'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="margin-top:10px; display:grid; gap:8px;">
                        <?php if (empty($file['deletedAt'])): ?>
                            <form method="post" action="/contractor/vault_update.php" style="display:grid; gap:8px;">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="fileId" value="<?= sanitize($file['fileId']); ?>">
                                <div class="field">
                                    <label><?= sanitize('Title'); ?></label>
                                    <input name="title" value="<?= sanitize($file['title'] ?? ''); ?>" required>
                                </div>
                                <div class="field">
                                    <label><?= sanitize('Document type'); ?></label>
                                    <select name="docType" required>
                                        <?php foreach (['GST','PAN','ITR','BalanceSheet','Affidavit','Undertaking','ExperienceCert','Other'] as $type): ?>
                                            <option value="<?= sanitize($type); ?>" <?= (($file['docType'] ?? ($file['category'] ?? '')) === $type) ? 'selected' : ''; ?>><?= sanitize($type); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label><?= sanitize('Tags (comma separated)'); ?></label>
                                    <input name="tags" value="<?= sanitize(implode(', ', $file['tags'] ?? [])); ?>" placeholder="e.g. gst, fy2023">
                                </div>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <button class="btn" type="submit"><?= sanitize('Save'); ?></button>
                                    <a class="btn danger" href="#" onclick="event.preventDefault(); document.getElementById('del-<?= sanitize($file['fileId']); ?>').submit();"><?= sanitize('Delete'); ?></a>
                                </div>
                            </form>
                            <form id="del-<?= sanitize($file['fileId']); ?>" method="post" action="/contractor/vault_delete.php" style="display:none;">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="fileId" value="<?= sanitize($file['fileId']); ?>">
                            </form>
                        <?php else: ?>
                            <p class="muted"><?= sanitize('Deleted on ' . ($file['deletedAt'] ?? '')); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    });
});
