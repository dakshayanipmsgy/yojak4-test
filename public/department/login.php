<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $errors = [];
    $forgotErrors = [];
    $forgotSuccess = null;
    $loginId = '';
    $deptResetForm = [
        'deptId' => '',
        'contact' => '',
    ];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $uaHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $action = $_POST['action'] ?? 'login';
        if ($action === 'forgot_admin') {
            $deptResetForm['deptId'] = strtolower(trim((string)($_POST['deptId'] ?? '')));
            $deptResetForm['contact'] = trim((string)($_POST['contact'] ?? ''));
            if (!is_valid_dept_id($deptResetForm['deptId'])) {
                $forgotErrors[] = 'Enter a valid department ID (3-10 lowercase letters/numbers).';
            }
            $deviceKey = 'dept_reset_' . hash('sha256', ($ip ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . $deptResetForm['deptId']);
            $allowed = password_reset_rate_limit_allowed($deviceKey, 86400, 3);
            if (!$allowed) {
                $forgotErrors[] = 'Too many reset requests from this device. Try again in 24 hours.';
            }

            if (!$forgotErrors) {
                $dept = load_department($deptResetForm['deptId']);
                $resolved = $dept ? resolve_department_admin_account($deptResetForm['deptId']) : ['ok' => false, 'reason' => 'department_missing'];
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $logContext = [
                    'at' => now_kolkata()->format(DateTime::ATOM),
                    'event' => 'DEPT_ADMIN_RESET_REQUEST',
                    'deptId' => $deptResetForm['deptId'],
                    'requesterIp' => $ip,
                    'requesterUaHash' => hash('sha256', $ua ?: 'unknown'),
                    'result' => $resolved['ok'] ? 'queued' : 'skipped',
                    'reasonCode' => $resolved['ok'] ? 'ok' : ($resolved['reason'] ?? 'unknown'),
                ];

                if ($resolved['ok']) {
                    $req = add_password_reset_request(
                        $deptResetForm['deptId'],
                        $resolved['activeAdminUserId'],
                        'department_login_forgot',
                        $deptResetForm['contact'] !== '' ? $deptResetForm['contact'] : null,
                        null,
                        $ip,
                        $ua,
                        $resolved['activeAdminUserId']
                    );
                    $logContext['requestId'] = $req['requestId'] ?? null;
                    $logContext['adminUserId'] = $resolved['activeAdminUserId'];
                }

                logEvent(DATA_PATH . '/logs/auth.log', $logContext);
                password_reset_rate_limit_record($deviceKey, 86400, 3);
                $forgotSuccess = 'If the department exists, your reset request has been sent to superadmin.';
            }
        } else {
            $loginId = normalize_login_identifier((string)($_POST['loginId'] ?? ''));
            $password = $_POST['password'] ?? '';

            if ($loginId === '' || $password === '') {
                $errors[] = t('login_invalid');
            } else {
                $rateKey = rate_limit_key($loginId);
                if (!check_rate_limit($rateKey)) {
                    $errors[] = t('device_locked');
                    $parsed = parse_department_login_identifier($loginId);
                    log_auth_attempt([
                        'type' => 'dept_login',
                        'loginId' => $loginId,
                        'deptId' => $parsed['deptId'] ?? null,
                        'ip' => $ip,
                        'uaHash' => $uaHash,
                        'result' => 'blocked',
                        'reasonCode' => 'rate_limited',
                    ]);
                } else {
                    $authResult = authenticate_department_user($loginId, $password);
                    $record = $authResult['record'] ?? null;
                    $reason = $authResult['error'] ?? null;
                    $parsed = $authResult['parsed'] ?? parse_department_login_identifier($loginId);
                    if ($record) {
                        record_rate_limit_attempt($rateKey, true);
                        login_user($record);
                        update_department_last_login($record['fullUserId']);
                        log_auth_attempt([
                            'type' => 'dept_login',
                            'loginId' => $loginId,
                            'deptId' => $record['deptId'] ?? ($parsed['deptId'] ?? null),
                            'ip' => $ip,
                            'uaHash' => $uaHash,
                            'result' => 'success',
                            'reasonCode' => 'ok',
                        ]);
                        if (!empty($record['mustResetPassword'])) {
                            redirect('/auth/force_reset.php');
                        }
                        set_flash('success', t('login_success'));
                        redirect('/home.php');
                    } else {
                        record_rate_limit_attempt($rateKey, false);
                        $result = 'fail';
                        $message = t('login_invalid');
                        if ($reason === 'suspended') {
                            $message = 'Account suspended.';
                            $result = 'suspended';
                        } elseif ($reason === 'role_missing') {
                            $message = 'Role misconfigured; contact admin.';
                            $result = 'role_missing';
                        }
                        log_auth_attempt([
                            'type' => 'dept_login',
                            'loginId' => $loginId,
                            'deptId' => $parsed['deptId'] ?? null,
                            'ip' => $ip,
                            'uaHash' => $uaHash,
                            'result' => $result,
                            'reasonCode' => $reason ?? 'invalid_credentials',
                        ]);
                        $errors[] = $message;
                    }
                }
            }
        }
    } else {
        $user = current_user();
        if ($user && ($user['type'] ?? '') === 'department') {
            if (!empty($user['mustResetPassword'])) {
                redirect('/auth/force_reset.php');
            }
            redirect('/home.php');
        }
    }

    $title = get_app_config()['appName'] . ' | Department Login';
    render_layout($title, function () use ($errors, $loginId, $forgotErrors, $forgotSuccess, $deptResetForm) {
        ?>
        <div class="card">
            <h2><?= sanitize('Department Login'); ?></h2>
            <p class="muted"><?= sanitize('Sign in using your department user ID and password.'); ?></p>
            <?php if ($errors): ?>
                <div class="flashes">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/department/login.php">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="action" value="login">
                <div class="field">
                    <label for="loginId"><?= sanitize('Login ID'); ?></label>
                    <input id="loginId" name="loginId" value="<?= sanitize($loginId); ?>" required placeholder="usershort.role.dept">
                    <p class="muted" style="margin:6px 0 0;font-size:0.9rem;"><?= sanitize('Example: ramesh.admin.jhdpw or sita.engineer.jhdpw'); ?></p>
                </div>
                <div class="field">
                    <label for="password"><?= sanitize(t('password')); ?></label>
                    <input id="password" name="password" type="password" required>
                </div>
                <button class="btn" type="submit"><?= sanitize(t('submit')); ?></button>
            </form>
            <div style="margin-top:14px; border-top:1px solid var(--border); padding-top:12px;">
                <button class="link-btn" type="button" onclick="document.getElementById('forgotPanel').classList.toggle('open');" style="display:flex;align-items:center;gap:6px;color:var(--primary);background:none;border:none;padding:0;cursor:pointer;">
                    <span style="font-weight:700;">Forgot password? (Admin)</span>
                    <span class="muted" style="font-size:0.9rem;">Secure reset via superadmin</span>
                </button>
                <div id="forgotPanel" class="slide-panel <?= $forgotErrors || $forgotSuccess ? 'open' : ''; ?>" style="margin-top:10px; border:1px solid var(--border); border-radius:10px; padding:12px; background:var(--surface-2);">
                    <?php if ($forgotErrors): ?>
                        <div class="flashes">
                            <?php foreach ($forgotErrors as $error): ?>
                                <div class="flash error"><?= sanitize($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($forgotSuccess): ?>
                        <div class="flash success"><?= sanitize($forgotSuccess); ?></div>
                    <?php else: ?>
                        <p class="muted" style="margin:0 0 8px;">Forgot admin password? Request superadmin approval securely.</p>
                    <?php endif; ?>
                    <form method="post" action="/department/login.php" style="display:grid;gap:10px;margin-top:8px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="action" value="forgot_admin">
                        <div class="field" style="margin-bottom:4px;">
                            <label for="deptId"><?= sanitize('Department ID'); ?></label>
                            <input id="deptId" name="deptId" value="<?= sanitize($deptResetForm['deptId']); ?>" required minlength="3" maxlength="10" pattern="[a-z0-9]{3,10}" placeholder="e.g. jhdpw">
                        </div>
                        <div class="field" style="margin-bottom:4px;">
                            <label for="contact"><?= sanitize('Contact (optional)'); ?></label>
                            <input id="contact" name="contact" value="<?= sanitize($deptResetForm['contact']); ?>" placeholder="Phone or email for follow-up">
                        </div>
                        <button class="btn secondary" type="submit"><?= sanitize('Send reset request'); ?></button>
                        <p class="muted" style="margin:0;font-size:0.9rem;">If the department exists, your reset request will be sent to superadmin. Limit: 3 per device/day.</p>
                    </form>
                </div>
            </div>
        </div>
        <style>
            .slide-panel { display:none; }
            .slide-panel.open { display:block; }
            .link-btn:hover { text-decoration: underline; }
            @media (max-width: 640px) {
                .slide-panel { padding:10px; }
            }
        </style>
        <?php
    });
});
