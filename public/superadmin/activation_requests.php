<?php
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_role('superadmin');
    redirect('/superadmin/schemes/activation_requests.php');
});
