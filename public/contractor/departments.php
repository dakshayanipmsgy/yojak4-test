<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'] ?? '';
    $contractor = $yojId !== '' ? load_contractor($yojId) : null;
    if (!$contractor || ($contractor['status'] ?? '') !== 'approved') {
        render_error_page('Contractor account not approved.');
        return;
    }
    ensure_contractor_links_env($yojId);
    ensure_contractor_notifications_env($yojId);

    $search = strtolower(trim($_GET['q'] ?? ''));
    $departments = [];
    $myDepartments = [];

    foreach (departments_index() as $entry) {
        $deptId = $entry['deptId'] ?? '';
        if ($deptId === '') {
            continue;
        }
        $department = load_department($deptId);
        if (!$department || ($department['status'] ?? '') !== 'active') {
            continue;
        }
        $name = $department['nameEn'] ?? ($entry['nameEn'] ?? $deptId);
        $district = $department['district'] ?? '';
        $haystack = strtolower($deptId . ' ' . $name . ' ' . $district);
        if ($search !== '' && !str_contains($haystack, $search)) {
            continue;
        }

        $latestRequest = null;
        $requests = load_department_contractor_requests($deptId);
        usort($requests, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
        foreach ($requests as $req) {
            if (($req['yojId'] ?? '') === $yojId) {
                $latestRequest = $req;
                break;
            }
        }

        $link = load_contractor_link($yojId, $deptId);
        if ($link || $latestRequest) {
            $myDepartments[$deptId] = [
                'deptId' => $deptId,
                'name' => $name,
                'district' => $district,
                'status' => $link ? ($link['status'] ?? 'pending') : ($latestRequest['status'] ?? 'pending'),
                'link' => $link,
                'request' => $latestRequest,
            ];
        }

        if (!empty($department['visibleToContractors'])) {
            $departments[] = [
                'deptId' => $deptId,
                'name' => $name,
                'district' => $district,
                'acceptingLinkRequests' => !empty($department['acceptingLinkRequests']),
                'latestRequest' => $latestRequest,
                'link' => $link,
            ];
        }
    }

    usort($departments, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));
    ksort($myDepartments);

    $title = get_app_config()['appName'] . ' | Departments';
    render_layout($title, function () use ($departments, $myDepartments) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0 0 6px 0;"><?= sanitize('My Departments'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Linked or requested departments with status.'); ?></p>
                </div>
                <a class="btn secondary" href="/contractor/dashboard.php"><?= sanitize('Dashboard'); ?></a>
            </div>
            <?php if (!$myDepartments): ?>
                <p class="muted" style="margin-top:12px;"><?= sanitize('No department links yet. Request access to get started.'); ?></p>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-top:12px;">
                    <?php foreach ($myDepartments as $dept): ?>
                        <div class="card" style="padding:14px;background:#111820;border:1px solid #1f2a37;">
                            <h3 style="margin:0 0 6px 0;"><?= sanitize($dept['name']); ?></h3>
                            <p class="muted" style="margin:0 0 8px 0;"><?= sanitize(strtoupper($dept['deptId']) . ($dept['district'] ? ' • ' . $dept['district'] : '')); ?></p>
                            <?php
                            $status = $dept['status'] ?? 'pending';
                            $labelClass = 'pill';
                            $labelText = ucfirst($status);
                            if ($status === 'active') {
                                $labelClass .= ' success';
                                $labelText = 'Active';
                            } elseif ($status === 'suspended') {
                                $labelText = 'Suspended';
                            } elseif ($status === 'revoked') {
                                $labelText = 'Revoked';
                            } else {
                                $labelText = 'Pending';
                            }
                            ?>
                            <span class="<?= sanitize($labelClass); ?>"><?= sanitize($labelText); ?></span>
                            <div class="buttons" style="margin-top:10px;">
                                <?php if (($dept['link']['status'] ?? '') === 'active'): ?>
                                    <a class="btn" href="/contractor/department.php?deptId=<?= urlencode($dept['deptId']); ?>"><?= sanitize('Open Workspace'); ?></a>
                                <?php else: ?>
                                    <span class="btn secondary" style="cursor:default;"><?= sanitize('Awaiting Approval'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card" style="margin-top:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0 0 6px 0;"><?= sanitize('Find Departments'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Search departments and request a link.'); ?></p>
                </div>
                <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;">
                    <input type="search" name="q" placeholder="Search by name, district or ID" value="<?= sanitize($_GET['q'] ?? ''); ?>" style="min-width:240px;">
                    <button class="btn secondary" type="submit"><?= sanitize('Search'); ?></button>
                </form>
            </div>
            <?php if (!$departments): ?>
                <p class="muted" style="margin-top:12px;"><?= sanitize('No departments available right now.'); ?></p>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin-top:12px;">
                    <?php foreach ($departments as $dept): ?>
                        <div class="card" style="padding:14px;background:#111820;border:1px solid #1f2a37;">
                            <h3 style="margin:0 0 6px 0;"><?= sanitize($dept['name']); ?></h3>
                            <p class="muted" style="margin:0 0 8px 0;"><?= sanitize(strtoupper($dept['deptId']) . ($dept['district'] ? ' • ' . $dept['district'] : '')); ?></p>
                            <?php if ($dept['link']): ?>
                                <span class="pill success"><?= sanitize('Linked'); ?></span>
                            <?php elseif ($dept['latestRequest'] && ($dept['latestRequest']['status'] ?? '') === 'pending'): ?>
                                <span class="pill"><?= sanitize('Request Pending'); ?></span>
                            <?php elseif (empty($dept['acceptingLinkRequests'])): ?>
                                <span class="pill"><?= sanitize('Not accepting requests'); ?></span>
                            <?php endif; ?>
                            <form method="post" action="/contractor/dept_request_link.php" style="margin-top:10px;display:grid;gap:8px;">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="deptId" value="<?= sanitize($dept['deptId']); ?>">
                                <textarea name="message" rows="2" placeholder="Short message (optional)" style="width:100%;background:#0d1117;color:#e6edf3;border:1px solid #30363d;border-radius:10px;padding:10px;"></textarea>
                                <button class="btn" type="submit" <?= ($dept['link'] || ($dept['latestRequest']['status'] ?? '') === 'pending' || empty($dept['acceptingLinkRequests'])) ? 'disabled' : ''; ?>>
                                    <?= sanitize($dept['link'] ? 'Linked' : 'Request Access'); ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    });
});
