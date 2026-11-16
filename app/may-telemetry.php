<?php
declare(strict_types=1);

/**
 * Operational telemetry and rate-limiting for Mây Chatbot.
 * Stores zero sensitive personal data. Complies with privacy and architectural invariants.
 */

function may_telemetry_file(): string {
    return sys_get_temp_dir() . '/jobsimu_may_telemetry.jsonl';
}

function may_ratelimit_file(): string {
    return sys_get_temp_dir() . '/jobsimu_may_ratelimit.json';
}

/**
 * Rate limit check per IP / User.
 * Guests: 10 messages / day.
 * Logged-in users: 30 messages / day.
 */
function may_check_rate_limit(?array $user, string $ip): array {
    $file = may_ratelimit_file();
    $data = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if ($raw) $data = json_decode($raw, true) ?: [];
    }

    $today = date('Y-m-d');
    $isUser = !empty($user['id']);
    $limit = $isUser ? 30 : 10;
    $key = $isUser ? 'u_' . (int)$user['id'] : 'g_' . substr(hash('sha256', $ip . $today), 0, 16);

    $entry = $data[$key] ?? ['date' => $today, 'count' => 0];
    if (($entry['date'] ?? '') !== $today) {
        $entry = ['date' => $today, 'count' => 0];
    }

    $count = (int)$entry['count'];
    $allowed = ($count < $limit);
    $remaining = max(0, $limit - $count);

    return [
        'allowed' => $allowed,
        'remaining' => $remaining,
        'limit' => $limit,
        'count' => $count,
        'key' => $key
    ];
}

/**
 * Increment the rate limit counter after a successful request.
 */
function may_increment_rate_limit(string $key): void {
    $file = may_ratelimit_file();
    $data = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if ($raw) $data = json_decode($raw, true) ?: [];
    }

    $today = date('Y-m-d');
    if (!isset($data[$key]) || ($data[$key]['date'] ?? '') !== $today) {
        $data[$key] = ['date' => $today, 'count' => 1];
    } else {
        $data[$key]['count'] = ((int)$data[$key]['count']) + 1;
    }

    // Prune entries older than 2 days
    $threshold = date('Y-m-d', strtotime('-2 days'));
    foreach ($data as $k => $v) {
        if (($v['date'] ?? '') < $threshold) unset($data[$k]);
    }

    @file_put_contents($file, json_encode($data), LOCK_EX);
}

/**
 * Log sanitized telemetry metadata. Zero personal data, zero prompt text.
 */
function may_log_telemetry(array $entry): void {
    $file = may_telemetry_file();
    $sanitized = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_type' => $entry['user_type'] ?? 'Khách',
        'provider' => $entry['provider'] ?? 'unknown',
        'model' => $entry['model'] ?? 'unknown',
        'latency_ms' => (int)($entry['latency_ms'] ?? 0),
        'http_status' => (int)($entry['http_status'] ?? 200),
        'status' => $entry['status'] ?? 'completed',
        'error' => mb_substr((string)($entry['error'] ?? ''), 0, 200)
    ];

    $line = json_encode($sanitized, JSON_UNESCAPED_UNICODE) . "\n";
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Read recent sanitized telemetry logs.
 */
function may_get_telemetry_logs(int $limit = 50): array {
    $file = may_telemetry_file();
    if (!is_file($file)) return [];
    
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return [];
    
    $rows = [];
    $total = count($lines);
    $start = max(0, $total - $limit);
    for ($i = $total - 1; $i >= $start; $i--) {
        $decoded = json_decode($lines[$i], true);
        if ($decoded) $rows[] = $decoded;
    }
    return $rows;
}

/**
 * Calculate aggregate telemetry statistics for admin dashboard.
 */
function may_get_telemetry_stats(): array {
    $file = may_telemetry_file();
    $stats = [
        'total_requests' => 0,
        'groq_count' => 0,
        'cloudflare_count' => 0,
        'fallback_count' => 0,
        'groq_latency_sum' => 0,
        'groq_latency_avg' => 0,
        'cloudflare_latency_sum' => 0,
        'cloudflare_latency_avg' => 0,
        'today_requests' => 0
    ];

    if (!is_file($file)) return $stats;

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return $stats;

    $today = date('Y-m-d');
    foreach ($lines as $line) {
        $row = json_decode($line, true);
        if (!$row) continue;
        $stats['total_requests']++;

        if (str_starts_with((string)$row['timestamp'], $today)) {
            $stats['today_requests']++;
        }

        $provider = $row['provider'] ?? '';
        $latency = (int)($row['latency_ms'] ?? 0);
        $status = $row['status'] ?? '';

        if ($status === 'fallback' || $status === 'failed' || $provider === 'none') {
            $stats['fallback_count']++;
        } elseif ($provider === 'groq' || str_starts_with($provider, 'groq')) {
            $stats['groq_count']++;
            $stats['groq_latency_sum'] += $latency;
        } elseif ($provider === 'cloudflare' || str_starts_with($provider, 'cloudflare')) {
            $stats['cloudflare_count']++;
            $stats['cloudflare_latency_sum'] += $latency;
        }
    }

    if ($stats['groq_count'] > 0) {
        $stats['groq_latency_avg'] = (int)round($stats['groq_latency_sum'] / $stats['groq_count']);
    }
    if ($stats['cloudflare_count'] > 0) {
        $stats['cloudflare_latency_avg'] = (int)round($stats['cloudflare_latency_sum'] / $stats['cloudflare_count']);
    }

    return $stats;
}
