<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/tender_discovery.php');
    }

    require_csrf();
    require_role('superadmin');
    ensure_tender_discovery_env();

    $sources = tender_discovery_sources();
    $summary = tender_discovery_run($sources);

    $message = 'Run completed. New: ' . ($summary['newCount'] ?? 0) . ', Fetched: ' . ($summary['totalFetched'] ?? 0);
    if (!empty($summary['errors'])) {
        $message .= '. Errors: ' . count($summary['errors']);
    }

    set_flash('success', $message);
    redirect('/superadmin/tender_discovery.php');
});
