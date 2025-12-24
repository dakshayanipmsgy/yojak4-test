<?php
require_once __DIR__ . '/../../../bootstrap.php';

$user = currentUser();
if ($user) {
    if (!empty($user['mustResetPassword'])) {
        header('Location: /auth/force_reset.php');
        exit;
    }
    header('Location: /superadmin/dashboard.php');
    exit;
}

$errors = [];
$formData = ['username' => '', 'password' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['username'] = trim($_POST['username'] ?? '');
    $formData['password'] = $_POST['password'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = t('csrfInvalid', $lang);
        logEvent('auth.log', ['event' => 'csrf_invalid', 'path' => '/auth/login.php']);
    }

    if ($formData['username'] === '') {
        $errors[] = t('username', $lang) . ' ' . t('formErrors', $lang);
    }
    if ($formData['password'] === '') {
        $errors[] = t('password', $lang) . ' ' . t('formErrors', $lang);
    }

    if (empty($errors)) {
        $result = authenticate($formData['username'], $formData['password'], $config);
        if ($result['success']) {
            registerLoginSession($result['user']);
            session_regenerate_id(true);
            header('Location: ' . (!empty($result['user']['mustResetPassword']) ? '/auth/force_reset.php' : '/superadmin/dashboard.php'));
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
    renderLayoutStart(t('login', $lang), $lang, $config, null, true);

    echo '<div class="card">';
    echo '<div class="grid">';
    echo '<div>';
    echo '<div class="chip">' . t('superadminOnly', $lang) . '</div>';
    echo '<h2>' . t('login', $lang) . '</h2>';
    echo '<p class="text-muted">' . t('homeLead', $lang) . '</p>';
    echo '</div>';
    echo '<div>'; 
    if (!empty($errors)) {
        echo '<div class="alert alert-danger">' . escape(t('formErrors', $lang)) . '<ul>';
        foreach ($errors as $err) {
            echo '<li>' . escape($err) . '</li>';
        }
        echo '</ul></div>';
    }
    echo '<form method="POST" action="" autocomplete="off">';
    echo csrfInput();
    echo '<div class="input-group"><label for="username">' . t('username', $lang) . '</label>';
    echo '<input id="username" name="username" value="' . escape($formData['username']) . '" required></div>';
    echo '<div class="input-group"><label for="password">' . t('password', $lang) . '</label>';
    echo '<input type="password" id="password" name="password" required></div>';
    echo '<div class="form-actions"><button class="btn" type="submit">' . t('loginButton', $lang) . '</button></div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="card">';
    echo '<h3>' . t('rememberLanguage', $lang) . '</h3>';
    echo '<p class="text-muted">' . escape(t('languageToggle', $lang)) . '</p>';
    echo '<a class="btn" href="?lang=' . ($lang === 'en' ? 'hi' : 'en') . '">' . escape(t('languageToggle', $lang)) . '</a>';
    echo '</div>';

    renderLayoutEnd();
}, $lang, $config);
