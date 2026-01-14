<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    record_public_visit('/site/terms.php');

    $title = get_app_config()['appName'] . ' | Terms & Conditions';

    render_layout($title, function () {
        ?>
        <style>
            <?= public_theme_css(); ?>
            .legal-section { display: grid; gap: 12px; }
            .legal-section ul { padding-left: 18px; margin: 0; display: grid; gap: 8px; }
        </style>
        <section class="card legal-section">
            <h1 style="margin:0;">Terms &amp; Conditions</h1>
            <p class="muted">Effective for all YOJAK public users and contractors.</p>
            <ul>
                <li>YOJAK is a documentation and workflow preparation platform, not an e-tendering or bidding portal.</li>
                <li>We do not collect or store bid rates, bid values, or competitive pricing information.</li>
                <li>Users are responsible for accuracy of the documents they generate and submit.</li>
                <li>Account access is secured by sessions, role-based access, and CSRF protections.</li>
                <li>Misuse or unauthorized access attempts can result in access suspension.</li>
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
