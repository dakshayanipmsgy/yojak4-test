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
        <div class="card">
            <h2><?= sanitize('Profile'); ?></h2>
            <p class="muted"><?= sanitize('Keep your contact details updated for approvals.'); ?></p>
            <?php if ($errors): ?>
                <div class="flashes">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/contractor/profile.php">
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
        <?php
    });
});
