<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'] ?? '';
    $src = $_GET['src'] ?? 'dept';

    if ($src === 'dept') {
        $deptId = normalize_dept_id(trim($_GET['deptId'] ?? ''));
        $ytdId = trim($_GET['id'] ?? '');
        if (!is_valid_dept_id($deptId) || $ytdId === '') {
            render_error_page('Tender not found.');
            return;
        }
        $snapshot = load_public_tender_snapshot($deptId, $ytdId);
        if (!$snapshot) {
            render_error_page('Tender not available.');
            return;
        }
        $department = load_department($deptId);
        $link = load_contractor_link($yojId, $deptId);
        $linked = $link && ($link['status'] ?? '') === 'active';

        $title = get_app_config()['appName'] . ' | Tender';
        render_layout($title, function () use ($snapshot, $department, $linked) {
            ?>
            <div class="card" style="display:grid;gap:12px;">
                <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:flex-start;">
                    <div>
                        <h2 style="margin:0;"><?= sanitize($snapshot['title'] ?? 'Tender'); ?></h2>
                        <p class="muted" style="margin:4px 0 0;">
                            <?= sanitize($snapshot['ytdId'] ?? ''); ?>
                            • <?= sanitize($snapshot['deptPublic']['nameEn'] ?? ($department['nameEn'] ?? '')); ?>
                            <?php if (!empty($snapshot['deptPublic']['district'])): ?>
                                • <?= sanitize($snapshot['deptPublic']['district']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <a class="btn secondary" href="/contractor/tenders.php"><?= sanitize('Back'); ?></a>
                        <form method="post" action="/contractor/start_pack.php">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <input type="hidden" name="src" value="dept">
                            <input type="hidden" name="deptId" value="<?= sanitize($snapshot['deptId'] ?? ''); ?>">
                            <input type="hidden" name="ytdId" value="<?= sanitize($snapshot['ytdId'] ?? ''); ?>">
                            <button class="btn" type="submit"><?= sanitize('Start Pack'); ?></button>
                        </form>
                        <?php if (!$linked): ?>
                            <a class="btn secondary" href="/contractor/departments.php"><?= sanitize('Link to department (recommended)'); ?></a>
                        <?php else: ?>
                            <span class="pill success"><?= sanitize('Linked'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <?php if (!empty($snapshot['submissionDeadline'])): ?>
                        <span class="pill"><?= sanitize('Submission: ' . $snapshot['submissionDeadline']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($snapshot['openingDate'])): ?>
                        <span class="pill"><?= sanitize('Opening: ' . $snapshot['openingDate']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($snapshot['publishedAt'])): ?>
                        <span class="pill"><?= sanitize('Published: ' . $snapshot['publishedAt']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($snapshot['emd'])): ?>
                        <span class="pill"><?= sanitize('EMD: ' . $snapshot['emd']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($snapshot['summaryPublic'])): ?>
                    <div class="card" style="background:var(--surface-2);">
                        <h3 style="margin:0 0 6px 0;"><?= sanitize('Summary'); ?></h3>
                        <p style="margin:0;"><?= nl2br(sanitize($snapshot['summaryPublic'])); ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($snapshot['attachmentsPublic'])): ?>
                    <div class="card" style="background:var(--surface-2);">
                        <h3 style="margin:0 0 6px 0;"><?= sanitize('Attachments'); ?></h3>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <?php foreach ($snapshot['attachmentsPublic'] as $att): ?>
                                <a class="pill" href="/download.php?type=dept_public_tender&deptId=<?= urlencode($snapshot['deptId'] ?? ''); ?>&ytdId=<?= urlencode($snapshot['ytdId'] ?? ''); ?>&file=<?= urlencode($att['storedPath'] ?? ''); ?>" target="_blank" rel="noopener">
                                    <?= sanitize($att['name'] ?? 'Attachment'); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="card" style="background:var(--surface-2);">
                    <h3 style="margin:0 0 6px 0;"><?= sanitize('Checklist'); ?></h3>
                    <?php if (!empty($snapshot['requirementSetId'])): ?>
                        <?php if ($linked): ?>
                            <p class="muted" style="margin:0;"><?= sanitize('Official requirement set will be auto-applied to your pack.'); ?></p>
                        <?php else: ?>
                            <p class="muted" style="margin:0;"><?= sanitize('Link to the department to unlock the official checklist in your pack.'); ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="muted" style="margin:0;"><?= sanitize('No official checklist provided.'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        });
        return;
    }

    // Discovery fallback
    redirect('/contractor/discovered_tender_view.php?id=' . urlencode($_GET['id'] ?? ''));
});
