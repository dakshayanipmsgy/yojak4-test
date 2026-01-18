<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $token = trim($_GET['t'] ?? '');
    if ($token === '') {
        render_error_page('Invalid customer link.');
        return;
    }

    $tokenData = scheme_load_customer_token($token);
    if (!$tokenData) {
        render_error_page('Customer link not found.');
        return;
    }

    if (!empty($tokenData['revoked'])) {
        render_error_page('This link has been revoked.');
        return;
    }

    $expiresAt = $tokenData['expiresAt'] ?? null;
    if ($expiresAt && strtotime($expiresAt) < time()) {
        render_error_page('This link has expired.');
        return;
    }

    $schemeId = $tokenData['schemeId'] ?? '';
    $recordId = $tokenData['recordId'] ?? '';
    $entityKey = $tokenData['entity'] ?? '';
    $yojId = $tokenData['yojId'] ?? '';

    $definition = scheme_load_definition($schemeId);
    if (!$definition) {
        render_error_page('Scheme not available.');
        return;
    }

    $record = scheme_load_record($yojId, $schemeId, $entityKey, $recordId);
    if (!$record) {
        render_error_page('Record not found.');
        return;
    }

    $docId = trim($_GET['doc'] ?? '');
    if ($docId !== '') {
        $allowedDocs = $tokenData['visibleDocs'] ?? [];
        if (!in_array($docId, $allowedDocs, true)) {
            render_error_page('Document not available.');
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

        scheme_log_portal([
            'event' => 'DOC_VIEW',
            'schemeId' => $schemeId,
            'recordId' => $recordId,
            'yojId' => $yojId,
            'token' => $token,
            'docId' => $docId,
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
                @media print { body { padding: 0; } }
            </style>
        </head>
        <body>
            <div class="doc">
                <?= $html; ?>
            </div>
        </body>
        </html>
        <?php
        return;
    }

    $visibleDocs = $tokenData['visibleDocs'] ?? [];
    $docs = array_filter($definition['documents'] ?? [], fn($doc) => in_array($doc['docId'] ?? '', $visibleDocs, true));
    $customerName = $tokenData['customerName'] ?? 'Customer';
    $vendorName = $tokenData['vendorName'] ?? '';

    scheme_log_portal([
        'event' => 'ACCESS',
        'schemeId' => $schemeId,
        'recordId' => $recordId,
        'yojId' => $yojId,
        'token' => $token,
    ]);

    $title = get_app_config()['appName'] . ' | Customer Documents';
    render_layout($title, function () use ($docs, $customerName, $vendorName, $token) {
        ?>
        <div class="card" style="max-width:820px;margin:0 auto;display:grid;gap:16px;">
            <div>
                <h2 style="margin:0;">Documents for <?= sanitize($customerName); ?></h2>
                <p class="muted" style="margin:6px 0 0;">Provided by <?= sanitize($vendorName); ?></p>
            </div>
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
                <?php foreach ($docs as $doc): ?>
                    <div style="border:1px solid var(--border);border-radius:12px;padding:12px;background:var(--surface-2);display:grid;gap:8px;">
                        <div>
                            <h3 style="margin:0 0 4px 0;"><?= sanitize($doc['label'] ?? $doc['docId'] ?? 'Document'); ?></h3>
                            <p class="muted" style="margin:0;">Doc ID: <?= sanitize($doc['docId'] ?? ''); ?></p>
                        </div>
                        <a class="btn secondary" href="/site/customer.php?t=<?= urlencode($token); ?>&doc=<?= urlencode($doc['docId'] ?? ''); ?>" target="_blank" rel="noopener">Download / Print</a>
                    </div>
                <?php endforeach; ?>
                <?php if (!$docs): ?>
                    <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                        <p class="muted" style="margin:0;">No documents available.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    });
});
