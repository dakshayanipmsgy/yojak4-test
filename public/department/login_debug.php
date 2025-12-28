<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    if (($user['roleId'] ?? '') !== 'admin') {
        render_error_page('Only Department Admins can access this tool.');
        exit;
    }

    $input = '';
    $report = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $input = trim((string)($_POST['login_id'] ?? ''));
        $report = build_login_debug_report($input, $user['deptId'] ?? null);
        log_login_debug($report, $user);
    }

    $title = get_app_config()['appName'] . ' | Login Debugger';
    render_layout($title, function () use ($user, $input, $report) {
        ?>
        <div class="hero">
            <div class="card" style="display:flex;flex-direction:column;gap:12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                    <div>
                        <div class="pill"><?= sanitize('Department Admin Utility'); ?></div>
                        <h2 style="margin:6px 0 4px;"><?= sanitize('Login Debugger'); ?></h2>
                        <p class="muted" style="margin:0;"><?= sanitize('Check user id formatting and storage within your department.'); ?></p>
                    </div>
                    <div class="pill" style="background:#13233a;color:#9cc4ff;"><?= sanitize('Department: ' . ($user['deptId'] ?? '')); ?></div>
                </div>
                <form method="post" style="display:grid;gap:12px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <div class="field">
                        <label for="login_id"><?= sanitize('Full User ID (your department only)'); ?></label>
                        <input id="login_id" name="login_id" type="text" required placeholder="shortid.role.<?= sanitize($user['deptId'] ?? 'dept'); ?>"
                               value="<?= sanitize($input); ?>" autocomplete="off">
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <button class="btn" type="submit"><?= sanitize('Run Debug'); ?></button>
                        <span class="muted" style="font-size:13px;"><?= sanitize('No passwords displayed; file-path insights only.'); ?></span>
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
