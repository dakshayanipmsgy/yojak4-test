<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $schemeId = trim($_GET['schemeId'] ?? '');
    $docId = trim($_GET['docId'] ?? '');
    $recordId = trim($_GET['recordId'] ?? '');
    $entityKey = trim($_GET['entity'] ?? '');

    if ($schemeId === '' || $docId === '' || $recordId === '') {
        render_error_page('Missing parameters.');
        return;
    }
    if (!scheme_has_access($yojId, $schemeId)) {
        render_error_page('Scheme access not enabled.');
        return;
    }

    $definition = scheme_load_definition($schemeId);
    if (!$definition) {
        render_error_page('Scheme not compiled.');
        return;
    }

    if ($entityKey === '') {
        foreach ($definition['entities'] ?? [] as $entity) {
            $candidate = scheme_load_record($yojId, $schemeId, $entity['key'] ?? '', $recordId);
            if ($candidate) {
                $entityKey = $entity['key'] ?? '';
                break;
            }
        }
    }

    $record = scheme_load_record($yojId, $schemeId, $entityKey, $recordId);
    if (!$record) {
        render_error_page('Record not found.');
        return;
    }

    $doc = null;
    foreach ($definition['documents'] ?? [] as $entry) {
        if (($entry['docId'] ?? '') === $docId) {
            $doc = $entry;
            break;
        }
    }
    if (!$doc) {
        render_error_page('Document not found.');
        return;
    }

    $contractor = load_contractor($yojId) ?? [];
    $html = scheme_render_document_html($definition, $doc, $record, $contractor);

    $cachePath = DATA_PATH . '/contractors/approved/' . $yojId . '/schemes/' . $schemeId . '/docs/' . $recordId;
    if (!is_dir($cachePath)) {
        mkdir($cachePath, 0775, true);
    }
    writeJsonAtomic($cachePath . '/' . $docId . '.json', [
        'docId' => $docId,
        'recordId' => $recordId,
        'generatedAt' => now_kolkata()->format(DateTime::ATOM),
        'html' => $html,
    ]);

    scheme_log_usage($yojId, $schemeId, 'DOC_GENERATE', [
        'docId' => $docId,
        'recordId' => $recordId,
    ]);

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= sanitize($doc['label'] ?? $docId); ?></title>
        <style>
            body { font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 24px; color: #111827; background: #fff; }
            .doc { max-width: 900px; margin: 0 auto; }
            .doc-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
            .doc-table th, .doc-table td { border: 1px solid #d1d5db; padding: 8px; font-size: 14px; }
            .muted { color: #6b7280; }
            @media print {
                body { padding: 0; }
                .doc { margin: 0; }
            }
        </style>
    </head>
    <body>
        <div class="doc">
            <?= $html; ?>
        </div>
    </body>
    </html>
    <?php
});
