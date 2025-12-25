<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/packs.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_packs_env($yojId);

    $packId = trim($_POST['packId'] ?? '');
    $itemId = trim($_POST['itemId'] ?? '');
    $pack = $packId !== '' ? load_pack($yojId, $packId) : null;
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    if ($itemId === '') {
        render_error_page('Invalid item.');
        return;
    }

    $item = pack_item_by_id($pack, $itemId);
    if (!$item) {
        render_error_page('Item not found.');
        return;
    }

    if (!isset($_FILES['documents'])) {
        set_flash('error', 'No files selected.');
        redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
        return;
    }

    $files = $_FILES['documents'];
    $allowed = allowed_vault_mimes();
    $maxSize = 10 * 1024 * 1024;
    $destDir = pack_upload_dir($yojId, $packId, $itemId);
    if (!is_dir($destDir)) {
        mkdir($destDir, 0775, true);
    }

    $errors = [];
    $newRefs = $item['fileRefs'] ?? [];
    $uploadedCount = 0;
    $count = is_array($files['name']) ? count($files['name']) : 0;
    for ($i = 0; $i < $count; $i++) {
        $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed for one of the files.';
            continue;
        }
        $size = (int)($files['size'][$i] ?? 0);
        if ($size > $maxSize) {
            $errors[] = 'File too large (max 10MB).';
            continue;
        }
        $tmpName = $files['tmp_name'][$i] ?? '';
        if ($tmpName === '') {
            $errors[] = 'Invalid upload.';
            continue;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!isset($allowed[$mime])) {
            $errors[] = 'Unsupported file type.';
            continue;
        }
        $ext = $allowed[$mime];
        $safeName = safe_pack_filename((string)($files['name'][$i] ?? 'file.' . $ext), $ext);
        $destination = $destDir . '/' . $safeName;
        if (!move_uploaded_file($tmpName, $destination)) {
            $errors[] = 'Could not store file.';
            continue;
        }
        $webPath = str_replace(PUBLIC_PATH, '', $destination);
        $newRefs[] = [
            'name' => $safeName,
            'path' => $webPath,
            'sizeBytes' => $size,
            'uploadedAt' => now_kolkata()->format(DateTime::ATOM),
        ];
        $uploadedCount++;
    }

    if ($uploadedCount === 0) {
        set_flash('error', $errors ? implode(' ', $errors) : 'Upload failed.');
        redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
        return;
    }

    $item['fileRefs'] = $newRefs;
    if (!empty($newRefs)) {
        $item['status'] = in_array($item['status'] ?? '', ['generated', 'done'], true) ? $item['status'] : 'uploaded';
    }

    foreach ($pack['items'] as &$existing) {
        if (($existing['itemId'] ?? '') === $itemId) {
            $existing = $item;
            break;
        }
    }
    unset($existing);

    $pack['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    save_pack($pack);

    pack_log([
        'event' => 'item_uploaded',
        'yojId' => $yojId,
        'packId' => $packId,
        'itemId' => $itemId,
        'fileCount' => $uploadedCount,
    ]);

    $message = $uploadedCount . ' file(s) uploaded.';
    if ($errors) {
        $message .= ' Some files were skipped: ' . implode(' ', $errors);
    }
    set_flash('success', $message);
    redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
});
