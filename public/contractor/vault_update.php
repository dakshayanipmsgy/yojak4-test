<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        render_error_page('Invalid request.');
        return;
    }
    $user = require_role('contractor');
    require_csrf();
    $contractor = load_contractor($user['yojId']);
    if (!$contractor) {
        render_error_page('Contractor not found.');
        return;
    }

    $fileId = trim($_POST['fileId'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $docType = trim($_POST['docType'] ?? '');
    $tagsInput = trim($_POST['tags'] ?? '');

    $errors = [];
    if ($fileId === '') {
        $errors[] = 'Missing file.';
    }
    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    $allowedDocTypes = ['GST','PAN','ITR','BalanceSheet','Affidavit','Undertaking','ExperienceCert','Other'];
    if (!in_array($docType, $allowedDocTypes, true)) {
        $errors[] = 'Invalid document type.';
    }

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

    $index = contractor_vault_index($contractor['yojId']);
    $found = false;
    foreach ($index as &$record) {
        if (($record['fileId'] ?? '') === $fileId) {
            $found = true;
            if (!empty($record['deletedAt'])) {
                $errors[] = 'Cannot edit a deleted file.';
            } else {
                $record['docId'] = $record['docId'] ?? $fileId;
                $record['title'] = $title;
                $record['category'] = $docType;
                $record['docType'] = $docType;
                $record['tags'] = $tags;
            }
            break;
        }
    }

    if (!$found) {
        $errors[] = 'File not found.';
    }

    if ($errors) {
        foreach ($errors as $err) {
            set_flash('error', $err);
        }
        redirect('/contractor/vault.php');
    }

    save_contractor_vault_index($contractor['yojId'], $index);
    $metaPath = vault_file_meta_path($contractor['yojId'], $fileId);
    if (file_exists($metaPath)) {
        $meta = readJson($metaPath);
        if (is_array($meta)) {
            $meta['title'] = $title;
            $meta['category'] = $docType;
            $meta['docType'] = $docType;
            $meta['docId'] = $meta['docId'] ?? $fileId;
            $meta['tags'] = $tags;
            writeJsonAtomic($metaPath, $meta);
        }
    }

    set_flash('success', 'File details updated.');
    redirect('/contractor/vault.php');
});
