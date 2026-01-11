<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/offline_tenders.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_offline_tender_env($yojId);
    ensure_assisted_v2_env();

    $offtdId = trim((string)($_POST['offtdId'] ?? ''));
    $tender = $offtdId !== '' ? load_offline_tender($yojId, $offtdId) : null;
    if (!$tender || ($tender['yojId'] ?? '') !== $yojId) {
        render_error_page('Tender not found.');
        return;
    }

    try {
        assisted_v2_create_request($yojId, $offtdId, $tender);
        set_flash('success', 'Assisted pack requested. Our team will process your tender PDF.');
    } catch (Throwable $e) {
        set_flash('error', $e->getMessage());
    }

    redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
});
