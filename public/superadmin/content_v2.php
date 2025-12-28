<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $title = get_app_config()['appName'] . ' | Content Studio v2';
    $blogTopics = topic_v2_list('blog');
    $newsTopics = topic_v2_list('news');
    $csrf = csrf_token();
    $aiStatus = ai_get_config();
    $aiConfig = $aiStatus['config'] ?? ai_config_defaults();
    $aiConfigured = $aiStatus['ok'] ?? false;

    render_layout($title, function () use ($blogTopics, $newsTopics, $csrf, $aiConfig, $aiConfigured) {
        ?>
        <style>
            .tab-bar { display:flex; gap:10px; margin:16px 0; flex-wrap:wrap; }
            .tab-btn { padding:10px 14px; border-radius:10px; border:1px solid #30363d; background:#0f1520; color:#e6edf3; cursor:pointer; font-weight:700; }
            .tab-btn.active { background: var(--primary); border-color: var(--primary); color:#fff; box-shadow:0 8px 24px rgba(31,111,235,0.35); }
            .grid-2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:14px; }
            .result-card { border:1px solid #2b3440; border-radius:12px; padding:12px; background:#0f1520; display:grid; gap:6px; }
            .result-card:hover { border-color: var(--primary); }
            .chip { display:inline-block; padding:4px 8px; border-radius:999px; background:#111820; border:1px solid #2b3440; color:#9fb2c8; font-size:12px; margin-right:6px; margin-top:4px; }
            .pill.success { background:#12291a; border-color:#1f6f3a; color:#8ce99a; }
            .pill.danger { background:#2a1414; border-color:#5f1f1f; color:#f77676; }
            .muted-compact { font-size:12px; color: var(--muted); }
            .flex-between { display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; }
            .table-actions { display:flex; gap:8px; flex-wrap:wrap; }
            .status-dot { width:10px; height:10px; border-radius:50%; display:inline-block; margin-right:6px; background:#2ea043; }
            .tab-panel { display:none; }
            .tab-panel.active { display:block; }
            .message { margin-top:8px; padding:10px 12px; border-radius:10px; border:1px solid #30363d; background:#0f1520; font-weight:600; display:none; }
            .message.show { display:block; }
            .message.error { border-color: var(--danger); color:#f77676; }
            .message.success { border-color: var(--success); color:#8ce99a; }
            .keywords-input { width:100%; border-radius:10px; border:1px solid #30363d; background:#0d1117; color:#e6edf3; padding:10px 12px; }
            .diag { display:none; margin-top:6px; padding:10px 12px; border-radius:10px; border:1px dashed #2b3440; background:#0b1018; color:#9fb2c8; }
            .diag strong { color:#e6edf3; }
            .diag .actions { display:flex; gap:8px; align-items:center; margin-top:6px; flex-wrap:wrap; }
            .btn.compact { padding:6px 10px; font-size:12px; }
            @media (max-width: 640px) {
                .grid-2 { grid-template-columns:1fr; }
            }
        </style>

        <div class="card">
            <div class="flex-between">
                <div>
                    <h2 style="margin:0;">AI Status</h2>
                    <p class="muted" style="margin:4px 0 0;">Shared AI client (no prefetched fallbacks). </p>
                </div>
                <?php if ($aiConfigured): ?>
                    <span class="pill success">AI: Configured ✅ Provider: <?= sanitize($aiConfig['provider'] ?? ''); ?> • Model: <?= sanitize($aiConfig['textModel'] ?? ''); ?></span>
                <?php else: ?>
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        <span class="pill danger">AI: Config missing ❌</span>
                        <a class="btn secondary" href="/superadmin/ai_studio.php">Go to AI Studio</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="flex-between">
                <div>
                    <h2 style="margin:0;">Content Studio v2: Topic Builder</h2>
                    <p class="muted" style="margin:4px 0 0;">Fresh, AI-traceable topics for Blogs and News. No legacy fallbacks.</p>
                </div>
                <div class="pill">Superadmin • Asia/Kolkata</div>
            </div>
        </div>

        <div class="tab-bar">
            <button class="tab-btn active" data-tab="blog">Blog Topics</button>
            <button class="tab-btn" data-tab="news">News Topics</button>
        </div>

        <?php foreach (['blog' => $blogTopics, 'news' => $newsTopics] as $type => $savedList): ?>
            <div class="tab-panel <?= $type === 'blog' ? 'active' : ''; ?>" data-panel="<?= $type; ?>">
                <div class="grid-2">
                    <div class="card">
                        <div class="flex-between">
                            <div>
                                <h3 style="margin:0;">Generate <?= $type === 'blog' ? 'Blog' : 'News'; ?> Topics</h3>
                                <p class="muted" style="margin:4px 0 0;">Each click creates a new jobId + nonce, logged with prompt hash.</p>
                            </div>
                            <span class="pill">AI only</span>
                        </div>
                        <form class="gen-form" data-type="<?= $type; ?>" style="margin-top:12px; display:grid; gap:10px;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf); ?>">
                            <input type="hidden" name="type" value="<?= $type; ?>">
                            <input type="hidden" name="count" value="5">
                            <div class="field">
                                <label for="<?= $type; ?>-prompt">Prompt (optional)</label>
                                <textarea name="prompt" id="<?= $type; ?>-prompt" rows="3" style="width:100%; border-radius:12px; border:1px solid #30363d; background:#0d1117; color:#e6edf3; padding:10px;" placeholder="Describe the theme, audience, or angle. Leave blank for platform-safe ideas."></textarea>
                            </div>
                            <?php if ($type === 'news'): ?>
                                <div class="field">
                                    <label for="<?= $type; ?>-length">News Length</label>
                                    <select name="newsLength" id="<?= $type; ?>-length">
                                        <option value="short">Short</option>
                                        <option value="standard" selected>Standard</option>
                                        <option value="long">Long</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <button class="btn" type="submit">Generate 5 Topics</button>
                            <p class="muted-compact">AI config guard enforced. Results logged in /data/logs/content_v2.log</p>
                        </form>
                        <div class="message" id="<?= $type; ?>-gen-message"></div>
                        <div class="diag" id="<?= $type; ?>-gen-diag"></div>
                    </div>
                    <div class="card" style="display:grid; gap:10px;">
                        <div class="flex-between">
                            <div>
                                <h3 style="margin:0;">Results</h3>
                                <p class="muted" style="margin:4px 0 0;">Pick a topic, tweak, then save.</p>
                            </div>
                            <span class="pill" id="<?= $type; ?>-job-pill">No job yet</span>
                        </div>
                        <div id="<?= $type; ?>-results" style="display:grid; gap:10px;">
                            <p class="muted" style="margin:0;">No topics yet. Generate to see AI options.</p>
                        </div>
                    </div>
                </div>

                <div class="grid-2" style="margin-top:14px;">
                    <div class="card">
                        <div class="flex-between">
                            <h3 style="margin:0;">Edit before saving</h3>
                            <span class="pill secondary" id="<?= $type; ?>-source-pill">Awaiting selection</span>
                        </div>
                        <form class="save-form" data-type="<?= $type; ?>" style="display:grid; gap:10px; margin-top:10px;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf); ?>">
                            <input type="hidden" name="type" value="<?= $type; ?>">
                            <input type="hidden" name="source" value="ai">
                            <input type="hidden" name="jobId" value="">
                            <input type="hidden" name="provider" value="">
                            <input type="hidden" name="model" value="">
                            <input type="hidden" name="modelUsed" value="">
                            <input type="hidden" name="requestId" value="">
                            <input type="hidden" name="httpStatus" value="">
                            <input type="hidden" name="aiOk" value="">
                            <input type="hidden" name="aiError" value="">
                            <input type="hidden" name="promptHash" value="">
                            <input type="hidden" name="nonce" value="">
                            <input type="hidden" name="generatedAt" value="">
                            <input type="hidden" name="rawTextSnippet" value="">
                            <div class="field">
                                <label>Topic title</label>
                                <input type="text" name="topicTitle" placeholder="Select or type a topic" required>
                            </div>
                            <div class="field">
                                <label>Topic angle (optional)</label>
                                <input type="text" name="topicAngle" placeholder="E.g., compliance-first approach">
                            </div>
                            <div class="field">
                                <label>Audience</label>
                                <input type="text" name="audience" value="Jharkhand government contractors">
                            </div>
                            <div class="field">
                                <label>Keywords (comma separated)</label>
                                <input type="text" name="keywords" class="keywords-input" placeholder="compliance, workflow, automation">
                            </div>
                            <?php if ($type === 'news'): ?>
                                <div class="field">
                                    <label>News length</label>
                                    <select name="newsLength">
                                        <option value="short">Short</option>
                                        <option value="standard" selected>Standard</option>
                                        <option value="long">Long</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="buttons" style="margin-top:4px;">
                                <button class="btn" type="submit">Save Selected Topic</button>
                                <button class="btn secondary manual-save" type="button">Save as Manual Topic</button>
                            </div>
                        </form>
                        <div class="message" id="<?= $type; ?>-save-message"></div>
                        <div class="diag" id="<?= $type; ?>-save-diag"></div>
                    </div>

                    <div class="card">
                        <div class="flex-between">
                            <h3 style="margin:0;">Saved Topics</h3>
                            <span class="pill">Drafts</span>
                        </div>
                        <div class="message" id="<?= $type; ?>-draft-message"></div>
                        <div class="diag" id="<?= $type; ?>-draft-diag"></div>
                        <div class="table-responsive" style="margin-top:10px; overflow-x:auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="text-align:left;">Title</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="<?= $type; ?>-saved-body">
                                    <?php if (!$savedList): ?>
                                        <tr><td colspan="4" class="muted">No topics yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($savedList as $row): ?>
                                            <tr data-topic-id="<?= sanitize($row['topicId']); ?>" data-news-length="<?= sanitize($row['newsLength'] ?? ''); ?>">
                                                <td><?= sanitize($row['topicTitle'] ?? ''); ?></td>
                                                <td><?= sanitize($row['status'] ?? 'draft'); ?></td>
                                                <td><?= sanitize($row['createdAt'] ?? ''); ?></td>
                                                <td class="table-actions">
                                                    <a class="btn secondary" style="padding:6px 10px;" href="/superadmin/topic_view.php?type=<?= $type; ?>&topicId=<?= urlencode($row['topicId']); ?>">View</a>
                                                    <button class="btn" data-generate="blog" data-source-type="<?= $type; ?>" data-topic-id="<?= sanitize($row['topicId']); ?>" type="button" style="padding:6px 10px;">Generate Blog Draft</button>
                                                    <button class="btn secondary" data-generate="news" data-source-type="<?= $type; ?>" data-topic-id="<?= sanitize($row['topicId']); ?>" type="button" style="padding:6px 10px;">Generate News Draft</button>
                                                    <button class="btn danger delete-btn" data-type="<?= $type; ?>" data-topic-id="<?= sanitize($row['topicId']); ?>" type="button" style="padding:6px 10px;">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <script>
            const state = {
                blog: { results: [], aiMeta: null, jobId: '', newsLength: null },
                news: { results: [], aiMeta: null, jobId: '', newsLength: 'standard' }
            };

            const savedData = {
                blog: <?= json_encode($blogTopics); ?>,
                news: <?= json_encode($newsTopics); ?>
            };
            const csrfToken = '<?= sanitize($csrf); ?>';

            function setMessage(id, text, isError = false) {
                const el = document.getElementById(id);
                if (!el) return;
                if (text) {
                    el.textContent = text;
                    el.classList.add('show');
                    el.classList.toggle('error', isError);
                    el.classList.toggle('success', !isError);
                } else {
                    el.textContent = '';
                    el.classList.remove('show', 'error', 'success');
                }
            }

            function escapeHtml(value) {
                return (value || '').replace(/[&<>"']/g, m => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                }[m] || m));
            }

            function renderDiagnostics(targetId, meta, errorText = '') {
                const el = document.getElementById(targetId);
                if (!el) return;
                el.innerHTML = '';
                if (!meta) {
                    el.style.display = 'none';
                    return;
                }
                const provider = meta.provider || 'unknown';
                const model = meta.modelUsed || meta.model || 'unknown';
                const httpStatus = meta.httpStatus ?? 'n/a';
                const requestId = meta.requestId || '(not provided)';
                const responseId = meta.responseId || '';
                const finishReasons = Array.isArray(meta.finishReasons) && meta.finishReasons.length ? meta.finishReasons.join(', ') : '';
                const blockReason = meta.blockReason || meta.promptBlockReason || '';
                const textLength = meta.textLength ?? meta.textLen ?? (meta.rawTextSnippet ? meta.rawTextSnippet.length : '');
                const line = document.createElement('div');
                line.className = 'muted-compact';
                line.innerHTML = `<strong>Provider:</strong> ${escapeHtml(provider)} • <strong>Model:</strong> ${escapeHtml(model)} • <strong>HTTP:</strong> ${escapeHtml(String(httpStatus))} • <strong>Request:</strong> ${escapeHtml(requestId)}`;
                if (responseId) {
                    const resp = document.createElement('div');
                    resp.className = 'muted-compact';
                    resp.textContent = `Response ID: ${responseId}`;
                    line.appendChild(resp);
                }
                if (blockReason || finishReasons || textLength !== '') {
                    const diagLine = document.createElement('div');
                    diagLine.className = 'muted-compact';
                    diagLine.textContent = `${blockReason ? `Block: ${blockReason} • ` : ''}${finishReasons ? `Finish: ${finishReasons} • ` : ''}Text length: ${textLength || 0}`;
                    line.appendChild(diagLine);
                }
                const actions = document.createElement('div');
                actions.className = 'actions';
                const copyBtn = document.createElement('button');
                copyBtn.type = 'button';
                copyBtn.className = 'btn secondary compact';
                copyBtn.textContent = 'Copy debug';
                const debugText = `provider=${provider}\nmodelUsed=${model}\nhttpStatus=${httpStatus}\nrequestId=${requestId}\nresponseId=${responseId || ''}\nfinishReasons=${finishReasons}\nblockReason=${blockReason}\ntextLength=${textLength || 0}`;
                copyBtn.addEventListener('click', () => {
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(debugText).then(() => {
                            copyBtn.textContent = 'Copied';
                            setTimeout(() => { copyBtn.textContent = 'Copy debug'; }, 1200);
                        }).catch(() => alert('Unable to copy debug payload.'));
                    } else {
                        alert('Clipboard unavailable.');
                    }
                });
                actions.appendChild(copyBtn);
                if (errorText || meta.error) {
                    const err = document.createElement('div');
                    err.className = 'muted-compact';
                    err.textContent = errorText || meta.error || '';
                    actions.appendChild(err);
                }
                el.appendChild(line);
                el.appendChild(actions);
                el.style.display = 'block';
            }

            function renderResults(type) {
                const container = document.getElementById(`${type}-results`);
                const jobPill = document.getElementById(`${type}-job-pill`);
                const sourcePill = document.getElementById(`${type}-source-pill`);
                container.innerHTML = '';
                const list = state[type].results;
                if (!list || list.length === 0) {
                    container.innerHTML = '<p class="muted" style="margin:0;">No topics yet.</p>';
                    jobPill.textContent = 'No job yet';
                    sourcePill.textContent = 'Awaiting selection';
                    renderDiagnostics(`${type}-gen-diag`, null);
                    renderDiagnostics(`${type}-save-diag`, null);
                    return;
                }
                jobPill.textContent = `Job ${state[type].jobId || 'pending'}`;
                sourcePill.textContent = 'AI topic selected';
                list.forEach((item, idx) => {
                    const safeTitle = escapeHtml(item.topicTitle);
                    const safeAngle = escapeHtml(item.topicAngle);
                    const card = document.createElement('label');
                    card.className = 'result-card';
                    card.innerHTML = `
                        <div class="flex-between">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <input type="radio" name="${type}-result" value="${idx}">
                                <div style="font-weight:700;">${safeTitle}</div>
                            </div>
                            ${item.topicAngle ? `<span class="pill secondary">${safeAngle}</span>` : ''}
                        </div>
                        ${item.topicAngle ? `<div class="muted-compact">${safeAngle}</div>` : ''}
                        <div>${(item.keywords || []).map(k => `<span class="chip">${escapeHtml(k)}</span>`).join('')}</div>
                    `;
                    card.querySelector('input').addEventListener('change', () => {
                        fillEditor(type, item);
                    });
                    container.appendChild(card);
                });
                renderDiagnostics(`${type}-gen-diag`, state[type].aiMeta || null, (state[type].aiMeta && state[type].aiMeta.ok === false) ? (state[type].aiMeta.error || '') : '');
            }

            function fillEditor(type, item) {
                const form = document.querySelector(`.save-form[data-type="${type}"]`);
                if (!form) return;
                form.topicTitle.value = item.topicTitle || '';
                form.topicAngle.value = item.topicAngle || '';
                form.keywords.value = (item.keywords || []).join(', ');
                form.source.value = 'ai';
                const sourcePill = document.getElementById(`${type}-source-pill`);
                sourcePill.textContent = 'AI topic selected';
                const meta = state[type].aiMeta || {};
                form.provider.value = meta.provider || '';
                const resolvedModel = meta.modelUsed || meta.model || '';
                form.model.value = resolvedModel;
                if (form.modelUsed) {
                    form.modelUsed.value = resolvedModel;
                }
                form.requestId.value = meta.requestId || '';
                form.httpStatus.value = meta.httpStatus || '';
                if (form.aiOk) {
                    form.aiOk.value = meta.ok === false ? '0' : '1';
                }
                if (form.aiError) {
                    form.aiError.value = meta.error || '';
                }
                form.promptHash.value = meta.promptHash || '';
                form.nonce.value = meta.nonce || '';
                form.generatedAt.value = meta.generatedAt || '';
                form.rawTextSnippet.value = meta.rawTextSnippet || '';
                form.jobId.value = state[type].jobId || '';
                if (form.newsLength) {
                    form.newsLength.value = state[type].newsLength || 'standard';
                }
                renderDiagnostics(`${type}-save-diag`, meta, meta.ok === false ? (meta.error || '') : '');
            }

            function renderSaved(type) {
                const tbody = document.getElementById(`${type}-saved-body`);
                if (!tbody) return;
                const rows = savedData[type] || [];
                if (rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="muted">No topics yet.</td></tr>';
                    return;
                }
                tbody.innerHTML = '';
                rows.forEach(row => {
                    const tr = document.createElement('tr');
                    tr.dataset.topicId = row.topicId;
                    tr.dataset.newsLength = row.newsLength || '';
                    const titleTd = document.createElement('td');
                    titleTd.textContent = row.topicTitle || '';
                    const statusTd = document.createElement('td');
                    statusTd.textContent = row.status || '';
                    const createdTd = document.createElement('td');
                    createdTd.textContent = row.createdAt || '';
                    const actionsTd = document.createElement('td');
                    actionsTd.className = 'table-actions';
                    const viewLink = document.createElement('a');
                    viewLink.className = 'btn secondary';
                    viewLink.style.padding = '6px 10px';
                    viewLink.href = `/superadmin/topic_view.php?type=${type}&topicId=${encodeURIComponent(row.topicId)}`;
                    viewLink.textContent = 'View';
                    const genBlogBtn = document.createElement('button');
                    genBlogBtn.className = 'btn';
                    genBlogBtn.dataset.generate = 'blog';
                    genBlogBtn.dataset.sourceType = type;
                    genBlogBtn.dataset.topicId = row.topicId;
                    genBlogBtn.style.padding = '6px 10px';
                    genBlogBtn.type = 'button';
                    genBlogBtn.textContent = 'Generate Blog Draft';
                    const genNewsBtn = document.createElement('button');
                    genNewsBtn.className = 'btn secondary';
                    genNewsBtn.dataset.generate = 'news';
                    genNewsBtn.dataset.sourceType = type;
                    genNewsBtn.dataset.topicId = row.topicId;
                    genNewsBtn.style.padding = '6px 10px';
                    genNewsBtn.type = 'button';
                    genNewsBtn.textContent = 'Generate News Draft';
                    const delBtn = document.createElement('button');
                    delBtn.className = 'btn danger delete-btn';
                    delBtn.dataset.type = type;
                    delBtn.dataset.topicId = row.topicId;
                    delBtn.type = 'button';
                    delBtn.style.padding = '6px 10px';
                    delBtn.textContent = 'Delete';
                    actionsTd.appendChild(viewLink);
                    actionsTd.appendChild(genBlogBtn);
                    actionsTd.appendChild(genNewsBtn);
                    actionsTd.appendChild(delBtn);
                    tr.appendChild(titleTd);
                    tr.appendChild(statusTd);
                    tr.appendChild(createdTd);
                    tr.appendChild(actionsTd);
                    tbody.appendChild(tr);
                });
            }

            function handleTabSwitch() {
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const tab = btn.dataset.tab;
                        document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b === btn));
                        document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.toggle('active', panel.dataset.panel === tab));
                    });
                });
            }

            function bindGenerationForms() {
                document.querySelectorAll('.gen-form').forEach(form => {
                    form.addEventListener('submit', (e) => {
                        e.preventDefault();
                        const type = form.dataset.type;
                        setMessage(`${type}-gen-message`, 'Generating topics...', false);
                        const formData = new FormData(form);
                        fetch('/superadmin/topic_generate.php', {
                            method: 'POST',
                            body: formData,
                            headers: {'Accept': 'application/json'}
                        }).then(resp => resp.json()).then(data => {
                            if (!data.ok) {
                                setMessage(`${type}-gen-message`, data.error || 'Generation failed.', true);
                                state[type].results = [];
                                state[type].aiMeta = data.aiMeta || null;
                                renderDiagnostics(`${type}-gen-diag`, data.aiMeta || null, data.error || '');
                                renderResults(type);
                                return;
                            }
                            state[type].results = data.results || [];
                            state[type].aiMeta = data.aiMeta || null;
                            state[type].jobId = data.jobId || '';
                            state[type].newsLength = data.newsLength || (type === 'news' ? 'standard' : null);
                            renderResults(type);
                            setMessage(`${type}-gen-message`, 'Topics generated and logged.', false);
                        }).catch(() => {
                            setMessage(`${type}-gen-message`, 'Network error while generating.', true);
                            renderDiagnostics(`${type}-gen-diag`, null);
                        });
                    });
                });
            }

            function bindSaveForms() {
                document.querySelectorAll('.save-form').forEach(form => {
                    form.addEventListener('submit', (e) => {
                        e.preventDefault();
                        const type = form.dataset.type;
                        form.source.value = 'ai';
                        submitSave(form, type);
                    });
                });

                document.querySelectorAll('.manual-save').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const form = e.target.closest('.save-form');
                        if (!form) return;
                        const type = form.dataset.type;
                        form.source.value = 'manual';
                        form.provider.value = '';
                        form.model.value = '';
                        form.requestId.value = '';
                        form.httpStatus.value = '';
                        form.promptHash.value = '';
                        form.nonce.value = '';
                        form.generatedAt.value = '';
                        form.rawTextSnippet.value = '';
                        if (form.modelUsed) {
                            form.modelUsed.value = '';
                        }
                        if (form.aiOk) {
                            form.aiOk.value = '';
                        }
                        if (form.aiError) {
                            form.aiError.value = '';
                        }
                        renderDiagnostics(`${type}-save-diag`, null);
                        submitSave(form, type, true);
                    });
                });
            }

            function submitSave(form, type, manual = false) {
                const formData = new FormData(form);
                setMessage(`${type}-save-message`, manual ? 'Saving manual topic...' : 'Saving selected topic...', false);
                fetch('/superadmin/topic_save.php', {
                    method: 'POST',
                    body: formData,
                    headers: {'Accept': 'application/json'}
                }).then(resp => resp.json()).then(data => {
                    if (!data.ok) {
                        setMessage(`${type}-save-message`, data.error || 'Save failed.', true);
                        return;
                    }
                    setMessage(`${type}-save-message`, 'Topic saved.', false);
                    const saved = data.saved || null;
                    if (saved) {
                        savedData[type] = [saved, ...(savedData[type] || [])];
                        renderSaved(type);
                    }
                }).catch(() => {
                    setMessage(`${type}-save-message`, 'Network error while saving.', true);
                });
            }

            function bindDeleteActions() {
                document.querySelectorAll('table').forEach(table => {
                    table.addEventListener('click', (e) => {
                        const genBtn = e.target.closest('[data-generate]');
                        if (genBtn) {
                            const topicId = genBtn.dataset.topicId;
                            const targetType = genBtn.dataset.generate;
                            const sourceType = genBtn.dataset.sourceType || 'blog';
                            const row = genBtn.closest('tr');
                            const newsLength = row ? (row.dataset.newsLength || '') : '';
                            triggerDraftGeneration(sourceType, targetType, topicId, newsLength);
                            return;
                        }
                        const btn = e.target.closest('.delete-btn');
                        if (!btn) return;
                        const type = btn.dataset.type;
                        const topicId = btn.dataset.topicId;
                        fetch('/superadmin/topic_delete.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({type, topicId, csrf_token: csrfToken})
                        }).then(resp => resp.json()).then(data => {
                            if (!data.ok) {
                                alert(data.error || 'Delete failed');
                                return;
                            }
                            savedData[type] = (savedData[type] || []).filter(row => row.topicId !== topicId);
                            renderSaved(type);
                        }).catch(() => alert('Network error deleting topic.'));
                    });
                });
            }

            handleTabSwitch();
            bindGenerationForms();
            bindSaveForms();
            bindDeleteActions();
            renderSaved('blog');
            renderSaved('news');

            function triggerDraftGeneration(sourceType, targetType, topicId, newsLength) {
                if (!topicId || !targetType) return;
                const messageId = `${sourceType}-draft-message`;
                setMessage(messageId, `Generating ${targetType} draft...`, false);
                renderDiagnostics(`${sourceType}-draft-diag`, null);
                const fd = new FormData();
                fd.append('csrf_token', csrfToken);
                fd.append('type', targetType);
                fd.append('sourceType', sourceType);
                fd.append('topicId', topicId);
                if (targetType === 'news') {
                    fd.append('newsLength', newsLength || 'standard');
                }
                fetch('/superadmin/content_generate_from_topic.php', {
                    method: 'POST',
                    body: fd,
                    headers: {'Accept': 'application/json'}
                }).then(resp => resp.json()).then(data => {
                    if (!data.ok) {
                        setMessage(messageId, data.error || 'Draft generation failed.', true);
                        renderDiagnostics(`${sourceType}-draft-diag`, data.aiMeta || null, data.error || '');
                        return;
                    }
                    renderDiagnostics(`${sourceType}-draft-diag`, data.aiMeta || null, '');
                    setMessage(messageId, 'Draft created. Redirecting...', false);
                    if (data.viewUrl) {
                        window.location.href = data.viewUrl;
                    }
                }).catch(() => {
                    setMessage(messageId, 'Network error while creating draft.', true);
                    renderDiagnostics(`${sourceType}-draft-diag`, null);
                });
            }
        </script>
        <?php
    });
});
