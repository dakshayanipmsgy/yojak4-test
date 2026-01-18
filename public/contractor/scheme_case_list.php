<?php
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $schemeCode = strtoupper(trim($_GET['schemeCode'] ?? ''));
    if ($schemeCode === '') {
        redirect('/contractor/schemes.php');
    }
    $enabled = contractor_enabled_schemes($user['yojId'] ?? '');
    $version = $enabled[$schemeCode] ?? '';
    if (!$version) {
        set_flash('error', 'Scheme not enabled yet.');
        redirect('/contractor/schemes.php');
    }

    $cases = list_scheme_cases($schemeCode, $user['yojId'] ?? '');

    render_layout('Scheme Cases', function () use ($schemeCode, $cases) {
        ?>
        <style>
            .card { padding:16px; margin-bottom:12px; }
            .muted { color: var(--muted); }
        </style>
        <h1><?= sanitize($schemeCode); ?> Cases</h1>
        <a class="btn" href="/contractor/scheme_case_new.php?schemeCode=<?= urlencode($schemeCode); ?>">Create Case</a>
        <div style="margin-top:16px;">
            <?php if (!$cases) { ?>
                <div class="card">No cases yet.</div>
            <?php } ?>
            <?php foreach ($cases as $case) { ?>
                <div class="card">
                    <h3><?= sanitize($case['title'] ?? ''); ?></h3>
                    <p class="muted">Case ID: <?= sanitize($case['caseId'] ?? ''); ?> · Status: <?= sanitize($case['status'] ?? ''); ?></p>
                    <a class="btn secondary" href="/contractor/scheme_case.php?schemeCode=<?= urlencode($schemeCode); ?>&caseId=<?= urlencode($case['caseId'] ?? ''); ?>">Open Case</a>
                </div>
            <?php } ?>
        </div>
        <?php
    });
});
