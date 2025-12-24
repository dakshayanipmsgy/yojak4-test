<?php
require_once __DIR__ . '/../../bootstrap.php';

$user = currentUser();

safePage(function () use ($lang, $config, $user) {
    renderLayoutStart(t('welcome', $lang), $lang, $config, $user, true);
    echo '<div class="grid">';
    echo '<div class="card">';
    echo '<h2>' . t('welcome', $lang) . '</h2>';
    echo '<p class="text-muted">' . t('homeLead', $lang) . '</p>';
    echo '<p class="text-muted">' . t('homeCta', $lang) . '</p>';
    echo '<div class="form-actions"><a class="btn" href="/auth/login.php">' . t('loginButton', $lang) . '</a></div>';
    echo '</div>';

    echo '<div class="card">';
    echo '<h2>' . t('healthCheck', $lang) . '</h2>';
    echo '<p class="text-muted">' . escape($config['appName'] ?? 'YOJAK') . '</p>';
    echo '<ul class="text-muted">';
    echo '<li>' . escape($config['timezone'] ?? 'Asia/Kolkata') . '</li>';
    echo '<li>' . escape(t('langEnglish', $lang)) . ' / ' . escape(t('langHindi', $lang)) . '</li>';
    echo '</ul>';
    echo '</div>';
    echo '</div>';
    renderLayoutEnd();
}, $lang, $config);
