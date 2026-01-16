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
        .top-contact .contact-icon-link {
            border-color: var(--public-border);
            background: #ffffff;
            color: var(--public-text);
        }
        .top-contact .contact-icon-link:hover {
            background: #eef2ff;
            border-color: #d6e0ff;
            color: var(--public-accent);
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
        .footer-contact-icons {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
            align-items: center;
        }
        .footer-contact-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            min-width: 86px;
        }
        .footer-contact-link {
            width: 46px;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            border: 1px solid var(--public-border);
            background: #f8fafc;
            color: var(--public-text);
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }
        .footer-contact-link svg {
            width: 22px;
            height: 22px;
            display: block;
        }
        .footer-contact-link:hover {
            background: #eef2ff;
            border-color: #d6e0ff;
            color: var(--public-accent);
        }
        .footer-contact-label {
            font-size: 12px;
            color: var(--public-muted);
            text-align: center;
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
            <div class="footer-contact-icons">
                <div class="footer-contact-item">
                    <a class="footer-contact-link" href="tel:<?= sanitize($contact['mobile']); ?>" aria-label="Call YOJAK" title="Call <?= sanitize($contact['mobile']); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M2.25 6.75c0 7.56 6.19 13.75 13.75 13.75h2.25a1.5 1.5 0 0 0 1.5-1.5v-2.27a1.5 1.5 0 0 0-1.06-1.44l-3.12-.93a1.5 1.5 0 0 0-1.56.4l-1.3 1.3a12.35 12.35 0 0 1-5.18-5.18l1.3-1.3a1.5 1.5 0 0 0 .4-1.56l-.93-3.12A1.5 1.5 0 0 0 6.27 2.25H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/>
                        </svg>
                    </a>
                    <span class="footer-contact-label"><?= sanitize($labels['phone'] ?? 'Mobile'); ?></span>
                </div>
                <div class="footer-contact-item">
                    <a class="footer-contact-link" href="mailto:<?= sanitize($contact['email']); ?>" aria-label="Email YOJAK" title="Email <?= sanitize($contact['email']); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v9a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 16.5v-9Z"/>
                            <path d="m3.75 7.5 8.25 6 8.25-6"/>
                        </svg>
                    </a>
                    <span class="footer-contact-label"><?= sanitize($labels['email'] ?? 'Email'); ?></span>
                </div>
                <div class="footer-contact-item">
                    <a class="footer-contact-link" href="<?= sanitize($contact['instagramUrl']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram YOJAK" title="Instagram: <?= sanitize($contact['instagram']); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3.5" y="3.5" width="17" height="17" rx="4"/>
                            <circle cx="12" cy="12" r="4"/>
                            <circle cx="17.25" cy="6.75" r="1"/>
                        </svg>
                    </a>
                    <span class="footer-contact-label">Instagram</span>
                </div>
                <div class="footer-contact-item">
                    <a class="footer-contact-link" href="<?= sanitize($contact['facebookUrl']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook YOJAK" title="Facebook: <?= sanitize($contact['facebook']); ?>">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M13.5 8.5h2V6h-2c-1.66 0-3 1.34-3 3v2h-2v2.5h2v6h2.5v-6h2l.5-2.5h-2.5V9c0-.28.22-.5.5-.5Z"/>
                        </svg>
                    </a>
                    <span class="footer-contact-label">Facebook</span>
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
