<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('scheme_builder');
    $schemes = list_schemes();

    render_layout('Schemes', function () use ($schemes, $actor) {
        ?>
        <style>
            .page-header { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; }
            .table { width:100%; border-collapse: collapse; }
            .table th, .table td { text-align:left; padding:10px; border-bottom:1px solid var(--border); }
            .pill { padding:4px 10px; border-radius:999px; font-size:12px; border:1px solid var(--border); background:var(--surface-2); }
            .muted { color: var(--muted); }
            .card-grid { display:grid; gap:16px; }
        </style>
        <div class="page-header">
            <div>
                <h1>Scheme Builder</h1>
                <p class="muted">Create, version, and publish scheme configurations with JSON backed storage.</p>
            </div>
            <a class="btn" href="/superadmin/schemes/new.php">Create Scheme</a>
        </div>
        <div class="card" style="padding:16px; margin-top:16px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Scheme Code</th>
                        <th>Name</th>
                        <th>Case Label</th>
                        <th>Updated</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$schemes) { ?>
                    <tr>
                        <td colspan="6" class="muted">No schemes yet. Start with the setup wizard.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($schemes as $scheme) { ?>
                    <tr>
                        <td><strong><?= sanitize($scheme['schemeCode'] ?? ''); ?></strong></td>
                        <td><?= sanitize($scheme['name'] ?? ''); ?></td>
                        <td><?= sanitize($scheme['caseLabel'] ?? 'Beneficiary'); ?></td>
                        <td><?= sanitize($scheme['updatedAt'] ?? ''); ?></td>
                        <td><span class="pill">Draft</span></td>
                        <td>
                            <a class="btn secondary" href="/superadmin/schemes/edit.php?schemeCode=<?= urlencode($scheme['schemeCode'] ?? ''); ?>&version=draft">Open Builder</a>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
