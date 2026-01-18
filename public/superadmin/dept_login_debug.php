<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = require_role('superadmin');
    if (!empty($actor['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $input = '';
    $report = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $input = trim((string)($_POST['login_id'] ?? ''));
        $report = build_login_debug_report($input);
        log_login_debug($report, $actor);
    }

    $title = get_app_config()['appName'] . ' | Login Debugger';
    render_layout($title, function () use ($input, $report) {
        ?>
        <div class="hero">
            <div class="card" style="display:flex;flex-direction:column;gap:12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                    <div>
                        <div class="pill"><?= sanitize('Superadmin Utility'); ?></div>
                        <h2 style="margin:6px 0 4px;"><?= sanitize('Department Login Debugger'); ?></h2>
                        <p class="muted" style="margin:0;"><?= sanitize('Paste a full user id to see where authentication fails.'); ?></p>
                    </div>
                    <div class="pill" style="background:var(--surface-2);color:var(--text);border:1px solid var(--border);"><?= sanitize('Timezone: Asia/Kolkata'); ?></div>
                </div>
                <form method="post" style="display:grid;gap:12px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <div class="field">
                        <label for="login_id"><?= sanitize('Full User ID'); ?></label>
                        <input id="login_id" name="login_id" type="text" required placeholder="shortid.role.dept"
                               value="<?= sanitize($input); ?>" autocomplete="off">
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <button class="btn" type="submit"><?= sanitize('Run Debug'); ?></button>
                        <span class="muted" style="font-size:13px;"><?= sanitize('No passwords collected. File-system only.'); ?></span>
                    </div>
                </form>
            </div>
            <?php if ($report !== null): ?>
                <div class="card" style="display:grid;gap:12px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <h3 style="margin:0;"><?= sanitize('Results'); ?></h3>
                        <?php if (!empty($report['expectedPath'])): ?>
                            <button type="button" class="btn secondary" data-copy="<?= sanitize($report['expectedPath']); ?>" onclick="copyPath(this)">
                                <?= sanitize('Copy expected path'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($report['userMessage'])): ?>
                        <div class="flash error" style="margin:0;"><?= sanitize($report['userMessage']); ?></div>
                    <?php endif; ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                        <div class="pill"><?= sanitize('Normalized: ' . ($report['normalized'] ?? '')); ?></div>
                        <div class="pill"><?= sanitize('Expected path: ' . ($report['expectedPath'] ?? 'n/a')); ?></div>
                        <div class="pill" style="<?= ($report['pathExists'] ?? false) ? 'border-color:var(--success);color:#8ce99a;' : 'border-color:var(--danger);color:#f77676;'; ?>">
                            <?= sanitize(($report['pathExists'] ?? false) ? 'User JSON located' : 'User JSON missing'); ?>
                        </div>
                        <div class="pill" style="<?= ($report['rolePresent'] ?? false) ? 'border-color:var(--success);color:#8ce99a;' : 'border-color:var(--danger);color:#f77676;'; ?>">
                            <?= sanitize($report['roleMessage'] ?? ''); ?>
                        </div>
                        <div class="pill">
                            <?= sanitize('Status: ' . ($report['userStatus'] ?? 'unknown')); ?>
                        </div>
                    </div>
                    <?php if (!empty($report['parsed'])): ?>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
                            <div class="card" style="padding:12px;">
                                <div class="muted" style="font-size:12px;"><?= sanitize('userShortId'); ?></div>
                                <div style="font-weight:700;"><?= sanitize($report['parsed']['userShortId']); ?></div>
                            </div>
                            <div class="card" style="padding:12px;">
                                <div class="muted" style="font-size:12px;"><?= sanitize('roleId'); ?></div>
                                <div style="font-weight:700;"><?= sanitize($report['parsed']['roleId']); ?></div>
                            </div>
                            <div class="card" style="padding:12px;">
                                <div class="muted" style="font-size:12px;"><?= sanitize('deptId'); ?></div>
                                <div style="font-weight:700;"><?= sanitize($report['parsed']['deptId']); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <script>
            function copyPath(btn) {
                const value = btn.getAttribute('data-copy') || '';
                if (!navigator.clipboard) {
                    return;
                }
                navigator.clipboard.writeText(value).then(() => {
                    btn.innerText = 'Copied!';
                    setTimeout(() => btn.innerText = 'Copy expected path', 1200);
                });
            }
        </script>
        <?php
    });
});
