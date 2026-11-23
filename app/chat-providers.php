<?php
declare(strict_types=1);

/**
 * Đọc giá trị cấu hình theo thứ tự ưu tiên đa tầng:
 * 1. getenv($key)
 * 2. $_ENV[$key]
 * 3. $_SERVER[$key]
 * 4. Mảng $config['ai'][$key] hoặc $config['ai'][strtolower($key)] (nếu $config tồn tại)
 * 5. Bảng CSDL settings: setting(strtolower($key))
 * 6. $default
 */
function chat_provider_env(string $key, string $default = ''): string {
    $val = getenv($key);
    if ($val !== false && trim((string)$val) !== '') {
        return trim((string)$val);
    }
    if (isset($_ENV[$key]) && trim((string)$_ENV[$key]) !== '') {
        return trim((string)$_ENV[$key]);
    }
    if (isset($_SERVER[$key]) && trim((string)$_SERVER[$key]) !== '') {
        return trim((string)$_SERVER[$key]);
    }

    global $config;
    if (is_array($config) && isset($config['ai']) && is_array($config['ai'])) {
        $ai = $config['ai'];
        if (isset($ai[$key]) && trim((string)$ai[$key]) !== '') {
            return trim((string)$ai[$key]);
        }
        $lower = strtolower($key);
        if (isset($ai[$lower]) && trim((string)$ai[$lower]) !== '') {
            return trim((string)$ai[$lower]);
        }
    }

    if (function_exists('setting')) {
        $dbVal = setting(strtolower($key));
        if (trim($dbVal) !== '') {
            return trim($dbVal);
        }
    }

    return $default;
}

/**
 * Xác định nguồn nạp cấu hình thực tế:
 * 'env' (Biến môi trường hệ thống / .env)
 * 'config' (File config.php)
 * 'db' (Cơ sở dữ liệu bảng settings)
 * 'none' (Chưa có cấu hình)
 */
function chat_provider_detect_source(string $key): string {
    $val = getenv($key);
    if ($val !== false && trim((string)$val) !== '') return 'env';
    if (isset($_ENV[$key]) && trim((string)$_ENV[$key]) !== '') return 'env';
    if (isset($_SERVER[$key]) && trim((string)$_SERVER[$key]) !== '') return 'env';

    global $config;
    if (is_array($config) && isset($config['ai']) && is_array($config['ai'])) {
        if (!empty($config['ai'][$key]) || !empty($config['ai'][strtolower($key)])) return 'config';
    }

    if (function_exists('setting')) {
        if (trim(setting(strtolower($key))) !== '') return 'db';
    }

    return 'none';
}

/**
 * Cung cấp cấu hình thô đầy đủ cho mọi provider, dùng chung giữa Chatbot và Admin UI.
 */
function chat_provider_get_raw_config(): array {
    $groqKey = chat_provider_env('GROQ_API_KEY');
    $groqModel = chat_provider_env('GROQ_MODEL', 'openai/gpt-oss-120b');

    $cfToken = chat_provider_env('CLOUDFLARE_API_TOKEN');
    $cfAccount = chat_provider_env('CLOUDFLARE_ACCOUNT_ID');
    $cfModel = chat_provider_env('CLOUDFLARE_MODEL', '@cf/meta/llama-3.1-8b-instruct-fp8-fast');

    $groqConfigured = ($groqKey !== '');

    // Account ID Cloudflare: chấp nhận chuỗi hex 31-36 ký tự (chuẩn là 32 ký tự hex)
    $cfAccountLen = strlen($cfAccount);
    $cfAccountValid = ($cfAccount !== '' && preg_match('/^[a-f0-9]{31,36}$/i', $cfAccount) === 1);
    $cfConfigured = ($cfToken !== '' && $cfAccountValid);

    return [
        'groq' => [
            'name' => 'groq',
            'label' => 'Groq (Nhà cung cấp chính)',
            'key' => $groqKey,
            'model' => $groqModel ?: 'openai/gpt-oss-120b',
            'configured' => $groqConfigured,
            'source' => chat_provider_detect_source('GROQ_API_KEY'),
            'missing' => $groqConfigured ? [] : ['GROQ_API_KEY'],
        ],
        'cloudflare' => [
            'name' => 'cloudflare',
            'label' => 'Cloudflare Workers AI (Dự phòng)',
            'token' => $cfToken,
            'account' => $cfAccount,
            'account_len' => $cfAccountLen,
            'model' => $cfModel ?: '@cf/meta/llama-3.1-8b-instruct-fp8-fast',
            'configured' => $cfConfigured,
            'account_valid' => $cfAccountValid,
            'source' => chat_provider_detect_source('CLOUDFLARE_API_TOKEN'),
            'missing' => array_values(array_filter([
                $cfToken === '' ? 'CLOUDFLARE_API_TOKEN' : null,
                $cfAccount === '' ? 'CLOUDFLARE_ACCOUNT_ID' : (!$cfAccountValid ? 'CLOUDFLARE_ACCOUNT_ID (phải là 32 ký tự hex)' : null),
            ])),
        ]
    ];
}

function chat_provider_config(): array {
    $raw = chat_provider_get_raw_config();
    $providers = [];

    if ($raw['groq']['configured']) {
        $providers[] = [
            'name' => 'groq',
            'key' => $raw['groq']['key'],
            'url' => 'https://api.groq.com/openai/v1/chat/completions',
            'model' => $raw['groq']['model'],
        ];
    }

    if ($raw['cloudflare']['configured']) {
        $providers[] = [
            'name' => 'cloudflare',
            'key' => $raw['cloudflare']['token'],
            'url' => 'https://api.cloudflare.com/client/v4/accounts/' . $raw['cloudflare']['account'] . '/ai/v1/chat/completions',
            'model' => $raw['cloudflare']['model'],
            'account' => $raw['cloudflare']['account']
        ];
    }

    return $providers;
}

function chat_provider_cooldown_file(): string {
    return sys_get_temp_dir() . '/jobsimu_chat_cooldown.json';
}

function chat_provider_health_file(): string {
    return sys_get_temp_dir() . '/jobsimu_chat_health.json';
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

/**
 * Lấy trạng thái Health Check độc lập:
 * 1. 'unconfigured': Thiếu cấu hình
 * 2. 'untested': Chưa kiểm tra
 * 3. 'healthy': Kết nối thành công
 * 4. 'error': Kết nối lỗi
 */
function chat_provider_get_health_status(string $providerName): array {
    $raw = chat_provider_get_raw_config();
    $pCfg = $raw[$providerName] ?? null;

    if (!$pCfg || !$pCfg['configured']) {
        return [
            'status' => 'unconfigured',
            'label' => 'Thiếu cấu hình',
            'badge_class' => 'badge-off',
            'ok' => false,
            'http_status' => 0,
            'latency_ms' => 0,
            'message' => 'Chưa cấu hình đầy đủ API Key hoặc Account ID.',
            'tested_at' => null,
            'missing' => $pCfg['missing'] ?? []
        ];
    }

    $file = chat_provider_health_file();
    $data = [];
    if (is_file($file)) {
        $rawFile = @file_get_contents($file);
        if ($rawFile) {
            $data = json_decode($rawFile, true) ?: [];
        }
    }

    if (!isset($data[$providerName])) {
        return [
            'status' => 'untested',
            'label' => 'Chưa kiểm tra',
            'badge_class' => 'badge-untested',
            'ok' => null,
            'http_status' => 0,
            'latency_ms' => 0,
            'message' => 'Đã cấu hình nhưng chưa thực hiện kiểm tra kết nối.',
            'tested_at' => null,
            'missing' => []
        ];
    }

    $entry = $data[$providerName];
    $ok = !empty($entry['ok']);
    return [
        'status' => $ok ? 'healthy' : 'error',
        'label' => $ok ? 'Kết nối thành công' : 'Kết nối lỗi',
        'badge_class' => $ok ? 'badge-on' : 'badge-error',
        'ok' => $ok,
        'http_status' => (int)($entry['http_status'] ?? 0),
        'latency_ms' => (int)($entry['latency_ms'] ?? 0),
        'message' => (string)($entry['message'] ?? ''),
        'tested_at' => (string)($entry['tested_at'] ?? ''),
        'missing' => []
    ];
}

function chat_provider_save_health_status(string $providerName, array $result): void {
    $file = chat_provider_health_file();
    $data = [];
    if (is_file($file)) {
        $rawFile = @file_get_contents($file);
        if ($rawFile) {
            $data = json_decode($rawFile, true) ?: [];
        }
    }
    $data[$providerName] = [
        'ok' => !empty($result['ok']),
        'http_status' => (int)($result['http_status'] ?? 0),
        'latency_ms' => (int)($result['latency_ms'] ?? 0),
        'message' => (string)($result['message'] ?? ''),
        'tested_at' => date('Y-m-d H:i:s'),
    ];
    @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Làm sạch lỗi API, không bao giờ in key/token hoặc raw traceback.
 */
function chat_provider_sanitize_error(int $status): string {
    switch ($status) {
        case 401:
            return 'Lỗi xác thực (HTTP 401): Khóa API hoặc Token không hợp lệ hoặc đã bị thu hồi.';
        case 403:
            return 'Lỗi phân quyền (HTTP 403): Token không đủ quyền truy cập tài nguyên Workers AI.';
        case 404:
            return 'Không tìm thấy tài nguyên (HTTP 404): Account ID không đúng hoặc model không tồn tại.';
        case 429:
            return 'Vượt quá hạn mức (HTTP 429): Tài khoản đã chạm ngưỡng Rate Limit hoặc hết credits.';
        case 408:
        case 0:
            return 'Hết thời gian chờ (Timeout): Không thể kết nối tới máy chủ API bên thứ ba.';
        case 500:
        case 502:
        case 503:
        case 504:
            return "Lỗi máy chủ nhà cung cấp (HTTP {$status}): Dịch vụ tạm thời không phản hồi.";
        default:
            return "Kết nối thất bại (HTTP {$status}).";
    }
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

    if (empty($allProviders)) {
        return [
            'message' => 'AI hiện chưa khả dụng hoặc đã hết hạn mức. Câu hỏi đã được lưu; bạn có thể thử lại sau.',
            'model' => 'unavailable',
            'provider' => 'none',
            'latency_ms' => (int)((microtime(true) - $start) * 1000),
            'http_status' => 503,
            'fallback_reason' => 'unconfigured'
        ];
    }

    $lastStatus = 503;
    $fallbackReason = 'all_busy';
    $anyInCooldown = false;
    $allInCooldown = true;

    foreach ($allProviders as $p) {
        $pName = $p['name'] ?? 'unknown';
        if ($enableCooldown && chat_provider_is_in_cooldown($pName)) {
            $anyInCooldown = true;
            continue;
        }
        $allInCooldown = false;

        $payload = ['model' => $p['model'], 'messages' => $messages, 'temperature' => 0.4, 'stream' => false];
        if ($pName === 'groq') {
            $payload['max_completion_tokens'] = 2048;
            $payload['reasoning_effort'] = 'low';
        } else {
            $payload['max_tokens'] = 800;
        }
        $r = $transport($p, $payload);
        $status = (int)($r['status'] ?? 0);
        $lastStatus = $status;
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
            $fallbackReason = 'invalid_response';
            break;
        }

        // Map fallback reason based on failure status
        if ($status === 401 || $status === 403) {
            $fallbackReason = 'auth_error';
        } elseif ($status === 429 || $status === 402) {
            $fallbackReason = 'quota_exceeded';
        } elseif ($status === 0 || $status === 408) {
            $fallbackReason = 'timeout';
        } elseif ($status >= 500) {
            $fallbackReason = 'server_error';
        }

        if ($enableCooldown && ($status === 429 || $status >= 500 || $status === 0 || $status === 408)) {
            chat_provider_record_failure($pName, $status, $r['retry_after'] ?? null);
        }

        if (!in_array($status, [0, 401, 402, 408, 429], true) && $status < 500) break;
    }

    if ($allInCooldown) {
        $fallbackReason = 'circuit_breaker_active';
    }

    return [
        'message' => 'AI hiện chưa khả dụng hoặc đã hết hạn mức. Câu hỏi đã được lưu; bạn có thể thử lại sau.',
        'model' => 'unavailable',
        'provider' => 'none',
        'latency_ms' => (int)((microtime(true) - $start) * 1000),
        'http_status' => $lastStatus ?: 503,
        'fallback_reason' => $fallbackReason
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
        $raw = chat_provider_get_raw_config();
        $missing = $raw[$providerName]['missing'] ?? ['Chưa cấu hình API Key'];
        $res = [
            'ok' => false,
            'provider' => $providerName,
            'model' => '—',
            'latency_ms' => 0,
            'http_status' => 0,
            'message' => 'Thiếu cấu hình: ' . implode(', ', $missing),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        chat_provider_save_health_status($providerName, $res);
        return $res;
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
        $cleanMsg = "Kết nối thành công tới {$providerName} ({$latency}ms)";
    } else {
        chat_provider_record_failure($providerName, $status);
        $cleanMsg = chat_provider_sanitize_error($status);
    }

    $result = [
        'ok' => $ok,
        'provider' => $providerName,
        'model' => $target['model'],
        'latency_ms' => $latency,
        'http_status' => $status,
        'message' => $cleanMsg,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    chat_provider_save_health_status($providerName, $result);
    return $result;
}
