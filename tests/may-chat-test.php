<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/chat-providers.php';
require_once __DIR__ . '/../app/may-telemetry.php';
require_once __DIR__ . '/../app/may-actions.php';
require_once __DIR__ . '/../app/may-prompt.php';

function expect_chat(bool $cond, string $msg): void {
    if (!$cond) throw new RuntimeException("Chat test failed: {$msg}");
}

echo "Running Mây Chatbot Integration Tests...\n";

// 1. Rate Limiter Tests
$guestIp = '192.168.1.' . mt_rand(100, 250);
$check1 = may_check_rate_limit(null, $guestIp);
expect_chat($check1['allowed'] === true, 'Initial guest query allowed');
expect_chat($check1['remaining'] === 10, 'Guest limit is 10');

// Increment 10 times to hit limit
for ($i = 0; $i < 10; $i++) {
    may_increment_rate_limit($check1['key']);
}
$check2 = may_check_rate_limit(null, $guestIp);
expect_chat($check2['allowed'] === false, 'Guest blocked after 10 queries');
expect_chat($check2['remaining'] === 0, 'Zero remaining for guest');

$userCheck = may_check_rate_limit(['id' => 9999], $guestIp);
expect_chat($userCheck['allowed'] === true, 'Authenticated user has separate limit');
expect_chat($userCheck['limit'] === 30, 'User limit is 30');

// 2. Circuit Breaker Cooldown Tests
chat_provider_reset_cooldown();
expect_chat(!chat_provider_is_in_cooldown('groq'), 'Groq initially not in cooldown');

chat_provider_record_failure('groq', 429, 60);
expect_chat(chat_provider_is_in_cooldown('groq'), 'Groq in cooldown after 429');

$status = chat_provider_cooldown_status();
expect_chat(isset($status['groq']), 'Cooldown recorded in transient storage');

chat_provider_reset_cooldown('groq');
expect_chat(!chat_provider_is_in_cooldown('groq'), 'Groq cooldown reset successfully');

// 3. Telemetry Logging & Metrics
may_log_telemetry([
    'user_type' => 'Khách',
    'provider' => 'groq',
    'model' => 'openai/gpt-oss-120b',
    'latency_ms' => 450,
    'http_status' => 200,
    'status' => 'completed'
]);
may_log_telemetry([
    'user_type' => 'Học sinh #1',
    'provider' => 'cloudflare',
    'model' => '@cf/meta/llama-3.1-8b-instruct-fp8-fast',
    'latency_ms' => 380,
    'http_status' => 200,
    'status' => 'completed'
]);
may_log_telemetry([
    'user_type' => 'Khách',
    'provider' => 'none',
    'model' => 'unavailable',
    'latency_ms' => 120,
    'http_status' => 503,
    'status' => 'fallback'
]);

$stats = may_get_telemetry_stats();
expect_chat($stats['total_requests'] >= 3, 'Stats counted total requests');
expect_chat($stats['groq_count'] >= 1, 'Stats counted Groq requests');
expect_chat($stats['cloudflare_count'] >= 1, 'Stats counted Cloudflare requests');
expect_chat($stats['fallback_count'] >= 1, 'Stats counted fallback requests');

$recentLogs = may_get_telemetry_logs(5);
expect_chat(count($recentLogs) >= 3, 'Retrieved sanitized log records');
expect_chat(!isset($recentLogs[0]['user_message']), 'Zero prompt text stored in logs (Privacy compliance)');

// 4. System Prompt Builder with Profile Isolation
$evidence = ['items' => []];
$guestPrompt = may_build_system_prompt($evidence, null, ['page_id' => 'home']);
expect_chat(!str_contains($guestPrompt, 'TỔNG HỢP HỒ SƠ NGƯỜI DÙNG'), 'Guest prompt has zero profile data');
expect_chat(str_contains($guestPrompt, 'KHÔNG NGỤY KHOA HỌC'), 'Scientific guardrails enforced');
expect_chat(str_contains($guestPrompt, 'KHÔNG ĐƯA CHỈ SỐ PHÙ HỢP ĐƠN LẺ'), 'No single score suitability enforced');

$userProfile = [
    'result_code' => 'IAS',
    'top_traits' => ['Investigative', 'Artistic', 'Social'],
    'favorites_count' => 3
];
$userPrompt = may_build_system_prompt($evidence, $userProfile, ['page_id' => 'career', 'entity_id' => '5']);
expect_chat(str_contains($userPrompt, 'Mã Holland gần nhất: IAS'), 'Opted-in profile included');
expect_chat(str_contains($userPrompt, 'Trang đang xem: career'), 'Page context included');
expect_chat(!str_contains($userPrompt, 'password') && !str_contains($userPrompt, 'email'), 'Credentials excluded from prompt');

// 5. Dual Provider Failover with Mock Transport
$mockProviders = [
    ['name' => 'groq', 'model' => 'gpt-oss-120b'],
    ['name' => 'cloudflare', 'model' => 'llama-3.1-8b']
];
$calls = [];
$mockTransport = function(array $p, array $payload) use (&$calls) {
    $calls[] = $p['name'];
    if ($p['name'] === 'groq') {
        // Groq fails with 429
        return ['status' => 429, 'body' => []];
    }
    // Cloudflare succeeds
    return [
        'status' => 200,
        'body' => [
            'choices' => [
                ['message' => ['content' => 'Xin chào từ Cloudflare'], 'finish_reason' => 'stop']
            ]
        ]
    ];
};

$ans = chat_provider_answer([['role' => 'user', 'content' => 'Test']], $mockProviders, $mockTransport);
expect_chat($calls === ['groq', 'cloudflare'], 'Primary failed and automatically failed over to secondary');
expect_chat($ans['provider'] === 'cloudflare', 'Answer provided by Cloudflare');
expect_chat($ans['message'] === 'Xin chào từ Cloudflare', 'Correct fallback message returned');

echo "✓ All Mây Chatbot Integration Tests passed successfully!\n";
