<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/ai_studio.php');
    }

    require_csrf();

    $config = load_ai_config(true);
    $mode = trim($_POST['mode'] ?? 'connectivity');
    $progress = [
        'Preparing secure request...',
        'Checking configuration...',
        'Dispatching to provider...',
        'Waiting for response...',
        'Parsing response...',
    ];

    $callResult = [
        'ok' => false,
        'parsedOk' => false,
        'providerOk' => false,
        'rawText' => '',
        'json' => null,
        'errors' => ['Configuration missing.'],
        'httpStatus' => null,
        'parseStage' => 'fallback_manual',
        'requestId' => null,
    ];

    if (($config['provider'] ?? '') && ($config['textModel'] ?? '') && ($config['apiKey'] ?? '')) {
        $systemPrompt = 'You are an API connectivity checker. Respond ONLY with compact JSON keys: status, summary, echo.';
        $userPrompt = 'Return JSON with status:"ok", summary:"AI Studio reachable", echo:"sample". Keep it one sentence.';
        if ($mode === 'json_strict') {
            $systemPrompt = 'You verify JSON cleanliness. Respond with a JSON object that includes status, summary, echo, and a nested meta object with ok:boolean.';
            $userPrompt = 'Return a JSON object without markdown fences. Keep status:"ok", summary:"Strict JSON path", echo:"sample", meta:{ok:true}.';
        }
        $callResult = ai_call([
            'purpose' => 'ai_studio_test',
            'systemPrompt' => $systemPrompt,
            'userPrompt' => $userPrompt,
            'expectJson' => true,
            'runMode' => $mode,
        ]);
    } else {
        $callResult['errors'] = ['Please save provider, key, and model names before testing.'];
    }

    $progress[] = $callResult['ok'] ? 'Result: success' : 'Result: errors present - review details.';

    ai_log([
        'event' => 'ai_test',
        'provider' => $config['provider'] ?? '',
        'model' => $config['textModel'] ?? '',
        'ok' => $callResult['ok'],
        'errorCount' => count($callResult['errors'] ?? []),
    ]);

    $title = get_app_config()['appName'] . ' | AI Test';
    $displayKey = mask_api_key_display($config['apiKey'] ?? null);

    render_layout($title, function () use ($config, $callResult, $progress, $displayKey, $mode) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                <div>
                    <h2 style="margin-bottom:6px;"><?= sanitize('AI Connectivity Test'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Runs a safe sample prompt and shows both raw and parsed JSON output.'); ?></p>
                </div>
                <a class="btn secondary" href="/superadmin/ai_studio.php"><?= sanitize('Back to AI Studio'); ?></a>
            </div>
        </div>
        <div class="card" style="margin-top:14px;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;">
            <div>
                <h4 style="margin:0 0 6px 0;"><?= sanitize('Configuration snapshot'); ?></h4>
                <div class="pill"><?= sanitize('Provider: ' . ($config['provider'] ?: 'not set')); ?></div><br>
                <div class="pill"><?= sanitize('Text model: ' . ($config['textModel'] ?: 'not set')); ?></div><br>
                <div class="pill"><?= sanitize('Image model: ' . ($config['imageModel'] ?: 'not set')); ?></div><br>
                <div class="pill"><?= sanitize('Key: ' . $displayKey); ?></div>
                <div class="pill muted" style="margin-top:6px;"><?= sanitize('Mode: ' . $mode); ?></div>
            </div>
            <div>
                <label class="muted" for="progress-box"><?= sanitize('Progress (pseudo-stream)'); ?></label>
                <textarea id="progress-box" readonly rows="7" style="width:100%;background:#0f1520;color:#e6edf3;border:1px solid #30363d;border-radius:10px;padding:10px;resize:vertical;"><?= sanitize(implode("\n", $progress)); ?></textarea>
            </div>
        </div>
        <div class="card" style="margin-top:14px;">
            <h4 style="margin-top:0;"><?= sanitize('Raw Response'); ?></h4>
            <textarea readonly rows="8" style="width:100%;background:#0f1520;color:#e6edf3;border:1px solid #30363d;border-radius:10px;padding:10px;resize:vertical;"><?= sanitize($callResult['rawText'] ?: 'No response received.'); ?></textarea>
            <div style="margin-top:12px;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;">
                <div>
                    <h4 style="margin:0 0 6px 0;"><?= sanitize('Parsed JSON'); ?></h4>
                    <?php if ($callResult['json'] !== null): ?>
                        <pre style="background:#0f1520;border:1px solid #30363d;border-radius:10px;padding:10px;overflow:auto;"><?= sanitize(json_encode($callResult['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                    <?php else: ?>
                        <p class="muted" style="margin:0;"><?= sanitize('No JSON parsed. Raw text is available for manual inspection.'); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <h4 style="margin:0 0 6px 0;"><?= sanitize('Errors & notes'); ?></h4>
                    <?php if (!empty($callResult['errors'])): ?>
                        <ul style="padding-left:18px;margin:0;color:#f77676;">
                            <?php foreach ($callResult['errors'] as $error): ?>
                                <li><?= sanitize($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="muted" style="margin:0;"><?= sanitize('No errors detected.'); ?></p>
                    <?php endif; ?>
                    <div style="margin-top:10px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px;">
                        <div class="pill"><?= sanitize('HTTP: ' . ($callResult['httpStatus'] ?? 'n/a')); ?></div>
                        <div class="pill"><?= sanitize('Parsed: ' . (!empty($callResult['parsedOk']) ? 'yes' : 'no')); ?></div>
                        <div class="pill muted"><?= sanitize('Stage: ' . ($callResult['parseStage'] ?? 'fallback_manual')); ?></div>
                        <?php if (!empty($callResult['requestId'])): ?>
                            <div class="pill muted"><?= sanitize('Request ID: ' . $callResult['requestId']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    });
});
