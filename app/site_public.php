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

function public_icon_svg(string $name): string
{
    switch ($name) {
        case 'phone':
            $path = '<path d="M2.25 5.5c0 8.974 7.276 16.25 16.25 16.25h2.5a1 1 0 0 0 1-1v-3.25a1 1 0 0 0-1-1h-3.5a1 1 0 0 0-1 1v1.25a12.25 12.25 0 0 1-7.5-7.5h1.25a1 1 0 0 0 1-1V6.5a1 1 0 0 0-1-1H3.25a1 1 0 0 0-1 1V5.5Z"/>';
            break;
        case 'email':
            $path = '<path d="M3 5.75A2.75 2.75 0 0 1 5.75 3h12.5A2.75 2.75 0 0 1 21 5.75v8.5A2.75 2.75 0 0 1 18.25 17H5.75A2.75 2.75 0 0 1 3 14.25v-8.5Zm2.2.45a.75.75 0 0 0-.95 1.14l6.95 5.75a1.75 1.75 0 0 0 2.2 0l6.95-5.75a.75.75 0 1 0-.95-1.14l-6.6 5.46a.25.25 0 0 1-.32 0L5.2 6.2Z"/>';
            break;
        case 'instagram':
            $path = '<path d="M7 3.5A3.5 3.5 0 0 0 3.5 7v10A3.5 3.5 0 0 0 7 20.5h10A3.5 3.5 0 0 0 20.5 17V7A3.5 3.5 0 0 0 17 3.5H7Zm5 4a4.5 4.5 0 1 1 0 9a4.5 4.5 0 0 1 0-9Zm0 2a2.5 2.5 0 1 0 0 5a2.5 2.5 0 0 0 0-5Zm5.5-.75a.75.75 0 1 0 0 1.5a.75.75 0 0 0 0-1.5Z"/>';
            break;
        case 'facebook':
            $path = '<path d="M13.25 8.5V6.9c0-.9.4-1.4 1.5-1.4h1.5V3h-2.3C11.1 3 9.75 4.35 9.75 6.8V8.5H8v2.8h1.75V21h3.5v-9.7h2.5l.5-2.8h-3Z"/>';
            break;
        default:
            return '';
    }

    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $path . '</svg>';
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
        .contact-icon-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .contact-icon-link {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            border: 1px solid var(--public-border);
            background: #fff;
            color: var(--public-muted);
            transition: color 0.2s ease, border-color 0.2s ease, background 0.2s ease;
        }
        .contact-icon-link svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }
        .contact-icon-link:hover {
            color: var(--public-accent);
            border-color: #cbd9f7;
            background: #f3f7ff;
        }
        .footer-contact-icons {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }
        .footer-contact-icons .contact-icon-card {
            min-width: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid var(--public-border);
            background: #fff;
            color: var(--public-text);
            text-decoration: none;
        }
        .footer-contact-icons .contact-icon-card svg {
            width: 22px;
            height: 22px;
            fill: currentColor;
        }
        .footer-contact-icons .contact-icon-label {
            font-size: 12px;
            color: var(--public-muted);
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
            <div class="footer-contact-icons" aria-label="<?= sanitize($labels['social'] ?? 'Contact methods'); ?>">
                <a class="contact-icon-card" href="tel:<?= sanitize($contact['mobile']); ?>" aria-label="Call YOJAK" title="Call <?= sanitize($contact['mobile']); ?>">
                    <?= public_icon_svg('phone'); ?>
                    <span class="contact-icon-label"><?= sanitize($labels['phone'] ?? 'Mobile'); ?></span>
                </a>
                <a class="contact-icon-card" href="mailto:<?= sanitize($contact['email']); ?>" aria-label="Email YOJAK" title="Email <?= sanitize($contact['email']); ?>">
                    <?= public_icon_svg('email'); ?>
                    <span class="contact-icon-label"><?= sanitize($labels['email'] ?? 'Email'); ?></span>
                </a>
                <a class="contact-icon-card" href="<?= sanitize($contact['instagramUrl']); ?>" target="_blank" rel="noopener noreferrer" aria-label="YOJAK on Instagram" title="Instagram: <?= sanitize($contact['instagram']); ?>">
                    <?= public_icon_svg('instagram'); ?>
                    <span class="contact-icon-label">Instagram</span>
                </a>
                <a class="contact-icon-card" href="<?= sanitize($contact['facebookUrl']); ?>" target="_blank" rel="noopener noreferrer" aria-label="YOJAK on Facebook" title="Facebook: <?= sanitize($contact['facebook']); ?>">
                    <?= public_icon_svg('facebook'); ?>
                    <span class="contact-icon-label">Facebook</span>
                </a>
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
