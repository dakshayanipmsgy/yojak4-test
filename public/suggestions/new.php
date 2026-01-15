<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_login();
    if (!in_array(($user['type'] ?? ''), ['contractor', 'department'], true)) {
        render_error_page('Only contractor or department users can submit suggestions.');
        return;
    }

    $pageUrl = suggestion_normalize_page_url((string)($_GET['page'] ?? ($_SERVER['HTTP_REFERER'] ?? '')));
    $deviceHint = suggestion_device_hint($_SERVER['HTTP_USER_AGENT'] ?? '');
    $success = !empty($_GET['success']);

    $title = get_app_config()['appName'] . ' | Share a Suggestion';
    render_layout($title, function () use ($pageUrl, $deviceHint, $success) {
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
            @media (max-width: 720px) {
                .suggestion-form {
                    max-width: 100%;
                }
            }
        </style>
        <div class="suggestion-wrapper">
            <div class="card">
                <h2 style="margin-bottom:6px;">Share a suggestion</h2>
                <p class="muted" style="margin:0;">We are building YOJAK to fulfill all your needs. Please share what features you want, or what is confusing, so we can improve.</p>
            </div>
            <?php if ($success): ?>
                <div class="card">
                    <h3 style="margin-bottom:6px;">Thanks! Your suggestion was saved.</h3>
                    <p class="muted" style="margin:0 0 12px 0;">We appreciate your feedback and will review it soon.</p>
                    <a class="btn" href="/suggestions/new.php">Submit another suggestion</a>
                </div>
            <?php else: ?>
                <div class="card">
                    <form class="suggestion-form" method="post" action="/suggestions/create.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="pageUrl" value="<?= sanitize($pageUrl); ?>">
                        <div>
                            <label for="category">Category</label>
                            <select id="category" name="category" required>
                                <option value="feature">Feature request</option>
                                <option value="bug">Bug report</option>
                                <option value="ui">UI/UX feedback</option>
                                <option value="performance">Performance</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="title">Title</label>
                            <input id="title" name="title" type="text" minlength="5" maxlength="80" placeholder="Short title" required>
                        </div>
                        <div>
                            <label for="message">Message</label>
                            <textarea id="message" name="message" minlength="20" maxlength="2000" placeholder="Share what you need or what feels confusing" required></textarea>
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
            <?php endif; ?>
        </div>
        <?php
    });
});
