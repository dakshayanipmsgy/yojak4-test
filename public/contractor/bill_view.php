<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $contractor = load_contractor($user['yojId']);
    if (!$contractor) {
        render_error_page('Contractor not found.');
        return;
    }

    $billId = trim($_GET['id'] ?? '');
    if ($billId === '') {
        render_error_page('Bill id missing.');
        return;
    }
    $bill = load_contractor_bill($contractor['yojId'], $billId);
    if (!$bill) {
        render_error_page('Bill not found.');
        return;
    }

    $title = get_app_config()['appName'] . ' | ' . ($bill['title'] ?? $bill['billId']);
    $statusColors = [
        'draft' => '#8b949e',
        'submitted' => '#f0ad4e',
        'approved' => '#2ea043',
        'paid' => '#58a6ff',
    ];
    $statuses = allowed_bill_statuses();
    $allowedMimes = allowed_vault_mimes();
    $formatDate = function (?string $value): string {
        if (!$value) {
            return '—';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return '—';
        }
        return date('d M Y, h:i A', $ts);
    };

    render_layout($title, function () use ($bill, $statusColors, $statuses, $allowedMimes, $formatDate) {
        $status = $bill['status'] ?? 'draft';
        $color = $statusColors[$status] ?? '#8b949e';
        $nextReminder = bill_next_reminder($bill);
        ?>
        <div class="card" style="display:grid; gap:12px;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div>
                    <div class="pill" style="border-color:#30363d;"><?= sanitize($bill['billId']); ?></div>
                    <h2 style="margin:6px 0 4px;"><?= sanitize($bill['title'] ?? ''); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Invoice & Payment Tracker'); ?></p>
                </div>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <span class="pill" style="border-color: <?= $color; ?>; color: <?= $color; ?>;">
                        <?= sanitize(ucfirst($status)); ?>
                    </span>
                    <?php if ($nextReminder): ?>
                        <span class="tag" style="border-color:#58a6ff; color:#58a6ff;"><?= sanitize('Next reminder: ' . $formatDate($nextReminder)); ?></span>
                    <?php else: ?>
                        <span class="tag"><?= sanitize('No upcoming reminder'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px;">
                <div class="card" style="background:#111820;">
                    <p class="muted" style="margin:0 0 4px;"><?= sanitize('Workorder'); ?></p>
                    <h3 style="margin:0;"><?= sanitize($bill['workorderRef'] ?? 'Not provided'); ?></h3>
                </div>
                <div class="card" style="background:#111820;">
                    <p class="muted" style="margin:0 0 4px;"><?= sanitize('Amount Text'); ?></p>
                    <h3 style="margin:0;"><?= sanitize($bill['amountText'] ?? 'Not provided'); ?></h3>
                </div>
                <div class="card" style="background:#111820;">
                    <p class="muted" style="margin:0 0 4px;"><?= sanitize('Updated'); ?></p>
                    <h3 style="margin:0;"><?= sanitize($formatDate($bill['updatedAt'] ?? '')); ?></h3>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:12px; margin-top:12px;">
            <div class="card">
                <h3 style="margin-top:0;"><?= sanitize('Status & Timeline'); ?></h3>
                <form method="post" action="/contractor/bill_update.php" style="display:grid; gap:10px; margin-bottom:12px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="billId" value="<?= sanitize($bill['billId']); ?>">
                    <input type="hidden" name="action" value="status">
                    <div class="field">
                        <label><?= sanitize('Change Status'); ?></label>
                        <select name="status" required>
                            <?php foreach ($statuses as $st): ?>
                                <option value="<?= sanitize($st); ?>" <?= $st === $status ? 'selected' : ''; ?>><?= sanitize(ucfirst($st)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <label class="pill" style="display:inline-flex; align-items:center; gap:6px; background:#0f1520;">
                        <input type="checkbox" name="confirmRollback" value="1">
                        <?= sanitize('Confirm if you are rolling back to an earlier status.'); ?>
                    </label>
                    <button class="btn" type="submit"><?= sanitize('Update Status'); ?></button>
                </form>
                <div style="display:grid; gap:8px;">
                    <?php foreach (array_reverse($bill['statusHistory'] ?? []) as $item): ?>
                        <div class="card" style="background:#111820;">
                            <div style="display:flex; justify-content:space-between; gap:8px;">
                                <div>
                                    <div class="pill" style="border-color:#30363d;"><?= sanitize(ucfirst($item['status'] ?? '')); ?></div>
                                    <p class="muted" style="margin:4px 0 0;"><?= sanitize($item['note'] ?? ''); ?></p>
                                </div>
                                <span class="muted"><?= sanitize($formatDate($item['changedAt'] ?? '')); ?></span>
                            </div>
                            <p class="muted" style="margin:4px 0 0;"><?= sanitize('Actor: ' . ($item['actor'] ?? '')); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top:0;"><?= sanitize('Bill Details'); ?></h3>
                <form method="post" action="/contractor/bill_update.php" style="display:grid; gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="billId" value="<?= sanitize($bill['billId']); ?>">
                    <input type="hidden" name="action" value="metadata">
                    <div class="field">
                        <label><?= sanitize('Title'); ?></label>
                        <input name="title" value="<?= sanitize($bill['title'] ?? ''); ?>" required maxlength="120">
                    </div>
                    <div class="field">
                        <label><?= sanitize('Workorder Reference'); ?> <span class="muted">(optional)</span></label>
                        <input name="workorderRef" value="<?= sanitize($bill['workorderRef'] ?? ''); ?>" maxlength="80">
                    </div>
                    <div class="field">
                        <label><?= sanitize('Amount Text'); ?> <span class="muted">(max 30 chars)</span></label>
                        <input name="amountText" value="<?= sanitize($bill['amountText'] ?? ''); ?>" maxlength="30">
                    </div>
                    <button class="btn" type="submit"><?= sanitize('Save Details'); ?></button>
                </form>
            </div>

            <div class="card">
                <h3 style="margin-top:0;"><?= sanitize('Attachments'); ?></h3>
                <p class="muted" style="margin-top:0;">PDF/JPG/PNG only. Files are stored securely.</p>
                <form method="post" action="/contractor/bill_upload.php" enctype="multipart/form-data" style="display:grid; gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="billId" value="<?= sanitize($bill['billId']); ?>">
                    <div class="field">
                        <label><?= sanitize('Upload attachment'); ?></label>
                        <input type="file" name="attachment" required accept="<?= sanitize(implode(',', array_keys($allowedMimes))); ?>">
                    </div>
                    <button class="btn secondary" type="submit"><?= sanitize('Upload'); ?></button>
                </form>
                <div style="display:grid; gap:8px; margin-top:12px;">
                    <?php if (empty($bill['attachments'])): ?>
                        <p class="muted"><?= sanitize('No attachments yet.'); ?></p>
                    <?php endif; ?>
                    <?php foreach ($bill['attachments'] as $att): ?>
                        <div class="card" style="background:#111820;">
                            <div style="display:flex; justify-content:space-between; gap:8px; flex-wrap:wrap; align-items:center;">
                                <div>
                                    <div class="pill" style="border-color:#30363d;"><?= sanitize($att['fileId'] ?? ''); ?></div>
                                    <p style="margin:4px 0 0;"><?= sanitize($att['originalName'] ?? basename($att['path'] ?? '')); ?></p>
                                    <p class="muted" style="margin:2px 0 0;"><?= sanitize(format_bytes((int)($att['sizeBytes'] ?? 0))); ?> • <?= sanitize($att['mime'] ?? ''); ?></p>
                                </div>
                                <a class="btn secondary" href="<?= sanitize($att['path'] ?? '#'); ?>" target="_blank" rel="noopener"><?= sanitize('Open'); ?></a>
                            </div>
                            <p class="muted" style="margin:4px 0 0;"><?= sanitize('Uploaded at ' . $formatDate($att['uploadedAt'] ?? '')); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top:0;"><?= sanitize('Reminders'); ?></h3>
                <form method="post" action="/contractor/bill_add_reminder.php" style="display:grid; gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="billId" value="<?= sanitize($bill['billId']); ?>">
                    <div class="field">
                        <label><?= sanitize('Reminder note'); ?></label>
                        <input name="note" maxlength="160" placeholder="<?= sanitize('e.g. Follow up after submission'); ?>" required>
                    </div>
                    <div class="field">
                        <label><?= sanitize('Tie to status milestone'); ?> <span class="muted">(optional)</span></label>
                        <select name="statusRef">
                            <option value=""><?= sanitize('None'); ?></option>
                            <?php foreach ($statuses as $st): ?>
                                <option value="<?= sanitize($st); ?>"><?= sanitize(ucfirst($st)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label><?= sanitize('Remind on'); ?></label>
                        <input type="datetime-local" name="remindAt" required>
                    </div>
                    <button class="btn" type="submit"><?= sanitize('Add Reminder'); ?></button>
                </form>
                <div style="display:grid; gap:8px; margin-top:12px;">
                    <?php if (empty($bill['reminders'])): ?>
                        <p class="muted"><?= sanitize('No reminders yet.'); ?></p>
                    <?php endif; ?>
                    <?php foreach (array_reverse($bill['reminders'] ?? []) as $rem): ?>
                        <div class="card" style="background:#111820;">
                            <div style="display:flex; justify-content:space-between; gap:8px; flex-wrap:wrap;">
                                <div>
                                    <div class="pill" style="border-color:#30363d;"><?= sanitize($rem['reminderId'] ?? ''); ?></div>
                                    <p style="margin:4px 0 0;"><?= sanitize($rem['note'] ?? ''); ?></p>
                                    <?php if (!empty($rem['statusRef'])): ?>
                                        <p class="muted" style="margin:2px 0 0;"><?= sanitize('For status: ' . ucfirst($rem['statusRef'])); ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="tag" style="border-color:#58a6ff; color:#58a6ff;"><?= sanitize($formatDate($rem['remindAt'] ?? '')); ?></span>
                            </div>
                            <p class="muted" style="margin:4px 0 0;"><?= sanitize('Created at ' . $formatDate($rem['createdAt'] ?? '')); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    });
});
