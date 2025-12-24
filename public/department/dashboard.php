<?php
require_once __DIR__ . '/../../bootstrap.php';

$user = requireAuth('department');
requireNoForceReset($user);

$parsed = parseDepartmentUserId($user['username'] ?? '');
$department = $parsed ? loadDepartment($parsed['deptId']) : null;

safePage(function () use ($lang, $config, $user, $department, $parsed) {
    renderLayoutStart('Department Dashboard', $lang, $config, $user, true);

    echo '<div class="card">';
    echo '<h2>Department Dashboard</h2>';
    echo '<p class="text-muted">Secure shell for department operations. Content boundaries enforced.</p>';
    echo '<div class="grid">';
    echo '<div class="stack"><p class="eyebrow">User</p><p>' . escape($user['username'] ?? '') . '</p></div>';
    echo '<div class="stack"><p class="eyebrow">Display Name</p><p>' . escape($user['displayName'] ?? '') . '</p></div>';
    if ($parsed) {
        echo '<div class="stack"><p class="eyebrow">Department</p><p>' . escape($parsed['deptId']) . '</p></div>';
    }
    echo '<div class="stack"><p class="eyebrow">Role</p><p>' . escape($user['roleId'] ?? 'admin') . '</p></div>';
    echo '</div>';
    if ($department) {
        echo '<div class="stack" style="margin-top:12px;"><p class="eyebrow">Department Name</p><p>' . escape($department['nameEn'] ?? '') . ' / ' . escape($department['nameHi'] ?? '') . '</p></div>';
    }
    echo '<div class="alert alert-info" style="margin-top:12px;">Templates and sensitive documents remain inside department storage and are not exposed through superadmin screens.</div>';
    echo '</div>';

    renderLayoutEnd();
}, $lang, $config);
