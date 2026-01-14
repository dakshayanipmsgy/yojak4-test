<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = current_user();
    $target = resolve_user_dashboard($user);
    if ($target) {
        log_home_redirect($user['type'] ?? 'unknown', $target, 'redirect_from_staff_login');
        redirect($target);
    }

    logEvent(DATA_PATH . '/logs/site.log', [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => 'STAFF_LOGIN_PAGE_VIEW',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    ]);

    $title = get_app_config()['appName'] . ' | YOJAK Staff Login';

    render_layout($title, function () {
        ?>
        <style>
            <?= public_theme_css(); ?>
            .staff-card {
                display: grid;
                gap: 16px;
            }
            .staff-actions {
                display: grid;
                gap: 12px;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }
            .staff-action {
                display: grid;
                gap: 8px;
                align-items: start;
            }
            .staff-note {
                font-size: 13px;
                color: var(--public-muted);
            }
        </style>

        <div class="card staff-card">
            <h2 style="margin:0;"><?= sanitize('YOJAK Staff Login'); ?></h2>
            <p class="muted" style="margin:0;"><?= sanitize('Choose your staff login path. Both options accept approved YOJAK staff credentials.'); ?></p>
            <div class="staff-actions">
                <div class="staff-action">
                    <a class="btn" href="/auth/login.php"><?= sanitize('Superadmin Login'); ?></a>
                    <div class="staff-note"><?= sanitize('Full platform administration and oversight.'); ?></div>
                </div>
                <div class="staff-action">
                    <a class="btn" href="/auth/login.php"><?= sanitize('Employee Login'); ?></a>
                    <div class="staff-note"><?= sanitize('RBAC-scoped staff access for internal tasks.'); ?></div>
                </div>
            </div>
        </div>
        <?php
    });
});
