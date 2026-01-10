<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    set_flash('error', 'This assisted extraction endpoint has been retired. Please use the new Assisted Extraction request button.');
    $offtdId = trim($_POST['id'] ?? $_GET['id'] ?? '');
    if ($offtdId !== '') {
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        return;
    }
    redirect('/contractor/offline_tenders.php');
});
