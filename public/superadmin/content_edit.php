<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $id = $_GET['id'] ?? '';
    if ($id === '') {
        render_error_page('Missing content ID.');
        return;
    }

    $item = load_content_by_id($id);
    if (!$item) {
        render_error_page('Content not found.');
        return;
    }

    $title = get_app_config()['appName'] . ' | Edit Content';

    render_layout($title, function () use ($item) {
        $dupFlag = !empty($item['generation']['dupFlag']);
        $dupOf = $item['generation']['dupOfContentId'] ?? null;
        $dupBasis = $item['generation']['dupBasis'] ?? null;
        $dupSimilarity = $item['generation']['similarityScore'] ?? null;
        $regenAttempted = !empty($item['generation']['regenAttempted']);
        $regenAuto = !empty($item['generation']['regenAuto']);
        ?>
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Edit <?= sanitize(ucfirst($item['type'] ?? 'content')); ?></h2>
                    <p class="muted" style="margin:4px 0 0;">Status: <?= sanitize($item['status'] ?? ''); ?> • ID: <?= sanitize($item['id'] ?? ''); ?></p>
                </div>
                <a class="btn secondary" href="/superadmin/content_studio.php">Back to Content Studio</a>
            </div>
            <?php if ($dupFlag): ?>
                <div style="margin-top:12px;padding:14px;border-radius:12px;border:1px solid #f85149;background:linear-gradient(135deg, rgba(248,81,73,0.14), rgba(248,81,73,0.06));display:grid;gap:6px;">
                    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;">
                        <div>
                            <div style="font-weight:700;color:#f77676;">This draft is similar to a recent post. We tried regenerating once.</div>
                            <div class="muted" style="margin-top:4px;">
                                <?= $dupOf ? 'Matched against: ' . sanitize($dupOf) . ' • ' : ''; ?>
                                <?= $dupBasis ? 'Basis: ' . sanitize($dupBasis) . ' • ' : ''; ?>
                                <?= $dupSimilarity !== null ? 'Similarity: ' . sanitize((string)round((float)$dupSimilarity * 100, 1)) . '%' : 'Similarity unknown'; ?>
                            </div>
                        </div>
                        <button class="btn danger" type="button" id="regen-again-btn">Regenerate again</button>
                    </div>
                    <div class="muted" id="regen-status">A fresh draft will use a new jobId and keep this one intact.</div>
                    <pre id="regen-log" style="margin:0;max-height:160px;overflow:auto;background:#0f1520;border:1px dashed #f85149;padding:10px;border-radius:10px;font-size:13px;">Ready for regeneration logs...</pre>
                </div>
            <?php elseif ($regenAttempted): ?>
                <div style="margin-top:12px;padding:12px;border-radius:12px;border:1px solid #30363d;background:#111820;">
                    <div style="font-weight:700;">Automatic regeneration completed.</div>
                    <div class="muted">No duplicate warning remains, but this draft was refreshed to avoid repetition.</div>
                </div>
            <?php endif; ?>
            <form method="post" action="/superadmin/content_save.php" style="margin-top:14px;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="id" value="<?= sanitize($item['id']); ?>">
                <div class="field" style="grid-column:1/-1;">
                    <label for="title">Title</label>
                    <input id="title" type="text" name="title" value="<?= sanitize($item['title'] ?? ''); ?>" required>
                </div>
                <div class="field">
                    <label for="slug">Slug</label>
                    <input id="slug" type="text" name="slug" value="<?= sanitize($item['slug'] ?? ''); ?>" required>
                    <small class="muted">lowercase, hyphen, 3–80 chars</small>
                </div>
                <div class="field">
                    <label for="publish_at">Schedule (optional)</label>
                    <input id="publish_at" type="datetime-local" name="publish_at" value="<?= $item['publishAt'] ? sanitize(date('Y-m-d\TH:i', strtotime($item['publishAt']))): ''; ?>">
                    <small class="muted">Set future time to schedule.</small>
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <label for="excerpt">Excerpt</label>
                    <textarea id="excerpt" name="excerpt" rows="2" style="width:100%;border-radius:12px;border:1px solid #30363d;background:#0d1117;color:#e6edf3;padding:10px;"><?= sanitize($item['excerpt'] ?? ''); ?></textarea>
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <label for="body">Body (HTML)</label>
                    <textarea id="body" name="body" rows="10" style="width:100%;border-radius:12px;border:1px solid #30363d;background:#0d1117;color:#e6edf3;padding:10px;"><?= sanitize($item['bodyHtml'] ?? ''); ?></textarea>
                    <small class="muted">Scripts and inline events are stripped on save.</small>
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <button class="btn" type="submit">Save Draft</button>
                </div>
            </form>
        </div>

        <?php if (!empty($item['generation'])): ?>
            <div class="card" style="margin-top:14px;">
                <h3 style="margin-top:0;">Generation details</h3>
                <p class="muted" style="margin:4px 0;">Job: <?= sanitize($item['generation']['jobId'] ?? ''); ?> • Nonce: <?= sanitize($item['generation']['nonce'] ?? ''); ?></p>
                <p class="muted" style="margin:4px 0;">Prompt hash: <?= sanitize(substr((string)($item['generation']['promptHash'] ?? ''), 0, 16)); ?> • Output hash: <?= sanitize(substr((string)($item['generation']['outputHash'] ?? ''), 0, 16)); ?></p>
                <p class="muted" style="margin:4px 0;">Requested: <?= sanitize($item['generation']['typeRequested'] ?? ''); ?> <?= !empty($item['generation']['lengthRequested']) ? '(' . sanitize($item['generation']['lengthRequested']) . ')' : ''; ?> • Model: <?= sanitize($item['generation']['provider'] ?? ''); ?> <?= sanitize($item['generation']['model'] ?? ''); ?> • Temp: <?= sanitize((string)($item['generation']['temperature'] ?? '')); ?></p>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-top:14px;display:flex;gap:14px;flex-wrap:wrap;align-items:center;">
            <div style="flex:1;min-width:220px;">
                <h3 style="margin-top:0;">Preview</h3>
                <?php if (!empty($item['coverImagePath'])): ?>
                    <img src="<?= sanitize($item['coverImagePath']); ?>" alt="Cover" style="max-width:100%;border-radius:12px;border:1px solid #30363d;">
                <?php endif; ?>
                <h4><?= sanitize($item['title'] ?? ''); ?></h4>
                <p class="muted"><?= sanitize($item['excerpt'] ?? ''); ?></p>
            </div>
            <div style="display:grid;gap:10px;flex:1;min-width:220px;">
                <form method="post" action="/superadmin/content_publish.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($item['id']); ?>">
                    <button class="btn" type="submit">Publish now</button>
                </form>
                <form method="post" action="/superadmin/content_schedule.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($item['id']); ?>">
                    <input type="hidden" name="publish_at" value="">
                    <button class="btn secondary" type="submit" onclick="return setScheduleFromForm();">Schedule from form value</button>
                </form>
                <form method="post" action="/superadmin/content_delete.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($item['id']); ?>">
                    <button class="btn danger" type="submit" onclick="return confirm('Delete this item?');">Delete</button>
                </form>
            </div>
        </div>
        <script>
            function setScheduleFromForm() {
                const publishInput = document.getElementById('publish_at');
                const hidden = document.querySelector('form[action=\"/superadmin/content_schedule.php\"] input[name=\"publish_at\"]');
                if (!publishInput || !hidden) return false;
                hidden.value = publishInput.value;
                return true;
            }
            const regenBtn = document.getElementById('regen-again-btn');
            if (regenBtn) {
                const regenStatus = document.getElementById('regen-status');
                const regenLog = document.getElementById('regen-log');
                const regenPayload = {
                    csrf: '<?= sanitize(csrf_token()); ?>',
                    type: '<?= sanitize($item['type'] ?? 'blog'); ?>',
                    length: '<?= $item['type'] === 'news' ? sanitize($item['generation']['lengthRequested'] ?? 'standard') : 'standard'; ?>',
                    prompt: <?= json_encode($item['promptUsed'] ?? ''); ?>,
                };
                let regenSource = null;
                let regenTimer = null;

                function appendRegenLog(text) {
                    if (!regenLog) return;
                    regenLog.textContent += '\\n' + text;
                    regenLog.scrollTop = regenLog.scrollHeight;
                }

                function finishAndRedirect(contentId) {
                    regenStatus.textContent = 'New draft ready: ' + contentId + '. Redirecting...';
                    setTimeout(() => {
                        window.location.href = '/superadmin/content_edit.php?id=' + encodeURIComponent(contentId);
                    }, 700);
                }

                function pollJob(jobId) {
                    regenTimer = setInterval(() => {
                        fetch('/superadmin/content_stream.php?jobId=' + encodeURIComponent(jobId) + '&poll=1', {headers:{'Accept':'application/json'}})
                            .then(resp => resp.json())
                            .then(job => {
                                const chunks = job.chunks || [];
                                if (chunks.length) {
                                    const last = chunks[chunks.length - 1];
                                    if (last && last.text) appendRegenLog(last.text);
                                }
                                if (job.status === 'done' && job.resultContentId) {
                                    clearInterval(regenTimer);
                                    finishAndRedirect(job.resultContentId);
                                }
                                if (job.status === 'error') {
                                    clearInterval(regenTimer);
                                    regenStatus.textContent = job.errorText || 'Regeneration failed.';
                                    regenBtn.disabled = false;
                                }
                            })
                            .catch(() => appendRegenLog('Polling error. Retrying...'));
                    }, 1500);
                }

                function streamJob(jobId) {
                    regenSource = new EventSource('/superadmin/content_stream.php?jobId=' + encodeURIComponent(jobId));
                    regenSource.onmessage = function (ev) {
                        const payload = JSON.parse(ev.data);
                        if (payload.chunk) appendRegenLog(payload.chunk);
                        if (payload.status === 'done' && payload.contentId) {
                            regenSource.close();
                            finishAndRedirect(payload.contentId);
                        }
                        if (payload.status === 'error') {
                            regenSource.close();
                            regenStatus.textContent = payload.error || 'Regeneration failed.';
                            regenBtn.disabled = false;
                        }
                    };
                    regenSource.onerror = function () {
                        appendRegenLog('Stream interrupted. Falling back to polling...');
                        if (regenSource) regenSource.close();
                        streamJobCleanup();
                        pollJob(jobId);
                    };
                }

                function streamJobCleanup() {
                    if (regenSource) {
                        regenSource.close();
                        regenSource = null;
                    }
                    if (regenTimer) {
                        clearInterval(regenTimer);
                        regenTimer = null;
                    }
                }

                regenBtn.addEventListener('click', () => {
                    regenBtn.disabled = true;
                    regenStatus.textContent = 'Starting regeneration request...';
                    appendRegenLog('Requesting a new job...');
                    const form = new FormData();
                    form.append('csrf_token', regenPayload.csrf);
                    form.append('type', regenPayload.type);
                    form.append('length', regenPayload.length);
                    form.append('variation', 'high');
                    if (regenPayload.prompt) {
                        form.append('prompt', regenPayload.prompt);
                    }
                    fetch('/superadmin/content_generate.php', {
                        method: 'POST',
                        body: form,
                        headers: {'Accept': 'application/json'}
                    }).then(resp => resp.json()).then(data => {
                        if (!data.ok || !data.jobId) {
                            regenStatus.textContent = data.error || 'Unable to start regeneration.';
                            regenBtn.disabled = false;
                            return;
                        }
                        appendRegenLog('Job started: ' + data.jobId);
                        regenStatus.textContent = 'Streaming new draft (job ' + data.jobId + ')...';
                        streamJob(data.jobId);
                    }).catch(() => {
                        regenStatus.textContent = 'Network error starting regeneration.';
                        regenBtn.disabled = false;
                    });
                });
            }
        </script>
        <?php
    });
});
