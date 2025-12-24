<?php
require_once __DIR__ . '/../../../bootstrap.php';

$user = requireAuth(['superadmin', 'department']);

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = t('csrfInvalid', $lang);
        logEvent('auth.log', ['event' => 'csrf_invalid', 'path' => '/auth/force_reset.php']);
    }

    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $errors[] = t('passwordRules', $lang);
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = t('confirmPassword', $lang) . ' ' . t('formErrors', $lang);
    }

    if (password_verify($newPassword, $user['passwordHash'] ?? '')) {
        $errors[] = t('passwordRules', $lang);
    }

    if (empty($errors)) {
        $user['passwordHash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $user['mustResetPassword'] = false;
        saveUser($user);
        logEvent('auth.log', ['event' => 'password_reset', 'username' => $user['username']]);
        $success = true;
        $redirectPath = ($user['type'] ?? '') === 'department' ? '/department/dashboard.php' : '/superadmin/dashboard.php';
        header('Location: ' . $redirectPath);
        exit;
    }
}

safePage(function () use ($lang, $config, $errors, $success) {
    $user = currentUser();
    renderLayoutStart(t('forceResetTitle', $lang), $lang, $config, $user, true);

    echo '<div class="card">';
    echo '<div class="alert alert-info">' . t('resetRequiredBanner', $lang) . '</div>';
    if (!empty($errors)) {
        echo '<div class="alert alert-danger">';
        echo '<ul>';
        foreach ($errors as $error) {
            echo '<li>' . escape($error) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    echo '<form method="POST" action="">';
    echo csrfInput();
    echo '<div class="input-group"><label for="new_password">' . t('newPassword', $lang) . '</label>';
    echo '<input type="password" id="new_password" name="new_password" required minlength="8"></div>';
    echo '<div class="input-group"><label for="confirm_password">' . t('confirmPassword', $lang) . '</label>';
    echo '<input type="password" id="confirm_password" name="confirm_password" required minlength="8"></div>';
    echo '<div class="form-actions"><button class="btn" type="submit">' . t('resetCta', $lang) . '</button></div>';
    echo '</form>';
    echo '</div>';

    renderLayoutEnd();
}, $lang, $config);
