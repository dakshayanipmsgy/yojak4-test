<?php
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $schemeCode = strtoupper(trim($_GET['schemeCode'] ?? ''));
    $caseId = trim($_GET['caseId'] ?? '');
    $packId = trim($_GET['packId'] ?? '');
    if ($schemeCode === '' || $caseId === '' || $packId === '') {
        redirect('/contractor/schemes.php');
    }
    $enabled = contractor_enabled_schemes($user['yojId'] ?? '');
    $version = $enabled[$schemeCode] ?? '';
    if (!$version) {
        set_flash('error', 'Scheme not enabled yet.');
        redirect('/contractor/schemes.php');
    }

    $case = readJson(scheme_case_core_path($schemeCode, $user['yojId'] ?? '', $caseId));
    if (!$case) {
        render_error_page('Case not found.');
    }
    $scheme = load_scheme_version($schemeCode, $case['schemeVersion'] ?? $version);
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
    $runtime = scheme_update_pack_runtime($schemeCode, $user['yojId'] ?? '', $caseId, $selectedPack, $values);

    render_layout('Pack', function () use ($schemeCode, $caseId, $packId, $selectedPack, $values, $runtime, $scheme, $user) {
        $roleId = $user['roleId'] ?? 'vendor_admin';
        $documents = scheme_pack_documents($scheme, $selectedPack);
        ?>
        <style>
            .grid { display:grid; gap:16px; }
            .card { padding:16px; }
            .field { display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
            input, textarea, select { padding:10px; border-radius:8px; border:1px solid var(--border); }
            .pill { padding:4px 10px; border-radius:999px; font-size:12px; border:1px solid var(--border); background:var(--surface-2); }
            .muted { color: var(--muted); }
        </style>
        <h1><?= sanitize($selectedPack['label'] ?? ''); ?> · Pack</h1>
        <p>Status: <span class="pill"><?= sanitize(scheme_pack_status_label($runtime['status'] ?? '')); ?></span></p>

        <div class="grid">
            <div class="card">
                <h3>Missing Fields</h3>
                <?php if (!empty($runtime['missingFields'])) { ?>
                    <ul>
                        <?php foreach ($runtime['missingFields'] as $missing) { ?>
                            <li><?= sanitize($missing); ?></li>
                        <?php } ?>
                    </ul>
                <?php } else { ?>
                    <p class="muted">All required fields are complete.</p>
                <?php } ?>
            </div>

            <div class="card">
                <h3>Fields</h3>
                <p class="muted">Update case data for this pack.</p>
                <a class="btn" href="/contractor/scheme_fields.php?schemeCode=<?= urlencode($schemeCode); ?>&caseId=<?= urlencode($caseId); ?>">Open Fields Form</a>
            </div>

            <div class="card">
                <h3>Documents</h3>
                <?php if (empty($documents)) { ?>
                    <p class="muted">No documents configured.</p>
                <?php } ?>
                <?php foreach ($documents as $doc) { ?>
                    <div style="margin-bottom:12px;">
                        <strong><?= sanitize($doc['label'] ?? ''); ?></strong>
                        <form method="post" action="/contractor/scheme_doc_generate.php" style="margin-top:8px;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                            <input type="hidden" name="caseId" value="<?= sanitize($caseId); ?>">
                            <input type="hidden" name="packId" value="<?= sanitize($packId); ?>">
                            <input type="hidden" name="docId" value="<?= sanitize($doc['docId']); ?>">
                            <button class="btn secondary" type="submit">Generate</button>
                        </form>
                        <?php $latest = scheme_document_latest_generation($schemeCode, $user['yojId'] ?? '', $caseId, $doc['docId']); ?>
                        <?php if ($latest) { ?>
                            <a class="btn secondary" style="margin-top:6px;" href="/contractor/scheme_doc_view.php?schemeCode=<?= urlencode($schemeCode); ?>&caseId=<?= urlencode($caseId); ?>&docId=<?= urlencode($doc['docId']); ?>">View Latest</a>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>

            <div class="card">
                <h3>Workflow</h3>
                <p class="muted">State: <?= sanitize($runtime['workflowState'] ?? 'Draft'); ?></p>
                <?php if (!empty($selectedPack['workflow']['states'])) { ?>
                    <p class="muted">States: <?= sanitize(implode(', ', $selectedPack['workflow']['states'])); ?></p>
                <?php } ?>
                <?php if (!empty($selectedPack['workflow']['enabled'])) { ?>
                    <form method="post" action="/contractor/scheme_workflow_transition.php" style="margin-top:8px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                        <input type="hidden" name="caseId" value="<?= sanitize($caseId); ?>">
                        <input type="hidden" name="packId" value="<?= sanitize($packId); ?>">
                        <button class="btn secondary" type="submit">Move to Next State</button>
                    </form>
                <?php } ?>
            </div>
        </div>
        <?php
    });
});
