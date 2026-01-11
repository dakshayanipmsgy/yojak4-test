<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_role('superadmin');
    $reqId = trim((string)($_GET['reqId'] ?? ''));
    if ($reqId !== '') {
        redirect('/superadmin/assisted_v2/process.php?reqId=' . urlencode($reqId));
    }
    redirect('/superadmin/assisted_v2/queue.php');
});
