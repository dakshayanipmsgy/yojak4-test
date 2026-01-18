<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_scheme_builder();
    $errors = [];
    $values = [
        'schemeId' => '',
        'name' => '',
        'shortDescription' => '',
        'category' => 'other',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $values['schemeId'] = strtoupper(trim($_POST['schemeId'] ?? ''));
        $values['name'] = trim($_POST['name'] ?? '');
        $values['shortDescription'] = trim($_POST['shortDescription'] ?? '');
        $values['category'] = trim($_POST['category'] ?? 'other');

        if ($values['schemeId'] === '') {
            $errors[] = 'Scheme ID is required.';
        }
        if ($values['name'] === '') {
            $errors[] = 'Scheme name is required.';
        }
        if (!preg_match('/^[A-Z0-9\-]+$/', $values['schemeId'])) {
            $errors[] = 'Scheme ID must use uppercase letters, numbers, and hyphens only.';
        }
        if (scheme_load_metadata($values['schemeId'])) {
            $errors[] = 'Scheme ID already exists.';
        }

        if (!$errors) {
            scheme_create_shell($values['schemeId'], $values['name'], $values['shortDescription'], $values['category'], [
                'userType' => $user['type'],
                'userId' => $user['username'] ?? $user['displayName'] ?? 'unknown',
            ]);
            set_flash('success', 'Scheme shell created.');
            redirect('/superadmin/scheme_import.php?schemeId=' . urlencode($values['schemeId']));
        }
    }

    $title = get_app_config()['appName'] . ' | New Scheme';
    render_layout($title, function () use ($errors, $values) {
        ?>
        <div class="card" style="max-width:720px;margin:0 auto;">
            <h2 style="margin-top:0;">Create Scheme Shell</h2>
            <p class="muted">Define the basic metadata before importing the JSON definition.</p>
            <?php if ($errors): ?>
                <div class="pill" style="border-color:#f08c00;color:#f08c00;">
                    <?= sanitize(implode(' ', $errors)); ?>
                </div>
            <?php endif; ?>
            <form method="post" style="display:grid;gap:12px;margin-top:16px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <label style="display:grid;gap:6px;">
                    <span>Scheme ID</span>
                    <input class="input" name="schemeId" placeholder="SCM-PMSURYAGHAR" value="<?= sanitize($values['schemeId']); ?>" required>
                </label>
                <label style="display:grid;gap:6px;">
                    <span>Scheme Name</span>
                    <input class="input" name="name" placeholder="PM Surya Ghar Vendor Workflow" value="<?= sanitize($values['name']); ?>" required>
                </label>
                <label style="display:grid;gap:6px;">
                    <span>Short Description</span>
                    <textarea class="input" name="shortDescription" rows="3" placeholder="Leads → Quotation → Invoice" required><?= sanitize($values['shortDescription']); ?></textarea>
                </label>
                <label style="display:grid;gap:6px;">
                    <span>Category</span>
                    <select class="input" name="category">
                        <?php foreach (['energy','water','housing','agri','other'] as $option): ?>
                            <option value="<?= sanitize($option); ?>" <?= $values['category'] === $option ? 'selected' : ''; ?>><?= sanitize(ucfirst($option)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="btn" type="submit">Create Scheme</button>
            </form>
        </div>
        <?php
    });
});
