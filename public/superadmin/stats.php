<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('stats_view');
    if (($actor['type'] ?? '') === 'superadmin' && !empty($actor['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $departments = departments_index();
    $contractors = contractors_index();
    $pendingContractors = list_pending_contractors();
    $employees = staff_employee_index();
    $resetRequests = load_all_password_reset_requests();

    $cards = [
        ['label' => 'Departments', 'value' => count($departments)],
        ['label' => 'Active Departments', 'value' => count(array_filter($departments, fn($d) => ($d['status'] ?? '') === 'active'))],
        ['label' => 'Approved Contractors', 'value' => count($contractors)],
        ['label' => 'Pending Contractors', 'value' => count($pendingContractors)],
        ['label' => 'Employees', 'value' => count($employees)],
        ['label' => 'Pending Reset Requests', 'value' => count(array_filter($resetRequests, fn($r) => ($r['status'] ?? '') === 'pending'))],
    ];

    $title = get_app_config()['appName'] . ' | Platform Stats';
    render_layout($title, function () use ($cards) {
        ?>
        <div class="card">
            <h2 style="margin-bottom:6px;"><?= sanitize('Platform Snapshot (Counts Only)'); ?></h2>
            <p class="muted" style="margin-top:0;"><?= sanitize('Superadmin and auditors see counts only. No content or document access.'); ?></p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:12px;">
                <?php foreach ($cards as $card): ?>
                    <div class="card" style="border:1px solid var(--border);">
                        <div class="muted" style="text-transform:uppercase;font-size:12px;letter-spacing:0.06em;"><?= sanitize($card['label']); ?></div>
                        <div style="font-size:28px;font-weight:800;margin-top:6px;"><?= sanitize((string)$card['value']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
