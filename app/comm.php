<?php
declare(strict_types=1);

function comm_config_path(): string
{
    return DATA_PATH . '/comm/config.json';
}

function comm_secret_path(): string
{
    return DATA_PATH . '/comm/secret.key';
}

function comm_default_config(): array
{
    return [
        'whatsapp' => [
            'enabled' => false,
            'provider' => 'meta_cloud_api',
            'phoneNumberId' => '',
            'accessTokenEnc' => '',
            'authTemplateName' => '',
            'templateLang' => 'en_US',
            'senderDisplay' => 'YOJAK',
        ],
        'email' => [
            'enabled' => false,
            'smtpHost' => '',
            'smtpPort' => 587,
            'smtpEncryption' => 'tls',
            'smtpUser' => '',
            'smtpPassEnc' => '',
            'fromEmail' => 'connect@yojak.co.in',
            'fromName' => 'YOJAK',
        ],
        'updatedAt' => now_kolkata()->format(DateTime::ATOM),
    ];
}

function ensure_comm_environment(): void
{
    $directories = [
        DATA_PATH . '/comm',
        DATA_PATH . '/otp',
        DATA_PATH . '/otp/contractor_signup',
        DATA_PATH . '/notifications',
        DATA_PATH . '/notifications/contractor',
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    if (!file_exists(comm_secret_path())) {
        $secret = base64_encode(random_bytes(32));
        file_put_contents(comm_secret_path(), $secret, LOCK_EX);
    }

    if (!file_exists(comm_config_path())) {
        writeJsonAtomic(comm_config_path(), comm_default_config());
    }
}

function comm_secret_key(): string
{
    ensure_comm_environment();
    $raw = trim((string)file_get_contents(comm_secret_path()));
    $decoded = base64_decode($raw, true);
    if ($decoded === false || $decoded === '') {
        $decoded = random_bytes(32);
        file_put_contents(comm_secret_path(), base64_encode($decoded), LOCK_EX);
    }
    return $decoded;
}

function comm_encrypt(string $value): string
{
    if ($value === '') {
        return '';
    }
    $key = comm_secret_key();
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Unable to encrypt secret value.');
    }
    return base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($ciphertext);
}

function comm_decrypt(string $payload): string
{
    if ($payload === '') {
        return '';
    }
    $parts = explode(':', $payload);
    if (count($parts) !== 3) {
        return '';
    }
    [$ivB64, $tagB64, $cipherB64] = $parts;
    $iv = base64_decode($ivB64, true);
    $tag = base64_decode($tagB64, true);
    $cipher = base64_decode($cipherB64, true);
    if ($iv === false || $tag === false || $cipher === false) {
        return '';
    }
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', comm_secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? '' : $plain;
}

function get_comm_config(): array
{
    ensure_comm_environment();
    $config = readJson(comm_config_path());
    if (!is_array($config) || !$config) {
        $config = comm_default_config();
    }
    return array_replace_recursive(comm_default_config(), $config);
}

function save_comm_config(array $config): void
{
    $config['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(comm_config_path(), $config);
}

function comm_masked_value(string $value): string
{
    if ($value === '') {
        return '';
    }
    $length = strlen($value);
    if ($length <= 4) {
        return str_repeat('*', $length);
    }
    return str_repeat('*', $length - 4) . substr($value, -4);
}

function otp_storage_dir(): string
{
    return DATA_PATH . '/otp/contractor_signup';
}

function otp_device_fingerprint(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash('sha256', $ip . '|' . $ua);
}

function otp_mobile_hash(string $mobileE164): string
{
    return sha1($mobileE164 . '|' . comm_secret_key());
}

function otp_hash_code(string $otp): string
{
    return hash('sha256', $otp . '|' . comm_secret_key());
}

function otp_generate_code(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function otp_rate_limit_state(string $mobileE164, string $deviceFingerprint): array
{
    $files = glob(otp_storage_dir() . '/OTP-' . otp_mobile_hash($mobileE164) . '-*.json') ?: [];
    $records = [];
    foreach ($files as $file) {
        $data = readJson($file);
        if (!empty($data)) {
            $records[] = $data;
        }
    }

    $now = now_kolkata();
    $windowStart = $now->modify('-15 minutes');
    $dayKey = $now->format('Y-m-d');
    $deviceCount = 0;
    $dailyCount = 0;

    foreach ($records as $record) {
        $createdRaw = $record['createdAt'] ?? null;
        if (!$createdRaw) {
            continue;
        }
        try {
            $createdAt = new DateTimeImmutable((string)$createdRaw, new DateTimeZone('Asia/Kolkata'));
        } catch (Exception $e) {
            continue;
        }
        $sendAttempts = (int)($record['sendAttempts'] ?? 1);
        if ($createdAt >= $windowStart && ($record['deviceFingerprint'] ?? '') === $deviceFingerprint) {
            $deviceCount += $sendAttempts;
        }
        if ($createdAt->format('Y-m-d') === $dayKey) {
            $dailyCount += $sendAttempts;
        }
    }

    return [
        'deviceCount' => $deviceCount,
        'dailyCount' => $dailyCount,
    ];
}

function otp_rate_limit_allowed(string $mobileE164, string $deviceFingerprint): array
{
    $state = otp_rate_limit_state($mobileE164, $deviceFingerprint);
    if ($state['deviceCount'] >= 3) {
        return [false, 'Too many OTP requests from this device. Please wait 15 minutes.'];
    }
    if ($state['dailyCount'] >= 8) {
        return [false, 'Daily OTP limit reached. Please try again tomorrow.'];
    }
    return [true, ''];
}

function otp_store_record(array $record): void
{
    $path = otp_storage_dir() . '/' . ($record['id'] ?? '') . '.json';
    writeJsonAtomic($path, $record);
}

function otp_create_record(string $mobileE164, string $otpCode, string $deviceFingerprint): array
{
    $createdAt = now_kolkata();
    $expiresAt = $createdAt->modify('+10 minutes');
    $id = 'OTP-' . otp_mobile_hash($mobileE164) . '-' . $createdAt->format('YmdHis') . '-' . substr(bin2hex(random_bytes(2)), 0, 4);

    return [
        'id' => $id,
        'mobileE164' => $mobileE164,
        'otpHash' => otp_hash_code($otpCode),
        'createdAt' => $createdAt->format(DateTime::ATOM),
        'expiresAt' => $expiresAt->format(DateTime::ATOM),
        'attemptsLeft' => 5,
        'sendAttempts' => 1,
        'status' => 'sent',
        'deviceFingerprint' => $deviceFingerprint,
        'meta' => [
            'waMessageId' => null,
            'providerStatus' => 'pending',
            'providerError' => null,
        ],
    ];
}

function otp_find_latest_active(string $mobileE164): ?array
{
    $files = glob(otp_storage_dir() . '/OTP-' . otp_mobile_hash($mobileE164) . '-*.json') ?: [];
    $records = [];
    foreach ($files as $file) {
        $data = readJson($file);
        if (!empty($data)) {
            $records[] = $data;
        }
    }
    usort($records, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
    $now = now_kolkata();
    foreach ($records as $record) {
        if (($record['status'] ?? '') !== 'sent') {
            continue;
        }
        $expiresRaw = $record['expiresAt'] ?? null;
        if (!$expiresRaw) {
            continue;
        }
        try {
            $expiresAt = new DateTimeImmutable((string)$expiresRaw, new DateTimeZone('Asia/Kolkata'));
        } catch (Exception $e) {
            continue;
        }
        if ($expiresAt < $now) {
            $record['status'] = 'expired';
            otp_store_record($record);
            continue;
        }
        if (($record['attemptsLeft'] ?? 0) <= 0) {
            $record['status'] = 'blocked';
            otp_store_record($record);
            continue;
        }
        return $record;
    }
    return null;
}

function otp_verify_code(string $mobileE164, string $otpCode): array
{
    $record = otp_find_latest_active($mobileE164);
    if (!$record) {
        return ['success' => false, 'message' => 'No active OTP found. Please resend.'];
    }

    $hash = otp_hash_code($otpCode);
    if (!hash_equals($record['otpHash'] ?? '', $hash)) {
        $record['attemptsLeft'] = max(0, (int)($record['attemptsLeft'] ?? 0) - 1);
        if ($record['attemptsLeft'] <= 0) {
            $record['status'] = 'blocked';
        }
        otp_store_record($record);

        logEvent(DATA_PATH . '/logs/otp.log', [
            'at' => now_kolkata()->format(DateTime::ATOM),
            'event' => 'OTP_VERIFY',
            'mobile' => $mobileE164,
            'last4' => substr($mobileE164, -4),
            'result' => 'invalid',
            'attemptsLeft' => $record['attemptsLeft'],
        ]);

        return ['success' => false, 'message' => 'Incorrect OTP. Please try again.'];
    }

    $record['status'] = 'verified';
    otp_store_record($record);

    logEvent(DATA_PATH . '/logs/otp.log', [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => 'OTP_VERIFY',
        'mobile' => $mobileE164,
        'last4' => substr($mobileE164, -4),
        'result' => 'verified',
        'attemptsLeft' => $record['attemptsLeft'],
    ]);

    return ['success' => true, 'message' => 'OTP verified.'];
}

function whatsapp_send_otp(string $mobileE164, string $otpCode, array $config): array
{
    $phoneNumberId = $config['whatsapp']['phoneNumberId'] ?? '';
    $accessToken = comm_decrypt($config['whatsapp']['accessTokenEnc'] ?? '');
    $templateName = $config['whatsapp']['authTemplateName'] ?? '';
    $lang = $config['whatsapp']['templateLang'] ?? 'en_US';

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $mobileE164,
        'type' => 'template',
        'template' => [
            'name' => $templateName,
            'language' => ['code' => $lang],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $otpCode,
                        ],
                    ],
                ],
            ],
        ],
    ];

    $url = 'https://graph.facebook.com/v17.0/' . urlencode($phoneNumberId) . '/messages';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $responseBody = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $success = $httpCode >= 200 && $httpCode < 300 && !$curlError;
    $responseJson = json_decode((string)$responseBody, true);
    $waMessageId = $responseJson['messages'][0]['id'] ?? null;
    $errorCode = $responseJson['error']['code'] ?? null;
    $errorMessage = $responseJson['error']['message'] ?? null;
    $status = $success ? 'sent' : 'failed';

    logEvent(DATA_PATH . '/logs/whatsapp.log', [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => 'WA_SEND_OTP',
        'mobile' => $mobileE164,
        'last4' => substr($mobileE164, -4),
        'status' => $status,
        'waMessageId' => $waMessageId,
        'errorCode' => $errorCode ?? ($curlError !== '' ? 'curl_error' : null),
    ]);

    if ($success && empty($responseJson['error'])) {
        return [
            'success' => true,
            'messageId' => $waMessageId,
            'status' => 'sent',
            'error' => null,
        ];
    }

    $errorDetail = $curlError !== '' ? $curlError : ($errorMessage ?? 'Unknown error');
    return [
        'success' => false,
        'messageId' => $waMessageId,
        'status' => 'failed',
        'error' => $errorDetail,
    ];
}

function smtp_send_email(array $config, string $toEmail, string $subject, string $body): array
{
    $host = trim((string)($config['smtpHost'] ?? ''));
    $port = (int)($config['smtpPort'] ?? 587);
    $encryption = $config['smtpEncryption'] ?? 'tls';
    $username = trim((string)($config['smtpUser'] ?? ''));
    $password = comm_decrypt($config['smtpPassEnc'] ?? '');
    $fromEmail = trim((string)($config['fromEmail'] ?? ''));
    $fromName = trim((string)($config['fromName'] ?? ''));

    $transport = $host;
    if ($encryption === 'ssl') {
        $transport = 'ssl://' . $host;
    }

    $fp = stream_socket_client($transport . ':' . $port, $errno, $errstr, 15);
    if (!$fp) {
        return ['success' => false, 'error' => 'SMTP connection failed: ' . $errstr];
    }
    stream_set_timeout($fp, 15);

    $expect = function (array $codes) use ($fp): bool {
        $response = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (preg_match('/^\\d{3} /', $line)) {
                break;
            }
        }
        $code = (int)substr($response, 0, 3);
        return in_array($code, $codes, true);
    };

    $send = function (string $command) use ($fp): void {
        fwrite($fp, $command . "\r\n");
    };

    if (!$expect([220])) {
        fclose($fp);
        return ['success' => false, 'error' => 'SMTP server not ready'];
    }

    $send('EHLO yojak.local');
    if (!$expect([250])) {
        $send('HELO yojak.local');
        if (!$expect([250])) {
            fclose($fp);
            return ['success' => false, 'error' => 'SMTP HELO failed'];
        }
    }

    if ($encryption === 'tls') {
        $send('STARTTLS');
        if (!$expect([220])) {
            fclose($fp);
            return ['success' => false, 'error' => 'STARTTLS failed'];
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return ['success' => false, 'error' => 'TLS negotiation failed'];
        }
        $send('EHLO yojak.local');
        if (!$expect([250])) {
            fclose($fp);
            return ['success' => false, 'error' => 'SMTP EHLO after TLS failed'];
        }
    }

    if ($username !== '' && $password !== '') {
        $send('AUTH LOGIN');
        if (!$expect([334])) {
            fclose($fp);
            return ['success' => false, 'error' => 'SMTP auth not accepted'];
        }
        $send(base64_encode($username));
        if (!$expect([334])) {
            fclose($fp);
            return ['success' => false, 'error' => 'SMTP username rejected'];
        }
        $send(base64_encode($password));
        if (!$expect([235])) {
            fclose($fp);
            return ['success' => false, 'error' => 'SMTP password rejected'];
        }
    }

    $send('MAIL FROM:<' . $fromEmail . '>');
    if (!$expect([250])) {
        fclose($fp);
        return ['success' => false, 'error' => 'MAIL FROM rejected'];
    }

    $send('RCPT TO:<' . $toEmail . '>');
    if (!$expect([250, 251])) {
        fclose($fp);
        return ['success' => false, 'error' => 'RCPT TO rejected'];
    }

    $send('DATA');
    if (!$expect([354])) {
        fclose($fp);
        return ['success' => false, 'error' => 'DATA not accepted'];
    }

    $headers = [
        'From: ' . ($fromName !== '' ? $fromName . ' <' . $fromEmail . '>' : $fromEmail),
        'To: <' . $toEmail . '>',
        'Subject: ' . $subject,
        'Date: ' . gmdate('D, d M Y H:i:s O'),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
    fwrite($fp, $message . "\r\n");
    if (!$expect([250])) {
        fclose($fp);
        return ['success' => false, 'error' => 'Message rejected'];
    }

    $send('QUIT');
    fclose($fp);
    return ['success' => true, 'error' => null];
}
