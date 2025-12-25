<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $employee = require_active_employee();
    if (!employee_has_permission($employee, 'tickets')) {
        set_flash('error', 'You do not have access to support tickets.');
        redirect('/staff/dashboard.php');
    }

    $title = get_app_config()['appName'] . ' | Support Tickets';
    render_layout($title, function () {
        ?>
        <div class="card">
            <h2 style="margin-bottom:6px;"><?= sanitize('Support Tickets'); ?></h2>
            <p class="muted" style="margin-top:0;"><?= sanitize('Placeholder workspace for employee support tasks. No department documents are exposed.'); ?></p>
            <div class="pill"><?= sanitize('Ticket tooling will plug in here.'); ?></div>
        </div>
        <?php
    });
});
