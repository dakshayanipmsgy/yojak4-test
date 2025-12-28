<?php
declare(strict_types=1);

function set_flash(string $type, string $message): void
{
    if (!isset($_SESSION['flashes'])) {
        $_SESSION['flashes'] = [];
    }
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function consume_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}

function render_layout(string $title, callable $content): void
{
    $appName = get_app_config()['appName'] ?? 'YOJAK';
    $lang = get_language();
    $user = current_user();
    $flashes = consume_flashes();
    ?>
    <!DOCTYPE html>
    <html lang="<?= sanitize($lang); ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= sanitize($title); ?></title>
        <style>
            :root {
                --primary: #1f6feb;
                --primary-dark: #144ea3;
                --background: #0d1117;
                --surface: #161b22;
                --text: #e6edf3;
                --muted: #8b949e;
                --danger: #f85149;
                --success: #2ea043;
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
                background: linear-gradient(180deg, #0b1a2a, #0d1117 60%);
                color: var(--text);
                min-height: 100vh;
            }
            a { color: var(--primary); text-decoration: none; }
            header {
                background: rgba(22,27,34,0.9);
                backdrop-filter: blur(8px);
                border-bottom: 1px solid #30363d;
                position: sticky;
                top: 0;
                z-index: 10;
            }
            .nav {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 12px 18px;
                max-width: 1100px;
                margin: 0 auto;
            }
            .brand {
                display: flex;
                align-items: center;
                gap: 10px;
                font-weight: 700;
                font-size: 18px;
                letter-spacing: 0.6px;
            }
            .brand-logo {
                width: 36px;
                height: 36px;
                background: linear-gradient(135deg, #1f6feb, #2ea043);
                border-radius: 10px;
                display: grid;
                place-items: center;
                font-weight: 800;
                color: #fff;
                box-shadow: 0 8px 16px rgba(0,0,0,0.3);
            }
            .nav-links {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .nav-links a, .nav-links form button {
                color: var(--text);
                padding: 8px 12px;
                border-radius: 8px;
                border: 1px solid transparent;
                background: transparent;
                cursor: pointer;
                font-weight: 600;
            }
            .nav-links a:hover, .nav-links form button:hover {
                background: #21262d;
            }
            .nav-links .primary {
                background: var(--primary);
                border-color: var(--primary);
                color: #fff;
                box-shadow: 0 4px 12px rgba(31,111,235,0.3);
            }
            .container {
                max-width: 1100px;
                margin: 24px auto;
                padding: 0 16px 32px;
            }
            .hero {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 16px;
                align-items: center;
            }
            .card {
                background: var(--surface);
                border: 1px solid #30363d;
                border-radius: 14px;
                padding: 18px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            }
            .card h1, .card h2, .card h3 { margin-top: 0; }
            .muted { color: var(--muted); }
            .buttons {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-top: 12px;
            }
            .btn {
                background: var(--primary);
                border: 1px solid var(--primary-dark);
                color: #fff;
                padding: 10px 14px;
                border-radius: 10px;
                cursor: pointer;
                font-weight: 700;
                box-shadow: 0 6px 18px rgba(31,111,235,0.25);
            }
            .btn.secondary {
                background: #21262d;
                border-color: #30363d;
            }
            .btn.danger {
                background: var(--danger);
                border-color: #c03a34;
            }
            form .field {
                display: flex;
                flex-direction: column;
                gap: 6px;
                margin-bottom: 12px;
            }
            input, select {
                padding: 10px 12px;
                border-radius: 10px;
                border: 1px solid #30363d;
                background: #0d1117;
                color: var(--text);
            }
            .flashes {
                margin-bottom: 16px;
                display: grid;
                gap: 8px;
            }
            .flash {
                padding: 10px 12px;
                border-radius: 10px;
                border: 1px solid #30363d;
                background: #111820;
                font-weight: 600;
            }
            .flash.success { border-color: var(--success); color: #8ce99a; }
            .flash.error { border-color: var(--danger); color: #f77676; }
            .lang-toggle {
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .pill {
                padding: 6px 10px;
                border-radius: 999px;
                border: 1px solid #30363d;
                background: #111820;
                font-size: 12px;
                color: var(--muted);
            }
            .error-card { border-color: var(--danger); }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 12px;
            }
            th, td {
                padding: 10px;
                border-bottom: 1px solid #30363d;
                text-align: left;
            }
            th { color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; font-size: 12px; }
            tr:hover td { background: #0f1520; }
            .tag {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 999px;
                border: 1px solid #30363d;
                font-size: 12px;
                color: var(--muted);
            }
            .tag.success { border-color: var(--success); color: #8ce99a; }
            @media (max-width: 600px) {
                .nav { flex-direction: column; align-items: flex-start; gap: 10px; }
                .nav-links { width: 100%; }
            }
        </style>
    </head>
    <body>
        <header>
            <div class="nav">
                <div class="brand">
                    <div class="brand-logo">YJ</div>
                    <div><?= sanitize($appName); ?></div>
                </div>
                <div class="nav-links">
                    <a href="/site/index.php"><?= sanitize(t('nav_home')); ?></a>
                    <?php if ($user && ($user['type'] ?? '') === 'superadmin'): ?>
                        <a href="/superadmin/dashboard.php"><?= sanitize(t('nav_dashboard')); ?></a>
                        <a href="/superadmin/departments.php"><?= sanitize('Departments'); ?></a>
                        <a href="/superadmin/contractors.php"><?= sanitize('Contractors'); ?></a>
                        <a href="/superadmin/employees.php"><?= sanitize('Employees'); ?></a>
                        <a href="/superadmin/ai_studio.php"><?= sanitize('AI Studio'); ?></a>
                        <a href="/superadmin/tender_discovery.php"><?= sanitize('Tender Discovery'); ?></a>
                        <a href="/superadmin/backup.php"><?= sanitize('Backups'); ?></a>
                        <a href="/superadmin/factory_reset.php"><?= sanitize('Factory Reset'); ?></a>
                        <a href="/superadmin/stats.php"><?= sanitize('Stats'); ?></a>
                        <a href="/superadmin/reset_requests.php"><?= sanitize('Reset Approvals'); ?></a>
                        <a href="/superadmin/profile.php"><?= sanitize(t('profile')); ?></a>
                        <form method="post" action="/auth/logout.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <button type="submit" class="nav-link"><?= sanitize(t('logout')); ?></button>
                        </form>
                    <?php elseif ($user && ($user['type'] ?? '') === 'department'): ?>
                        <a href="/department/dashboard.php"><?= sanitize('Department'); ?></a>
                        <a href="/department/roles.php"><?= sanitize('Roles'); ?></a>
                        <a href="/department/users.php"><?= sanitize('Users'); ?></a>
                        <a href="/department/templates.php"><?= sanitize('Templates'); ?></a>
                        <a href="/department/quick_doc.php"><?= sanitize('Quick Doc'); ?></a>
                        <a href="/department/docs_inbox.php"><?= sanitize('Docs'); ?></a>
                        <a href="/department/tenders.php"><?= sanitize('Tenders'); ?></a>
                        <a href="/department/workorders.php"><?= sanitize('Workorders'); ?></a>
                        <a href="/department/requirements.php"><?= sanitize('Requirements'); ?></a>
                        <a href="/department/dak.php"><?= sanitize('DAK'); ?></a>
                        <a href="/department/health.php"><?= sanitize('Health'); ?></a>
                        <form method="post" action="/department/logout.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <button type="submit" class="nav-link"><?= sanitize(t('logout')); ?></button>
                        </form>
                    <?php elseif ($user && ($user['type'] ?? '') === 'contractor'): ?>
                        <a href="/contractor/dashboard.php"><?= sanitize('Contractor'); ?></a>
                        <a href="/contractor/packs.php"><?= sanitize('Tender Packs'); ?></a>
                        <a href="/contractor/vault.php"><?= sanitize('Vault'); ?></a>
                        <a href="/contractor/bills.php"><?= sanitize('Bills'); ?></a>
                        <a href="/contractor/workorders.php"><?= sanitize('Workorders'); ?></a>
                        <a href="/contractor/discovered_tenders.php"><?= sanitize('Discovered'); ?></a>
                        <a href="/contractor/offline_tenders.php"><?= sanitize('Offline Tenders'); ?></a>
                        <a href="/contractor/tender_archive.php"><?= sanitize('Tender Archive'); ?></a>
                        <form method="post" action="/contractor/logout.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <button type="submit" class="nav-link"><?= sanitize(t('logout')); ?></button>
                        </form>
                    <?php elseif ($user && ($user['type'] ?? '') === 'employee'): ?>
                        <a href="/staff/dashboard.php"><?= sanitize('Staff'); ?></a>
                        <?php if (in_array('tickets', $user['permissions'] ?? [], true)): ?>
                            <a href="/staff/tickets.php"><?= sanitize('Tickets'); ?></a>
                        <?php endif; ?>
                        <?php if (in_array('audit_view', $user['permissions'] ?? [], true)): ?>
                            <a href="/staff/audit.php"><?= sanitize('Audit'); ?></a>
                        <?php endif; ?>
                        <?php if (in_array('stats_view', $user['permissions'] ?? [], true)): ?>
                            <a href="/superadmin/stats.php"><?= sanitize('Stats'); ?></a>
                        <?php endif; ?>
                        <?php if (in_array('reset_approvals', $user['permissions'] ?? [], true)): ?>
                            <a href="/superadmin/reset_requests.php"><?= sanitize('Reset Approvals'); ?></a>
                        <?php endif; ?>
                        <form method="post" action="/auth/logout.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <button type="submit" class="nav-link"><?= sanitize(t('logout')); ?></button>
                        </form>
                    <?php else: ?>
                        <a href="/contractor/login.php"><?= sanitize('Contractor Login'); ?></a>
                        <a href="/department/login.php"><?= sanitize('Department Login'); ?></a>
                        <a href="/auth/login.php" class="primary"><?= sanitize(t('nav_auth')); ?></a>
                    <?php endif; ?>
                    <form method="get" class="lang-toggle">
                        <span class="pill"><?= sanitize(t('language')); ?></span>
                        <select name="lang" onchange="this.form.submit()">
                            <option value="en" <?= $lang === 'en' ? 'selected' : ''; ?>><?= sanitize(t('english')); ?></option>
                            <option value="hi" <?= $lang === 'hi' ? 'selected' : ''; ?>><?= sanitize(t('hindi')); ?></option>
                        </select>
                    </form>
                </div>
            </div>
        </header>
        <main class="container">
            <?php if ($flashes): ?>
                <div class="flashes">
                    <?php foreach ($flashes as $flash): ?>
                        <div class="flash <?= sanitize($flash['type']); ?>"><?= sanitize($flash['message']); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php $content(); ?>
        </main>
    </body>
    </html>
    <?php
}

function safe_page(callable $callback): void
{
    try {
        $callback();
    } catch (Throwable $e) {
        logEvent(DATA_PATH . '/logs/php_errors.log', [
            'event' => 'page_exception',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        render_error_page();
    }
}
