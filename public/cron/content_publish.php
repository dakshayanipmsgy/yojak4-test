<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

$token = $_GET['token'] ?? '';
if ($token === '' || $token !== get_content_cron_token()) {
    http_response_code(403);
    echo 'Invalid token';
    exit;
}

$now = now_kolkata();
$published = [];

foreach (['blog', 'news'] as $type) {
    $index = load_content_index($type);
    foreach ($index as $row) {
        if (($row['status'] ?? '') === 'scheduled' && !empty($row['publishAt'])) {
            $publishAt = new DateTimeImmutable($row['publishAt']);
            if ($publishAt <= $now) {
                $item = load_content_item($type, $row['id']);
                if ($item) {
                    $item['status'] = 'published';
                    $item['publishedAt'] = $now->format(DateTime::ATOM);
                    $item['publishAt'] = null;
                    $item['updatedAt'] = $item['publishedAt'];
                    save_content_item($item);
                    $published[] = $item['id'];
                }
            }
        }
    }
}

content_log(['event' => 'cron_publish', 'published' => $published, 'count' => count($published)]);

echo 'Published: ' . implode(', ', $published);
