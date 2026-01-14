<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    record_public_visit('/site/privacy.php');

    $title = get_app_config()['appName'] . ' | Privacy Policy';

    render_layout($title, function () {
        ?>
        <style>
            <?= public_theme_css(); ?>
            .legal-section { display: grid; gap: 12px; }
            .legal-section ul { padding-left: 18px; margin: 0; display: grid; gap: 8px; }
        </style>
        <section class="card legal-section">
            <h1 style="margin:0;">Privacy Policy</h1>
            <p class="muted">We collect only what is needed to provide documentation services.</p>
            <ul>
                <li>We store account details and uploaded documents required for tender/workorder preparation.</li>
                <li>We do not request bid values or pricing details.</li>
                <li>Session security, CSRF protection, and access controls are enforced across the platform.</li>
                <li>Data is stored in JSON files on secure servers with file locking to prevent corruption.</li>
                <li>For questions or data requests, contact us using the details below.</li>
            </ul>
        </section>
        <?php
        render_public_footer([
            'title' => 'Contact',
            'support' => 'Reach us for onboarding or support.',
            'phone' => 'Mobile',
            'email' => 'Email',
            'social' => 'Social',
            'terms' => 'Terms & Conditions',
            'privacy' => 'Privacy Policy',
            'contact' => 'Contact',
        ]);
    });
});
