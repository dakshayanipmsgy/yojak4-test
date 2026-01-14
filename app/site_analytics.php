<?php
declare(strict_types=1);

function site_analytics_paths(): array
{
    return [
        'visits' => DATA_PATH . '/site/analytics/visits.json',
        'log' => DATA_PATH . '/site/analytics/visits_log.jsonl',
        'lock' => DATA_PATH . '/locks/site_analytics.lock',
    ];
}

function site_analytics_default_state(string $date, string $nowIso): array
{
    return [
        'totalPageViews' => 0,
        'totalUniqueVisitors' => 0,
        'daily' => [
            $date => [
                'pageViews' => 0,
                'uniqueVisitors' => 0,
            ],
        ],
        'updatedAt' => $nowIso,
    ];
}

function site_is_bot_user_agent(string $userAgent): bool
{
    return (bool)preg_match('/bot|crawler|spider|headless/i', $userAgent);
}

function site_set_cookie(string $name, string $value, int $expires): void
{
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie($name, $value, [
        'expires' => $expires,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function site_append_jsonl(string $path, array $payload): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $line = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $handle = fopen($path, 'a');
    if ($handle) {
        flock($handle, LOCK_EX);
        fwrite($handle, $line . PHP_EOL);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function record_public_visit(string $page): array
{
    $paths = site_analytics_paths();
    $now = now_kolkata();
    $date = $now->format('Y-m-d');
    $nowIso = $now->format(DateTime::ATOM);

    $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $isBot = site_is_bot_user_agent($userAgent);

    $visitorId = (string)($_COOKIE['yojak_vid'] ?? '');
    if ($visitorId === '' || !preg_match('/^[a-f0-9]{16,}$/', $visitorId)) {
        $visitorId = bin2hex(random_bytes(16));
        site_set_cookie('yojak_vid', $visitorId, time() + 31536000);
    }

    $lastUvDate = (string)($_COOKIE['yojak_last_uv_date'] ?? '');
    $shouldCountUnique = !$isBot && $lastUvDate !== $date;
    if ($shouldCountUnique) {
        site_set_cookie('yojak_last_uv_date', $date, time() + 31536000);
    }

    $state = site_analytics_default_state($date, $nowIso);

    $lockHandle = fopen($paths['lock'], 'c');
    if (!$lockHandle) {
        logEvent(DATA_PATH . '/logs/site.log', [
            'event' => 'site_analytics_lock_failed',
            'page' => $page,
        ]);
        return $state;
    }

    if (!flock($lockHandle, LOCK_EX)) {
        fclose($lockHandle);
        logEvent(DATA_PATH . '/logs/site.log', [
            'event' => 'site_analytics_lock_busy',
            'page' => $page,
        ]);
        return $state;
    }

    try {
        $state = readJson($paths['visits']);
        if (!is_array($state) || $state === []) {
            $state = site_analytics_default_state($date, $nowIso);
        }
        if (!isset($state['daily']) || !is_array($state['daily'])) {
            $state['daily'] = [];
        }
        if (!isset($state['daily'][$date])) {
            $state['daily'][$date] = ['pageViews' => 0, 'uniqueVisitors' => 0];
        }

        $countPageView = !$isBot;
        if ($countPageView) {
            $state['totalPageViews'] = (int)($state['totalPageViews'] ?? 0) + 1;
            $state['daily'][$date]['pageViews'] = (int)($state['daily'][$date]['pageViews'] ?? 0) + 1;
        }

        if ($shouldCountUnique) {
            $state['totalUniqueVisitors'] = (int)($state['totalUniqueVisitors'] ?? 0) + 1;
            $state['daily'][$date]['uniqueVisitors'] = (int)($state['daily'][$date]['uniqueVisitors'] ?? 0) + 1;
        }

        $state['updatedAt'] = $nowIso;
        writeJsonAtomic($paths['visits'], $state);

        site_append_jsonl($paths['log'], [
            'at' => $nowIso,
            'page' => $page,
            'visitorId' => $visitorId,
            'isBot' => $isBot,
            'countedPageView' => $countPageView,
            'countedUnique' => $shouldCountUnique,
        ]);
    } catch (Throwable $e) {
        logEvent(DATA_PATH . '/logs/site.log', [
            'event' => 'site_analytics_error',
            'page' => $page,
            'message' => $e->getMessage(),
        ]);
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        return $state;
    }

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);

    return $state;
}
