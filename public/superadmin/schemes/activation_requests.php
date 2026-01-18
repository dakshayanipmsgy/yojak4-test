<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    require_role('superadmin');
    $requests = list_activation_requests('pending');

    render_layout('Scheme Activation Requests', function () use ($requests) {
        ?>
        <style>
            .table { width:100%; border-collapse:collapse; }
            .table th, .table td { padding:10px; text-align:left; border-bottom:1px solid var(--border); }
            .muted { color: var(--muted); }
        </style>
        <h1>Scheme Activation Requests</h1>
        <div class="card" style="padding:16px; margin-top:16px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Contractor</th>
                        <th>Scheme</th>
                        <th>Version</th>
                        <th>Requested At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$requests) { ?>
                    <tr><td colspan="6" class="muted">No pending requests.</td></tr>
                <?php } ?>
                <?php foreach ($requests as $request) { ?>
                    <tr>
                        <td><?= sanitize($request['requestId'] ?? ''); ?></td>
                        <td><?= sanitize($request['yojId'] ?? ''); ?></td>
                        <td><?= sanitize($request['schemeCode'] ?? ''); ?></td>
                        <td><?= sanitize($request['requestedVersion'] ?? ''); ?></td>
                        <td><?= sanitize($request['createdAt'] ?? ''); ?></td>
                        <td>
                            <form method="post" action="/superadmin/schemes/approve_activation.php" style="display:inline-block;">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="path" value="<?= sanitize($request['_path'] ?? ''); ?>">
                                <button class="btn" type="submit">Approve</button>
                            </form>
                            <form method="post" action="/superadmin/schemes/reject_activation.php" style="display:inline-block;">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="path" value="<?= sanitize($request['_path'] ?? ''); ?>">
                                <button class="btn secondary" type="submit">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
