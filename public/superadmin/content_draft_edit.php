<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $type = $_GET['type'] ?? '';
    $contentId = trim((string)($_GET['contentId'] ?? ''));
    if (!in_array($type, ['blog', 'news'], true) || $contentId === '') {
        render_error_page('Invalid draft request.');
        return;
    }

    $draft = content_v2_load_draft($type, $contentId);
    if (!$draft || ($draft['deletedAt'] ?? null) !== null) {
        render_error_page('Draft not found.');
        return;
    }

    $title = get_app_config()['appName'] . ' | Edit Draft ' . sanitize($contentId);

    render_layout($title, function () use ($draft, $type, $contentId) {
        $gen = $draft['generation'] ?? [];
        ?>
        <style>
            .muted-compact { font-size:12px; color:var(--muted); }
            .grid-2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:14px; }
        </style>
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Edit Draft <?= sanitize(strtoupper($type)); ?></h2>
                    <p class="muted" style="margin:4px 0 0;">ID: <?= sanitize($contentId); ?> • Status: <?= sanitize($draft['status'] ?? 'draft'); ?></p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn secondary" href="/superadmin/content_draft_view.php?type=<?= urlencode($type); ?>&contentId=<?= urlencode($contentId); ?>">View</a>
                    <a class="btn secondary" href="/superadmin/content_v2.php">Back</a>
                </div>
            </div>
        </div>

        <form method="post" action="/superadmin/content_draft_save.php" class="card" style="margin-top:14px;display:grid;gap:12px;">
            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
            <input type="hidden" name="type" value="<?= sanitize($type); ?>">
            <input type="hidden" name="contentId" value="<?= sanitize($contentId); ?>">
            <div class="field">
                <label for="title">Title</label>
                <input id="title" type="text" name="title" value="<?= sanitize($draft['title'] ?? ''); ?>" required>
            </div>
            <div class="field">
                <label for="slug">Slug</label>
                <input id="slug" type="text" name="slug" value="<?= sanitize($draft['slug'] ?? ''); ?>" required>
                <div class="muted-compact">Auto-uniqued when saving; lowercase, hyphenated.</div>
            </div>
            <div class="field">
                <label for="excerpt">Excerpt</label>
                <textarea id="excerpt" name="excerpt" rows="2" style="width:100%;border-radius:12px;border:1px solid #30363d;background:#0d1117;color:#e6edf3;padding:10px;"><?= sanitize($draft['excerpt'] ?? ''); ?></textarea>
            </div>
            <div class="field">
                <label for="body">Body (HTML)</label>
                <textarea id="body" name="body" rows="12" style="width:100%;border-radius:12px;border:1px solid #30363d;background:#0d1117;color:#e6edf3;padding:10px;"><?= sanitize($draft['bodyHtml'] ?? ''); ?></textarea>
                <div class="muted-compact">Scripts and inline events are stripped on save.</div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button class="btn" type="submit">Save Draft</button>
                <a class="btn secondary" href="/superadmin/content_draft_view.php?type=<?= urlencode($type); ?>&contentId=<?= urlencode($contentId); ?>">Cancel</a>
            </div>
        </form>

        <div class="card" style="margin-top:14px;display:grid;gap:10px;">
            <h3 style="margin:0;">Generation diagnostics</h3>
            <div class="muted-compact">Job <?= sanitize($gen['jobId'] ?? ''); ?> • Output hash <?= sanitize($gen['outputHash'] ?? ''); ?> • Prompt hash <?= sanitize($gen['promptHash'] ?? ''); ?></div>
            <div class="grid-2">
                <div>
                    <p class="muted-compact">Provider: <?= sanitize($gen['provider'] ?? ''); ?></p>
                    <p class="muted-compact">Model: <?= sanitize($gen['model'] ?? ''); ?></p>
                    <p class="muted-compact">Request ID: <?= sanitize($gen['requestId'] ?? ''); ?></p>
                </div>
                <div>
                    <p class="muted-compact">HTTP: <?= sanitize((string)($gen['httpStatus'] ?? '')); ?></p>
                    <p class="muted-compact">Nonce: <?= sanitize($gen['nonce'] ?? ''); ?></p>
                    <p class="muted-compact">Created: <?= sanitize($gen['createdAt'] ?? ''); ?></p>
                </div>
            </div>
        </div>
        <?php
    });
});
