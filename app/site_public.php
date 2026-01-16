<?php
declare(strict_types=1);

function public_contact_details(): array
{
    return [
        'mobile' => '7070278178',
        'email' => 'connect@yojak.co.in',
        'instagram' => 'yojak.jh',
        'facebook' => 'yojak.jh',
        'instagramUrl' => 'https://www.instagram.com/yojak.jh/',
        'facebookUrl' => 'https://www.facebook.com/yojak.jh/',
    ];
}

function public_contact_icon_svg(string $name): string
{
    switch ($name) {
        case 'phone':
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6.6 2.6c.5-.5 1.3-.5 1.8 0l2.4 2.4c.5.5.6 1.2.3 1.8l-1 1.9a12.1 12.1 0 0 0 5.5 5.5l1.9-1c.6-.3 1.3-.2 1.8.3l2.4 2.4c.5.5.5 1.3 0 1.8l-1.5 1.5c-.7.7-1.7 1-2.7.8a17.7 17.7 0 0 1-7.6-4.3A17.7 17.7 0 0 1 4.3 7.8c-.2-1 .1-2 .8-2.7z"/></svg>';
        case 'email':
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zm0 2v.3l8 5 8-5V7H4zm16 10V9.7l-7.5 4.7a2 2 0 0 1-2.1 0L3 9.7V17h17z"/></svg>';
        case 'instagram':
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm10 2H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3zm-5 3.3A4.7 4.7 0 1 1 7.3 12 4.7 4.7 0 0 1 12 7.3zm0 2A2.7 2.7 0 1 0 14.7 12 2.7 2.7 0 0 0 12 9.3zm6-2.9a1.1 1.1 0 1 1-1.1 1.1A1.1 1.1 0 0 1 18 6.4z"/></svg>';
        case 'facebook':
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M13.5 9H16V6h-2.5a3.5 3.5 0 0 0-3.5 3.5V12H7.5v3H10v6h3v-6h2.4l.6-3H13V9.5A.5.5 0 0 1 13.5 9z"/></svg>';
        default:
            return '';
    }
}

function public_theme_css(): string
{
    return <<<CSS
        :root {
            --public-bg: #ffffff;
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
        .contact-icon-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .contact-icon-stack {
            display: grid;
            gap: 6px;
            justify-items: center;
            text-align: center;
            min-width: 72px;
        }
        .contact-icon {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            border: 1px solid var(--public-border);
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--public-muted);
            transition: all 0.2s ease;
        }
        .contact-icon svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }
        .contact-icon:hover,
        .contact-icon:focus-visible {
            color: var(--public-accent);
            border-color: #c7d2fe;
            box-shadow: 0 6px 16px rgba(31, 111, 235, 0.18);
        }
        .contact-icon-label {
            font-size: 11px;
            color: var(--public-muted);
            letter-spacing: 0.02em;
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
            <div class="contact-icon-row" style="justify-content:space-between;">
                <div class="contact-icon-row">
                    <div class="contact-icon-stack">
                        <a class="contact-icon" href="tel:<?= sanitize($contact['mobile']); ?>" aria-label="Call YOJAK" title="Call <?= sanitize($contact['mobile']); ?>">
                            <?= public_contact_icon_svg('phone'); ?>
                        </a>
                        <div class="contact-icon-label"><?= sanitize($labels['phone'] ?? 'Phone'); ?></div>
                    </div>
                    <div class="contact-icon-stack">
                        <a class="contact-icon" href="mailto:<?= sanitize($contact['email']); ?>" aria-label="Email YOJAK" title="Email <?= sanitize($contact['email']); ?>">
                            <?= public_contact_icon_svg('email'); ?>
                        </a>
                        <div class="contact-icon-label"><?= sanitize($labels['email'] ?? 'Email'); ?></div>
                    </div>
                </div>
                <div class="contact-icon-row">
                    <div class="contact-icon-stack">
                        <a class="contact-icon" href="<?= sanitize($contact['instagramUrl']); ?>" target="_blank" rel="noopener noreferrer" aria-label="YOJAK on Instagram" title="Instagram: <?= sanitize($contact['instagram']); ?>">
                            <?= public_contact_icon_svg('instagram'); ?>
                        </a>
                        <div class="contact-icon-label">Instagram</div>
                    </div>
                    <div class="contact-icon-stack">
                        <a class="contact-icon" href="<?= sanitize($contact['facebookUrl']); ?>" target="_blank" rel="noopener noreferrer" aria-label="YOJAK on Facebook" title="Facebook: <?= sanitize($contact['facebook']); ?>">
                            <?= public_contact_icon_svg('facebook'); ?>
                        </a>
                        <div class="contact-icon-label">Facebook</div>
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
