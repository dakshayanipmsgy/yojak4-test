<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $query = trim($_GET['q'] ?? '');
    $departments = departments_index();
    if ($query !== '') {
        $departments = array_values(array_filter($departments, function ($dept) use ($query) {
            $needle = strtolower($query);
            return str_contains(strtolower($dept['deptId'] ?? ''), $needle)
                || str_contains(strtolower($dept['nameEn'] ?? ''), $needle)
                || str_contains(strtolower($dept['nameHi'] ?? ''), $needle);
        }));
    }

    $title = get_app_config()['appName'] . ' | Departments';
    render_layout($title, function () use ($departments, $query) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Departments'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Registry of all departments. Superadmin cannot access department documents.'); ?></p>
                </div>
                <a class="btn" href="/superadmin/department_create.php"><?= sanitize('Create Department'); ?></a>
            </div>
            <form method="get" style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
                <input type="text" name="q" value="<?= sanitize($query); ?>" placeholder="<?= sanitize('Search by id or name'); ?>" style="flex:1;min-width:200px;">
                <button class="btn secondary" type="submit"><?= sanitize('Search'); ?></button>
            </form>
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('Dept ID'); ?></th>
                        <th><?= sanitize('Name (EN)'); ?></th>
                        <th><?= sanitize('Status'); ?></th>
                        <th><?= sanitize('Created'); ?></th>
                        <th><?= sanitize('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$departments): ?>
                        <tr><td colspan="5" class="muted"><?= sanitize('No departments yet.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td><?= sanitize($dept['deptId'] ?? ''); ?></td>
                                <td><?= sanitize($dept['nameEn'] ?? ''); ?></td>
                                <td><span class="tag <?= ($dept['status'] ?? '') === 'active' ? 'success' : ''; ?>"><?= sanitize(ucfirst($dept['status'] ?? '')); ?></span></td>
                                <td><?= sanitize(isset($dept['createdAt']) ? (new DateTime($dept['createdAt']))->format('d M Y') : ''); ?></td>
                                <td>
                                    <a class="btn secondary" href="/superadmin/department_view.php?deptId=<?= urlencode($dept['deptId'] ?? ''); ?>"><?= sanitize('View'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
