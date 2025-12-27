<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $type = $_GET['type'] ?? '';
    $topicId = trim((string)($_GET['topicId'] ?? ''));

    if (!in_array($type, ['blog', 'news'], true) || $topicId === '') {
        render_error_page('Invalid topic request.');
        return;
    }

    $record = topic_v2_load_record($type, $topicId);
    $title = get_app_config()['appName'] . ' | Topic ' . sanitize($topicId);

    render_layout($title, function () use ($record, $type, $topicId) {
        ?>
        <style>
            .chip { display:inline-block; padding:4px 8px; border-radius:999px; background:#111820; border:1px solid #2b3440; color:#9fb2c8; font-size:12px; margin-right:6px; margin-top:4px; }
            .flex-between { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
        </style>
        <div class="card">
            <div class="flex-between">
                <div>
                    <h2 style="margin:0;">Topic <?= sanitize($topicId); ?></h2>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize(strtoupper($type)); ?> • Traceable record</p>
                </div>
                <a class="btn secondary" href="/superadmin/content_v2.php">Back to Content v2</a>
            </div>
            <?php if (!$record): ?>
                <p class="muted" style="margin-top:12px;">Topic not found.</p>
            <?php else: ?>
                <div style="display:grid; gap:10px; margin-top:12px;">
                    <div class="pill"><?= sanitize($record['status'] ?? 'draft'); ?> • Created <?= sanitize($record['createdAt'] ?? ''); ?></div>
                    <div class="card" style="background:#0f1520; border:1px solid #2b3440;">
                        <h3 style="margin-top:0;">Details</h3>
                        <p><strong>Title:</strong> <?= sanitize($record['topicTitle'] ?? ''); ?></p>
                        <?php if (!empty($record['topicAngle'])): ?>
                            <p><strong>Angle:</strong> <?= sanitize($record['topicAngle']); ?></p>
                        <?php endif; ?>
                        <p><strong>Audience:</strong> <?= sanitize($record['audience'] ?? ''); ?></p>
                        <?php if (!empty($record['keywords'])): ?>
                            <p><strong>Keywords:</strong>
                                <?php foreach ($record['keywords'] as $kw): ?>
                                    <span class="chip"><?= sanitize($kw); ?></span>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($record['newsLength'])): ?>
                            <p><strong>News length:</strong> <?= sanitize($record['newsLength']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="card" style="background:#0f1520; border:1px solid #2b3440;">
                        <h3 style="margin-top:0;">AI Meta</h3>
                        <?php if (empty($record['aiMeta'])): ?>
                            <p class="muted">Manual topic (no AI metadata).</p>
                        <?php else: ?>
                            <ul style="list-style:none; padding:0; margin:0; display:grid; gap:6px;">
                                <li><strong>Provider:</strong> <?= sanitize($record['aiMeta']['provider'] ?? ''); ?></li>
                                <li><strong>Model:</strong> <?= sanitize($record['aiMeta']['model'] ?? ''); ?></li>
                                <li><strong>Request ID:</strong> <?= sanitize($record['aiMeta']['requestId'] ?? ''); ?></li>
                                <li><strong>HTTP Status:</strong> <?= sanitize((string)($record['aiMeta']['httpStatus'] ?? '')); ?></li>
                                <li><strong>Prompt hash:</strong> <?= sanitize($record['aiMeta']['promptHash'] ?? ''); ?></li>
                                <li><strong>Nonce:</strong> <?= sanitize($record['aiMeta']['nonce'] ?? ''); ?></li>
                                <li><strong>Generated at:</strong> <?= sanitize($record['aiMeta']['generatedAt'] ?? ''); ?></li>
                                <li><strong>Raw snippet:</strong>
                                    <div class="muted" style="white-space:pre-wrap;"><?= sanitize($record['aiMeta']['rawTextSnippet'] ?? ''); ?></div>
                                </li>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    });
});
