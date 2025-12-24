<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function currentUser(): ?array {
    if (!isset($_SESSION['username'])) {
        return null;
    }
    return loadUser($_SESSION['username']);
}

function loadUser(string $username): ?array {
    $path = userPath($username);
    if ($path === null || !file_exists($path)) {
        return null;
    }
    $user = readJson($path, []);
    if (!is_array($user)) {
        return null;
    }
    $user['username'] = $username;
    return $user;
}

function saveUser(array $user): void {
    if (empty($user['username'])) {
        throw new RuntimeException('Missing username');
    }
    $path = userPath($user['username']);
    if ($path === null) {
        throw new RuntimeException('Unknown user');
    }
    $user['updatedAt'] = isoNow();
    writeJsonAtomic($path, $user);
}

function userPath(string $username): ?string {
    if ($username === 'superadmin') {
        return dataPath('users/superadmin.json');
    }
    return null;
}

function requireAuth(string $role = 'superadmin'): array {
    $user = currentUser();
    if (!$user) {
        header('Location: /auth/login.php');
        exit;
    }

    if ($role && (($user['type'] ?? '') !== $role)) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access denied';
        exit;
    }

    return $user;
}

function requireNoForceReset(array $user): void {
    if (!empty($user['mustResetPassword'])) {
        header('Location: /auth/force_reset.php');
        exit;
    }
}

function registerLoginSession(array $user): void {
    $_SESSION['username'] = $user['username'];
}

function clearSession(): void {
    session_regenerate_id(true);
    $_SESSION = [];
}

function normalizeLoginId(string $id): string {
    return strtolower(trim($id));
}

function rateLimitKey(string $loginId): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    return sha1($ip . '|' . $ua . '|' . normalizeLoginId($loginId));
}

function getRateLimitRecord(string $key): array {
    $path = dataPath('security/ratelimits/' . $key . '.json');
    return readJson($path, ['key' => $key, 'attempts' => [], 'blockedUntil' => null]);
}

function saveRateLimitRecord(string $key, array $record): void {
    $path = dataPath('security/ratelimits/' . $key . '.json');
    writeJsonAtomic($path, $record);
}

function rateLimitStatus(string $key, array $config): array {
    $record = getRateLimitRecord($key);
    $now = time();
    $windowSeconds = $config['security']['rateLimit']['windowSeconds'] ?? 900;
    $maxAttempts = $config['security']['rateLimit']['maxAttempts'] ?? 8;
    $blockSeconds = $config['security']['rateLimit']['blockSeconds'] ?? 1800;

    $record['attempts'] = array_values(array_filter($record['attempts'], function ($ts) use ($now, $windowSeconds) {
        return $ts >= ($now - $windowSeconds);
    }));

    if (!empty($record['blockedUntil']) && $record['blockedUntil'] > $now) {
        return ['blocked' => true, 'record' => $record];
    }

    if (count($record['attempts']) >= $maxAttempts) {
        $record['blockedUntil'] = $now + $blockSeconds;
        saveRateLimitRecord($key, $record);
        return ['blocked' => true, 'record' => $record];
    }

    return ['blocked' => false, 'record' => $record];
}

function recordFailedAttempt(string $key, array $config): void {
    $status = rateLimitStatus($key, $config);
    $record = $status['record'];
    $record['attempts'][] = time();
    $record['attempts'] = array_slice($record['attempts'], -($config['security']['rateLimit']['maxAttempts'] ?? 8));
    saveRateLimitRecord($key, $record);
}

function clearRateLimit(string $key): void {
    $path = dataPath('security/ratelimits/' . $key . '.json');
    if (file_exists($path)) {
        @unlink($path);
    }
}

function authenticate(string $username, string $password, array $config): array {
    $user = loadUser($username);
    $key = rateLimitKey($username);

    $rate = rateLimitStatus($key, $config);
    if ($rate['blocked']) {
        logEvent('auth.log', [
            'event' => 'rate_limit_block',
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'record' => $rate['record'],
        ]);
        return ['success' => false, 'message' => 'rate_limited'];
    }

    if (!$user || ($user['status'] ?? '') !== 'active') {
        recordFailedAttempt($key, $config);
        logEvent('auth.log', [
            'event' => 'login_failed',
            'reason' => 'unknown_user',
            'username' => $username,
        ]);
        return ['success' => false, 'message' => 'invalid'];
    }

    if (!password_verify($password, $user['passwordHash'] ?? '')) {
        recordFailedAttempt($key, $config);
        $user['failedLoginCount'] = (int)($user['failedLoginCount'] ?? 0) + 1;
        saveUser($user);
        logEvent('auth.log', [
            'event' => 'login_failed',
            'reason' => 'bad_password',
            'username' => $username,
        ]);
        return ['success' => false, 'message' => 'invalid'];
    }

    clearRateLimit($key);
    $user['failedLoginCount'] = 0;
    $user['lastLoginAt'] = isoNow();
    saveUser($user);
    logEvent('auth.log', [
        'event' => 'login_success',
        'username' => $username,
    ]);

    return ['success' => true, 'user' => $user];
}

function requirePostCsrf(?string $token): void {
    if (!verifyCsrfToken($token)) {
        logEvent('auth.log', [
            'event' => 'csrf_failed',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
        http_response_code(400);
        echo t('csrfInvalid', getLanguage(loadConfig()));
        exit;
    }
}

