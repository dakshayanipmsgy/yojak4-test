<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role('superadmin');
if (!empty($user['mustResetPassword'])) {
    redirect('/auth/force_reset.php');
}

try {
    $isPoll = isset($_GET['poll']);
    $jobId = trim((string)($_GET['jobId'] ?? ''));

    $sendError = function (int $status, string $message) use ($isPoll): void {
        http_response_code($status);
        if ($isPoll) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'status' => 'error', 'error' => $message]);
        } else {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            echo 'data: ' . json_encode(['status' => 'error', 'error' => $message]) . "\n\n";
            @ob_flush();
            flush();
        }
    };

    if ($jobId === '') {
        content_log(['event' => 'content_stream_missing_job', 'jobId' => null, 'error' => 'JobId missing in request']);
        $sendError(400, 'Missing jobId. Please start a new generation.');
        exit;
    }

    $jobPath = content_job_path($jobId);
    if (!file_exists($jobPath)) {
        content_log(['event' => 'content_stream_missing_job', 'jobId' => $jobId, 'error' => 'Job file not found']);
        $sendError(404, 'Job not found. Please submit generation again.');
        exit;
    }

    $job = readJson($jobPath);
    if (($job['jobId'] ?? '') !== $jobId) {
        content_log(['event' => 'content_stream_job_mismatch', 'jobId' => $jobId, 'error' => 'Job id mismatch']);
        $sendError(404, 'Job mismatch. Please start a fresh generation.');
        exit;
    }

    if ($isPoll) {
        header('Content-Type: application/json');
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
        process_content_job($jobId, function (string $chunk) use ($send, $jobId) {
            $send(['chunk' => $chunk, 'jobId' => $jobId]);
        });
    }

    $lastCount = 0;

    while (true) {
        if (connection_aborted()) {
            break;
        }

        $job = readJson($jobPath);
        if (!$job) {
            $send(['status' => 'error', 'error' => 'Job missing.', 'jobId' => $jobId]);
            break;
        }

        $chunks = $job['chunks'] ?? [];
        $newCount = count($chunks);
        if ($newCount > $lastCount) {
            for ($i = $lastCount; $i < $newCount; $i++) {
                $text = $chunks[$i]['text'] ?? '';
                if ($text !== '') {
                    $send(['chunk' => $text, 'jobId' => $jobId]);
                }
            }
            $lastCount = $newCount;
        }

        if (($job['status'] ?? '') === 'done') {
            $contentId = $job['resultContentId'] ?? null;
            if (!$contentId) {
                content_log(['event' => 'content_stream_missing_content', 'jobId' => $jobId, 'error' => 'Content ID missing for completed job']);
                $send(['status' => 'error', 'error' => 'Draft missing. Please generate again.', 'jobId' => $jobId]);
                break;
            }
            $generationMeta = $job['generation'] ?? [];
            $send([
                'status' => 'done',
                'contentId' => $contentId,
                'jobId' => $jobId,
                'meta' => [
                    'promptHash' => substr((string)($generationMeta['promptHash'] ?? ''), 0, 16),
                    'outputHash' => substr((string)($generationMeta['outputHash'] ?? ''), 0, 16),
                    'nonce' => $generationMeta['nonce'] ?? null,
                    'type' => $generationMeta['typeRequested'] ?? null,
                    'length' => $generationMeta['lengthRequested'] ?? null,
                ],
            ]);
            break;
        }
        if (($job['status'] ?? '') === 'error') {
            $send(['status' => 'error', 'error' => $job['errorText'] ?? 'Unknown error', 'jobId' => $jobId]);
            break;
        }

        sleep(1);
    }
} catch (Throwable $e) {
    content_log([
        'event' => 'content_stream_error',
        'jobId' => $_GET['jobId'] ?? null,
        'error' => $e->getMessage(),
    ]);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['ok' => false, 'error' => 'Streaming failed. Please retry.']);
}
