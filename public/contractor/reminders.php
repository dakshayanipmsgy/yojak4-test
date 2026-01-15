<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_reminders_env($yojId);

    $vaultFiles = contractor_vault_index($yojId);
    $packOptions = [];
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
            $attachments = pack_attachment_map($pack, $vaultFiles);
            $missingCount = count(pack_missing_checklist_item_ids($pack, $attachments));
            upsert_missing_docs_reminder($yojId, $pack, $missingCount);
            upsert_pack_deadline_reminder($yojId, $pack);
            $packOptions[$packId] = $pack['tenderTitle'] ?? $pack['title'] ?? $packId;
        }
    }

    $reminders = reminder_index_entries($yojId);
    $normalized = array_map('normalize_reminder_entry', $reminders);
    usort($normalized, static function (array $a, array $b): int {
        $statusOrder = ($a['status'] ?? 'open') === 'done' ? 1 : 0;
        $statusOrderB = ($b['status'] ?? 'open') === 'done' ? 1 : 0;
        if ($statusOrder !== $statusOrderB) {
            return $statusOrder <=> $statusOrderB;
        }
        $timeA = $a['dueAt'] ? strtotime((string)$a['dueAt']) : PHP_INT_MAX;
        $timeB = $b['dueAt'] ? strtotime((string)$b['dueAt']) : PHP_INT_MAX;
        return $timeA <=> $timeB;
    });

    $title = get_app_config()['appName'] . ' | Reminders';

    render_layout($title, function () use ($normalized, $packOptions) {
        ?>
        <div class="card" style="display:grid; gap:12px;">
            <div>
                <h2 style="margin:0;"><?= sanitize('Reminders'); ?></h2>
                <p class="muted" style="margin:4px 0 0;"><?= sanitize('Stay on top of missing documents and deadlines.'); ?></p>
            </div>
            <form method="post" action="/contractor/reminder_create.php" style="display:grid; gap:10px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label><?= sanitize('Title'); ?></label>
                    <input name="title" placeholder="<?= sanitize('e.g. Follow up on missing PAN'); ?>" required>
                </div>
                <div class="field">
                    <label><?= sanitize('Due at (Asia/Kolkata)'); ?></label>
                    <input type="datetime-local" name="dueAt" required>
                </div>
                <div class="field">
                    <label><?= sanitize('Pack (optional)'); ?></label>
                    <select name="packId">
                        <option value=""><?= sanitize('No pack link'); ?></option>
                        <?php foreach ($packOptions as $packId => $label): ?>
                            <option value="<?= sanitize($packId); ?>"><?= sanitize($label . ' (' . $packId . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="buttons">
                    <button class="btn" type="submit"><?= sanitize('Create reminder'); ?></button>
                </div>
            </form>
        </div>

        <div class="card" style="display:grid; gap:10px; margin-top:12px;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
                <h3 style="margin:0;"><?= sanitize('Your reminders'); ?></h3>
                <span class="pill"><?= sanitize(count($normalized) . ' total'); ?></span>
            </div>
            <?php if (!$normalized): ?>
                <p class="muted"><?= sanitize('No reminders yet. Create one above or attach a vault doc to clear missing items.'); ?></p>
            <?php else: ?>
                <div style="display:grid; gap:8px;">
                    <?php foreach ($normalized as $reminder): ?>
                        <?php
                        $packId = $reminder['packId'] ?? '';
                        $link = $packId !== '' ? '/contractor/pack_view.php?packId=' . urlencode($packId) : '';
                        if (($reminder['type'] ?? '') === 'missing_docs' && $link !== '') {
                            $link .= '#missing-docs';
                        }
                        ?>
                        <div style="border:1px solid var(--border); border-radius:10px; padding:10px; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                            <div>
                                <strong><?= sanitize($reminder['title'] ?? 'Reminder'); ?></strong>
                                <div class="muted" style="margin-top:4px;">
                                    <?= sanitize(($reminder['type'] ?? 'custom') . ' • ' . ($reminder['dueAt'] ?? 'No due date')); ?>
                                </div>
                                <?php if ($packId !== ''): ?>
                                    <div class="muted" style="margin-top:4px;"><?= sanitize('Pack: ' . $packId); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="buttons" style="gap:6px;">
                                <?php if ($link !== ''): ?>
                                    <a class="btn secondary" href="<?= sanitize($link); ?>"><?= sanitize('Open pack'); ?></a>
                                <?php endif; ?>
                                <?php if (($reminder['status'] ?? 'open') !== 'done'): ?>
                                    <form method="post" action="/contractor/reminder_mark_done.php">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="remId" value="<?= sanitize($reminder['remId'] ?? ''); ?>">
                                        <button class="btn" type="submit"><?= sanitize('Mark done'); ?></button>
                                    </form>
                                <?php else: ?>
                                    <span class="pill"><?= sanitize('Done'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    });
});
