<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/department/tenders.php');
    }
    require_csrf();
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $deptId = $user['deptId'] ?? '';
    ensure_department_env($deptId);
    require_department_permission($user, 'manage_tenders');

    $id = trim($_POST['id'] ?? '');
    $tender = load_department_tender($deptId, $id);
    if (!$tender) {
        set_flash('error', 'Tender not found.');
        redirect('/department/tenders.php');
    }

    $title = trim($_POST['title'] ?? '');
    $publish = trim($_POST['publish'] ?? '');
    $submission = trim($_POST['submission'] ?? '');
    $opening = trim($_POST['opening'] ?? '');
    $completionMonths = trim($_POST['completionMonths'] ?? '');
    $paymentSteps = array_filter(array_map('trim', preg_split('/\r?\n/', (string)($_POST['paymentSteps'] ?? '')) ?: []));
    $emdText = trim($_POST['emdText'] ?? '');
    $sdPercent = trim($_POST['sdPercent'] ?? '');
    $pgPercent = trim($_POST['pgPercent'] ?? '');
    $reqIds = $_POST['requirementSetIds'] ?? [];
    $requirementSets = load_requirement_sets($deptId);
    $validReqs = [];
    foreach ($requirementSets as $set) {
        if (in_array($set['setId'] ?? '', $reqIds, true)) {
            $validReqs[] = $set['setId'];
        }
    }

    $tender['title'] = $title !== '' ? $title : ($tender['title'] ?? '');
    $tender['tenderNumberFormat'] = [
        'prefix' => trim($_POST['prefix'] ?? ($tender['tenderNumberFormat']['prefix'] ?? '')),
        'sequence' => max(1, (int)($_POST['sequence'] ?? ($tender['tenderNumberFormat']['sequence'] ?? 1))),
        'postfix' => trim($_POST['postfix'] ?? ($tender['tenderNumberFormat']['postfix'] ?? '')),
    ];
    $tender['dates'] = [
        'publish' => $publish,
        'submission' => $submission,
        'opening' => $opening,
    ];
    $tender['completionMonths'] = $completionMonths !== '' ? (int)$completionMonths : null;
    $tender['paymentSteps'] = array_values($paymentSteps);
    $tender['emdText'] = $emdText;
    $tender['sdPercent'] = $sdPercent;
    $tender['pgPercent'] = $pgPercent;
    $tender['requirementSetIds'] = $validReqs;
    $tender['updatedAt'] = now_kolkata()->format(DateTime::ATOM);

    save_department_tender($deptId, $tender);
    append_department_audit($deptId, [
        'by' => $user['username'] ?? '',
        'action' => 'tender_updated',
        'meta' => ['id' => $id],
    ]);
    set_flash('success', 'Tender updated.');
    redirect('/department/tender_view.php?id=' . urlencode($id));
});
