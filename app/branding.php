<?php
declare(strict_types=1);

function branding_config_path(): string
{
    return DATA_PATH . '/platform/branding.json';
}

function branding_log_path(): string
{
    return DATA_PATH . '/logs/branding.log';
}

function branding_default_config(): array
{
    return [
        'logoEnabled' => false,
        'logoPublicPath' => null,
        'logoUploadedAt' => null,
        'updatedAt' => null,
        'updatedBy' => null,
    ];
}

function ensure_branding_environment(): void
{
    $platformDir = DATA_PATH . '/platform';
    if (!is_dir($platformDir)) {
        mkdir($platformDir, 0775, true);
    }

    $uploadDir = PUBLIC_PATH . '/uploads/branding';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $logFile = branding_log_path();
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }
    if (!file_exists($logFile)) {
        touch($logFile);
    }

    $configPath = branding_config_path();
    if (!file_exists($configPath)) {
        $now = now_kolkata()->format(DateTime::ATOM);
        $config = branding_default_config();
        $config['updatedAt'] = $now;
        $config['updatedBy'] = 'system';
        writeJsonAtomic($configPath, $config);
    }
}

function branding_read_config(): array
{
    $defaults = branding_default_config();
    $config = readJson(branding_config_path());
    foreach ($defaults as $key => $default) {
        if (!array_key_exists($key, $config)) {
            $config[$key] = $default;
        }
    }
    $config['logoEnabled'] = (bool)($config['logoEnabled'] ?? false);
    $config['logoPublicPath'] = $config['logoPublicPath'] ?? null;
    $config['logoUploadedAt'] = $config['logoUploadedAt'] ?? null;
    $config['updatedAt'] = $config['updatedAt'] ?? null;
    $config['updatedBy'] = $config['updatedBy'] ?? null;
    return $config;
}

function branding_write_config(array $config): void
{
    writeJsonAtomic(branding_config_path(), $config);
}

function branding_update_config(array $changes, string $actor): array
{
    $config = branding_read_config();
    foreach ($changes as $key => $value) {
        $config[$key] = $value;
    }
    $config['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    $config['updatedBy'] = $actor;
    branding_write_config($config);
    return $config;
}

function branding_log(string $event, string $actor, string $result, array $details = []): void
{
    $entry = [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => $event,
        'actor' => $actor,
        'result' => $result,
        'details' => $details,
    ];

    $file = branding_log_path();
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

function branding_logo_exists(?string $publicPath): bool
{
    if (!$publicPath) {
        return false;
    }
    $absolute = rtrim(PUBLIC_PATH, '/') . $publicPath;
    return file_exists($absolute);
}

function branding_display_logo_path(): ?string
{
    $config = branding_read_config();
    if (!$config['logoEnabled']) {
        return null;
    }
    if (!branding_logo_exists($config['logoPublicPath'])) {
        return null;
    }
    return $config['logoPublicPath'];
}

function branding_handle_upload(array $file, string $actor): array
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('No file selected.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed with error code ' . $error . '.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Uploaded file is empty.');
    }
    if ($size > 2 * 1024 * 1024) {
        throw new RuntimeException('File exceeds 2MB limit.');
    }

    $tmpPath = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Invalid upload payload.');
    }

    $name = $file['name'] ?? 'upload';
    $extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['png', 'jpg', 'jpeg', 'webp'];
    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException('Only PNG, JPG, JPEG, or WEBP files are allowed.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath) ?: '';
    $allowedMimes = ['image/png', 'image/jpeg', 'image/webp'];
    if (!in_array($mime, $allowedMimes, true)) {
        throw new RuntimeException('Uploaded file is not a valid image.');
    }

    $targetExtension = $extension === 'jpeg' ? 'jpg' : $extension;
    $uploadDir = PUBLIC_PATH . '/uploads/branding';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    foreach ($allowed as $ext) {
        $existing = $uploadDir . '/logo.' . $ext;
        if (file_exists($existing)) {
            unlink($existing);
        }
    }

    $targetPath = $uploadDir . '/logo.' . $targetExtension;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Unable to store uploaded file.');
    }

    $publicPath = '/uploads/branding/logo.' . $targetExtension;
    $config = branding_update_config([
        'logoEnabled' => true,
        'logoPublicPath' => $publicPath,
        'logoUploadedAt' => now_kolkata()->format(DateTime::ATOM),
    ], $actor);

    branding_log('upload_logo', $actor, 'success', [
        'path' => $publicPath,
        'mime' => $mime,
        'size' => $size,
    ]);

    return $config;
}

function branding_handle_toggle(bool $enabled, string $actor): array
{
    $config = branding_update_config(['logoEnabled' => $enabled], $actor);
    branding_log('toggle_logo', $actor, 'success', ['enabled' => $enabled]);
    return $config;
}

function branding_handle_delete(string $actor): array
{
    $config = branding_read_config();
    $publicPath = $config['logoPublicPath'] ?? null;
    $deleted = false;
    if ($publicPath) {
        $absolute = rtrim(PUBLIC_PATH, '/') . $publicPath;
        if (file_exists($absolute)) {
            $deleted = unlink($absolute);
        }
    }

    $config = branding_update_config([
        'logoEnabled' => false,
        'logoPublicPath' => null,
        'logoUploadedAt' => null,
    ], $actor);

    branding_log('delete_logo', $actor, 'success', [
        'deleted' => $deleted,
        'previousPath' => $publicPath,
    ]);

    return $config;
}
