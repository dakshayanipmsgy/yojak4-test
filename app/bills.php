<?php
declare(strict_types=1);

function bills_log_path(): string
{
    return DATA_PATH . '/logs/bills.log';
}

function ensure_bills_log(): void
{
    $path = bills_log_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!file_exists($path)) {
        touch($path);
    }
}

function contractor_bills_root(string $yojId): string
{
    return contractors_approved_path($yojId) . '/bills';
}

function contractor_bills_index_path(string $yojId): string
{
    return contractor_bills_root($yojId) . '/index.json';
}

function contractor_bill_path(string $yojId, string $billId): string
{
    return contractor_bills_root($yojId) . '/' . $billId . '/bill.json';
}

function contractor_bill_upload_root(string $yojId, string $billId): string
{
    return PUBLIC_PATH . '/uploads/contractors/' . $yojId . '/bills/' . $billId;
}

function ensure_contractor_bills_root(string $yojId): void
{
    ensure_bills_log();
    $root = contractor_bills_root($yojId);
    $uploadsRoot = PUBLIC_PATH . '/uploads/contractors/' . $yojId . '/bills';
    $directories = [
        $root,
        $uploadsRoot,
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $indexPath = contractor_bills_index_path($yojId);
    if (!file_exists($indexPath)) {
        writeJsonAtomic($indexPath, []);
    }
}

function allowed_bill_statuses(): array
{
    return ['draft', 'submitted', 'approved', 'paid'];
}

function contractor_bills_index(string $yojId): array
{
    ensure_contractor_bills_root($yojId);
    $data = readJson(contractor_bills_index_path($yojId));
    return is_array($data) ? array_values($data) : [];
}

function save_contractor_bills_index(string $yojId, array $entries): void
{
    writeJsonAtomic(contractor_bills_index_path($yojId), array_values($entries));
}

function generate_bill_id(string $yojId): string
{
    do {
        $candidate = 'BILL-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $path = contractor_bill_path($yojId, $candidate);
    } while (file_exists($path));

    return $candidate;
}

function create_contractor_bill(string $yojId, string $title, string $workorderRef = '', string $amountText = ''): array
{
    ensure_contractor_bills_root($yojId);
    $billId = generate_bill_id($yojId);
    $billDir = dirname(contractor_bill_path($yojId, $billId));
    if (!is_dir($billDir)) {
        mkdir($billDir, 0775, true);
    }

    $now = now_kolkata()->format(DateTime::ATOM);
    $bill = [
        'billId' => $billId,
        'title' => $title,
        'workorderRef' => $workorderRef,
        'amountText' => $amountText,
        'status' => 'draft',
        'submittedAt' => null,
        'approvedAt' => null,
        'paidAt' => null,
        'attachments' => [],
        'reminders' => [],
        'statusHistory' => [
            [
                'status' => 'draft',
                'changedAt' => $now,
                'actor' => 'contractor',
                'note' => 'Created',
            ],
        ],
        'createdAt' => $now,
        'updatedAt' => $now,
    ];

    writeJsonAtomic(contractor_bill_path($yojId, $billId), $bill);
    upsert_bill_index_entry($yojId, $bill);

    logEvent(bills_log_path(), [
        'event' => 'bill_created',
        'yojId' => $yojId,
        'billId' => $billId,
        'title' => $title,
    ]);

    return $bill;
}

function upsert_bill_index_entry(string $yojId, array $bill): void
{
    $index = contractor_bills_index($yojId);
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['billId'] ?? '') === ($bill['billId'] ?? '')) {
            $entry['title'] = $bill['title'] ?? '';
            $entry['status'] = $bill['status'] ?? 'draft';
            $entry['workorderRef'] = $bill['workorderRef'] ?? '';
            $entry['amountText'] = $bill['amountText'] ?? '';
            $entry['updatedAt'] = $bill['updatedAt'] ?? $entry['updatedAt'] ?? now_kolkata()->format(DateTime::ATOM);
            $entry['createdAt'] = $bill['createdAt'] ?? $entry['createdAt'] ?? now_kolkata()->format(DateTime::ATOM);
            $found = true;
            break;
        }
    }
    unset($entry);

    if (!$found) {
        $index[] = [
            'billId' => $bill['billId'] ?? '',
            'title' => $bill['title'] ?? '',
            'status' => $bill['status'] ?? 'draft',
            'workorderRef' => $bill['workorderRef'] ?? '',
            'amountText' => $bill['amountText'] ?? '',
            'createdAt' => $bill['createdAt'] ?? now_kolkata()->format(DateTime::ATOM),
            'updatedAt' => $bill['updatedAt'] ?? now_kolkata()->format(DateTime::ATOM),
        ];
    }

    usort($index, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));
    save_contractor_bills_index($yojId, $index);
}

function load_contractor_bill(string $yojId, string $billId): ?array
{
    ensure_contractor_bills_root($yojId);
    $path = contractor_bill_path($yojId, $billId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function save_contractor_bill(string $yojId, array $bill): void
{
    writeJsonAtomic(contractor_bill_path($yojId, $bill['billId']), $bill);
    upsert_bill_index_entry($yojId, $bill);
}

function validate_status_transition(string $current, string $next): bool
{
    $statuses = allowed_bill_statuses();
    $currentIndex = array_search($current, $statuses, true);
    $nextIndex = array_search($next, $statuses, true);

    if ($currentIndex === false || $nextIndex === false) {
        return false;
    }

    if ($nextIndex === $currentIndex) {
        return true;
    }

    if ($nextIndex === $currentIndex + 1) {
        return true;
    }

    return $nextIndex < $currentIndex; // rollback allowed with confirmation gate handled elsewhere
}

function apply_status_change(array $bill, string $newStatus, string $actor, bool $isRollback): array
{
    $now = now_kolkata()->format(DateTime::ATOM);
    $statuses = allowed_bill_statuses();
    $currentIndex = array_search($bill['status'] ?? 'draft', $statuses, true);
    $nextIndex = array_search($newStatus, $statuses, true);

    $bill['status'] = $newStatus;
    $bill['updatedAt'] = $now;

    if ($newStatus === 'submitted') {
        $bill['submittedAt'] = $now;
    } elseif ($newStatus === 'approved') {
        $bill['approvedAt'] = $now;
    } elseif ($newStatus === 'paid') {
        $bill['paidAt'] = $now;
    }

    if ($isRollback && $currentIndex !== false && $nextIndex !== false && $nextIndex < $currentIndex) {
        if ($nextIndex < array_search('paid', $statuses, true)) {
            $bill['paidAt'] = null;
        }
        if ($nextIndex < array_search('approved', $statuses, true)) {
            $bill['approvedAt'] = null;
        }
        if ($nextIndex < array_search('submitted', $statuses, true)) {
            $bill['submittedAt'] = null;
        }
    }

    if (!isset($bill['statusHistory']) || !is_array($bill['statusHistory'])) {
        $bill['statusHistory'] = [];
    }

    $bill['statusHistory'][] = [
        'status' => $newStatus,
        'changedAt' => $now,
        'actor' => $actor,
        'note' => $isRollback ? 'Rollback' : 'Status update',
    ];

    return $bill;
}

function add_bill_attachment(string $yojId, array $bill, array $file): array
{
    $allowed = allowed_vault_mimes();
    $mime = $file['type'] ?? '';
    $size = (int)($file['size'] ?? 0);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Unsupported file type.');
    }

    $ext = $allowed[$mime];
    $fileId = 'ATT-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
    $uploadDir = contractor_bill_upload_root($yojId, $bill['billId']);
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $targetPath = $uploadDir . '/' . $fileId . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Failed to store uploaded file.');
    }

    $publicPath = str_replace(PUBLIC_PATH, '', $targetPath);
    $attachment = [
        'fileId' => $fileId,
        'path' => $publicPath,
        'originalName' => $file['name'] ?? ($fileId . '.' . $ext),
        'uploadedAt' => now_kolkata()->format(DateTime::ATOM),
        'sizeBytes' => $size,
        'mime' => $mime,
    ];

    if (!isset($bill['attachments']) || !is_array($bill['attachments'])) {
        $bill['attachments'] = [];
    }
    $bill['attachments'][] = $attachment;
    $bill['updatedAt'] = $attachment['uploadedAt'];

    logEvent(bills_log_path(), [
        'event' => 'bill_attachment_uploaded',
        'yojId' => $yojId,
        'billId' => $bill['billId'],
        'fileId' => $fileId,
        'name' => $attachment['originalName'],
        'size' => $size,
    ]);

    return $bill;
}

function add_bill_reminder(array $bill, string $note, string $remindAt, string $statusRef = ''): array
{
    $reminderId = 'REM-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
    if (!isset($bill['reminders']) || !is_array($bill['reminders'])) {
        $bill['reminders'] = [];
    }

    $bill['reminders'][] = [
        'reminderId' => $reminderId,
        'note' => $note,
        'statusRef' => $statusRef,
        'remindAt' => $remindAt,
        'createdAt' => now_kolkata()->format(DateTime::ATOM),
    ];

    $bill['updatedAt'] = now_kolkata()->format(DateTime::ATOM);

    return $bill;
}

function bill_next_reminder(array $bill): ?string
{
    if (empty($bill['reminders']) || !is_array($bill['reminders'])) {
        return null;
    }

    $now = now_kolkata();
    $upcoming = array_filter($bill['reminders'], function ($reminder) use ($now) {
        if (empty($reminder['remindAt'])) {
            return false;
        }
        $dt = DateTimeImmutable::createFromFormat(DateTime::ATOM, (string)$reminder['remindAt']);
        if (!$dt) {
            return false;
        }
        return $dt >= $now;
    });

    if (!$upcoming) {
        return null;
    }

    usort($upcoming, function ($a, $b) {
        return strcmp((string)($a['remindAt'] ?? ''), (string)($b['remindAt'] ?? ''));
    });

    return $upcoming[0]['remindAt'] ?? null;
}
