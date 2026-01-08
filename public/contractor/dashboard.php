<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $title = get_app_config()['appName'] . ' | Contractor Dashboard';

    $vaultFiles = contractor_vault_index($yojId);
    $packs = [];
    foreach (['tender', 'workorder'] as $context) {
        foreach (packs_index($yojId, $context) as $entry) {
            $packId = $entry['packId'] ?? '';
            if ($packId === '') {
                continue;
            }
            $pack = load_pack($yojId, $packId, $context);
            if (!$pack) {
                continue;
            }
            $deadline = $pack['submissionDeadline'] ?? ($pack['dates']['submission'] ?? '');
            $deadlineAt = null;
            if ($deadline !== '') {
                try {
                    $deadlineAt = new DateTimeImmutable($deadline, new DateTimeZone('Asia/Kolkata'));
                } catch (Exception $e) {
                    $deadlineAt = null;
                }
            }
            $attachments = pack_attachment_map($pack, $vaultFiles);
            $missingCount = count(pack_missing_checklist_item_ids($pack, $attachments));
            $stats = pack_stats($pack);
            $packs[] = [
                'packId' => $pack['packId'],
                'title' => $pack['tenderTitle'] ?? $pack['title'] ?? 'Pack',
                'context' => $context,
                'deadline' => $deadline,
                'deadlineAt' => $deadlineAt,
                'missingCount' => $missingCount,
                'printReady' => $missingCount === 0 && $stats['pendingRequired'] === 0,
            ];
        }
    }
    usort($packs, static function (array $a, array $b): int {
        $aTime = $a['deadlineAt'] ? $a['deadlineAt']->getTimestamp() : PHP_INT_MAX;
        $bTime = $b['deadlineAt'] ? $b['deadlineAt']->getTimestamp() : PHP_INT_MAX;
        return $aTime <=> $bTime;
    });
    $urgentPacks = array_slice($packs, 0, 5);

    render_layout($title, function () use ($user, $urgentPacks) {
        ?>
        <div class="card">
            <h2><?= sanitize('Welcome, ' . ($user['displayName'] ?? $user['username'])); ?></h2>
            <p class="muted"><?= sanitize('Manage your profile, documents, and upcoming tools.'); ?></p>
            <div class="buttons">
                <a class="btn" href="/contractor/vault.php"><?= sanitize('Open Vault'); ?></a>
                <a class="btn secondary" href="/contractor/profile.php"><?= sanitize('Edit Profile'); ?></a>
                <a class="btn secondary" href="/contractor/reminders.php"><?= sanitize('Reminders'); ?></a>
            </div>
        </div>
        <div class="card" style="display:grid; gap:12px;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
                <h3 style="margin:0;"><?= sanitize('My urgent packs'); ?></h3>
                <span class="pill"><?= sanitize(count($urgentPacks) . ' active'); ?></span>
            </div>
            <?php if ($urgentPacks): ?>
                <div style="display:grid; gap:8px;">
                    <?php foreach ($urgentPacks as $pack): ?>
                        <div style="border:1px solid #30363d; border-radius:10px; padding:10px; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                            <div>
                                <strong><?= sanitize($pack['title']); ?></strong>
                                <div class="muted" style="margin-top:4px;">
                                    <?= sanitize($pack['packId']); ?>
                                    <?= $pack['deadline'] !== '' ? sanitize(' • Deadline: ' . $pack['deadline']) : sanitize(' • Deadline not set'); ?>
                                </div>
                                <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:6px;">
                                    <span class="pill" style="<?= $pack['missingCount'] > 0 ? 'border-color:#f85149;color:#ffb3b8;' : ''; ?>"><?= sanitize($pack['missingCount'] . ' missing docs'); ?></span>
                                    <span class="pill" style="<?= $pack['printReady'] ? 'border-color:#2ea043;color:#8ce99a;' : ''; ?>"><?= sanitize($pack['printReady'] ? 'Print ready' : 'Needs updates'); ?></span>
                                </div>
                            </div>
                            <div class="buttons" style="gap:6px;">
                                <a class="btn secondary" href="/contractor/pack_view.php?packId=<?= sanitize($pack['packId']); ?>#missing-docs"><?= sanitize('Review'); ?></a>
                                <a class="btn" href="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=full" target="_blank" rel="noopener"><?= sanitize('Print'); ?></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="muted" style="margin:0;"><?= sanitize('No packs yet. Start a pack to track missing docs and deadlines.'); ?></p>
            <?php endif; ?>
        </div>
        <div class="card">
            <h3><?= sanitize('Shortcuts'); ?></h3>
            <div class="buttons">
                <a class="btn" href="/contractor/vault_upload.php"><?= sanitize('Upload Document'); ?></a>
                <a class="btn secondary" href="/contractor/offline_tenders.php"><?= sanitize('Offline Tenders'); ?></a>
                <a class="btn secondary" href="/contractor/reminders.php"><?= sanitize('Reminders'); ?></a>
                <a class="btn secondary" href="/contractor/support.php"><?= sanitize('Report Issue'); ?></a>
            </div>
        </div>
        <?php
    });
});
