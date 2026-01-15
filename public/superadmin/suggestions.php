<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $roleFilter = (string)($_GET['role'] ?? '');
    $statusFilter = (string)($_GET['status'] ?? '');
    $allowedRoles = ['', 'contractor', 'department'];
    $allowedStatuses = ['', 'new', 'reviewed', 'planned', 'done'];
    if (!in_array($roleFilter, $allowedRoles, true)) {
        $roleFilter = '';
    }
    if (!in_array($statusFilter, $allowedStatuses, true)) {
        $statusFilter = '';
    }

    $suggestions = suggestion_list();
    $filtered = suggestions_filter($suggestions, $roleFilter, $statusFilter);

    $selectedId = (string)($_GET['id'] ?? '');
    $selected = $selectedId !== '' ? suggestion_find($selectedId) : null;

    $title = get_app_config()['appName'] . ' | Suggestions';
    render_layout($title, function () use ($filtered, $roleFilter, $statusFilter, $selected) {
        $formatDate = function (?string $value): string {
            if (!$value) {
                return '-';
            }
            try {
                return (new DateTimeImmutable($value, new DateTimeZone('Asia/Kolkata')))->format('d M Y, H:i');
            } catch (Throwable $e) {
                return $value;
            }
        };
        ?>
        <style>
            .suggestions-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.95rem;
            }
            .suggestions-table th,
            .suggestions-table td {
                text-align: left;
                padding: 10px 12px;
                border-bottom: 1px solid #e2e8f0;
                vertical-align: top;
            }
            .suggestions-table th {
                color: #0f172a;
                font-weight: 600;
            }
            .suggestions-filters {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                align-items: center;
            }
            .suggestions-filters select {
                padding: 8px 10px;
                border-radius: 8px;
                border: 1px solid #e2e8f0;
            }
            @media (max-width: 720px) {
                .suggestions-table,
                .suggestions-table thead,
                .suggestions-table tbody,
                .suggestions-table th,
                .suggestions-table td,
                .suggestions-table tr {
                    display: block;
                }
                .suggestions-table tr {
                    margin-bottom: 12px;
                    border: 1px solid #e2e8f0;
                    border-radius: 10px;
                    padding: 8px;
                }
                .suggestions-table td {
                    border-bottom: none;
                    padding: 6px 8px;
                }
                .suggestions-table th {
                    display: none;
                }
            }
        </style>
        <div class="card" style="margin-bottom:16px;">
            <h2 style="margin-bottom:6px;">Suggestion Inbox</h2>
            <p class="muted" style="margin:0;">Review feedback submitted by contractors and departments.</p>
        </div>
        <div class="card" style="margin-bottom:16px;">
            <form class="suggestions-filters" method="get" action="/superadmin/suggestions.php">
                <label>
                    Role
                    <select name="role">
                        <option value="" <?= $roleFilter === '' ? 'selected' : ''; ?>>All</option>
                        <option value="contractor" <?= $roleFilter === 'contractor' ? 'selected' : ''; ?>>Contractor</option>
                        <option value="department" <?= $roleFilter === 'department' ? 'selected' : ''; ?>>Department</option>
                    </select>
                </label>
                <label>
                    Status
                    <select name="status">
                        <option value="" <?= $statusFilter === '' ? 'selected' : ''; ?>>All</option>
                        <option value="new" <?= $statusFilter === 'new' ? 'selected' : ''; ?>>New</option>
                        <option value="reviewed" <?= $statusFilter === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                        <option value="planned" <?= $statusFilter === 'planned' ? 'selected' : ''; ?>>Planned</option>
                        <option value="done" <?= $statusFilter === 'done' ? 'selected' : ''; ?>>Done</option>
                    </select>
                </label>
                <button class="btn" type="submit">Apply Filters</button>
                <a class="btn secondary" href="/superadmin/suggestions.php">Reset</a>
            </form>
        </div>
        <div class="card" style="overflow:hidden;">
            <?php if (!$filtered): ?>
                <p class="muted" style="margin:0;">No suggestions found for the selected filters.</p>
            <?php else: ?>
                <table class="suggestions-table">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Role</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtered as $item): ?>
                            <tr>
                                <td><?= sanitize($formatDate($item['createdAt'] ?? null)); ?></td>
                                <td><?= sanitize($item['createdBy']['role'] ?? '-'); ?></td>
                                <td><?= sanitize($item['title'] ?? '-'); ?></td>
                                <td><?= sanitize($item['category'] ?? '-'); ?></td>
                                <td><?= sanitize($item['status'] ?? 'new'); ?></td>
                                <td><a class="btn secondary" href="/superadmin/suggestions.php?id=<?= sanitize($item['id'] ?? ''); ?>">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php if ($selected): ?>
            <div class="card" style="margin-top:16px;">
                <h3 style="margin-bottom:8px;">Suggestion Details</h3>
                <div class="muted" style="margin-bottom:12px;">ID: <?= sanitize($selected['id'] ?? ''); ?></div>
                <div style="display:grid;gap:8px;">
                    <div><strong>Title:</strong> <?= sanitize($selected['title'] ?? ''); ?></div>
                    <div><strong>Category:</strong> <?= sanitize($selected['category'] ?? ''); ?></div>
                    <div><strong>Status:</strong> <?= sanitize($selected['status'] ?? 'new'); ?></div>
                    <div><strong>Role:</strong> <?= sanitize($selected['createdBy']['role'] ?? ''); ?></div>
                    <div><strong>User:</strong> <?= sanitize($selected['createdBy']['userId'] ?? ''); ?></div>
                    <div><strong>Department:</strong> <?= sanitize($selected['createdBy']['deptId'] ?? ''); ?></div>
                    <div><strong>Contractor:</strong> <?= sanitize($selected['createdBy']['yojId'] ?? ''); ?></div>
                    <div><strong>Page URL:</strong> <?= sanitize($selected['pageUrl'] ?? ''); ?></div>
                    <div><strong>Device:</strong> <?= sanitize($selected['deviceHint'] ?? ''); ?></div>
                    <div><strong>Message:</strong><br><?= nl2br(sanitize($selected['message'] ?? '')); ?></div>
                </div>
                <?php if (($selected['status'] ?? 'new') !== 'reviewed'): ?>
                    <form method="post" action="/superadmin/suggestions_update.php" style="margin-top:12px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="id" value="<?= sanitize($selected['id'] ?? ''); ?>">
                        <input type="hidden" name="status" value="reviewed">
                        <button class="btn secondary" type="submit">Mark as Reviewed</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php
    });
});
