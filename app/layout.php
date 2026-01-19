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
    $path = $_SERVER['REQUEST_URI'] ?? '';
    $isPublicVisitor = !$user;
    $contactDetails = function_exists('public_contact_details') ? public_contact_details() : null;
    $mobileContact = $contactDetails ?? [
        'mobile' => '7070278178',
        'email' => 'connect@yojak.co.in',
    ];
    $publicNav = [
        'home' => ['hi' => 'मुख्य पृष्ठ', 'en' => 'Home'],
        'how' => ['hi' => 'कैसे काम करता है', 'en' => 'How it works'],
        'features' => ['hi' => 'विशेषताएँ', 'en' => 'Features'],
        'templates' => ['hi' => 'टेम्पलेट्स और पैक', 'en' => 'Templates & Packs'],
        'contact' => ['hi' => 'संपर्क', 'en' => 'Contact'],
        'departmentLogin' => ['hi' => 'विभाग लॉगिन', 'en' => 'Department Login'],
        'contractorLogin' => ['hi' => 'ठेकेदार लॉगिन', 'en' => 'Contractor Login'],
        'yojakLogin' => ['hi' => 'YOJAK लॉगिन', 'en' => 'YOJAK Login'],
        'call' => ['hi' => 'कॉल', 'en' => 'Call'],
        'email' => ['hi' => 'ईमेल', 'en' => 'Email'],
    ];
    $navLinks = [];
    $logoutAction = null;
    if ($user && ($user['type'] ?? '') === 'superadmin') {
        $navLinks = [
            ['label' => t('nav_home'), 'href' => '/home.php'],
            ['label' => t('nav_dashboard'), 'href' => '/superadmin/dashboard.php'],
            ['label' => 'Users', 'href' => '/superadmin/users.php'],
            ['label' => 'AI Studio', 'href' => '/superadmin/ai_studio.php'],
            ['label' => 'Tender Discovery', 'href' => '/superadmin/tender_discovery.php'],
            ['label' => 'Templates', 'href' => '/superadmin/templates.php'],
            ['label' => 'Pack Presets', 'href' => '/superadmin/packs_blueprints.php'],
            ['label' => 'Backups', 'href' => '/superadmin/backup.php'],
            ['label' => 'Support Inbox', 'href' => '/superadmin/support_dashboard.php'],
            ['label' => 'Assisted Pack v2', 'href' => '/superadmin/assisted_v2/queue.php'],
            ['label' => 'Schemes Builder', 'href' => '/superadmin/schemes/index.php'],
            ['label' => 'Activation Requests', 'href' => '/superadmin/schemes/activation_requests.php'],
            ['label' => 'Staff Guide', 'href' => '/superadmin/staff_guide.php'],
            ['label' => 'Contractor Guides', 'href' => '/superadmin/guide/index.php'],
            ['label' => 'Error Log', 'href' => '/superadmin/error_log.php'],
            ['label' => 'Factory Reset', 'href' => '/superadmin/factory_reset.php'],
            ['label' => 'Stats', 'href' => '/superadmin/stats.php'],
            ['label' => 'Reset Approvals', 'href' => '/superadmin/reset_requests.php'],
            ['label' => t('profile'), 'href' => '/superadmin/profile.php'],
        ];
        $logoutAction = '/auth/logout.php';
    } elseif ($user && ($user['type'] ?? '') === 'department') {
        $navLinks = [
            ['label' => t('nav_home'), 'href' => '/home.php'],
            ['label' => 'Department', 'href' => '/department/dashboard.php'],
            ['label' => 'Roles', 'href' => '/department/roles.php'],
            ['label' => 'Users', 'href' => '/department/users.php'],
            ['label' => 'Contractors', 'href' => '/department/contractors.php'],
            ['label' => 'Requests', 'href' => '/department/contractor_requests.php'],
            ['label' => 'Templates', 'href' => '/department/templates.php'],
            ['label' => 'Quick Doc', 'href' => '/department/quick_doc.php'],
            ['label' => 'Docs', 'href' => '/department/docs_inbox.php'],
            ['label' => 'Tenders', 'href' => '/department/tenders.php'],
            ['label' => 'Workorders', 'href' => '/department/workorders.php'],
            ['label' => 'Requirement Sets', 'href' => '/department/requirement_sets.php'],
            ['label' => 'DAK', 'href' => '/department/dak.php'],
            ['label' => 'Health', 'href' => '/department/health.php'],
            ['label' => 'Support', 'href' => '/department/support.php'],
        ];
        $logoutAction = '/department/logout.php';
    } elseif ($user && ($user['type'] ?? '') === 'contractor') {
        $navLinks = [
            ['label' => t('nav_home'), 'href' => '/home.php'],
            ['label' => 'Contractor', 'href' => '/contractor/dashboard.php'],
            ['label' => 'Departments', 'href' => '/contractor/departments.php'],
            ['label' => 'Vault', 'href' => '/contractor/vault.php'],
            ['label' => 'Bills', 'href' => '/contractor/bills.php'],
            ['label' => 'Workorders', 'href' => '/contractor/workorders.php'],
            ['label' => 'Tenders', 'href' => '/contractor/tenders.php'],
            ['label' => 'Templates', 'href' => '/contractor/templates.php'],
            ['label' => 'Pack Presets', 'href' => '/contractor/packs_blueprints.php'],
            ['label' => 'Schemes', 'href' => '/contractor/schemes.php'],
            ['label' => 'Notifications', 'href' => '/contractor/notifications.php'],
            ['label' => 'Support', 'href' => '/contractor/support.php'],
        ];
        $logoutAction = '/contractor/logout.php';
    } elseif ($user && ($user['type'] ?? '') === 'employee') {
        $navLinks = [
            ['label' => t('nav_home'), 'href' => '/home.php'],
            ['label' => 'Staff', 'href' => '/staff/dashboard.php'],
            ['label' => 'Guide', 'href' => '/employees/staff_guide.php'],
        ];
        if (in_array('tickets', $user['permissions'] ?? [], true)) {
            $navLinks[] = ['label' => 'Tickets', 'href' => '/staff/tickets.php'];
        }
        if (in_array('can_process_assisted', $user['permissions'] ?? [], true)) {
            $navLinks[] = ['label' => 'Assisted Pack v2', 'href' => '/staff/assisted_v2/queue.php'];
        }
        if (in_array('audit_view', $user['permissions'] ?? [], true)) {
            $navLinks[] = ['label' => 'Audit', 'href' => '/staff/audit.php'];
        }
        if (in_array('scheme_builder', $user['permissions'] ?? [], true) || in_array('*', $user['permissions'] ?? [], true)) {
            $navLinks[] = ['label' => 'Schemes', 'href' => '/superadmin/schemes/index.php'];
        }
        if (in_array('stats_view', $user['permissions'] ?? [], true)) {
            $navLinks[] = ['label' => 'Stats', 'href' => '/superadmin/stats.php'];
        }
        if (in_array('reset_approvals', $user['permissions'] ?? [], true)) {
            $navLinks[] = ['label' => 'Reset Approvals', 'href' => '/superadmin/reset_requests.php'];
        }
        $logoutAction = '/auth/logout.php';
    } else {
        $navLinks = [
            ['label' => $publicNav['home'][$lang], 'href' => '/site/index.php'],
            ['label' => $publicNav['how'][$lang], 'href' => '/site/index.php#how-it-works'],
            ['label' => $publicNav['features'][$lang], 'href' => '/site/index.php#features'],
            ['label' => $publicNav['templates'][$lang], 'href' => '/site/index.php#templates-packs'],
            ['label' => $publicNav['contact'][$lang], 'href' => '/site/contact.php'],
        ];
    }
    $mobilePrimaryAction = $user
        ? ['label' => t('nav_home'), 'href' => '/home.php', 'style' => 'secondary']
        : null;
    $flashes = consume_flashes();
    ?>
    <!DOCTYPE html>
    <html lang="<?= sanitize($lang); ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= sanitize($title); ?></title>
        <link rel="stylesheet" href="/assets/css/theme_tokens.css">
        <style>
            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
                background: var(--background);
                color: var(--text);
                min-height: 100vh;
            }
            a { color: var(--primary); text-decoration: none; }
            header {
                background: rgba(255,255,255,0.98);
                backdrop-filter: blur(8px);
                border-bottom: 1px solid var(--border);
                position: sticky;
                top: 0;
                z-index: 10;
            }
            .wrap {
                width: 100%;
                max-width: 1440px;
                margin: 0 auto;
                padding: 0 20px;
            }
            .nav {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 12px 0;
            }
            .nav-left {
                display: flex;
                align-items: center;
                gap: 14px;
            }
            .nav-actions {
                display: flex;
                align-items: center;
                gap: 8px;
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
                width: 32px;
                height: 32px;
                background: linear-gradient(135deg, #1f6feb, #2ea043);
                border-radius: 9px;
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
                background: #f3f4f6;
            }
            .nav-links .primary {
                background: var(--primary);
                border-color: var(--primary);
                color: #fff;
                box-shadow: 0 4px 12px rgba(31,111,235,0.18);
            }
            .nav-links .secondary {
                background: #f1f5f9;
                border-color: #d0d7e2;
                color: var(--text);
            }
            .brand-logo-image {
                width: auto;
                height: auto;
                min-height: 32px;
                padding: 4px 10px;
                border-radius: 10px;
                border: 1px solid var(--border);
                background: #ffffff;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 8px 16px rgba(15, 23, 42, 0.12);
            }
            .brand-logo-image img {
                display: block;
                max-height: 32px;
                max-width: 160px;
                object-fit: contain;
            }
            .desktop-only { display: flex; }
            .mobile-only { display: none; }
            .hamburger {
                width: 40px;
                height: 40px;
                border-radius: 10px;
                border: 1px solid var(--border);
                background: #ffffff;
                display: grid;
                place-items: center;
                cursor: pointer;
                font-size: 18px;
                color: var(--text);
            }
            .hamburger:focus {
                outline: 2px solid rgba(31,111,235,0.4);
                outline-offset: 2px;
            }
            .mobile-menu {
                position: fixed;
                inset: 0;
                display: none;
                z-index: 60;
            }
            .mobile-menu.active { display: block; }
            .mobile-menu-backdrop {
                position: absolute;
                inset: 0;
                background: rgba(148, 163, 184, 0.4);
            }
            .mobile-menu-panel {
                position: absolute;
                top: 0;
                right: 0;
                height: 100%;
                width: 340px;
                max-width: 90%;
                background: #ffffff;
                display: flex;
                flex-direction: column;
                gap: 16px;
                padding: 18px;
                box-shadow: -18px 0 30px rgba(15, 23, 42, 0.18);
            }
            .mobile-menu-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-weight: 700;
                font-size: 16px;
            }
            .mobile-menu-close {
                border: 1px solid var(--border);
                background: #ffffff;
                border-radius: 10px;
                width: 36px;
                height: 36px;
                cursor: pointer;
                font-size: 18px;
            }
            .mobile-menu-links {
                display: grid;
                gap: 6px;
            }
            .mobile-menu-links a,
            .mobile-menu-links button {
                text-decoration: none;
                color: var(--text);
                padding: 10px 12px;
                border-radius: 10px;
                border: 1px solid var(--border);
                background: #f8fafc;
                font-weight: 600;
                text-align: left;
            }
            .mobile-menu-links button {
                width: 100%;
                cursor: pointer;
            }
            .mobile-menu-section {
                display: grid;
                gap: 10px;
            }
            .mobile-menu-buttons {
                display: grid;
                gap: 8px;
            }
            .mobile-menu-buttons .btn {
                width: 100%;
                justify-content: center;
            }
            .mobile-contact {
                display: grid;
                gap: 6px;
                padding: 12px;
                border-radius: 12px;
                border: 1px solid var(--border);
                background: #f8fafc;
                font-size: 14px;
            }
            .no-js-menu {
                display: none;
                border: 1px solid var(--border);
                border-radius: 12px;
                padding: 8px 12px;
                margin-top: 10px;
                background: #ffffff;
            }
            .js-enabled .no-js-menu { display: none !important; }
            .no-js-menu summary {
                cursor: pointer;
                font-weight: 600;
            }
            .mobile-accordion {
                border: 1px solid var(--border);
                border-radius: 14px;
                background: var(--surface);
                overflow: hidden;
            }
            .mobile-accordion summary {
                list-style: none;
                padding: 12px 16px;
                font-weight: 700;
                display: flex;
                align-items: center;
                justify-content: space-between;
                cursor: pointer;
            }
            .mobile-accordion summary::-webkit-details-marker {
                display: none;
            }
            .mobile-accordion[open] summary {
                border-bottom: 1px solid var(--border);
            }
            .mobile-accordion .card {
                border: none;
                box-shadow: none;
                margin: 0;
                border-radius: 0;
            }
            .container {
                width: 100%;
                max-width: 1440px;
                margin: 24px auto;
                padding: 0 20px 32px;
            }
            .hero {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 16px;
                align-items: center;
            }
            .card {
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 14px;
                padding: 18px;
                box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
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
                box-shadow: 0 6px 18px rgba(31,111,235,0.16);
            }
            .btn.secondary {
                background: #f1f5f9;
                border-color: #d0d7e2;
                color: var(--text);
                box-shadow: none;
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
                border: 1px solid var(--border);
                background: #ffffff;
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
                border: 1px solid var(--border);
                background: #ffffff;
                font-weight: 600;
            }
            .flash.success { border-color: var(--success); color: #166534; background: #ecfdf3; }
            .flash.error { border-color: var(--danger); color: #b91c1c; background: #fef2f2; }
            .lang-toggle {
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .pill {
                padding: 6px 10px;
                border-radius: 999px;
                border: 1px solid var(--border);
                background: #f8fafc;
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
                border-bottom: 1px solid var(--border);
                text-align: left;
            }
            th { color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; font-size: 12px; }
            tr:hover td { background: #f9fafb; }
            .tag {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 999px;
                border: 1px solid var(--border);
                font-size: 12px;
                color: var(--muted);
            }
            .tag.success { border-color: var(--success); color: #166534; background: #ecfdf3; }
            @media (min-width: 769px) {
                .mobile-accordion summary { display: none; }
                .mobile-accordion {
                    border: none;
                    background: transparent;
                    padding: 0;
                }
                .mobile-accordion .card {
                    border: 1px solid var(--border);
                    border-radius: 14px;
                    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
                    padding: 18px;
                }
            }
            @media (max-width: 768px) {
                .desktop-only { display: none !important; }
                .mobile-only { display: flex; }
                .nav {
                    padding: 6px 0;
                    align-items: center;
                }
                .nav-actions {
                    margin-left: auto;
                    justify-content: flex-end;
                }
                .wrap { padding: 0 16px; }
                .nav-left { gap: 8px; }
                .brand { font-size: 16px; }
                .brand-logo { width: 24px; height: 24px; border-radius: 7px; }
                .brand-logo-image { min-height: 24px; padding: 2px 8px; }
                .brand-logo-image img { max-height: 24px; }
                .nav-actions .btn {
                    padding: 6px 10px;
                    font-size: 12px;
                    border-radius: 8px;
                    box-shadow: none;
                }
                .container {
                    margin: 16px auto;
                    padding: 0 16px 24px;
                }
                .card { padding: 16px; }
                .buttons {
                    flex-direction: column;
                    align-items: stretch;
                }
                .buttons .btn,
                .buttons a.btn {
                    width: 100%;
                    text-align: center;
                }
                form .field input,
                form .field select,
                form .field textarea,
                input,
                select,
                textarea {
                    width: 100%;
                }
                table {
                    display: block;
                    overflow-x: auto;
                    width: 100%;
                }
                th, td { white-space: nowrap; }
                .hero { grid-template-columns: 1fr; }
                .mobile-menu-panel { width: 320px; }
                .no-js-menu { display: block; }
                .mobile-accordion .card { padding: 12px; }
            }
            @media (max-width: 420px) {
                .brand { font-size: 15px; }
                .nav-actions .btn { font-size: 11px; }
                .hamburger { width: 36px; height: 36px; }
                h1 { font-size: 24px; }
                h2 { font-size: 20px; }
                h3 { font-size: 18px; }
            }
            body.menu-open {
                overflow: hidden;
            }
        </style>
    </head>
    <body>
        <header>
            <div class="wrap nav">
                <div class="nav-left">
                    <div class="brand">
                        <?= render_logo_html('md'); ?>
                        <div>
                            <?= sanitize($appName); ?>
                        </div>
                    </div>
                </div>
                <div class="nav-actions mobile-only">
                    <button class="hamburger" type="button" aria-label="Open menu" data-mobile-toggle>☰</button>
                    <?php if ($mobilePrimaryAction): ?>
                        <a class="btn <?= $mobilePrimaryAction['style'] === 'primary' ? '' : 'secondary'; ?>" href="<?= sanitize($mobilePrimaryAction['href']); ?>"><?= sanitize($mobilePrimaryAction['label']); ?></a>
                    <?php endif; ?>
                </div>
                <div class="nav-links desktop-only">
                    <?php foreach ($navLinks as $link): ?>
                        <a href="<?= sanitize($link['href']); ?>"><?= sanitize($link['label']); ?></a>
                    <?php endforeach; ?>
                    <?php if ($logoutAction): ?>
                        <form method="post" action="<?= sanitize($logoutAction); ?>" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <button type="submit" class="nav-link"><?= sanitize(t('logout')); ?></button>
                        </form>
                    <?php endif; ?>
                    <form method="get" class="lang-toggle">
                        <span class="pill"><?= sanitize(t('language')); ?></span>
                        <select name="lang" onchange="this.form.submit()">
                            <option value="en" <?= $lang === 'en' ? 'selected' : ''; ?>><?= sanitize(t('english')); ?></option>
                            <option value="hi" <?= $lang === 'hi' ? 'selected' : ''; ?>><?= sanitize(t('hindi')); ?></option>
                        </select>
                    </form>
                    <?php if (!$user): ?>
                        <a href="/department/login.php" class="secondary"><?= sanitize($publicNav['departmentLogin'][$lang]); ?></a>
                        <a href="/contractor/login.php" class="secondary"><?= sanitize($publicNav['contractorLogin'][$lang]); ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <details class="no-js-menu mobile-only">
                <summary><?= sanitize('Menu'); ?></summary>
                <div style="display:grid; gap:8px; margin-top:10px;">
                    <?php foreach ($navLinks as $link): ?>
                        <a href="<?= sanitize($link['href']); ?>"><?= sanitize($link['label']); ?></a>
                    <?php endforeach; ?>
                    <?php if (!$user): ?>
                        <a href="/department/login.php"><?= sanitize($publicNav['departmentLogin'][$lang]); ?></a>
                        <a href="/contractor/login.php"><?= sanitize($publicNav['contractorLogin'][$lang]); ?></a>
                        <a href="/auth/login.php"><?= sanitize($publicNav['yojakLogin'][$lang]); ?></a>
                    <?php endif; ?>
                </div>
            </details>
        </header>
        <div class="mobile-menu" id="mobile-menu" aria-hidden="true">
            <div class="mobile-menu-backdrop" data-mobile-close></div>
            <div class="mobile-menu-panel" role="dialog" aria-modal="true" aria-label="<?= sanitize('Mobile Menu'); ?>">
                <div class="mobile-menu-header">
                    <span><?= sanitize('Menu'); ?></span>
                    <button class="mobile-menu-close" type="button" aria-label="Close menu" data-mobile-close>×</button>
                </div>
                <div class="mobile-menu-section">
                    <nav class="mobile-menu-links">
                        <?php foreach ($navLinks as $link): ?>
                            <a href="<?= sanitize($link['href']); ?>" data-mobile-link><?= sanitize($link['label']); ?></a>
                        <?php endforeach; ?>
                        <?php if ($logoutAction): ?>
                            <form method="post" action="<?= sanitize($logoutAction); ?>">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <button type="submit"><?= sanitize(t('logout')); ?></button>
                            </form>
                        <?php endif; ?>
                    </nav>
                </div>
                <?php if (!$user): ?>
                    <div class="mobile-menu-section">
                        <div class="mobile-menu-buttons">
                            <a class="btn secondary" href="/department/login.php"><?= sanitize($publicNav['departmentLogin'][$lang]); ?></a>
                            <a class="btn secondary" href="/contractor/login.php"><?= sanitize($publicNav['contractorLogin'][$lang]); ?></a>
                            <a class="btn" href="/auth/login.php"><?= sanitize($publicNav['yojakLogin'][$lang]); ?></a>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="mobile-menu-section">
                    <form method="get" class="lang-toggle">
                        <span class="pill"><?= sanitize(t('language')); ?></span>
                        <select name="lang" onchange="this.form.submit()">
                            <option value="en" <?= $lang === 'en' ? 'selected' : ''; ?>><?= sanitize(t('english')); ?></option>
                            <option value="hi" <?= $lang === 'hi' ? 'selected' : ''; ?>><?= sanitize(t('hindi')); ?></option>
                        </select>
                    </form>
                </div>
                <div class="mobile-contact">
                    <div><strong><?= sanitize($publicNav['call'][$lang]); ?>:</strong> <a href="tel:<?= sanitize($mobileContact['mobile']); ?>"><?= sanitize($mobileContact['mobile']); ?></a></div>
                    <div><strong><?= sanitize($publicNav['email'][$lang]); ?>:</strong> <a href="mailto:<?= sanitize($mobileContact['email']); ?>"><?= sanitize($mobileContact['email']); ?></a></div>
                </div>
            </div>
        </div>
        <main class="container wrap">
            <?php if ($flashes): ?>
                <div class="flashes">
                    <?php foreach ($flashes as $flash): ?>
                        <div class="flash <?= sanitize($flash['type']); ?>"><?= sanitize($flash['message']); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php $content(); ?>
        </main>
        <script>
            (() => {
                document.body.classList.add('js-enabled');
                const menu = document.getElementById('mobile-menu');
                const openers = document.querySelectorAll('[data-mobile-toggle]');
                const closers = document.querySelectorAll('[data-mobile-close]');
                const links = menu ? menu.querySelectorAll('[data-mobile-link]') : [];
                if (!menu) {
                    return;
                }
                const openMenu = () => {
                    menu.classList.add('active');
                    menu.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('menu-open');
                    const firstLink = menu.querySelector('[data-mobile-link]');
                    if (firstLink) {
                        firstLink.focus();
                    }
                };
                const closeMenu = () => {
                    menu.classList.remove('active');
                    menu.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('menu-open');
                };
                openers.forEach((btn) => btn.addEventListener('click', openMenu));
                closers.forEach((btn) => btn.addEventListener('click', closeMenu));
                links.forEach((link) => link.addEventListener('click', closeMenu));
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeMenu();
                    }
                });
                const setupAccordions = () => {
                    const isMobile = window.matchMedia('(max-width: 768px)').matches;
                    if (!isMobile) {
                        return;
                    }
                    document.querySelectorAll('details.mobile-accordion').forEach((detail) => {
                        if (!detail.hasAttribute('data-mobile-open')) {
                            detail.removeAttribute('open');
                        }
                    });
                };
                setupAccordions();
            })();
        </script>
    </body>
    </html>
    <?php
}
