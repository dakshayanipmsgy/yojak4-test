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

    $bills = contractor_bills_index($contractor['yojId']);
    $detailed = [];
    foreach ($bills as $entry) {
        $bill = load_contractor_bill($contractor['yojId'], $entry['billId']);
        if ($bill) {
            $detailed[] = $bill;
        }
    }

    $title = get_app_config()['appName'] . ' | Bills';
    $statusColors = [
        'draft' => 'var(--muted)',
        'submitted' => '#f0ad4e',
        'approved' => '#2ea043',
        'paid' => '#58a6ff',
    ];
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

    render_layout($title, function () use ($detailed, $statusColors, $formatDate) {
        ?>
        <div class="card">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize('Bills & Payments'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Track invoices, status, attachments, and reminders.'); ?></p>
                </div>
                <a class="btn" href="/contractor/bill_create.php"><?= sanitize('Create Bill'); ?></a>
            </div>
        </div>
        <div class="card" style="margin-top:12px;">
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('Bill'); ?></th>
                        <th><?= sanitize('Title'); ?></th>
                        <th><?= sanitize('Status'); ?></th>
                        <th><?= sanitize('Workorder Ref'); ?></th>
                        <th><?= sanitize('Amount Text'); ?></th>
                        <th><?= sanitize('Next Reminder'); ?></th>
                        <th><?= sanitize('Updated'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$detailed): ?>
                        <tr>
                            <td colspan="7" class="muted"><?= sanitize('No bills yet. Create your first one.'); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($detailed as $bill): ?>
                        <?php
                        $status = $bill['status'] ?? 'draft';
                        $color = $statusColors[$status] ?? 'var(--muted)';
                        $nextReminder = bill_next_reminder($bill);
                        ?>
                        <tr>
                            <td>
                                <a href="/contractor/bill_view.php?id=<?= sanitize($bill['billId']); ?>" class="pill" style="border-color:var(--border);">
                                    <?= sanitize($bill['billId']); ?>
                                </a>
                            </td>
                            <td><?= sanitize($bill['title'] ?? ''); ?></td>
                            <td>
                                <span class="pill" style="border-color: <?= $color; ?>; color: <?= $color; ?>;">
                                    <?= sanitize(ucfirst($status)); ?>
                                </span>
                            </td>
                            <td><?= sanitize($bill['workorderRef'] ?? ''); ?></td>
                            <td><?= sanitize($bill['amountText'] ?? ''); ?></td>
                            <td>
                                <?php if ($nextReminder): ?>
                                    <span class="tag" style="border-color: #58a6ff; color:#58a6ff;"><?= sanitize($formatDate($nextReminder)); ?></span>
                                <?php else: ?>
                                    <span class="muted"><?= sanitize('None'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="muted"><?= sanitize($formatDate($bill['updatedAt'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
