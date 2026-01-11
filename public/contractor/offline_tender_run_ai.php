<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $offtdId = trim((string)($_POST['id'] ?? ($_GET['id'] ?? '')));
    set_flash('error', 'Contractor-side AI extraction is disabled. Use Assisted Pack v2.');
    if ($offtdId !== '') {
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
    }
    redirect('/contractor/offline_tenders.php');
});
