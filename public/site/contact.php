<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    record_public_visit('/site/contact.php');

    $title = get_app_config()['appName'] . ' | Contact';
    $contact = public_contact_details();

    render_layout($title, function () use ($contact) {
        ?>
        <style>
            <?= public_theme_css(); ?>
            .contact-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        </style>
        <section class="card" style="display:grid;gap:14px;">
            <h1 style="margin:0;">Contact YOJAK</h1>
            <p class="muted" style="margin:0;">We are ready to help contractors and departments onboard quickly.</p>
            <div class="contact-grid">
                <div>
                    <div class="muted" style="font-size:12px;">Mobile</div>
                    <div><a href="tel:<?= sanitize($contact['mobile']); ?>"><?= sanitize($contact['mobile']); ?></a></div>
                </div>
                <div>
                    <div class="muted" style="font-size:12px;">Email</div>
                    <div><a href="mailto:<?= sanitize($contact['email']); ?>"><?= sanitize($contact['email']); ?></a></div>
                </div>
                <div>
                    <div class="muted" style="font-size:12px;">Social</div>
                    <div style="display:grid;gap:6px;">
                        <a href="<?= sanitize($contact['instagramUrl']); ?>" target="_blank" rel="noopener">Instagram: <?= sanitize($contact['instagram']); ?></a>
                        <a href="<?= sanitize($contact['facebookUrl']); ?>" target="_blank" rel="noopener">Facebook: <?= sanitize($contact['facebook']); ?></a>
                    </div>
                </div>
            </div>
            <div class="card" style="background:#f8fbff; border-color:#e4e9f2;">
                <strong>Quick help</strong>
                <p class="muted" style="margin:6px 0 0;">Share your tender/workorder details and we will guide you through the documentation steps.</p>
            </div>
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
