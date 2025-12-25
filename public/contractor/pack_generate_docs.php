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
    $pack = $packId !== '' ? load_pack($yojId, $packId) : null;
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    $docs = $_POST['docs'] ?? [];
    if (!is_array($docs)) {
        $docs = [];
    }
    $docs = array_values(array_unique(array_map('strval', $docs)));
    $allowed = ['cover', 'undertaking'];
    $selected = array_values(array_intersect($docs, $allowed));
    if (!$selected) {
        set_flash('error', 'Select at least one document to generate.');
        redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
        return;
    }

    $generatedDir = pack_generated_dir($yojId, $packId);
    if (!is_dir($generatedDir)) {
        mkdir($generatedDir, 0775, true);
    }

    $now = now_kolkata();
    $generatedAt = $now->format(DateTime::ATOM);
    $newDocs = $pack['generatedDocs'] ?? [];

    $tenderTitle = $pack['title'] ?? 'Tender';
    $sourceId = $pack['sourceTender']['id'] ?? '';

    foreach ($selected as $type) {
        $docId = 'DOC-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        $filename = $docId . '.html';
        $path = $generatedDir . '/' . $filename;

        $body = '';
        if ($type === 'cover') {
            $body = '<h1>Cover Letter</h1>'
                . '<p>Date: ' . htmlspecialchars($now->format('Y-m-d')) . '</p>'
                . '<p>Subject: Submission of tender documents for ' . htmlspecialchars($tenderTitle) . ' (' . htmlspecialchars($sourceId) . ')</p>'
                . '<p>Dear Sir/Madam,</p>'
                . '<p>We hereby submit the enclosed documents as part of the tender pack. All documents are true and correct to the best of our knowledge.</p>'
                . '<p>Regards,<br>Authorized Signatory</p>';
        } elseif ($type === 'undertaking') {
            $body = '<h1>Undertaking / Declaration</h1>'
                . '<p>Date: ' . htmlspecialchars($now->format('Y-m-d')) . '</p>'
                . '<p>We undertake that the information and documents provided in this tender pack for ' . htmlspecialchars($tenderTitle) . ' are authentic and valid.</p>'
                . '<p>We agree to abide by all terms and conditions of the tender.</p>'
                . '<p>Authorized Signatory</p>';
        }

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'
            . htmlspecialchars($tenderTitle) . '</title><style>body{font-family:Arial,sans-serif;background:#0d1117;color:#e6edf3;padding:24px;}h1{margin-top:0;color:#fff;}p{line-height:1.6;}</style></head><body>'
            . $body . '</body></html>';

        file_put_contents($path, $html);
        $newDocs[] = [
            'docId' => $docId,
            'title' => $type === 'cover' ? 'Cover letter' : 'Undertaking',
            'path' => str_replace(PUBLIC_PATH, '', $path),
            'generatedAt' => $generatedAt,
        ];
    }

    // Update statuses for generated items if applicable.
    foreach ($pack['items'] as &$item) {
        if (($item['status'] ?? '') === 'pending') {
            $item['status'] = 'generated';
        }
    }
    unset($item);

    $pack['generatedDocs'] = $newDocs;
    $pack['updatedAt'] = $generatedAt;
    save_pack($pack);

    pack_log([
        'event' => 'docs_generated',
        'yojId' => $yojId,
        'packId' => $packId,
        'docs' => $selected,
    ]);

    set_flash('success', 'Documents generated.');
    redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
});
