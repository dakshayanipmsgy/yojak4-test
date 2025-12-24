<?php
require_once __DIR__ . '/../../../bootstrap.php';

$user = requireAuth('superadmin');
requireNoForceReset($user);

safePage(function () use ($lang, $config, $user) {
    renderLayoutStart(t('profile', $lang), $lang, $config, $user, true);

    echo '<div class="card">';
    echo '<h2>' . t('profile', $lang) . '</h2>';
    echo '<p class="text-muted">' . t('statusActive', $lang) . '</p>';
    echo '<div class="grid">';
    echo '<div><p class="eyebrow">' . t('username', $lang) . '</p><p>' . escape($user['username']) . '</p></div>';
    echo '<div><p class="eyebrow">' . t('lastLogin', $lang) . '</p><p>' . escape(formatDateTime($user['lastLoginAt'] ?? null)) . '</p></div>';
    echo '<div><p class="eyebrow">' . t('mustReset', $lang) . '</p><p>' . (!empty($user['mustResetPassword']) ? t('resetRequiredBanner', $lang) : t('statusActive', $lang)) . '</p></div>';
    echo '</div>';
    echo '</div>';

    renderLayoutEnd();
}, $lang, $config);
