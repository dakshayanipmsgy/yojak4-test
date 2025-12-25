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
    require_department_permission($user, 'manage_tenders');

    $tenders = load_department_tenders($deptId);
    $title = get_app_config()['appName'] . ' | Tenders';
    render_layout($title, function () use ($tenders) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Tenders'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Department owned YTD tenders.'); ?></p>
                </div>
                <a class="btn" href="/department/tender_create.php"><?= sanitize('Create Tender'); ?></a>
            </div>
            <table style="margin-top:12px;">
                <thead>
                    <tr>
                        <th><?= sanitize('ID'); ?></th>
                        <th><?= sanitize('Title'); ?></th>
                        <th><?= sanitize('Publish'); ?></th>
                        <th><?= sanitize('Submission'); ?></th>
                        <th><?= sanitize('Opening'); ?></th>
                        <th><?= sanitize('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$tenders): ?>
                        <tr><td colspan="6" class="muted"><?= sanitize('No tenders yet.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($tenders as $tender): ?>
                            <tr>
                                <td><?= sanitize($tender['id'] ?? ''); ?></td>
                                <td><?= sanitize($tender['title'] ?? ''); ?></td>
                                <td><?= sanitize($tender['publishDate'] ?? ''); ?></td>
                                <td><?= sanitize($tender['submissionDate'] ?? ''); ?></td>
                                <td><?= sanitize($tender['openingDate'] ?? ''); ?></td>
                                <td><a class="btn secondary" href="/department/tender_view.php?id=<?= urlencode($tender['id'] ?? ''); ?>"><?= sanitize('View'); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
