<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/chat-providers.php';
require_once __DIR__ . '/../app/may-telemetry.php';

function test_assert(bool $cond, string $msg): void {
    if (!$cond) {
        throw new RuntimeException("Assertion failed: {$msg}");
    }
}

echo "Testing Compass Assistant Configuration & Failover...\n";

// 1. Test multi-tier config loader (chat_provider_env)
// A. From $_SERVER
$_SERVER['TEST_AI_VAR'] = 'server_val';
test_assert(chat_provider_env('TEST_AI_VAR') === 'server_val', 'Loads from $_SERVER');
unset($_SERVER['TEST_AI_VAR']);

// B. From $_ENV
$_ENV['TEST_AI_VAR'] = 'env_val';
test_assert(chat_provider_env('TEST_AI_VAR') === 'env_val', 'Loads from $_ENV');
unset($_ENV['TEST_AI_VAR']);

// C. From global $config['ai']
global $config;
$origConfig = $config;
$config['ai'] = ['TEST_AI_VAR' => 'config_val'];
test_assert(chat_provider_env('TEST_AI_VAR') === 'config_val', 'Loads from $config[ai]');
$config = $origConfig;

// D. Fallback default
test_assert(chat_provider_env('NON_EXISTENT_VAR', 'my_default') === 'my_default', 'Falls back to default value');

// 2. Test chat_provider_detect_source
test_assert(in_array(chat_provider_detect_source('GROQ_API_KEY'), ['env', 'db', 'config', 'none'], true), 'Detects valid source type');

// 3. Test 4 health check statuses
// Test 'unconfigured' status when provider has empty keys
$rawTest = chat_provider_get_raw_config();
test_assert(isset($rawTest['groq'], $rawTest['cloudflare']), 'Raw config has groq and cloudflare');

// Mock empty health
chat_provider_reset_cooldown();
@unlink(chat_provider_health_file());

// If provider is configured, initial status should be 'untested'
if ($rawTest['groq']['configured']) {
    $statusGroq = chat_provider_get_health_status('groq');
    test_assert($statusGroq['status'] === 'untested', 'Initial state is untested when configured');
}

// 4. Test Health Check execution & sanitized messages
if ($rawTest['groq']['configured']) {
    $res = chat_provider_health_check('groq');
    test_assert(isset($res['ok'], $res['message'], $res['http_status']), 'Health check returned structured result');
    $statusGroqAfter = chat_provider_get_health_status('groq');
    test_assert(in_array($statusGroqAfter['status'], ['healthy', 'error'], true), 'Status updated to healthy or error after check');
}

// 5. Test fallback classification in chat_provider_answer
// A. Unconfigured (empty providers)
$ansUnconf = chat_provider_answer([['role'=>'user','content'=>'hi']], []);
test_assert($ansUnconf['fallback_reason'] === 'unconfigured', 'Empty providers yields unconfigured fallback');

// B. Auth error (401)
$pMock = [['name'=>'mock_p','model'=>'mock','key'=>'k','url'=>'http://mock']];
$ansAuth = chat_provider_answer([['role'=>'user','content'=>'hi']], $pMock, fn()=>['status'=>401,'body'=>[]], false);
test_assert($ansAuth['fallback_reason'] === 'auth_error', 'HTTP 401 yields auth_error fallback');

// C. Quota exceeded (429)
$ansQuota = chat_provider_answer([['role'=>'user','content'=>'hi']], $pMock, fn()=>['status'=>429,'body'=>[]], false);
test_assert($ansQuota['fallback_reason'] === 'quota_exceeded', 'HTTP 429 yields quota_exceeded fallback');

// D. Timeout (0 or 408)
$ansTimeout = chat_provider_answer([['role'=>'user','content'=>'hi']], $pMock, fn()=>['status'=>0,'body'=>[]], false);
test_assert($ansTimeout['fallback_reason'] === 'timeout', 'HTTP 0 yields timeout fallback');

// E. Circuit breaker active
chat_provider_record_failure('mock_p', 429, 120);
$ansCb = chat_provider_answer([['role'=>'user','content'=>'hi']], $pMock, fn()=>['status'=>200,'body'=>[]], true);
test_assert($ansCb['fallback_reason'] === 'circuit_breaker_active', 'Cooldown yields circuit_breaker_active fallback');
chat_provider_reset_cooldown('mock_p');

// 6. Test telemetry logging and fallback aggregation
may_log_telemetry([
    'user_type' => 'Test',
    'provider' => 'none',
    'model' => 'unavailable',
    'latency_ms' => 10,
    'http_status' => 503,
    'status' => 'fallback',
    'fallback_reason' => 'unconfigured'
]);

$stats = may_get_telemetry_stats();
test_assert(isset($stats['fallback_reasons']['unconfigured']), 'Telemetry aggregates unconfigured fallback');
test_assert($stats['fallback_reasons']['unconfigured'] >= 1, 'Counted unconfigured fallback');

echo "✓ All Compass Assistant Configuration & Failover tests passed!\n";
