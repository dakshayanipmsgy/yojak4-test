<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/departments.php';
require_once __DIR__ . '/includes/safe_page.php';

// Enforce timezone before any timestamps are generated.
date_default_timezone_set('Asia/Kolkata');

ensureDataScaffolding();
$config = loadConfig();
date_default_timezone_set($config['timezone'] ?? 'Asia/Kolkata');
ensureUserFiles();

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(dataPath('sessions'));
    session_start([
        'cookie_lifetime' => 60 * 60 * 24,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

$lang = getLanguage($config);
