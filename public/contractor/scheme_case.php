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

    $packs = [];
    foreach ($scheme['packs'] ?? [] as $pack) {
        $runtime = scheme_update_pack_runtime($schemeCode, $user['yojId'] ?? '', $caseId, $pack, $values);
        $packs[] = ['pack' => $pack, 'runtime' => $runtime];
    }

    render_layout('Case Overview', function () use ($case, $packs, $schemeCode) {
        ?>
        <style>
            .grid { display:grid; gap:16px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
            .card { padding:16px; }
            .pill { padding:4px 10px; border-radius:999px; font-size:12px; border:1px solid var(--border); background:var(--surface-2); }
        </style>
        <h1><?= sanitize($case['caseLabel'] ?? 'Case'); ?> Overview</h1>
        <p>Case ID: <strong><?= sanitize($case['caseId'] ?? ''); ?></strong> · Status: <?= sanitize($case['status'] ?? ''); ?></p>
        <div class="grid">
            <?php foreach ($packs as $item) {
                $pack = $item['pack'];
                $runtime = $item['runtime'];
            ?>
                <div class="card">
                    <h3><?= sanitize($pack['label'] ?? ''); ?></h3>
                    <p><span class="pill"><?= sanitize($runtime['status'] ?? ''); ?></span></p>
                    <p class="text-muted">Missing fields: <?= sanitize(implode(', ', $runtime['missingFields'] ?? [])); ?></p>
                    <a class="btn secondary" href="/contractor/scheme_pack.php?schemeCode=<?= urlencode($schemeCode); ?>&caseId=<?= urlencode($case['caseId'] ?? ''); ?>&packId=<?= urlencode($pack['packId'] ?? ''); ?>">Open Pack</a>
                </div>
            <?php } ?>
        </div>
        <?php
    });
});
