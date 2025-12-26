<?php
declare(strict_types=1);

const BASE_PATH = __DIR__ . '/..';
const DATA_PATH = BASE_PATH . '/data';
const PUBLIC_PATH = BASE_PATH . '/public';

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/staff.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/departments.php';
require_once __DIR__ . '/contractors.php';
require_once __DIR__ . '/offline_tenders.php';
require_once __DIR__ . '/workorders.php';
require_once __DIR__ . '/tender_archive.php';
require_once __DIR__ . '/packs.php';
require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/content.php';
require_once __DIR__ . '/bills.php';
require_once __DIR__ . '/tender_discovery.php';

set_default_timezone();
start_app_session();
ensure_data_structure();
ensure_content_structure();
ensure_departments_root();
ensure_contractors_root();
ensure_staff_environment();
rotate_language_from_request();
initialize_php_error_logging();
