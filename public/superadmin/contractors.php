<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_role('superadmin');
    $tab = $_GET['tab'] ?? 'pending';
    $title = get_app_config()['appName'] . ' | Contractors';

    $pending = list_pending_contractors();
    $approved = contractors_index();
    $rejected = list_rejected_contractors();

    render_layout($title, function () use ($tab, $pending, $approved, $rejected) {
        ?>
        <div class="card">
            <h2><?= sanitize('Contractor Approvals'); ?></h2>
            <p class="muted"><?= sanitize('Review, approve, or reject contractor signups.'); ?></p>
            <div class="buttons">
                <a class="btn <?= $tab === 'pending' ? '' : 'secondary'; ?>" href="/superadmin/contractors.php?tab=pending"><?= sanitize('Pending'); ?> (<?= count($pending); ?>)</a>
                <a class="btn <?= $tab === 'approved' ? '' : 'secondary'; ?>" href="/superadmin/contractors.php?tab=approved"><?= sanitize('Approved'); ?> (<?= count($approved); ?>)</a>
                <a class="btn <?= $tab === 'rejected' ? '' : 'secondary'; ?>" href="/superadmin/contractors.php?tab=rejected"><?= sanitize('Rejected'); ?> (<?= count($rejected); ?>)</a>
            </div>
            <?php if ($tab === 'pending'): ?>
                <table>
                    <thead>
                        <tr>
                            <th><?= sanitize('Signup ID'); ?></th>
                            <th><?= sanitize('Mobile'); ?></th>
                            <th><?= sanitize('Name'); ?></th>
                            <th><?= sanitize('Created'); ?></th>
                            <th><?= sanitize('Actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$pending): ?>
                            <tr><td colspan="5"><?= sanitize('No pending signups.'); ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($pending as $item): ?>
                            <tr>
                                <td><?= sanitize($item['signupId']); ?></td>
                                <td><?= sanitize($item['mobile']); ?></td>
                                <td><?= sanitize($item['name'] ?? ''); ?></td>
                                <td><?= sanitize($item['createdAt'] ?? ''); ?></td>
                                <td style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <form method="post" action="/superadmin/contractor_approve.php">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="signupId" value="<?= sanitize($item['signupId']); ?>">
                                        <button class="btn" type="submit">Approve</button>
                                    </form>
                                    <form method="post" action="/superadmin/contractor_reject.php">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="signupId" value="<?= sanitize($item['signupId']); ?>">
                                        <input type="hidden" name="reason" value="Rejected by superadmin">
                                        <button class="btn danger" type="submit">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($tab === 'approved'): ?>
                <table>
                    <thead>
                        <tr>
                            <th><?= sanitize('YOJ ID'); ?></th>
                            <th><?= sanitize('Mobile'); ?></th>
                            <th><?= sanitize('Name'); ?></th>
                            <th><?= sanitize('Status'); ?></th>
                            <th><?= sanitize('Approved'); ?></th>
                            <th><?= sanitize('View'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$approved): ?>
                            <tr><td colspan="6"><?= sanitize('No approved contractors.'); ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($approved as $item): ?>
                            <tr>
                                <td><?= sanitize($item['yojId']); ?></td>
                                <td><?= sanitize($item['mobile']); ?></td>
                                <td><?= sanitize($item['name'] ?? ''); ?></td>
                                <td><?= sanitize($item['status'] ?? ''); ?></td>
                                <td><?= sanitize($item['approvedAt'] ?? ''); ?></td>
                                <td><a class="btn secondary" href="/superadmin/contractor_view.php?yojId=<?= urlencode($item['yojId']); ?>">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th><?= sanitize('Signup ID'); ?></th>
                            <th><?= sanitize('Mobile'); ?></th>
                            <th><?= sanitize('Name'); ?></th>
                            <th><?= sanitize('Rejected At'); ?></th>
                            <th><?= sanitize('Reason'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rejected): ?>
                            <tr><td colspan="5"><?= sanitize('No rejected signups.'); ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($rejected as $item): ?>
                            <tr>
                                <td><?= sanitize($item['signupId']); ?></td>
                                <td><?= sanitize($item['mobile']); ?></td>
                                <td><?= sanitize($item['name'] ?? ''); ?></td>
                                <td><?= sanitize($item['rejectedAt'] ?? ''); ?></td>
                                <td><?= sanitize($item['reason'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    });
});
