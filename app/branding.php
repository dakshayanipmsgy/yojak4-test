<?php
declare(strict_types=1);

function branding_config_path(): string
{
    return DATA_PATH . '/site/branding.json';
}

function branding_log_path(): string
{
    return DATA_PATH . '/logs/site.log';
}

function branding_default_config(): array
{
    return [
        'logoPath' => null,
        'logoUpdatedAt' => null,
    ];
}

function ensure_branding_environment(): void
{
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
        $config = branding_default_config();
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
    $config['logoPath'] = $config['logoPath'] ?? null;
    $config['logoUpdatedAt'] = $config['logoUpdatedAt'] ?? null;
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
    logEvent($file, $entry);
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
    if (!branding_logo_exists($config['logoPath'])) {
        return null;
    }
    return $config['logoPath'];
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
    $allowed = ['png'];
    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException('Only PNG files are allowed.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath) ?: '';
    $allowedMimes = ['image/png'];
    if (!in_array($mime, $allowedMimes, true)) {
        throw new RuntimeException('Uploaded file is not a valid image.');
    }

    $uploadDir = PUBLIC_PATH . '/uploads/branding';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $targetPath = $uploadDir . '/logo.png';
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Unable to store uploaded file.');
    }

    $publicPath = branding_logo_public_path();
    $config = branding_update_config([
        'logoPath' => $publicPath,
        'logoUpdatedAt' => now_kolkata()->format(DateTime::ATOM),
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
    $config = branding_read_config();
    branding_log('toggle_logo', $actor, 'ignored', ['enabled' => $enabled]);
    return $config;
}

function branding_handle_delete(string $actor): array
{
    $config = branding_read_config();
    $publicPath = $config['logoPath'] ?? null;
    $deleted = false;
    if ($publicPath) {
        $absolute = rtrim(PUBLIC_PATH, '/') . $publicPath;
        if (file_exists($absolute)) {
            $deleted = unlink($absolute);
        }
    }

    $config = branding_update_config([
        'logoPath' => null,
        'logoUpdatedAt' => null,
    ], $actor);

    branding_log('delete_logo', $actor, 'success', [
        'deleted' => $deleted,
        'previousPath' => $publicPath,
    ]);

    return $config;
}
