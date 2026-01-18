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

    render_layout('Pack', function () use ($schemeCode, $caseId, $packId, $selectedPack, $values, $runtime, $scheme) {
        $roleId = 'vendor_admin';
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
        <p>Status: <span class="pill"><?= sanitize($runtime['status'] ?? ''); ?></span></p>

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
                <form method="post" action="/contractor/scheme_fields_save.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                    <input type="hidden" name="caseId" value="<?= sanitize($caseId); ?>">
                    <?php foreach ($scheme['fieldDictionary'] ?? [] as $field) {
                        $viewRoles = $field['visibility']['view'] ?? [];
                        $editRoles = $field['visibility']['edit'] ?? [];
                        if ($viewRoles && !in_array($roleId, $viewRoles, true)) {
                            continue;
                        }
                        $value = $values[$field['key']] ?? '';
                        $readonly = ($editRoles && !in_array($roleId, $editRoles, true));
                        $type = $field['type'] ?? 'text';
                    ?>
                        <div class="field">
                            <label><?= sanitize($field['label'] ?? ''); ?></label>
                            <?php if ($type === 'textarea') { ?>
                                <textarea name="fields[<?= sanitize($field['key']); ?>]" <?= $readonly ? 'readonly' : ''; ?>><?= sanitize((string)$value); ?></textarea>
                            <?php } else { ?>
                                <input type="<?= $type === 'date' ? 'date' : 'text'; ?>" name="fields[<?= sanitize($field['key']); ?>]" value="<?= sanitize((string)$value); ?>" <?= $readonly ? 'readonly' : ''; ?>>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <button class="btn" type="submit">Save Fields</button>
                </form>
            </div>

            <div class="card">
                <h3>Documents</h3>
                <?php if (empty($selectedPack['documents'])) { ?>
                    <p class="muted">No documents configured.</p>
                <?php } ?>
                <?php foreach ($selectedPack['documents'] ?? [] as $doc) { ?>
                    <div style="margin-bottom:12px;">
                        <strong><?= sanitize($doc['label'] ?? ''); ?></strong>
                        <form method="post" action="/contractor/scheme_pack_generate_doc.php" style="margin-top:8px;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                            <input type="hidden" name="caseId" value="<?= sanitize($caseId); ?>">
                            <input type="hidden" name="packId" value="<?= sanitize($packId); ?>">
                            <input type="hidden" name="docId" value="<?= sanitize($doc['docId']); ?>">
                            <button class="btn secondary" type="submit">Generate</button>
                        </form>
                    </div>
                <?php } ?>
            </div>

            <div class="card">
                <h3>Workflow</h3>
                <p class="muted">State: <?= sanitize($runtime['workflowState'] ?? 'Draft'); ?></p>
                <?php if (!empty($selectedPack['workflow']['states'])) { ?>
                    <p class="muted">States: <?= sanitize(implode(', ', $selectedPack['workflow']['states'])); ?></p>
                <?php } ?>
            </div>
        </div>
        <?php
    });
});
