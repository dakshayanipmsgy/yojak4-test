<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role('superadmin');
if (!empty($user['mustResetPassword'])) {
    redirect('/auth/force_reset.php');
}

$jobId = $_GET['jobId'] ?? '';
if ($jobId === '') {
    http_response_code(400);
    echo 'Missing jobId';
    exit;
}

$jobPath = content_job_path($jobId);
if (!file_exists($jobPath)) {
    http_response_code(404);
    echo 'Job not found';
    exit;
}

if (isset($_GET['poll'])) {
    header('Content-Type: application/json');
    $job = readJson($jobPath);
    echo json_encode($job ?: ['status' => 'error', 'errorText' => 'Job not found']);
    exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$send = function (array $payload): void {
    echo 'data: ' . json_encode($payload) . "\n\n";
    @ob_flush();
    flush();
};

set_time_limit(0);
ignore_user_abort(true);

// Kick off generation if not already processing
if (mark_job_processing($jobId)) {
    process_content_job($jobId, function (string $chunk) use ($send) {
        $send(['chunk' => $chunk]);
    });
}

$lastCount = 0;

while (true) {
    if (connection_aborted()) {
        break;
    }

    $job = readJson($jobPath);
    if (!$job) {
        $send(['status' => 'error', 'error' => 'Job missing.']);
        break;
    }

    $chunks = $job['chunks'] ?? [];
    $newCount = count($chunks);
    if ($newCount > $lastCount) {
        for ($i = $lastCount; $i < $newCount; $i++) {
            $text = $chunks[$i]['text'] ?? '';
            if ($text !== '') {
                $send(['chunk' => $text]);
            }
        }
        $lastCount = $newCount;
    }

    if (($job['status'] ?? '') === 'done') {
        $send(['status' => 'done', 'contentId' => $job['resultContentId'] ?? null]);
        break;
    }
    if (($job['status'] ?? '') === 'error') {
        $send(['status' => 'error', 'error' => $job['errorText'] ?? 'Unknown error']);
        break;
    }

    sleep(1);
}
