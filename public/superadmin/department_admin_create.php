<?php
require_once __DIR__ . '/../../../bootstrap.php';

$user = requireAuth('superadmin');
requireNoForceReset($user);

$deptId = normalizeDeptId($_GET['deptId'] ?? '');
$department = $deptId !== '' ? loadDepartment($deptId) : null;

if (!$department) {
    http_response_code(404);
    safePage(function () use ($lang, $config, $user) {
        renderLayoutStart('Department Not Found', $lang, $config, $user, true);
        echo '<div class="card"><div class="friendly-error"><h2>Department not found</h2><p class="text-muted">Unable to create admin because the department does not exist.</p><a class="btn" href="/superadmin/departments.php">Back to registry</a></div></div>';
        renderLayoutEnd();
    }, $lang, $config);
    exit;
}

$errors = [];
$formData = [
    'adminShortId' => '',
    'displayName' => '',
    'password' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = t('csrfInvalid', $lang);
        logEvent('departments.log', ['event' => 'csrf_invalid', 'path' => '/superadmin/department_admin_create.php']);
    }

    $formData['adminShortId'] = strtolower(trim($_POST['admin_short_id'] ?? ''));
    $formData['displayName'] = trim($_POST['display_name'] ?? '');
    $formData['password'] = $_POST['password'] ?? '';

    if (empty($errors)) {
        $result = createOrReplaceDepartmentAdmin($deptId, $formData['adminShortId'], $formData['displayName'], $formData['password']);
        if ($result['success']) {
            setFlash('success', 'Department admin saved. Existing admin archived if present.');
            header('Location: /superadmin/department_view.php?deptId=' . urlencode($deptId));
            exit;
        }
        $errors = array_merge($errors, $result['errors'] ?? ['Unable to save admin.']);
    }
}

safePage(function () use ($lang, $config, $user, $department, $errors, $formData) {
    renderLayoutStart('Department Admin', $lang, $config, $user, true);

    echo '<div class="card">';
    echo '<div class="section-title"><div><h2>Department Admin</h2><p class="card-subtitle">Only one active admin per department. Previous admin will be archived.</p></div>';
    echo '<a class="ghost-btn" href="/superadmin/department_view.php?deptId=' . escape($department['deptId']) . '">Back to department</a></div>';

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
    echo '<div class="input-group"><label for="admin_short_id">Admin Short ID (3-12 lowercase a-z0-9)</label><input id="admin_short_id" name="admin_short_id" value="' . escape($formData['adminShortId']) . '" required pattern="[a-z0-9]{3,12}"></div>';
    echo '<div class="input-group"><label for="display_name">Display Name</label><input id="display_name" name="display_name" value="' . escape($formData['displayName']) . '" required></div>';
    echo '<div class="input-group"><label for="password">Temporary Password (min 8 chars)</label><input type="password" id="password" name="password" required minlength="8"></div>';
    echo '</div>';
    echo '<p class="hint">Login format: <strong>adminShortId.admin.' . escape($department['deptId']) . '</strong>. Admin must reset password on first login.</p>';
    echo '<div class="form-actions"><button class="btn" type="submit">Save Admin</button></div>';
    echo '</form>';
    echo '</div>';

    renderLayoutEnd();
}, $lang, $config);
