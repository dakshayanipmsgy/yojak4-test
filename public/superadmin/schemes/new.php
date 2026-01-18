<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    require_superadmin_or_permission('scheme_builder');

    render_layout('New Scheme', function () {
        ?>
        <style>
            .wizard { display:grid; gap:16px; }
            .wizard section { padding:16px; border:1px solid var(--border); border-radius:12px; background:var(--surface); }
            .field { display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
            input, textarea { padding:10px; border-radius:8px; border:1px solid var(--border); }
            textarea { min-height:80px; }
            .muted { color: var(--muted); }
        </style>
        <h1>Scheme Setup Wizard</h1>
        <p class="muted">Start a new scheme draft. Define the case terminology, roles, and modules.</p>
        <form method="post" action="/superadmin/schemes/create.php" class="wizard">
            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
            <section>
                <h3>Step 1 · Basic Info</h3>
                <div class="field">
                    <label>Scheme Code</label>
                    <input name="schemeCode" placeholder="JH-XYZ" required>
                </div>
                <div class="field">
                    <label>Scheme Name</label>
                    <input name="name" placeholder="Scheme Name" required>
                </div>
                <div class="field">
                    <label>Description</label>
                    <textarea name="description" placeholder="Plain description"></textarea>
                </div>
                <div class="field">
                    <label>Case Label</label>
                    <input name="caseLabel" placeholder="Beneficiary" value="Beneficiary">
                </div>
            </section>
            <section>
                <h3>Step 2 · Roles</h3>
                <p class="muted">Enter roles, one per line, in the format <strong>role_id: Label</strong>.</p>
                <textarea name="roles">vendor_admin: Vendor Admin
vendor_staff: Vendor Staff
customer: Customer
authority: Authority</textarea>
            </section>
            <section>
                <h3>Step 3 · Modules</h3>
                <p class="muted">Enter modules, one per line, in the format <strong>module_id: Label</strong>.</p>
                <textarea name="modules">application: Application
compliance: Compliance</textarea>
            </section>
            <section style="display:flex; justify-content:flex-end;">
                <button class="btn" type="submit">Create Draft</button>
            </section>
        </form>
        <?php
    });
});
