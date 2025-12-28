<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'] ?? '';
    $deptId = normalize_dept_id(trim($_GET['deptId'] ?? ''));
    if (!is_valid_dept_id($deptId)) {
        render_error_page('Invalid department.');
        return;
    }

    $link = load_contractor_link($yojId, $deptId);
    if (!$link || ($link['status'] ?? '') !== 'active') {
        render_error_page('You do not have access to this department.');
        return;
    }

    $department = load_department($deptId);
    $tenders = load_department_published_tenders($deptId);
    $title = get_app_config()['appName'] . ' | Tenders';

    render_layout($title, function () use ($department, $tenders) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0 0 6px 0;"><?= sanitize($department['nameEn'] ?? 'Department'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Published tenders for linked contractors'); ?></p>
                </div>
                <a class="btn secondary" href="/contractor/department.php?deptId=<?= urlencode($department['deptId'] ?? ''); ?>"><?= sanitize('Back'); ?></a>
            </div>
            <?php if (!$tenders): ?>
                <p class="muted" style="margin-top:10px;"><?= sanitize('No published tenders yet.'); ?></p>
            <?php else: ?>
                <table style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th><?= sanitize('ID'); ?></th>
                            <th><?= sanitize('Title'); ?></th>
                            <th><?= sanitize('Publish'); ?></th>
                            <th><?= sanitize('Submission'); ?></th>
                            <th><?= sanitize('Opening'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenders as $tender): ?>
                            <tr>
                                <td><?= sanitize($tender['id'] ?? ''); ?></td>
                                <td><?= sanitize($tender['title'] ?? ''); ?></td>
                                <td><?= sanitize($tender['publishDate'] ?? ''); ?></td>
                                <td><?= sanitize($tender['submissionDate'] ?? ''); ?></td>
                                <td><?= sanitize($tender['openingDate'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    });
});
