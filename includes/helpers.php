<?php
declare(strict_types=1);

const BASE_PATH = __DIR__ . '/..';
const DATA_PATH = BASE_PATH . '/data';

function ensureDirectory(string $path): void {
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function isoNow(): string {
    return date('c');
}

function formatDateTime(?string $value): string {
    if (empty($value)) {
        return '-';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '-';
    }
    return date('d M Y, h:i A', $timestamp);
}

function readJson(string $path, array $default = []): array {
    if (!file_exists($path)) {
        return $default;
    }

    $handle = fopen($path, 'r');
    if ($handle === false) {
        throw new RuntimeException("Unable to open {$path} for reading");
    }

    if (!flock($handle, LOCK_SH)) {
        fclose($handle);
        throw new RuntimeException("Unable to lock {$path} for reading");
    }

    $contents = stream_get_contents($handle) ?: '';
    flock($handle, LOCK_UN);
    fclose($handle);

    $decoded = json_decode($contents, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Invalid JSON in {$path}: " . json_last_error_msg());
    }

    return is_array($decoded) ? $decoded : $default;
}

function writeJsonAtomic(string $path, array $data): void {
    $dir = dirname($path);
    ensureDirectory($dir);

    $lockFile = $path . '.lock';
    $lockHandle = fopen($lockFile, 'c');
    if ($lockHandle === false) {
        throw new RuntimeException("Unable to open lock file for {$path}");
    }

    if (!flock($lockHandle, LOCK_EX)) {
        fclose($lockHandle);
        throw new RuntimeException("Unable to lock {$path}");
    }

    $tempPath = tempnam($dir, 'json');
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        throw new RuntimeException('Unable to encode JSON');
    }

    if (file_put_contents($tempPath, $json) === false) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        throw new RuntimeException("Unable to write temporary file for {$path}");
    }

    if (!rename($tempPath, $path)) {
        @unlink($tempPath);
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        throw new RuntimeException("Unable to replace {$path} atomically");
    }

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}

function dataPath(string $path = ''): string {
    return rtrim(DATA_PATH . ($path ? '/' . ltrim($path, '/') : ''), '/');
}

function logEvent(string $file, array $context): void {
    $logPath = dataPath('logs/' . $file);
    ensureDirectory(dirname($logPath));
    $context['timestamp'] = isoNow();
    file_put_contents($logPath, json_encode($context, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function defaultConfig(): array {
    return [
        'appName' => 'YOJAK',
        'timezone' => 'Asia/Kolkata',
        'langDefault' => 'hi',
        'security' => [
            'csrfSecretRotationDays' => 30,
            'rateLimit' => [
                'windowSeconds' => 900,
                'maxAttempts' => 8,
                'blockSeconds' => 1800,
            ],
        ],
        'restrictions' => 'No bid values or rates are allowed anywhere in the application.',
    ];
}

function loadConfig(): array {
    $configPath = dataPath('config/app.json');
    ensureDirectory(dirname($configPath));
    if (!file_exists($configPath)) {
        writeJsonAtomic($configPath, defaultConfig());
    }

    $config = readJson($configPath, defaultConfig());
    return array_merge(defaultConfig(), $config);
}

function ensureUserFiles(): void {
    $superAdminPath = dataPath('users/superadmin.json');
    ensureDirectory(dirname($superAdminPath));
    if (!file_exists($superAdminPath)) {
        $now = isoNow();
        writeJsonAtomic($superAdminPath, [
            'type' => 'superadmin',
            'username' => 'superadmin',
            'passwordHash' => password_hash('pass123', PASSWORD_DEFAULT),
            'mustResetPassword' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
            'lastLoginAt' => null,
            'failedLoginCount' => 0,
            'status' => 'active',
        ]);
    }

    $indexPath = dataPath('users/index.json');
    ensureDirectory(dirname($indexPath));
    if (!file_exists($indexPath)) {
        writeJsonAtomic($indexPath, ['users' => ['superadmin']]);
    }
}

function ensureDataScaffolding(): void {
    ensureDirectory(dataPath('security/ratelimits'));
    ensureDirectory(dataPath('logs'));
    ensureDirectory(dataPath('locks'));
    ensureDirectory(dataPath('sessions'));
    ensureDirectory(dataPath('departments'));
}

function translations(): array {
    return [
        'en' => [
            'appName' => 'YOJAK',
            'welcome' => 'Welcome to YOJAK',
            'homeLead' => 'A secure foundation for project governance.',
            'login' => 'Login',
            'logout' => 'Logout',
            'username' => 'Username',
            'password' => 'Password',
            'rememberLanguage' => 'Language',
            'languageToggle' => 'Switch to Hindi',
            'languageToggleShort' => 'HI',
            'languageCurrent' => 'English',
            'submit' => 'Submit',
            'superadminOnly' => 'Superadmin login only for now.',
            'dashboard' => 'Dashboard',
            'profile' => 'Profile',
            'resetPassword' => 'Reset Password',
            'forceResetTitle' => 'Password Reset Required',
            'newPassword' => 'New Password',
            'confirmPassword' => 'Confirm Password',
            'resetCta' => 'Update Password',
            'lastLogin' => 'Last Login',
            'mustReset' => 'Password reset required',
            'statusActive' => 'Active',
            'healthCheck' => 'Health Check',
            'homeCta' => 'Explore secure admin access.',
            'errorFriendly' => 'Something went wrong. Our team has been notified.',
            'backHome' => 'Back to home',
            'rateLimited' => 'Too many attempts. Please try again later.',
            'csrfInvalid' => 'Security validation failed. Please refresh and try again.',
            'passwordRules' => 'Password must be at least 8 characters and differ from the previous password.',
            'loggedOut' => 'You have been logged out.',
            'formErrors' => 'Please fix the issues below.',
            'langEnglish' => 'English',
            'langHindi' => 'Hindi',
            'navNote' => 'No bid values or rates are permitted.',
            'resetRequiredBanner' => 'You must reset your password before continuing.',
            'loginButton' => 'Sign In',
        ],
        'hi' => [
            'appName' => 'योज़क',
            'welcome' => 'योज़क में आपका स्वागत है',
            'homeLead' => 'परियोजना प्रबंधन के लिए सुरक्षित आधार।',
            'login' => 'लॉगिन',
            'logout' => 'लॉगआउट',
            'username' => 'उपयोगकर्ता नाम',
            'password' => 'पासवर्ड',
            'rememberLanguage' => 'भाषा',
            'languageToggle' => 'अंग्रेजी में बदलें',
            'languageToggleShort' => 'EN',
            'languageCurrent' => 'हिंदी',
            'submit' => 'सबमिट',
            'superadminOnly' => 'अभी केवल सुपरएडमिन लॉगिन।',
            'dashboard' => 'डैशबोर्ड',
            'profile' => 'प्रोफ़ाइल',
            'resetPassword' => 'पासवर्ड बदलें',
            'forceResetTitle' => 'पासवर्ड बदलना आवश्यक है',
            'newPassword' => 'नया पासवर्ड',
            'confirmPassword' => 'पासवर्ड की पुष्टि करें',
            'resetCta' => 'पासवर्ड अपडेट करें',
            'lastLogin' => 'अंतिम लॉगिन',
            'mustReset' => 'पासवर्ड रीसेट आवश्यक',
            'statusActive' => 'सक्रिय',
            'healthCheck' => 'स्वास्थ्य जांच',
            'homeCta' => 'सुरक्षित एडमिन एक्सेस देखें।',
            'errorFriendly' => 'कुछ गलत हो गया। कृपया बाद में पुनः प्रयास करें।',
            'backHome' => 'होम पर वापस जाएँ',
            'rateLimited' => 'बहुत अधिक प्रयास। कृपया बाद में प्रयास करें।',
            'csrfInvalid' => 'सुरक्षा जांच विफल हुई। कृपया पृष्ठ ताज़ा करें।',
            'passwordRules' => 'पासवर्ड कम से कम 8 वर्णों का हो और पुराना पासवर्ड न हो।',
            'loggedOut' => 'आप लॉगआउट हो गए हैं।',
            'formErrors' => 'कृपया नीचे दिए गए मुद्दों को ठीक करें।',
            'langEnglish' => 'English',
            'langHindi' => 'हिंदी',
            'navNote' => 'बोली/रेट की प्रविष्टियाँ प्रतिबंधित हैं।',
            'resetRequiredBanner' => 'आगे बढ़ने से पहले पासवर्ड बदलें।',
            'loginButton' => 'साइन इन',
        ],
    ];
}

function setLanguage(string $lang): void {
    $_SESSION['lang'] = $lang;
    setcookie('yojak_lang', $lang, ['expires' => time() + 60 * 60 * 24 * 30, 'path' => '/', 'httponly' => false]);
}

function getLanguage(array $config): string {
    $allowed = ['en', 'hi'];
    if (isset($_GET['lang']) && in_array($_GET['lang'], $allowed, true)) {
        setLanguage($_GET['lang']);
    }

    if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], $allowed, true)) {
        return $_SESSION['lang'];
    }

    if (isset($_COOKIE['yojak_lang']) && in_array($_COOKIE['yojak_lang'], $allowed, true)) {
        $_SESSION['lang'] = $_COOKIE['yojak_lang'];
        return $_COOKIE['yojak_lang'];
    }

    return in_array($config['langDefault'] ?? 'hi', $allowed, true) ? ($config['langDefault'] ?? 'hi') : 'hi';
}

function t(string $key, string $lang): string {
    $map = translations();
    if (isset($map[$lang][$key])) {
        return $map[$lang][$key];
    }
    if (isset($map['en'][$key])) {
        return $map['en'][$key];
    }
    return $key;
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool {
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfInput(): string {
    $token = htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'][$type] = $message;
}

function getFlash(string $type): ?string {
    if (isset($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

function renderLayoutStart(string $title, string $lang, array $config, ?array $user = null, bool $showNav = true): void {
    $appName = escape($config['appName'] ?? 'YOJAK');
    $displayTitle = escape($title);
    $toggleLabel = $lang === 'en' ? t('languageToggleShort', 'en') : t('languageToggleShort', 'hi');
    $otherLang = $lang === 'en' ? 'hi' : 'en';
    $langName = $lang === 'en' ? t('langEnglish', 'en') : t('langHindi', 'hi');
    $navNote = t('navNote', $lang);
    $userType = $user['type'] ?? null;
    echo "<!DOCTYPE html><html lang=\"{$lang}\"><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">";
    echo "<title>{$displayTitle} | {$appName}</title>";
    echo '<style>' . baseStyles() . '</style>';
    echo '</head><body>';
    if ($showNav) {
        echo '<header class="topbar">';
        echo '<div class="logo">YOJAK</div>';
        echo '<div class="nav-links">';
        echo '<a href="/site/index.php">' . t('welcome', $lang) . '</a>';
        if ($user) {
            if ($userType === 'superadmin') {
                echo '<a href="/superadmin/dashboard.php">' . t('dashboard', $lang) . '</a>';
                echo '<a href="/superadmin/departments.php">Departments</a>';
                echo '<a href="/superadmin/profile.php">' . t('profile', $lang) . '</a>';
            } elseif ($userType === 'department') {
                echo '<a href="/department/dashboard.php">Dept Dashboard</a>';
            }
        } else {
            echo '<a href="/auth/login.php">' . t('login', $lang) . '</a>';
            echo '<a href="/department/login.php">Department Login</a>';
        }
        echo '</div>';
        echo '<div class="nav-actions">';
        echo '<span class="nav-note">' . escape($navNote) . '</span>';
        echo '<a class="chip" href="?lang=' . $otherLang . '" aria-label="' . escape(t('rememberLanguage', $lang)) . '">' . escape($toggleLabel) . '</a>';
        if ($user) {
            $logoutAction = $userType === 'department' ? '/department/logout.php' : '/auth/logout.php';
            echo '<form class="inline" method="POST" action="' . $logoutAction . '">' . csrfInput() . '<button class="ghost" type="submit">' . t('logout', $lang) . '</button></form>';
        }
        echo '</div>';
        echo '</header>';
    }
    echo '<main class="page">';
    echo '<div class="page-header"><div><p class="eyebrow">' . escape($langName) . '</p><h1>' . $displayTitle . '</h1></div></div>';
}

function renderLayoutEnd(): void {
    echo '</div></main>';
    echo '</body></html>';
}

function baseStyles(): string {
    return <<<CSS
    :root {
        --bg: #0f172a;
        --card: #111827;
        --muted: #94a3b8;
        --accent: #06b6d4;
        --accent-2: #22c55e;
        --danger: #ef4444;
        --text: #e2e8f0;
        --border: #1f2937;
        --shadow: 0 10px 30px rgba(0,0,0,0.25);
        --font: 'Inter', system-ui, -apple-system, sans-serif;
    }
    * { box-sizing: border-box; }
    body { margin:0; font-family: var(--font); background: linear-gradient(135deg, #0f172a, #0b1120); color: var(--text); }
    a { color: var(--accent); text-decoration: none; }
    .topbar { position: sticky; top:0; z-index:10; display:flex; align-items:center; justify-content:space-between; padding:14px 20px; background: rgba(15,23,42,0.85); backdrop-filter: blur(10px); border-bottom:1px solid var(--border); }
    .logo { font-weight: 800; letter-spacing: 1px; }
    .nav-links { display:flex; gap:16px; align-items:center; }
    .nav-links a { color: var(--text); opacity:0.8; padding:6px 10px; border-radius:8px; transition: all 0.2s; }
    .nav-links a:hover { background: rgba(255,255,255,0.04); opacity:1; }
    .nav-actions { display:flex; gap:10px; align-items:center; }
    .chip { padding:6px 10px; border:1px solid var(--border); border-radius:999px; color: var(--text); background: rgba(255,255,255,0.04); font-weight:600; }
    .inline { display:inline; }
    .ghost { background: transparent; border:1px solid var(--border); color: var(--text); padding:8px 14px; border-radius:10px; cursor:pointer; }
    .ghost:hover { border-color: var(--accent); color: var(--accent); }
    .page { max-width: 960px; margin: 0 auto; padding: 28px 18px 60px; }
    .page-header { display:flex; align-items:center; justify-content: space-between; margin-bottom: 18px; }
    .eyebrow { text-transform: uppercase; letter-spacing: 1.2px; font-size: 12px; color: var(--muted); margin: 0 0 6px 0; }
    h1 { margin:0; font-size: 28px; }
    .card { background: var(--card); border:1px solid var(--border); border-radius: 16px; box-shadow: var(--shadow); padding: 22px; margin-bottom: 18px; }
    .card h2 { margin-top:0; margin-bottom:10px; }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; }
    .btn { background: linear-gradient(135deg, var(--accent), #3b82f6); border:none; color:white; padding: 12px 16px; border-radius: 12px; cursor:pointer; font-weight:700; width:100%; }
    .btn:hover { transform: translateY(-1px); box-shadow: var(--shadow); }
    .input-group { display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
    .input-group label { color: var(--muted); font-size: 14px; }
    .input-group input { padding: 12px; border-radius: 10px; border:1px solid var(--border); background: #0b1224; color: var(--text); }
    .text-muted { color: var(--muted); }
    .badge { display:inline-block; padding:6px 10px; background: rgba(34,197,94,0.15); color:#4ade80; border-radius: 999px; font-weight:600; font-size:12px; }
    .alert { padding:12px; border-radius:12px; margin-bottom: 12px; border:1px solid var(--border); }
    .alert-danger { background: rgba(239,68,68,0.12); color:#fecdd3; }
    .alert-info { background: rgba(6,182,212,0.12); color:#67e8f9; }
    .form-actions { display:flex; justify-content: flex-end; }
    .friendly-error { text-align:center; padding: 32px; }
    .nav-note { font-size:12px; color: var(--muted); }
    .table { width:100%; border-collapse: collapse; margin-top: 12px; }
    .table th, .table td { text-align:left; padding: 12px; border-bottom:1px solid var(--border); }
    .table th { color: var(--muted); font-size: 12px; letter-spacing: 0.5px; text-transform: uppercase; }
    .table tr:hover td { background: rgba(255,255,255,0.02); }
    .pill { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background: rgba(6,182,212,0.15); color:#67e8f9; font-weight:600; font-size:12px; }
    .pill.danger { background: rgba(239,68,68,0.15); color:#fecdd3; }
    .pill.muted { background: rgba(148,163,184,0.15); color:#cbd5e1; }
    .stack { display:flex; flex-direction:column; gap:6px; }
    .row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .row .input-group { flex:1; margin:0; }
    .ghost-btn { background: transparent; border:1px solid var(--border); color: var(--text); padding:8px 14px; border-radius: 10px; cursor:pointer; }
    .ghost-btn:hover { border-color: var(--accent); color: var(--accent); }
    .hint { font-size:12px; color: var(--muted); }
    .section-title { display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .card-subtitle { color: var(--muted); margin-top:0; }
    @media(max-width: 640px) {
        .topbar { flex-wrap: wrap; gap:10px; }
        .nav-links { width:100%; }
        .nav-actions { width:100%; justify-content: flex-start; }
        .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
    }
    CSS;
}

function renderErrorPage(string $lang, array $config): void {
    http_response_code(500);
    renderLayoutStart('Error', $lang, $config, null, false);
    echo '<div class="friendly-error card">';
    echo '<h2>' . escape(t('errorFriendly', $lang)) . '</h2>';
    echo '<p class="text-muted">' . escape(t('backHome', $lang)) . '</p>';
    echo '<a class="btn" href="/site/index.php">' . t('backHome', $lang) . '</a>';
    echo '</div>';
    renderLayoutEnd();
}
