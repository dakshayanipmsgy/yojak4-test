<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $config = load_ai_config(true);
    $errors = [];
    $lastProviderCall = null;

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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $provider = trim($_POST['provider'] ?? '');
        $apiKeyInput = trim($_POST['api_key'] ?? '');
        $textModel = trim($_POST['text_model'] ?? '');
        $imageModel = trim($_POST['image_model'] ?? '');

        if (!in_array($provider, ['openai', 'gemini'], true)) {
            $errors[] = 'Provider is required.';
        }
        if ($textModel === '') {
            $errors[] = 'Text model is required.';
        }
        if ($imageModel === '') {
            $errors[] = 'Image model is required.';
        }

        $resolvedKey = $apiKeyInput !== '' ? $apiKeyInput : ($config['apiKey'] ?? '');
        if ($resolvedKey === '') {
            $errors[] = 'API key is required.';
        }

        if (!$errors) {
            save_ai_config($provider, $resolvedKey, $textModel, $imageModel);
            set_flash('success', 'AI settings saved successfully.');
            redirect('/superadmin/ai_studio.php');
        } else {
            set_flash('error', implode(' ', $errors));
            $config['provider'] = $provider;
            $config['textModel'] = $textModel;
            $config['imageModel'] = $imageModel;
        }
    }

    $displayKey = mask_api_key_display($config['apiKey'] ?? null);
    $title = get_app_config()['appName'] . ' | AI Studio';

    render_layout($title, function () use ($config, $displayKey, $lastProviderCall) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:flex-start;gap:18px;flex-wrap:wrap;justify-content:space-between;">
                <div>
                    <h2 style="margin-bottom:6px;"><?= sanitize('AI Studio'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Configure provider, models, and keep keys hidden behind server-side storage.'); ?></p>
                </div>
                <span class="pill"><?= sanitize('Superadmin only'); ?></span>
            </div>
            <form method="post" style="margin-top:16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;align-items:end;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
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
                <div class="field" style="grid-column:1/-1;">
                    <button class="btn" type="submit"><?= sanitize('Save Settings'); ?></button>
                    <span class="muted" style="margin-left:10px;"><?= sanitize('Keys are obfuscated with a server secret and stored outside web root.'); ?></span>
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
                    <button class="btn" type="submit" name="mode" value="json_strict"><?= sanitize('Test JSON Mode'); ?></button>
                    <span class="pill"><?= sanitize('Non-destructive sample prompt'); ?></span>
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
