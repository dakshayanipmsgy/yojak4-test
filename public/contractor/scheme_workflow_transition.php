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
    if ($schemeCode === '' || $caseId === '' || $packId === '') {
        redirect('/contractor/schemes.php');
    }

    $case = readJson(scheme_case_core_path($schemeCode, $user['yojId'] ?? '', $caseId));
    if (!$case) {
        render_error_page('Case not found.');
    }
    $scheme = load_scheme_version($schemeCode, $case['schemeVersion'] ?? '');
    $values = scheme_case_values($schemeCode, $user['yojId'] ?? '', $caseId);

    $selectedPack = null;
    foreach ($scheme['packs'] ?? [] as $pack) {
        if (($pack['packId'] ?? '') === $packId) {
            $selectedPack = $pack;
            break;
        }
    }
    if (!$selectedPack) {
        render_error_page('Pack not found.');
    }

    $runtimePath = scheme_case_pack_runtime_path($schemeCode, $user['yojId'] ?? '', $caseId, $packId);
    $runtime = readJson($runtimePath);
    $currentState = $runtime['workflowState'] ?? ($selectedPack['workflow']['defaultState'] ?? ($selectedPack['workflow']['states'][0] ?? 'Draft'));
    $nextState = scheme_pack_next_state($selectedPack, $currentState);

    if ($nextState === '') {
        set_flash('error', 'No further workflow state.');
        redirect('/contractor/scheme_pack.php?schemeCode=' . urlencode($schemeCode) . '&caseId=' . urlencode($caseId) . '&packId=' . urlencode($packId));
    }

    $transitionConfig = null;
    foreach ($selectedPack['workflow']['transitions'] ?? [] as $transition) {
        if (($transition['from'] ?? '') === $currentState && ($transition['to'] ?? '') === $nextState) {
            $transitionConfig = $transition;
            break;
        }
    }

    if ($transitionConfig) {
        $allowedRoles = $transitionConfig['roles'] ?? [];
        if ($allowedRoles && !in_array($user['roleId'] ?? '', $allowedRoles, true)) {
            set_flash('error', 'You are not allowed to perform this transition.');
            redirect('/contractor/scheme_pack.php?schemeCode=' . urlencode($schemeCode) . '&caseId=' . urlencode($caseId) . '&packId=' . urlencode($packId));
        }
        $requiredFields = $transitionConfig['requiredFields'] ?? [];
        $missingFields = [];
        foreach ($requiredFields as $key) {
            if (($values[$key] ?? '') === '') {
                $missingFields[] = $key;
            }
        }
        if ($missingFields) {
            set_flash('error', 'Missing required fields: ' . implode(', ', $missingFields));
            redirect('/contractor/scheme_pack.php?schemeCode=' . urlencode($schemeCode) . '&caseId=' . urlencode($caseId) . '&packId=' . urlencode($packId));
        }
        $requiredDocs = $transitionConfig['requiredDocs'] ?? [];
        $generatedDocs = $runtime['generatedDocs'] ?? [];
        $missingDocs = array_values(array_diff($requiredDocs, $generatedDocs));
        if ($missingDocs) {
            set_flash('error', 'Missing required documents: ' . implode(', ', $missingDocs));
            redirect('/contractor/scheme_pack.php?schemeCode=' . urlencode($schemeCode) . '&caseId=' . urlencode($caseId) . '&packId=' . urlencode($packId));
        }
    }

    $runtime['workflowState'] = $nextState;
    $runtime['status'] = scheme_pack_status_from_runtime($selectedPack, $runtime['missingFields'] ?? [], $runtime['generatedDocs'] ?? [], $nextState);
    $runtime['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic($runtimePath, $runtime);

    scheme_append_timeline($schemeCode, $user['yojId'] ?? '', $caseId, ['event' => 'WORKFLOW_TRANSITION', 'packId' => $packId, 'from' => $currentState, 'to' => $nextState]);

    set_flash('success', 'Workflow updated to ' . $nextState . '.');
    redirect('/contractor/scheme_pack.php?schemeCode=' . urlencode($schemeCode) . '&caseId=' . urlencode($caseId) . '&packId=' . urlencode($packId));
});
