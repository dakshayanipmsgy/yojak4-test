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
    require_department_permission($user, 'manage_templates');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $action = $_POST['action'] ?? '';
        if ($action === 'sync') {
            sync_global_templates($deptId);
            set_flash('success', 'Global templates synced into cache.');
            append_department_audit($deptId, [
                'by' => $user['username'] ?? '',
                'action' => 'global_templates_synced',
            ]);
            redirect('/department/templates.php');
        }
    }

    $deptTemplates = load_department_templates($deptId);
    $globalTemplates = load_global_templates_cache($deptId);
    $title = get_app_config()['appName'] . ' | Templates';

    render_layout($title, function () use ($deptTemplates, $globalTemplates) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Templates'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Department templates with optional global cache.'); ?></p>
                </div>
                <div class="buttons">
                    <form method="post" action="/department/templates.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="action" value="sync">
                        <button class="btn secondary" type="submit"><?= sanitize('Sync Global'); ?></button>
                    </form>
                    <a class="btn" href="/department/template_create.php"><?= sanitize('Create Template'); ?></a>
                </div>
            </div>

            <h3 style="margin-top:16px;"><?= sanitize('Department Templates'); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('Title'); ?></th>
                        <th><?= sanitize('Placeholders'); ?></th>
                        <th><?= sanitize('Updated'); ?></th>
                        <th><?= sanitize('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$deptTemplates): ?>
                        <tr><td colspan="4" class="muted"><?= sanitize('No templates yet.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($deptTemplates as $tpl): ?>
                            <tr>
                                <td><?= sanitize($tpl['title'] ?? ''); ?></td>
                                <td>
                                    <?php foreach (($tpl['placeholders'] ?? []) as $ph): ?>
                                        <span class="tag"><?= sanitize($ph); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td><?= sanitize($tpl['updatedAt'] ?? ''); ?></td>
                                <td><a class="btn secondary" href="/department/template_edit.php?id=<?= urlencode($tpl['templateId'] ?? ''); ?>"><?= sanitize('Edit'); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h3 style="margin-top:16px;"><?= sanitize('Global Cache (read-only)'); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('Title'); ?></th>
                        <th><?= sanitize('Placeholders'); ?></th>
                        <th><?= sanitize('Updated'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$globalTemplates): ?>
                        <tr><td colspan="3" class="muted"><?= sanitize('No global templates cached.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($globalTemplates as $tpl): ?>
                            <tr>
                                <td><?= sanitize($tpl['title'] ?? ''); ?></td>
                                <td>
                                    <?php foreach (($tpl['placeholders'] ?? []) as $ph): ?>
                                        <span class="tag"><?= sanitize($ph); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td><?= sanitize($tpl['updatedAt'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
