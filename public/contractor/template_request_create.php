<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_contractor_library_env($yojId);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/template_request_new.php');
    }

    require_csrf();

    $type = ($_POST['type'] ?? '') === 'pack' ? 'pack' : 'template';
    $title = trim((string)($_POST['title'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $sourceTenderType = (string)($_POST['sourceTenderType'] ?? 'uploaded_pdf');
    $sourceTenderId = trim((string)($_POST['sourceTenderId'] ?? ''));

    $errors = [];
    if ($title === '' || mb_strlen($title) < 5 || mb_strlen($title) > 120) {
        $errors[] = 'Title must be between 5 and 120 characters.';
    }
    if ($notes !== '' && mb_strlen($notes) > 5000) {
        $errors[] = 'Notes must be under 5000 characters.';
    }
    if (!in_array($sourceTenderType, ['offline', 'discovered', 'uploaded_pdf'], true)) {
        $errors[] = 'Invalid source tender type.';
    }
    if ($sourceTenderId !== '' && mb_strlen($sourceTenderId) > 50) {
        $errors[] = 'Source tender ID is too long.';
    }

    $attachments = [];
    $file = $_FILES['attachment'] ?? null;
    if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errors[] = 'Failed to upload tender PDF.';
        } else {
            $maxSize = 15 * 1024 * 1024;
            if (($file['size'] ?? 0) > $maxSize) {
                $errors[] = 'Tender PDF exceeds 15 MB.';
            }
            $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                $errors[] = 'Only PDF attachments are allowed.';
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if ($mime !== 'application/pdf') {
                $errors[] = 'Uploaded file is not a valid PDF.';
            }
        }
    }

    if ($errors) {
        logEvent(DATA_PATH . '/logs/template_requests.log', [
            'event' => 'REQUEST_FAILED',
            'yojId' => $yojId,
            'errors' => $errors,
        ]);
        set_flash('error', implode(' ', $errors));
        redirect('/contractor/template_request_new.php?type=' . urlencode($type));
    }

    $requestId = template_request_generate_id();
    if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $uploadDir = template_request_upload_dir($yojId, $requestId);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', (string)($file['name'] ?? 'tender.pdf'));
        if (!str_ends_with(strtolower($safeName), '.pdf')) {
            $safeName .= '.pdf';
        }
        $targetPath = $uploadDir . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            set_flash('error', 'Unable to store the tender PDF. Please try again.');
            redirect('/contractor/template_request_new.php?type=' . urlencode($type));
        }
        $attachments[] = [
            'name' => $safeName,
            'path' => $targetPath,
        ];
    }

    $now = now_kolkata()->format(DateTime::ATOM);
    $request = [
        'requestId' => $requestId,
        'yojId' => $yojId,
        'type' => $type,
        'title' => $title,
        'notes' => $notes,
        'sourceTenderType' => $sourceTenderType,
        'sourceTenderId' => $sourceTenderId !== '' ? $sourceTenderId : null,
        'attachments' => $attachments,
        'status' => 'pending',
        'createdAt' => $now,
        'updatedAt' => $now,
    ];

    template_request_save($yojId, $request);
    logEvent(DATA_PATH . '/logs/template_requests.log', [
        'event' => 'REQUEST_CREATED',
        'yojId' => $yojId,
        'requestId' => $requestId,
        'type' => $type,
    ]);

    set_flash('success', 'Request submitted. YOJAK staff will review it soon.');
    redirect('/contractor/template_requests.php');
});
