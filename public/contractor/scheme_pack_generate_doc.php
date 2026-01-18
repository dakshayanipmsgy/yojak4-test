<?php
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/schemes.php');
    }
    require_csrf();
    $schemeCode = strtoupper(trim($_POST['schemeCode'] ?? ''));
    $caseId = trim($_POST['caseId'] ?? '');
    $packId = trim($_POST['packId'] ?? '');
    $docId = trim($_POST['docId'] ?? '');

    if ($schemeCode === '' || $caseId === '' || $packId === '' || $docId === '') {
        redirect('/contractor/schemes.php');
    }

    $case = readJson(scheme_case_core_path($schemeCode, $user['yojId'] ?? '', $caseId));
    if (!$case) {
        render_error_page('Case not found.');
    }
    $scheme = load_scheme_version($schemeCode, $case['schemeVersion'] ?? '');
    $values = scheme_case_values($schemeCode, $user['yojId'] ?? '', $caseId);

    $selectedPack = null;
    $selectedDoc = null;
    foreach ($scheme['packs'] ?? [] as $pack) {
        if (($pack['packId'] ?? '') === $packId) {
            $selectedPack = $pack;
            foreach ($pack['documents'] ?? [] as $doc) {
                if (($doc['docId'] ?? '') === $docId) {
                    $selectedDoc = $doc;
                    break;
                }
            }
            break;
        }
    }

    if (!$selectedPack || !$selectedDoc) {
        render_error_page('Document not found.');
    }

    $rendered = scheme_render_template($selectedDoc['templateBody'] ?? '', $values);
    $docPayload = [
        'docId' => $docId,
        'label' => $selectedDoc['label'] ?? $docId,
        'schemeVersion' => $case['schemeVersion'] ?? '',
        'generatedAt' => now_kolkata()->format(DateTime::ATOM),
        'renderedHtml' => $rendered,
    ];

    $docDir = scheme_case_documents_dir($schemeCode, $user['yojId'] ?? '', $caseId);
    if (!is_dir($docDir)) {
        mkdir($docDir, 0775, true);
    }
    writeJsonAtomic($docDir . '/' . $docId . '.json', $docPayload);

    $runtimePath = scheme_case_pack_runtime_path($schemeCode, $user['yojId'] ?? '', $caseId, $packId);
    $runtime = readJson($runtimePath);
    $generated = $runtime['generatedDocs'] ?? [];
    if (!in_array($docId, $generated, true)) {
        $generated[] = $docId;
    }
    $runtime['generatedDocs'] = $generated;
    if (($runtime['status'] ?? '') === 'ready') {
        $runtime['status'] = 'generated';
    }
    $runtime['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic($runtimePath, $runtime);

    set_flash('success', 'Document generated.');
    redirect('/contractor/scheme_pack.php?schemeCode=' . urlencode($schemeCode) . '&caseId=' . urlencode($caseId) . '&packId=' . urlencode($packId));
});
