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

    $docs = array_filter(list_department_docs($deptId), function ($doc) {
        return in_array($doc['status'] ?? '', ['outbox', 'signed', 'archived'], true);
    });
    $title = get_app_config()['appName'] . ' | Docs Outbox';
    render_layout($title, function () use ($docs) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Docs Outbox'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Create and forward documents.'); ?></p>
                </div>
            </div>
            <form method="post" action="/department/doc_forward.php" style="margin-top:12px;display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="title"><?= sanitize('Title'); ?></label>
                    <input id="title" name="title" required>
                </div>
                <div class="field">
                    <label for="toUser"><?= sanitize('To User (optional)'); ?></label>
                    <input id="toUser" name="toUser" placeholder="user.role.dept">
                </div>
                <div class="field">
                    <label for="notesGreen"><?= sanitize('Notes (optional)'); ?></label>
                    <textarea id="notesGreen" name="notesGreen" rows="3" style="width:100%;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:10px;padding:10px;"></textarea>
                </div>
                <button class="btn" type="submit"><?= sanitize('Create & Forward'); ?></button>
            </form>

            <h3 style="margin-top:16px;"><?= sanitize('Sent Items'); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('Title'); ?></th>
                        <th><?= sanitize('Status'); ?></th>
                        <th><?= sanitize('To'); ?></th>
                        <th><?= sanitize('Updated'); ?></th>
                        <th><?= sanitize('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$docs): ?>
                        <tr><td colspan="5" class="muted"><?= sanitize('No documents.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($docs as $doc): ?>
                            <tr>
                                <td><?= sanitize($doc['title'] ?? ''); ?></td>
                                <td><span class="tag"><?= sanitize($doc['status'] ?? ''); ?></span></td>
                                <td><?= sanitize($doc['toUser'] ?? ''); ?></td>
                                <td><?= sanitize($doc['updatedAt'] ?? ''); ?></td>
                                <td><a class="btn secondary" href="/department/doc_view.php?id=<?= urlencode($doc['docId'] ?? ''); ?>"><?= sanitize('Open'); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
