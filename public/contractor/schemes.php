<?php
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'] ?? '';
    $schemes = list_schemes();
    $enabled = contractor_enabled_schemes($yojId);

    $pending = [];
    $requestsDir = contractor_scheme_requests_dir($yojId);
    if (is_dir($requestsDir)) {
        foreach (glob($requestsDir . '/REQ-*.json') ?: [] as $file) {
            $record = readJson($file);
            if (($record['status'] ?? '') === 'pending') {
                $pending[strtoupper($record['schemeCode'] ?? '')] = $record;
            }
        }
    }

    render_layout('Schemes', function () use ($schemes, $enabled, $pending) {
        ?>
        <style>
            .grid { display:grid; gap:16px; }
            .scheme-card { padding:16px; border-radius:12px; border:1px solid var(--border); background:var(--surface); }
            .muted { color: var(--muted); }
        </style>
        <h1>Available Schemes</h1>
        <p class="muted">Schemes are off by default. Request activation to start using a scheme.</p>
        <div class="grid">
            <?php foreach ($schemes as $scheme) {
                $schemeCode = strtoupper($scheme['schemeCode'] ?? '');
                $versions = list_scheme_versions($schemeCode);
                $enabledVersion = $enabled[$schemeCode] ?? null;
                $pendingRequest = $pending[$schemeCode] ?? null;
            ?>
                <div class="scheme-card">
                    <h3><?= sanitize($scheme['name'] ?? ''); ?> <span class="muted">(<?= sanitize($schemeCode); ?>)</span></h3>
                    <p class="muted"><?= sanitize($scheme['description'] ?? ''); ?></p>
                    <p class="muted">Case Label: <?= sanitize($scheme['caseLabel'] ?? 'Beneficiary'); ?></p>
                    <?php if ($enabledVersion) { ?>
                        <p><strong>Enabled:</strong> <?= sanitize($enabledVersion); ?></p>
                        <a class="btn" href="/contractor/scheme_case_list.php?schemeCode=<?= urlencode($schemeCode); ?>">Open Cases</a>
                    <?php } elseif ($pendingRequest) { ?>
                        <p class="muted">Activation request pending for <?= sanitize($pendingRequest['requestedVersion'] ?? ''); ?>.</p>
                    <?php } else { ?>
                        <form method="post" action="/contractor/schemes/request_activation.php">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
                            <label>Version</label>
                            <select name="version">
                                <?php foreach ($versions as $version) { ?>
                                    <option value="<?= sanitize($version); ?>"><?= sanitize($version); ?></option>
                                <?php } ?>
                            </select>
                            <button class="btn" type="submit" style="margin-top:8px;">Request Activation</button>
                        </form>
                    <?php } ?>
                </div>
            <?php } ?>
            <?php if (!$schemes) { ?>
                <div class="scheme-card">
                    <p class="muted">No schemes available yet.</p>
                </div>
            <?php } ?>
        </div>
        <?php
    });
});
