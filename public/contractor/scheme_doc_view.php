<?php
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $schemeCode = strtoupper(trim($_GET['schemeCode'] ?? ''));
    $caseId = trim($_GET['caseId'] ?? '');
    $docId = trim($_GET['docId'] ?? '');
    $genId = trim($_GET['genId'] ?? '');
    if ($schemeCode === '' || $caseId === '' || $docId === '') {
        redirect('/contractor/schemes.php');
    }

    $case = readJson(scheme_case_core_path($schemeCode, $user['yojId'] ?? '', $caseId));
    if (!$case) {
        render_error_page('Case not found.');
    }

    if ($genId !== '') {
        $doc = readJson(scheme_document_generation_path($schemeCode, $user['yojId'] ?? '', $caseId, $docId, $genId));
    } else {
        $doc = scheme_document_latest_generation($schemeCode, $user['yojId'] ?? '', $caseId, $docId);
    }
    if (!$doc) {
        render_error_page('Document not generated yet.');
    }

    render_layout('Document View', function () use ($schemeCode, $caseId, $docId, $doc) {
        ?>
        <style>
            .doc-shell { padding:16px; background:var(--surface); border:1px solid var(--border); border-radius:12px; }
            .placeholder-line { border-bottom:1px solid var(--border); display:inline-block; min-width:120px; }
        </style>
        <h1><?= sanitize($doc['label'] ?? 'Document'); ?></h1>
        <p class="muted">Generated at <?= sanitize($doc['generatedAt'] ?? ''); ?></p>
        <a class="btn secondary" href="/contractor/scheme_doc_print.php?schemeCode=<?= urlencode($schemeCode); ?>&caseId=<?= urlencode($caseId); ?>&docId=<?= urlencode($docId); ?>&mode=print" target="_blank">Print</a>
        <div class="doc-shell" style="margin-top:16px;">
            <?= $doc['renderedHtml'] ?? ''; ?>
        </div>
        <?php
    });
});
