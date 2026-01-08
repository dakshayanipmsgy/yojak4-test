<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        redirect('/contractor/vault.php#vault-upload');
        return;
    }

    $isJson = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $respond = function (array $payload, int $status = 200) use ($isJson): void {
        if ($isJson) {
            http_response_code($status);
            header('Content-Type: application/json');
            echo json_encode($payload);
            return;
        }
        if (!empty($payload['errors']) && is_array($payload['errors'])) {
            foreach ($payload['errors'] as $error) {
                set_flash('error', (string)$error);
            }
        } elseif (!empty($payload['message'])) {
            set_flash('success', (string)$payload['message']);
        }
        redirect('/contractor/vault.php');
    };

    $user = require_role('contractor');
    $contractor = load_contractor($user['yojId']);
    if (!$contractor) {
        render_error_page('Contractor not found.');
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $errors = [];
        $tagsInput = trim($_POST['tags'] ?? '');

        $tags = [];
        if ($tagsInput !== '') {
            foreach (explode(',', $tagsInput) as $tag) {
                $t = trim($tag);
                if ($t === '') {
                    continue;
                }
                if (strlen($t) < 2 || strlen($t) > 20) {
                    $errors[] = 'Tags must be between 2 and 20 characters.';
                    break;
                }
                $tags[] = $t;
            }
            $tags = array_values(array_unique($tags));
            if (count($tags) > 10) {
                $errors[] = 'Maximum 10 tags allowed.';
            }
        }

        if (!isset($_FILES['document']) || ($_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Please select a file to upload.';
        }

        if (!$errors && isset($_FILES['document'])) {
            $file = $_FILES['document'];
            $maxSize = 15 * 1024 * 1024;
            if (($file['size'] ?? 0) > $maxSize) {
                $errors[] = 'File too large. Max 15MB allowed.';
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            $allowed = allowed_vault_mimes();
            $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            $blockedExtensions = ['php', 'phtml', 'phar', 'js', 'exe', 'sh', 'bat', 'cmd', 'com', 'msi'];
            if ($extension !== '' && in_array($extension, $blockedExtensions, true)) {
                $errors[] = 'File type not allowed.';
            } elseif (!isset($allowed[$mime])) {
                $errors[] = 'Unsupported file type.';
            } else {
                $allowedExts = [$allowed[$mime]];
                if ($mime === 'image/jpeg') {
                    $allowedExts[] = 'jpeg';
                }
                if (!in_array($extension, $allowedExts, true)) {
                    $errors[] = 'File extension does not match the detected file type.';
                }
            }
        }

        if (!$errors) {
            $fileId = generate_vault_file_id();
            $ext = allowed_vault_mimes()[$mime];
            $originalName = basename((string)$file['name']);
            $storedName = $fileId . '.' . $ext;
            $vaultFilesDir = contractors_vault_files_path($contractor['yojId']);
            if (!is_dir($vaultFilesDir)) {
                mkdir($vaultFilesDir, 0775, true);
            }
            $destination = $vaultFilesDir . '/' . $storedName;
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $errors[] = 'Failed to store the uploaded file.';
            } else {
                $uploadedAt = now_kolkata()->format(DateTime::ATOM);
                $record = [
                    'fileId' => $fileId,
                    'docId' => $fileId,
                    'title' => pathinfo($originalName, PATHINFO_FILENAME),
                    'category' => 'Other',
                    'docType' => 'Other',
                    'originalName' => $originalName,
                    'storedName' => $storedName,
                    'storedPath' => $destination,
                    'mime' => $mime,
                    'size' => (int)$file['size'],
                    'sizeBytes' => (int)$file['size'],
                    'tags' => $tags,
                    'uploadedAt' => $uploadedAt,
                    'deletedAt' => null,
                ];

                $index = contractor_vault_index($contractor['yojId']);
                array_unshift($index, $record);
                save_contractor_vault_index($contractor['yojId'], $index);

                $fileDir = ensure_vault_file_dir($contractor['yojId'], $fileId);
                $meta = $record;
                $meta['notes'] = '';
                $meta['source'] = 'uploaded';
                writeJsonAtomic($fileDir . '/meta.json', $meta);

                logEvent(DATA_PATH . '/logs/vault_upload.log', [
                    'at' => $uploadedAt,
                    'event' => 'VAULT_UPLOAD',
                    'yojId' => $contractor['yojId'],
                    'fileId' => $fileId,
                    'result' => 'success',
                    'errorCode' => null,
                    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ]);

                $respond([
                    'ok' => true,
                    'message' => 'File uploaded to vault.',
                    'item' => $record,
                    'downloadUrl' => '/contractor/vault_download.php?fileId=' . urlencode($fileId),
                ]);
                return;
            }
        }

        logEvent(DATA_PATH . '/logs/vault_upload.log', [
            'at' => now_kolkata()->format(DateTime::ATOM),
            'event' => 'VAULT_UPLOAD',
            'yojId' => $contractor['yojId'],
            'fileId' => null,
            'result' => 'fail',
            'errorCode' => $errors[0] ?? 'upload_failed',
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        $respond([
            'ok' => false,
            'errors' => $errors ?: ['Upload failed. Please retry.'],
        ], 400);
        return;
    }
});
