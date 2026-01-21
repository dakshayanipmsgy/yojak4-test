<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $docId = trim((string)($_GET['docId'] ?? ''));
    if ($docId === '') {
        render_error_page('Document not found.');
        return;
    }

    $path = contractor_generated_docs_path($yojId) . '/' . $docId . '.json';
    if (!file_exists($path)) {
        render_error_page('Document not found.');
        return;
    }

    $doc = readJson($path);
    if (!$doc || ($doc['yojId'] ?? '') !== $yojId) {
        render_error_page('Unauthorized access.');
        return;
    }

    $missing = $doc['missingFields'] ?? [];
    if (!is_array($missing)) {
        $missing = [];
    }

    $title = get_app_config()['appName'] . ' | View Doc';
    render_layout($title, function () use ($doc, $missing) {
        ?>
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize($doc['title'] ?? 'Generated Doc'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;">Doc ID: <?= sanitize($doc['docId'] ?? ''); ?></p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn" href="/contractor/create_doc_print.php?docId=<?= urlencode($doc['docId'] ?? ''); ?>&mode=print" target="_blank" rel="noopener">Print</a>
                    <a class="btn secondary" href="/contractor/create_docs.php">Back to Create Docs</a>
                </div>
            </div>
        </div>

        <?php if ($missing): ?>
            <div class="card" style="margin-top:12px;border-color:#fbbf24;background:#fff8e1;">
                <strong><?= sanitize((string)count($missing)); ?> missing fields will print as blanks:</strong>
                <ul style="margin:8px 0 0 18px;">
                    <?php foreach ($missing as $key): ?>
                        <li><?= sanitize((string)$key); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-top:12px;">
            <iframe title="Document preview" style="width:100%;min-height:720px;border:1px solid var(--border);border-radius:12px;" srcdoc="<?= htmlspecialchars((string)($doc['renderedHtml'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></iframe>
        </div>

        <form method="post" action="/contractor/create_doc_delete.php" style="margin-top:12px;">
            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
            <input type="hidden" name="docId" value="<?= sanitize($doc['docId'] ?? ''); ?>">
            <button class="btn secondary" type="submit">Delete Doc</button>
        </form>
        <?php
    });
});
