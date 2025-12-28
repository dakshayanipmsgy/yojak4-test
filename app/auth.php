<?php
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_created'] = time();
    }

    $config = get_app_config();
    $rotationDays = (int)($config['security']['csrfSecretRotationDays'] ?? 30);
    $created = $_SESSION['csrf_token_created'] ?? time();
    if ((time() - $created) > ($rotationDays * 86400)) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_created'] = time();
    }

    return $_SESSION['csrf_token'];
}

function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        logEvent(DATA_PATH . '/logs/auth.log', [
            'event' => 'csrf_failed',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
        render_error_page(t('csrf_invalid'));
        exit;
    }
}

function normalize_login_identifier(string $value): string
{
    return strtolower(trim($value));
}

function rate_limit_key(string $loginId): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    return hash('sha256', $ip . '|' . $ua . '|' . normalize_login_identifier($loginId));
}

function password_reset_rate_limit_path(string $key): string
{
    return DATA_PATH . '/security/password_resets/ratelimits/' . $key . '.json';
}

function password_reset_rate_limit_state(string $key): array
{
    $path = password_reset_rate_limit_path($key);
    $state = readJson($path);
    if (!isset($state['attempts'])) {
        $state['attempts'] = [];
    }
    return $state;
}

function password_reset_rate_limit_allowed(string $key, int $windowSeconds, int $maxAttempts): bool
{
    $state = password_reset_rate_limit_state($key);
    $now = time();
    $state['attempts'] = array_values(array_filter(
        $state['attempts'],
        fn($timestamp) => ($now - (int)$timestamp) <= $windowSeconds
    ));
    writeJsonAtomic(password_reset_rate_limit_path($key), $state);
    return count($state['attempts']) < $maxAttempts;
}

function password_reset_rate_limit_record(string $key, int $windowSeconds, int $maxAttempts): void
{
    $state = password_reset_rate_limit_state($key);
    $now = time();
    $state['attempts'] = array_values(array_filter(
        $state['attempts'],
        fn($timestamp) => ($now - (int)$timestamp) <= $windowSeconds
    ));
    if (count($state['attempts']) < $maxAttempts) {
        $state['attempts'][] = $now;
    }
    writeJsonAtomic(password_reset_rate_limit_path($key), $state);
}

function fetch_rate_limit_state(string $key): array
{
    $path = DATA_PATH . '/security/ratelimits/' . $key . '.json';
    $state = readJson($path);
    if (!isset($state['attempts'])) {
        $state['attempts'] = [];
    }
    if (!array_key_exists('blockedUntil', $state)) {
        $state['blockedUntil'] = null;
    }
    return $state;
}

function save_rate_limit_state(string $key, array $state): void
{
    $path = DATA_PATH . '/security/ratelimits/' . $key . '.json';
    writeJsonAtomic($path, $state);
}

function check_rate_limit(string $key): bool
{
    $config = get_app_config();
    $rlConfig = $config['security']['rateLimit'] ?? ['windowSeconds' => 900, 'maxAttempts' => 8, 'blockSeconds' => 1800];
    $window = (int)($rlConfig['windowSeconds'] ?? 900);
    $maxAttempts = (int)($rlConfig['maxAttempts'] ?? 8);

    $state = fetch_rate_limit_state($key);
    $now = time();

    if (!empty($state['blockedUntil']) && $state['blockedUntil'] > $now) {
        return false;
    }

    $state['attempts'] = array_values(array_filter(
        $state['attempts'],
        fn($timestamp) => ($now - (int)$timestamp) <= $window
    ));
    save_rate_limit_state($key, $state);

    return count($state['attempts']) < $maxAttempts;
}

function record_rate_limit_attempt(string $key, bool $success): void
{
    $config = get_app_config();
    $rlConfig = $config['security']['rateLimit'] ?? ['windowSeconds' => 900, 'maxAttempts' => 8, 'blockSeconds' => 1800];
    $window = (int)($rlConfig['windowSeconds'] ?? 900);
    $maxAttempts = (int)($rlConfig['maxAttempts'] ?? 8);
    $blockSeconds = (int)($rlConfig['blockSeconds'] ?? 1800);

    $state = fetch_rate_limit_state($key);
    $now = time();
    $state['attempts'] = array_values(array_filter(
        $state['attempts'],
        fn($timestamp) => ($now - (int)$timestamp) <= $window
    ));

    if ($success) {
        $state['attempts'] = [];
        $state['blockedUntil'] = null;
    } else {
        $state['attempts'][] = $now;
        if (count($state['attempts']) >= $maxAttempts) {
            $state['blockedUntil'] = $now + $blockSeconds;
        }
    }

    save_rate_limit_state($key, $state);
}

function get_user_record(string $username): ?array
{
    $normalized = normalize_login_identifier($username);
    if ($normalized !== 'superadmin') {
        return null;
    }
    $path = DATA_PATH . '/users/superadmin.json';
    $data = readJson($path);
    return $data ?: null;
}

function persist_user_record(array $user): void
{
    if (($user['username'] ?? '') !== 'superadmin') {
        return;
    }
    $path = DATA_PATH . '/users/superadmin.json';
    writeJsonAtomic($path, $user);
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $auth = [
        'type' => $user['type'] ?? null,
        'deptId' => $user['deptId'] ?? null,
        'fullUserId' => $user['fullUserId'] ?? ($user['username'] ?? null),
        'roleId' => $user['roleId'] ?? null,
        'permissions' => $user['permissions'] ?? [],
    ];
    $_SESSION['user'] = [
        'username' => $user['username'] ?? ($user['fullUserId'] ?? ($user['mobile'] ?? '')),
        'type' => $user['type'],
        'deptId' => $user['deptId'] ?? null,
        'yojId' => $user['yojId'] ?? null,
        'roleId' => $user['roleId'] ?? null,
        'displayName' => $user['displayName'] ?? ($user['username'] ?? ''),
        'mustResetPassword' => $user['mustResetPassword'] ?? false,
        'lastLoginAt' => $user['lastLoginAt'] ?? null,
    ];

    if (($user['type'] ?? '') === 'employee') {
        $_SESSION['user']['empId'] = $user['empId'] ?? null;
        $_SESSION['user']['role'] = $user['role'] ?? null;
        $_SESSION['user']['permissions'] = $user['permissions'] ?? [];
        $_SESSION['user']['status'] = $user['status'] ?? null;
    } elseif (($user['type'] ?? '') === 'department') {
        $_SESSION['user']['fullUserId'] = $user['fullUserId'] ?? null;
        $_SESSION['user']['permissions'] = $user['permissions'] ?? [];
    }

    $_SESSION['auth'] = $auth;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('/auth/login.php');
    }
    return $user;
}

function require_role(string $role): array
{
    $user = current_user();
    if (!$user) {
        if ($role === 'contractor') {
            redirect('/contractor/login.php');
        }
        if ($role === 'department') {
            redirect('/department/login.php');
        }
        redirect('/auth/login.php');
    }
    if (($user['type'] ?? '') === $role && !empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    if (($user['type'] ?? '') !== $role) {
        if ($role === 'contractor') {
            redirect('/contractor/login.php');
        }
        if ($role === 'employee') {
            redirect('/auth/login.php');
        }
        redirect('/auth/login.php');
    }
    return $user;
}

function authenticate_superadmin(string $username, string $password): bool
{
    $record = get_user_record($username);
    if (!$record || ($record['status'] ?? '') !== 'active') {
        return false;
    }
    return password_verify($password, $record['passwordHash'] ?? '');
}

function log_auth_attempt(array $payload): void
{
    $entry = array_merge([
        'at' => now_kolkata()->format(DateTime::ATOM),
    ], $payload);

    $file = DATA_PATH . '/logs/auth.log';
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $handle = fopen($file, 'a');
    if ($handle) {
        flock($handle, LOCK_EX);
        fwrite($handle, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function authenticate_department_user(string $identifier, string $password): array
{
    $parsed = parse_department_login_identifier(normalize_login_identifier($identifier));
    if (!$parsed) {
        return ['record' => null, 'error' => 'invalid_identifier', 'parsed' => null];
    }
    $department = load_department($parsed['deptId']);
    if (!$department || ($department['status'] ?? '') !== 'active') {
        return ['record' => null, 'error' => 'department_inactive', 'parsed' => $parsed];
    }
    $record = load_active_department_user($parsed['fullUserId']);
    if (!$record) {
        return ['record' => null, 'error' => 'not_found', 'parsed' => $parsed];
    }
    if (($record['deptId'] ?? '') !== $parsed['deptId'] || ($record['fullUserId'] ?? '') !== $parsed['fullUserId']) {
        return ['record' => null, 'error' => 'identifier_mismatch', 'parsed' => $parsed];
    }
    if (($record['status'] ?? '') === 'suspended') {
        return ['record' => $record, 'error' => 'suspended', 'parsed' => $parsed];
    }
    if (($record['status'] ?? '') !== 'active') {
        return ['record' => null, 'error' => 'invalid_status', 'parsed' => $parsed];
    }
    if (!password_verify($password, $record['passwordHash'] ?? '')) {
        return ['record' => null, 'error' => 'invalid_credentials', 'parsed' => $parsed];
    }

    $roleId = $record['roleId'] ?? '';
    $role = find_department_role($parsed['deptId'], $roleId);
    if (!$role) {
        return ['record' => null, 'error' => 'role_missing', 'parsed' => $parsed];
    }

    $record['type'] = 'department';
    $record['permissions'] = $role['permissions'] ?? [];

    return ['record' => $record, 'error' => null, 'parsed' => $parsed];
}

function update_last_login(string $username): void
{
    $record = get_user_record($username);
    if (!$record) {
        return;
    }
    $record['lastLoginAt'] = now_kolkata()->format(DateTime::ATOM);
    $record['failedLoginCount'] = 0;
    $record['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    persist_user_record($record);
}

function handle_failed_login(string $username): void
{
    $record = get_user_record($username);
    if (!$record) {
        return;
    }
    $record['failedLoginCount'] = ($record['failedLoginCount'] ?? 0) + 1;
    $record['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    persist_user_record($record);
}

function update_password(string $username, string $newPassword): void
{
    $record = get_user_record($username);
    if (!$record) {
        return;
    }
    $record['passwordHash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $record['mustResetPassword'] = false;
    $record['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    persist_user_record($record);
    $_SESSION['user']['mustResetPassword'] = false;
}

function authenticate_contractor_user(string $mobile, string $password): ?array
{
    $contractor = find_contractor_by_mobile($mobile);
    if (!$contractor || ($contractor['status'] ?? '') !== 'approved') {
        return null;
    }
    if (!password_verify($password, $contractor['passwordHash'] ?? '')) {
        return null;
    }
    return $contractor;
}

function update_contractor_last_login(string $yojId): void
{
    $contractor = load_contractor($yojId);
    if (!$contractor) {
        return;
    }
    $contractor['lastLoginAt'] = now_kolkata()->format(DateTime::ATOM);
    save_contractor($contractor);
}

function authenticate_employee(string $username, string $password): ?array
{
    $record = find_employee_by_username($username);
    if (!$record || ($record['status'] ?? '') !== 'active') {
        return null;
    }

    if (!password_verify($password, $record['passwordHash'] ?? '')) {
        return null;
    }

    return $record;
}
