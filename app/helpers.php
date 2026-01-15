<?php
declare(strict_types=1);

function set_default_timezone(): void
{
    $config = get_app_config();
    $timezone = $config['timezone'] ?? 'Asia/Kolkata';
    date_default_timezone_set($timezone);
}

function start_app_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('yojak_session');
        session_start();
    }
}

function ensure_data_structure(): void
{
    $directories = [
        DATA_PATH,
        DATA_PATH . '/config',
        DATA_PATH . '/users',
        DATA_PATH . '/sessions',
        DATA_PATH . '/security/ratelimits',
        DATA_PATH . '/security/password_resets',
        DATA_PATH . '/security/password_resets/ratelimits',
        DATA_PATH . '/staff/employees',
        DATA_PATH . '/backups',
        DATA_PATH . '/logs',
        DATA_PATH . '/locks',
        DATA_PATH . '/ai',
        DATA_PATH . '/discovery',
        DATA_PATH . '/discovery/discovered',
        DATA_PATH . '/support',
        DATA_PATH . '/support/tickets',
        DATA_PATH . '/support/uploads',
        DATA_PATH . '/logs/runtime_errors',
        DATA_PATH . '/logs/public_tenders',
        DATA_PATH . '/assisted_v2',
        DATA_PATH . '/assisted_v2/requests',
        DATA_PATH . '/assisted_v2/templates',
        DATA_PATH . '/defaults',
        DATA_PATH . '/site',
        DATA_PATH . '/site/analytics',
        DATA_PATH . '/site/inquiries',
        DATA_PATH . '/suggestions',
        DATA_PATH . '/ratelimits',
        DATA_PATH . '/ratelimits/suggestions',
        DATA_PATH . '/otp',
        DATA_PATH . '/otp/contractor_signup',
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $configPath = DATA_PATH . '/config/app.json';
    if (!file_exists($configPath)) {
        $defaultConfig = [
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
            'notes' => 'No bid value/rates stored anywhere in this application.',
        ];
        writeJsonAtomic($configPath, $defaultConfig);
    }

    $userPath = DATA_PATH . '/users/superadmin.json';
    if (!file_exists($userPath)) {
        $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $user = [
            'type' => 'superadmin',
            'username' => 'superadmin',
            'passwordHash' => password_hash('pass123', PASSWORD_DEFAULT),
            'mustResetPassword' => true,
            'createdAt' => $now->format(DateTime::ATOM),
            'updatedAt' => $now->format(DateTime::ATOM),
            'lastLoginAt' => null,
            'failedLoginCount' => 0,
            'status' => 'active',
        ];
        writeJsonAtomic($userPath, $user);
    }

    $userIndexPath = DATA_PATH . '/users/index.json';
    if (!file_exists($userIndexPath)) {
        $index = [
            'superadmin' => [
                'type' => 'superadmin',
                'path' => 'superadmin.json',
            ],
        ];
        writeJsonAtomic($userIndexPath, $index);
    }

    $staffIndexPath = DATA_PATH . '/staff/employees/index.json';
    if (!file_exists($staffIndexPath)) {
        writeJsonAtomic($staffIndexPath, []);
    }

    $passwordResetIndex = DATA_PATH . '/security/password_resets/index.json';
    if (!file_exists($passwordResetIndex)) {
        writeJsonAtomic($passwordResetIndex, []);
    }

    $supportIndex = DATA_PATH . '/support/tickets/index.json';
    if (!file_exists($supportIndex)) {
        writeJsonAtomic($supportIndex, []);
    }

    $logFiles = [
        DATA_PATH . '/logs/superadmin.log',
        DATA_PATH . '/logs/auth.log',
        DATA_PATH . '/logs/backup.log',
        DATA_PATH . '/logs/reset.log',
        DATA_PATH . '/logs/tender_discovery.log',
        DATA_PATH . '/logs/support.log',
        DATA_PATH . '/logs/linking.log',
        DATA_PATH . '/logs/tenders_publication.log',
        DATA_PATH . '/logs/packs.log',
        DATA_PATH . '/logs/assisted_v2.log',
        DATA_PATH . '/logs/print.log',
        DATA_PATH . '/logs/contractor_profile.log',
        DATA_PATH . '/logs/templates.log',
        DATA_PATH . '/logs/site.log',
        DATA_PATH . '/logs/suggestions.log',
        DATA_PATH . '/logs/whatsapp.log',
        DATA_PATH . '/logs/email.log',
        DATA_PATH . '/logs/otp.log',
    ];
    foreach ($logFiles as $logFile) {
        if (!file_exists($logFile)) {
            touch($logFile);
        }
    }

    $visitsPath = DATA_PATH . '/site/analytics/visits.json';
    if (!file_exists($visitsPath)) {
        $now = now_kolkata();
        $date = $now->format('Y-m-d');
        $defaultVisits = [
            'totalPageViews' => 0,
            'totalUniqueVisitors' => 0,
            'daily' => [
                $date => [
                    'pageViews' => 0,
                    'uniqueVisitors' => 0,
                ],
            ],
            'updatedAt' => $now->format(DateTime::ATOM),
        ];
        writeJsonAtomic($visitsPath, $defaultVisits);
    }

    $visitsLogPath = DATA_PATH . '/site/analytics/visits_log.jsonl';
    if (!file_exists($visitsLogPath)) {
        touch($visitsLogPath);
    }

    if (!file_exists(default_contractor_templates_path())) {
        default_contractor_templates();
    }
}

function initialize_php_error_logging(): void
{
    $errorLog = DATA_PATH . '/logs/php_errors.log';
    if (!file_exists($errorLog)) {
        touch($errorLog);
    }
    ini_set('log_errors', '1');
    ini_set('error_log', $errorLog);
}

function get_app_config(): array
{
    $path = DATA_PATH . '/config/app.json';
    if (!file_exists($path)) {
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
        ];
    }
    return readJson($path);
}

function readJson(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $handle = fopen($path, 'r');
    if (!$handle) {
        return [];
    }
    flock($handle, LOCK_SH);
    $content = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    $data = json_decode((string)$content, true);
    if (!is_array($data)) {
        $data = [];
    }

    return $data;
}

function writeJsonAtomic(string $path, array $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $tempPath = $path . '.tmp';
    $handle = fopen($tempPath, 'c');
    if (!$handle) {
        throw new RuntimeException('Unable to open temp file for writing JSON.');
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new RuntimeException('Unable to acquire lock for writing JSON.');
    }

    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        flock($handle, LOCK_UN);
        fclose($handle);
        throw new RuntimeException('Failed to encode JSON data.');
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, $encoded);
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    rename($tempPath, $path);
}

function logEvent(string $file, array $context): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $context['timestamp'] = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format(DateTime::ATOM);
    $line = json_encode($context, JSON_UNESCAPED_SLASHES);
    $handle = fopen($file, 'a');
    if ($handle) {
        flock($handle, LOCK_EX);
        fwrite($handle, $line . PHP_EOL);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function now_kolkata(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
}

function sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function mask_ip(string $ip): string
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            $parts[3] = 'xxx';
            return implode('.', $parts);
        }
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return substr($ip, 0, 6) . '::xxxx';
    }
    return $ip !== '' ? 'masked' : 'unknown';
}

function mask_mobile(string $mobile): string
{
    $digits = preg_replace('/\D+/', '', $mobile);
    if (strlen($digits) === 10) {
        return substr($digits, 0, 2) . '******' . substr($digits, -2);
    }
    if (strlen($digits) > 4) {
        return substr($digits, 0, 2) . str_repeat('*', max(2, strlen($digits) - 4)) . substr($digits, -2);
    }
    return $mobile !== '' ? '******' : 'unknown';
}

function generate_temp_password(int $length = 12): string
{
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghjkmnpqrstuvwxyz';
    $digits = '23456789';
    $all = $upper . $lower . $digits;

    $password = $upper[random_int(0, strlen($upper) - 1)]
        . $lower[random_int(0, strlen($lower) - 1)]
        . $digits[random_int(0, strlen($digits) - 1)];

    while (strlen($password) < $length) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }

    return str_shuffle($password);
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function render_error_page(string $message = ''): void
{
    $title = get_app_config()['appName'] . ' | Error';
    render_layout($title, function () use ($message) {
        ?>
        <div class="card error-card">
            <h2><?= sanitize(t('error_title')); ?></h2>
            <p><?= sanitize($message !== '' ? $message : t('error_generic')); ?></p>
        </div>
        <?php
    });
}
