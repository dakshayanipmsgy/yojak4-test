<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/workorders.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_workorder_env($yojId);

    $woId = trim($_POST['id'] ?? '');
    $mode = trim($_POST['mode'] ?? 'save_details');
    $workorder = $woId !== '' ? load_workorder($yojId, $woId) : null;

    if (!$workorder || ($workorder['yojId'] ?? '') !== $yojId) {
        render_error_page('Workorder not found.');
        return;
    }

    $errors = [];

    if ($mode === 'save_details') {
        $title = trim($_POST['title'] ?? ($workorder['title'] ?? ''));
        $dept = trim($_POST['deptName'] ?? '');
        $location = trim($_POST['projectLocation'] ?? '');

        if ($title === '') {
            $errors[] = 'Title is required.';
        }

        if ($errors) {
            set_flash('error', implode(' ', $errors));
            redirect('/contractor/workorder_view.php?id=' . urlencode($woId));
            return;
        }

        $workorder['title'] = $title;
        $workorder['deptName'] = $dept;
        $workorder['projectLocation'] = $location;
    } elseif ($mode === 'save_obligations') {
        $obligations = [];
        $input = $_POST['obligations'] ?? [];
        foreach ($input as $payload) {
            $title = trim((string)($payload['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $due = normalize_workorder_due(isset($payload['dueAt']) ? (string)$payload['dueAt'] : '');
            $obligations[] = [
                'itemId' => $payload['itemId'] ?? 'WCHK-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)),
                'title' => $title,
                'description' => trim((string)($payload['description'] ?? '')),
                'dueAt' => $due,
                'status' => in_array($payload['status'] ?? '', ['pending','in_progress','done'], true) ? $payload['status'] : 'pending',
            ];
        }
        $removeIds = $_POST['obligations_remove'] ?? [];
        if (is_array($removeIds) && $removeIds) {
            $obligations = array_values(array_filter($obligations, function ($item) use ($removeIds) {
                return !in_array($item['itemId'] ?? '', $removeIds, true);
            }));
        }
        $new = $_POST['new_obligations'] ?? [];
        foreach ($new as $entry) {
            $title = trim((string)($entry['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $obligations[] = [
                'itemId' => 'WCHK-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)),
                'title' => $title,
                'description' => trim((string)($entry['description'] ?? '')),
                'dueAt' => normalize_workorder_due(isset($entry['dueAt']) ? (string)$entry['dueAt'] : ''),
                'status' => 'pending',
            ];
            if (count($obligations) >= 300) {
                break;
            }
        }

        if (count($obligations) > 300) {
            $errors[] = 'Obligations limit reached (300 items max).';
        }

        if ($errors) {
            set_flash('error', implode(' ', $errors));
            redirect('/contractor/workorder_view.php?id=' . urlencode($woId));
            return;
        }

        $workorder['obligationsChecklist'] = $obligations;
    } elseif ($mode === 'save_docs') {
        $docs = [];
        $input = $_POST['requiredDocs'] ?? [];
        foreach ($input as $entry) {
            $name = trim((string)($entry['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $docs[] = [
                'name' => $name,
                'notes' => trim((string)($entry['notes'] ?? '')),
            ];
        }
        $remove = $_POST['requiredDocs_remove'] ?? [];
        if (is_array($remove) && $remove) {
            foreach ($remove as $idx) {
                unset($docs[(int)$idx]);
            }
            $docs = array_values($docs);
        }
        $newDocs = $_POST['new_requiredDocs'] ?? [];
        foreach ($newDocs as $entry) {
            $name = trim((string)($entry['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $docs[] = [
                'name' => $name,
                'notes' => trim((string)($entry['notes'] ?? '')),
            ];
            if (count($docs) >= 300) {
                break;
            }
        }
        $workorder['requiredDocs'] = array_slice($docs, 0, 300);
    } elseif ($mode === 'save_timeline') {
        $timeline = [];
        $input = $_POST['timeline'] ?? [];
        foreach ($input as $entry) {
            $milestone = trim((string)($entry['milestone'] ?? ''));
            if ($milestone === '') {
                continue;
            }
            $timeline[] = [
                'milestone' => $milestone,
                'dueAt' => normalize_workorder_due(isset($entry['dueAt']) ? (string)$entry['dueAt'] : ''),
            ];
        }
        $remove = $_POST['timeline_remove'] ?? [];
        if (is_array($remove) && $remove) {
            foreach ($remove as $idx) {
                unset($timeline[(int)$idx]);
            }
            $timeline = array_values($timeline);
        }
        $newTimeline = $_POST['new_timeline'] ?? [];
        foreach ($newTimeline as $entry) {
            $milestone = trim((string)($entry['milestone'] ?? ''));
            if ($milestone === '') {
                continue;
            }
            $timeline[] = [
                'milestone' => $milestone,
                'dueAt' => normalize_workorder_due(isset($entry['dueAt']) ? (string)$entry['dueAt'] : ''),
            ];
            if (count($timeline) >= 300) {
                break;
            }
        }
        $workorder['timeline'] = array_slice($timeline, 0, 300);
    }

    $workorder['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    save_workorder($workorder);

    set_flash('success', 'Workorder updated.');
    redirect('/contractor/workorder_view.php?id=' . urlencode($woId));
});
