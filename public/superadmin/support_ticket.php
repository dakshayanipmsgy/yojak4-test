<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    $ticketId = $_GET['ticketId'] ?? '';
    $path = support_ticket_path($ticketId);
    if ($ticketId === '' || !file_exists($path)) {
        render_error_page('Ticket not found');
        return;
    }
    $ticket = readJson($path);
    $title = get_app_config()['appName'] . ' | Ticket ' . $ticketId;
    render_layout($title, function () use ($ticket) {
        ?>
        <div class="card">
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:6px;">Ticket <?= sanitize($ticket['ticketId'] ?? ''); ?></h2>
                    <p class="muted" style="margin:0;">Type: <?= sanitize(ucfirst($ticket['type'] ?? '')); ?> • Severity: <?= sanitize(ucfirst($ticket['severity'] ?? '')); ?></p>
                </div>
                <span class="pill" style="border-color:#2ea043;color:#8ce99a;">Status: <?= sanitize(ucwords(str_replace('_',' ', $ticket['status'] ?? ''))); ?></span>
            </div>
            <p style="margin-top:10px;white-space:pre-wrap;">Title: <?= sanitize($ticket['title'] ?? ''); ?></p>
            <div class="card" style="background:var(--surface-2);margin-top:12px;border:1px solid var(--border);">
                <h3 style="margin-top:0;">Message</h3>
                <p style="white-space:pre-wrap;"><?= nl2br(sanitize($ticket['message'] ?? '')); ?></p>
            </div>
            <div style="display:grid;gap:8px;margin-top:12px;">
                <div class="pill">User: <?= sanitize(($ticket['user']['userType'] ?? '') . ' ' . ($ticket['user']['fullUserId'] ?? '')); ?></div>
                <div class="pill">Created: <?= sanitize($ticket['createdAt'] ?? ''); ?></div>
                <div class="pill">Updated: <?= sanitize($ticket['updatedAt'] ?? ''); ?></div>
                <?php if (!empty($ticket['closedAt'])): ?>
                    <div class="pill">Closed: <?= sanitize($ticket['closedAt']); ?></div>
                <?php endif; ?>
            </div>
            <div class="card" style="background:var(--surface-2);margin-top:12px;border:1px solid var(--border);">
                <h3 style="margin-top:0;">Context</h3>
                <p class="muted" style="margin:0;">Page: <?= sanitize($ticket['pageContext']['url'] ?? ''); ?></p>
                <p class="muted" style="margin:0;">Referrer: <?= sanitize($ticket['pageContext']['referrer'] ?? ''); ?></p>
                <p class="muted" style="margin:0;">UA Hash: <?= sanitize($ticket['pageContext']['uaHash'] ?? ''); ?> • IP: <?= sanitize($ticket['pageContext']['ipMasked'] ?? ''); ?></p>
            </div>
            <?php if (!empty($ticket['attachments'])): ?>
                <div class="card" style="background:var(--surface-2);margin-top:12px;border:1px solid var(--border);">
                    <h3 style="margin-top:0;">Attachments</h3>
                    <ul>
                        <?php foreach ($ticket['attachments'] as $file): ?>
                            <li>
                                <a href="/superadmin/support_download.php?ticketId=<?= urlencode($ticket['ticketId']); ?>&file=<?= urlencode($file['name'] ?? ''); ?>"><?= sanitize($file['name'] ?? ''); ?></a>
                                <span class="muted">(<?= sanitize((string)($file['mime'] ?? '')); ?>, <?= sanitize((string)($file['size'] ?? '')); ?> bytes)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="card" style="background:var(--surface-2);margin-top:12px;border:1px solid var(--border);">
                <h3 style="margin-top:0;">Admin Notes</h3>
                <?php if (!empty($ticket['adminNotes'])): ?>
                    <ul>
                        <?php foreach ($ticket['adminNotes'] as $note): ?>
                            <li><strong><?= sanitize($note['at'] ?? ''); ?></strong>: <?= sanitize($note['note'] ?? ''); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted">No notes yet.</p>
                <?php endif; ?>
                <form method="post" action="/superadmin/support_update.php" style="display:grid;gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="ticketId" value="<?= sanitize($ticket['ticketId'] ?? ''); ?>">
                    <div class="field">
                        <label>Status</label>
                        <select name="status">
                            <?php foreach (['open','in_review','resolved','closed'] as $status): ?>
                                <option value="<?= sanitize($status); ?>" <?= ($ticket['status'] ?? '') === $status ? 'selected' : ''; ?>><?= sanitize(ucwords(str_replace('_',' ', $status))); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Add Note</label>
                        <textarea name="note" rows="3" placeholder="Internal note"></textarea>
                    </div>
                    <button type="submit" class="btn">Update</button>
                </form>
            </div>
        </div>
        <?php
    });
});
