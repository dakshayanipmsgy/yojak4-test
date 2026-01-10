<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    set_flash('error', 'The legacy assisted extraction editor has been retired.');
    redirect('/superadmin/assisted_queue.php');
});
