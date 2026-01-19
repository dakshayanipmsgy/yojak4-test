<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('template_manager');
    $requestId = trim((string)($_GET['requestId'] ?? ''));
    if ($requestId === '') {
        render_error_page('Request ID is required.');
        return;
    }
    $request = load_template_request($requestId);
    if (!$request) {
        render_error_page('Request not found.');
        return;
    }

    $attachments = $request['attachments'] ?? [];
    $title = get_app_config()['appName'] . ' | Request ' . $requestId;
    render_layout($title, function () use ($request, $attachments) {
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div>
                <h2 style="margin:0;">Request <?= sanitize($request['requestId'] ?? ''); ?></h2>
                <p class="muted" style="margin:4px 0 0;">Contractor: <?= sanitize($request['yojId'] ?? ''); ?> • Type: <?= sanitize($request['type'] ?? 'template'); ?></p>
            </div>
            <div class="card" style="border:1px solid var(--border);background:var(--surface-2);">
                <h3 style="margin:0 0 8px 0;">Details</h3>
                <p class="muted" style="margin:0;white-space:pre-wrap;"><?= sanitize($request['notes'] ?? ''); ?></p>
                <p class="muted" style="margin:10px 0 0;">Status: <?= sanitize($request['status'] ?? 'new'); ?></p>
            </div>
            <?php if ($attachments): ?>
                <div class="card" style="border:1px solid var(--border);background:var(--surface-2);">
                    <h3 style="margin:0 0 8px 0;">Attachments</h3>
                    <ul style="margin:0;padding-left:18px;">
                        <?php foreach ($attachments as $file): ?>
                            <li>
                                <a href="/download.php?type=template_request&requestId=<?= sanitize($request['requestId'] ?? ''); ?>&file=<?= sanitize($file); ?>">
                                    <?= sanitize($file); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="card" style="border:1px solid var(--border);background:var(--surface-2);display:grid;gap:12px;">
                <h3 style="margin:0;">Actions</h3>
                <form method="post" action="/superadmin/template_request_assign.php" style="display:grid;gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="requestId" value="<?= sanitize($request['requestId'] ?? ''); ?>">
                    <label style="display:grid;gap:6px;">
                        <span class="muted">Assign To (employee ID or superadmin)</span>
                        <input class="input" type="text" name="assignedTo" value="<?= sanitize($request['assignedTo'] ?? ''); ?>" placeholder="EMP-XXXXXX or superadmin">
                    </label>
                    <button class="btn secondary" type="submit">Assign / Update</button>
                </form>
                <form method="post" action="/superadmin/template_request_mark_delivered.php" style="display:grid;gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="requestId" value="<?= sanitize($request['requestId'] ?? ''); ?>">
                    <label style="display:grid;gap:6px;">
                        <span class="muted">Created Template IDs (comma separated)</span>
                        <input class="input" type="text" name="createdTemplateIds" value="<?= sanitize(implode(',', $request['result']['createdTemplateIds'] ?? [])); ?>">
                    </label>
                    <label style="display:grid;gap:6px;">
                        <span class="muted">Created Pack Template IDs (comma separated)</span>
                        <input class="input" type="text" name="createdPackTemplateIds" value="<?= sanitize(implode(',', $request['result']['createdPackTemplateIds'] ?? [])); ?>">
                    </label>
                    <button class="btn" type="submit">Mark Delivered</button>
                </form>
                <form method="post" action="/superadmin/template_request_reject.php" style="display:grid;gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="requestId" value="<?= sanitize($request['requestId'] ?? ''); ?>">
                    <label style="display:grid;gap:6px;">
                        <span class="muted">Rejection Notes</span>
                        <textarea class="input" name="rejectionNotes" rows="3" placeholder="Reason for rejection"></textarea>
                    </label>
                    <button class="btn secondary" type="submit">Reject</button>
                </form>
            </div>
            <a class="btn secondary" href="/superadmin/template_requests.php">Back to Requests</a>
        </div>
        <?php
    });
});
