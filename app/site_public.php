<?php
declare(strict_types=1);

function public_contact_details(): array
{
    return [
        'mobile' => '7070278178',
        'email' => 'connect@yojak.co.in',
        'instagram' => 'yojak.jh',
        'facebook' => 'yojak.jh',
        'instagramUrl' => 'https://instagram.com/yojak.jh',
        'facebookUrl' => 'https://facebook.com/yojak.jh',
    ];
}

function public_theme_css(): string
{
    return <<<CSS
        :root {
            --public-bg: #f6f8fc;
            --public-surface: #ffffff;
            --public-text: #0b1320;
            --public-muted: #5c6b7a;
            --public-border: #e4e9f2;
            --public-accent: #1f6feb;
            --public-accent-dark: #184a9c;
            --public-accent-soft: #eef2ff;
        }
        body {
            background: var(--public-bg);
            color: var(--public-text);
        }
        header {
            background: rgba(255,255,255,0.95);
            border-bottom: 1px solid var(--public-border);
        }
        .nav-links a, .nav-links form button {
            color: var(--public-text);
        }
        .nav-links a:hover, .nav-links form button:hover {
            background: #f1f4f9;
        }
        .brand-logo {
            background: linear-gradient(135deg, #1f6feb, #0ea5e9);
        }
        .card {
            background: var(--public-surface);
            border-color: var(--public-border);
            box-shadow: 0 18px 30px rgba(15, 23, 42, 0.08);
        }
        .muted { color: var(--public-muted); }
        .pill {
            background: var(--public-accent-soft);
            border-color: #d6e0ff;
            color: #1e3a8a;
        }
        .btn {
            background: var(--public-accent);
            border-color: var(--public-accent-dark);
            color: #fff;
        }
        .btn.secondary {
            background: #f1f5f9;
            border-color: #d8e0eb;
            color: var(--public-text);
            box-shadow: none;
        }
        .btn.outline {
            background: transparent;
            border-color: #cbd5e1;
            color: var(--public-text);
            box-shadow: none;
        }
        .top-contact {
            background: #f8fafc;
            border-bottom: 1px solid var(--public-border);
            color: var(--public-muted);
        }
        .top-contact a {
            color: var(--public-text);
        }
        .nav-links .primary {
            color: #fff;
        }
        .nav-links .secondary {
            background: #eef2ff;
            border-color: #d6e0ff;
            color: #1e3a8a;
        }
        .footer {
            margin-top: 28px;
            border-top: 1px solid var(--public-border);
            padding-top: 24px;
        }
        .footer a {
            color: var(--public-text);
        }
    CSS;
}

function render_public_footer(array $labels): void
{
    $contact = public_contact_details();
    ?>
    <footer class="footer" id="contact">
        <div class="card" style="display:grid;gap:18px;">
            <div style="display:grid;gap:8px;">
                <h3 style="margin:0;"><?= sanitize($labels['title'] ?? 'Contact'); ?></h3>
                <p class="muted" style="margin:0;"><?= sanitize($labels['support'] ?? 'Reach us for onboarding or questions.'); ?></p>
            </div>
            <div style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                <div>
                    <div class="muted" style="font-size:12px;"><?= sanitize($labels['phone'] ?? 'Mobile'); ?></div>
                    <div><a href="tel:<?= sanitize($contact['mobile']); ?>"><?= sanitize($contact['mobile']); ?></a></div>
                </div>
                <div>
                    <div class="muted" style="font-size:12px;"><?= sanitize($labels['email'] ?? 'Email'); ?></div>
                    <div><a href="mailto:<?= sanitize($contact['email']); ?>"><?= sanitize($contact['email']); ?></a></div>
                </div>
                <div>
                    <div class="muted" style="font-size:12px;"><?= sanitize($labels['social'] ?? 'Social'); ?></div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <a href="<?= sanitize($contact['instagramUrl']); ?>" target="_blank" rel="noopener">Instagram: <?= sanitize($contact['instagram']); ?></a>
                        <a href="<?= sanitize($contact['facebookUrl']); ?>" target="_blank" rel="noopener">Facebook: <?= sanitize($contact['facebook']); ?></a>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                <a href="/site/terms.php"><?= sanitize($labels['terms'] ?? 'Terms & Conditions'); ?></a>
                <span class="muted">•</span>
                <a href="/site/privacy.php"><?= sanitize($labels['privacy'] ?? 'Privacy Policy'); ?></a>
                <span class="muted">•</span>
                <a href="/site/contact.php"><?= sanitize($labels['contact'] ?? 'Contact'); ?></a>
            </div>
            <div class="muted" style="font-size:12px;">© <?= sanitize((string)date('Y')); ?> YOJAK. All rights reserved.</div>
        </div>
    </footer>
    <?php
}
