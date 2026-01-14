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

    $visitState = record_public_visit('/site/index.php');

    $title = get_app_config()['appName'] . ' | ' . t('welcome_title');
    $lang = get_language();
    $text = [
        'tagline' => [
            'hi' => 'ठेकेदारों के लिए दस्तावेज़ प्लेटफ़ॉर्म',
            'en' => 'Contractor-first documentation platform',
        ],
        'heroTitle' => [
            'hi' => 'दस्तावेज़ मिनटों में—दिनों में नहीं।',
            'en' => 'Documents in minutes — not days.',
        ],
        'heroSupport' => [
            'hi' => 'कॉपी-पेस्ट छोड़िए, काम तेज़ कीजिए।',
            'en' => 'Stop copy-paste. Get submission-ready files faster.',
        ],
        'heroBullets' => [
            'hi' => [
                'टेंडर पैक, एनैक्सचर, चेकलिस्ट — प्रिंट के लिए तैयार।',
                'पिछले टेंडर से दोबारा इस्तेमाल होने वाले टेम्पलेट्स।',
                'GST/PAN/ITR और अन्य दस्तावेज़ों का सुरक्षित वॉल्ट।',
            ],
            'en' => [
                'Tender packs, annexures, checklists — ready to print.',
                'Reusable templates from past tenders.',
                'Secure document vault for GST/PAN/ITR and more.',
            ],
        ],
        'ctaPrimary' => [
            'hi' => 'कॉन्ट्रैक्टर',
            'en' => 'Contractor',
        ],
        'ctaSecondary' => [
            'hi' => 'विभाग',
            'en' => 'Department',
        ],
        'ctaTertiary' => [
            'hi' => 'YOJAK (स्टाफ)',
            'en' => 'YOJAK (Staff)',
        ],
        'ctaContractorHelp' => [
            'hi' => 'पैक बनाएं, एनैक्सचर प्रिंट करें',
            'en' => 'Create packs, print annexures',
        ],
        'ctaDepartmentHelp' => [
            'hi' => 'टेम्पलेट्स, टेंडर, वर्कऑर्डर',
            'en' => 'Templates, tenders, workorders',
        ],
        'ctaStaffHelp' => [
            'hi' => 'सुपरएडमिन / कर्मचारी लॉगिन',
            'en' => 'Superadmin / employee login',
        ],
        'whatTitle' => [
            'hi' => 'YOJAK क्या है?',
            'en' => 'What is YOJAK?',
        ],
        'whatLine1' => [
            'hi' => 'YOJAK सरकारी ठेकेदारों को टेंडर/वर्कऑर्डर के दस्तावेज़ जल्दी तैयार करने में मदद करता है।',
            'en' => 'YOJAK helps government contractors prepare tender/workorder paperwork quickly.',
        ],
        'whatLine2' => [
            'hi' => 'यह कोई e-tendering या bidding पोर्टल नहीं है।',
            'en' => 'This is NOT an e-tendering or bidding portal.',
        ],
        'whatLine3' => [
            'hi' => 'हम कभी भी bid rates नहीं मांगते।',
            'en' => 'We never ask for bid rates.',
        ],
        'helpsTitle' => [
            'hi' => 'यह आपकी कैसे मदद करता है',
            'en' => 'How it helps you',
        ],
        'helpsSupport' => [
            'hi' => 'सरल वर्कफ़्लो ताकि टीम तुरंत टेंडर पैक बना सके।',
            'en' => 'Simple workflows so your team can assemble packs fast.',
        ],
        'howTitle' => [
            'hi' => 'कैसे काम करता है',
            'en' => 'How it works',
        ],
        'howSupport' => [
            'hi' => 'तीन आसान चरण — अपलोड से प्रिंट तक।',
            'en' => 'Three easy steps from upload to print.',
        ],
        'templatesTitle' => [
            'hi' => 'टेम्पलेट्स और पैक',
            'en' => 'Templates & Packs',
        ],
        'templatesSupport' => [
            'hi' => 'स्थानीय विभागों के अनुरूप पैक और आसान पुन: उपयोग।',
            'en' => 'Packs aligned to local departments with easy reuse.',
        ],
        'trustTitle' => [
            'hi' => 'भरोसे के साथ तैयार',
            'en' => 'Built for trust',
        ],
        'trustSupport' => [
            'hi' => 'झारखंड-प्रथम, ठेकेदार-प्रथम दृष्टिकोण।',
            'en' => 'Jharkhand-first, contractor-first by design.',
        ],
        'trustCard1Title' => [
            'hi' => 'विश्वसनीय डेटा प्रथाएँ',
            'en' => 'Trusted data practices',
        ],
        'trustCard1Desc' => [
            'hi' => 'सुरक्षित सत्र, CSRF सुरक्षा और सुरक्षित एरर हैंडलिंग।',
            'en' => 'Secure sessions, CSRF protection, and safe error handling.',
        ],
        'trustCard2Title' => [
            'hi' => 'झारखंड-प्रथम रोलआउट',
            'en' => 'Jharkhand-first rollout',
        ],
        'trustCard2Desc' => [
            'hi' => 'स्थानीय ठेकेदार वर्कफ़्लो और ऑफ़लाइन सबमिशन के लिए तैयार।',
            'en' => 'Designed for local contractor workflows and offline submissions.',
        ],
        'faqTitle' => [
            'hi' => 'FAQ',
            'en' => 'FAQ',
        ],
        'footerTitle' => [
            'hi' => 'संपर्क करें',
            'en' => 'Contact us',
        ],
        'footerSupport' => [
            'hi' => 'ऑनबोर्डिंग या सहायता के लिए हमसे संपर्क करें।',
            'en' => 'Reach us for onboarding or support.',
        ],
        'footerPhone' => [
            'hi' => 'मोबाइल',
            'en' => 'Mobile',
        ],
        'footerEmail' => [
            'hi' => 'ईमेल',
            'en' => 'Email',
        ],
        'footerSocial' => [
            'hi' => 'सोशल',
            'en' => 'Social',
        ],
        'footerTerms' => [
            'hi' => 'नियम व शर्तें',
            'en' => 'Terms & Conditions',
        ],
        'footerPrivacy' => [
            'hi' => 'गोपनीयता नीति',
            'en' => 'Privacy Policy',
        ],
        'footerContact' => [
            'hi' => 'संपर्क',
            'en' => 'Contact',
        ],
        'visitorsLabel' => [
            'hi' => 'ठेकेदारों ने YOJAK देखा',
            'en' => 'Contractors explored YOJAK',
        ],
    ];

    $helpCards = [
        [
            'title' => ['hi' => 'PDF अपलोड करें', 'en' => 'Upload tender PDF'],
            'desc' => [
                'hi' => 'टेंडर/NIT जोड़ते ही चेकलिस्ट और डॉक फॉर्मेट मिलते हैं।',
                'en' => 'Add tender/NIT to get instant checklist and formats.',
            ],
        ],
        [
            'title' => ['hi' => 'ऑटो-फिल टेम्पलेट्स', 'en' => 'Auto-fill templates'],
            'desc' => [
                'hi' => 'कंपनी विवरण एक बार डालें और हर टेम्पलेट में भरो।',
                'en' => 'Fill company details once and reuse everywhere.',
            ],
        ],
        [
            'title' => ['hi' => 'प्रिंट/ZIP सबमिशन', 'en' => 'Print/ZIP submission'],
            'desc' => [
                'hi' => 'पूरा पैक प्रिंट करें या ZIP डाउनलोड करें।',
                'en' => 'Print the full pack or export as ZIP.',
            ],
        ],
        [
            'title' => ['hi' => 'डॉक वॉल्ट', 'en' => 'Document vault'],
            'desc' => [
                'hi' => 'GST, PAN, ITR जैसे दस्तावेज़ सुरक्षित रखें और पुन: उपयोग करें।',
                'en' => 'Store GST, PAN, ITR safely and reuse instantly.',
            ],
        ],
    ];

    $steps = [
        [
            'label' => ['hi' => '1. टेंडर/वर्कऑर्डर अपलोड', 'en' => '1. Upload tender/workorder'],
            'desc' => ['hi' => 'PDF जोड़ते ही सिस्टम पैक बनाना शुरू करता है।', 'en' => 'Upload a PDF and the pack gets prepared instantly.'],
        ],
        [
            'label' => ['hi' => '2. पैक जनरेट करें', 'en' => '2. Generate the pack'],
            'desc' => ['hi' => 'चेकलिस्ट + फॉर्मेट अपने आप तैयार होते हैं।', 'en' => 'Checklist and formats are auto-generated.'],
        ],
        [
            'label' => ['hi' => '3. भरें और सबमिट', 'en' => '3. Fill and submit'],
            'desc' => ['hi' => 'अधूरी जानकारी भरें और प्रिंट/ZIP निकालें।', 'en' => 'Fill missing details and export to print/ZIP.'],
        ],
    ];

    $templateCards = [
        [
            'title' => ['hi' => 'टेंडर पैक', 'en' => 'Tender packs'],
            'desc' => ['hi' => 'सम्पूर्ण पैक — चेकलिस्ट, एनैक्सचर, फॉर्मेट।', 'en' => 'Complete packs with checklist, annexures, formats.'],
        ],
        [
            'title' => ['hi' => 'रेडी टेम्पलेट', 'en' => 'Ready templates'],
            'desc' => ['hi' => 'पिछले टेंडर से तैयार टेम्पलेट्स का रिपीट उपयोग।', 'en' => 'Reuse templates from previous tenders.'],
        ],
        [
            'title' => ['hi' => 'ऑफ़लाइन सबमिशन', 'en' => 'Offline submission'],
            'desc' => ['hi' => 'कागज़ी सबमिशन के लिए प्रिंट तैयार पैक।', 'en' => 'Print-ready packs for offline submission.'],
        ],
    ];

    $faq = [
        [
            'q' => ['hi' => 'क्या YOJAK e-tendering पोर्टल है?', 'en' => 'Is YOJAK an e-tendering portal?'],
            'a' => ['hi' => 'नहीं। यह केवल दस्तावेज़ तैयार करने का प्लेटफ़ॉर्म है।', 'en' => 'No. It is only for preparing documentation packs.'],
        ],
        [
            'q' => ['hi' => 'क्या आप bid rates स्टोर करते हैं?', 'en' => 'Do you store bid rates?'],
            'a' => ['hi' => 'कभी नहीं। हम bid value नहीं लेते।', 'en' => 'Never. We do not collect bid values.'],
        ],
        [
            'q' => ['hi' => 'अगर मेरा विभाग YOJAK पर नहीं है?', 'en' => 'Can I use it if my department is not on YOJAK?'],
            'a' => ['hi' => 'हाँ। ऑफ़लाइन टेंडर मोड में आप पैक तैयार कर सकते हैं।', 'en' => 'Yes. Use offline mode to prepare your packs.'],
        ],
    ];

    $visitors = (int)($visitState['totalUniqueVisitors'] ?? 0);
    $visitorsDisplay = number_format($visitors) . '+';

    render_layout($title, function () use ($lang, $text, $helpCards, $steps, $templateCards, $faq, $visitorsDisplay) {
        ?>
        <style>
            <?= public_theme_css(); ?>
            .public-hero {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 20px;
                align-items: center;
            }
            .hero-bullets {
                display: grid;
                gap: 10px;
                padding-left: 18px;
                margin: 12px 0 0;
            }
            .hero-card {
                display: grid;
                gap: 14px;
            }
            .cta-row {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 12px;
            }
            .cta-column {
                display: grid;
                gap: 6px;
            }
            .cta-helper {
                font-size: 12px;
                color: var(--public-muted);
            }
            .section {
                margin-top: 28px;
                display: grid;
                gap: 14px;
            }
            .section-header {
                display: grid;
                gap: 6px;
            }
            .grid-3 {
                display: grid;
                gap: 14px;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }
            .grid-4 {
                display: grid;
                gap: 14px;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }
            .notice-card {
                border-left: 4px solid var(--public-accent);
                background: #f8fbff;
            }
            .trust-strip {
                display: grid;
                gap: 16px;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                align-items: center;
            }
            .trust-metric {
                display: grid;
                gap: 6px;
                padding: 16px;
                border-radius: 12px;
                border: 1px solid var(--public-border);
                background: #f8fbff;
                font-weight: 700;
            }
            .faq-card {
                display: grid;
                gap: 8px;
                border: 1px solid var(--public-border);
                border-radius: 12px;
                padding: 16px;
                background: #ffffff;
            }
            .faq-card h4 { margin: 0; }
            .lead {
                font-size: 18px;
                line-height: 1.6;
            }
            @media (max-width: 720px) {
                .lead { font-size: 16px; }
            }
        </style>

        <section class="public-hero">
            <div class="card hero-card">
                <span class="pill" style="width:fit-content;"><?= sanitize($text['tagline'][$lang]); ?></span>
                <h1 style="margin:0; font-size: clamp(28px, 4vw, 40px);">
                    <?= sanitize($text['heroTitle'][$lang]); ?>
                </h1>
                <p class="muted lead" style="margin:0;">
                    <?= sanitize($text['heroSupport'][$lang]); ?>
                </p>
                <ul class="hero-bullets">
                    <?php foreach ($text['heroBullets'][$lang] as $bullet): ?>
                        <li><?= sanitize($bullet); ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="cta-row">
                    <div class="cta-column">
                        <a class="btn" href="/contractor/login.php"><?= sanitize($text['ctaPrimary'][$lang]); ?></a>
                        <div class="cta-helper"><?= sanitize($text['ctaContractorHelp'][$lang]); ?></div>
                    </div>
                    <div class="cta-column">
                        <a class="btn" href="/department/login.php"><?= sanitize($text['ctaSecondary'][$lang]); ?></a>
                        <div class="cta-helper"><?= sanitize($text['ctaDepartmentHelp'][$lang]); ?></div>
                    </div>
                    <div class="cta-column">
                        <a class="btn" href="/site/staff_login.php"><?= sanitize($text['ctaTertiary'][$lang]); ?></a>
                        <div class="cta-helper"><?= sanitize($text['ctaStaffHelp'][$lang]); ?></div>
                    </div>
                </div>
            </div>
            <div class="card hero-card notice-card">
                <h3 style="margin:0;"><?= sanitize($text['whatTitle'][$lang]); ?></h3>
                <p class="muted" style="margin:0;"><?= sanitize($text['whatLine1'][$lang]); ?></p>
                <p class="muted" style="margin:0;"><?= sanitize($text['whatLine2'][$lang]); ?></p>
                <p class="muted" style="margin:0;"><?= sanitize($text['whatLine3'][$lang]); ?></p>
            </div>
        </section>

        <section class="section" id="features">
            <div class="section-header">
                <h2 style="margin:0;"><?= sanitize($text['helpsTitle'][$lang]); ?></h2>
                <p class="muted" style="margin:0;"><?= sanitize($text['helpsSupport'][$lang]); ?></p>
            </div>
            <div class="grid-4">
                <?php foreach ($helpCards as $card): ?>
                    <div class="card" style="display:grid;gap:8px;">
                        <h3 style="margin:0;"><?= sanitize($card['title'][$lang]); ?></h3>
                        <p class="muted" style="margin:0;"><?= sanitize($card['desc'][$lang]); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section" id="how-it-works">
            <div class="section-header">
                <h2 style="margin:0;"><?= sanitize($text['howTitle'][$lang]); ?></h2>
                <p class="muted" style="margin:0;"><?= sanitize($text['howSupport'][$lang]); ?></p>
            </div>
            <div class="grid-3">
                <?php foreach ($steps as $step): ?>
                    <div class="card" style="display:grid;gap:8px;">
                        <h3 style="margin:0;"><?= sanitize($step['label'][$lang]); ?></h3>
                        <p class="muted" style="margin:0;"><?= sanitize($step['desc'][$lang]); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section" id="templates-packs">
            <div class="section-header">
                <h2 style="margin:0;"><?= sanitize($text['templatesTitle'][$lang]); ?></h2>
                <p class="muted" style="margin:0;"><?= sanitize($text['templatesSupport'][$lang]); ?></p>
            </div>
            <div class="grid-3">
                <?php foreach ($templateCards as $card): ?>
                    <div class="card" style="display:grid;gap:8px;">
                        <h3 style="margin:0;"><?= sanitize($card['title'][$lang]); ?></h3>
                        <p class="muted" style="margin:0;"><?= sanitize($card['desc'][$lang]); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 style="margin:0;"><?= sanitize($text['trustTitle'][$lang]); ?></h2>
                <p class="muted" style="margin:0;"><?= sanitize($text['trustSupport'][$lang]); ?></p>
            </div>
            <div class="trust-strip">
                <div class="trust-metric">
                    <div style="font-size:28px;"><?= sanitize($visitorsDisplay); ?></div>
                    <div class="muted" style="font-weight:500;"><?= sanitize($text['visitorsLabel'][$lang]); ?></div>
                </div>
                <div class="card" style="display:grid;gap:8px;">
                    <strong><?= sanitize($text['trustCard1Title'][$lang]); ?></strong>
                    <span class="muted"><?= sanitize($text['trustCard1Desc'][$lang]); ?></span>
                </div>
                <div class="card" style="display:grid;gap:8px;">
                    <strong><?= sanitize($text['trustCard2Title'][$lang]); ?></strong>
                    <span class="muted"><?= sanitize($text['trustCard2Desc'][$lang]); ?></span>
                </div>
            </div>
        </section>

        <section class="section" id="faq">
            <div class="section-header">
                <h2 style="margin:0;"><?= sanitize($text['faqTitle'][$lang]); ?></h2>
            </div>
            <div class="grid-3">
                <?php foreach ($faq as $item): ?>
                    <div class="faq-card">
                        <h4><?= sanitize($item['q'][$lang]); ?></h4>
                        <p class="muted" style="margin:0;"><?= sanitize($item['a'][$lang]); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <?php
            render_public_footer([
                'title' => $text['footerTitle'][$lang],
                'support' => $text['footerSupport'][$lang],
                'phone' => $text['footerPhone'][$lang],
                'email' => $text['footerEmail'][$lang],
                'social' => $text['footerSocial'][$lang],
                'terms' => $text['footerTerms'][$lang],
                'privacy' => $text['footerPrivacy'][$lang],
                'contact' => $text['footerContact'][$lang],
            ]);
        ?>
        <?php
    });
});
