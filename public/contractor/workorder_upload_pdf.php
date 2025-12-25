<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/workorders.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_workorder_env($yojId);

    $woId = trim($_POST['id'] ?? '');
    $workorder = $woId !== '' ? load_workorder($yojId, $woId) : null;
    if (!$workorder || ($workorder['yojId'] ?? '') !== $yojId) {
        render_error_page('Workorder not found.');
        return;
    }

    $upload = $_FILES['workorder_pdf'] ?? null;
    $errors = [];
    if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Select a PDF to upload.';
    } else {
        if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $upload['tmp_name']) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            if ($mime !== 'application/pdf') {
                $errors[] = 'Only PDF files allowed.';
            }
            if (($upload['size'] ?? 0) > 10 * 1024 * 1024) {
                $errors[] = 'PDF too large (max 10MB).';
            }
        }
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/contractor/workorder_view.php?id=' . urlencode($woId));
        return;
    }

    $uploadDir = workorder_upload_dir($yojId, $woId);
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $target = uniqid('src_', true) . '.pdf';
    $dest = rtrim($uploadDir, '/') . '/' . $target;
    if (!move_uploaded_file($upload['tmp_name'], $dest)) {
        set_flash('error', 'Failed to store PDF.');
        redirect('/contractor/workorder_view.php?id=' . urlencode($woId));
        return;
    }

    $workorder['sourceFiles'][] = [
        'name' => basename((string)$upload['name']),
        'path' => str_replace(PUBLIC_PATH, '', $dest),
        'sizeBytes' => (int)($upload['size'] ?? 0),
        'uploadedAt' => now_kolkata()->format(DateTime::ATOM),
    ];
    $workorder['source'] = 'uploaded_pdf';
    $workorder['updatedAt'] = now_kolkata()->format(DateTime::ATOM);

    save_workorder($workorder);
    workorder_log([
        'event' => 'pdf_uploaded',
        'yojId' => $yojId,
        'woId' => $woId,
    ]);

    set_flash('success', 'PDF uploaded.');
    redirect('/contractor/workorder_view.php?id=' . urlencode($woId));
});
