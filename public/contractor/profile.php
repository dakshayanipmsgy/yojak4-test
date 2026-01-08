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
    $errors = $_SESSION['contractor_profile_errors'] ?? [];
    unset($_SESSION['contractor_profile_errors']);
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

    if (isset($_SESSION['contractor_profile_form']) && is_array($_SESSION['contractor_profile_form'])) {
        $form = array_merge($form, $_SESSION['contractor_profile_form']);
        unset($_SESSION['contractor_profile_form']);
    }

    $requiredFields = [
        'firmName' => 'Firm name',
        'firmType' => 'Firm type',
        'addressLine1' => 'Address line 1',
        'district' => 'District',
        'state' => 'State',
        'pincode' => 'Pincode',
        'authorizedSignatoryName' => 'Authorized signatory name',
    ];
    $missing = [];
    foreach ($requiredFields as $field => $label) {
        if (trim((string)($form[$field] ?? '')) === '') {
            $missing[] = $label;
        }
    }
    $hasTaxId = trim((string)($form['gstNumber'] ?? '')) !== '' || trim((string)($form['panNumber'] ?? '')) !== '';
    if (!$hasTaxId) {
        $missing[] = 'GST or PAN';
    }
    $totalRequired = count($requiredFields) + 1;
    $completedRequired = $totalRequired - count($missing);
    $completionPercent = (int)round(($completedRequired / max(1, $totalRequired)) * 100);

    render_layout($title, function () use ($errors, $contractor, $form, $completionPercent, $missing) {
        $districts = ['Bokaro', 'Chatra', 'Deoghar', 'Dhanbad', 'Dumka', 'East Singhbhum', 'Garhwa', 'Giridih', 'Godda', 'Gumla', 'Hazaribagh', 'Jamtara', 'Khunti', 'Koderma', 'Latehar', 'Lohardaga', 'Pakur', 'Palamu', 'Ramgarh', 'Ranchi', 'Sahebganj', 'Seraikela Kharsawan', 'Simdega', 'West Singhbhum'];
        $firmTypes = ['Proprietorship', 'Partnership', 'LLP', 'Company', 'Other'];
        ?>
        <div class="card" style="display:grid;gap:14px;">
            <div>
                <h2 style="margin-bottom:6px;"><?= sanitize('Profile'); ?></h2>
                <p class="muted" style="margin:0;"><?= sanitize('Keep your contact and firm details updated. These values auto-fill tender templates.'); ?></p>
            </div>
            <div style="border:1px solid #30363d;border-radius:12px;padding:12px;background:#0f1520;display:grid;gap:10px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                    <div>
                        <div style="font-weight:600;"><?= sanitize('Profile completion'); ?></div>
                        <div class="muted" style="font-size:13px;"><?= sanitize('Missing fields will print as blanks.'); ?></div>
                    </div>
                    <div class="pill" style="border-color:#2ea043;color:#8ce99a;font-weight:700;"><?= sanitize($completionPercent . '%'); ?></div>
                </div>
                <div style="background:#0b111b;border-radius:999px;height:10px;overflow:hidden;border:1px solid #2a323c;">
                    <div style="height:10px;width:<?= (int)$completionPercent; ?>%;background:linear-gradient(90deg,#2563eb,#22c55e);"></div>
                </div>
                <?php if ($missing): ?>
                    <div>
                        <div class="muted" style="font-size:13px;margin-bottom:6px;"><?= sanitize('Still missing:'); ?></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php foreach ($missing as $item): ?>
                                <span class="tag"><?= sanitize($item); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <a class="btn secondary" href="#profile-form" style="width:max-content;color:var(--text);"><?= sanitize('Add now'); ?></a>
                <?php endif; ?>
            </div>
            <?php if ($errors): ?>
                <div class="flashes">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form id="profile-form" method="post" action="/contractor/profile_save.php" style="display:grid;gap:12px;">
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
