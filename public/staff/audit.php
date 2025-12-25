<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $employee = require_active_employee();
    if (!employee_has_permission($employee, 'audit_view')) {
        set_flash('error', 'You do not have access to audit metadata.');
        redirect('/staff/dashboard.php');
    }

    $logFiles = glob(DATA_PATH . '/logs/*.log') ?: [];
    $logs = [];
    foreach ($logFiles as $file) {
        $logs[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'updated' => (new DateTimeImmutable('@' . filemtime($file)))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format(DateTime::ATOM),
        ];
    }
    usort($logs, fn($a, $b) => strcmp($b['updated'] ?? '', $a['updated'] ?? ''));

    $title = get_app_config()['appName'] . ' | Audit Metadata';
    render_layout($title, function () use ($logs) {
        ?>
        <div class="card">
            <h2 style="margin-bottom:6px;"><?= sanitize('Audit Metadata'); ?></h2>
            <p class="muted" style="margin-top:0;"><?= sanitize('View log file names, sizes, and timestamps. Contents stay hidden to protect sensitive data.'); ?></p>
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('Log File'); ?></th>
                        <th><?= sanitize('Size'); ?></th>
                        <th><?= sanitize('Last Updated'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$logs): ?>
                        <tr><td colspan="3" class="muted"><?= sanitize('No logs yet.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= sanitize($log['name'] ?? ''); ?></td>
                                <td><?= sanitize(number_format((int)($log['size'] ?? 0)) . ' bytes'); ?></td>
                                <td><?= sanitize($log['updated'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
