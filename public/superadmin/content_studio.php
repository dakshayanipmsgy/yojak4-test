<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $title = get_app_config()['appName'] . ' | Content Studio';
    $blogs = list_content('blog');
    $newsList = list_content('news');

    render_layout($title, function () use ($blogs, $newsList) {
        ?>
        <div class="card" style="border-color:#f85149;background:rgba(248,81,73,0.08);color:#f77676;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <strong><?= sanitize('Legacy Content Studio'); ?></strong>
                    <div class="muted" style="color:#f77676;"><?= sanitize('Use Content Studio v2 for new topics and drafts to avoid confusion.'); ?></div>
                </div>
                <a class="btn" href="/superadmin/content_v2.php" style="background:#f85149;border-color:#c03a34;"><?= sanitize('Open Content Studio v2'); ?></a>
            </div>
        </div>

        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Content Studio</h2>
                    <p class="muted" style="margin:4px 0 0;">Generate blogs and news in one click, then edit, publish, or schedule.</p>
                </div>
                <span class="pill">Superadmin • AI powered</span>
            </div>
            <form id="generate-form" method="post" action="/superadmin/content_generate.php" style="margin-top:16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;align-items:end;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field" style="grid-column:1/-1;">
                    <label for="prompt">Prompt</label>
                    <textarea id="prompt" name="prompt" rows="3" style="width:100%;border-radius:12px;border:1px solid #30363d;background:#0d1117;color:#e6edf3;padding:10px;" placeholder="Describe the theme, audience, and key points. Leave blank for random platform news."></textarea>
                </div>
                <div class="field">
                    <label for="type">Type</label>
                    <select id="type" name="type" required>
                        <option value="blog">Blog</option>
                        <option value="news">News</option>
                    </select>
                </div>
                <div class="field">
                    <label for="length">News Length</label>
                    <select id="length" name="length">
                        <option value="short">Short</option>
                        <option value="standard" selected>Standard</option>
                        <option value="long">Long</option>
                    </select>
                    <small class="muted">Used for news only.</small>
                </div>
                <div class="field">
                    <label for="variation-slider">Variation level</label>
                    <input type="range" id="variation-slider" min="1" max="3" step="1" value="3" aria-label="Variation slider">
                    <input type="hidden" id="variation-value" name="variation" value="high">
                    <div class="muted" id="variation-label" style="margin-top:4px;">High — maximize uniqueness</div>
                </div>
                <div class="field">
                    <label class="muted" style="display:flex;gap:8px;align-items:center;">
                        <input type="checkbox" id="random_platform" name="random_platform" value="1">
                        Random platform news if prompt empty
                    </label>
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <button class="btn" type="submit" id="generate-btn">Generate</button>
                    <span class="muted" style="margin-left:8px;">Live streaming via SSE with draft auto-save.</span>
                </div>
            </form>
        </div>

        <div class="card" style="margin-top:16px;">
            <h3 style="margin-top:0;">Live Generation Stream</h3>
            <textarea id="stream-log" readonly rows="8" style="width:100%;resize:vertical;background:#0f1520;border:1px solid #30363d;border-radius:10px;padding:10px;color:#e6edf3;">Waiting for a job...</textarea>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:10px;">
                <span class="pill" id="job-indicator">Current jobId: none</span>
                <span class="muted" id="draft-indicator">No draft yet.</span>
            </div>
            <div style="margin-top:8px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <span class="pill secondary" id="meta-summary" style="display:none;">Meta: pending</span>
            </div>
            <div class="buttons" style="margin-top:10px;">
                <button class="btn secondary" type="button" id="clear-log">Clear</button>
                <a class="btn" id="edit-link" style="display:none;" href="#">Open Draft</a>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <h3 style="margin:0;">Content Library</h3>
                <span class="pill">Drafts, Published, Scheduled</span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin-top:12px;">
                <?php foreach (['blog' => $blogs, 'news' => $newsList] as $label => $items): ?>
                    <div class="card" style="background:#0f1520;border:1px dashed #30363d;">
                        <h4 style="margin-top:0;text-transform:capitalize;"><?= sanitize($label); ?></h4>
                        <?php if (!$items): ?>
                            <p class="muted" style="margin:0;">No items yet.</p>
                        <?php else: ?>
                            <ul style="list-style:none;padding:0;margin:0;display:grid;gap:8px;">
                                <?php foreach (array_slice($items, 0, 5) as $item): ?>
                                    <li style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
                                        <div>
                                            <div style="font-weight:700;"><?= sanitize($item['title'] ?? 'Untitled'); ?></div>
                                            <div class="muted" style="font-size:12px;"><?= sanitize($item['status'] ?? ''); ?> • <?= sanitize($item['slug'] ?? ''); ?></div>
                                        </div>
                                        <a class="btn secondary" style="padding:6px 10px;" href="/superadmin/content_edit.php?id=<?= urlencode($item['id']); ?>">Edit</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <script>
            const form = document.getElementById('generate-form');
            const logBox = document.getElementById('stream-log');
            const clearBtn = document.getElementById('clear-log');
            const editLink = document.getElementById('edit-link');
            const jobIndicator = document.getElementById('job-indicator');
            const draftIndicator = document.getElementById('draft-indicator');
            const metaSummary = document.getElementById('meta-summary');
            const typeSelect = document.getElementById('type');
            const lengthSelect = document.getElementById('length');
            const variationSlider = document.getElementById('variation-slider');
            const variationLabel = document.getElementById('variation-label');
            const variationValue = document.getElementById('variation-value');
            let eventSource = null;
            let pollTimer = null;
            let lastCount = 0;
            let currentJobId = '';

            function appendLog(text) {
                const now = new Date().toLocaleTimeString();
                logBox.value += `\\n[${now}] ${text}`;
                logBox.scrollTop = logBox.scrollHeight;
            }

            clearBtn.addEventListener('click', () => {
                logBox.value = 'Waiting for a job...';
                editLink.style.display = 'none';
                draftIndicator.textContent = 'No draft yet.';
                jobIndicator.textContent = 'Current jobId: none';
                metaSummary.style.display = 'none';
                currentJobId = '';
            });

            function updateVariationLabel(val) {
                if (val === '1') {
                    variationLabel.textContent = 'Low — subtle changes, calmer tone';
                    variationValue.value = 'low';
                } else if (val === '2') {
                    variationLabel.textContent = 'Medium — rotate phrasing and structure';
                    variationValue.value = 'medium';
                } else {
                    variationLabel.textContent = 'High — maximize uniqueness';
                    variationValue.value = 'high';
                }
            }

            variationSlider.addEventListener('input', (e) => updateVariationLabel(e.target.value));
            updateVariationLabel(variationSlider.value);

            typeSelect.addEventListener('change', () => {
                const isNews = typeSelect.value === 'news';
                lengthSelect.disabled = !isNews;
                lengthSelect.style.opacity = isNews ? '1' : '0.6';
            });
            typeSelect.dispatchEvent(new Event('change'));

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                logBox.value = 'Submitting job...';
                editLink.style.display = 'none';
                metaSummary.style.display = 'none';
                const formData = new FormData(form);
                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {'Accept': 'application/json'}
                }).then(resp => resp.json()).then(data => {
                    if (!data.ok) {
                        appendLog(data.error || 'Unable to start job.');
                        return;
                    }
                    appendLog('Job started: ' + data.jobId);
                    jobIndicator.textContent = 'Current jobId: ' + data.jobId;
                    currentJobId = data.jobId;
                    startStream(data.jobId);
                }).catch(() => appendLog('Network error starting job.'));
            });

            function startStream(jobId) {
                if (eventSource) {
                    eventSource.close();
                }
                if (pollTimer) {
                    clearInterval(pollTimer);
                }
                lastCount = 0;
                currentJobId = jobId;
                jobIndicator.textContent = 'Current jobId: ' + jobId;
                eventSource = new EventSource('/superadmin/content_stream.php?jobId=' + encodeURIComponent(jobId));
                eventSource.onmessage = function (ev) {
                    const payload = JSON.parse(ev.data);
                    if (payload.chunk) {
                        appendLog(payload.chunk);
                    }
                    if (payload.status === 'done' && payload.contentId) {
                        editLink.href = '/superadmin/content_edit.php?id=' + encodeURIComponent(payload.contentId);
                        editLink.style.display = 'inline-flex';
                        const draftMsg = 'Created draft: ' + payload.contentId;
                        draftIndicator.textContent = draftMsg;
                        if (payload.meta) {
                            metaSummary.textContent = `Meta: job ${payload.jobId} • content ${payload.contentId} • prompt ${payload.meta.promptHash || 'n/a'} • output ${payload.meta.outputHash || 'n/a'}`;
                            metaSummary.style.display = 'inline-flex';
                        }
                        appendLog(draftMsg + '. Click "Open Draft".');
                    }
                    if (payload.status === 'error') {
                        appendLog(payload.error || 'Generation error.');
                    }
                    if (payload.status === 'done' || payload.status === 'error') {
                        eventSource.close();
                    }
                };
                eventSource.onerror = function () {
                    appendLog('Streaming error. Switching to polling...');
                    eventSource.close();
                    startPolling(jobId);
                };
            }

            function startPolling(jobId) {
                pollTimer = setInterval(() => {
                    fetch('/superadmin/content_stream.php?jobId=' + encodeURIComponent(jobId) + '&poll=1', {headers:{'Accept':'application/json'}})
                        .then(resp => resp.json())
                        .then(job => {
                            if (job.ok === false) {
                                appendLog(job.error || 'Job unavailable. Please regenerate.');
                                clearInterval(pollTimer);
                                return;
                            }
                            const chunks = job.chunks || [];
                            if (chunks.length > lastCount) {
                                for (let i = lastCount; i < chunks.length; i++) {
                                    if (chunks[i].text) appendLog(chunks[i].text);
                                }
                                lastCount = chunks.length;
                            }
                            if (job.status === 'done' && job.resultContentId) {
                                editLink.href = '/superadmin/content_edit.php?id=' + encodeURIComponent(job.resultContentId);
                                editLink.style.display = 'inline-flex';
                                const draftMsg = 'Created draft: ' + job.resultContentId;
                                draftIndicator.textContent = draftMsg;
                                if (job.generation) {
                                    const promptHash = (job.generation.promptHash || '').slice(0, 16);
                                    const outputHash = (job.generation.outputHash || '').slice(0, 16);
                                    metaSummary.textContent = `Meta: job ${job.jobId} • content ${job.resultContentId} • prompt ${promptHash || 'n/a'} • output ${outputHash || 'n/a'}`;
                                    metaSummary.style.display = 'inline-flex';
                                }
                                appendLog(draftMsg + '. Click \"Open Draft\".');
                                clearInterval(pollTimer);
                            }
                            if (job.status === 'error') {
                                appendLog(job.errorText || 'Generation error.');
                                clearInterval(pollTimer);
                            }
                        })
                        .catch(() => appendLog('Polling error.'));
                }, 1500);
            }
        </script>
        <?php
    });
});
