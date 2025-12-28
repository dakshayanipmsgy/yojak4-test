<?php
declare(strict_types=1);

function support_index_path(): string
{
    return DATA_PATH . '/support/tickets/index.json';
}

function support_ticket_path(string $ticketId): string
{
    return DATA_PATH . '/support/tickets/' . $ticketId . '.json';
}

function support_upload_dir(string $ticketId): string
{
    return DATA_PATH . '/support/uploads/' . $ticketId;
}

function support_log_path(): string
{
    return DATA_PATH . '/logs/support.log';
}

function support_generate_ticket_id(): string
{
    $date = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Ymd');
    $rand = random_int(1000, 9999);
    return 'TKT-' . $date . '-' . $rand;
}

function support_load_index(): array
{
    $index = readJson(support_index_path());
    return is_array($index) ? $index : [];
}

function support_save_index(array $index): void
{
    writeJsonAtomic(support_index_path(), array_values($index));
}

function support_append_jsonl(string $file, array $payload): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $payload['timestamp'] = now_kolkata()->format(DateTime::ATOM);
    $handle = fopen($file, 'a');
    if ($handle) {
        flock($handle, LOCK_EX);
        fwrite($handle, json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function support_masked_request(): array
{
    $query = $_GET ?? [];
    $maskedQuery = [];
    foreach ($query as $key => $value) {
        if (stripos((string)$key, 'password') !== false || stripos((string)$key, 'token') !== false || stripos((string)$key, 'csrf') !== false || stripos((string)$key, 'session') !== false || stripos((string)$key, 'auth') !== false) {
            $maskedQuery[$key] = '[redacted]';
            continue;
        }
        $maskedQuery[$key] = is_string($value) ? $value : json_encode($value);
    }

    return [
        'path' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'query' => $maskedQuery,
    ];
}

function support_current_user_context(): array
{
    $user = current_user();
    return [
        'userType' => $user['type'] ?? null,
        'yojId' => $user['yojId'] ?? null,
        'deptId' => $user['deptId'] ?? null,
        'fullUserId' => $user['fullUserId'] ?? ($user['username'] ?? null),
    ];
}

function support_record_runtime_error(string $level, string $message, ?string $file = null, ?int $line = null, ?string $trace = null): string
{
    $reference = 'ERR-' . (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
    $_SESSION['last_error_reference'] = $reference;

    $entry = [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'level' => $level,
        'reference' => $reference,
        'message' => $message,
        'file' => $file ? basename($file) : null,
        'line' => $line,
        'request' => support_masked_request(),
        'user' => support_current_user_context(),
        'ipMasked' => mask_ip($_SERVER['REMOTE_ADDR'] ?? ''),
        'uaHash' => hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
        'traceSnippet' => $trace ? substr($trace, 0, 1500) : null,
    ];

    $logFile = DATA_PATH . '/logs/runtime_errors/' . (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d') . '.jsonl';
    support_append_jsonl($logFile, $entry);

    return $reference;
}

function support_render_friendly_error(string $reference): void
{
    http_response_code(500);
    $title = get_app_config()['appName'] . ' | Error';
    $supportLink = '/contractor/support.php';
    $user = current_user();
    if (($user['type'] ?? '') === 'department') {
        $supportLink = '/department/support.php';
    }
    render_layout($title, function () use ($reference, $supportLink) {
        ?>
        <div class="card error-card">
            <h2><?= sanitize('Something went wrong'); ?></h2>
            <p><?= sanitize('We encountered an issue while processing your request.'); ?></p>
            <p class="muted">Reference Code: <strong><?= sanitize($reference); ?></strong></p>
            <div class="buttons">
                <a class="btn" href="<?= sanitize($supportLink); ?>?ref=<?= urlencode($reference); ?>">Report this error</a>
                <a class="btn secondary" href="/">Go back home</a>
            </div>
        </div>
        <?php
    });
}

function install_runtime_error_handlers(): void
{
    ini_set('display_errors', '0');
    error_reporting(E_ALL);

    set_error_handler(function (int $severity, string $message, string $file = '', int $line = 0): bool {
        // Respect error_reporting
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $level = 'warning';
        if (in_array($severity, [E_ERROR, E_USER_ERROR, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $level = 'fatal';
        } elseif (in_array($severity, [E_NOTICE, E_USER_NOTICE], true)) {
            $level = 'notice';
        } elseif (in_array($severity, [E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING], true)) {
            $level = 'warning';
        }

        $ref = support_record_runtime_error($level, $message, $file, $line, debug_backtrace_as_string());

        if ($level === 'fatal') {
            support_render_friendly_error($ref);
            return true;
        }

        return false;
    });

    set_exception_handler(function (Throwable $e): void {
        $ref = support_record_runtime_error('exception', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
        support_render_friendly_error($ref);
    });

    register_shutdown_function(function (): void {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
            $ref = support_record_runtime_error('fatal', $error['message'] ?? 'Fatal error', $error['file'] ?? null, $error['line'] ?? null, null);
            if (!headers_sent()) {
                support_render_friendly_error($ref);
            }
        }
    });
}

function debug_backtrace_as_string(): string
{
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $lines = [];
    foreach ($trace as $frame) {
        $lines[] = ($frame['file'] ?? '[internal]') . ':' . ($frame['line'] ?? 0);
    }
    return implode("\n", $lines);
}

function safe_page(callable $callback): void
{
    try {
        $callback();
    } catch (Throwable $e) {
        $ref = support_record_runtime_error('exception', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
        support_render_friendly_error($ref);
        exit;
    }
}

function support_validate_ticket(array $data): array
{
    $errors = [];
    $title = trim($data['title'] ?? '');
    $message = trim($data['message'] ?? '');

    if ($title === '' || strlen($title) < 5 || strlen($title) > 120) {
        $errors[] = 'Title must be between 5 and 120 characters.';
    }
    if ($message === '' || strlen($message) < 20 || strlen($message) > 5000) {
        $errors[] = 'Description must be between 20 and 5000 characters.';
    }
    $type = $data['type'] ?? 'feedback';
    if (!in_array($type, ['feedback', 'bug', 'other'], true)) {
        $errors[] = 'Invalid ticket type.';
    }
    $severity = $data['severity'] ?? (($type === 'bug') ? 'medium' : 'low');
    if (!in_array($severity, ['low', 'medium', 'high'], true)) {
        $errors[] = 'Invalid severity.';
    }
    return $errors;
}

function support_store_ticket(array $data, array $files): array
{
    $user = current_user();
    if (!$user || !in_array(($user['type'] ?? ''), ['contractor', 'department', 'superadmin'], true)) {
        throw new RuntimeException('Unauthorized');
    }

    $ticketId = support_generate_ticket_id();
    $now = now_kolkata()->format(DateTime::ATOM);
    $type = $data['type'] ?? 'feedback';
    $severity = $data['severity'] ?? (($type === 'bug') ? 'medium' : 'low');
    $status = 'open';

    $context = [
        'url' => $data['page'] ?? ($_SERVER['REQUEST_URI'] ?? ''),
        'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
        'uaHash' => hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''),
        'ipMasked' => mask_ip($_SERVER['REMOTE_ADDR'] ?? ''),
        'localTime' => now_kolkata()->format(DateTime::ATOM),
    ];

    $attachments = support_handle_uploads($ticketId, $files);

    $message = trim($data['message'] ?? '');
    $appendSections = [];
    if (!empty($data['steps'])) {
        $appendSections[] = "Steps to reproduce: " . trim((string)$data['steps']);
    }
    if (!empty($data['expected'])) {
        $appendSections[] = "Expected result: " . trim((string)$data['expected']);
    }
    if (!empty($data['actual'])) {
        $appendSections[] = "Actual result: " . trim((string)$data['actual']);
    }
    if ($appendSections) {
        $message .= "\n\n" . implode("\n", $appendSections);
    }

    $ticket = [
        'ticketId' => $ticketId,
        'type' => $type,
        'title' => trim($data['title'] ?? ''),
        'message' => $message,
        'severity' => $severity,
        'user' => [
            'userType' => $user['type'] ?? null,
            'yojId' => $user['yojId'] ?? null,
            'deptId' => $user['deptId'] ?? null,
            'fullUserId' => $user['fullUserId'] ?? ($user['username'] ?? null),
        ],
        'pageContext' => $context,
        'attachments' => $attachments,
        'status' => $status,
        'adminNotes' => [],
        'createdAt' => $now,
        'updatedAt' => $now,
        'closedAt' => null,
    ];

    $path = support_ticket_path($ticketId);
    writeJsonAtomic($path, $ticket);

    $index = support_load_index();
    $index[] = [
        'ticketId' => $ticketId,
        'type' => $type,
        'severity' => $severity,
        'status' => $status,
        'createdAt' => $now,
        'userType' => $ticket['user']['userType'],
        'yojId' => $ticket['user']['yojId'],
        'deptId' => $ticket['user']['deptId'],
        'fullUserId' => $ticket['user']['fullUserId'],
        'title' => $ticket['title'],
    ];
    support_save_index($index);

    support_append_jsonl(support_log_path(), [
        'event' => 'ticket_created',
        'ticketId' => $ticketId,
        'user' => $ticket['user'],
        'type' => $type,
        'severity' => $severity,
    ]);

    return $ticket;
}

function support_handle_uploads(string $ticketId, array $files): array
{
    if (!isset($files['attachments'])) {
        return [];
    }
    $uploads = $files['attachments'];
    if (!is_array($uploads['name'])) {
        return [];
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    $stored = [];
    $count = 0;

    foreach ($uploads['name'] as $idx => $name) {
        if ($uploads['error'][$idx] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($count >= 3) {
            break;
        }
        if ($uploads['error'][$idx] !== UPLOAD_ERR_OK) {
            continue;
        }
        $size = (int)$uploads['size'][$idx];
        if ($size > 5 * 1024 * 1024) {
            continue;
        }
        $tmp = $uploads['tmp_name'][$idx];
        $mime = mime_content_type($tmp) ?: ($uploads['type'][$idx] ?? '');
        if (!in_array($mime, $allowed, true)) {
            continue;
        }
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name));
        $destDir = support_upload_dir($ticketId);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }
        $dest = $destDir . '/' . $safeName;
        if (!move_uploaded_file($tmp, $dest)) {
            continue;
        }
        $stored[] = [
            'name' => $safeName,
            'storedPath' => $dest,
            'size' => $size,
            'mime' => $mime,
        ];
        $count++;
    }

    return $stored;
}

function support_update_ticket(string $ticketId, string $status, ?string $note): void
{
    $path = support_ticket_path($ticketId);
    if (!file_exists($path)) {
        throw new RuntimeException('Ticket not found');
    }
    $ticket = readJson($path);
    if (!$ticket) {
        throw new RuntimeException('Ticket data missing');
    }

    $allowedStatuses = ['open', 'in_review', 'resolved', 'closed'];
    if (!in_array($status, $allowedStatuses, true)) {
        throw new RuntimeException('Invalid status');
    }

    $now = now_kolkata()->format(DateTime::ATOM);
    $ticket['status'] = $status;
    $ticket['updatedAt'] = $now;
    if (in_array($status, ['resolved', 'closed'], true)) {
        $ticket['closedAt'] = $now;
    } else {
        $ticket['closedAt'] = null;
    }

    if ($note !== null && trim($note) !== '') {
        $ticket['adminNotes'][] = [
            'at' => $now,
            'by' => 'superadmin',
            'note' => trim($note),
        ];
    }

    writeJsonAtomic($path, $ticket);

    $index = support_load_index();
    foreach ($index as &$entry) {
        if (($entry['ticketId'] ?? '') === $ticketId) {
            $entry['status'] = $status;
            $entry['severity'] = $ticket['severity'] ?? ($entry['severity'] ?? 'medium');
            $entry['title'] = $ticket['title'] ?? $entry['title'];
            break;
        }
    }
    support_save_index($index);

    support_append_jsonl(support_log_path(), [
        'event' => 'ticket_updated',
        'ticketId' => $ticketId,
        'status' => $status,
        'note' => $note,
    ]);
}

function support_create_ticket_from_error(array $error): string
{
    $message = 'Auto-created from error reference ' . ($error['reference'] ?? 'N/A') . "\n\n" . ($error['message'] ?? '');
    $ticket = [
        'type' => 'bug',
        'title' => 'Runtime error ' . ($error['reference'] ?? ''),
        'message' => $message,
        'severity' => 'high',
        'page' => $error['request']['path'] ?? '',
    ];
    $created = support_store_ticket($ticket, []);
    return $created['ticketId'];
}

function support_prefill_message(?string $reference): string
{
    if ($reference) {
        return "Reference code: {$reference}\n\nDescribe what happened:";
    }
    return '';
}

function support_render_form(string $title, string $userType): void
{
    $ref = $_GET['ref'] ?? ($_SESSION['last_error_reference'] ?? '');
    $prefill = support_prefill_message($ref);
    ?>
    <div class="card">
        <h2 style="margin-bottom:6px;"><?= sanitize($title); ?></h2>
        <p class="muted" style="margin-top:0;">Share feedback or report a bug. Your details are captured securely.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
            <span class="pill">Logged in as <?= sanitize(ucfirst($userType)); ?></span>
            <?php if ($ref): ?>
                <span class="pill" style="border-color:#2ea043;color:#8ce99a;">Error Ref: <?= sanitize($ref); ?></span>
            <?php endif; ?>
        </div>
        <form method="post" action="/support/submit.php" enctype="multipart/form-data" style="display:grid;gap:12px;" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
            <input type="hidden" name="page" value="<?= sanitize($_SERVER['REQUEST_URI'] ?? ''); ?>">
            <div class="field">
                <label style="font-weight:700;">Type</label>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <?php $types = ['feedback' => 'Feedback', 'bug' => 'Report a bug', 'other' => 'Other']; foreach ($types as $value => $label): ?>
                        <label class="pill" style="cursor:pointer;"><input type="radio" name="type" value="<?= sanitize($value); ?>" <?= $value === 'bug' ? 'checked' : ''; ?> style="margin-right:6px;"><?= sanitize($label); ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="field">
                <label style="font-weight:700;">Title</label>
                <input type="text" name="title" maxlength="120" placeholder="Concise summary" required>
            </div>
            <div class="field">
                <label style="font-weight:700;">Description</label>
                <textarea name="message" rows="5" maxlength="5000" placeholder="Tell us what happened" required><?= sanitize($prefill); ?></textarea>
            </div>
            <div class="field">
                <label style="font-weight:700;">Bug details (optional)</label>
                <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
                    <input type="text" name="steps" placeholder="Steps to reproduce">
                    <input type="text" name="expected" placeholder="Expected result">
                    <input type="text" name="actual" placeholder="Actual result">
                </div>
            </div>
            <div class="field" style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));align-items:center;">
                <div>
                    <label style="font-weight:700;">Severity</label>
                    <select name="severity">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight:700;">Attachments (max 3)</label>
                    <input type="file" name="attachments[]" accept="image/jpeg,image/png,image/webp,application/pdf" multiple>
                    <p class="muted" style="margin:4px 0 0;">JPG/PNG/WebP/PDF up to 5MB each.</p>
                </div>
            </div>
            <div class="buttons">
                <button type="submit" class="btn">Submit</button>
                <a class="btn secondary" href="/">Cancel</a>
            </div>
        </form>
    </div>
    <?php
}
