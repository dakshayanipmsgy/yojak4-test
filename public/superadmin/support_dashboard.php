<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    $index = support_load_index();

    $filters = [
        'status' => $_GET['status'] ?? '',
        'type' => $_GET['type'] ?? '',
        'severity' => $_GET['severity'] ?? '',
        'userType' => $_GET['userType'] ?? '',
    ];

    $filtered = array_values(array_filter($index, function ($row) use ($filters) {
        if ($filters['status'] && ($row['status'] ?? '') !== $filters['status']) {
            return false;
        }
        if ($filters['type'] && ($row['type'] ?? '') !== $filters['type']) {
            return false;
        }
        if ($filters['severity'] && ($row['severity'] ?? '') !== $filters['severity']) {
            return false;
        }
        if ($filters['userType'] && ($row['userType'] ?? '') !== $filters['userType']) {
            return false;
        }
        return true;
    }));

    $title = get_app_config()['appName'] . ' | Support Inbox';
    render_layout($title, function () use ($filtered, $filters) {
        ?>
        <div class="card">
            <h2 style="margin-bottom:6px;">Support Inbox</h2>
            <p class="muted" style="margin-top:0;">Review feedback, bugs, and updates from all users.</p>
            <form method="get" style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));align-items:end;">
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All</option>
                        <?php foreach (['open','in_review','resolved','closed'] as $status): ?>
                            <option value="<?= sanitize($status); ?>" <?= $filters['status'] === $status ? 'selected' : ''; ?>><?= sanitize(ucwords(str_replace('_',' ', $status))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Type</label>
                    <select name="type">
                        <option value="">All</option>
                        <?php foreach (['feedback','bug','other'] as $type): ?>
                            <option value="<?= sanitize($type); ?>" <?= $filters['type'] === $type ? 'selected' : ''; ?>><?= sanitize(ucfirst($type)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Severity</label>
                    <select name="severity">
                        <option value="">All</option>
                        <?php foreach (['low','medium','high'] as $sev): ?>
                            <option value="<?= sanitize($sev); ?>" <?= $filters['severity'] === $sev ? 'selected' : ''; ?>><?= sanitize(ucfirst($sev)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>User Type</label>
                    <select name="userType">
                        <option value="">All</option>
                        <?php foreach (['contractor','department','superadmin'] as $u): ?>
                            <option value="<?= sanitize($u); ?>" <?= $filters['userType'] === $u ? 'selected' : ''; ?>><?= sanitize(ucfirst($u)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn">Filter</button>
                </div>
            </form>
        </div>
        <div class="card">
            <h3 style="margin-top:0;">Tickets (<?= count($filtered); ?>)</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>User</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtered as $row): ?>
                            <tr>
                                <td><a href="/superadmin/support_ticket.php?ticketId=<?= urlencode($row['ticketId']); ?>"><?= sanitize($row['ticketId']); ?></a></td>
                                <td><?= sanitize($row['title'] ?? ''); ?></td>
                                <td><span class="tag" style="border-color:#1f6feb;"><?= sanitize(ucfirst($row['type'] ?? '')); ?></span></td>
                                <td><span class="tag" style="border-color:#f59f00;color:#fcd34d;"><?= sanitize(ucfirst($row['severity'] ?? '')); ?></span></td>
                                <td><span class="tag" style="border-color:#2ea043;color:#8ce99a;"><?= sanitize(ucwords(str_replace('_',' ', $row['status'] ?? ''))); ?></span></td>
                                <td><?= sanitize(($row['userType'] ?? '') . ' ' . ($row['fullUserId'] ?? '')); ?></td>
                                <td><?= sanitize($row['createdAt'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$filtered): ?>
                            <tr><td colspan="7">No tickets found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    });
});
