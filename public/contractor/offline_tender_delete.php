<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/offline_tenders.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_offline_tender_env($yojId);

    $offtdId = trim($_POST['id'] ?? '');
    $tender = $offtdId !== '' ? load_offline_tender($yojId, $offtdId) : null;
    if (!$tender || ($tender['yojId'] ?? '') !== $yojId) {
        render_error_page('Tender not found.');
        return;
    }

    $tender['deletedAt'] = now_kolkata()->format(DateTime::ATOM);
    $tender['status'] = 'archived';
    $tender['updatedAt'] = $tender['deletedAt'];

    save_offline_tender($tender);
    set_flash('success', 'Tender archived.');

    redirect('/contractor/offline_tenders.php');
});
