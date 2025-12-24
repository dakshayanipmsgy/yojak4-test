<?php
require_once __DIR__ . '/../../../bootstrap.php';

$user = requireAuth('superadmin');
requireNoForceReset($user);

$errors = [];
$formData = [
    'deptId' => '',
    'nameEn' => '',
    'nameHi' => '',
    'address' => '',
    'contactEmail' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = t('csrfInvalid', $lang);
        logEvent('departments.log', ['event' => 'csrf_invalid', 'path' => '/superadmin/department_create.php']);
    }

    $formData['deptId'] = normalizeDeptId($_POST['dept_id'] ?? '');
    $formData['nameEn'] = trim($_POST['name_en'] ?? '');
    $formData['nameHi'] = trim($_POST['name_hi'] ?? '');
    $formData['address'] = trim($_POST['address'] ?? '');
    $formData['contactEmail'] = trim($_POST['contact_email'] ?? '');

    if (empty($errors)) {
        $result = createDepartment([
            'deptId' => $formData['deptId'],
            'nameEn' => $formData['nameEn'],
            'nameHi' => $formData['nameHi'],
            'address' => $formData['address'],
            'contactEmail' => $formData['contactEmail'],
        ]);

        if ($result['success']) {
            setFlash('success', 'Department created.');
            header('Location: /superadmin/department_view.php?deptId=' . urlencode($formData['deptId']));
            exit;
        }

        $errors = array_merge($errors, $result['errors'] ?? ['Unable to create department.']);
    }
}

safePage(function () use ($lang, $config, $user, $errors, $formData) {
    renderLayoutStart('Create Department', $lang, $config, $user, true);

    echo '<div class="card">';
    echo '<div class="section-title"><div><h2>Create Department</h2><p class="card-subtitle">Metadata only. Files remain within department scope.</p></div>';
    echo '<a class="ghost-btn" href="/superadmin/departments.php">Back to registry</a></div>';

    if (!empty($errors)) {
        echo '<div class="alert alert-danger"><ul>';
        foreach ($errors as $error) {
            echo '<li>' . escape($error) . '</li>';
        }
        echo '</ul></div>';
    }

    echo '<form method="POST" action="">';
    echo csrfInput();
    echo '<div class="grid">';
    echo '<div class="input-group"><label for="dept_id">Department ID (3-10 lowercase a-z0-9)</label><input id="dept_id" name="dept_id" value="' . escape($formData['deptId']) . '" required pattern="[a-z0-9]{3,10}"></div>';
    echo '<div class="input-group"><label for="name_en">Name (English)</label><input id="name_en" name="name_en" value="' . escape($formData['nameEn']) . '" required></div>';
    echo '<div class="input-group"><label for="name_hi">नाम (हिंदी)</label><input id="name_hi" name="name_hi" value="' . escape($formData['nameHi']) . '" required></div>';
    echo '<div class="input-group"><label for="contact_email">Contact Email (optional)</label><input type="email" id="contact_email" name="contact_email" value="' . escape($formData['contactEmail']) . '"></div>';
    echo '<div class="input-group"><label for="address">Address (optional)</label><input id="address" name="address" value="' . escape($formData['address']) . '"></div>';
    echo '</div>';
    echo '<div class="form-actions"><button class="btn" type="submit">Create Department</button></div>';
    echo '</form>';
    echo '<p class="hint">Timezone enforced: Asia/Kolkata. No department documents are shown to superadmin.</p>';
    echo '</div>';

    renderLayoutEnd();
}, $lang, $config);
