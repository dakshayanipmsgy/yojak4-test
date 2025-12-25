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
    if ($docId === '') {
        set_flash('error', 'Document not found.');
        redirect('/department/docs_outbox.php');
    }
    $doc = load_department_doc($deptId, $docId);
    if (!$doc) {
        set_flash('error', 'Document missing.');
        redirect('/department/docs_outbox.php');
    }
    if (!isset($doc['auditTrail']) || !is_array($doc['auditTrail'])) {
        $doc['auditTrail'] = [];
    }
    $now = now_kolkata()->format(DateTime::ATOM);
    $doc['status'] = 'archived';
    $doc['auditTrail'][] = [
        'at' => $now,
        'by' => $user['username'] ?? '',
        'action' => 'archived',
        'meta' => [],
    ];
    $doc['updatedAt'] = $now;
    save_department_doc($deptId, $doc);
    append_department_audit($deptId, [
        'by' => $user['username'] ?? '',
        'action' => 'doc_archived',
        'meta' => ['docId' => $docId],
    ]);
    set_flash('success', 'Document archived.');
    redirect('/department/doc_view.php?id=' . urlencode($docId));
});
