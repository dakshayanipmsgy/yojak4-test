<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/print_settings.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    if (empty($_FILES['logo']) || !is_uploaded_file($_FILES['logo']['tmp_name'])) {
        set_flash('error', 'No logo uploaded.');
        redirect('/contractor/print_settings.php');
        return;
    }

    $file = $_FILES['logo'];
    if ((int)$file['size'] > 2 * 1024 * 1024) {
        set_flash('error', 'Logo must be under 2MB.');
        redirect('/contractor/print_settings.php');
        return;
    }

    $info = getimagesize($file['tmp_name']);
    if ($info === false) {
        set_flash('error', 'Invalid image file.');
        redirect('/contractor/print_settings.php');
        return;
    }
    $mime = $info['mime'] ?? '';
    $allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        set_flash('error', 'Only PNG, JPG or WebP images are allowed.');
        redirect('/contractor/print_settings.php');
        return;
    }

    $source = null;
    if ($mime === 'image/png') {
        $source = imagecreatefrompng($file['tmp_name']);
    } elseif ($mime === 'image/jpeg' || $mime === 'image/jpg') {
        $source = imagecreatefromjpeg($file['tmp_name']);
    } elseif ($mime === 'image/webp') {
        if (!function_exists('imagecreatefromwebp')) {
            set_flash('error', 'WebP not supported on server.');
            redirect('/contractor/print_settings.php');
            return;
        }
        $source = imagecreatefromwebp($file['tmp_name']);
    }

    if (!$source) {
        set_flash('error', 'Failed to read image.');
        redirect('/contractor/print_settings.php');
        return;
    }

    $width = imagesx($source);
    $height = imagesy($source);
    $maxWidth = (int)round(96 / 25.4 * 35); // 35mm at ~96dpi
    $maxHeight = (int)round(96 / 25.4 * 20); // 20mm at ~96dpi
    $scale = min($maxWidth / max(1, $width), $maxHeight / max(1, $height), 1);
    $targetW = (int)max(1, round($width * $scale));
    $targetH = (int)max(1, round($height * $scale));

    $canvas = imagecreatetruecolor($targetW, $targetH);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefill($canvas, 0, 0, $transparent);
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetW, $targetH, $width, $height);

    $uploadDir = PUBLIC_PATH . '/uploads/contractors/' . $yojId;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }
    $destPath = $uploadDir . '/logo.png';
    if (!imagepng($canvas, $destPath)) {
        set_flash('error', 'Failed to save resized logo.');
        redirect('/contractor/print_settings.php');
        return;
    }

    imagedestroy($source);
    imagedestroy($canvas);

    $settings = load_contractor_print_settings($yojId);
    $settings['logoPathPublic'] = str_replace(PUBLIC_PATH, '', $destPath);
    $settings['logoEnabled'] = true;
    save_contractor_print_settings($yojId, $settings);

    logEvent(PACK_PRINT_LOG, [
        'event' => 'logo_uploaded',
        'yojId' => $yojId,
        'size' => $file['size'] ?? 0,
        'mime' => $mime,
    ]);

    set_flash('success', 'Logo uploaded and resized for printing.');
    redirect('/contractor/print_settings.php');
});
