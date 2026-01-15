<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $deptId = $user['deptId'] ?? '';
    ensure_department_env($deptId);
    require_department_permission($user, 'docs_workflow');

    $docId = trim($_GET['id'] ?? '');
    if ($docId === '') {
        render_error_page('Document not found.');
        return;
    }
    $doc = load_department_doc($deptId, $docId);
    if (!$doc) {
        render_error_page('Document not found.');
        return;
    }

    $title = get_app_config()['appName'] . ' | ' . ($doc['title'] ?? 'Document');
    render_layout($title, function () use ($doc) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize($doc['title'] ?? 'Document'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Status: ' . ($doc['status'] ?? '')); ?></p>
                </div>
                <a class="btn secondary" href="/department/docs_inbox.php"><?= sanitize('Back'); ?></a>
            </div>
            <div style="margin-top:12px;display:grid;gap:10px;">
                <div class="pill"><?= sanitize('From: ' . ($doc['fromUser'] ?? '')); ?></div>
                <div class="pill"><?= sanitize('To: ' . ($doc['toUser'] ?? '')); ?></div>
                <?php if (!empty($doc['notesGreen'])): ?>
                    <div class="card" style="background:var(--surface-2);">
                        <strong><?= sanitize('Notes'); ?></strong>
                        <p class="muted"><?= nl2br(sanitize($doc['notesGreen'] ?? '')); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <div style="margin-top:12px;display:flex;gap:12px;flex-wrap:wrap;">
                <form method="post" action="/department/doc_forward.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="docId" value="<?= sanitize($doc['docId'] ?? ''); ?>">
                    <div class="field">
                        <label for="toUser"><?= sanitize('Forward To'); ?></label>
                        <input id="toUser" name="toUser" placeholder="user.role.dept">
                    </div>
                    <div class="field">
                        <label for="notesGreen"><?= sanitize('Notes'); ?></label>
                        <textarea id="notesGreen" name="notesGreen" rows="3" style="width:260px;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:10px;padding:10px;"></textarea>
                    </div>
                    <button class="btn" type="submit"><?= sanitize('Forward'); ?></button>
                </form>
                <form method="post" action="/department/doc_sign.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="docId" value="<?= sanitize($doc['docId'] ?? ''); ?>">
                    <button class="btn secondary" type="submit"><?= sanitize('Mark Signed'); ?></button>
                </form>
                <form method="post" action="/department/doc_archive.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="docId" value="<?= sanitize($doc['docId'] ?? ''); ?>">
                    <button class="btn danger" type="submit"><?= sanitize('Archive'); ?></button>
                </form>
            </div>
            <h3 style="margin-top:16px;"><?= sanitize('Audit Trail'); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('At'); ?></th>
                        <th><?= sanitize('By'); ?></th>
                        <th><?= sanitize('Action'); ?></th>
                        <th><?= sanitize('Meta'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($doc['auditTrail'])): ?>
                        <tr><td colspan="4" class="muted"><?= sanitize('No audit entries.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($doc['auditTrail'] as $entry): ?>
                            <tr>
                                <td><?= sanitize($entry['at'] ?? ''); ?></td>
                                <td><?= sanitize($entry['by'] ?? ''); ?></td>
                                <td><?= sanitize($entry['action'] ?? ''); ?></td>
                                <td><?= sanitize(json_encode($entry['meta'] ?? [])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
