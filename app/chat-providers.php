<?php
declare(strict_types=1);

function chat_provider_config(): array {
    $providers = [];
    if ($key = trim((string)(getenv('GROQ_API_KEY') ?: ''))) {
        $model = trim((string)(getenv('GROQ_MODEL') ?: 'openai/gpt-oss-120b'));
        $providers[] = [
            'name' => 'groq',
            'key' => $key,
            'url' => 'https://api.groq.com/openai/v1/chat/completions',
            'model' => $model
        ];
    }
    $key = trim((string)(getenv('CLOUDFLARE_API_TOKEN') ?: ''));
    $account = trim((string)(getenv('CLOUDFLARE_ACCOUNT_ID') ?: ''));
    if ($key && preg_match('/^[a-f0-9]{32}$/i', $account)) {
        $model = trim((string)(getenv('CLOUDFLARE_MODEL') ?: '@cf/meta/llama-3.1-8b-instruct-fp8-fast'));
        $providers[] = [
            'name' => 'cloudflare',
            'key' => $key,
            'url' => 'https://api.cloudflare.com/client/v4/accounts/' . $account . '/ai/v1/chat/completions',
            'model' => $model,
            'account' => $account
        ];
    }
    return $providers;
}

function chat_provider_cooldown_file(): string {
    return sys_get_temp_dir() . '/jobsimu_chat_cooldown.json';
}

function chat_provider_cooldown_status(): array {
    $file = chat_provider_cooldown_file();
    if (!is_file($file)) return [];
    $raw = @file_get_contents($file);
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function chat_provider_is_in_cooldown(string $providerName): bool {
    $status = chat_provider_cooldown_status();
    $until = (int)($status[$providerName]['until'] ?? 0);
    return $until > time();
}

function chat_provider_record_failure(string $providerName, int $httpStatus = 0, ?int $retryAfter = null): void {
    $status = chat_provider_cooldown_status();
    $cooldownSec = max(30, min(300, $retryAfter ?? 60));
    $status[$providerName] = [
        'until' => time() + $cooldownSec,
        'duration_sec' => $cooldownSec,
        'last_status' => $httpStatus,
        'failed_at' => date('Y-m-d H:i:s'),
        'failure_count' => ((int)($status[$providerName]['failure_count'] ?? 0)) + 1
    ];
    @file_put_contents(chat_provider_cooldown_file(), json_encode($status, JSON_PRETTY_PRINT), LOCK_EX);
}

function chat_provider_record_success(string $providerName): void {
    $status = chat_provider_cooldown_status();
    if (isset($status[$providerName])) {
        unset($status[$providerName]);
        @file_put_contents(chat_provider_cooldown_file(), json_encode($status, JSON_PRETTY_PRINT), LOCK_EX);
    }
}

function chat_provider_reset_cooldown(string $providerName = ''): void {
    if ($providerName === '') {
        @unlink(chat_provider_cooldown_file());
        return;
    }
    $status = chat_provider_cooldown_status();
    unset($status[$providerName]);
    @file_put_contents(chat_provider_cooldown_file(), json_encode($status, JSON_PRETTY_PRINT), LOCK_EX);
}

function chat_provider_mask_secret(string $secret, int $prefix = 4, int $suffix = 3): string {
    $len = strlen($secret);
    if ($len === 0) return '—';
    if ($len <= ($prefix + $suffix)) return str_repeat('*', $len);
    return substr($secret, 0, $prefix) . '...' . substr($secret, -$suffix);
}

/** No redirects, no raw provider errors or credentials in logs. */
function chat_provider_transport(array $provider, array $payload): array {
    if (!function_exists('curl_init')) return ['status' => 0, 'body' => [], 'retry_after' => null];
    $ch = curl_init($provider['url']);
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 18,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $provider['key'],
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        CURLOPT_HEADERFUNCTION => function($ch, $headerLine) use (&$headers) {
            $len = strlen($headerLine);
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        }
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (PHP_VERSION_ID < 80500) {
        @curl_close($ch);
    }
    $retryAfter = null;
    if (isset($headers['retry-after'])) {
        $ra = (int)$headers['retry-after'];
        if ($ra > 0) $retryAfter = $ra;
    }
    return [
        'status' => $status,
        'body' => is_string($raw) ? (json_decode($raw, true) ?: []) : [],
        'retry_after' => $retryAfter
    ];
}

/** One attempt per configured provider; respects circuit breaker cooldown; never reroute a safety refusal. */
function chat_provider_answer(array $messages, ?array $providers = null, ?callable $transport = null, ?bool $enableCooldown = null): array {
    $start = microtime(true);
    $realTransport = ($transport === null);
    $enableCooldown = $enableCooldown ?? $realTransport;
    $transport = $transport ?? 'chat_provider_transport';
    $allProviders = $providers ?? chat_provider_config();
    foreach ($allProviders as $p) {
        $pName = $p['name'] ?? 'unknown';
        if ($enableCooldown && chat_provider_is_in_cooldown($pName)) {
            continue;
        }
        $payload = ['model' => $p['model'], 'messages' => $messages, 'temperature' => 0.4, 'stream' => false];
        if ($pName === 'groq') {
            $payload['max_completion_tokens'] = 2048;
            $payload['reasoning_effort'] = 'low';
        } else {
            $payload['max_tokens'] = 800;
        }
        $r = $transport($p, $payload);
        $status = (int)($r['status'] ?? 0);
        $body = is_array($r['body'] ?? null) ? $r['body'] : [];
        $choice = $body['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        if (!empty($message['refusal']) || ($choice['finish_reason'] ?? '') === 'content_filter') {
            if ($enableCooldown) chat_provider_record_success($pName);
            return [
                'message' => 'Tôi chưa thể hỗ trợ yêu cầu này. Bạn có thể đặt lại câu hỏi theo hướng tìm hiểu nghề hoặc cách xử lý an toàn.',
                'model' => $pName . '/' . $p['model'],
                'provider' => $pName,
                'latency_ms' => (int)((microtime(true) - $start) * 1000),
                'http_status' => $status
            ];
        }
        if ($status >= 200 && $status < 300) {
            $text = $message['content'] ?? null;
            if (is_string($text) && trim($text) !== '') {
                if ($enableCooldown) chat_provider_record_success($pName);
                return [
                    'message' => mb_substr(trim($text), 0, 8000),
                    'model' => $pName . '/' . $p['model'],
                    'provider' => $pName,
                    'latency_ms' => (int)((microtime(true) - $start) * 1000),
                    'http_status' => $status
                ];
            }
            if ($enableCooldown) chat_provider_record_failure($pName, $status);
            break; // Invalid success response needs diagnosis, not blind routing.
        }
        if ($enableCooldown && ($status === 429 || $status >= 500 || $status === 0 || $status === 408)) {
            chat_provider_record_failure($pName, $status, $r['retry_after'] ?? null);
        }
        if (!in_array($status, [0, 401, 402, 408, 429], true) && $status < 500) break;
    }
    return [
        'message' => 'AI hiện chưa khả dụng hoặc đã hết hạn mức. Câu hỏi đã được lưu; bạn có thể thử lại sau.',
        'model' => 'unavailable',
        'provider' => 'none',
        'latency_ms' => (int)((microtime(true) - $start) * 1000),
        'http_status' => 503
    ];
}

function chat_provider_health_check(string $providerName): array {
    $configs = chat_provider_config();
    $target = null;
    foreach ($configs as $cfg) {
        if ($cfg['name'] === $providerName) {
            $target = $cfg;
            break;
        }
    }
    if (!$target) {
        return [
            'ok' => false,
            'provider' => $providerName,
            'model' => '—',
            'latency_ms' => 0,
            'http_status' => 0,
            'message' => "Provider '{$providerName}' chưa được cấu hình biến môi trường.",
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    $start = microtime(true);
    $payload = [
        'model' => $target['model'],
        'messages' => [['role' => 'user', 'content' => 'Test connection. Respond with: OK']],
        'temperature' => 0.1,
        'stream' => false
    ];
    if ($target['name'] === 'groq') {
        $payload['max_completion_tokens'] = 20;
    } else {
        $payload['max_tokens'] = 20;
    }

    $res = chat_provider_transport($target, $payload);
    $latency = (int)((microtime(true) - $start) * 1000);
    $status = (int)$res['status'];
    $ok = ($status >= 200 && $status < 300);

    if ($ok) {
        chat_provider_record_success($providerName);
    } else {
        chat_provider_record_failure($providerName, $status);
    }

    return [
        'ok' => $ok,
        'provider' => $providerName,
        'model' => $target['model'],
        'latency_ms' => $latency,
        'http_status' => $status,
        'message' => $ok ? "Kết nối thành công tới {$providerName} ({$latency}ms)" : "Kết nối thất bại (HTTP {$status})",
        'timestamp' => date('Y-m-d H:i:s')
    ];
}
