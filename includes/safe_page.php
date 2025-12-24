<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function safePage(callable $callback, string $lang, array $config): void {
    ob_start();
    try {
        $callback();
        ob_end_flush();
    } catch (Throwable $e) {
        ob_end_clean();
        logEvent('php_errors.log', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        renderErrorPage($lang, $config);
    }
}

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

