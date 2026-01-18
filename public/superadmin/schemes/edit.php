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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $action = $_POST['action'] ?? '';

        if ($action === 'update_overview') {
            $draft['name'] = trim($_POST['name'] ?? $draft['name']);
            $draft['description'] = trim($_POST['description'] ?? $draft['description']);
            $draft['caseLabel'] = trim($_POST['caseLabel'] ?? $draft['caseLabel']);
            scheme_log_audit($schemeCode, 'update_overview', $actor['type'] ?? 'actor');
        }

        if ($action === 'add_role') {
            $roleId = trim($_POST['roleId'] ?? '');
            $label = trim($_POST['label'] ?? '');
            if ($roleId !== '') {
                $draft['roles'][] = ['roleId' => $roleId, 'label' => $label ?: $roleId];
                scheme_log_audit($schemeCode, 'add_role', $actor['type'] ?? 'actor', ['roleId' => $roleId]);
            }
        }

        if ($action === 'add_module') {
            $moduleId = trim($_POST['moduleId'] ?? '');
            $label = trim($_POST['label'] ?? '');
            if ($moduleId !== '') {
                $draft['modules'][] = ['moduleId' => $moduleId, 'label' => $label ?: $moduleId];
                scheme_log_audit($schemeCode, 'add_module', $actor['type'] ?? 'actor', ['moduleId' => $moduleId]);
            }
        }

        if ($action === 'add_field') {
            $label = trim($_POST['fieldLabel'] ?? '');
            if ($label !== '') {
                $draft = scheme_add_field($draft, [
                    'label' => $label,
                    'type' => $_POST['fieldType'] ?? 'text',
                    'required' => isset($_POST['required']),
                    'minLen' => (int)($_POST['minLen'] ?? 0) ?: null,
                    'maxLen' => (int)($_POST['maxLen'] ?? 0) ?: null,
                    'pattern' => trim($_POST['pattern'] ?? '') ?: null,
                    'moduleId' => $_POST['moduleId'] ?? '',
                    'viewRoles' => $_POST['viewRoles'] ?? [],
                    'editRoles' => $_POST['editRoles'] ?? [],
                ]);
                scheme_log_audit($schemeCode, 'add_field', $actor['type'] ?? 'actor', ['label' => $label]);
            }
        }

        if ($action === 'add_pack') {
            $packId = trim($_POST['packId'] ?? '');
            if ($packId !== '') {
                $draft = scheme_add_pack($draft, [
                    'packId' => $packId,
                    'label' => trim($_POST['packLabel'] ?? $packId),
                    'moduleId' => $_POST['packModuleId'] ?? '',
                    'requiredFieldKeys' => $_POST['requiredFieldKeys'] ?? [],
                ]);
                scheme_log_audit($schemeCode, 'add_pack', $actor['type'] ?? 'actor', ['packId' => $packId]);
            }
        }

        if ($action === 'add_document') {
            $packId = trim($_POST['docPackId'] ?? '');
            $docId = trim($_POST['docId'] ?? '');
            if ($packId !== '' && $docId !== '') {
                $draft = scheme_add_document($draft, $packId, [
                    'docId' => $docId,
                    'label' => trim($_POST['docLabel'] ?? $docId),
                    'templateBody' => $_POST['templateBody'] ?? '',
                ]);
                scheme_log_audit($schemeCode, 'add_document', $actor['type'] ?? 'actor', ['packId' => $packId, 'docId' => $docId]);
            }
        }

        if ($action === 'update_workflow') {
            $packId = trim($_POST['workflowPackId'] ?? '');
            if ($packId !== '') {
                $states = array_filter(array_map('trim', explode(',', $_POST['workflowStates'] ?? '')));
                $workflow = [
                    'enabled' => isset($_POST['workflowEnabled']),
                    'states' => $states ?: ['Draft', 'Submitted', 'Approved', 'Completed'],
                    'transitions' => [],
                ];
                foreach ($draft['packs'] ?? [] as $pack) {
                    if (($pack['packId'] ?? '') === $packId && !empty($pack['workflow']['transitions'])) {
                        $workflow['transitions'] = $pack['workflow']['transitions'];
                    }
                }
                $draft = scheme_update_pack_workflow($draft, $packId, $workflow);
                scheme_log_audit($schemeCode, 'update_workflow', $actor['type'] ?? 'actor', ['packId' => $packId]);
            }
        }

        if ($action === 'add_transition') {
            $packId = trim($_POST['transitionPackId'] ?? '');
            if ($packId !== '') {
                $transition = [
                    'from' => trim($_POST['transitionFrom'] ?? ''),
                    'to' => trim($_POST['transitionTo'] ?? ''),
                    'roles' => $_POST['transitionRoles'] ?? [],
                    'requiredFields' => $_POST['transitionFields'] ?? [],
                    'requiredDocs' => $_POST['transitionDocs'] ?? [],
                    'approval' => null,
                ];
                $draft = scheme_add_workflow_transition($draft, $packId, $transition);
                scheme_log_audit($schemeCode, 'add_transition', $actor['type'] ?? 'actor', ['packId' => $packId]);
            }
        }

        save_scheme_draft($schemeCode, $draft);
        redirect('/superadmin/schemes/edit.php?schemeCode=' . urlencode($schemeCode) . '&version=draft&tab=' . urlencode($_GET['tab'] ?? 'overview'));
    }

    $tab = $_GET['tab'] ?? 'overview';
    $roles = $draft['roles'] ?? [];
    $modules = $draft['modules'] ?? [];
    $fields = $draft['fieldDictionary'] ?? [];
    $packs = $draft['packs'] ?? [];

    render_layout('Scheme Builder', function () use ($schemeCode, $draft, $tab, $roles, $modules, $fields, $packs, $actor) {
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
        </style>
        <h1>Scheme Builder · <?= sanitize($schemeCode); ?></h1>
        <div class="tabs">
            <?php
            $tabs = ['overview' => 'Overview', 'dictionary' => 'Data Dictionary', 'packs' => 'Packs', 'workflows' => 'Workflows', 'modules' => 'Modules', 'publish' => 'Publish', 'advanced' => 'Advanced'];
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
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="action" value="update_overview">
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
                    <h3>Roles</h3>
                    <ul class="list">
                        <?php foreach ($roles as $role) { ?>
                            <li><strong><?= sanitize($role['roleId'] ?? ''); ?></strong> — <?= sanitize($role['label'] ?? ''); ?></li>
                        <?php } ?>
                    </ul>
                    <form method="post" style="margin-top:12px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
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
                    <form method="post" style="margin-top:12px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
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
                    <p class="muted">Click-based field creation automatically generates canonical keys.</p>
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid var(--border);">Key</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid var(--border);">Label</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid var(--border);">Type</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid var(--border);">Required</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$fields) { ?>
                            <tr><td colspan="4" class="muted" style="padding:8px;">No fields added yet.</td></tr>
                        <?php } ?>
                        <?php foreach ($fields as $field) { ?>
                            <tr>
                                <td style="padding:8px;"><code><?= sanitize($field['key'] ?? ''); ?></code></td>
                                <td style="padding:8px;"><?= sanitize($field['label'] ?? ''); ?></td>
                                <td style="padding:8px;"><?= sanitize($field['type'] ?? ''); ?></td>
                                <td style="padding:8px;"><?= !empty($field['required']) ? 'Yes' : 'No'; ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="card" style="padding:16px;">
                    <h3>Add Field</h3>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="action" value="add_field">
                        <div class="field">
                            <label>Field Label</label>
                            <input name="fieldLabel" placeholder="Customer Name" required>
                        </div>
                        <div class="field">
                            <label>Type</label>
                            <select name="fieldType">
                                <option value="text">Text</option>
                                <option value="number">Number</option>
                                <option value="date">Date</option>
                                <option value="textarea">Textarea</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Module</label>
                            <select name="moduleId">
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
                            <p class="muted">Documents: <?= sanitize(count($pack['documents'] ?? [])); ?></p>
                        </div>
                    <?php } ?>
                </div>
                <div class="card" style="padding:16px;">
                    <h3>Add Pack</h3>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="action" value="add_pack">
                        <div class="field">
                            <label>Pack ID</label>
                            <input name="packId" placeholder="application_pack" required>
                        </div>
                        <div class="field">
                            <label>Label</label>
                            <input name="packLabel" placeholder="Application Pack">
                        </div>
                        <div class="field">
                            <label>Module</label>
                            <select name="packModuleId">
                                <?php foreach ($modules as $module) { ?>
                                    <option value="<?= sanitize($module['moduleId']); ?>"><?= sanitize($module['label']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Required Field Keys</label>
                            <?php foreach ($fields as $field) { ?>
                                <label><input type="checkbox" name="requiredFieldKeys[]" value="<?= sanitize($field['key']); ?>"> <?= sanitize($field['label']); ?></label><br>
                            <?php } ?>
                        </div>
                        <button class="btn" type="submit">Add Pack</button>
                    </form>
                </div>
                <div class="card" style="padding:16px;">
                    <h3>Add Document</h3>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="action" value="add_document">
                        <div class="field">
                            <label>Pack</label>
                            <select name="docPackId">
                                <?php foreach ($packs as $pack) { ?>
                                    <option value="<?= sanitize($pack['packId']); ?>"><?= sanitize($pack['label']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Doc ID</label>
                            <input name="docId" placeholder="application_form" required>
                        </div>
                        <div class="field">
                            <label>Label</label>
                            <input name="docLabel" placeholder="Application Form">
                        </div>
                        <div class="two-col">
                            <div>
                                <div class="field">
                                    <label>Template</label>
                                    <textarea name="templateBody" id="template-body" placeholder="Dear {{field:case.customer_name}}, ..."></textarea>
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
                        <button class="btn" type="submit" style="margin-top:12px;">Add Document</button>
                    </form>
                </div>
            </div>
            <script>
                const textarea = document.getElementById('template-body');
                document.querySelectorAll('.placeholder-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        if (!textarea) return;
                        const key = btn.dataset.key || '';
                        const placeholder = `{{field:${key}}}`;
                        const start = textarea.selectionStart || 0;
                        const end = textarea.selectionEnd || 0;
                        const text = textarea.value;
                        textarea.value = text.slice(0, start) + placeholder + text.slice(end);
                        textarea.focus();
                        const pos = start + placeholder.length;
                        textarea.setSelectionRange(pos, pos);
                    });
                });
            </script>
        <?php } ?>

        <?php if ($tab === 'workflows') { ?>
            <div class="grid">
                <div class="card" style="padding:16px;">
                    <h3>Pack Workflows</h3>
                    <?php foreach ($packs as $pack) { ?>
                        <div class="card" style="padding:12px; margin-bottom:12px;">
                            <strong><?= sanitize($pack['label'] ?? ''); ?></strong>
                            <p class="muted">States: <?= sanitize(implode(', ', $pack['workflow']['states'] ?? [])); ?></p>
                            <p class="muted">Transitions: <?= sanitize(count($pack['workflow']['transitions'] ?? [])); ?></p>
                        </div>
                    <?php } ?>
                </div>
                <div class="card" style="padding:16px;">
                    <h3>Update Workflow</h3>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="action" value="update_workflow">
                        <div class="field">
                            <label>Pack</label>
                            <select name="workflowPackId">
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
                        <button class="btn" type="submit">Save Workflow</button>
                    </form>
                </div>
                <div class="card" style="padding:16px;">
                    <h3>Add Transition</h3>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="action" value="add_transition">
                        <div class="field">
                            <label>Pack</label>
                            <select name="transitionPackId">
                                <?php foreach ($packs as $pack) { ?>
                                    <option value="<?= sanitize($pack['packId']); ?>"><?= sanitize($pack['label']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="two-col">
                            <div class="field">
                                <label>From</label>
                                <input name="transitionFrom" placeholder="Draft">
                            </div>
                            <div class="field">
                                <label>To</label>
                                <input name="transitionTo" placeholder="Submitted">
                            </div>
                        </div>
                        <div class="field">
                            <label>Roles</label>
                            <?php foreach ($roles as $role) { ?>
                                <label><input type="checkbox" name="transitionRoles[]" value="<?= sanitize($role['roleId']); ?>"> <?= sanitize($role['label']); ?></label><br>
                            <?php } ?>
                        </div>
                        <div class="field">
                            <label>Required Fields</label>
                            <?php foreach ($fields as $field) { ?>
                                <label><input type="checkbox" name="transitionFields[]" value="<?= sanitize($field['key']); ?>"> <?= sanitize($field['label']); ?></label><br>
                            <?php } ?>
                        </div>
                        <div class="field">
                            <label>Required Docs</label>
                            <?php foreach ($packs as $pack) { ?>
                                <?php foreach ($pack['documents'] ?? [] as $doc) { ?>
                                    <label><input type="checkbox" name="transitionDocs[]" value="<?= sanitize($doc['docId']); ?>"> <?= sanitize($doc['label']); ?></label><br>
                                <?php } ?>
                            <?php } ?>
                        </div>
                        <button class="btn" type="submit">Add Transition</button>
                    </form>
                </div>
            </div>
        <?php } ?>

        <?php if ($tab === 'modules') { ?>
            <div class="card" style="padding:16px;">
                <h3>Modules</h3>
                <ul class="list">
                    <?php foreach ($modules as $module) { ?>
                        <li><strong><?= sanitize($module['moduleId'] ?? ''); ?></strong> — <?= sanitize($module['label'] ?? ''); ?></li>
                    <?php } ?>
                </ul>
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
            </div>
        <?php } ?>
        <?php
    });
});
