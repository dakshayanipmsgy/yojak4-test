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

    $title = get_app_config()['appName'] . ' | Draft ' . sanitize($contentId);

    render_layout($title, function () use ($draft, $type, $contentId) {
        $gen = $draft['generation'] ?? [];
        $tags = is_array($draft['tags'] ?? null) ? $draft['tags'] : [];
        ?>
        <style>
            .flex-between { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
            .pill { display:inline-block; padding:6px 10px; border-radius:999px; background:#111820; border:1px solid #2b3440; color:#9fb2c8; font-size:12px; }
            .muted-compact { font-size:12px; color:var(--muted); }
            .grid-2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:14px; }
            .body-preview { background:#0f1520; border:1px solid #2b3440; border-radius:12px; padding:12px; }
            .chip { display:inline-block; padding:4px 8px; border-radius:999px; background:#0f1520; border:1px solid #2b3440; color:#9fb2c8; font-size:12px; margin-right:6px; margin-top:4px; }
        </style>
        <div class="card">
            <div class="flex-between">
                <div>
                    <h2 style="margin:0;">Draft <?= sanitize(strtoupper($type)); ?> • <?= sanitize($contentId); ?></h2>
                    <p class="muted" style="margin:4px 0 0;">Status: <?= sanitize($draft['status'] ?? 'draft'); ?> • Updated <?= sanitize($draft['updatedAt'] ?? ''); ?></p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn secondary" href="/superadmin/content_v2.php">Back to Content v2</a>
                    <a class="btn" href="/superadmin/content_draft_edit.php?type=<?= urlencode($type); ?>&contentId=<?= urlencode($contentId); ?>">Edit Draft</a>
                </div>
            </div>
        </div>

        <div class="grid-2" style="margin-top:14px;">
            <div class="card" style="display:grid; gap:10px;">
                <div class="flex-between">
                    <div>
                        <h3 style="margin:0;"><?= sanitize($draft['title'] ?? ''); ?></h3>
                        <p class="muted-compact" style="margin:4px 0 0;">Slug: <?= sanitize($draft['slug'] ?? ''); ?></p>
                    </div>
                    <span class="pill"><?= sanitize($draft['newsLength'] ?? ($type === 'news' ? 'standard' : 'blog')); ?></span>
                </div>
                <p class="muted"><?= sanitize($draft['excerpt'] ?? ''); ?></p>
                <?php if ($tags): ?>
                    <div>
                        <?php foreach ($tags as $tag): ?>
                            <span class="chip"><?= sanitize($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="body-preview">
                    <?= $draft['bodyHtml'] ?? '<p class="muted">No body.</p>'; ?>
                </div>
            </div>
            <div class="card" style="display:grid; gap:10px;">
                <div class="flex-between">
                    <h3 style="margin:0;">Diagnostics</h3>
                    <span class="pill">Job <?= sanitize($gen['jobId'] ?? ''); ?></span>
                </div>
                <ul style="list-style:none; padding:0; margin:0; display:grid; gap:6px;">
                    <li><strong>Provider:</strong> <?= sanitize($gen['provider'] ?? ''); ?></li>
                    <li><strong>Model:</strong> <?= sanitize($gen['modelUsed'] ?? ($gen['model'] ?? '')); ?></li>
                    <li><strong>HTTP:</strong> <?= sanitize((string)($gen['httpStatus'] ?? '')); ?></li>
                    <li><strong>Request ID:</strong> <?= sanitize($gen['requestId'] ?? ''); ?></li>
                    <li><strong>Prompt hash:</strong> <?= sanitize($gen['promptHash'] ?? ''); ?></li>
                    <li><strong>Output hash:</strong> <?= sanitize($gen['outputHash'] ?? ''); ?></li>
                    <li><strong>Nonce:</strong> <?= sanitize($gen['nonce'] ?? ''); ?></li>
                    <li><strong>Created at:</strong> <?= sanitize($gen['createdAt'] ?? ''); ?></li>
                    <li><strong>Raw snippet:</strong>
                        <div class="muted" style="white-space:pre-wrap;"><?= sanitize($gen['rawTextSnippet'] ?? ''); ?></div>
                    </li>
                </ul>
                <?php if (!empty($draft['topicId'])): ?>
                    <a class="btn secondary" href="/superadmin/topic_view.php?type=<?= urlencode($type); ?>&topicId=<?= urlencode($draft['topicId']); ?>">View Topic</a>
                <?php endif; ?>
                <form method="post" action="/superadmin/content_publish.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="type" value="<?= sanitize($type); ?>">
                    <input type="hidden" name="contentId" value="<?= sanitize($contentId); ?>">
                    <button class="btn" type="submit">Publish (stub)</button>
                </form>
            </div>
        </div>
        <?php
    });
});
