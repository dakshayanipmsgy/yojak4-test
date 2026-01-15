<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_login();
    if (!in_array(($user['type'] ?? ''), ['contractor', 'department'], true)) {
        render_error_page('Only contractor or department users can submit suggestions.');
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/suggestions/new.php');
    }

    require_csrf();

    $deviceHint = suggestion_device_hint($_SERVER['HTTP_USER_AGENT'] ?? '');
    $rateKey = suggestion_rate_limit_key($user);
    $errors = [];
    if (!suggestion_rate_limit_allowed($rateKey)) {
        $errors[] = 'You have reached the hourly suggestion limit. Please try again later.';
    }

    $category = (string)($_POST['category'] ?? 'feature');
    $title = (string)($_POST['title'] ?? '');
    $message = (string)($_POST['message'] ?? '');
    $pageUrl = suggestion_normalize_page_url((string)($_POST['pageUrl'] ?? ''));

    $errors = array_merge($errors, suggestion_validate([
        'category' => $category,
        'title' => $title,
        'message' => $message,
    ]));

    if ($errors) {
        $titleText = get_app_config()['appName'] . ' | Share a Suggestion';
        render_layout($titleText, function () use ($errors, $category, $title, $message, $pageUrl, $deviceHint) {
            ?>
            <style>
                .suggestion-wrapper {
                    display: grid;
                    gap: 16px;
                }
                .suggestion-form {
                    display: grid;
                    gap: 12px;
                    max-width: 720px;
                }
                .suggestion-form label {
                    font-weight: 600;
                    color: #0f172a;
                }
                .suggestion-form input[type="text"],
                .suggestion-form select,
                .suggestion-form textarea {
                    width: 100%;
                    padding: 10px 12px;
                    border-radius: 10px;
                    border: 1px solid #e2e8f0;
                    background: #fff;
                    color: #0f172a;
                    font-size: 0.95rem;
                }
                .suggestion-form textarea {
                    min-height: 160px;
                    resize: vertical;
                }
                .suggestion-meta {
                    display: grid;
                    gap: 6px;
                    font-size: 0.9rem;
                    color: var(--muted);
                }
            </style>
            <div class="suggestion-wrapper">
                <div class="card">
                    <h2 style="margin-bottom:6px;">Share a suggestion</h2>
                    <p class="muted" style="margin:0;">We are building YOJAK to fulfill all your needs. Please share what features you want, or what is confusing, so we can improve.</p>
                </div>
                <div class="card">
                    <?php if ($errors): ?>
                        <div class="card" style="box-shadow:none;border:1px solid #fee2e2;background:#fef2f2;margin-bottom:12px;">
                            <strong style="color:#b91c1c;">Please fix the following:</strong>
                            <ul style="margin:8px 0 0 18px;color:#b91c1c;">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= sanitize($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <form class="suggestion-form" method="post" action="/suggestions/create.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="pageUrl" value="<?= sanitize($pageUrl); ?>">
                        <div>
                            <label for="category">Category</label>
                            <select id="category" name="category" required>
                                <?php
                                $categories = [
                                    'feature' => 'Feature request',
                                    'bug' => 'Bug report',
                                    'ui' => 'UI/UX feedback',
                                    'performance' => 'Performance',
                                    'other' => 'Other',
                                ];
                                foreach ($categories as $value => $label):
                                    $selected = $value === $category ? 'selected' : '';
                                    ?>
                                    <option value="<?= sanitize($value); ?>" <?= $selected; ?>><?= sanitize($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="title">Title</label>
                            <input id="title" name="title" type="text" minlength="5" maxlength="80" value="<?= sanitize($title); ?>" required>
                        </div>
                        <div>
                            <label for="message">Message</label>
                            <textarea id="message" name="message" minlength="20" maxlength="2000" required><?= sanitize($message); ?></textarea>
                        </div>
                        <div class="suggestion-meta">
                            <div>Which page were you on? <strong><?= sanitize($pageUrl !== '' ? $pageUrl : 'Not provided'); ?></strong></div>
                            <div>Device detected: <strong><?= sanitize($deviceHint); ?></strong></div>
                        </div>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <button class="btn" type="submit">Share Suggestion</button>
                            <a class="btn secondary" href="/home.php">Back to Home</a>
                        </div>
                    </form>
                </div>
            </div>
            <?php
        });
        return;
    }

    suggestion_rate_limit_record($rateKey);
    suggestion_store($user, [
        'category' => $category,
        'title' => $title,
        'message' => $message,
        'pageUrl' => $pageUrl,
    ], $deviceHint);

    redirect('/suggestions/new.php?success=1');
});
