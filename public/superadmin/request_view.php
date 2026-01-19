<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_role('superadmin');
    $requestId = trim((string)($_GET['id'] ?? ''));

    if ($requestId === '') {
        render_error_page('Request not found.');
        return;
    }

    $request = load_request($requestId);
    if (!$request) {
        render_error_page('Request not found.');
        return;
    }

    $title = get_app_config()['appName'] . ' | Request';

    render_layout($title, function () use ($request) {
        $typeLabel = ($request['type'] ?? '') === 'pack' ? 'Pack Blueprint' : 'Template';
        ?>
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Request: <?= sanitize($typeLabel); ?></h2>
                    <p class="muted" style="margin:4px 0 0;">Status: <?= sanitize($request['status'] ?? 'new'); ?></p>
                </div>
                <a class="btn secondary" href="/superadmin/<?= ($request['type'] ?? '') === 'pack' ? 'packs.php' : 'templates.php'; ?>">Back</a>
            </div>
        </div>

        <div class="card" style="margin-top:12px; display:grid; gap:8px;">
            <h3 style="margin:0;"><?= sanitize($request['title'] ?? 'Request'); ?></h3>
            <p class="muted" style="margin:0;">ID: <?= sanitize($request['id'] ?? ''); ?> • Contractor: <?= sanitize($request['yojId'] ?? ''); ?></p>
            <?php if (!empty($request['notes'])): ?>
                <p style="margin:0; white-space:pre-wrap;"><?= sanitize($request['notes']); ?></p>
            <?php endif; ?>
            <?php if (!empty($request['tenderRef'])): ?>
                <div class="muted" style="font-size:13px;">
                    Tender: <?= sanitize($request['tenderRef']['offtdId'] ?? ''); ?> <?= sanitize($request['tenderRef']['tenderTitle'] ?? ''); ?>
                </div>
            <?php endif; ?>
            <div>
                <strong>Uploads</strong>
                <ul style="margin:6px 0 0 16px;">
                    <?php foreach (($request['uploads'] ?? []) as $upload): ?>
                        <li><?= sanitize($upload['name'] ?? 'tender.pdf'); ?> — <?= sanitize($upload['path'] ?? ''); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php if (!empty($request['delivered'])): ?>
                <div class="muted">Delivered: <?= sanitize(($request['delivered']['scope'] ?? '') . ' • ' . ($request['delivered']['entityId'] ?? '')); ?></div>
            <?php endif; ?>
        </div>

        <div class="card" style="margin-top:12px;">
            <form method="post" action="/superadmin/request_update.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="requestId" value="<?= sanitize($request['id'] ?? ''); ?>">
                <label class="field">
                    <span>Status</span>
                    <select name="status">
                        <?php $status = $request['status'] ?? 'new'; ?>
                        <option value="new" <?= $status === 'new' ? 'selected' : ''; ?>>New</option>
                        <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="delivered" <?= $status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </label>
                <button class="btn" type="submit">Update Status</button>
                <a class="btn secondary" href="/superadmin/<?= ($request['type'] ?? '') === 'pack' ? 'pack_edit.php?requestId=' . urlencode($request['id'] ?? '') : 'template_edit.php?requestId=' . urlencode($request['id'] ?? '') ?>">Deliver Now</a>
            </form>
        </div>
        <?php
    });
});
