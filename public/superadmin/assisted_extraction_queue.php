<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = assisted_staff_actor();
    $index = assisted_extraction_index();

    $filters = [
        'status' => trim($_GET['status'] ?? ''),
        'assignedTo' => trim($_GET['assignedTo'] ?? ''),
    ];

    $filtered = array_values(array_filter($index, function ($row) use ($filters) {
        if ($filters['status'] && ($row['status'] ?? '') !== $filters['status']) {
            return false;
        }
        if ($filters['assignedTo'] !== '' && (string)($row['assignedTo'] ?? '') !== $filters['assignedTo']) {
            return false;
        }
        return true;
    }));

    usort($filtered, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));

    $title = get_app_config()['appName'] . ' | Assisted Extraction Queue';
    render_layout($title, function () use ($filtered, $filters) {
        ?>
        <div class="card" style="display:grid;gap:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Assisted Extraction</h2>
                    <p class="muted" style="margin:4px 0 0;">Queue of contractor requests for manual/AI-assisted checklist preparation.</p>
                </div>
            </div>
            <form method="get" style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));align-items:end;">
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
                <div>
                    <button class="btn" type="submit">Filter</button>
                </div>
            </form>
        </div>
        <div class="card">
            <h3 style="margin-top:0;">Requests (<?= count($filtered); ?>)</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Contractor</th>
                            <th>Tender</th>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$filtered): ?>
                            <tr><td colspan="6" class="muted">No requests found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($filtered as $row): ?>
                            <tr>
                                <td><a href="/superadmin/assisted_extraction_view.php?reqId=<?= urlencode($row['reqId'] ?? ''); ?>"><?= sanitize($row['reqId'] ?? ''); ?></a></td>
                                <td><?= sanitize($row['yojId'] ?? ''); ?></td>
                                <td><?= sanitize($row['offtdId'] ?? ''); ?></td>
                                <td><span class="tag" style="border-color:#1f6feb;"><?= sanitize(ucwords(str_replace('_',' ', $row['status'] ?? ''))); ?></span></td>
                                <td><?= sanitize($row['assignedTo'] ?? ''); ?></td>
                                <td><?= sanitize($row['createdAt'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    });
});
