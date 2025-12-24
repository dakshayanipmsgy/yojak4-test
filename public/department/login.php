<?php
require_once __DIR__ . '/../../bootstrap.php';

$user = currentUser();
if ($user && ($user['type'] ?? '') === 'department') {
    if (!empty($user['mustResetPassword'])) {
        header('Location: /auth/force_reset.php');
        exit;
    }
    header('Location: /department/dashboard.php');
    exit;
}

$errors = [];
$formData = ['user_id' => '', 'password' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['user_id'] = normalizeLoginId($_POST['user_id'] ?? '');
    $formData['password'] = $_POST['password'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = t('csrfInvalid', $lang);
        logEvent('departments.log', ['event' => 'csrf_invalid', 'path' => '/department/login.php']);
    }

    if ($formData['user_id'] === '') {
        $errors[] = 'User ID is required.';
    }
    if ($formData['password'] === '') {
        $errors[] = 'Password is required.';
    }

    if (empty($errors)) {
        $result = authenticate($formData['user_id'], $formData['password'], $config);
        if ($result['success'] && ($result['user']['type'] ?? '') === 'department') {
            registerLoginSession($result['user']);
            session_regenerate_id(true);
            $redirectPath = !empty($result['user']['mustResetPassword']) ? '/auth/force_reset.php' : '/department/dashboard.php';
            header('Location: ' . $redirectPath);
            exit;
        }

        if (($result['message'] ?? '') === 'rate_limited') {
            $errors[] = t('rateLimited', $lang);
        } else {
            $errors[] = t('formErrors', $lang);
        }
    }
}

safePage(function () use ($lang, $config, $errors, $formData) {
    renderLayoutStart('Department Login', $lang, $config, null, true);

    echo '<div class="card">';
    echo '<div class="grid">';
    echo '<div>';
    echo '<div class="chip">Department Portal</div>';
    echo '<h2>Department Login</h2>';
    echo '<p class="text-muted">Admin: adminShortId.admin.deptId</p>';
    echo '</div>';
    echo '<div>';
    if (!empty($errors)) {
        echo '<div class="alert alert-danger"><ul>';
        foreach ($errors as $error) {
            echo '<li>' . escape($error) . '</li>';
        }
        echo '</ul></div>';
    }
    echo '<form method="POST" action="" autocomplete="off">';
    echo csrfInput();
    echo '<div class="input-group"><label for="user_id">User ID</label>';
    echo '<input id="user_id" name="user_id" value="' . escape($formData['user_id']) . '" required></div>';
    echo '<div class="input-group"><label for="password">Password</label>';
    echo '<input type="password" id="password" name="password" required></div>';
    echo '<div class="form-actions"><button class="btn" type="submit">' . t('loginButton', $lang) . '</button></div>';
    echo '</form>';
    echo '<p class="hint">Only department admins can sign in here. Superadmin accounts stay on the primary login page.</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    renderLayoutEnd();
}, $lang, $config);
