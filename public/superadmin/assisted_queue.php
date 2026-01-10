<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = assisted_require_staff_access();
    $isEmployee = ($actor['type'] ?? '') === 'employee';

    $statusFilter = trim($_GET['status'] ?? '');
    $assignedFilter = trim($_GET['assignedTo'] ?? '');
    $yojFilter = trim($_GET['yojId'] ?? '');
    $offtdFilter = trim($_GET['offtdId'] ?? '');
    $from = trim($_GET['from'] ?? '');
    $to = trim($_GET['to'] ?? '');

    $records = assisted_tasks_index();
    $filtered = [];

    foreach ($records as $entry) {
        $entryStatus = $entry['status'] ?? '';
        $assignedTo = $entry['assignedTo']['userId'] ?? '';

        if ($isEmployee) {
            $isAssignedToActor = $assignedTo !== '' && $assignedTo === ($actor['empId'] ?? '');
            $isClaimable = $assignedTo === '' && in_array($entryStatus, ['queued', 'in_progress'], true);
            if (!$isAssignedToActor && !$isClaimable) {
                continue;
            }
        }

        if ($statusFilter !== '' && $entryStatus !== $statusFilter) {
            continue;
        }
        if ($assignedFilter !== '' && $assignedTo !== $assignedFilter) {
            continue;
        }
        if ($yojFilter !== '' && ($entry['contractor']['yojId'] ?? '') !== $yojFilter) {
            continue;
        }
        if ($offtdFilter !== '' && ($entry['tender']['offtdId'] ?? '') !== $offtdFilter) {
            continue;
        }
        if ($from !== '' || $to !== '') {
            $createdAt = $entry['createdAt'] ?? '';
            $createdTs = $createdAt !== '' ? strtotime($createdAt) : false;
            if ($from !== '') {
                $fromTs = strtotime($from . ' 00:00:00');
                if ($createdTs !== false && $createdTs < $fromTs) {
                    continue;
                }
            }
            if ($to !== '') {
                $toTs = strtotime($to . ' 23:59:59');
                if ($createdTs !== false && $createdTs > $toTs) {
                    continue;
                }
            }
        }

        $filtered[] = $entry;
    }

    $title = get_app_config()['appName'] . ' | Assisted Extraction Queue';

    render_layout($title, function () use ($filtered, $statusFilter, $assignedFilter, $yojFilter, $offtdFilter, $from, $to, $actor, $isEmployee) {
        ?>
        <div class="card" style="display:grid;gap:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Assisted Extraction Queue</h2>
                    <p class="muted" style="margin:4px 0 0;">Task workflow for tender extraction, assignment, and delivery.</p>
                </div>
                <span class="pill">Tasks: <?= sanitize((string)count($filtered)); ?></span>
            </div>
            <form method="get" action="" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;align-items:end;">
                <label class="field">
                    <span>Status</span>
                    <select name="status">
                        <option value="">All</option>
                        <?php foreach (['queued','in_progress','delivered','cancelled'] as $status): ?>
                            <option value="<?= sanitize($status); ?>" <?= $statusFilter === $status ? 'selected' : ''; ?>><?= sanitize(ucwords(str_replace('_',' ', $status))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span>Assigned To (User ID)</span>
                    <input name="assignedTo" value="<?= sanitize($assignedFilter); ?>" placeholder="EMP-...">
                </label>
                <label class="field">
                    <span>Contractor YOJ ID</span>
                    <input name="yojId" value="<?= sanitize($yojFilter); ?>" placeholder="YOJ-...">
                </label>
                <label class="field">
                    <span>Offline Tender ID</span>
                    <input name="offtdId" value="<?= sanitize($offtdFilter); ?>" placeholder="OFFTD-...">
                </label>
                <label class="field">
                    <span>From</span>
                    <input type="date" name="from" value="<?= sanitize($from); ?>">
                </label>
                <label class="field">
                    <span>To</span>
                    <input type="date" name="to" value="<?= sanitize($to); ?>">
                </label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="btn" type="submit">Filter</button>
                    <a class="btn secondary" href="/superadmin/assisted_queue.php">Reset</a>
                </div>
            </form>
        </div>

        <div class="card" style="margin-top:12px;">
            <?php if (!$filtered): ?>
                <p class="muted" style="margin:0;">No tasks match the current filters.</p>
            <?php else: ?>
                <div style="overflow:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Task ID</th>
                                <th>Status</th>
                                <th>Contractor</th>
                                <th>Tender</th>
                                <th>Assigned</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filtered as $row): ?>
                                <?php
                                $status = $row['status'] ?? 'queued';
                                $assignedId = $row['assignedTo']['userId'] ?? '';
                                $isAssignedToActor = $isEmployee && $assignedId !== '' && $assignedId === ($actor['empId'] ?? '');
                                $isClaimable = $assignedId === '' && in_array($status, ['queued', 'in_progress'], true);
                                ?>
                                <tr>
                                    <td><a href="/superadmin/assisted_task.php?taskId=<?= urlencode($row['taskId'] ?? ''); ?>"><?= sanitize($row['taskId'] ?? ''); ?></a></td>
                                    <td><span class="pill"><?= sanitize(ucwords(str_replace('_',' ', $status))); ?></span></td>
                                    <td>
                                        <div><?= sanitize($row['contractor']['name'] ?? ''); ?></div>
                                        <div class="muted" style="font-size:12px;"><?= sanitize($row['contractor']['yojId'] ?? ''); ?></div>
                                    </td>
                                    <td>
                                        <div><?= sanitize($row['tender']['title'] ?? ''); ?></div>
                                        <div class="muted" style="font-size:12px;"><?= sanitize($row['tender']['offtdId'] ?? ''); ?></div>
                                    </td>
                                    <td><?= sanitize($row['assignedTo']['name'] ?? 'Unassigned'); ?></td>
                                    <td class="muted" style="font-size:12px;"><?= sanitize($row['updatedAt'] ?? ''); ?></td>
                                    <td>
                                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                            <a class="btn secondary" href="/superadmin/assisted_task.php?taskId=<?= urlencode($row['taskId'] ?? ''); ?>">Open</a>
                                            <?php if ($isClaimable): ?>
                                                <form method="post" action="/superadmin/assisted_task_assign.php" style="margin:0;">
                                                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                                    <input type="hidden" name="taskId" value="<?= sanitize($row['taskId'] ?? ''); ?>">
                                                    <input type="hidden" name="action" value="claim">
                                                    <button class="btn" type="submit">Claim</button>
                                                </form>
                                            <?php elseif ($isAssignedToActor): ?>
                                                <span class="pill">Assigned to you</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    });
});
