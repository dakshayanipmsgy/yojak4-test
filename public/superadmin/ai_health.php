<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $configResult = ai_get_config(true);
    $config = $configResult['config'] ?? ai_config_defaults();
    $configErrors = $configResult['errors'] ?? [];
    $displayKey = mask_api_key_display($config['apiKey'] ?? null);
    $nowIso = now_kolkata()->format(DateTime::ATOM);

    $preflightIssues = [];
    if (empty($config['apiKeyStored'])) {
        $preflightIssues[] = 'Key not saved.';
    }
    if (trim((string)($config['textModel'] ?? '')) === '') {
        $preflightIssues[] = 'Model not set.';
    }
    $preflightIssues = array_values(array_unique(array_merge($preflightIssues, $configErrors)));

    $testDefinitions = [
        'json_echo' => [
            'label' => 'Test 1: JSON echo',
            'expectation' => 'Return {"ok":true,"ts":"' . $nowIso . '"}',
            'system' => 'You are an AI connectivity validator. Respond only with compact JSON keys ok and ts.',
            'user' => 'Return JSON exactly as {"ok":true,"ts":"' . $nowIso . '"}. No markdown or commentary.',
            'options' => [
                'expectJson' => true,
                'runMode' => 'health_check_json',
                'temperature' => 0.1,
                'maxTokens' => 120,
            ],
        ],
        'topics' => [
            'label' => 'Test 2: Topic titles (JSON)',
            'expectation' => 'Return 5 short topic titles in JSON',
            'system' => 'You are an AI connectivity validator. Respond only with JSON containing topics: string array of short titles.',
            'user' => 'Return exactly 5 concise topic titles (max 6 words each) inside JSON: {"topics":["title1","title2","title3","title4","title5"]}.',
            'options' => [
                'expectJson' => true,
                'runMode' => 'health_check_topics',
                'temperature' => 0.25,
                'maxTokens' => 220,
            ],
        ],
    ];

    $healthResult = [
        'ran' => false,
        'overallOk' => false,
        'diagnosis' => [],
        'tests' => [],
        'emptyTwice' => false,
        'recommendFallback' => false,
        'debugSummary' => '',
        'timestamp' => $nowIso,
    ];

    $detectCandidates = static function (?string $rawBody): array {
        $rawBody = (string)($rawBody ?? '');
        $hasCandidates = false;
        $hasParts = false;
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded)) {
            if (!empty($decoded['candidates']) && is_array($decoded['candidates'])) {
                $hasCandidates = true;
                foreach ($decoded['candidates'] as $candidate) {
                    if (!is_array($candidate)) {
                        continue;
                    }
                    $parts = $candidate['content']['parts'] ?? ($candidate['content'][0]['parts'] ?? []);
                    if (is_array($parts)) {
                        foreach ($parts as $part) {
                            if (is_array($part) && isset($part['text']) && trim((string)$part['text']) !== '') {
                                $hasParts = true;
                                break 2;
                            }
                        }
                    }
                }
            }
            if (!empty($decoded['choices']) && is_array($decoded['choices'])) {
                $hasCandidates = true;
                foreach ($decoded['choices'] as $choice) {
                    if (!is_array($choice) || empty($choice['message'])) {
                        continue;
                    }
                    $content = $choice['message']['content'] ?? null;
                    if (is_string($content) && trim($content) !== '') {
                        $hasParts = true;
                        break;
                    }
                    if (is_array($content)) {
                        foreach ($content as $part) {
                            if (is_array($part) && isset($part['text']) && trim((string)$part['text']) !== '') {
                                $hasParts = true;
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        return ['hasCandidates' => $hasCandidates, 'hasParts' => $hasParts];
    };

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $healthResult['ran'] = true;
        if (empty($preflightIssues)) {
            foreach ($testDefinitions as $key => $definition) {
                $call = ai_call_text(
                    'ai_health_' . $key,
                    $definition['system'],
                    $definition['user'],
                    $definition['options']
                );

                $rawBody = $call['rawBody'] ?? ($call['rawText'] ?? '');
                $requestId = $call['requestId'] ?? ($call['rawEnvelope']['requestId'] ?? null);
                $responseId = $call['responseId'] ?? ($call['rawEnvelope']['responseId'] ?? null);
                $finishReasons = is_array($call['finishReasons'] ?? null) ? $call['finishReasons'] : ($call['rawEnvelope']['finishReasons'] ?? []);
                if (!is_array($finishReasons)) {
                    $finishReasons = [];
                }
                $blockReason = $call['promptBlockReason'] ?? ($call['rawEnvelope']['blockReason'] ?? null);
                $text = $call['rawText'] ?? ($call['text'] ?? '');
                $textLen = strlen((string)$text);
                $candidateFlags = $detectCandidates($rawBody);

                $healthResult['tests'][$key] = [
                    'label' => $definition['label'],
                    'expectation' => $definition['expectation'],
                    'ok' => (bool)($call['ok'] ?? false),
                    'providerOk' => (bool)($call['providerOk'] ?? false),
                    'parsedOk' => (bool)($call['parsedOk'] ?? false),
                    'httpStatus' => $call['httpStatus'] ?? null,
                    'requestId' => $requestId,
                    'responseId' => $responseId ?? $requestId,
                    'finishReasons' => array_values(array_filter(array_unique($finishReasons)) ?? []),
                    'blockReason' => $blockReason,
                    'textLen' => $textLen,
                    'rawText' => $text,
                    'rawBodySnippet' => substr($rawBody, 0, 800),
                    'errors' => $call['errors'] ?? [],
                    'modelUsed' => $call['modelUsed'] ?? ($config['textModel'] ?? ''),
                    'json' => $call['json'] ?? null,
                    'hasCandidates' => $candidateFlags['hasCandidates'],
                    'hasParts' => $candidateFlags['hasParts'],
                    'latencyMs' => $call['latencyMs'] ?? null,
                ];
            }

            $healthResult['emptyTwice'] = count($healthResult['tests']) === count($testDefinitions)
                && array_reduce($healthResult['tests'], static fn(bool $carry, array $row): bool => $carry && ($row['textLen'] ?? 0) === 0, true);
            $healthResult['overallOk'] = count($healthResult['tests']) === count($testDefinitions)
                && array_reduce($healthResult['tests'], static fn(bool $carry, array $row): bool => $carry && !empty($row['providerOk']) && ($row['textLen'] ?? 0) > 0, true);
            $healthResult['recommendFallback'] = $healthResult['emptyTwice'];
            $healthResult['diagnosis'][] = $healthResult['overallOk']
                ? 'Success: key + model returned JSON text with request/response IDs.'
                : 'Check provider permissions: one or more calls returned empty, blocked, or non-JSON output.';
            if ($healthResult['emptyTwice']) {
                $healthResult['diagnosis'][] = 'Model returned empty content twice; consider switching to the Flash fallback.';
            }
            foreach ($healthResult['tests'] as $row) {
                if (!empty($row['blockReason'])) {
                    $healthResult['diagnosis'][] = 'Prompt was blocked: ' . $row['blockReason'];
                }
                if (!empty($row['finishReasons'])) {
                    $healthResult['diagnosis'][] = 'Finish reasons: ' . implode(', ', $row['finishReasons']);
                }
                foreach ($row['errors'] as $err) {
                    $healthResult['diagnosis'][] = $err;
                }
            }
            if (empty($healthResult['diagnosis'])) {
                $healthResult['diagnosis'][] = 'No issues detected.';
            }

            $healthResult['debugSummary'] = implode("\n", [
                'provider=' . ($config['provider'] ?? 'n/a'),
                'model=' . ($config['textModel'] ?? 'n/a'),
                'json_test=http:' . ($healthResult['tests']['json_echo']['httpStatus'] ?? 'n/a') . ' req=' . ($healthResult['tests']['json_echo']['requestId'] ?? 'n/a') . ' resp=' . ($healthResult['tests']['json_echo']['responseId'] ?? 'n/a') . ' len=' . ($healthResult['tests']['json_echo']['textLen'] ?? 0),
                'topics_test=http:' . ($healthResult['tests']['topics']['httpStatus'] ?? 'n/a') . ' req=' . ($healthResult['tests']['topics']['requestId'] ?? 'n/a') . ' resp=' . ($healthResult['tests']['topics']['responseId'] ?? 'n/a') . ' len=' . ($healthResult['tests']['topics']['textLen'] ?? 0),
                'finish=' . implode(',', array_unique(array_merge(
                    $healthResult['tests']['json_echo']['finishReasons'] ?? [],
                    $healthResult['tests']['topics']['finishReasons'] ?? []
                ))),
                'block=' . (($healthResult['tests']['json_echo']['blockReason'] ?? '') ?: ($healthResult['tests']['topics']['blockReason'] ?? 'none')),
                'ranAt=' . $nowIso,
            ]);

            ai_log([
                'event' => 'ai_health_test',
                'actor' => $user['email'] ?? ($user['yojId'] ?? 'superadmin'),
                'provider' => $config['provider'] ?? '',
                'model' => $config['textModel'] ?? '',
                'overallOk' => $healthResult['overallOk'],
                'emptyTwice' => $healthResult['emptyTwice'],
                'tests' => array_map(static fn(array $row): array => [
                    'label' => $row['label'],
                    'ok' => $row['ok'],
                    'providerOk' => $row['providerOk'],
                    'parsedOk' => $row['parsedOk'],
                    'httpStatus' => $row['httpStatus'],
                    'requestId' => $row['requestId'],
                    'responseId' => $row['responseId'],
                    'textLen' => $row['textLen'],
                    'finishReasons' => $row['finishReasons'],
                    'blockReason' => $row['blockReason'],
                    'hasCandidates' => $row['hasCandidates'],
                    'hasParts' => $row['hasParts'],
                ], $healthResult['tests']),
            ]);
        } else {
            $healthResult['diagnosis'][] = 'Configuration incomplete: save provider, key, and model before running the health check.';
        }
    }

    $title = get_app_config()['appName'] . ' | AI Health + Model Permission Check';

    render_layout($title, function () use ($config, $displayKey, $healthResult, $preflightIssues) {
        $statusPillColor = !empty($healthResult['overallOk']) ? '#238636' : '#8a3d3d';
        $statusText = $healthResult['ran'] ? (!empty($healthResult['overallOk']) ? 'Healthy' : 'Needs attention') : 'Not run yet';
        ?>
        <div class="card">
            <div style="display:flex;align-items:flex-start;gap:18px;flex-wrap:wrap;justify-content:space-between;">
                <div>
                    <h2 style="margin-bottom:6px;">AI Health + Model Permission Check</h2>
                    <p class="muted" style="margin:0;">Prove the saved key and model can return live candidates/parts with IDs. Uses the shared AI client.</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <span class="pill">Superadmin only</span>
                    <span class="pill muted">Timezone: Asia/Kolkata</span>
                    <a class="btn secondary" href="/superadmin/ai_studio.php">AI Studio</a>
                </div>
            </div>
        </div>
        <div class="card" style="margin-top:14px;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;align-items:start;">
            <div>
                <h3 style="margin:0 0 6px 0;">Configuration snapshot</h3>
                <p class="muted" style="margin:0 0 8px 0;">Source: /data/ai/ai_config.json</p>
                <div class="pill">Provider: <?= sanitize($config['provider'] ?: 'not set'); ?></div><br>
                <div class="pill">Text model: <?= sanitize($config['textModel'] ?: 'not set'); ?></div><br>
                <div class="pill">Key stored: <?= sanitize(!empty($config['apiKeyStored']) ? 'yes' : 'no'); ?> (<?= sanitize($displayKey); ?>)</div><br>
                <div class="pill">Updated: <?= sanitize($config['updatedAt'] ?? 'n/a'); ?></div>
                <?php if (!empty($preflightIssues)): ?>
                    <div class="pill danger" style="margin-top:8px;">Preflight: <?= sanitize(implode(' | ', $preflightIssues)); ?></div>
                <?php else: ?>
                    <div class="pill muted" style="margin-top:8px;">Configuration looks valid.</div>
                <?php endif; ?>
                <div class="pill muted" style="margin-top:8px;">Sessions, RBAC, CSRF enforced • Filesystem JSON with locking</div>
            </div>
            <form method="post" style="align-self:end;display:grid;gap:10px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="card" style="background:var(--surface-2);border:1px solid var(--border);border-radius:14px;padding:14px;display:grid;gap:10px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <div>
                            <h4 style="margin:0 0 4px 0;">Run live tests</h4>
                            <p class="muted" style="margin:0;">2 calls: JSON echo + topic titles. Captures requestId/responseId.</p>
                        </div>
                        <button type="submit" class="btn primary" <?= !empty($preflightIssues) ? 'disabled' : ''; ?>>Run health tests</button>
                    </div>
                    <div class="pill" style="background:var(--surface);border:1px solid var(--border);color:var(--muted);">CSRF enforced • No secrets shown • Logs to /data/logs/ai.log</div>
                </div>
            </form>
        </div>

        <?php if ($healthResult['ran']): ?>
            <div class="card" style="margin-top:14px;display:grid;gap:12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                    <div>
                        <h3 style="margin:0 0 4px 0;">Report card</h3>
                        <p class="muted" style="margin:0;">Timestamp: <?= sanitize($healthResult['timestamp']); ?></p>
                    </div>
                    <span class="pill" style="background:<?= $statusPillColor; ?>;color:var(--text);"><?= sanitize($statusText); ?></span>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;">
                    <div class="pill">Model used: <?= sanitize($config['textModel'] ?: 'unknown'); ?></div>
                    <div class="pill">Provider: <?= sanitize($config['provider'] ?: 'unknown'); ?></div>
                    <div class="pill">Empty twice: <?= !empty($healthResult['emptyTwice']) ? 'yes' : 'no'; ?></div>
                    <div class="pill">Tests run: <?= sanitize((string)count($healthResult['tests'])); ?></div>
                </div>
                <div class="card" style="background:var(--surface-2);border:1px solid var(--border);border-radius:14px;padding:12px;display:grid;gap:10px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            <h4 style="margin:0;">Findings</h4>
                            <button type="button" class="btn secondary compact" id="copy-debug" data-summary="<?= sanitize($healthResult['debugSummary']); ?>">Copy debug</button>
                            <span class="pill muted">No secrets included</span>
                        </div>
                        <?php if (!empty($healthResult['recommendFallback'])): ?>
                            <form method="post" action="/superadmin/ai_studio.php" style="margin:0;">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="provider" value="<?= sanitize($config['provider'] ?? ''); ?>">
                                <input type="hidden" name="text_model" value="<?= sanitize($config['textModel'] ?? ''); ?>">
                                <input type="hidden" name="image_model" value="<?= sanitize($config['imageModel'] ?? ''); ?>">
                                <input type="hidden" name="action" value="switch_offline_fallback">
                                <button type="submit" class="btn danger">Switch to fallback model</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($healthResult['diagnosis'])): ?>
                        <ul style="margin:0;padding-left:18px;color:<?= !empty($healthResult['overallOk']) ? '#9ae9c2' : '#f77676'; ?>;">
                            <?php foreach ($healthResult['diagnosis'] as $diagnosis): ?>
                                <li><?= sanitize($diagnosis); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="muted" style="margin:0;">No findings captured.</p>
                    <?php endif; ?>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px;">
                    <?php foreach ($healthResult['tests'] as $key => $row): ?>
                        <?php $okColor = !empty($row['providerOk']) && ($row['textLen'] ?? 0) > 0 ? '#1f6feb' : '#8a3d3d'; ?>
                        <div class="card" style="border:1px solid <?= $okColor; ?>;border-radius:14px;padding:12px;display:grid;gap:10px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                                <div>
                                    <h4 style="margin:0;"><?= sanitize($row['label']); ?></h4>
                                    <p class="muted" style="margin:0;">Expectation: <?= sanitize($row['expectation']); ?></p>
                                </div>
                                <span class="pill" style="background:<?= $okColor; ?>;color:var(--text);">Text len: <?= sanitize((string)($row['textLen'] ?? 0)); ?></span>
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px;">
                                <div class="pill">HTTP: <?= sanitize($row['httpStatus'] ?? 'n/a'); ?></div>
                                <div class="pill">Request ID: <?= sanitize($row['requestId'] ?? 'n/a'); ?></div>
                                <div class="pill">Response ID: <?= sanitize($row['responseId'] ?? 'n/a'); ?></div>
                                <div class="pill">Model: <?= sanitize($row['modelUsed'] ?? ''); ?></div>
                                <div class="pill">Latency: <?= sanitize($row['latencyMs'] !== null ? ($row['latencyMs'] . ' ms') : 'n/a'); ?></div>
                                <div class="pill">Candidates: <?= !empty($row['hasCandidates']) ? 'yes' : 'no'; ?></div>
                                <div class="pill">Parts with text: <?= !empty($row['hasParts']) ? 'yes' : 'no'; ?></div>
                                <?php if (!empty($row['finishReasons'])): ?>
                                    <div class="pill">Finish: <?= sanitize(implode(', ', $row['finishReasons'])); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($row['blockReason'])): ?>
                                    <div class="pill danger">Block: <?= sanitize($row['blockReason']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h5 style="margin:0 0 6px 0;">Parsed JSON</h5>
                                <?php if (!empty($row['json'])): ?>
                                    <pre style="background:var(--surface-2);border:1px solid var(--border);border-radius:10px;padding:10px;overflow:auto;white-space:pre-wrap;"><?= sanitize(json_encode($row['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                                <?php else: ?>
                                    <p class="muted" style="margin:0;">No JSON parsed; see raw text.</p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h5 style="margin:0 0 6px 0;">Response text</h5>
                                <textarea readonly rows="6" style="width:100%;background:var(--surface-2);color:var(--text);border:1px solid var(--border);border-radius:10px;padding:10px;resize:vertical;"><?= sanitize($row['rawText'] ?: 'No response received.'); ?></textarea>
                                <details style="margin-top:10px;">
                                    <summary class="muted" style="cursor:pointer;">Raw body snippet</summary>
                                    <pre style="background:var(--surface-2);border:1px solid var(--border);border-radius:10px;padding:10px;overflow:auto;white-space:pre-wrap;"><?= sanitize($row['rawBodySnippet'] ?: 'No body captured.'); ?></pre>
                                </details>
                                <?php if (!empty($row['errors'])): ?>
                                    <ul style="margin:10px 0 0 0;padding-left:18px;color:#f77676;">
                                        <?php foreach ($row['errors'] as $error): ?>
                                            <li><?= sanitize($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="muted" style="margin:10px 0 0 0;">No provider errors reported.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card" style="margin-top:14px;">
                <h4 style="margin:0 0 6px 0;">No health run yet</h4>
                <p class="muted" style="margin:0;">Use the button above to trigger live JSON echo and topic probes. Captures requestId/responseId for audit.</p>
            </div>
        <?php endif; ?>

        <script>
            (function () {
                const copyBtn = document.getElementById('copy-debug');
                if (!copyBtn) return;
                copyBtn.addEventListener('click', function () {
                    const text = this.getAttribute('data-summary') || '';
                    if (!text) return;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(() => {
                            copyBtn.textContent = 'Copied';
                            setTimeout(() => { copyBtn.textContent = 'Copy debug'; }, 1200);
                        }).catch(() => alert('Unable to copy debug summary.'));
                    } else {
                        alert('Clipboard unavailable.');
                    }
                });
            })();
        </script>
        <?php
    });
});
