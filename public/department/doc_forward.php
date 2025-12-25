<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/department/docs_outbox.php');
    }
    require_csrf();
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $deptId = $user['deptId'] ?? '';
    ensure_department_env($deptId);
    require_department_permission($user, 'docs_workflow');

    $docId = trim($_POST['docId'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $toUser = trim($_POST['toUser'] ?? '');
    $notes = trim($_POST['notesGreen'] ?? '');

    $now = now_kolkata()->format(DateTime::ATOM);
    if ($docId !== '') {
        $doc = load_department_doc($deptId, $docId);
    } else {
        $doc = null;
    }

    if (!$doc) {
        $docId = 'DOC-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $doc = [
            'docId' => $docId,
            'title' => $title !== '' ? $title : 'Document',
            'status' => 'outbox',
            'fromUser' => $user['username'] ?? '',
            'toUser' => $toUser,
            'notesGreen' => $notes,
            'auditTrail' => [],
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
    }

    if ($title !== '') {
        $doc['title'] = $title;
    }
    $doc['fromUser'] = $user['username'] ?? '';
    $doc['toUser'] = $toUser;
    $doc['notesGreen'] = $notes;
    $doc['status'] = 'outbox';
    if (!isset($doc['auditTrail']) || !is_array($doc['auditTrail'])) {
        $doc['auditTrail'] = [];
    }
    $doc['auditTrail'][] = [
        'at' => $now,
        'by' => $user['username'] ?? '',
        'action' => 'forwarded',
        'meta' => ['to' => $toUser],
    ];
    $doc['updatedAt'] = $now;

    save_department_doc($deptId, $doc);
    append_department_audit($deptId, [
        'by' => $user['username'] ?? '',
        'action' => 'doc_forwarded',
        'meta' => ['docId' => $docId, 'to' => $toUser],
    ]);

    set_flash('success', 'Document forwarded.');
    redirect('/department/doc_view.php?id=' . urlencode($docId));
});
