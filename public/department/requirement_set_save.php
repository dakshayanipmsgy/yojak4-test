<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/department/requirement_sets.php');
    }
    require_csrf();
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $deptId = $user['deptId'] ?? '';
    ensure_department_env($deptId);
    require_department_permission($user, 'manage_requirements');

    $setId = trim($_POST['setId'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $visible = isset($_POST['visibleToContractors']);
    $itemsRaw = (string)($_POST['items'] ?? '');

    if ($name === '') {
        set_flash('error', 'Name is required.');
        redirect('/department/requirement_sets.php');
    }

    $items = [];
    foreach (array_filter(array_map('trim', preg_split('/\r?\n/', $itemsRaw) ?: [])) as $line) {
        $parts = array_map('trim', explode('|', $line));
        $title = $parts[0] ?? '';
        if ($title === '') {
            continue;
        }
        $items[] = [
            'title' => $title,
            'description' => $parts[1] ?? '',
            'category' => $parts[2] ?? '',
            'required' => !isset($parts[3]) || strtolower($parts[3]) !== 'optional',
        ];
    }

    if ($setId === '') {
        $set = create_requirement_set($deptId, $name, $items);
        $set['name'] = $name;
        $set['description'] = $description;
        $set['visibleToContractors'] = $visible;
        save_requirement_sets($deptId, array_merge(array_filter(load_requirement_sets($deptId), fn($s) => ($s['setId'] ?? '') !== $set['setId']), [$set]));
        append_department_audit($deptId, [
            'by' => $user['username'] ?? '',
            'action' => 'requirement_set_created',
            'meta' => ['setId' => $set['setId']],
        ]);
        set_flash('success', 'Requirement set created.');
    } else {
        $sets = load_requirement_sets($deptId);
        $updated = false;
        foreach ($sets as &$set) {
            if (($set['setId'] ?? '') === $setId) {
                $set['name'] = $name;
                $set['description'] = $description;
                $set['visibleToContractors'] = $visible;
                $set['items'] = [];
                foreach ($items as $item) {
                    $set['items'][] = [
                        'key' => 'REQITEM-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
                        'title' => $item['title'],
                        'description' => $item['description'],
                        'required' => (bool)($item['required'] ?? true),
                        'category' => $item['category'] ?? '',
                    ];
                }
                $set['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
                $updated = true;
                break;
            }
        }
        unset($set);
        if ($updated) {
            save_requirement_sets($deptId, $sets);
            append_department_audit($deptId, [
                'by' => $user['username'] ?? '',
                'action' => 'requirement_set_updated',
                'meta' => ['setId' => $setId],
            ]);
            set_flash('success', 'Requirement set updated.');
        } else {
            set_flash('error', 'Requirement set not found.');
        }
    }

    redirect('/department/requirement_sets.php');
});
