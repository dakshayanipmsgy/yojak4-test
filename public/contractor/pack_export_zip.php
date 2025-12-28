<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packId = trim(($method === 'POST' ? ($_POST['packId'] ?? '') : ($_GET['packId'] ?? '')));
    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);

    $pack = $packId !== '' ? load_pack($yojId, $packId, $context) : null;
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    if ($method === 'POST') {
        require_csrf();
    } else {
        $token = trim($_GET['token'] ?? '');
        if (!verify_pack_token($packId, $yojId, $token)) {
            render_error_page('Invalid export token.');
            return;
        }
    }

    $zipPath = tempnam(sys_get_temp_dir(), 'packzip_');
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
        render_error_page('Could not create ZIP.');
        return;
    }

    $contractor = load_contractor($yojId) ?? [];
    $vaultFiles = contractor_vault_index($yojId);
    $zip->addFromString('pack_index.html', pack_index_html($pack, $contractor, [], $vaultFiles));

    $baseUpload = packs_upload_root($yojId, $context);
    foreach ($pack['items'] ?? [] as $item) {
        foreach ($item['fileRefs'] ?? [] as $ref) {
            $relative = $ref['path'] ?? '';
            $fullPath = $relative !== '' ? PUBLIC_PATH . $relative : '';
            if ($fullPath === '' || !file_exists($fullPath)) {
                continue;
            }
            if (!is_path_within($fullPath, $baseUpload)) {
                continue;
            }
            $name = safe_pack_filename($ref['name'] ?? basename($fullPath), pathinfo($fullPath, PATHINFO_EXTENSION));
            $zip->addFile($fullPath, 'items/' . ($item['itemId'] ?? 'item') . '/' . $name);
        }
    }

    foreach ($pack['generatedDocs'] ?? [] as $doc) {
        $relative = $doc['path'] ?? '';
        $fullPath = $relative !== '' ? PUBLIC_PATH . $relative : '';
        if ($fullPath === '' || !file_exists($fullPath)) {
            continue;
        }
        if (!is_path_within($fullPath, $baseUpload)) {
            continue;
        }
        $name = safe_pack_filename(($doc['title'] ?? 'doc') . '.html', 'html');
        $zip->addFile($fullPath, 'generated/' . $name);
    }
    foreach ($pack['generatedTemplates'] ?? [] as $tpl) {
        $relative = $tpl['storedPath'] ?? '';
        $fullPath = $relative !== '' ? PUBLIC_PATH . $relative : '';
        if ($fullPath === '' || !file_exists($fullPath)) {
            continue;
        }
        if (!is_path_within($fullPath, $baseUpload)) {
            continue;
        }
        $name = safe_pack_filename(($tpl['name'] ?? 'template') . '.html', 'html');
        $zip->addFile($fullPath, 'generated/templates/' . $name);
    }

    $zip->close();

    pack_log([
        'event' => 'zip_export',
        'yojId' => $yojId,
        'packId' => $packId,
    ]);

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $packId . '.zip"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
    exit;
});
