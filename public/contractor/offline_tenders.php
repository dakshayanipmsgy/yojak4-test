<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_offline_tender_env($yojId);

    $tenders = offline_tenders_index($yojId);
    usort($tenders, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

    $title = get_app_config()['appName'] . ' | Offline Tenders';

    render_layout($title, function () use ($tenders) {
        ?>
        <div class="card">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize('Offline Tenders'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Upload NIT/NIB PDFs, run AI extraction, and track checklist & reminders.'); ?></p>
                </div>
                <a class="btn" href="/contractor/offline_tender_create.php"><?= sanitize('Create Offline Tender'); ?></a>
            </div>
        </div>
        <div style="display:grid; gap:12px; margin-top:12px;">
            <?php if (!$tenders): ?>
                <div class="card">
                    <p class="muted" style="margin:0;"><?= sanitize('No offline tenders yet. Start by uploading your tender PDFs.'); ?></p>
                </div>
            <?php endif; ?>
            <?php foreach ($tenders as $tender): ?>
                <div class="card" style="display:grid; gap:8px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
                        <div>
                            <h3 style="margin:0;"><?= sanitize($tender['title'] ?? 'Untitled Tender'); ?></h3>
                            <p class="muted" style="margin:4px 0 0;">
                                <?= sanitize($tender['id'] ?? ''); ?> •
                                <?= sanitize(ucfirst($tender['status'] ?? 'draft')); ?>
                                <?php if (!empty($tender['submissionDeadline'])): ?>
                                    • <?= sanitize('Submission: ' . $tender['submissionDeadline']); ?>
                                <?php endif; ?>
                                <?php if (!empty($tender['openingDate'])): ?>
                                    • <?= sanitize('Opening: ' . $tender['openingDate']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <a class="btn secondary" href="/contractor/offline_tender_view.php?id=<?= sanitize($tender['id']); ?>"><?= sanitize('Open'); ?></a>
                            <?php if (empty($tender['deletedAt'])): ?>
                                <form method="post" action="/contractor/offline_tender_delete.php" onsubmit="return confirm('Archive this tender?');">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                    <input type="hidden" name="id" value="<?= sanitize($tender['id']); ?>">
                                    <button class="btn danger" type="submit"><?= sanitize('Archive'); ?></button>
                                </form>
                            <?php else: ?>
                                <span class="pill" style="border-color: var(--danger); color: #f77676;"><?= sanitize('Archived'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    });
});
