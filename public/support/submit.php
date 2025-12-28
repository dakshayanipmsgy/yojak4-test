<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/');
    }
    require_csrf();
    $user = current_user();
    if (!$user || !in_array(($user['type'] ?? ''), ['contractor', 'department', 'superadmin'], true)) {
        redirect('/auth/login.php');
    }

    $errors = support_validate_ticket($_POST);
    if ($errors) {
        set_flash('error', implode(' ', $errors));
        if (($user['type'] ?? '') === 'department') {
            redirect('/department/support.php');
        }
        redirect('/contractor/support.php');
    }

    $ticket = support_store_ticket($_POST, $_FILES);

    $title = get_app_config()['appName'] . ' | Support Submitted';
    render_layout($title, function () use ($ticket) {
        ?>
        <div class="card">
            <h2><?= sanitize('Thanks! We received it.'); ?></h2>
            <p class="muted">Ticket ID: <strong><?= sanitize($ticket['ticketId']); ?></strong></p>
            <p><?= sanitize('Our team will review and get back to you.'); ?></p>
            <div class="buttons">
                <a class="btn" href="/">Return home</a>
            </div>
        </div>
        <?php
    });
});
