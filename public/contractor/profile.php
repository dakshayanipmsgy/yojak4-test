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
    $form = [
        'name' => trim((string)($contractor['name'] ?? '')),
        'firmName' => trim((string)($contractor['firmName'] ?? '')),
        'firmType' => trim((string)($contractor['firmType'] ?? '')),
        'addressLine1' => trim((string)($contractor['addressLine1'] ?? '')),
        'addressLine2' => trim((string)($contractor['addressLine2'] ?? '')),
        'district' => trim((string)($contractor['district'] ?? '')),
        'state' => trim((string)($contractor['state'] ?? 'Jharkhand')),
        'pincode' => trim((string)($contractor['pincode'] ?? '')),
        'authorizedSignatoryName' => trim((string)($contractor['authorizedSignatoryName'] ?? ($contractor['name'] ?? ''))),
        'authorizedSignatoryDesignation' => trim((string)($contractor['authorizedSignatoryDesignation'] ?? '')),
        'email' => trim((string)($contractor['email'] ?? '')),
        'gstNumber' => trim((string)($contractor['gstNumber'] ?? '')),
        'panNumber' => trim((string)($contractor['panNumber'] ?? '')),
        'bankName' => trim((string)($contractor['bankName'] ?? '')),
        'bankAccount' => trim((string)($contractor['bankAccount'] ?? '')),
        'ifsc' => trim((string)($contractor['ifsc'] ?? '')),
        'placeDefault' => trim((string)($contractor['placeDefault'] ?? '')),
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $form['name'] = trim($_POST['name'] ?? '');
        $form['firmName'] = trim($_POST['firmName'] ?? '');
        $form['firmType'] = trim($_POST['firmType'] ?? '');
        $form['addressLine1'] = trim($_POST['addressLine1'] ?? '');
        $form['addressLine2'] = trim($_POST['addressLine2'] ?? '');
        $form['district'] = trim($_POST['district'] ?? '');
        $form['state'] = trim($_POST['state'] ?? '');
        $form['pincode'] = trim($_POST['pincode'] ?? '');
        $form['authorizedSignatoryName'] = trim($_POST['authorizedSignatoryName'] ?? '');
        $form['authorizedSignatoryDesignation'] = trim($_POST['authorizedSignatoryDesignation'] ?? '');
        $form['email'] = trim($_POST['email'] ?? '');
        $form['gstNumber'] = trim($_POST['gstNumber'] ?? '');
        $form['panNumber'] = strtoupper(trim($_POST['panNumber'] ?? ''));
        $form['bankName'] = trim($_POST['bankName'] ?? '');
        $form['bankAccount'] = trim($_POST['bankAccount'] ?? '');
        $form['ifsc'] = strtoupper(trim($_POST['ifsc'] ?? ''));
        $form['placeDefault'] = trim($_POST['placeDefault'] ?? '');

        $allowedFirmTypes = ['Proprietorship', 'Partnership', 'LLP', 'Company', 'Other'];
        if ($form['name'] !== '' && strlen($form['name']) < 2) {
            $errors[] = 'Name must be at least 2 characters.';
        }
        if ($form['firmName'] !== '' && strlen($form['firmName']) < 2) {
            $errors[] = 'Firm name must be at least 2 characters.';
        }
        if ($form['firmType'] !== '' && !in_array($form['firmType'], $allowedFirmTypes, true)) {
            $errors[] = 'Invalid firm type selected.';
        }
        if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        }
        if ($form['pincode'] !== '' && !preg_match('/^[0-9]{6}$/', $form['pincode'])) {
            $errors[] = 'Pincode must be 6 digits.';
        }
        if ($form['panNumber'] !== '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $form['panNumber'])) {
            $errors[] = 'PAN should follow standard format (e.g., ABCDE1234F).';
        }
        if ($form['gstNumber'] !== '' && !preg_match('/^[0-9A-Z]{15}$/', $form['gstNumber'])) {
            $errors[] = 'GST number should be 15 characters.';
        }
        if ($form['ifsc'] !== '' && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $form['ifsc'])) {
            $errors[] = 'Invalid IFSC code.';
        }
        if ($form['bankAccount'] !== '' && strlen($form['bankAccount']) > 30) {
            $errors[] = 'Bank account number is too long.';
        }

        if (!$errors) {
            $contractor['name'] = $form['name'];
            $contractor['firmName'] = $form['firmName'];
            $contractor['firmType'] = $form['firmType'];
            $contractor['addressLine1'] = $form['addressLine1'];
            $contractor['addressLine2'] = $form['addressLine2'];
            $contractor['address'] = trim($form['addressLine1'] . ' ' . $form['addressLine2']);
            $contractor['district'] = $form['district'];
            $contractor['state'] = $form['state'];
            $contractor['pincode'] = $form['pincode'];
            $contractor['authorizedSignatoryName'] = $form['authorizedSignatoryName'];
            $contractor['authorizedSignatoryDesignation'] = $form['authorizedSignatoryDesignation'];
            $contractor['email'] = $form['email'];
            $contractor['gstNumber'] = $form['gstNumber'];
            $contractor['panNumber'] = $form['panNumber'];
            $contractor['bankName'] = $form['bankName'];
            $contractor['bankAccount'] = $form['bankAccount'];
            $contractor['ifsc'] = $form['ifsc'];
            $contractor['placeDefault'] = $form['placeDefault'];
            save_contractor($contractor);
            $_SESSION['user']['displayName'] = $form['firmName'] ?: ($form['name'] ?: $contractor['mobile']);
            set_flash('success', 'Profile updated.');
            redirect('/contractor/profile.php');
        }
    }

    render_layout($title, function () use ($errors, $contractor, $form) {
        $districts = ['Bokaro', 'Chatra', 'Deoghar', 'Dhanbad', 'Dumka', 'East Singhbhum', 'Garhwa', 'Giridih', 'Godda', 'Gumla', 'Hazaribagh', 'Jamtara', 'Khunti', 'Koderma', 'Latehar', 'Lohardaga', 'Pakur', 'Palamu', 'Ramgarh', 'Ranchi', 'Sahebganj', 'Seraikela Kharsawan', 'Simdega', 'West Singhbhum'];
        $firmTypes = ['Proprietorship', 'Partnership', 'LLP', 'Company', 'Other'];
        ?>
        <div class="card" style="display:grid;gap:14px;">
            <div>
                <h2 style="margin-bottom:6px;"><?= sanitize('Profile'); ?></h2>
                <p class="muted" style="margin:0;"><?= sanitize('Keep your contact and firm details updated. These values auto-fill tender templates.'); ?></p>
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
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                    <div class="field">
                        <label><?= sanitize('YOJ ID'); ?></label>
                        <div class="pill"><?= sanitize($contractor['yojId']); ?></div>
                    </div>
                    <div class="field">
                        <label><?= sanitize('Mobile'); ?></label>
                        <div class="pill"><?= sanitize($contractor['mobile']); ?></div>
                    </div>
                    <div class="field">
                        <label for="email"><?= sanitize('Email'); ?></label>
                        <input id="email" name="email" type="email" value="<?= sanitize($form['email']); ?>" placeholder="name@example.com">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                    <div class="field">
                        <label for="firmName"><?= sanitize('Firm Name'); ?></label>
                        <input id="firmName" name="firmName" value="<?= sanitize($form['firmName']); ?>" placeholder="Registered firm name">
                    </div>
                    <div class="field">
                        <label for="firmType"><?= sanitize('Firm Type'); ?></label>
                        <select id="firmType" name="firmType">
                            <option value=""><?= sanitize('Select type'); ?></option>
                            <?php foreach ($firmTypes as $type): ?>
                                <option value="<?= sanitize($type); ?>" <?= $form['firmType'] === $type ? 'selected' : ''; ?>><?= sanitize($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="name"><?= sanitize('Primary Contact / Signatory'); ?></label>
                        <input id="name" name="name" value="<?= sanitize($form['name']); ?>" placeholder="Name of contact person">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                    <div class="field">
                        <label for="authorizedSignatoryName"><?= sanitize('Authorized Signatory Name'); ?></label>
                        <input id="authorizedSignatoryName" name="authorizedSignatoryName" value="<?= sanitize($form['authorizedSignatoryName']); ?>" placeholder="Authorized signatory">
                    </div>
                    <div class="field">
                        <label for="authorizedSignatoryDesignation"><?= sanitize('Signatory Designation'); ?></label>
                        <input id="authorizedSignatoryDesignation" name="authorizedSignatoryDesignation" value="<?= sanitize($form['authorizedSignatoryDesignation']); ?>" placeholder="Proprietor/Director/etc.">
                    </div>
                    <div class="field">
                        <label for="placeDefault"><?= sanitize('Default Place (for letters)'); ?></label>
                        <input id="placeDefault" name="placeDefault" value="<?= sanitize($form['placeDefault']); ?>" placeholder="City/Town">
                    </div>
                </div>
                <div class="field">
                    <label><?= sanitize('Address'); ?></label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px;">
                        <input name="addressLine1" placeholder="<?= sanitize('Address line 1'); ?>" value="<?= sanitize($form['addressLine1']); ?>">
                        <input name="addressLine2" placeholder="<?= sanitize('Address line 2 (optional)'); ?>" value="<?= sanitize($form['addressLine2']); ?>">
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-top:8px;">
                        <div class="field" style="margin:0;">
                            <label for="district"><?= sanitize('District'); ?></label>
                            <select id="district" name="district">
                                <option value=""><?= sanitize('Select district'); ?></option>
                                <?php foreach ($districts as $district): ?>
                                    <option value="<?= sanitize($district); ?>" <?= $form['district'] === $district ? 'selected' : ''; ?>><?= sanitize($district); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field" style="margin:0;">
                            <label for="state"><?= sanitize('State'); ?></label>
                            <input id="state" name="state" value="<?= sanitize($form['state']); ?>" placeholder="State">
                        </div>
                        <div class="field" style="margin:0;">
                            <label for="pincode"><?= sanitize('Pincode'); ?></label>
                            <input id="pincode" name="pincode" value="<?= sanitize($form['pincode']); ?>" placeholder="6-digit">
                        </div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;">
                    <div class="field">
                        <label for="gstNumber"><?= sanitize('GST Number'); ?></label>
                        <input id="gstNumber" name="gstNumber" value="<?= sanitize($form['gstNumber']); ?>" placeholder="15-digit GSTIN">
                    </div>
                    <div class="field">
                        <label for="panNumber"><?= sanitize('PAN'); ?></label>
                        <input id="panNumber" name="panNumber" value="<?= sanitize($form['panNumber']); ?>" placeholder="ABCDE1234F">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;">
                    <div class="field">
                        <label for="bankName"><?= sanitize('Bank Name'); ?></label>
                        <input id="bankName" name="bankName" value="<?= sanitize($form['bankName']); ?>" placeholder="Bank for correspondence">
                    </div>
                    <div class="field">
                        <label for="bankAccount"><?= sanitize('Bank Account'); ?></label>
                        <input id="bankAccount" name="bankAccount" value="<?= sanitize($form['bankAccount']); ?>" placeholder="Account number (optional)">
                    </div>
                    <div class="field">
                        <label for="ifsc"><?= sanitize('IFSC'); ?></label>
                        <input id="ifsc" name="ifsc" value="<?= sanitize($form['ifsc']); ?>" placeholder="IFSC">
                    </div>
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
