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
        ?>
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Edit <?= sanitize(ucfirst($item['type'] ?? 'content')); ?></h2>
                    <p class="muted" style="margin:4px 0 0;">Status: <?= sanitize($item['status'] ?? ''); ?> • ID: <?= sanitize($item['id'] ?? ''); ?></p>
                </div>
                <a class="btn secondary" href="/superadmin/content_studio.php">Back to Content Studio</a>
            </div>
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
        </script>
        <?php
    });
});
