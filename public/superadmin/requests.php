<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_superadmin_or_permission('requests_manage');
    $typeParam = trim((string)($_GET['type'] ?? 'templates'));
    $type = $typeParam === 'packs' ? 'pack' : 'template';
    $requests = request_list($type);
    $employees = [];
    foreach (staff_employee_index() as $entry) {
        $record = load_employee($entry['empId'] ?? '');
        if ($record && ($record['status'] ?? '') === 'active') {
            $employees[] = $record;
        }
    }

    $title = get_app_config()['appName'] . ' | Requests';
    render_layout($title, function () use ($requests, $employees, $type, $typeParam) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize('Requests Queue'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Handle contractor requests for templates and packs.'); ?></p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn secondary" href="/superadmin/requests.php?type=templates"><?= sanitize('Templates'); ?></a>
                    <a class="btn secondary" href="/superadmin/requests.php?type=packs"><?= sanitize('Packs'); ?></a>
                </div>
            </div>
            <?php if (!$requests): ?>
                <p class="muted"><?= sanitize('No requests found.'); ?></p>
            <?php endif; ?>
            <div style="display:grid;gap:10px;">
                <?php foreach ($requests as $req): ?>
                    <div style="border:1px solid var(--border);border-radius:12px;padding:12px;display:grid;gap:6px;background:var(--surface-2);">
                        <strong><?= sanitize($req['title'] ?? 'Request'); ?></strong>
                        <span class="muted"><?= sanitize('Status: ' . request_status_label((string)($req['status'] ?? 'new'))); ?></span>
                        <span class="muted"><?= sanitize('From: ' . ($req['from']['yojId'] ?? '')); ?></span>
                        <span class="muted"><?= sanitize('Notes: ' . ($req['notes'] ?? '')); ?></span>
                        <?php if (!empty($req['attachments'])): ?>
                            <div class="muted"><?= sanitize('Attachments:'); ?></div>
                            <ul style="margin:0;padding-left:18px;">
                                <?php foreach ($req['attachments'] as $att): ?>
                                    <li><?= sanitize($att['name'] ?? ($att['file'] ?? 'attachment')); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            <form method="post" action="/superadmin/requests/assign.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="type" value="<?= sanitize($type); ?>">
                                <input type="hidden" name="id" value="<?= sanitize($req['id'] ?? ''); ?>">
                                <select name="staffId" required>
                                    <option value="superadmin"><?= sanitize('Assign to superadmin'); ?></option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= sanitize($emp['empId'] ?? ''); ?>"><?= sanitize(($emp['displayName'] ?? $emp['username'] ?? '') . ' (' . ($emp['role'] ?? '') . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn secondary" type="submit"><?= sanitize('Assign'); ?></button>
                            </form>
                            <form method="post" action="/superadmin/requests/resolve.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="type" value="<?= sanitize($type); ?>">
                                <input type="hidden" name="id" value="<?= sanitize($req['id'] ?? ''); ?>">
                                <select name="status">
                                    <option value="in_progress"><?= sanitize('Mark In Progress'); ?></option>
                                    <option value="delivered"><?= sanitize('Mark Delivered'); ?></option>
                                    <option value="rejected"><?= sanitize('Reject'); ?></option>
                                </select>
                                <input type="text" name="linkedId" placeholder="<?= sanitize($type === 'template' ? 'Linked TPL ID' : 'Linked PKB ID'); ?>">
                                <button class="btn" type="submit"><?= sanitize('Update'); ?></button>
                            </form>
                        </div>
                        <span class="muted"><?= sanitize('Updated: ' . ($req['updatedAt'] ?? '')); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
