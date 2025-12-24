<?php
require_once __DIR__ . '/../../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        logEvent('departments.log', ['event' => 'csrf_invalid', 'path' => '/department/logout.php']);
        header('Location: /department/login.php');
        exit;
    }
    clearSession();
    session_destroy();
    setcookie('yojak_lang', '', time() - 3600, '/');
    header('Location: /department/login.php');
    exit;
}

header('Location: /department/login.php');
