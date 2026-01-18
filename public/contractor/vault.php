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
        <div class="card" id="vault-upload">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize('Digital Vault'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Upload and tag documents securely.'); ?></p>
                </div>
                <span class="pill"><?= sanitize('Max 15MB'); ?></span>
            </div>
            <form id="vault-upload-form" method="post" action="/contractor/vault_upload.php" enctype="multipart/form-data" style="margin-top:12px; display:grid; gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="vault-document"><?= sanitize('Choose file'); ?></label>
                    <input id="vault-document" name="document" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                </div>
                <div class="field">
                    <label for="vault-tags"><?= sanitize('Tags (optional, comma separated)'); ?></label>
                    <input id="vault-tags" name="tags" placeholder="GST, registration, FY2023">
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button class="btn" id="vault-upload-button" type="submit"><?= sanitize('Upload'); ?></button>
                    <a class="btn secondary" href="/contractor/vault.php"><?= sanitize('Reset'); ?></a>
                </div>
            </form>
            <div id="vault-upload-progress" style="margin-top:12px; display:none;">
                <div class="muted" id="vault-upload-status"><?= sanitize('Preparing upload...'); ?></div>
                <div style="height:8px; background:var(--surface-2); border-radius:999px; overflow:hidden; margin-top:6px;">
                    <div id="vault-upload-bar" style="height:8px; width:0%; background:linear-gradient(90deg, #1f6feb, #2ea043);"></div>
                </div>
            </div>
            <div id="vault-upload-success" class="flash success" style="display:none; margin-top:12px;"></div>
            <div id="vault-upload-error" class="flash error" style="display:none; margin-top:12px;">
                <div id="vault-upload-error-text"></div>
                <button class="btn secondary" id="vault-upload-retry" type="button" style="margin-top:8px;"><?= sanitize('Retry'); ?></button>
            </div>
            <form method="get" action="/contractor/vault.php" style="margin-top:16px;">
                <input type="text" name="q" placeholder="<?= sanitize('Search title or tags...'); ?>" value="<?= sanitize($query); ?>" style="width:100%; margin-bottom:10px;">
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <?php foreach ($docTypes as $type): ?>
                        <?php $value = $type === 'All' ? '' : $type; ?>
                        <button name="docType" value="<?= sanitize($value); ?>" class="pill" style="cursor:pointer; border-color: <?= $docTypeFilter === $value ? 'var(--primary)' : 'var(--border)'; ?>; color: <?= $docTypeFilter === $value ? '#fff' : 'var(--muted)'; ?>; background: <?= $docTypeFilter === $value ? 'var(--primary)' : 'var(--surface-2)'; ?>;">
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
                <div class="card" id="vault-empty">
                    <p class="muted"><?= sanitize('No files match your search.'); ?></p>
                </div>
            <?php endif; ?>
            <div id="vault-list" data-csrf="<?= sanitize(csrf_token()); ?>" style="display:grid; gap:12px;">
            <?php foreach ($filtered as $file): ?>
                <div class="card">
                    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                        <div>
                            <h3 style="margin:0;"><?= sanitize($file['originalName'] ?? ($file['title'] ?? 'Untitled')); ?></h3>
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
                                <a class="btn secondary" href="/contractor/vault_download.php?fileId=<?= sanitize(urlencode($file['fileId'] ?? '')); ?>" target="_blank" rel="noopener">Open</a>
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
        </div>
        <script>
            (function () {
                const form = document.getElementById('vault-upload-form');
                if (!form) {
                    return;
                }
                const button = document.getElementById('vault-upload-button');
                const progressWrap = document.getElementById('vault-upload-progress');
                const progressBar = document.getElementById('vault-upload-bar');
                const statusText = document.getElementById('vault-upload-status');
                const successPanel = document.getElementById('vault-upload-success');
                const errorPanel = document.getElementById('vault-upload-error');
                const errorText = document.getElementById('vault-upload-error-text');
                const retryButton = document.getElementById('vault-upload-retry');
                const list = document.getElementById('vault-list');
                const emptyState = document.getElementById('vault-empty');
                const csrfToken = list ? list.dataset.csrf : '';

                const escapeHtml = (value) => {
                    const div = document.createElement('div');
                    div.textContent = value ?? '';
                    return div.innerHTML;
                };

                const resetPanels = () => {
                    if (successPanel) successPanel.style.display = 'none';
                    if (errorPanel) errorPanel.style.display = 'none';
                    if (progressWrap) progressWrap.style.display = 'none';
                    if (progressBar) progressBar.style.width = '0%';
                };

                const setStatus = (text) => {
                    if (statusText) {
                        statusText.textContent = text;
                    }
                };

                const addVaultCard = (item, downloadUrl) => {
                    if (!list || !item) {
                        return;
                    }
                    if (emptyState) {
                        emptyState.remove();
                    }
                    const tags = Array.isArray(item.tags) && item.tags.length
                        ? item.tags.map((tag) => `<span class="tag">${escapeHtml(tag)}</span>`).join('')
                        : `<span class="tag">No tags</span>`;
                    const sizeBytes = item.sizeBytes ?? item.size ?? 0;
                    const sizeLabel = `${(sizeBytes / 1048576) >= 1 ? (sizeBytes / 1048576).toFixed(2) + ' MB' : (sizeBytes / 1024) >= 1 ? (sizeBytes / 1024).toFixed(2) + ' KB' : sizeBytes + ' B'}`;
                    const card = document.createElement('div');
                    card.className = 'card';
                    card.innerHTML = `
                        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                            <div>
                                <h3 style="margin:0;">${escapeHtml(item.originalName ?? item.title ?? 'Untitled')}</h3>
                                <p class="muted" style="margin:4px 0 0;">${escapeHtml(item.fileId ?? '')} • ${escapeHtml(item.docType ?? 'Other')} • ${escapeHtml(sizeLabel)}</p>
                                <div style="margin-top:6px; display:flex; gap:6px; flex-wrap:wrap;">
                                    ${tags}
                                    <span class="pill" style="background:var(--surface-2);color:var(--text);border:1px solid var(--border);">Uploaded</span>
                                </div>
                            </div>
                            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                <a class="btn secondary" href="${escapeHtml(downloadUrl ?? '#')}" target="_blank" rel="noopener">Open</a>
                            </div>
                        </div>
                        <div style="margin-top:10px; display:grid; gap:8px;">
                            <form method="post" action="/contractor/vault_update.php" style="display:grid; gap:8px;">
                                <input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}">
                                <input type="hidden" name="fileId" value="${escapeHtml(item.fileId ?? '')}">
                                <div class="field">
                                    <label>Title</label>
                                    <input name="title" value="${escapeHtml(item.title ?? '')}" required>
                                </div>
                                <div class="field">
                                    <label>Document type</label>
                                    <select name="docType" required>
                                        ${['GST','PAN','ITR','BalanceSheet','Affidavit','Undertaking','ExperienceCert','Other'].map((type) => `<option value="${escapeHtml(type)}"${type === (item.docType ?? 'Other') ? ' selected' : ''}>${escapeHtml(type)}</option>`).join('')}
                                    </select>
                                </div>
                                <div class="field">
                                    <label>Tags (comma separated)</label>
                                    <input name="tags" value="${escapeHtml((item.tags ?? []).join(', '))}" placeholder="e.g. gst, fy2023">
                                </div>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <button class="btn" type="submit">Save</button>
                                    <a class="btn danger" href="#" onclick="event.preventDefault(); document.getElementById('del-${escapeHtml(item.fileId ?? '')}').submit();">Delete</a>
                                </div>
                            </form>
                            <form id="del-${escapeHtml(item.fileId ?? '')}" method="post" action="/contractor/vault_delete.php" style="display:none;">
                                <input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}">
                                <input type="hidden" name="fileId" value="${escapeHtml(item.fileId ?? '')}">
                            </form>
                        </div>
                    `;
                    list.prepend(card);
                };

                if (retryButton) {
                    retryButton.addEventListener('click', () => {
                        resetPanels();
                        if (button) {
                            button.disabled = false;
                            button.textContent = 'Upload';
                        }
                    });
                }

                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    resetPanels();
                    const fileInput = document.getElementById('vault-document');
                    if (!fileInput || !fileInput.files || !fileInput.files.length) {
                        if (errorText) {
                            errorText.textContent = 'Please select a file to upload.';
                        }
                        if (errorPanel) {
                            errorPanel.style.display = 'block';
                        }
                        return;
                    }
                    const formData = new FormData(form);
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', form.action, true);
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.setRequestHeader('Accept', 'application/json');
                    if (button) {
                        button.disabled = true;
                        button.textContent = 'Uploading...';
                    }
                    if (progressWrap) {
                        progressWrap.style.display = 'block';
                    }
                    setStatus('Uploading...');
                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable && progressBar) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            progressBar.style.width = `${percent}%`;
                            setStatus(`Uploading... ${percent}%`);
                        }
                    };
                    xhr.onload = () => {
                        let response = null;
                        try {
                            response = JSON.parse(xhr.responseText);
                        } catch (e) {
                            response = null;
                        }
                        if (xhr.status >= 200 && xhr.status < 300 && response && response.ok) {
                            if (successPanel) {
                                successPanel.textContent = response.message || 'Upload complete.';
                                successPanel.style.display = 'block';
                            }
                            addVaultCard(response.item, response.downloadUrl);
                            form.reset();
                        } else {
                            if (errorText) {
                                const msg = response && response.errors ? response.errors.join(' ') : 'Upload failed. Please retry.';
                                errorText.textContent = msg;
                            }
                            if (errorPanel) {
                                errorPanel.style.display = 'block';
                            }
                        }
                        if (button) {
                            button.disabled = false;
                            button.textContent = 'Upload';
                        }
                        setStatus('Upload finished.');
                    };
                    xhr.onerror = () => {
                        if (errorText) {
                            errorText.textContent = 'Upload failed due to a network error. Please retry.';
                        }
                        if (errorPanel) {
                            errorPanel.style.display = 'block';
                        }
                        if (button) {
                            button.disabled = false;
                            button.textContent = 'Upload';
                        }
                        setStatus('Upload failed.');
                    };
                    xhr.send(formData);
                });
            })();
        </script>
        <?php
    });
});
