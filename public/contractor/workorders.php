<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_workorder_env($yojId);

    $workorders = workorders_index($yojId);
    usort($workorders, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

    $title = get_app_config()['appName'] . ' | Workorders';

    render_layout($title, function () use ($workorders) {
        ?>
        <div class="card">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize('Workorders'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Create workorders manually or upload PDFs. Run AI to extract obligations, documents, and timeline.'); ?></p>
                </div>
                <a class="btn" href="/contractor/workorder_create.php"><?= sanitize('Create Workorder'); ?></a>
            </div>
        </div>
        <div style="display:grid; gap:12px; margin-top:12px;">
            <?php if (!$workorders): ?>
                <div class="card">
                    <p class="muted" style="margin:0;"><?= sanitize('No workorders yet. Create one to get started.'); ?></p>
                </div>
            <?php endif; ?>
            <?php foreach ($workorders as $wo): ?>
                <div class="card" style="display:grid; gap:8px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
                        <div>
                            <h3 style="margin:0;"><?= sanitize($wo['title'] ?? 'Workorder'); ?></h3>
                            <p class="muted" style="margin:4px 0 0;">
                                <?= sanitize($wo['woId'] ?? ''); ?> • <?= sanitize($wo['deptName'] ?? ''); ?>
                                <?php if (!empty($wo['projectLocation'])): ?>
                                    • <?= sanitize($wo['projectLocation']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <a class="btn secondary" href="/contractor/workorder_view.php?id=<?= sanitize($wo['woId']); ?>"><?= sanitize('Open'); ?></a>
                        </div>
                    </div>
                    <?php if (!empty($wo['linkedPackId'])): ?>
                        <div class="pill"><?= sanitize('Linked pack: ' . $wo['linkedPackId']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    });
});
