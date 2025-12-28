<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $title = get_app_config()['appName'] . ' | Department Forgot Password';
    $errors = [];
    $deptId = '';
    $contact = '';
    $successMessage = 'If the department exists, a reset request has been sent for approval.';
    $showInfo = true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $deptId = normalize_dept_id((string)($_POST['deptId'] ?? ''));
        $contact = trim((string)($_POST['contact'] ?? ''));
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $uaHash = hash('sha256', $ua);

        if (!is_valid_dept_id($deptId)) {
            $errors[] = 'Enter a valid department ID.';
        }

        $deviceKey = 'dept_admin_reset_device_' . hash('sha256', ($ip ?? '') . '|' . $ua);
        $deptKey = 'dept_admin_reset_' . $deptId . '_' . hash('sha256', ($ip ?? '') . '|' . $ua);
        $deviceAllowed = password_reset_rate_limit_allowed($deviceKey, 86400, 3);
        $deptAllowed = password_reset_rate_limit_allowed($deptKey, 86400, 3);
        if (!$deviceAllowed || !$deptAllowed) {
            $errors[] = 'Too many reset requests. Please try again tomorrow.';
        }

        $requestId = null;
        $resolvedAdminUserId = null;
        $result = 'validation_failed';

        if (!$errors) {
            $department = load_department($deptId);
            if (!$department) {
                $result = 'department_missing';
            } else {
                $resolvedAdminUserId = $department['activeAdminUserId'] ?? null;
                if (!$resolvedAdminUserId) {
                    $result = 'no_active_admin';
                } else {
                    $parsedAdmin = parse_department_login_identifier($resolvedAdminUserId);
                    if (!$parsedAdmin || ($parsedAdmin['deptId'] ?? '') !== $deptId || ($parsedAdmin['roleId'] ?? '') !== 'admin') {
                        $result = 'invalid_admin_pointer';
                    } else {
                        $adminRecord = load_active_department_user($resolvedAdminUserId);
                        if (!$adminRecord || ($adminRecord['type'] ?? '') !== 'department' || ($adminRecord['roleId'] ?? '') !== 'admin') {
                            $result = 'admin_record_missing';
                        } else {
                            $req = add_password_reset_request(
                                $deptId,
                                $resolvedAdminUserId,
                                'dept_admin_portal',
                                $contact !== '' ? $contact : null,
                                null,
                                $ip,
                                $ua
                            );
                            $requestId = $req['requestId'] ?? null;
                            $result = 'created';
                        }
                    }
                }
            }

            password_reset_rate_limit_record($deviceKey, 86400, 3);
            password_reset_rate_limit_record($deptKey, 86400, 3);

            logEvent(DATA_PATH . '/logs/auth.log', [
                'at' => now_kolkata()->format(DateTime::ATOM),
                'event' => 'DEPT_ADMIN_RESET_REQUEST',
                'deptId' => $deptId,
                'resolvedAdminUserId' => $resolvedAdminUserId,
                'requestId' => $requestId,
                'result' => $result,
                'requesterIp' => $ip,
                'requesterUaHash' => $uaHash,
            ]);

            set_flash('success', $successMessage);
            redirect('/department/reset_requested.php');
        } else {
            logEvent(DATA_PATH . '/logs/auth.log', [
                'at' => now_kolkata()->format(DateTime::ATOM),
                'event' => 'DEPT_ADMIN_RESET_REQUEST',
                'deptId' => $deptId,
                'resolvedAdminUserId' => null,
                'requestId' => null,
                'result' => 'rate_limited_or_invalid',
                'requesterIp' => $ip,
                'requesterUaHash' => $uaHash,
            ]);
        }
        $showInfo = false;
    }

    $lang = get_language();
    $languages = available_languages();

    render_layout($title, function () use ($errors, $deptId, $successMessage, $showInfo, $contact, $lang, $languages) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div style="flex:1;min-width:220px;">
                    <h2 style="margin-bottom:6px;"><?= sanitize('Forgot Password (Department Admin)'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Submit a secure reset request for the active department admin. Superadmin will review and approve.'); ?></p>
                </div>
                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                    <?php foreach ($languages as $code): ?>
                        <a class="pill" style="padding:8px 10px;border-color: <?= $lang === $code ? 'var(--primary)' : '#30363d'; ?>; background: <?= $lang === $code ? '#1f6feb22' : '#111820'; ?>; color: <?= $lang === $code ? '#fff' : 'var(--muted)'; ?>" href="?lang=<?= sanitize($code); ?>"><?= sanitize($code === 'en' ? 'English' : 'हिन्दी'); ?></a>
                    <?php endforeach; ?>
                    <a class="btn secondary" href="/department/login.php"><?= sanitize('Back to Login'); ?></a>
                </div>
            </div>
            <?php if ($errors): ?>
                <div class="flashes" style="margin-top:12px;">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($showInfo): ?>
                <div class="flash" style="margin-top:12px;border-color:#30363d;"><?= sanitize($successMessage); ?></div>
            <?php endif; ?>
            <form method="post" action="/department/forgot_password.php" style="margin-top:12px;display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="deptId"><?= sanitize('Department ID'); ?></label>
                    <input id="deptId" name="deptId" value="<?= sanitize($deptId); ?>" required minlength="2" maxlength="12" pattern="[a-z0-9]{2,12}" placeholder="e.g., jhdpw">
                    <p class="muted" style="margin:6px 0 0;font-size:0.9rem;"><?= sanitize('Enter lowercase letters or numbers only.'); ?></p>
                </div>
                <div class="field">
                    <label for="contact"><?= sanitize('Contact number/email (optional)'); ?></label>
                    <input id="contact" name="contact" value="<?= sanitize($contact ?? ''); ?>" placeholder="Share a phone or email for follow-up">
                    <p class="muted" style="margin:6px 0 0;font-size:0.9rem;"><?= sanitize('We will share this with superadmin for verification.'); ?></p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <button class="btn" type="submit"><?= sanitize('Submit Reset Request'); ?></button>
                    <div class="pill" style="border-color:#30363d;"><?= sanitize('Requests are rate limited for your safety.'); ?></div>
                </div>
                <p class="muted" style="margin:0;font-size:0.95rem;"><?= sanitize('For security, we never reveal whether a department exists. Approved requests receive a temporary password from superadmin.'); ?></p>
            </form>
        </div>
        <?php
    });
});
