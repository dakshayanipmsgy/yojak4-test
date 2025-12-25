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

    $docs = array_filter(list_department_docs($deptId), fn($doc) => ($doc['status'] ?? '') === 'inbox');
    $title = get_app_config()['appName'] . ' | Docs Inbox';
    render_layout($title, function () use ($docs) {
        ?>
        <div class="card">
            <h2 style="margin-bottom:4px;"><?= sanitize('Docs Inbox'); ?></h2>
            <p class="muted" style="margin:0;"><?= sanitize('Incoming documents. Forward or sign as required.'); ?></p>
            <table style="margin-top:12px;">
                <thead>
                    <tr>
                        <th><?= sanitize('Title'); ?></th>
                        <th><?= sanitize('From'); ?></th>
                        <th><?= sanitize('Updated'); ?></th>
                        <th><?= sanitize('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$docs): ?>
                        <tr><td colspan="4" class="muted"><?= sanitize('No docs in inbox.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($docs as $doc): ?>
                            <tr>
                                <td><?= sanitize($doc['title'] ?? ''); ?></td>
                                <td><?= sanitize($doc['fromUser'] ?? ''); ?></td>
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
