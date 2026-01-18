<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('scheme_builder');
    $schemeCode = strtoupper(trim($_GET['schemeCode'] ?? ''));
    $version = $_GET['version'] ?? 'draft';
    if ($schemeCode === '') {
        redirect('/superadmin/schemes/index.php');
    }

    $draft = load_scheme_draft($schemeCode);
    if (!$draft) {
        render_error_page('Scheme draft not found.');
    }

    $tab = $_GET['tab'] ?? 'overview';
    $roles = $draft['roles'] ?? [];
    $modules = $draft['modules'] ?? [];
    $fields = $draft['fieldDictionary'] ?? [];
    $packs = $draft['packs'] ?? [];
    $documents = $draft['documents'] ?? [];

    render_layout('Scheme Builder', function () use ($schemeCode, $draft, $tab, $roles, $modules, $fields, $packs, $documents, $actor) {
        ?>
        <style>
            .tabs { display:flex; gap:12px; flex-wrap:wrap; margin:16px 0; }
            .tabs a { padding:8px 14px; border-radius:999px; border:1px solid var(--border); color:var(--text); }
            .tabs a.active { background:var(--primary); color:var(--primary-contrast); border-color:var(--primary-dark); }
            .grid { display:grid; gap:16px; }
            .field { display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
            input, textarea, select { padding:10px; border-radius:8px; border:1px solid var(--border); }
            textarea { min-height:120px; }
            .muted { color: var(--muted); }
            .two-col { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px; }
            .pill { padding:4px 10px; border-radius:999px; font-size:12px; border:1px solid var(--border); background:var(--surface-2); }
            .list { margin:0; padding-left:18px; }
            .sidebar { background:var(--surface-2); padding:12px; border-radius:12px; border:1px solid var(--border); }
            .placeholder-btn { display:flex; justify-content:space-between; width:100%; padding:8px 10px; border-radius:8px; border:1px solid var(--border); background:var(--surface); cursor:pointer; }
            table { width:100%; border-collapse:collapse; }
            th, td { text-align:left; padding:8px; border-bottom:1px solid var(--border); vertical-align:top; }
            .badge { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:12px; border:1px solid var(--border); background:var(--surface-2); }
            .actions { display:flex; gap:8px; flex-wrap:wrap; }
        </style>
        <h1>Scheme Builder · <?= sanitize($schemeCode); ?></h1>
        <div class="tabs">
            <?php
            $tabs = [
                'overview' => 'Overview',
                'case_roles' => 'Case & Roles',
                'dictionary' => 'Data Dictionary',
                'packs' => 'Packs',
                'documents' => 'Documents',
                'workflows' => 'Workflows',
                'publish' => 'Publish',
                'advanced' => 'Advanced',
            ];
            foreach ($tabs as $key => $label) {
                $active = $tab === $key ? 'active' : '';
                echo '<a class="' . $active . '" href="/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=' . urlencode($key) . '">' . sanitize($label) . '</a>';
            }
            ?>
        </div>

        <?php if ($tab === 'overview') { ?>
            <div class="grid">
                <div class="card" style="padding:16px;">
                    <h3>Scheme Overview</h3>
                    <form method="post" action="/superadmin/schemes/save_overview.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                        <div class="field">
                            <label>Scheme Name</label>
                            <input name="name" value="<?= sanitize($draft['name'] ?? ''); ?>">
                        </div>
                        <div class="field">
                            <label>Description</label>
                            <textarea name="description"><?= sanitize($draft['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="field">
                            <label>Case Label</label>
                            <input name="caseLabel" value="<?= sanitize($draft['caseLabel'] ?? 'Beneficiary'); ?>">
                        </div>
                        <button class="btn" type="submit">Save Overview</button>
                    </form>
                </div>
                <div class="card" style="padding:16px;">
                    <h3>Version Snapshot</h3>
                    <p class="muted">Draft version stays editable. Publish to create immutable versions for cases.</p>
                    <p><span class="badge">Current Draft</span></p>
                </div>
            </div>
        <?php } ?>

        <?php if ($tab === 'case_roles') { ?>
            <div class="grid">
                <div class="card" style="padding:16px;">
                    <h3>Roles</h3>
                    <ul class="list">
                        <?php foreach ($roles as $role) { ?>
                            <li><strong><?= sanitize($role['roleId'] ?? ''); ?></strong> — <?= sanitize($role['label'] ?? ''); ?></li>
                        <?php } ?>
                    </ul>
                    <form method="post" action="/superadmin/schemes/save_case_roles.php" style="margin-top:12px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                        <input type="hidden" name="action" value="add_role">
                        <div class="field">
                            <label>Role ID</label>
                            <input name="roleId" placeholder="vendor_admin" required>
                        </div>
                        <div class="field">
                            <label>Label</label>
                            <input name="label" placeholder="Vendor Admin">
                        </div>
                        <button class="btn secondary" type="submit">Add Role</button>
                    </form>
                </div>
                <div class="card" style="padding:16px;">
                    <h3>Modules</h3>
                    <ul class="list">
                        <?php foreach ($modules as $module) { ?>
                            <li><strong><?= sanitize($module['moduleId'] ?? ''); ?></strong> — <?= sanitize($module['label'] ?? ''); ?></li>
                        <?php } ?>
                    </ul>
                    <form method="post" action="/superadmin/schemes/save_case_roles.php" style="margin-top:12px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                        <input type="hidden" name="action" value="add_module">
                        <div class="field">
                            <label>Module ID</label>
                            <input name="moduleId" placeholder="application" required>
                        </div>
                        <div class="field">
                            <label>Label</label>
                            <input name="label" placeholder="Application">
                        </div>
                        <button class="btn secondary" type="submit">Add Module</button>
                    </form>
                </div>
            </div>
        <?php } ?>

        <?php if ($tab === 'dictionary') { ?>
            <div class="grid">
                <div class="card" style="padding:16px;">
                    <h3>Fields</h3>
                    <p class="muted">Auto-keys follow <code>module.slug</code> or <code>case.slug</code> with unique suffixes.</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Label</th>
                                <th>Key</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Module</th>
                                <th>Roles</th>
                                <th>Validation</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$fields) { ?>
                            <tr><td colspan="7" class="muted">No fields added yet.</td></tr>
                        <?php } ?>
                        <?php foreach ($fields as $field) {
                            $validation = [];
                            if (!empty($field['validation']['minLen'])) { $validation[] = 'minLen ' . (int)$field['validation']['minLen']; }
                            if (!empty($field['validation']['maxLen'])) { $validation[] = 'maxLen ' . (int)$field['validation']['maxLen']; }
                            if (!empty($field['validation']['pattern'])) { $validation[] = 'pattern'; }
                            if (!empty($field['validation']['min'])) { $validation[] = 'min ' . $field['validation']['min']; }
                            if (!empty($field['validation']['max'])) { $validation[] = 'max ' . $field['validation']['max']; }
                            if (!empty($field['validation']['dateMin'])) { $validation[] = 'date ≥ ' . $field['validation']['dateMin']; }
                            if (!empty($field['validation']['dateMax'])) { $validation[] = 'date ≤ ' . $field['validation']['dateMax']; }
                            if (!empty($field['validation']['options'])) { $validation[] = 'options'; }
                            if (!empty($field['unique'])) { $validation[] = 'unique'; }
                            $roleSummary = 'View: ' . implode(', ', $field['visibility']['view'] ?? []) . ' | Edit: ' . implode(', ', $field['visibility']['edit'] ?? []);
                        ?>
                            <tr>
                                <td><?= sanitize($field['label'] ?? ''); ?></td>
                                <td><code><?= sanitize($field['key'] ?? ''); ?></code></td>
                                <td><?= sanitize($field['type'] ?? ''); ?></td>
                                <td><?= !empty($field['required']) ? 'Yes' : 'No'; ?></td>
                                <td><?= sanitize($field['moduleId'] ?? ''); ?></td>
                                <td><?= sanitize($roleSummary); ?></td>
                                <td><?= sanitize(implode(', ', $validation)); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="card" style="padding:16px;">
                    <h3>Add Field</h3>
                    <form method="post" action="/superadmin/schemes/fields_add.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                        <div class="field">
                            <label>Field Label</label>
                            <input name="label" placeholder="Customer Name" required>
                        </div>
                        <div class="field">
                            <label>Type</label>
                            <select name="type">
                                <option value="text">Text</option>
                                <option value="number">Number</option>
                                <option value="date">Date</option>
                                <option value="dropdown">Dropdown</option>
                                <option value="file">File</option>
                                <option value="textarea">Textarea</option>
                                <option value="yesno">Yes/No</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Module</label>
                            <select name="moduleId">
                                <option value="">case</option>
                                <?php foreach ($modules as $module) { ?>
                                    <option value="<?= sanitize($module['moduleId']); ?>"><?= sanitize($module['label']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Required</label>
                            <input type="checkbox" name="required" value="1">
                        </div>
                        <div class="two-col">
                            <div class="field">
                                <label>Min Length</label>
                                <input name="minLen" type="number" min="0">
                            </div>
                            <div class="field">
                                <label>Max Length</label>
                                <input name="maxLen" type="number" min="0">
                            </div>
                        </div>
                        <div class="two-col">
                            <div class="field">
                                <label>Min (Number)</label>
                                <input name="min" type="number" step="any">
                            </div>
                            <div class="field">
                                <label>Max (Number)</label>
                                <input name="max" type="number" step="any">
                            </div>
                        </div>
                        <div class="two-col">
                            <div class="field">
                                <label>Min Date</label>
                                <input name="dateMin" type="date">
                            </div>
                            <div class="field">
                                <label>Max Date</label>
                                <input name="dateMax" type="date">
                            </div>
                        </div>
                        <div class="field">
                            <label>Pattern</label>
                            <input name="pattern" placeholder="Optional regex">
                        </div>
                        <div class="field">
                            <label>Dropdown Options (comma separated)</label>
                            <input name="options" placeholder="Option A, Option B">
                        </div>
                        <div class="field">
                            <label>Unique across contractor cases</label>
                            <input type="checkbox" name="unique" value="1">
                        </div>
                        <div class="field">
                            <label>Visibility (View)</label>
                            <?php foreach ($roles as $role) { ?>
                                <label><input type="checkbox" name="viewRoles[]" value="<?= sanitize($role['roleId']); ?>" checked> <?= sanitize($role['label']); ?></label><br>
                            <?php } ?>
                        </div>
                        <div class="field">
                            <label>Visibility (Edit)</label>
                            <?php foreach ($roles as $role) { ?>
                                <label><input type="checkbox" name="editRoles[]" value="<?= sanitize($role['roleId']); ?>" checked> <?= sanitize($role['label']); ?></label><br>
                            <?php } ?>
                        </div>
                        <button class="btn" type="submit">Add Field</button>
                    </form>
                </div>
                <div class="card" style="padding:16px;">
                    <h3>Update / Delete Field</h3>
                    <form method="post" action="/superadmin/schemes/fields_update.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                        <div class="field">
                            <label>Select Field</label>
                            <select name="key">
                                <?php foreach ($fields as $field) { ?>
                                    <option value="<?= sanitize($field['key']); ?>"><?= sanitize($field['label']); ?> (<?= sanitize($field['key']); ?>)</option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Label</label>
                            <input name="label" placeholder="Updated label">
                        </div>
                        <div class="field">
                            <label>Type</label>
                            <select name="type">
                                <option value="text">Text</option>
                                <option value="number">Number</option>
                                <option value="date">Date</option>
                                <option value="dropdown">Dropdown</option>
                                <option value="file">File</option>
                                <option value="textarea">Textarea</option>
                                <option value="yesno">Yes/No</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Module</label>
                            <select name="moduleId">
                                <option value="">case</option>
                                <?php foreach ($modules as $module) { ?>
                                    <option value="<?= sanitize($module['moduleId']); ?>"><?= sanitize($module['label']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Required</label>
                            <input type="checkbox" name="required" value="1">
                        </div>
                        <div class="two-col">
                            <div class="field">
                                <label>Min Length</label>
                                <input name="minLen" type="number" min="0">
                            </div>
                            <div class="field">
                                <label>Max Length</label>
                                <input name="maxLen" type="number" min="0">
                            </div>
                        </div>
                        <div class="field">
                            <label>Pattern</label>
                            <input name="pattern" placeholder="Optional regex">
                        </div>
                        <div class="field">
                            <label>Dropdown Options</label>
                            <input name="options" placeholder="Option A, Option B">
                        </div>
                        <div class="field">
                            <label>Unique</label>
                            <input type="checkbox" name="unique" value="1">
                        </div>
                        <div class="field">
                            <label>Visibility (View)</label>
                            <?php foreach ($roles as $role) { ?>
                                <label><input type="checkbox" name="viewRoles[]" value="<?= sanitize($role['roleId']); ?>"> <?= sanitize($role['label']); ?></label><br>
                            <?php } ?>
                        </div>
                        <div class="field">
                            <label>Visibility (Edit)</label>
                            <?php foreach ($roles as $role) { ?>
                                <label><input type="checkbox" name="editRoles[]" value="<?= sanitize($role['roleId']); ?>"> <?= sanitize($role['label']); ?></label><br>
                            <?php } ?>
                        </div>
                        <div class="actions">
                            <button class="btn" type="submit">Update Field</button>
                        </div>
                    </form>
                    <form method="post" action="/superadmin/schemes/fields_delete.php" style="margin-top:12px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                        <div class="field">
                            <label>Select Field</label>
                            <select name="key">
                                <?php foreach ($fields as $field) { ?>
                                    <option value="<?= sanitize($field['key']); ?>"><?= sanitize($field['label']); ?> (<?= sanitize($field['key']); ?>)</option>
                                <?php } ?>
                            </select>
                        </div>
                        <button class="btn secondary" type="submit">Delete Field</button>
                    </form>
                </div>
            </div>
        <?php } ?>

        <?php if ($tab === 'packs') { ?>
            <div class="grid">
                <div class="card" style="padding:16px;">
                    <h3>Packs</h3>
                    <?php if (!$packs) { ?><p class="muted">No packs defined yet.</p><?php } ?>
                    <?php foreach ($packs as $pack) { ?>
                        <div class="card" style="padding:12px; margin-top:12px;">
                            <strong><?= sanitize($pack['label'] ?? ''); ?></strong> <span class="pill"><?= sanitize($pack['packId'] ?? ''); ?></span>
                            <p class="muted">Module: <?= sanitize($pack['moduleId'] ?? ''); ?></p>
                            <p class="muted">Required fields: <?= sanitize(implode(', ', $pack['requiredFieldKeys'] ?? [])); ?></p>
                            <p class="muted">Documents: <?= sanitize(implode(', ', $pack['documentIds'] ?? [])); ?></p>
                            <p class="muted">Workflow: <?= !empty($pack['workflow']['enabled']) ? 'Enabled' : 'Disabled'; ?></p>
                        </div>
                    <?php } ?>
                </div>
                <div class="card" style="padding:16px;">
                    <h3>Add Pack</h3>
                    <form method="post" action="/superadmin/schemes/packs_add.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                        <div class="field">
                            <label>Label</label>
                            <input name="label" placeholder="Application Pack" required>
                        </div>
                        <div class="field">
                            <label>Pack ID</label>
                            <input name="packId" placeholder="application_pack" required>
                        </div>
                        <div class="field">
                            <label>Module</label>
                            <select name="moduleId">
                                <option value="">case</option>
                                <?php foreach ($modules as $module) { ?>
                                    <option value="<?= sanitize($module['moduleId']); ?>"><?= sanitize($module['label']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Required Fields</label>
                            <?php foreach ($fields as $field) { ?>
                                <label><input type="checkbox" name="requiredFieldKeys[]" value="<?= sanitize($field['key']); ?>"> <?= sanitize($field['label']); ?></label><br>
                            <?php } ?>
                        </div>
                        <div class="field">
                            <label>Documents Included</label>
                            <?php foreach ($documents as $doc) { ?>
                                <label><input type="checkbox" name="documentIds[]" value="<?= sanitize($doc['docId']); ?>"> <?= sanitize($doc['label']); ?></label><br>
                            <?php } ?>
                        </div>
                        <div class="field">
                            <label>Workflow Enabled</label>
                            <input type="checkbox" name="workflowEnabled" value="1">
                        </div>
                        <div class="field">
                            <label>Workflow States (comma separated)</label>
                            <input name="workflowStates" placeholder="Draft, Submitted, Approved, Completed">
                        </div>
                        <div class="field">
                            <label>Default Workflow State</label>
                            <input name="workflowDefaultState" placeholder="Draft">
                        </div>
                        <button class="btn" type="submit">Add Pack</button>
                    </form>
                </div>
                <div class="card" style="padding:16px;">
                    <h3>Update / Delete Pack</h3>
                    <form method="post" action="/superadmin/schemes/packs_update.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                        <div class="field">
                            <label>Select Pack</label>
                            <select name="packId">
                                <?php foreach ($packs as $pack) { ?>
                                    <option value="<?= sanitize($pack['packId']); ?>"><?= sanitize($pack['label']); ?> (<?= sanitize($pack['packId']); ?>)</option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Label</label>
                            <input name="label" placeholder="Updated Pack Label">
                        </div>
                        <div class="field">
                            <label>New Pack ID</label>
                            <input name="newPackId" placeholder="application_pack">
                        </div>
                        <div class="field">
                            <label>Module</label>
                            <select name="moduleId">
                                <option value="">case</option>
                                <?php foreach ($modules as $module) { ?>
                                    <option value="<?= sanitize($module['moduleId']); ?>"><?= sanitize($module['label']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Required Fields</label>
                            <?php foreach ($fields as $field) { ?>
                                <label><input type="checkbox" name="requiredFieldKeys[]" value="<?= sanitize($field['key']); ?>"> <?= sanitize($field['label']); ?></label><br>
                            <?php } ?>
                        </div>
                        <div class="field">
                            <label>Documents Included</label>
                            <?php foreach ($documents as $doc) { ?>
                                <label><input type="checkbox" name="documentIds[]" value="<?= sanitize($doc['docId']); ?>"> <?= sanitize($doc['label']); ?></label><br>
                            <?php } ?>
                        </div>
                        <div class="field">
                            <label>Workflow Enabled</label>
                            <input type="checkbox" name="workflowEnabled" value="1">
                        </div>
                        <div class="field">
                            <label>Workflow States (comma separated)</label>
                            <input name="workflowStates" placeholder="Draft, Submitted, Approved, Completed">
                        </div>
                        <div class="field">
                            <label>Default Workflow State</label>
                            <input name="workflowDefaultState" placeholder="Draft">
                        </div>
                        <button class="btn" type="submit">Update Pack</button>
                    </form>
                    <form method="post" action="/superadmin/schemes/packs_delete.php" style="margin-top:12px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                        <div class="field">
                            <label>Select Pack</label>
                            <select name="packId">
                                <?php foreach ($packs as $pack) { ?>
                                    <option value="<?= sanitize($pack['packId']); ?>"><?= sanitize($pack['label']); ?> (<?= sanitize($pack['packId']); ?>)</option>
                                <?php } ?>
                            </select>
                        </div>
                        <button class="btn secondary" type="submit">Delete Pack</button>
                    </form>
                </div>
            </div>
        <?php } ?>

        <?php if ($tab === 'documents') { ?>
            <div class="grid">
                <div class="card" style="padding:16px;">
                    <h3>Documents Library</h3>
                    <?php if (!$documents) { ?><p class="muted">No documents defined yet.</p><?php } ?>
                    <?php foreach ($documents as $doc) { ?>
                        <div class="card" style="padding:12px; margin-top:12px;">
                            <strong><?= sanitize($doc['label'] ?? ''); ?></strong> <span class="pill"><?= sanitize($doc['docId'] ?? ''); ?></span>
                            <p class="muted">Auto-generate: <?= !empty($doc['generation']['auto']) ? 'Yes' : 'No'; ?> · Manual: <?= !empty($doc['generation']['allowManual']) ? 'Yes' : 'No'; ?> · Regen: <?= !empty($doc['generation']['allowRegen']) ? 'Yes' : 'No'; ?></p>
                        </div>
                    <?php } ?>
                </div>
                <div class="card" style="padding:16px;">
                    <h3>Add Document</h3>
                    <form method="post" action="/superadmin/schemes/docs_add.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                        <div class="field">
                            <label>Doc ID</label>
                            <input name="docId" placeholder="application_form" required>
                        </div>
                        <div class="field">
                            <label>Label</label>
                            <input name="label" placeholder="Application Form">
                        </div>
                        <div class="two-col">
                            <div>
                                <div class="field">
                                    <label>Template</label>
                                    <textarea name="templateBody" class="template-editor" data-target="add"></textarea>
                                </div>
                            </div>
                            <div class="sidebar">
                                <strong>Insert Field</strong>
                                <p class="muted">Click a field to insert placeholder.</p>
                                <div style="display:grid; gap:8px;">
                                    <?php foreach ($fields as $field) { ?>
                                        <button class="placeholder-btn" type="button" data-key="<?= sanitize($field['key']); ?>">
                                            <span><?= sanitize($field['label']); ?></span>
                                            <span class="muted">{{field:<?= sanitize($field['key']); ?>}}</span>
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <div class="two-col" style="margin-top:12px;">
                            <label><input type="checkbox" name="autoGenerate" value="1"> Auto-generate on case creation</label>
                            <label><input type="checkbox" name="allowManual" value="1" checked> Allow manual generation</label>
                            <label><input type="checkbox" name="allowRegen" value="1" checked> Allow regeneration</label>
                            <label><input type="checkbox" name="lockAfterGen" value="1"> Lock after generation</label>
                        </div>
                        <div class="two-col" style="margin-top:12px;">
                            <label><input type="checkbox" name="vendorVisible" value="1" checked> Vendor only</label>
                            <label><input type="checkbox" name="customerDownload" value="1"> Vendor + Customer downloadable</label>
                            <label><input type="checkbox" name="authorityOnly" value="1"> Authority only</label>
                        </div>
                        <button class="btn" type="submit" style="margin-top:12px;">Add Document</button>
                    </form>
                </div>
                <div class="card" style="padding:16px;">
                    <h3>Update / Delete Document</h3>
                    <form method="post" action="/superadmin/schemes/docs_update.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                        <div class="field">
                            <label>Select Document</label>
                            <select name="docId">
                                <?php foreach ($documents as $doc) { ?>
                                    <option value="<?= sanitize($doc['docId']); ?>"><?= sanitize($doc['label']); ?> (<?= sanitize($doc['docId']); ?>)</option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>New Doc ID</label>
                            <input name="newDocId" placeholder="application_form">
                        </div>
                        <div class="field">
                            <label>Label</label>
                            <input name="label" placeholder="Updated label">
                        </div>
                        <div class="two-col">
                            <div>
                                <div class="field">
                                    <label>Template</label>
                                    <textarea name="templateBody" class="template-editor" data-target="update"></textarea>
                                </div>
                            </div>
                            <div class="sidebar">
                                <strong>Insert Field</strong>
                                <p class="muted">Click a field to insert placeholder.</p>
                                <div style="display:grid; gap:8px;">
                                    <?php foreach ($fields as $field) { ?>
                                        <button class="placeholder-btn" type="button" data-key="<?= sanitize($field['key']); ?>">
                                            <span><?= sanitize($field['label']); ?></span>
                                            <span class="muted">{{field:<?= sanitize($field['key']); ?>}}</span>
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <div class="two-col" style="margin-top:12px;">
                            <label><input type="checkbox" name="autoGenerate" value="1"> Auto-generate on case creation</label>
                            <label><input type="checkbox" name="allowManual" value="1" checked> Allow manual generation</label>
                            <label><input type="checkbox" name="allowRegen" value="1" checked> Allow regeneration</label>
                            <label><input type="checkbox" name="lockAfterGen" value="1"> Lock after generation</label>
                        </div>
                        <div class="two-col" style="margin-top:12px;">
                            <label><input type="checkbox" name="vendorVisible" value="1" checked> Vendor only</label>
                            <label><input type="checkbox" name="customerDownload" value="1"> Vendor + Customer downloadable</label>
                            <label><input type="checkbox" name="authorityOnly" value="1"> Authority only</label>
                        </div>
                        <button class="btn" type="submit" style="margin-top:12px;">Update Document</button>
                    </form>
                    <form method="post" action="/superadmin/schemes/docs_delete.php" style="margin-top:12px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                        <div class="field">
                            <label>Select Document</label>
                            <select name="docId">
                                <?php foreach ($documents as $doc) { ?>
                                    <option value="<?= sanitize($doc['docId']); ?>"><?= sanitize($doc['label']); ?> (<?= sanitize($doc['docId']); ?>)</option>
                                <?php } ?>
                            </select>
                        </div>
                        <button class="btn secondary" type="submit">Delete Document</button>
                    </form>
                </div>
            </div>
            <script>
                let activeEditor = null;
                document.querySelectorAll('.template-editor').forEach(editor => {
                    editor.addEventListener('focus', () => {
                        activeEditor = editor;
                    });
                });
                document.querySelectorAll('.placeholder-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const editor = activeEditor || document.querySelector('.template-editor');
                        if (!editor) return;
                        const key = btn.dataset.key || '';
                        const placeholder = `{{field:${key}}}`;
                        const start = editor.selectionStart || 0;
                        const end = editor.selectionEnd || 0;
                        const text = editor.value;
                        editor.value = text.slice(0, start) + placeholder + text.slice(end);
                        editor.focus();
                        const pos = start + placeholder.length;
                        editor.setSelectionRange(pos, pos);
                    });
                });
            </script>
        <?php } ?>

        <?php if ($tab === 'workflows') { ?>
            <div class="grid">
                <div class="card" style="padding:16px;">
                    <h3>Pack Workflows</h3>
                    <?php foreach ($packs as $pack) {
                        $states = $pack['workflow']['states'] ?? [];
                        $transitions = $pack['workflow']['transitions'] ?? [];
                    ?>
                        <div class="card" style="padding:12px; margin-bottom:12px;">
                            <strong><?= sanitize($pack['label'] ?? ''); ?></strong>
                            <p class="muted">States: <?= sanitize(implode(', ', $states)); ?></p>
                            <p class="muted">Transitions: <?= sanitize(count($transitions)); ?></p>
                        </div>
                    <?php } ?>
                </div>
                <div class="card" style="padding:16px;">
                    <h3>Update Workflow</h3>
                    <form method="post" action="/superadmin/schemes/workflows_update.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                        <div class="field">
                            <label>Pack</label>
                            <select name="packId">
                                <?php foreach ($packs as $pack) { ?>
                                    <option value="<?= sanitize($pack['packId']); ?>"><?= sanitize($pack['label']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Enabled</label>
                            <input type="checkbox" name="workflowEnabled" value="1">
                        </div>
                        <div class="field">
                            <label>States (comma separated)</label>
                            <input name="workflowStates" placeholder="Draft, Submitted, Approved, Completed">
                        </div>
                        <div class="field">
                            <label>Default State</label>
                            <input name="workflowDefaultState" placeholder="Draft">
                        </div>
                        <button class="btn" type="submit">Save Workflow</button>
                    </form>
                </div>
                <div class="card" style="padding:16px;">
                    <h3>Add Transition (forward-only)</h3>
                    <form method="post" action="/superadmin/schemes/workflows_update.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                        <input type="hidden" name="action" value="add_transition">
                        <div class="field">
                            <label>Pack</label>
                            <select name="packId">
                                <?php foreach ($packs as $pack) { ?>
                                    <option value="<?= sanitize($pack['packId']); ?>"><?= sanitize($pack['label']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="two-col">
                            <div class="field">
                                <label>From</label>
                                <input name="from" placeholder="Draft">
                            </div>
                            <div class="field">
                                <label>To</label>
                                <input name="to" placeholder="Submitted">
                            </div>
                        </div>
                        <div class="field">
                            <label>Roles Allowed</label>
                            <?php foreach ($roles as $role) { ?>
                                <label><input type="checkbox" name="roles[]" value="<?= sanitize($role['roleId']); ?>"> <?= sanitize($role['label']); ?></label><br>
                            <?php } ?>
                        </div>
                        <div class="field">
                            <label>Required Fields</label>
                            <?php foreach ($fields as $field) { ?>
                                <label><input type="checkbox" name="requiredFields[]" value="<?= sanitize($field['key']); ?>"> <?= sanitize($field['label']); ?></label><br>
                            <?php } ?>
                        </div>
                        <div class="field">
                            <label>Required Documents</label>
                            <?php foreach ($documents as $doc) { ?>
                                <label><input type="checkbox" name="requiredDocs[]" value="<?= sanitize($doc['docId']); ?>"> <?= sanitize($doc['label']); ?></label><br>
                            <?php } ?>
                        </div>
                        <div class="field">
                            <label>Approval Required</label>
                            <input type="checkbox" name="approval" value="1">
                        </div>
                        <button class="btn" type="submit">Add Transition</button>
                    </form>
                </div>
            </div>
        <?php } ?>

        <?php if ($tab === 'publish') { ?>
            <div class="card" style="padding:16px;">
                <h3>Publish Version</h3>
                <p class="muted">Publishing snapshots the current draft as the next immutable version.</p>
                <?php if (($actor['type'] ?? '') === 'superadmin') { ?>
                    <form method="post" action="/superadmin/schemes/publish.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                        <button class="btn" type="submit">Publish Draft</button>
                    </form>
                <?php } else { ?>
                    <p class="muted">Only superadmin can publish versions.</p>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if ($tab === 'advanced') { ?>
            <div class="card" style="padding:16px;">
                <h3>Draft JSON (read-only)</h3>
                <pre><?= sanitize(json_encode($draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                <form method="get" action="/superadmin/schemes/download_json.php">
                    <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                    <button class="btn secondary" type="submit">Download JSON</button>
                </form>
            </div>
        <?php } ?>
        <?php
    });
});
