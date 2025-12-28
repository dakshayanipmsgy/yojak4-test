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

    $healthResult = [
        'ran' => false,
        'ok' => null,
        'httpStatus' => null,
        'requestId' => null,
        'modelUsed' => $config['textModel'] ?? '',
        'rawText' => '',
        'rawBodySnippet' => '',
        'errors' => [],
        'diagnosis' => '',
        'provider' => $config['provider'] ?? '',
        'latencyMs' => null,
        'timestamp' => now_kolkata()->format(DateTime::ATOM),
        'parsedOk' => false,
        'providerOk' => false,
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $healthResult['ran'] = true;

        if (empty($configErrors) && ($config['apiKey'] ?? '') !== '') {
            $now = now_kolkata()->format(DateTime::ATOM);
            $systemPrompt = 'You are an AI connectivity validator. Respond only with compact JSON containing ok:boolean and ts:string.';
            $userPrompt = 'Reply with JSON: {"ok":true, "ts":"' . $now . '"}';
            $call = ai_call_text(
                'ai_health_check',
                $systemPrompt,
                $userPrompt,
                [
                    'expectJson' => true,
                    'runMode' => 'health_check',
                    'temperature' => 0.1,
                    'maxTokens' => 120,
                ]
            );

            $healthResult['ok'] = $call['ok'] ?? false;
            $healthResult['httpStatus'] = $call['httpStatus'] ?? null;
            $healthResult['requestId'] = $call['requestId'] ?? null;
            $healthResult['modelUsed'] = $call['modelUsed'] ?? ($config['textModel'] ?? '');
            $healthResult['rawText'] = $call['rawText'] ?? ($call['text'] ?? '');
            $healthResult['rawBodySnippet'] = substr($call['rawBody'] ?? ($call['rawText'] ?? ''), 0, 800);
            $healthResult['errors'] = $call['errors'] ?? [];
            $healthResult['providerOk'] = $call['providerOk'] ?? false;
            $healthResult['parsedOk'] = $call['parsedOk'] ?? false;
            $healthResult['latencyMs'] = $call['latencyMs'] ?? null;
            $healthResult['timestamp'] = now_kolkata()->format(DateTime::ATOM);

            $status = (int)($healthResult['httpStatus'] ?? 0);
            if (!empty($healthResult['ok'])) {
                $healthResult['diagnosis'] = 'Success: Provider reachable and JSON parsed. This client is identical to content_v2 generation calls.';
            } elseif (in_array($status, [401, 403], true)) {
                $healthResult['diagnosis'] = 'Authentication failed: API key invalid or missing model permissions.';
            } elseif ($status === 404) {
                $healthResult['diagnosis'] = 'Model not found: verify the configured model name.';
            } elseif ($status === 429) {
                $healthResult['diagnosis'] = 'Rate limited: slow down requests or upgrade quota.';
            } elseif (($healthResult['rawText'] ?? '') === '' && !empty($healthResult['providerOk'])) {
                $healthResult['diagnosis'] = 'Provider anomaly: call succeeded but returned empty content.';
            } elseif ($status === 0 && !empty($call['providerError'])) {
                $healthResult['diagnosis'] = 'Connectivity or timeout issue: verify SSL, DNS, and outbound internet access.';
            } elseif (empty($healthResult['providerOk'])) {
                $healthResult['diagnosis'] = 'Provider rejected the request; re-check API key and model eligibility.';
            } else {
                $healthResult['diagnosis'] = 'Parsing failed; inspect the response payload for formatting issues.';
            }

            ai_log([
                'event' => 'ai_health_test',
                'actor' => $user['email'] ?? ($user['yojId'] ?? 'superadmin'),
                'provider' => $config['provider'] ?? '',
                'model' => $healthResult['modelUsed'],
                'httpStatus' => $healthResult['httpStatus'],
                'requestId' => $healthResult['requestId'],
                'ok' => $healthResult['ok'],
                'parsedOk' => $healthResult['parsedOk'],
                'providerOk' => $healthResult['providerOk'],
                'diagnosis' => $healthResult['diagnosis'],
                'latencyMs' => $healthResult['latencyMs'],
                'errorCount' => count($healthResult['errors']),
                'textLength' => strlen((string)$healthResult['rawText']),
            ]);
        } else {
            $healthResult['errors'] = array_merge($configErrors, ['AI is not configured. Set provider, API key, and model in AI Studio.']);
            $healthResult['diagnosis'] = 'Configuration incomplete: save provider, key, and model before running the health check.';
        }
    }

    $title = get_app_config()['appName'] . ' | AI Health Check';
    $displayKey = mask_api_key_display($config['apiKey'] ?? null);

    render_layout($title, function () use ($config, $displayKey, $healthResult) {
        $statusPillColor = !empty($healthResult['ok']) ? '#1f6feb' : '#8a3d3d';
        $statusText = $healthResult['ok'] === null ? 'Not run yet' : ($healthResult['ok'] ? 'Healthy' : 'Needs attention');
        $debugSummary = "provider=" . ($config['provider'] ?? 'n/a') . "\n" .
            "modelUsed=" . ($healthResult['modelUsed'] ?: ($config['textModel'] ?? 'unknown')) . "\n" .
            "httpStatus=" . ($healthResult['httpStatus'] ?? 'n/a') . "\n" .
            "requestId=" . ($healthResult['requestId'] ?? 'n/a') . "\n" .
            "diagnosis=" . ($healthResult['diagnosis'] ?: 'n/a') . "\n" .
            "ranAt=" . ($healthResult['timestamp'] ?? 'n/a');
        ?>
        <div class="card">
            <div style="display:flex;align-items:flex-start;gap:18px;flex-wrap:wrap;justify-content:space-between;">
                <div>
                    <h2 style="margin-bottom:6px;">AI Health Check</h2>
                    <p class="muted" style="margin:0;">Confirm the saved provider, model, and key can return live AI output. Uses the same shared client as content_v2.</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <span class="pill">Superadmin only</span>
                    <span class="pill muted">Timezone: Asia/Kolkata</span>
                    <a class="btn secondary" href="/superadmin/ai_studio.php">AI Studio</a>
                </div>
            </div>
        </div>
        <div class="card" style="margin-top:14px;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;">
            <div>
                <h3 style="margin:0 0 6px 0;">Configuration snapshot</h3>
                <p class="muted" style="margin:0 0 8px 0;">Data source: /data/ai/ai_config.json</p>
                <div class="pill">Provider: <?= sanitize($config['provider'] ?: 'not set'); ?></div><br>
                <div class="pill">Text model: <?= sanitize($config['textModel'] ?: 'not set'); ?></div><br>
                <div class="pill">Key stored: <?= sanitize($config['apiKeyStored'] ? 'yes' : 'no'); ?> (<?= sanitize($displayKey); ?>)</div><br>
                <div class="pill">Updated: <?= sanitize($config['updatedAt'] ?? 'n/a'); ?></div>
                <div class="pill muted" style="margin-top:8px;">Shared client powers AI Studio + content_v2.</div>
            </div>
            <form method="post" style="align-self:end;display:grid;gap:10px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="card" style="background:#0f1520;border:1px solid #253047;border-radius:14px;padding:14px;display:grid;gap:10px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <div>
                            <h4 style="margin:0 0 4px 0;">One-click diagnosis</h4>
                            <p class="muted" style="margin:0;">Runs a tiny JSON echo prompt via the configured provider.</p>
                        </div>
                        <button type="submit" class="btn primary">Run health test</button>
                    </div>
                    <div class="pill" style="background:#0c111b;color:#9ea7b3;">CSRF + session enforced • Filesystem config only</div>
                    <p class="muted" style="margin:0;">If successful here, content_v2 generation will succeed with the same key/model.</p>
                </div>
            </form>
        </div>

        <?php if ($healthResult['ran']): ?>
            <div class="card" style="margin-top:14px;display:grid;gap:12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                    <div>
                        <h3 style="margin:0 0 4px 0;">Latest health result</h3>
                        <p class="muted" style="margin:0;">Timestamp: <?= sanitize($healthResult['timestamp'] ?? 'n/a'); ?></p>
                    </div>
                    <span class="pill" style="background:<?= $statusPillColor; ?>;color:#e6edf3;"><?= sanitize($statusText); ?></span>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
                    <div class="pill">HTTP: <?= sanitize($healthResult['httpStatus'] ?? 'n/a'); ?></div>
                    <div class="pill">Model used: <?= sanitize($healthResult['modelUsed'] ?: ($config['textModel'] ?? 'unknown')); ?></div>
                    <div class="pill">Request ID: <?= sanitize($healthResult['requestId'] ?? 'n/a'); ?></div>
                    <div class="pill">Latency: <?= sanitize($healthResult['latencyMs'] !== null ? ($healthResult['latencyMs'] . ' ms') : 'n/a'); ?></div>
                </div>
                <div class="card" style="background:#0f1520;border:1px solid #253047;border-radius:14px;padding:12px;display:grid;gap:10px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <h4 style="margin:0;">Diagnosis</h4>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            <button type="button" class="btn secondary compact" id="copy-summary" data-summary="<?= sanitize($debugSummary); ?>">Copy debug summary</button>
                            <span class="pill muted">No secrets included</span>
                        </div>
                    </div>
                    <p style="margin:0;color:<?= !empty($healthResult['ok']) ? '#3fb950' : '#f77676'; ?>;"><?= sanitize($healthResult['diagnosis'] ?: 'No diagnosis available.'); ?></p>
                    <?php if (!empty($healthResult['errors'])): ?>
                        <ul style="margin:0;padding-left:18px;color:#f77676;">
                            <?php foreach ($healthResult['errors'] as $error): ?>
                                <li><?= sanitize($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="muted" style="margin:0;">No errors reported by the provider.</p>
                    <?php endif; ?>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;">
                    <div>
                        <h4 style="margin:0 0 6px 0;">Parsed JSON</h4>
                        <?php if (!empty($healthResult['parsedOk']) && ($healthResult['rawText'] ?? '') !== ''): ?>
                            <?php $parsed = parse_ai_json($healthResult['rawText']); ?>
                            <?php if ($parsed['json'] !== null): ?>
                                <pre style="background:#0f1520;border:1px solid #253047;border-radius:10px;padding:10px;overflow:auto;white-space:pre-wrap;"><?= sanitize(json_encode($parsed['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                            <?php else: ?>
                                <p class="muted" style="margin:0;">Parsing failed. Check raw response below.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="muted" style="margin:0;">No JSON parsed. Raw response is available for review.</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h4 style="margin:0 0 6px 0;">Response text</h4>
                        <textarea readonly rows="8" style="width:100%;background:#0f1520;color:#e6edf3;border:1px solid #30363d;border-radius:10px;padding:10px;resize:vertical;"><?= sanitize($healthResult['rawText'] ?: 'No response received.'); ?></textarea>
                        <details style="margin-top:10px;">
                            <summary class="muted" style="cursor:pointer;">Raw body snippet</summary>
                            <pre style="background:#0f1520;border:1px solid #253047;border-radius:10px;padding:10px;overflow:auto;white-space:pre-wrap;"><?= sanitize($healthResult['rawBodySnippet'] ?: 'No body captured.'); ?></pre>
                        </details>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card" style="margin-top:14px;">
                <h4 style="margin:0 0 6px 0;">No health run yet</h4>
                <p class="muted" style="margin:0;">Use the button above to trigger a real provider call and capture diagnostics.</p>
            </div>
        <?php endif; ?>

        <script>
            (function () {
                const copyBtn = document.getElementById('copy-summary');
                if (!copyBtn) return;
                copyBtn.addEventListener('click', function () {
                    const text = this.getAttribute('data-summary') || '';
                    if (!text) return;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(() => {
                            copyBtn.textContent = 'Copied';
                            setTimeout(() => { copyBtn.textContent = 'Copy debug summary'; }, 1200);
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
