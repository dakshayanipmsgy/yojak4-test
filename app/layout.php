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
        'tagline' => ['hi' => 'ठेकेदार-प्रथम दस्तावेज़ प्लेटफ़ॉर्म', 'en' => 'Contractor-first documentation'],
    ];
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
                --background: #ffffff;
                --surface: #ffffff;
                --text: #111827;
                --muted: #6b7280;
                --border: #e5e7eb;
                --danger: #f85149;
                --success: #2ea043;
            }
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
            .top-contact {
                background: #f9fafb;
                border-bottom: 1px solid var(--border);
                padding: 8px 0;
                font-size: 12px;
                color: var(--muted);
            }
            .top-contact-inner {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                align-items: center;
                justify-content: space-between;
            }
            .top-contact a { color: var(--text); }
            .nav {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 12px 0;
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
            @media (max-width: 600px) {
                .nav { flex-direction: column; align-items: flex-start; gap: 10px; }
                .nav-links { width: 100%; }
                .wrap { padding: 0 16px; }
                .brand-logo { width: 28px; height: 28px; }
                .brand-logo-image { min-height: 28px; }
                .brand-logo-image img { max-height: 28px; }
            }
        </style>
    </head>
    <body>
        <header>
            <?php if ($isPublicVisitor && $contactDetails): ?>
                <div class="top-contact">
                    <div class="wrap top-contact-inner">
                        <div>
                            <strong><?= sanitize($publicNav['call'][$lang]); ?>:</strong> <a href="tel:<?= sanitize($contactDetails['mobile']); ?>"><?= sanitize($contactDetails['mobile']); ?></a>
                            <span class="muted">•</span>
                            <strong><?= sanitize($publicNav['email'][$lang]); ?>:</strong> <a href="mailto:<?= sanitize($contactDetails['email']); ?>"><?= sanitize($contactDetails['email']); ?></a>
                        </div>
                        <div>
                            <a href="<?= sanitize($contactDetails['instagramUrl']); ?>" target="_blank" rel="noopener">Instagram: <?= sanitize($contactDetails['instagram']); ?></a>
                            <span class="muted">•</span>
                            <a href="<?= sanitize($contactDetails['facebookUrl']); ?>" target="_blank" rel="noopener">Facebook: <?= sanitize($contactDetails['facebook']); ?></a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="wrap nav">
                <div class="brand">
                    <?= render_logo_html('md'); ?>
                    <div>
                        <?= sanitize($appName); ?>
                        <?php if ($isPublicVisitor): ?>
                            <div class="muted" style="font-size:12px;"><?= sanitize($publicNav['tagline'][$lang]); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="nav-links">
                    <?php if ($user && ($user['type'] ?? '') === 'superadmin'): ?>
                        <a href="/home.php"><?= sanitize(t('nav_home')); ?></a>
                        <a href="/superadmin/dashboard.php"><?= sanitize(t('nav_dashboard')); ?></a>
                        <a href="/superadmin/departments.php"><?= sanitize('Departments'); ?></a>
                        <a href="/superadmin/contractors.php"><?= sanitize('Contractors'); ?></a>
                        <a href="/superadmin/employees.php"><?= sanitize('Employees'); ?></a>
                        <a href="/superadmin/ai_studio.php"><?= sanitize('AI Studio'); ?></a>
                        <a href="/superadmin/tender_discovery.php"><?= sanitize('Tender Discovery'); ?></a>
                        <a href="/superadmin/backup.php"><?= sanitize('Backups'); ?></a>
                        <a href="/superadmin/support_dashboard.php"><?= sanitize('Support Inbox'); ?></a>
                        <a href="/superadmin/assisted_v2/queue.php"><?= sanitize('Assisted Pack v2'); ?></a>
                        <a href="/superadmin/error_log.php"><?= sanitize('Error Log'); ?></a>
                        <a href="/superadmin/factory_reset.php"><?= sanitize('Factory Reset'); ?></a>
                        <a href="/superadmin/stats.php"><?= sanitize('Stats'); ?></a>
                        <a href="/superadmin/reset_requests.php"><?= sanitize('Reset Approvals'); ?></a>
                        <a href="/superadmin/profile.php"><?= sanitize(t('profile')); ?></a>
                        <form method="post" action="/auth/logout.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <button type="submit" class="nav-link"><?= sanitize(t('logout')); ?></button>
                        </form>
                    <?php elseif ($user && ($user['type'] ?? '') === 'department'): ?>
                        <a href="/home.php"><?= sanitize(t('nav_home')); ?></a>
                        <a href="/department/dashboard.php"><?= sanitize('Department'); ?></a>
                        <a href="/department/roles.php"><?= sanitize('Roles'); ?></a>
                        <a href="/department/users.php"><?= sanitize('Users'); ?></a>
                        <a href="/department/contractors.php"><?= sanitize('Contractors'); ?></a>
                        <a href="/department/contractor_requests.php"><?= sanitize('Requests'); ?></a>
                        <a href="/department/templates.php"><?= sanitize('Templates'); ?></a>
                        <a href="/department/quick_doc.php"><?= sanitize('Quick Doc'); ?></a>
                        <a href="/department/docs_inbox.php"><?= sanitize('Docs'); ?></a>
                        <a href="/department/tenders.php"><?= sanitize('Tenders'); ?></a>
                        <a href="/department/workorders.php"><?= sanitize('Workorders'); ?></a>
                        <a href="/department/requirement_sets.php"><?= sanitize('Requirement Sets'); ?></a>
                        <a href="/department/dak.php"><?= sanitize('DAK'); ?></a>
                        <a href="/department/health.php"><?= sanitize('Health'); ?></a>
                        <a href="/department/support.php"><?= sanitize('Support'); ?></a>
                        <form method="post" action="/department/logout.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <button type="submit" class="nav-link"><?= sanitize(t('logout')); ?></button>
                        </form>
                    <?php elseif ($user && ($user['type'] ?? '') === 'contractor'): ?>
                        <a href="/home.php"><?= sanitize(t('nav_home')); ?></a>
                        <a href="/contractor/dashboard.php"><?= sanitize('Contractor'); ?></a>
                        <a href="/contractor/departments.php"><?= sanitize('Departments'); ?></a>
                        <a href="/contractor/packs.php"><?= sanitize('Tender Packs'); ?></a>
                        <a href="/contractor/vault.php"><?= sanitize('Vault'); ?></a>
                        <a href="/contractor/bills.php"><?= sanitize('Bills'); ?></a>
                        <a href="/contractor/workorders.php"><?= sanitize('Workorders'); ?></a>
                        <a href="/contractor/tenders.php"><?= sanitize('Tenders'); ?></a>
                        <a href="/contractor/templates.php"><?= sanitize('Templates'); ?></a>
                        <a href="/contractor/discovered_tenders.php"><?= sanitize('Discovered'); ?></a>
                        <a href="/contractor/offline_tenders.php"><?= sanitize('Offline Tenders'); ?></a>
                        <a href="/contractor/tender_archive.php"><?= sanitize('Tender Archive'); ?></a>
                        <a href="/contractor/notifications.php"><?= sanitize('Notifications'); ?></a>
                        <a href="/contractor/support.php"><?= sanitize('Support'); ?></a>
                        <form method="post" action="/contractor/logout.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <button type="submit" class="nav-link"><?= sanitize(t('logout')); ?></button>
                        </form>
                    <?php elseif ($user && ($user['type'] ?? '') === 'employee'): ?>
                        <a href="/home.php"><?= sanitize(t('nav_home')); ?></a>
                        <a href="/staff/dashboard.php"><?= sanitize('Staff'); ?></a>
                        <?php if (in_array('tickets', $user['permissions'] ?? [], true)): ?>
                            <a href="/staff/tickets.php"><?= sanitize('Tickets'); ?></a>
                        <?php endif; ?>
                        <?php if (in_array('can_process_assisted', $user['permissions'] ?? [], true)): ?>
                            <a href="/staff/assisted_v2/queue.php"><?= sanitize('Assisted Pack v2'); ?></a>
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
                        <a href="/site/index.php"><?= sanitize($publicNav['home'][$lang]); ?></a>
                        <a href="/site/index.php#how-it-works"><?= sanitize($publicNav['how'][$lang]); ?></a>
                        <a href="/site/index.php#features"><?= sanitize($publicNav['features'][$lang]); ?></a>
                        <a href="/site/index.php#templates-packs"><?= sanitize($publicNav['templates'][$lang]); ?></a>
                        <a href="/site/contact.php"><?= sanitize($publicNav['contact'][$lang]); ?></a>
                    <?php endif; ?>
                    <?php if (!$user): ?>
                        <form method="get" class="lang-toggle">
                            <span class="pill"><?= sanitize(t('language')); ?></span>
                            <select name="lang" onchange="this.form.submit()">
                                <option value="en" <?= $lang === 'en' ? 'selected' : ''; ?>><?= sanitize(t('english')); ?></option>
                                <option value="hi" <?= $lang === 'hi' ? 'selected' : ''; ?>><?= sanitize(t('hindi')); ?></option>
                            </select>
                        </form>
                        <a href="/department/login.php" class="secondary"><?= sanitize($publicNav['departmentLogin'][$lang]); ?></a>
                        <a href="/contractor/login.php" class="secondary"><?= sanitize($publicNav['contractorLogin'][$lang]); ?></a>
                        <a href="/auth/login.php" class="primary"><?= sanitize($publicNav['yojakLogin'][$lang]); ?></a>
                    <?php else: ?>
                        <form method="get" class="lang-toggle">
                            <span class="pill"><?= sanitize(t('language')); ?></span>
                            <select name="lang" onchange="this.form.submit()">
                                <option value="en" <?= $lang === 'en' ? 'selected' : ''; ?>><?= sanitize(t('english')); ?></option>
                                <option value="hi" <?= $lang === 'hi' ? 'selected' : ''; ?>><?= sanitize(t('hindi')); ?></option>
                            </select>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </header>
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
    </body>
    </html>
    <?php
}
