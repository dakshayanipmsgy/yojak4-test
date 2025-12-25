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
    require_department_permission($user, 'manage_dak');

    $dakItems = load_dak_index($deptId);
    $title = get_app_config()['appName'] . ' | DAK Tracker';

    render_layout($title, function () use ($dakItems) {
        ?>
        <div class="card">
            <h2 style="margin-bottom:4px;"><?= sanitize('DAK Tracking'); ?></h2>
            <p class="muted" style="margin:0;"><?= sanitize('Track file movement history.'); ?></p>
            <form method="post" action="/department/dak_create.php" style="margin-top:12px;display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="fileRef"><?= sanitize('File Reference'); ?></label>
                    <input id="fileRef" name="fileRef" required>
                </div>
                <div class="field">
                    <label for="location"><?= sanitize('Current Location'); ?></label>
                    <input id="location" name="location" required>
                </div>
                <button class="btn" type="submit"><?= sanitize('Add DAK'); ?></button>
            </form>

            <h3 style="margin-top:16px;"><?= sanitize('DAK Items'); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('ID'); ?></th>
                        <th><?= sanitize('File Ref'); ?></th>
                        <th><?= sanitize('Location'); ?></th>
                        <th><?= sanitize('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$dakItems): ?>
                        <tr><td colspan="4" class="muted"><?= sanitize('No DAK entries.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($dakItems as $item): ?>
                            <tr>
                                <td><?= sanitize($item['dakId'] ?? ''); ?></td>
                                <td><?= sanitize($item['fileRef'] ?? ''); ?></td>
                                <td><?= sanitize($item['currentLocation'] ?? ''); ?></td>
                                <td>
                                    <form method="post" action="/department/dak_move.php" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="dakId" value="<?= sanitize($item['dakId'] ?? ''); ?>">
                                        <input type="text" name="location" placeholder="New location" required style="flex:1;min-width:140px;">
                                        <button class="btn secondary" type="submit"><?= sanitize('Move'); ?></button>
                                    </form>
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
