<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $employee = require_active_employee();
    if (!employee_has_permission($employee, 'can_process_assisted')) {
        redirect('/staff/dashboard.php');
    }
    ensure_assisted_v2_env();

    $status = trim((string)($_GET['status'] ?? ''));
    $allowed = ['pending', 'in_progress', 'delivered', 'rejected'];
    if ($status !== '' && !in_array($status, $allowed, true)) {
        $status = '';
    }

    $requests = assisted_v2_list_requests();
    $counts = array_fill_keys($allowed, 0);
    foreach ($requests as $req) {
        $state = $req['status'] ?? 'pending';
        if (isset($counts[$state])) {
            $counts[$state]++;
        }
    }
    $filtered = $status === '' ? $requests : array_values(array_filter($requests, static function (array $req) use ($status) {
        return ($req['status'] ?? '') === $status;
    }));

    $title = get_app_config()['appName'] . ' | Assisted Pack v2 Queue';
    render_layout($title, function () use ($filtered, $status, $counts) {
        ?>
        <div class="card" style="display:grid;gap:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize('Assisted Pack v2 Queue'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Process contractor requests using templates or external AI JSON paste.'); ?></p>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php foreach ($counts as $tab => $count): ?>
                    <a class="pill" href="/staff/assisted_v2/queue.php?status=<?= sanitize($tab); ?>" style="<?= $status === $tab ? 'border-color:#1f6feb;color:#9cc4ff;' : ''; ?>">
                        <?= sanitize(ucwords(str_replace('_',' ', $tab)) . ' (' . $count . ')'); ?>
                    </a>
                <?php endforeach; ?>
                <a class="pill" href="/staff/assisted_v2/queue.php" style="<?= $status === '' ? 'border-color:#1f6feb;color:#9cc4ff;' : ''; ?>"><?= sanitize('All'); ?></a>
            </div>
        </div>
        <div class="card">
            <h3 style="margin-top:0;"><?= sanitize('Requests (' . count($filtered) . ')'); ?></h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Request</th>
                            <th>Contractor</th>
                            <th>Tender</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Open</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$filtered): ?>
                            <tr><td colspan="6" class="muted"><?= sanitize('No requests found.'); ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($filtered as $req): ?>
                            <tr>
                                <td><?= sanitize($req['reqId'] ?? ''); ?></td>
                                <td><?= sanitize(($req['contractor']['yojId'] ?? '') . ' • ' . ($req['contractor']['name'] ?? '')); ?></td>
                                <td><?= sanitize($req['source']['tenderTitle'] ?? ($req['source']['offtdId'] ?? '')); ?></td>
                                <td><span class="pill"><?= sanitize(ucwords(str_replace('_',' ', $req['status'] ?? 'pending'))); ?></span></td>
                                <td><?= sanitize($req['createdAt'] ?? ''); ?></td>
                                <td><a class="btn secondary" href="/staff/assisted_v2/process.php?reqId=<?= sanitize(urlencode($req['reqId'] ?? '')); ?>"><?= sanitize('Open'); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    });
});
