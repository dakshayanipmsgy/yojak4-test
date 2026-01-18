<?php
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $schemeCode = strtoupper(trim($_GET['schemeCode'] ?? ''));
    $caseId = trim($_GET['caseId'] ?? '');
    if ($schemeCode === '' || $caseId === '') {
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
    $roleId = $user['roleId'] ?? 'vendor_admin';

    render_layout('Case Fields', function () use ($schemeCode, $caseId, $scheme, $values, $roleId) {
        ?>
        <style>
            .grid { display:grid; gap:16px; }
            .card { padding:16px; }
            .field { display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
            input, textarea, select { padding:10px; border-radius:8px; border:1px solid var(--border); }
            .muted { color: var(--muted); }
        </style>
        <h1>Case Fields</h1>
        <div class="grid">
            <div class="card">
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
                        $options = $field['validation']['options'] ?? [];
                    ?>
                        <div class="field">
                            <label><?= sanitize($field['label'] ?? ''); ?> <?= !empty($field['required']) ? '<span class="muted">(required)</span>' : ''; ?></label>
                            <?php if ($type === 'textarea') { ?>
                                <textarea name="fields[<?= sanitize($field['key']); ?>]" <?= $readonly ? 'readonly' : ''; ?>><?= sanitize((string)$value); ?></textarea>
                            <?php } elseif ($type === 'dropdown' && !empty($options)) { ?>
                                <select name="fields[<?= sanitize($field['key']); ?>]" <?= $readonly ? 'disabled' : ''; ?>>
                                    <option value="">Select</option>
                                    <?php foreach ($options as $option) { ?>
                                        <option value="<?= sanitize($option); ?>" <?= ((string)$value === (string)$option) ? 'selected' : ''; ?>><?= sanitize($option); ?></option>
                                    <?php } ?>
                                </select>
                            <?php } elseif ($type === 'yesno') { ?>
                                <select name="fields[<?= sanitize($field['key']); ?>]" <?= $readonly ? 'disabled' : ''; ?>>
                                    <option value="">Select</option>
                                    <option value="Yes" <?= ((string)$value === 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                    <option value="No" <?= ((string)$value === 'No') ? 'selected' : ''; ?>>No</option>
                                </select>
                            <?php } else { ?>
                                <input type="<?= $type === 'date' ? 'date' : 'text'; ?>" name="fields[<?= sanitize($field['key']); ?>]" value="<?= sanitize((string)$value); ?>" <?= $readonly ? 'readonly' : ''; ?>>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <button class="btn" type="submit">Save Fields</button>
                    <a class="btn secondary" href="/contractor/scheme_case.php?schemeCode=<?= urlencode($schemeCode); ?>&caseId=<?= urlencode($caseId); ?>" style="margin-left:8px;">Back to Case</a>
                </form>
            </div>
        </div>
        <?php
    });
});
