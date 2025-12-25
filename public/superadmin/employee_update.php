<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        render_error_page('Unsupported request.');
        return;
    }

    require_csrf();

    $action = $_POST['action'] ?? '';
    $empId = trim($_POST['empId'] ?? '');
    $role = $_POST['role'] ?? 'support';
    $permissions = $_POST['permissions'] ?? [];

    if ($empId === '') {
        set_flash('error', 'Employee id missing.');
        redirect('/superadmin/employees.php');
    }

    if (!load_employee($empId)) {
        set_flash('error', 'Employee not found.');
        redirect('/superadmin/employees.php');
    }

    try {
        if ($action === 'suspend') {
            update_employee_status($empId, 'suspended');
            set_flash('success', 'Employee suspended.');
        } elseif ($action === 'activate') {
            update_employee_status($empId, 'active');
            set_flash('success', 'Employee activated.');
        } elseif ($action === 'change_role') {
            if (!in_array($role, ['support', 'content', 'approvals', 'auditor'], true)) {
                throw new RuntimeException('Invalid role.');
            }
            update_employee_role_permissions($empId, $role, $permissions ?: employee_default_permissions($role));
            set_flash('success', 'Employee role updated.');
        } else {
            set_flash('error', 'Unknown action.');
        }
    } catch (Throwable $e) {
        set_flash('error', 'Unable to update employee: ' . $e->getMessage());
    }

    redirect('/superadmin/employees.php');
});
