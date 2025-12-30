<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = current_user();
    $target = resolve_user_dashboard($user);
    if ($target) {
        log_home_redirect($user['type'] ?? 'unknown', $target, 'redirect_from_public');
        redirect($target);
    }
    if ($user && !$target) {
        log_home_redirect($user['type'] ?? 'unknown', null, 'unknown_type');
        logout_user();
        redirect('/site/index.php');
    }
    $title = get_app_config()['appName'] . ' | ' . t('welcome_title');
    $lang = get_language();
    $text = [
        'heroTitle' => [
            'hi' => 'दस्तावेज़ मिनटों में—दिनों में नहीं।',
            'en' => 'Documents in minutes—not days.',
        ],
        'heroSupport' => [
            'hi' => 'कॉपी-पेस्ट छोड़िए, काम तेज़ कीजिए।',
            'en' => 'Skip copy-paste. Move faster with confidence.',
        ],
        'ctaPrimary' => [
            'hi' => 'विशेषताएं देखें',
            'en' => 'Explore Features',
        ],
        'ctaSecondary' => [
            'hi' => 'ऑफ़लाइन टेंडर तैयारी शुरू करें',
            'en' => 'Start Offline Tender Prep',
        ],
        'ctaSecondaryHint' => [
            'hi' => 'ठेकेदार लॉगिन पथ से सीधे तैयारी जारी रखें।',
            'en' => 'Head straight into the contractor path for offline prep.',
        ],
        'heroHighlights' => [
            'hi' => 'क्यों ठेकेदार पहले?',
            'en' => 'Contractor-first advantages',
        ],
        'signedIn' => [
            'hi' => 'आप साइन-इन हैं। सीधे डैशबोर्ड पर जाएं।',
            'en' => "You're signed in. Go to Dashboard.",
        ],
        'signedInCta' => [
            'hi' => 'डैशबोर्ड खोलें',
            'en' => 'Open Dashboard',
        ],
        'featuresTitle' => [
            'hi' => 'आप क्या कर सकते हैं',
            'en' => 'What you can do',
        ],
        'featuresSupport' => [
            'hi' => 'ऑफ़लाइन टेंडर से लेकर सुरक्षित दस्तावेज़ों तक—सब कुछ एक ही जगह।',
            'en' => 'From offline tenders to secure docs—everything in one place.',
        ],
        'howTitle' => [
            'hi' => 'कैसे काम करता है',
            'en' => 'How it works',
        ],
        'howSupport' => [
            'hi' => 'तीन आसान चरण, ताकि टीम तुरंत काम शुरू कर सके।',
            'en' => 'Three simple steps so teams can start quickly.',
        ],
        'audienceTitle' => [
            'hi' => 'किसके लिए बना है',
            'en' => 'Built for',
        ],
        'audienceSupport' => [
            'hi' => 'साफ़ मार्गदर्शन ताकि सही पोर्टल पर पहुँचें।',
            'en' => 'Clear guidance so you land in the right portal.',
        ],
        'resourcesTitle' => [
            'hi' => 'सहायता और अपडेट',
            'en' => 'Support & updates',
        ],
        'resourcesSupport' => [
            'hi' => 'लॉगिन के बाद सहायता इनबॉक्स उपलब्ध है। आपके विभाग का संपर्क विवरण वहीं मिलता है।',
            'en' => 'Support inbox is available after you sign in. Department contacts are listed there.',
        ],
        'jharkhand' => [
            'hi' => 'झारखंड-प्रथम रोलआउट के लिए तैयार।',
            'en' => 'Built for Jharkhand-first rollout.',
        ],
    ];

    $featureCards = [
        [
            'icon' => '🧭',
            'title' => ['hi' => 'ऑफ़लाइन टेंडर तैयारी', 'en' => 'Offline Tender Prep'],
            'desc' => [
                'hi' => 'NIT अपलोड करें, स्वचालित चेकलिस्ट पाएं और सबमिशन पैक तैयार करें।',
                'en' => 'Upload the NIT, get an auto-checklist, and prep the submission pack.',
            ],
        ],
        [
            'icon' => '🔐',
            'title' => ['hi' => 'डिजिटल वॉल्ट', 'en' => 'Digital Vault'],
            'desc' => [
                'hi' => 'GST, PAN, ITR, बैंक विवरण और शपथपत्र सुरक्षित और ताज़ा रखें।',
                'en' => 'Keep GST, PAN, ITR, bank details, and affidavits secure and updated.',
            ],
        ],
        [
            'icon' => '📦',
            'title' => ['hi' => 'पैक जनरेटर', 'en' => 'Pack Generator'],
            'desc' => [
                'hi' => 'प्रिंट या ZIP के रूप में सबमिशन सेट तैयार करें—आरएफपी के अनुरूप।',
                'en' => 'Generate submission sets as printouts or ZIPs aligned to the RFP.',
            ],
        ],
        [
            'icon' => '⏰',
            'title' => ['hi' => 'रिमाइंडर्स और ट्रैकिंग', 'en' => 'Reminders & Tracking'],
            'desc' => [
                'hi' => 'डेडलाइन, मीलस्टोन और पैक की स्थिति एक ही दृश्य में देखें।',
                'en' => 'Watch deadlines, milestones, and pack status in one view.',
            ],
        ],
    ];

    $steps = [
        [
            'label' => ['hi' => 'PDF अपलोड करें', 'en' => 'Upload tender PDF'],
            'desc' => ['hi' => 'ऑफ़लाइन NIT/PDF जोड़ें ताकि टेम्पलेट्स तुरंत मिलें।', 'en' => 'Add the offline NIT/PDF to unlock ready formats.'],
        ],
        [
            'label' => ['hi' => 'चेकलिस्ट + फॉर्मेट', 'en' => 'Checklist & formats'],
            'desc' => ['hi' => 'अनिवार्य दस्तावेज़ों की सूची और भरे जाने वाले फॉर्म अपने आप मिलते हैं।', 'en' => 'Get required documents and ready-to-fill formats automatically.'],
        ],
        [
            'label' => ['hi' => 'प्रिंट/ZIP पैक', 'en' => 'Print/ZIP pack'],
            'desc' => ['hi' => 'सबमिशन पैक को प्रिंट या ZIP के रूप में डाउनलोड करें और ट्रैक करें।', 'en' => 'Download the submission pack as printouts or ZIP and track it.'],
        ],
    ];

    $audiences = [
        [
            'title' => ['hi' => 'ठेकेदारों के लिए', 'en' => 'For Contractors'],
            'desc' => [
                'hi' => 'ऑफ़लाइन टेंडर तैयारी, दस्तावेज़ वॉल्ट और अलर्ट एक ही डैशबोर्ड में।',
                'en' => 'Offline tender prep, document vault, and alerts in one dashboard.',
            ],
            'cta' => '/contractor/login.php',
            'ctaLabel' => ['hi' => 'Contractor Login', 'en' => 'Login as Contractor'],
        ],
        [
            'title' => ['hi' => 'विभागों के लिए', 'en' => 'For Departments'],
            'desc' => [
                'hi' => 'दस्तावेज़ प्राप्त करें, वर्कफ़्लो ट्रैक करें और अनुमोदन सरल करें।',
                'en' => 'Receive packs, track workflows, and streamline approvals.',
            ],
            'cta' => '/department/login.php',
            'ctaLabel' => ['hi' => 'Department Login', 'en' => 'Login as Department'],
        ],
    ];

    $dashboardLinks = [
        'superadmin' => '/superadmin/dashboard.php',
        'department' => '/department/dashboard.php',
        'contractor' => '/contractor/dashboard.php',
        'employee' => '/staff/dashboard.php',
    ];

    render_layout($title, function () use ($user, $lang, $text, $featureCards, $steps, $audiences, $dashboardLinks) {
        $dashboardLink = null;
        if ($user) {
            $type = $user['type'] ?? '';
            if (isset($dashboardLinks[$type])) {
                $dashboardLink = $dashboardLinks[$type];
            }
        }
        ?>
        <style>
            .hero-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 16px;
                align-items: stretch;
            }
            .hero-card {
                display: grid;
                gap: 10px;
            }
            .pill.accent {
                border-color: rgba(46,160,67,0.4);
                color: #c9d1d9;
                background: rgba(46,160,67,0.1);
            }
            .pill.toggle {
                cursor: pointer;
                background: #1f6feb;
                border-color: #144ea3;
                color: #fff;
            }
            .lead { font-size: 18px; line-height: 1.5; }
            .section-card { margin-top: 18px; }
            .section-header { display: grid; gap: 6px; margin-bottom: 12px; }
            .grid { display: grid; gap: 12px; }
            .features-grid { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
            .feature-card { border: 1px solid #26303d; background: linear-gradient(180deg, #0f1724, #0d1117); }
            .feature-icon { font-size: 22px; }
            .steps-grid { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); counter-reset: step; }
            .step-card { position: relative; padding-top: 32px; }
            .step-card::before {
                counter-increment: step;
                content: counter(step);
                position: absolute;
                top: 12px;
                left: 12px;
                width: 28px;
                height: 28px;
                border-radius: 8px;
                background: #1f6feb;
                display: grid;
                place-items: center;
                font-weight: 800;
                color: #fff;
                box-shadow: 0 8px 18px rgba(31,111,235,0.25);
            }
            .audience-grid { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
            .notice { border: 1px solid #2ea043; background: rgba(46,160,67,0.08); display: grid; gap: 8px; }
            .muted.small { font-size: 13px; }
            .highlight-card ul { padding-left: 16px; margin: 0; display: grid; gap: 8px; }
            .highlight-card li { color: #c9d1d9; }
            .footer-note { display: flex; flex-direction: column; gap: 8px; }
            @media (max-width: 720px) {
                .lead { font-size: 16px; }
            }
        </style>

        <?php if ($dashboardLink): ?>
            <div class="card notice">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                    <div><?= sanitize($text['signedIn'][$lang]); ?></div>
                    <a class="btn" href="<?= sanitize($dashboardLink); ?>"><?= sanitize($text['signedInCta'][$lang]); ?></a>
                </div>
            </div>
        <?php endif; ?>

        <section class="hero-grid">
            <div class="card hero-card">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span class="pill accent"><?= sanitize(t('home_tagline')); ?></span>
                    <button class="pill toggle" type="button" id="hero-toggle"><?= sanitize($lang === 'hi' ? 'हिन्दी / English' : 'English / हिन्दी'); ?></button>
                </div>
                <h1 style="margin:0;"><?= sanitize($text['heroTitle'][$lang]); ?></h1>
                <p class="muted lead" style="margin:0;"><?= sanitize($text['heroSupport'][$lang]); ?></p>
                <div class="buttons">
                    <a class="btn" href="#features"><?= sanitize($text['ctaPrimary'][$lang]); ?></a>
                    <a class="btn secondary" href="/contractor/login.php"><?= sanitize($text['ctaSecondary'][$lang]); ?></a>
                </div>
                <p class="muted small" style="margin:0;"><?= sanitize($text['ctaSecondaryHint'][$lang]); ?></p>
            </div>
            <div class="card hero-card highlight-card">
                <div class="section-header" style="margin-bottom:8px;">
                    <h3 style="margin:0;"><?= sanitize($text['heroHighlights'][$lang]); ?></h3>
                    <p class="muted" style="margin:0;"><?= sanitize('Secure sessions, CSRF protection, and device-aware safeguards.'); ?></p>
                </div>
                <ul>
                    <li><?= sanitize('Offline tenders get the same guardrails as online flows.'); ?></li>
                    <li><?= sanitize('Language preference sticks via session + cookie across the site.'); ?></li>
                    <li><?= sanitize('Friendly error handling with logging to keep pages responsive.'); ?></li>
                </ul>
                <div class="buttons" style="margin-top:12px;">
                    <a class="btn secondary" href="/health.php"><?= sanitize('Platform Health'); ?></a>
                </div>
            </div>
        </section>

        <section class="card section-card" id="features">
            <div class="section-header">
                <h2 style="margin:0;"><?= sanitize($text['featuresTitle'][$lang]); ?></h2>
                <p class="muted" style="margin:0;"><?= sanitize($text['featuresSupport'][$lang]); ?></p>
            </div>
            <div class="grid features-grid">
                <?php foreach ($featureCards as $feature): ?>
                    <div class="card feature-card">
                        <div class="feature-icon" aria-hidden="true"><?= sanitize($feature['icon']); ?></div>
                        <h3 style="margin:8px 0 6px 0;"><?= sanitize($feature['title'][$lang]); ?></h3>
                        <p class="muted" style="margin:0;">
                            <?= sanitize($feature['desc'][$lang]); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card section-card">
            <div class="section-header">
                <h2 style="margin:0;"><?= sanitize($text['howTitle'][$lang]); ?></h2>
                <p class="muted" style="margin:0;"><?= sanitize($text['howSupport'][$lang]); ?></p>
            </div>
            <div class="grid steps-grid">
                <?php foreach ($steps as $step): ?>
                    <div class="card step-card">
                        <h3 style="margin:0 0 6px 0;"><?= sanitize($step['label'][$lang]); ?></h3>
                        <p class="muted" style="margin:0;"><?= sanitize($step['desc'][$lang]); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card section-card">
            <div class="section-header">
                <h2 style="margin:0;"><?= sanitize($text['audienceTitle'][$lang]); ?></h2>
                <p class="muted" style="margin:0;"><?= sanitize($text['audienceSupport'][$lang]); ?></p>
            </div>
            <div class="grid audience-grid">
                <?php foreach ($audiences as $audience): ?>
                    <div class="card" style="display:grid;gap:8px;">
                        <h3 style="margin:0;"><?= sanitize($audience['title'][$lang]); ?></h3>
                        <p class="muted" style="margin:0;"><?= sanitize($audience['desc'][$lang]); ?></p>
                        <div class="buttons">
                            <a class="btn" href="<?= sanitize($audience['cta']); ?>"><?= sanitize($audience['ctaLabel'][$lang]); ?></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card section-card footer-note">
            <div class="section-header" style="margin-bottom:0;">
                <h3 style="margin:0;"><?= sanitize($text['resourcesTitle'][$lang]); ?></h3>
                <p class="muted" style="margin:0;"><?= sanitize($text['resourcesSupport'][$lang]); ?></p>
            </div>
            <div class="pill" style="display:inline-block;align-self:flex-start;">
                <?= sanitize($text['jharkhand'][$lang]); ?>
            </div>
        </section>
        <script>
            (function() {
                const toggle = document.getElementById('hero-toggle');
                if (!toggle) return;

                toggle.addEventListener('click', () => {
                    const langSelect = document.querySelector('.lang-toggle select');
                    const current = (langSelect && langSelect.value === 'en') ? 'en' : 'hi';
                    const next = current === 'hi' ? 'en' : 'hi';
                    if (langSelect && langSelect.form) {
                        langSelect.value = next;
                        langSelect.form.submit();
                        return;
                    }
                    const url = new URL(window.location.href);
                    url.searchParams.set('lang', next);
                    window.location.href = url.toString();
                });
            })();
        </script>
        <?php
    });
});
