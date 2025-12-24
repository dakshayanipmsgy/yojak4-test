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
        echo '<div class="card"><div class="friendly-error"><h2>Department not found</h2><p class="text-muted">Check the identifier and try again.</p><a class="btn" href="/superadmin/departments.php">Back to registry</a></div></div>';
        renderLayoutEnd();
    }, $lang, $config);
    exit;
}

$activeAdmin = activeDepartmentAdmin($deptId);
$flashSuccess = getFlash('success');

safePage(function () use ($lang, $config, $user, $department, $activeAdmin, $flashSuccess) {
    renderLayoutStart('Department', $lang, $config, $user, true);

    echo '<div class="card">';
    echo '<div class="section-title"><div><h2>' . escape($department['nameEn']) . ' (' . escape($department['deptId']) . ')</h2><p class="card-subtitle">Registry-only view. Department content remains private.</p></div>';
    echo '<a class="ghost-btn" href="/superadmin/departments.php">Back to registry</a></div>';

    if ($flashSuccess) {
        echo '<div class="alert alert-info">' . escape($flashSuccess) . '</div>';
    }

    echo '<div class="grid">';
    echo '<div class="stack"><p class="eyebrow">Name (English)</p><p>' . escape($department['nameEn'] ?? '') . '</p></div>';
    echo '<div class="stack"><p class="eyebrow">नाम (हिंदी)</p><p>' . escape($department['nameHi'] ?? '') . '</p></div>';
    echo '<div class="stack"><p class="eyebrow">Status</p><span class="pill">' . escape(ucfirst($department['status'] ?? 'active')) . '</span></div>';
    echo '<div class="stack"><p class="eyebrow">Created</p><p>' . escape(formatDateTime($department['createdAt'] ?? null)) . '</p></div>';
    echo '<div class="stack"><p class="eyebrow">Updated</p><p>' . escape(formatDateTime($department['updatedAt'] ?? null)) . '</p></div>';
    echo '</div>';

    echo '<div class="grid">';
    echo '<div class="stack"><p class="eyebrow">Address</p><p>' . escape($department['address'] ?? '-') . '</p></div>';
    echo '<div class="stack"><p class="eyebrow">Contact Email</p><p>' . escape($department['contactEmail'] ?? '-') . '</p></div>';
    echo '</div>';

    echo '<hr style="border:1px solid var(--border); margin:16px 0;">';
    echo '<div class="section-title"><div><h3>Department Admin</h3><p class="card-subtitle">Only one active department admin is permitted.</p></div>';
    echo '<a class="btn" style="width:auto" href="/superadmin/department_admin_create.php?deptId=' . escape($department['deptId']) . '">' . ($activeAdmin ? 'Replace Admin' : 'Create Admin') . '</a></div>';

    if ($activeAdmin) {
        echo '<div class="grid">';
        echo '<div class="stack"><p class="eyebrow">Admin User ID</p><p>' . escape($activeAdmin['fullUserId'] ?? '') . '</p></div>';
        echo '<div class="stack"><p class="eyebrow">Display Name</p><p>' . escape($activeAdmin['displayName'] ?? '') . '</p></div>';
        echo '<div class="stack"><p class="eyebrow">Last Login</p><p>' . escape(formatDateTime($activeAdmin['lastLoginAt'] ?? null)) . '</p></div>';
        echo '<div class="stack"><p class="eyebrow">Status</p><span class="pill">' . escape(ucfirst($activeAdmin['status'] ?? 'active')) . '</span></div>';
        echo '</div>';
    } else {
        echo '<p class="text-muted">No admin created yet.</p>';
    }

    if (!empty($department['adminHistory'])) {
        echo '<div class="card" style="margin-top:14px;"><h4>Archived Admins</h4><ul class="text-muted">';
        foreach (array_reverse($department['adminHistory']) as $archivedAdminId) {
            echo '<li>' . escape($archivedAdminId) . '</li>';
        }
        echo '</ul></div>';
    }

    echo '<div class="alert alert-info" style="margin-top:12px;">Superadmin cannot view or open department documents/templates. Boundary enforced at routing and storage level.</div>';
    echo '</div>';

    renderLayoutEnd();
}, $lang, $config);
