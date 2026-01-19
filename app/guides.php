<?php
declare(strict_types=1);

function guide_index_path(): string
{
    return DATA_PATH . '/guides/guide_index.json';
}

function guide_sections_dir(): string
{
    return DATA_PATH . '/guides/sections';
}

function guide_section_path(string $id): string
{
    return guide_sections_dir() . '/' . $id . '.json';
}

function guide_log_path(): string
{
    return DATA_PATH . '/logs/guide.log';
}

function ensure_guides_env(): void
{
    $baseDir = DATA_PATH . '/guides';
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0775, true);
    }
    $sectionsDir = guide_sections_dir();
    if (!is_dir($sectionsDir)) {
        mkdir($sectionsDir, 0775, true);
    }
    $logPath = guide_log_path();
    if (!file_exists($logPath)) {
        touch($logPath);
    }

    $indexPath = guide_index_path();
    if (!file_exists($indexPath)) {
        guide_seed_initial();
    }
}

function guide_seed_initial(): void
{
    $now = now_kolkata()->format(DateTime::ATOM);
    $sections = [];

    $sections[] = [
        'id' => 'GUIDE-OFFLINE-TENDERS',
        'title' => 'Offline Tenders & Assisted Extraction',
        'summary' => 'Upload a tender PDF → get a checklist + annexure formats → fill missing fields → print or export ZIP.',
        'audience' => 'contractor',
        'published' => true,
        'updatedAt' => $now,
        'contentBlocks' => [
            [
                'type' => 'intro',
                'text' => 'YOJAK helps you prepare tender paperwork faster. You upload the tender/NIB/NIT PDF and YOJAK helps create a structured “pack” that includes checklists and ready-to-print annexure templates. This is not a bidding portal and YOJAK does not capture bid rates.',
            ],
            [
                'type' => 'steps',
                'title' => 'Quick Workflow',
                'items' => [
                    'Create Offline Tender: Contractor → Offline Tenders → Create. Upload the tender PDF (NIB/NIT/NIT doc).',
                    'Assisted Extraction (2 options): Option A (Self) use the extraction tool if available. Option B (Assisted Pack v2) send for assisted extraction (superadmin/employee prepares pack).',
                    'Review Pack Summary: check tender title, tender number (if present), deadlines, eligibility docs.',
                    'Checklist: mark what you have and add missing items if needed.',
                    'Annexures & Formats: click “Generate Annexure Formats”; fill missing fields inside YOJAK (or leave blank to handwrite after printing).',
                    'Auto-fill from Profile: ensure your profile has PAN/GST/address/bank/contact so templates auto-fill. New custom fields are remembered so you don’t repeat them next time.',
                    'Print or Export: print sections or Full Pack. For clean PDF: disable “Headers and footers” in the print dialog. Export ZIP for online submission attachments.',
                ],
            ],
            [
                'type' => 'tips',
                'title' => 'Tips',
                'items' => [
                    'Keep your Vault updated (GST, PAN, ITR, registrations) so packs become “Ready” automatically.',
                    'Use reminders for deadlines.',
                ],
            ],
            [
                'type' => 'do_dont',
                'title' => 'What YOJAK will NOT do',
                'do' => [
                    'Keep your packs structured and ready with checklists and templates.',
                ],
                'dont' => [
                    'Store bid value or rates.',
                    'Submit bids on your behalf.',
                ],
            ],
            [
                'type' => 'faq',
                'title' => 'Common issues & fixes',
                'items' => [
                    [
                        'q' => '“My details didn’t fill automatically.”',
                        'a' => 'Update Contractor Profile and Saved Fields Memory, then regenerate annexures.',
                    ],
                    [
                        'q' => '“URL/date printed at bottom.”',
                        'a' => 'Turn OFF Headers and footers in the print dialog for clean PDFs.',
                    ],
                ],
            ],
        ],
    ];

    $sections[] = [
        'id' => 'GUIDE-SCHEMES',
        'title' => 'Schemes (Customer/Case Packs)',
        'summary' => 'Work on a customer/beneficiary as a Case file: packs, documents, workflow, and timeline all in one place.',
        'audience' => 'contractor',
        'published' => true,
        'updatedAt' => $now,
        'contentBlocks' => [
            [
                'type' => 'intro',
                'text' => 'Some work is not just a tender—there are schemes (like government/institutional programs) where you manage customer/beneficiary cases. YOJAK turns each customer into a Case File with packs and documents.',
            ],
            [
                'type' => 'steps',
                'title' => 'Quick Workflow',
                'items' => [
                    'Request Scheme Activation: Contractor → Schemes. Choose a scheme and request activation. Superadmin approves and enables the scheme version.',
                    'Create a Case (Customer/Beneficiary): open the scheme → Create Case. Enter basic customer details.',
                    'Case Overview = Packs: packs act like folders (Application Pack, Compliance Pack, Billing Pack, Handover Pack, etc.).',
                    'Fill Required Fields: if a pack shows “Not Ready”, click “Missing Fields” and fill them.',
                    'Generate Documents: documents pull data from Case fields automatically; print and submit.',
                    'Workflow (if enabled): move the case through steps like Draft → Submitted → Approved. YOJAK blocks step changes if required fields/docs are missing.',
                    'Timeline: every important action is recorded (for audit & clarity).',
                ],
            ],
            [
                'type' => 'tips',
                'title' => 'Tips',
                'items' => [
                    'Reusable fields: once you fill common fields, they auto-fill next time.',
                    'Keep case data clean; it becomes the source of truth for documents.',
                ],
            ],
            [
                'type' => 'faq',
                'title' => 'FAQ',
                'items' => [
                    [
                        'q' => '“Do changes affect old cases when a scheme updates?”',
                        'a' => 'Existing cases stay on their version; new cases use the new version.',
                    ],
                ],
            ],
        ],
    ];

    foreach ($sections as $section) {
        writeJsonAtomic(guide_section_path($section['id']), $section);
    }

    $indexSections = [
        [
            'id' => 'GUIDE-OFFLINE-TENDERS',
            'title' => 'Offline Tenders & Assisted Extraction',
            'published' => true,
            'order' => 1,
            'archived' => false,
        ],
        [
            'id' => 'GUIDE-SCHEMES',
            'title' => 'Schemes (Customer/Case Packs)',
            'published' => true,
            'order' => 2,
            'archived' => false,
        ],
    ];

    $index = [
        'version' => 1,
        'updatedAt' => $now,
        'sections' => $indexSections,
    ];

    writeJsonAtomic(guide_index_path(), $index);
}

function guide_index_entries(): array
{
    ensure_guides_env();
    $index = readJson(guide_index_path());
    $sections = $index['sections'] ?? [];
    if (!is_array($sections)) {
        return [];
    }
    usort($sections, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
    return $sections;
}

function guide_load_section(string $id): ?array
{
    $path = guide_section_path($id);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function guide_save_section(array $section): void
{
    if (empty($section['id'])) {
        throw new RuntimeException('Missing guide id.');
    }
    writeJsonAtomic(guide_section_path($section['id']), $section);
}

function guide_save_index(array $index): void
{
    $index['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(guide_index_path(), $index);
}

function guide_log_event(string $event, array $payload): void
{
    $entry = array_merge([
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => $event,
    ], $payload);

    $handle = fopen(guide_log_path(), 'a');
    if ($handle) {
        flock($handle, LOCK_EX);
        fwrite($handle, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function guide_sanitize_id(string $id): ?string
{
    $clean = strtoupper(trim($id));
    if ($clean === '') {
        return null;
    }
    if (!preg_match('/^[A-Z0-9][A-Z0-9\-]{2,}$/', $clean)) {
        return null;
    }
    return $clean;
}

function guide_generate_id(string $title, array $existingIds): string
{
    $slug = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($title)) ?? '');
    $slug = trim($slug ?? '', '-');
    if ($slug === '') {
        $slug = 'SECTION';
    }
    $base = 'GUIDE-' . $slug;
    $candidate = $base;
    while (in_array($candidate, $existingIds, true)) {
        $candidate = $base . '-' . strtoupper(bin2hex(random_bytes(2)));
    }
    return $candidate;
}

function guide_parse_lines(string $text): array
{
    $lines = preg_split('/\R/', $text) ?: [];
    $lines = array_map('trim', $lines);
    $lines = array_filter($lines, fn($line) => $line !== '');
    return array_values($lines);
}

function guide_collect_blocks_from_post(array $payload): array
{
    $blocks = $payload['blocks'] ?? [];
    if (!is_array($blocks)) {
        return [];
    }

    $normalized = [];
    $allowed = ['intro', 'steps', 'tips', 'faq', 'warning', 'do_dont'];
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $type = strtolower(trim((string)($block['type'] ?? '')));
        if (!in_array($type, $allowed, true)) {
            continue;
        }
        $entry = ['type' => $type];
        $title = trim((string)($block['title'] ?? ''));
        if ($title !== '') {
            $entry['title'] = $title;
        }
        if (in_array($type, ['intro', 'warning'], true)) {
            $text = trim((string)($block['text'] ?? ''));
            if ($text !== '') {
                $entry['text'] = $text;
            }
        } elseif (in_array($type, ['steps', 'tips'], true)) {
            $items = guide_parse_lines((string)($block['items_text'] ?? ''));
            $entry['items'] = $items;
        } elseif ($type === 'do_dont') {
            $entry['do'] = guide_parse_lines((string)($block['do_text'] ?? ''));
            $entry['dont'] = guide_parse_lines((string)($block['dont_text'] ?? ''));
        } elseif ($type === 'faq') {
            $questions = guide_parse_lines((string)($block['faq_questions'] ?? ''));
            $answers = guide_parse_lines((string)($block['faq_answers'] ?? ''));
            $faqItems = [];
            $max = max(count($questions), count($answers));
            for ($i = 0; $i < $max; $i++) {
                $q = $questions[$i] ?? '';
                $a = $answers[$i] ?? '';
                if ($q === '' && $a === '') {
                    continue;
                }
                $faqItems[] = [
                    'q' => $q,
                    'a' => $a,
                ];
            }
            $entry['items'] = $faqItems;
        }
        $normalized[] = $entry;
    }

    return $normalized;
}

function guide_render_text(string $text): string
{
    return nl2br(sanitize($text));
}
