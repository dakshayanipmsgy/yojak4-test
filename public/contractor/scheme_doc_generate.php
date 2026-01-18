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
            foreach (scheme_pack_documents($scheme, $pack) as $doc) {
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

    $latest = scheme_document_latest_generation($schemeCode, $user['yojId'] ?? '', $caseId, $docId);
    $lockAfterGen = !empty($selectedDoc['generation']['lockAfterGen']);
    $allowManual = !empty($selectedDoc['generation']['allowManual']);
    $allowRegen = !empty($selectedDoc['generation']['allowRegen']);

    if (!$allowManual) {
        set_flash('error', 'Manual generation is disabled for this document.');
        redirect('/contractor/scheme_pack.php?schemeCode=' . urlencode($schemeCode) . '&caseId=' . urlencode($caseId) . '&packId=' . urlencode($packId));
    }
    if ($latest && !$allowRegen) {
        set_flash('error', 'Regeneration is disabled for this document.');
        redirect('/contractor/scheme_pack.php?schemeCode=' . urlencode($schemeCode) . '&caseId=' . urlencode($caseId) . '&packId=' . urlencode($packId));
    }
    if ($latest && $lockAfterGen) {
        set_flash('error', 'Document is locked after generation.');
        redirect('/contractor/scheme_pack.php?schemeCode=' . urlencode($schemeCode) . '&caseId=' . urlencode($caseId) . '&packId=' . urlencode($packId));
    }

    $result = scheme_document_generate($scheme, $case, $selectedPack, $selectedDoc, $values, $user['yojId'] ?? '');
    if (!empty($result['error']) && $result['error'] === 'missing_fields') {
        set_flash('error', 'Missing fields: ' . implode(', ', $result['missing'] ?? []));
        redirect('/contractor/scheme_pack.php?schemeCode=' . urlencode($schemeCode) . '&caseId=' . urlencode($caseId) . '&packId=' . urlencode($packId));
    }

    $runtimePath = scheme_case_pack_runtime_path($schemeCode, $user['yojId'] ?? '', $caseId, $packId);
    $runtime = readJson($runtimePath);
    $generated = $runtime['generatedDocs'] ?? [];
    if (!in_array($docId, $generated, true)) {
        $generated[] = $docId;
    }
    $runtime['generatedDocs'] = $generated;
    $runtime['status'] = scheme_pack_status_from_runtime($selectedPack, $runtime['missingFields'] ?? [], $generated, $runtime['workflowState'] ?? '');
    $runtime['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic($runtimePath, $runtime);

    scheme_append_timeline($schemeCode, $user['yojId'] ?? '', $caseId, ['event' => 'DOC_GENERATED', 'docId' => $docId]);

    set_flash('success', 'Document generated.');
    redirect('/contractor/scheme_pack.php?schemeCode=' . urlencode($schemeCode) . '&caseId=' . urlencode($caseId) . '&packId=' . urlencode($packId));
});
