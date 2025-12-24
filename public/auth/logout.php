<?php
require_once __DIR__ . '/../../../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        logEvent('auth.log', ['event' => 'csrf_invalid', 'path' => '/auth/logout.php']);
        header('Location: /site/index.php');
        exit;
    }
    clearSession();
    session_destroy();
    setcookie('yojak_lang', '', time() - 3600, '/');
    header('Location: /auth/login.php');
    exit;
}

header('Location: /auth/login.php');
