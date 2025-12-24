<?php
require_once __DIR__ . '/../../../bootstrap.php';

$user = requireAuth('superadmin');
requireNoForceReset($user);

safePage(function () use ($lang, $config, $user) {
    renderLayoutStart(t('dashboard', $lang), $lang, $config, $user, true);

    echo '<div class="grid">';
    echo '<div class="card">';
    echo '<h2>' . t('dashboard', $lang) . '</h2>';
    echo '<p class="text-muted">' . t('homeLead', $lang) . '</p>';
    echo '<div class="badge">Superadmin</div>';
    echo '</div>';

    echo '<div class="card">';
    echo '<h3>' . t('profile', $lang) . '</h3>';
    echo '<p class="text-muted">' . escape($user['username']) . '</p>';
    echo '<p class="text-muted">' . t('lastLogin', $lang) . ': ' . escape(formatDateTime($user['lastLoginAt'] ?? null)) . '</p>';
    echo '</div>';
    echo '</div>';

    renderLayoutEnd();
}, $lang, $config);
