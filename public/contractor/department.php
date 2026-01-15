<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'] ?? '';
    $deptId = normalize_dept_id(trim($_GET['deptId'] ?? ''));
    if (!is_valid_dept_id($deptId)) {
        render_error_page('Invalid department.');
        return;
    }

    $link = load_contractor_link($yojId, $deptId);
    if (!$link) {
        render_error_page('You are not linked to this department.');
        return;
    }
    if (($link['status'] ?? '') !== 'active') {
        $title = get_app_config()['appName'] . ' | Department';
        render_layout($title, function () use ($link, $deptId) {
            ?>
            <div class="card">
                <h2 style="margin:0 0 6px 0;"><?= sanitize('Access blocked'); ?></h2>
                <p class="muted"><?= sanitize('Your link to this department is currently ' . ($link['status'] ?? 'inactive') . '.'); ?></p>
                <a class="btn secondary" href="/contractor/departments.php"><?= sanitize('Back to Departments'); ?></a>
            </div>
            <?php
        });
        return;
    }

    $department = load_department($deptId);
    if (!$department) {
        render_error_page('Department not found.');
        return;
    }
    $tenders = load_department_published_tenders($deptId);

    $title = get_app_config()['appName'] . ' | ' . ($department['nameEn'] ?? 'Department');
    render_layout($title, function () use ($department, $link, $tenders) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0 0 6px 0;"><?= sanitize($department['nameEn'] ?? 'Department'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize(strtoupper($department['deptId'] ?? '') . ($department['district'] ? ' • ' . $department['district'] : '')); ?></p>
                </div>
                <a class="btn secondary" href="/contractor/departments.php"><?= sanitize('All Departments'); ?></a>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:10px;">
                <span class="pill success"><?= sanitize('Link Active'); ?></span>
                <span class="pill"><?= sanitize('Dept Contractor ID: ' . ($link['deptContractorId'] ?? '')); ?></span>
            </div>
            <div style="margin-top:14px;display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
                <div class="card" style="background:var(--surface-2);border:1px solid var(--border);">
                    <h3 style="margin:0 0 6px 0;"><?= sanitize('Overview'); ?></h3>
                    <p class="muted" style="margin:0 0 6px 0;"><?= sanitize($department['address'] ?? ''); ?></p>
                    <?php if (!empty($department['contactEmail'])): ?>
                        <p style="margin:0 0 4px 0;"><?= sanitize('Email: ' . ($department['contactEmail'] ?? '')); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($department['contactPhone'])): ?>
                        <p style="margin:0;"><?= sanitize('Phone: ' . ($department['contactPhone'] ?? '')); ?></p>
                    <?php endif; ?>
                </div>
                <div class="card" style="background:var(--surface-2);border:1px solid var(--border);">
                    <h3 style="margin:0 0 6px 0;"><?= sanitize('Workorders'); ?></h3>
                    <p class="muted" style="margin:0;">No assigned workorders yet.</p>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0 0 4px 0;"><?= sanitize('Published Tenders'); ?></h3>
                    <p class="muted" style="margin:0;"><?= sanitize('Only tenders shared with linked contractors.'); ?></p>
                </div>
                <a class="btn secondary" href="/contractor/department_tenders.php?deptId=<?= urlencode($department['deptId'] ?? ''); ?>"><?= sanitize('Open List'); ?></a>
            </div>
            <?php if (!$tenders): ?>
                <p class="muted" style="margin-top:10px;"><?= sanitize('No published tenders yet.'); ?></p>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-top:12px;">
                    <?php foreach ($tenders as $tender): ?>
                        <div class="card" style="background:var(--surface-2);border:1px solid var(--border);">
                            <h4 style="margin:0 0 6px 0;"><?= sanitize($tender['title'] ?? $tender['id']); ?></h4>
                            <p class="muted" style="margin:0 0 6px 0;"><?= sanitize($tender['id'] ?? ''); ?></p>
                            <p style="margin:0 0 4px 0;"><?= sanitize('Publish: ' . ($tender['publishDate'] ?? '')); ?></p>
                            <p style="margin:0 0 4px 0;"><?= sanitize('Submission: ' . ($tender['submissionDate'] ?? '')); ?></p>
                            <p style="margin:0;"><?= sanitize('Opening: ' . ($tender['openingDate'] ?? '')); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    });
});
