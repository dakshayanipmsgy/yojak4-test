<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $errors = [];
    $data = [
        'deptId' => '',
        'nameEn' => '',
        'nameHi' => '',
        'address' => '',
        'contactEmail' => '',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $data['deptId'] = normalize_dept_id($_POST['deptId'] ?? '');
        $data['nameEn'] = trim($_POST['nameEn'] ?? '');
        $data['nameHi'] = trim($_POST['nameHi'] ?? '');
        $data['address'] = trim($_POST['address'] ?? '');
        $data['contactEmail'] = trim($_POST['contactEmail'] ?? '');

        if (!is_valid_dept_id($data['deptId'])) {
            $errors[] = 'Department ID must be 3-10 lowercase letters or numbers.';
        }
        if ($data['nameEn'] === '' || $data['nameHi'] === '') {
            $errors[] = 'Both English and Hindi names are required.';
        }
        if ($data['contactEmail'] !== '' && !filter_var($data['contactEmail'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Contact email must be valid if provided.';
        }
        if (department_exists($data['deptId'])) {
            $errors[] = 'Department ID already exists.';
        }

        if (!$errors) {
            $dept = create_department_record($data['deptId'], $data['nameEn'], $data['nameHi'], $data['address'], $data['contactEmail']);
            set_flash('success', 'Department created. Next, add a department admin.');
            redirect('/superadmin/department_view.php?deptId=' . urlencode($dept['deptId']));
        }
    }

    $title = get_app_config()['appName'] . ' | Create Department';
    render_layout($title, function () use ($errors, $data) {
        ?>
        <div class="card">
            <h2><?= sanitize('Create Department'); ?></h2>
            <p class="muted"><?= sanitize('Register a department. Department IDs are immutable.'); ?></p>
            <?php if ($errors): ?>
                <div class="flashes">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/superadmin/department_create.php">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="deptId"><?= sanitize('Department ID (3-10 lowercase letters/numbers)'); ?></label>
                    <input id="deptId" name="deptId" value="<?= sanitize($data['deptId']); ?>" required minlength="3" maxlength="10" pattern="[a-z0-9]+">
                </div>
                <div class="field">
                    <label for="nameEn"><?= sanitize('Department Name (English)'); ?></label>
                    <input id="nameEn" name="nameEn" value="<?= sanitize($data['nameEn']); ?>" required>
                </div>
                <div class="field">
                    <label for="nameHi"><?= sanitize('Department Name (Hindi)'); ?></label>
                    <input id="nameHi" name="nameHi" value="<?= sanitize($data['nameHi']); ?>" required>
                </div>
                <div class="field">
                    <label for="address"><?= sanitize('Address (optional)'); ?></label>
                    <input id="address" name="address" value="<?= sanitize($data['address']); ?>">
                </div>
                <div class="field">
                    <label for="contactEmail"><?= sanitize('Contact Email (optional)'); ?></label>
                    <input id="contactEmail" name="contactEmail" type="email" value="<?= sanitize($data['contactEmail']); ?>">
                </div>
                <div class="buttons">
                    <button class="btn" type="submit"><?= sanitize('Create'); ?></button>
                    <a class="btn secondary" href="/superadmin/departments.php"><?= sanitize('Cancel'); ?></a>
                </div>
            </form>
        </div>
        <?php
    });
});
