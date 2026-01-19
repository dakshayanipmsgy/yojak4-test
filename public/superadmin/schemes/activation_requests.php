<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    require_role('superadmin');
    $statusFilter = strtolower(trim((string)($_GET['status'] ?? 'pending')));
    $schemeFilter = strtoupper(trim((string)($_GET['scheme'] ?? '')));
    $query = trim((string)($_GET['q'] ?? ''));

    $allRequests = list_activation_requests();
    $schemeOptions = [];
    foreach ($allRequests as $request) {
        $code = strtoupper((string)($request['schemeCode'] ?? ''));
        if ($code !== '') {
            $schemeOptions[$code] = true;
        }
    }
    $schemeOptions = array_keys($schemeOptions);
    sort($schemeOptions);

    $requests = array_values(array_filter($allRequests, function (array $request) use ($statusFilter, $schemeFilter, $query): bool {
        $status = strtolower((string)($request['status'] ?? 'pending'));
        if ($statusFilter !== '' && $statusFilter !== 'all' && $status !== $statusFilter) {
            return false;
        }
        $schemeCode = strtoupper((string)($request['schemeCode'] ?? ''));
        if ($schemeFilter !== '' && $schemeCode !== $schemeFilter) {
            return false;
        }
        if ($query !== '') {
            $needle = strtolower($query);
            $requestId = strtolower((string)($request['requestId'] ?? ''));
            $yojId = strtolower((string)($request['yojId'] ?? ''));
            if (!str_contains($requestId, $needle) && !str_contains($yojId, $needle)) {
                return false;
            }
        }
        return true;
    }));

    render_layout('Scheme Activation Requests', function () use ($requests, $statusFilter, $schemeFilter, $query, $schemeOptions) {
        $formatDate = function (?string $value): string {
            if (!$value) {
                return '';
            }
            try {
                return (new DateTimeImmutable($value, new DateTimeZone('Asia/Kolkata')))->format('d M Y, H:i');
            } catch (Throwable $e) {
                return $value;
            }
        };
        ?>
        <style>
            .page-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
            .filters { display:grid; gap:12px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-top:12px; }
            .filters label { font-size:12px; color: var(--muted); display:block; margin-bottom:6px; }
            .filters input, .filters select {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid var(--border);
                border-radius: 10px;
                background: #fff;
                color: var(--text);
            }
            .table-wrap { width:100%; overflow-x:auto; }
            .table { width:100%; border-collapse:collapse; }
            .table th, .table td { padding:10px; text-align:left; border-bottom:1px solid var(--border); }
            .table th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); }
            .status-pill {
                display:inline-flex;
                align-items:center;
                padding: 4px 10px;
                border-radius:999px;
                font-size:12px;
                font-weight:600;
                border:1px solid transparent;
                text-transform: capitalize;
            }
            .status-pending { background:#fff7ed; color:#9a3412; border-color:#fdba74; }
            .status-approved { background:#ecfdf3; color:#166534; border-color:#86efac; }
            .status-rejected { background:#fef2f2; color:#991b1b; border-color:#fecaca; }
            .muted { color: var(--muted); }
            .action-stack { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
            .action-stack input[type="text"] { min-width: 160px; }
        </style>
        <div class="page-header">
            <div>
                <h1>Scheme Activation Requests</h1>
                <p class="muted" style="margin:0;">Review activation requests, filter by status or scheme, and approve pending items.</p>
            </div>
            <a class="btn secondary" href="/superadmin/dashboard.php">Back to Dashboard</a>
        </div>
        <div class="card" style="padding:16px; margin-top:16px;">
            <form method="get">
                <div class="filters">
                    <div>
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <?php
                            $statusOptions = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'];
                            foreach ($statusOptions as $value => $label) {
                                $selected = $statusFilter === $value || ($statusFilter === '' && $value === 'pending') ? 'selected' : '';
                                ?>
                                <option value="<?= sanitize($value); ?>" <?= $selected; ?>><?= sanitize($label); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div>
                        <label for="scheme">Scheme</label>
                        <select id="scheme" name="scheme">
                            <option value="">All Schemes</option>
                            <?php foreach ($schemeOptions as $code) { ?>
                                <option value="<?= sanitize($code); ?>" <?= $schemeFilter === $code ? 'selected' : ''; ?>><?= sanitize($code); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div>
                        <label for="q">Search</label>
                        <input id="q" type="text" name="q" placeholder="Search by YOJ ID or Request ID" value="<?= sanitize($query); ?>">
                    </div>
                    <div style="display:flex;align-items:flex-end;gap:8px;">
                        <button class="btn" type="submit">Apply Filters</button>
                        <a class="btn secondary" href="/superadmin/schemes/activation_requests.php">Clear</a>
                    </div>
                </div>
            </form>
            <div class="table-wrap" style="margin-top:16px;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Contractor (YOJ)</th>
                            <th>Scheme</th>
                            <th>Requested Version</th>
                            <th>Status</th>
                            <th>Created At (Asia/Kolkata)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$requests) { ?>
                        <tr><td colspan="7" class="muted">No activation requests found for the selected filters.</td></tr>
                    <?php } ?>
                    <?php foreach ($requests as $request) {
                        $status = strtolower((string)($request['status'] ?? 'pending'));
                        $statusClass = match ($status) {
                            'approved' => 'status-approved',
                            'rejected' => 'status-rejected',
                            default => 'status-pending',
                        };
                        ?>
                        <tr>
                            <td><?= sanitize($request['requestId'] ?? ''); ?></td>
                            <td><?= sanitize($request['yojId'] ?? ''); ?></td>
                            <td><?= sanitize($request['schemeCode'] ?? ''); ?></td>
                            <td><?= sanitize($request['requestedVersion'] ?? ''); ?></td>
                            <td><span class="status-pill <?= sanitize($statusClass); ?>"><?= sanitize($status); ?></span></td>
                            <td><?= sanitize($formatDate($request['createdAt'] ?? '')); ?></td>
                            <td>
                                <?php if ($status === 'pending') { ?>
                                    <div class="action-stack">
                                        <form method="post" action="/superadmin/schemes/approve_activation.php">
                                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                            <input type="hidden" name="path" value="<?= sanitize($request['_path'] ?? ''); ?>">
                                            <button class="btn" type="submit">Approve</button>
                                        </form>
                                        <form method="post" action="/superadmin/schemes/reject_activation.php">
                                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                            <input type="hidden" name="path" value="<?= sanitize($request['_path'] ?? ''); ?>">
                                            <input type="text" name="notes" placeholder="Optional notes">
                                            <button class="btn secondary" type="submit">Reject</button>
                                        </form>
                                    </div>
                                <?php } else { ?>
                                    <span class="muted">No actions</span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    });
});
