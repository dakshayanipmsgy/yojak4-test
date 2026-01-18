<?php
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $schemeCode = strtoupper(trim($_GET['schemeCode'] ?? ''));
    if ($schemeCode === '') {
        redirect('/contractor/schemes.php');
    }
    $enabled = contractor_enabled_schemes($user['yojId'] ?? '');
    $version = $enabled[$schemeCode] ?? '';
    if (!$version) {
        set_flash('error', 'Scheme not enabled yet.');
        redirect('/contractor/schemes.php');
    }
    $scheme = load_scheme_version($schemeCode, $version);

    render_layout('Create Case', function () use ($schemeCode, $scheme) {
        ?>
        <style>
            .field { display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
            input { padding:10px; border-radius:8px; border:1px solid var(--border); }
        </style>
        <h1>Create <?= sanitize($scheme['caseLabel'] ?? 'Case'); ?></h1>
        <form method="post" action="/contractor/scheme_case_create.php">
            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
            <input type="hidden" name="schemeCode" value="<?= sanitize($schemeCode); ?>">
            <div class="field">
                <label>Title</label>
                <input name="title" placeholder="Optional title">
            </div>
            <button class="btn" type="submit">Create Case</button>
        </form>
        <?php
    });
});
