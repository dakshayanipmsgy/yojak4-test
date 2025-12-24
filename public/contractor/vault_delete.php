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
    if ($fileId === '') {
        set_flash('error', 'Missing file.');
        redirect('/contractor/vault.php');
    }

    $index = contractor_vault_index($contractor['yojId']);
    $found = false;
    foreach ($index as &$record) {
        if (($record['fileId'] ?? '') === $fileId) {
            $found = true;
            $record['deletedAt'] = now_kolkata()->format(DateTime::ATOM);
            break;
        }
    }

    if (!$found) {
        set_flash('error', 'File not found.');
        redirect('/contractor/vault.php');
    }

    save_contractor_vault_index($contractor['yojId'], $index);
    $metaPath = vault_file_meta_path($contractor['yojId'], $fileId);
    if (file_exists($metaPath)) {
        $meta = readJson($metaPath);
        if (is_array($meta)) {
            $meta['deletedAt'] = now_kolkata()->format(DateTime::ATOM);
            writeJsonAtomic($metaPath, $meta);
        }
    }

    set_flash('success', 'File deleted.');
    redirect('/contractor/vault.php');
});
