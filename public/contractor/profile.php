<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $contractor = load_contractor($user['yojId']);
    if (!$contractor) {
        render_error_page('Contractor profile not found.');
        return;
    }

    $title = get_app_config()['appName'] . ' | Contractor Profile';
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $name = trim($_POST['name'] ?? '');
        $firmName = trim($_POST['firmName'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $district = trim($_POST['district'] ?? '');

        if ($name !== '' && strlen($name) < 2) {
            $errors[] = 'Name must be at least 2 characters.';
        }
        if ($firmName !== '' && strlen($firmName) < 2) {
            $errors[] = 'Firm name must be at least 2 characters.';
        }

        if (!$errors) {
            $contractor['name'] = $name;
            $contractor['firmName'] = $firmName;
            $contractor['address'] = $address;
            $contractor['district'] = $district;
            save_contractor($contractor);
            $_SESSION['user']['displayName'] = $name ?: $contractor['mobile'];
            set_flash('success', 'Profile updated.');
            redirect('/contractor/profile.php');
        }
    }

    render_layout($title, function () use ($errors, $contractor) {
        $districts = ['Bokaro', 'Chatra', 'Deoghar', 'Dhanbad', 'Dumka', 'East Singhbhum', 'Garhwa', 'Giridih', 'Godda', 'Gumla', 'Hazaribagh', 'Jamtara', 'Khunti', 'Koderma', 'Latehar', 'Lohardaga', 'Pakur', 'Palamu', 'Ramgarh', 'Ranchi', 'Sahebganj', 'Seraikela Kharsawan', 'Simdega', 'West Singhbhum'];
        ?>
        <div class="card" style="display:grid;gap:14px;">
            <div>
                <h2 style="margin-bottom:6px;"><?= sanitize('Profile'); ?></h2>
                <p class="muted" style="margin:0;"><?= sanitize('Keep your contact details updated for approvals.'); ?></p>
            </div>
            <?php if ($errors): ?>
                <div class="flashes">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/contractor/profile.php" style="display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label><?= sanitize('YOJ ID'); ?></label>
                    <div class="pill"><?= sanitize($contractor['yojId']); ?></div>
                </div>
                <div class="field">
                    <label><?= sanitize('Mobile'); ?></label>
                    <div class="pill"><?= sanitize($contractor['mobile']); ?></div>
                </div>
                <div class="field">
                    <label for="name"><?= sanitize('Full Name'); ?></label>
                    <input id="name" name="name" value="<?= sanitize($contractor['name'] ?? ''); ?>">
                </div>
                <div class="field">
                    <label for="firmName"><?= sanitize('Firm Name'); ?></label>
                    <input id="firmName" name="firmName" value="<?= sanitize($contractor['firmName'] ?? ''); ?>">
                </div>
                <div class="field">
                    <label for="address"><?= sanitize('Address'); ?></label>
                    <textarea id="address" name="address" rows="3" style="resize:vertical; padding:10px; border-radius:10px; border:1px solid #30363d; background:#0d1117; color:var(--text);"><?= sanitize($contractor['address'] ?? ''); ?></textarea>
                </div>
                <div class="field">
                    <label for="district"><?= sanitize('District'); ?></label>
                    <select id="district" name="district">
                        <option value=""><?= sanitize('Select district'); ?></option>
                        <?php foreach ($districts as $district): ?>
                            <option value="<?= sanitize($district); ?>" <?= ($contractor['district'] ?? '') === $district ? 'selected' : ''; ?>><?= sanitize($district); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn" type="submit"><?= sanitize('Save Profile'); ?></button>
            </form>
        </div>

        <div class="card" style="display:grid;gap:14px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:6px;"><?= sanitize('Password'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Set a strong password to keep your account secure.'); ?></p>
                </div>
                <div class="pill" style="background:#13233a;color:#9cc4ff;font-weight:600;"><?= sanitize('Min 8 characters'); ?></div>
            </div>
            <form method="post" action="/contractor/profile_password.php" style="display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="password_current"><?= sanitize('Current Password'); ?></label>
                    <input id="password_current" name="password_current" type="password" required autocomplete="current-password">
                </div>
                <div class="field">
                    <label for="password_new"><?= sanitize('New Password'); ?></label>
                    <input id="password_new" name="password_new" type="password" required minlength="8" autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="password_confirm"><?= sanitize('Confirm New Password'); ?></label>
                    <input id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password">
                </div>
                <button class="btn" type="submit"><?= sanitize('Save Password'); ?></button>
            </form>
        </div>
        <?php
    });
});
