<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = assisted_tasks_require_staff();
    ensure_assisted_tasks_env();

    $filters = [
        'status' => trim($_GET['status'] ?? ''),
        'assignedTo' => trim($_GET['assignedTo'] ?? ''),
        'dateFrom' => trim($_GET['dateFrom'] ?? ''),
        'dateTo' => trim($_GET['dateTo'] ?? ''),
        'search' => trim($_GET['search'] ?? ''),
    ];

    $index = assisted_tasks_index();
    $rows = $index['tasks'] ?? [];
    $enriched = [];
    foreach ($rows as $row) {
        $task = assisted_tasks_load_task($row['taskId'] ?? '');
        $row['tenderTitle'] = $task['extractForm']['tenderTitle'] ?? ($task['extractForm']['tenderNumber'] ?? '');
        $row['status'] = $task['status'] ?? ($row['status'] ?? 'requested');
        $row['assignedTo'] = $task['assignedTo'] ?? ($row['assignedTo'] ?? null);
        $row['createdAt'] = $task['createdAt'] ?? ($row['createdAt'] ?? '');
        $row['lastUpdatedAt'] = $task['lastUpdatedAt'] ?? ($row['lastUpdatedAt'] ?? '');
        $enriched[] = $row;
    }

    $filtered = array_values(array_filter($enriched, function ($row) use ($filters) {
        if ($filters['status'] !== '' && ($row['status'] ?? '') !== $filters['status']) {
            return false;
        }
        if ($filters['assignedTo'] !== '' && (string)($row['assignedTo'] ?? '') !== $filters['assignedTo']) {
            return false;
        }
        if ($filters['dateFrom'] !== '') {
            $created = strtotime((string)($row['createdAt'] ?? ''));
            $fromTs = strtotime($filters['dateFrom'] . ' 00:00:00');
            if ($created !== false && $fromTs !== false && $created < $fromTs) {
                return false;
            }
        }
        if ($filters['dateTo'] !== '') {
            $created = strtotime((string)($row['createdAt'] ?? ''));
            $toTs = strtotime($filters['dateTo'] . ' 23:59:59');
            if ($created !== false && $toTs !== false && $created > $toTs) {
                return false;
            }
        }
        if ($filters['search'] !== '') {
            $needle = mb_strtolower($filters['search']);
            $hay = mb_strtolower(($row['yojId'] ?? '') . ' ' . ($row['offtdId'] ?? '') . ' ' . ($row['tenderTitle'] ?? ''));
            if (!str_contains($hay, $needle)) {
                return false;
            }
        }
        return true;
    }));

    usort($filtered, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));

    $title = get_app_config()['appName'] . ' | Assisted Extraction Queue';
    render_layout($title, function () use ($filtered, $filters, $actor) {
        ?>
        <div class="card" style="display:grid;gap:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Assisted Extraction Tasks</h2>
                    <p class="muted" style="margin:4px 0 0;">Task queue for offline tender assisted extraction (v2).</p>
                </div>
                <span class="pill"><?= sanitize('Actor: ' . assisted_tasks_actor_label($actor)); ?></span>
            </div>
            <form method="get" style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));align-items:end;">
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All</option>
                        <?php foreach (['requested','in_progress','delivered','closed'] as $status): ?>
                            <option value="<?= sanitize($status); ?>" <?= $filters['status'] === $status ? 'selected' : ''; ?>><?= sanitize(ucwords(str_replace('_',' ', $status))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Assigned To</label>
                    <input name="assignedTo" value="<?= sanitize($filters['assignedTo']); ?>" placeholder="EMP- / superadmin">
                </div>
                <div class="field">
                    <label>Date From</label>
                    <input type="date" name="dateFrom" value="<?= sanitize($filters['dateFrom']); ?>">
                </div>
                <div class="field">
                    <label>Date To</label>
                    <input type="date" name="dateTo" value="<?= sanitize($filters['dateTo']); ?>">
                </div>
                <div class="field">
                    <label>Search</label>
                    <input name="search" value="<?= sanitize($filters['search']); ?>" placeholder="YOJ ID / OFFTD / title">
                </div>
                <div>
                    <button class="btn" type="submit">Filter</button>
                </div>
            </form>
        </div>

        <div class="card" style="margin-top:12px;">
            <h3 style="margin-top:0;">Tasks (<?= count($filtered); ?>)</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Task ID</th>
                            <th>Contractor</th>
                            <th>Tender</th>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$filtered): ?>
                            <tr><td colspan="7" class="muted">No tasks found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($filtered as $row): ?>
                            <tr>
                                <td><a href="/superadmin/assisted_task_edit.php?taskId=<?= urlencode($row['taskId'] ?? ''); ?>"><?= sanitize($row['taskId'] ?? ''); ?></a></td>
                                <td><?= sanitize($row['yojId'] ?? ''); ?></td>
                                <td>
                                    <div><?= sanitize($row['offtdId'] ?? ''); ?></div>
                                    <div class="muted" style="font-size:12px;"><?= sanitize($row['tenderTitle'] ?? ''); ?></div>
                                </td>
                                <td><span class="tag" style="border-color:#1f6feb;"><?= sanitize(ucwords(str_replace('_',' ', $row['status'] ?? ''))); ?></span></td>
                                <td><?= sanitize($row['assignedTo'] ?? ''); ?></td>
                                <td><?= sanitize($row['createdAt'] ?? ''); ?></td>
                                <td>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                        <a class="btn secondary" href="/superadmin/assisted_task_edit.php?taskId=<?= urlencode($row['taskId'] ?? ''); ?>">Open</a>
                                        <form method="post" action="/superadmin/assisted_task_save.php" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                            <input type="hidden" name="taskId" value="<?= sanitize($row['taskId'] ?? ''); ?>">
                                            <input type="hidden" name="action" value="assign">
                                            <button class="btn" type="submit">Assign to me</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    });
});
