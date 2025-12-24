<?php
require_once __DIR__ . '/../../../bootstrap.php';

$user = requireAuth('superadmin');
requireNoForceReset($user);

$departments = ensureDepartmentsIndex();
$query = trim($_GET['q'] ?? '');
if ($query !== '') {
    $departments = array_values(array_filter($departments, function ($dept) use ($query) {
        $haystack = strtolower(($dept['deptId'] ?? '') . ' ' . ($dept['nameEn'] ?? '') . ' ' . ($dept['nameHi'] ?? ''));
        return strpos($haystack, strtolower($query)) !== false;
    }));
}

safePage(function () use ($lang, $config, $user, $departments, $query) {
    renderLayoutStart('Departments', $lang, $config, $user, true);

    echo '<div class="card">';
    echo '<div class="section-title"><div><h2>Departments</h2><p class="card-subtitle">Registry only. Department content stays isolated.</p></div>';
    echo '<div class="row">';
    echo '<form method="GET" class="row" action="">';
    echo '<div class="input-group" style="min-width:240px"><label for="q">Search</label><input id="q" name="q" value="' . escape($query) . '" placeholder="Search dept id or name"></div>';
    echo '<div class="form-actions" style="margin:0"><button class="ghost-btn" type="submit">Filter</button></div>';
    echo '</form>';
    echo '<a class="btn" style="width:auto" href="/superadmin/department_create.php">Create Department</a>';
    echo '</div></div>';

    if (empty($departments)) {
        echo '<p class="text-muted">No departments yet.</p>';
    } else {
        echo '<div class="table-wrap"><table class="table">';
        echo '<thead><tr><th>Dept ID</th><th>Name (EN)</th><th>Name (HI)</th><th>Status</th><th>Created</th></tr></thead><tbody>';
        foreach ($departments as $dept) {
            $status = $dept['status'] ?? 'active';
            $pillClass = $status === 'active' ? 'pill' : 'pill muted';
            echo '<tr>';
            echo '<td><a href="/superadmin/department_view.php?deptId=' . escape($dept['deptId']) . '">' . escape($dept['deptId']) . '</a></td>';
            echo '<td>' . escape($dept['nameEn'] ?? '') . '</td>';
            echo '<td>' . escape($dept['nameHi'] ?? '') . '</td>';
            echo '<td><span class="' . $pillClass . '">' . escape(ucfirst($status)) . '</span></td>';
            echo '<td>' . escape(formatDateTime($dept['createdAt'] ?? null)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    echo '<div class="alert alert-info" style="margin-top:12px;">Superadmin boundary: document/template contents remain off-limits and are not linked from this registry.</div>';
    echo '</div>';

    renderLayoutEnd();
}, $lang, $config);
