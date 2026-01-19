<?php
declare(strict_types=1);

function staff_guide_index_path(): string
{
    return DATA_PATH . '/guides_staff/guide_index.json';
}

function staff_guide_sections_dir(): string
{
    return DATA_PATH . '/guides_staff/sections';
}

function staff_guide_section_path(string $id): string
{
    return staff_guide_sections_dir() . '/' . $id . '.json';
}

function staff_guide_log_path(): string
{
    return DATA_PATH . '/logs/guide_staff.log';
}

function staff_guide_default_sections(): array
{
    $now = now_kolkata()->format(DateTime::ATOM);

    return [
        [
            'id' => 'STAFF-GUIDE-AI-STUDIO',
            'title' => 'AI Studio',
            'summary' => 'Configure AI providers, keys, and models used across YOJAK.',
            'audience' => 'staff',
            'published' => true,
            'updatedAt' => $now,
            'contentBlocks' => [
                [
                    'type' => 'intro',
                    'text' => 'AI Studio manages the AI provider, API key, and model used across YOJAK workflows.',
                ],
                [
                    'type' => 'steps',
                    'title' => 'Steps',
                    'items' => [
                        'Open AI Studio from the superadmin dashboard.',
                        'Select the provider and preferred model.',
                        'Save the API key securely.',
                        'Run a connection test to confirm the model responds.',
                    ],
                ],
                [
                    'type' => 'tips',
                    'title' => 'Common failures to check',
                    'items' => [
                        'Invalid API key or missing permissions.',
                        'Model name not supported by the provider.',
                        'Network restrictions blocking outbound calls.',
                    ],
                ],
            ],
        ],
        [
            'id' => 'STAFF-GUIDE-ASSISTED-PACK-V2',
            'title' => 'Assisted Pack v2',
            'summary' => 'Staff-assisted extraction workflow for contractor tender PDFs.',
            'audience' => 'staff',
            'published' => true,
            'updatedAt' => $now,
            'contentBlocks' => [
                [
                    'type' => 'intro',
                    'text' => 'Assisted Pack v2 helps staff turn a contractor-provided tender PDF into a structured pack with checklists and annexure templates.',
                ],
                [
                    'type' => 'steps',
                    'title' => 'Workflow',
                    'items' => [
                        'Receive the assisted pack request and open the tender PDF.',
                        'Use the external AI prompt/copy workflow to extract key details.',
                        'Validate forbidden bid/rate rules before saving.',
                        'Save the pack and confirm checklist + annexure templates are created.',
                        'Notify the contractor that the pack is ready for review.',
                    ],
                ],
                [
                    'type' => 'tips',
                    'title' => 'Troubleshooting',
                    'items' => [
                        'If validation fails, check for bid value/rate fields and remove them.',
                        'Ensure dates and tender numbers are formatted consistently.',
                        'If sections are missing, re-run extraction on the PDF pages with those details.',
                    ],
                ],
            ],
        ],
        [
            'id' => 'STAFF-GUIDE-SCHEMES',
            'title' => 'Schemes',
            'summary' => 'Build, version, and activate scheme programs for contractors.',
            'audience' => 'staff',
            'published' => true,
            'updatedAt' => $now,
            'contentBlocks' => [
                [
                    'type' => 'intro',
                    'text' => 'Schemes are reusable program templates with versions, packs, and workflow rules.',
                ],
                [
                    'type' => 'steps',
                    'title' => 'Steps',
                    'items' => [
                        'Create or edit a scheme draft and update packs/fields.',
                        'Publish a new version when changes are complete.',
                        'Approve contractor activation requests for the scheme.',
                        'Support runtime issues by checking field requirements and workflow rules.',
                    ],
                ],
            ],
        ],
        [
            'id' => 'STAFF-GUIDE-TENDER-DISCOVERY',
            'title' => 'Tender Discovery',
            'summary' => 'Fetch and verify discovered tenders for contractor browsing.',
            'audience' => 'staff',
            'published' => true,
            'updatedAt' => $now,
            'contentBlocks' => [
                [
                    'type' => 'intro',
                    'text' => 'Tender Discovery pulls tenders from sources for contractors to browse and start offline prep quickly.',
                ],
                [
                    'type' => 'steps',
                    'title' => 'Steps',
                    'items' => [
                        'Run discovery manually or via the scheduled cron endpoint.',
                        'Verify logs for fetch status and errors.',
                        'Deduplicate tenders as needed and review for data quality.',
                        'Confirm contractors can view and start offline prep from the discovered list.',
                    ],
                ],
            ],
        ],
        [
            'id' => 'STAFF-GUIDE-USERS-HUB',
            'title' => 'Users Hub',
            'summary' => 'Manage departments, contractors, and employees from one hub.',
            'audience' => 'staff',
            'published' => true,
            'updatedAt' => $now,
            'contentBlocks' => [
                [
                    'type' => 'intro',
                    'text' => 'The Users hub keeps all user management in one place with quick counts and pending actions.',
                ],
                [
                    'type' => 'steps',
                    'title' => 'Steps',
                    'items' => [
                        'Review pending contractor approvals daily.',
                        'Check department admin issues and assign or fix missing admins.',
                        'Manage employee access and reset requests when needed.',
                        'Use profile settings to update branding or superadmin details.',
                    ],
                ],
                [
                    'type' => 'tips',
                    'title' => 'Common issues',
                    'items' => [
                        'Contractor approval delays: verify phone/email details and documents.',
                        'Department admin missing: create or reassign a department admin user.',
                    ],
                ],
            ],
        ],
        [
            'id' => 'STAFF-GUIDE-ERRORS-LOGS',
            'title' => 'Errors & Logs',
            'summary' => 'View logs, capture error references, and share details for support.',
            'audience' => 'staff',
            'published' => true,
            'updatedAt' => $now,
            'contentBlocks' => [
                [
                    'type' => 'intro',
                    'text' => 'Errors and logs help diagnose runtime problems quickly.',
                ],
                [
                    'type' => 'steps',
                    'title' => 'How to use logs',
                    'items' => [
                        'Open Error Log in the superadmin menu for recent issues.',
                        'Collect the error reference ID and timestamp.',
                        'Check /data/logs/ for related module logs.',
                        'Share the reference, URL, and steps to reproduce with the technical team.',
                    ],
                ],
            ],
        ],
    ];
}

function ensure_staff_guides_env(): void
{
    $baseDir = DATA_PATH . '/guides_staff';
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0775, true);
    }
    $sectionsDir = staff_guide_sections_dir();
    if (!is_dir($sectionsDir)) {
        mkdir($sectionsDir, 0775, true);
    }
    $logPath = staff_guide_log_path();
    if (!file_exists($logPath)) {
        touch($logPath);
    }

    $indexPath = staff_guide_index_path();
    if (!file_exists($indexPath)) {
        staff_guide_seed_initial();
    } else {
        staff_guide_seed_defaults_if_missing();
    }
}

function staff_guide_seed_initial(): void
{
    $sections = staff_guide_default_sections();
    foreach ($sections as $section) {
        writeJsonAtomic(staff_guide_section_path($section['id']), $section);
    }

    $indexSections = [];
    $order = 1;
    foreach ($sections as $section) {
        $indexSections[] = [
            'id' => $section['id'],
            'title' => $section['title'],
            'published' => true,
            'order' => $order,
            'archived' => false,
        ];
        $order++;
    }

    $index = [
        'version' => 1,
        'updatedAt' => now_kolkata()->format(DateTime::ATOM),
        'sections' => $indexSections,
    ];

    writeJsonAtomic(staff_guide_index_path(), $index);
}

function staff_guide_seed_defaults_if_missing(): void
{
    $index = readJson(staff_guide_index_path());
    $entries = $index['sections'] ?? [];
    $entries = is_array($entries) ? $entries : [];
    $existingIds = array_map(fn($entry) => $entry['id'] ?? '', $entries);
    $maxOrder = 0;
    foreach ($entries as $entry) {
        $maxOrder = max($maxOrder, (int)($entry['order'] ?? 0));
    }

    $changed = false;
    foreach (staff_guide_default_sections() as $section) {
        $id = $section['id'];
        if (!in_array($id, $existingIds, true)) {
            writeJsonAtomic(staff_guide_section_path($id), $section);
            $entries[] = [
                'id' => $id,
                'title' => $section['title'],
                'published' => true,
                'order' => ++$maxOrder,
                'archived' => false,
            ];
            $changed = true;
            continue;
        }
        if (!file_exists(staff_guide_section_path($id))) {
            writeJsonAtomic(staff_guide_section_path($id), $section);
            $changed = true;
        }
    }

    if ($changed) {
        $index['version'] = $index['version'] ?? 1;
        $index['sections'] = $entries;
        staff_guide_save_index($index);
    }
}

function staff_guide_index_entries(): array
{
    ensure_staff_guides_env();
    $index = readJson(staff_guide_index_path());
    $sections = $index['sections'] ?? [];
    if (!is_array($sections)) {
        return [];
    }
    usort($sections, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
    return $sections;
}

function staff_guide_load_section(string $id): ?array
{
    $path = staff_guide_section_path($id);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function staff_guide_save_section(array $section): void
{
    if (empty($section['id'])) {
        throw new RuntimeException('Missing guide id.');
    }
    writeJsonAtomic(staff_guide_section_path($section['id']), $section);
}

function staff_guide_save_index(array $index): void
{
    $index['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(staff_guide_index_path(), $index);
}

function staff_guide_log_event(string $event, array $payload): void
{
    $entry = array_merge([
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => $event,
    ], $payload);

    $handle = fopen(staff_guide_log_path(), 'a');
    if ($handle) {
        flock($handle, LOCK_EX);
        fwrite($handle, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function staff_guide_sanitize_id(string $id): ?string
{
    return guide_sanitize_id($id);
}

function staff_guide_generate_id(string $title, array $existingIds): string
{
    $slug = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($title)) ?? '');
    $slug = trim($slug ?? '', '-');
    if ($slug === '') {
        $slug = 'SECTION';
    }
    $base = 'STAFF-GUIDE-' . $slug;
    $candidate = $base;
    while (in_array($candidate, $existingIds, true)) {
        $candidate = $base . '-' . strtoupper(bin2hex(random_bytes(2)));
    }
    return $candidate;
}
