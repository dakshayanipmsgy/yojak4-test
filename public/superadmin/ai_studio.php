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
    $defaults = ai_config_defaults();
    $offlineDefaults = $defaults['purposeModels']['offlineTenderExtract'] ?? [
        'primaryModel' => '',
        'fallbackModel' => '',
        'useStreamingFallback' => true,
        'retryOnceOnEmpty' => true,
        'useStructuredJson' => true,
    ];
    $offlineModels = $config['purposeModels']['offlineTenderExtract'] ?? [
        'primaryModel' => '',
        'fallbackModel' => '',
        'useStreamingFallback' => true,
        'retryOnceOnEmpty' => true,
        'useStructuredJson' => true,
    ];
    $offlineStructured = (bool)($offlineModels['useStructuredJson'] ?? true);
    $errors = $configResult['errors'] ?? [];
    $lastProviderCall = null;
    $lastTest = [
        'at' => null,
        'ok' => null,
    ];

    if (file_exists(AI_PROVIDER_RAW_LOG)) {
        $lines = file(AI_PROVIDER_RAW_LOG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            $lastLine = trim((string)end($lines));
            $decoded = json_decode($lastLine, true);
            if (is_array($decoded)) {
                $lastProviderCall = $decoded;
            }
        }
    }

    if (file_exists(AI_LOG_FILE)) {
        $logLines = file(AI_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        for ($i = count($logLines) - 1; $i >= 0; $i--) {
            $row = json_decode((string)$logLines[$i], true);
            if (is_array($row) && ($row['event'] ?? '') === 'ai_test') {
                $lastTest['at'] = $row['timestamp'] ?? null;
                $lastTest['ok'] = (bool)($row['ok'] ?? false);
                break;
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $action = trim($_POST['action'] ?? 'save');
        $provider = trim($_POST['provider'] ?? '');
        $apiKeyInput = trim($_POST['api_key'] ?? '');
        $textModel = trim($_POST['text_model'] ?? ($config['textModel'] ?? ''));
        $imageModel = trim($_POST['image_model'] ?? ($config['imageModel'] ?? ''));
        $offlinePrimary = trim($_POST['offline_primary_model'] ?? ($offlineModels['primaryModel'] ?? ''));
        $offlineFallback = trim($_POST['offline_fallback_model'] ?? ($offlineModels['fallbackModel'] ?? ''));
        $offlineStreaming = isset($_POST['offline_use_streaming']) ? true : (bool)($offlineModels['useStreamingFallback'] ?? false);
        $offlineRetry = isset($_POST['offline_retry_on_empty']) ? true : (bool)($offlineModels['retryOnceOnEmpty'] ?? false);
        $offlineStructured = isset($_POST['offline_use_structured']) ? true : (bool)($offlineModels['useStructuredJson'] ?? true);

        if ($action === 'switch_offline_fallback') {
            if ($offlineFallback === '') {
                $offlineFallback = $offlineDefaults['fallbackModel'] ?? '';
            }
            $offlinePrimary = $offlineFallback;
            if ($provider === '') {
                $provider = 'gemini';
            }
        }

        $saveResult = ai_save_config([
            'provider' => $provider,
            'apiKey' => $apiKeyInput,
            'textModel' => $textModel,
            'imageModel' => $imageModel,
            'purposeModels' => [
                'offlineTenderExtract' => [
                    'primaryModel' => $offlinePrimary,
                    'fallbackModel' => $offlineFallback,
                    'useStreamingFallback' => $offlineStreaming,
                    'retryOnceOnEmpty' => $offlineRetry,
                    'useStructuredJson' => $offlineStructured,
                ],
            ],
        ]);

        if (!empty($saveResult['ok'])) {
            ai_log([
                'event' => 'ai_config_saved',
                'actor' => $user['email'] ?? ($user['yojId'] ?? 'superadmin'),
                'provider' => $provider,
                'textModel' => $textModel,
                'imageModel' => $imageModel,
                'offlinePrimary' => $offlinePrimary,
                'offlineFallback' => $offlineFallback,
                'offlineStreaming' => $offlineStreaming,
                'offlineRetryEmpty' => $offlineRetry,
                'offlineStructured' => $offlineStructured,
                'action' => $action,
            ]);
            set_flash('success', $action === 'switch_offline_fallback'
                ? 'Offline extraction switched to the fallback model. Settings saved.'
                : 'AI settings saved successfully.');
            redirect('/superadmin/ai_studio.php');
        } else {
            $errors = $saveResult['errors'] ?? ['Unable to save configuration.'];
            set_flash('error', implode(' ', $errors));
            $config['provider'] = $provider;
            $config['textModel'] = $textModel;
            $config['imageModel'] = $imageModel;
            $offlineModels = [
                'primaryModel' => $offlinePrimary,
                'fallbackModel' => $offlineFallback,
                'useStreamingFallback' => $offlineStreaming,
                'retryOnceOnEmpty' => $offlineRetry,
                'useStructuredJson' => $offlineStructured,
            ];
        }
    }

    $displayKey = $config['apiKeyStored'] ? mask_api_key_display($config['apiKey'] ?? 'stored') : 'Not set';
    $title = get_app_config()['appName'] . ' | AI Studio';

    render_layout($title, function () use ($config, $displayKey, $lastProviderCall, $offlineModels, $offlineStructured, $offlineDefaults, $lastTest) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:flex-start;gap:18px;flex-wrap:wrap;justify-content:space-between;">
                <div>
                    <h2 style="margin-bottom:6px;"><?= sanitize('AI Studio'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Configure provider, models, and keep keys hidden behind server-side storage.'); ?></p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <span class="pill"><?= sanitize('Superadmin only'); ?></span>
                    <span class="pill muted"><?= sanitize('Timezone: Asia/Kolkata'); ?></span>
                </div>
            </div>
            <form method="post" style="margin-top:16px;display:grid;gap:16px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="card" style="background:#0f1520;border:1px solid #253047;border-radius:14px;padding:14px;display:grid;gap:10px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                        <div>
                            <h3 style="margin:0 0 4px 0;"><?= sanitize('Connection status'); ?></h3>
                            <p class="muted" style="margin:0;"><?= sanitize('Uses /data/ai/ai_config.json as the single source of truth.'); ?></p>
                        </div>
                        <span class="pill" style="background:<?= !empty($config['apiKeyStored']) ? '#22863a' : '#8a3d3d'; ?>;color:#e6edf3;"><?= sanitize(!empty($config['apiKeyStored']) ? 'Key saved' : 'Key missing'); ?></span>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                        <div class="pill"><?= sanitize('Provider: ' . (($config['provider'] ?? '') !== '' ? $config['provider'] : 'not set')); ?></div>
                        <div class="pill"><?= sanitize('Text model: ' . (($config['textModel'] ?? '') !== '' ? $config['textModel'] : 'not set')); ?></div>
                        <div class="pill"><?= sanitize('Updated: ' . ($config['updatedAt'] ?? 'n/a')); ?></div>
                        <div class="pill"><?= sanitize('Last test: ' . ($lastTest['at'] ?? 'never')); ?></div>
                        <div class="pill" style="background:#0c111b;color:<?= $lastTest['ok'] === false ? '#f77676' : '#9ea7b3'; ?>;">
                            <?= sanitize('Last result: ' . ($lastTest['ok'] === null ? 'not run' : ($lastTest['ok'] ? 'ok' : 'error'))); ?>
                        </div>
                    </div>
                    <div class="pill muted" style="background:#0c111b;color:#9ea7b3;">
                        <?= sanitize('If configuration is missing, AI callers will show: “AI is not configured. Superadmin: set provider, API key, and model in AI Studio.”'); ?>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;align-items:end;">
                    <div class="field">
                        <label for="provider"><?= sanitize('Provider'); ?></label>
                        <select id="provider" name="provider" required>
                            <option value=""><?= sanitize('Select provider'); ?></option>
                            <option value="openai" <?= ($config['provider'] ?? '') === 'openai' ? 'selected' : ''; ?>><?= sanitize('OpenAI'); ?></option>
                            <option value="gemini" <?= ($config['provider'] ?? '') === 'gemini' ? 'selected' : ''; ?>><?= sanitize('Gemini'); ?></option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="api_key"><?= sanitize('API Key'); ?></label>
                        <input id="api_key" type="password" name="api_key" placeholder="<?= sanitize($config['hasApiKey'] ? 'Leave blank to keep existing key' : 'Required'); ?>">
                        <small class="muted"><?= sanitize('Current: ' . $displayKey); ?></small>
                    </div>
                    <div class="field">
                        <label for="text_model"><?= sanitize('Text Model'); ?></label>
                        <input id="text_model" type="text" name="text_model" value="<?= sanitize($config['textModel'] ?? ''); ?>" required placeholder="<?= sanitize('e.g. gpt-4o-mini'); ?>">
                    </div>
                    <div class="field">
                        <label for="image_model"><?= sanitize('Image Model'); ?></label>
                        <input id="image_model" type="text" name="image_model" value="<?= sanitize($config['imageModel'] ?? ''); ?>" required placeholder="<?= sanitize('e.g. gpt-image-1'); ?>">
                    </div>
                </div>

                <div id="offline-extraction" class="card" style="background:#0f1520;border:1px solid #253047;border-radius:14px;padding:14px;display:grid;gap:12px;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <div>
                            <h3 style="margin:0 0 4px 0;"><?= sanitize('Offline Tender Extraction'); ?></h3>
                            <p class="muted" style="margin:0;"><?= sanitize('Use safer defaults when Gemini 3 Pro Preview returns empty content. Flash fallback + structured outputs keep runs stable.'); ?></p>
                        </div>
                        <span class="pill" style="background:#1f6feb;color:#e6edf3;"><?= sanitize('Empty-response shield'); ?></span>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;align-items:end;">
                        <div class="field">
                            <label for="offline_primary_model"><?= sanitize('Primary model'); ?></label>
                            <input id="offline_primary_model" list="offline-model-options" type="text" name="offline_primary_model" value="<?= sanitize($offlineModels['primaryModel'] ?? ''); ?>" placeholder="<?= sanitize('Defaults to Text Model when blank'); ?>">
                            <small class="muted"><?= sanitize('Current primary: ' . (($offlineModels['primaryModel'] ?? '') !== '' ? $offlineModels['primaryModel'] : 'Uses text model')); ?></small>
                        </div>
                        <div class="field">
                            <label for="offline_fallback_model"><?= sanitize('Fallback model (Flash recommended)'); ?></label>
                            <input id="offline_fallback_model" list="offline-fallback-options" type="text" name="offline_fallback_model" value="<?= sanitize($offlineModels['fallbackModel'] ?? ''); ?>" placeholder="<?= sanitize($offlineDefaults['fallbackModel'] ?? 'gemini-3-flash-preview'); ?>">
                            <small class="muted"><?= sanitize('Used automatically after retries or when Gemini returns empty content.'); ?></small>
                        </div>
                    </div>
                    <datalist id="offline-model-options">
                        <option value="gemini-3-pro-preview">
                        <option value="gemini-1.5-pro">
                        <option value="gpt-4o-mini">
                    </datalist>
                    <datalist id="offline-fallback-options">
                        <option value="gemini-3-flash-preview">
                        <option value="gemini-1.5-flash">
                        <option value="gpt-4o-mini">
                    </datalist>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <label class="pill" style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="offline_use_structured" value="1" <?= !empty($offlineStructured) ? 'checked' : ''; ?>>
                            <?= sanitize('Structured outputs ON (recommended)'); ?>
                        </label>
                        <label class="pill" style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="offline_use_streaming" value="1" <?= !empty($offlineModels['useStreamingFallback']) ? 'checked' : ''; ?>>
                            <?= sanitize('Streaming fallback'); ?>
                        </label>
                        <label class="pill" style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="offline_retry_on_empty" value="1" <?= !empty($offlineModels['retryOnceOnEmpty']) ? 'checked' : ''; ?>>
                            <?= sanitize('Retry once on empty content'); ?>
                        </label>
                        <span class="pill muted"><?= sanitize('Defaults: Flash fallback + structured'); ?></span>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;align-items:center;">
                        <div class="muted" style="font-size:13px;line-height:1.5;">
                            <?= sanitize('If contractors report “AI provider returned empty final output”, switch from gemini-3-pro-preview to the Flash fallback below. This keeps extraction running without code edits.'); ?>
                        </div>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;">
                            <button class="btn secondary" type="submit" name="action" value="switch_offline_fallback"><?= sanitize('Switch to Flash fallback now'); ?></button>
                            <button class="btn" type="submit" name="action" value="save"><?= sanitize('Save Settings'); ?></button>
                        </div>
                    </div>
                    <div class="pill muted" style="background:#0c111b;color:#9ea7b3;">
                        <?= sanitize('Keys are obfuscated with a server secret and stored outside web root. Logs written to /data/logs/ai.log.'); ?>
                    </div>
                </div>
            </form>
        </div>
        <div class="card" style="margin-top:16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;align-items:start;">
            <div>
                <h3 style="margin-top:0;"><?= sanitize('Run a quick AI test'); ?></h3>
                <p class="muted" style="margin-top:6px;"><?= sanitize('Uses the saved text model and masks sensitive values. Shows raw and parsed JSON output.'); ?></p>
                <form id="ai-test-form" action="/superadmin/ai_test.php" method="post" style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <button class="btn secondary" type="submit" name="mode" value="connectivity"><?= sanitize('Test AI'); ?></button>
                    <button class="btn" type="submit" name="mode" value="json_strict"><?= sanitize('Test JSON Mode (Preview)'); ?></button>
                    <span class="pill"><?= sanitize('Non-destructive sample prompt & JSON preview'); ?></span>
                </form>
            </div>
            <div>
                <label class="muted" for="test-progress"><?= sanitize('Progress'); ?></label>
                <textarea id="test-progress" readonly rows="6" style="width:100%;resize:vertical;background:#0f1520;border:1px solid #30363d;border-radius:10px;padding:10px;color:#e6edf3;">Ready. Click "Test AI" to run a sample request.</textarea>
            </div>
        </div>
        <script>
            const testForm = document.getElementById('ai-test-form');
            const progressBox = document.getElementById('test-progress');
            if (testForm && progressBox) {
                testForm.addEventListener('submit', () => {
                    const steps = [
                        'Preparing secure request...',
                        'Locking JSON storage...',
                        'Calling provider with masked key...',
                        'Waiting for response...',
                        'Parsing JSON (with repair fallback)...'
                    ];
                    progressBox.value = steps.join('\n');
                });
            }
        </script>
        <?php if ($lastProviderCall): ?>
            <div class="card" style="margin-top:14px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div>
                        <h3 style="margin:0 0 4px 0;"><?= sanitize('Last provider call (redacted)'); ?></h3>
                        <p class="muted" style="margin:0;"><?= sanitize('Snapshot omits API keys and shows only safe envelope data.'); ?></p>
                    </div>
                    <span class="pill"><?= sanitize('Auto-logged'); ?></span>
                </div>
                <div style="margin-top:10px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;">
                    <div class="pill"><?= sanitize('Provider: ' . ($lastProviderCall['provider'] ?? 'unknown')); ?></div>
                    <div class="pill"><?= sanitize('Model: ' . ($lastProviderCall['model'] ?? 'unknown')); ?></div>
                    <div class="pill"><?= sanitize('HTTP: ' . ($lastProviderCall['httpStatus'] ?? 'n/a')); ?></div>
                    <div class="pill"><?= sanitize('Parsed: ' . (!empty($lastProviderCall['parsedOk']) ? 'yes' : 'no')); ?></div>
                    <?php if (!empty($lastProviderCall['requestId'])): ?>
                        <div class="pill muted"><?= sanitize('Request ID: ' . $lastProviderCall['requestId']); ?></div>
                    <?php endif; ?>
                </div>
                <div style="margin-top:10px;">
                    <label class="muted"><?= sanitize('Response snippet'); ?></label>
                    <pre style="background:#0f1520;border:1px solid #30363d;border-radius:10px;padding:10px;overflow:auto;white-space:pre-wrap;"><?= sanitize($lastProviderCall['responseSnippet'] ?? ''); ?></pre>
                </div>
            </div>
        <?php endif; ?>
        <?php
    });
});
