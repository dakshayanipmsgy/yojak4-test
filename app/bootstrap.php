<?php
declare(strict_types=1);

const BASE_PATH = __DIR__ . '/..';
const DATA_PATH = BASE_PATH . '/data';
const PUBLIC_PATH = BASE_PATH . '/public';

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/departments.php';

set_default_timezone();
start_app_session();
ensure_data_structure();
ensure_departments_root();
rotate_language_from_request();
initialize_php_error_logging();
