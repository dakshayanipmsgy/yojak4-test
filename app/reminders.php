<?php
declare(strict_types=1);

const REMINDERS_LOG = DATA_PATH . '/logs/reminders.log';

function ensure_reminders_env(string $yojId): void
{
    $dir = contractors_approved_path($yojId) . '/reminders';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!file_exists(reminders_index_path($yojId))) {
        writeJsonAtomic(reminders_index_path($yojId), []);
    }
    if (!file_exists(REMINDERS_LOG)) {
        touch(REMINDERS_LOG);
    }
}

function reminder_record_path(string $yojId, string $remId): string
{
    return contractors_approved_path($yojId) . '/reminders/' . $remId . '.json';
}

function reminder_index_entries(string $yojId): array
{
    $data = readJson(reminders_index_path($yojId));
    return is_array($data) ? array_values($data) : [];
}

function save_reminder_index_entries(string $yojId, array $entries): void
{
    writeJsonAtomic(reminders_index_path($yojId), array_values($entries));
}

function generate_reminder_id(): string
{
    return 'REM-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function normalize_reminder_entry(array $entry): array
{
    $remId = $entry['remId'] ?? ($entry['reminderId'] ?? '');
    $status = $entry['status'] ?? 'open';
    if ($status === 'active') {
        $status = 'open';
    }
    $entry['remId'] = $remId;
    $entry['status'] = $status;
    $entry['packId'] = $entry['packId'] ?? null;
    $entry['title'] = $entry['title'] ?? '';
    $entry['dueAt'] = $entry['dueAt'] ?? null;
    $entry['createdAt'] = $entry['createdAt'] ?? null;
    $entry['doneAt'] = $entry['doneAt'] ?? null;
    return $entry;
}

function save_reminder_record(string $yojId, array $reminder): void
{
    if (empty($reminder['remId'])) {
        throw new InvalidArgumentException('Reminder id missing.');
    }
    ensure_reminders_env($yojId);
    writeJsonAtomic(reminder_record_path($yojId, $reminder['remId']), $reminder);
}

function upsert_pack_deadline_reminder(string $yojId, array $pack): ?array
{
    $deadline = $pack['submissionDeadline'] ?? ($pack['dates']['submission'] ?? '');
    if ($deadline === '') {
        return null;
    }
    ensure_reminders_env($yojId);
    $entries = reminder_index_entries($yojId);
    $now = now_kolkata()->format(DateTime::ATOM);
    foreach ($entries as $idx => $entry) {
        $entry = normalize_reminder_entry($entry);
        if (($entry['type'] ?? '') === 'deadline'
            && ($entry['packId'] ?? '') === ($pack['packId'] ?? '')
            && ($entry['status'] ?? 'open') !== 'done') {
            $entry['title'] = $entry['title'] !== '' ? $entry['title'] : ('Submission deadline • ' . ($pack['tenderTitle'] ?? $pack['title'] ?? 'Pack'));
            $entry['dueAt'] = $deadline;
            $entries[$idx] = $entry;
            save_reminder_index_entries($yojId, $entries);
            save_reminder_record($yojId, $entry);
            return $entry;
        }
    }

    $remId = generate_reminder_id();
    $reminder = [
        'remId' => $remId,
        'type' => 'deadline',
        'title' => 'Submission deadline • ' . ($pack['tenderTitle'] ?? $pack['title'] ?? 'Pack'),
        'dueAt' => $deadline,
        'packId' => $pack['packId'] ?? null,
        'status' => 'open',
        'createdAt' => $now,
        'doneAt' => null,
    ];
    $entries[] = $reminder;
    save_reminder_index_entries($yojId, $entries);
    save_reminder_record($yojId, $reminder);
    logEvent(REMINDERS_LOG, [
        'event' => 'reminder_created',
        'yojId' => $yojId,
        'remId' => $remId,
        'type' => 'deadline',
        'packId' => $pack['packId'] ?? null,
    ]);
    return $reminder;
}

function upsert_missing_docs_reminder(string $yojId, array $pack, int $missingCount): void
{
    ensure_reminders_env($yojId);
    $entries = reminder_index_entries($yojId);
    $now = now_kolkata();
    $deadline = $pack['submissionDeadline'] ?? ($pack['dates']['submission'] ?? '');
    $fallbackDue = $now->modify('+2 days')->format(DateTime::ATOM);
    $dueAt = $deadline !== '' ? $deadline : $fallbackDue;
    $packId = $pack['packId'] ?? '';

    foreach ($entries as $idx => $entry) {
        $entry = normalize_reminder_entry($entry);
        if (($entry['type'] ?? '') === 'missing_docs' && ($entry['packId'] ?? '') === $packId) {
            if ($missingCount <= 0) {
                if (($entry['status'] ?? '') !== 'done') {
                    $entry['status'] = 'done';
                    $entry['doneAt'] = $now->format(DateTime::ATOM);
                    $entries[$idx] = $entry;
                    save_reminder_index_entries($yojId, $entries);
                    save_reminder_record($yojId, $entry);
                    logEvent(REMINDERS_LOG, [
                        'event' => 'reminder_done',
                        'yojId' => $yojId,
                        'remId' => $entry['remId'] ?? '',
                        'type' => 'missing_docs',
                        'packId' => $packId,
                    ]);
                }
                return;
            }
            $entry['title'] = $missingCount . ' documents pending';
            $entry['dueAt'] = $dueAt;
            $entry['status'] = 'open';
            $entries[$idx] = $entry;
            save_reminder_index_entries($yojId, $entries);
            save_reminder_record($yojId, $entry);
            return;
        }
    }

    if ($missingCount <= 0) {
        return;
    }

    $remId = generate_reminder_id();
    $reminder = [
        'remId' => $remId,
        'type' => 'missing_docs',
        'title' => $missingCount . ' documents pending',
        'dueAt' => $dueAt,
        'packId' => $packId,
        'status' => 'open',
        'createdAt' => $now->format(DateTime::ATOM),
        'doneAt' => null,
    ];
    $entries[] = $reminder;
    save_reminder_index_entries($yojId, $entries);
    save_reminder_record($yojId, $reminder);
    logEvent(REMINDERS_LOG, [
        'event' => 'reminder_created',
        'yojId' => $yojId,
        'remId' => $remId,
        'type' => 'missing_docs',
        'packId' => $packId,
        'missingCount' => $missingCount,
    ]);
}
