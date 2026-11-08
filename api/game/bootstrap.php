<?php
declare(strict_types=1);

/**
 * api/game/bootstrap.php
 * Common initialization for JOBSIMU 3D Game API endpoints.
 * Enforces session authentication, CSRF validation, and anti-tamper rules.
 */

// The playable browser slice currently saves only on this device. The legacy
// persistence code below has not yet implemented ownership checks, an authoritative
// action reducer, or atomic retries. Fail closed BEFORE legacy bootstrap/database
// initialization; do not expose these endpoints by adding an environment toggle.
// Remove this gate only with the replacement protocol and integration tests.
http_response_code(503);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
echo json_encode([
    'success' => false,
    'error' => [
        'code' => 'GAME_SYNC_UNAVAILABLE',
        'message' => 'Chế độ 3D hiện lưu trên thiết bị này; đồng bộ máy chủ chưa được hỗ trợ.',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

define('GAME_API', true);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function api_json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_error(string $message, int $statusCode = 400, string $errorCode = 'BAD_REQUEST'): void {
    api_json_response([
        'success' => false,
        'error' => [
            'code' => $errorCode,
            'message' => $message,
        ]
    ], $statusCode);
}

function api_get_json_input(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return $_POST ?: [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        api_error('Dữ liệu JSON không hợp lệ.', 400, 'INVALID_JSON');
    }
    return $decoded;
}

function api_verify_csrf_token(): void {
    $headers = getallheaders();
    $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? null;
    
    if (!$token) {
        $input = api_get_json_input();
        $token = $input['csrf_token'] ?? $input['csrf'] ?? ($_POST['csrf'] ?? null);
    }
    
    $sessionToken = $_SESSION['csrf'] ?? null;
    if (!$sessionToken || !$token || !hash_equals($sessionToken, (string)$token)) {
        api_error('Mã xác thực CSRF không hợp lệ hoặc phiên đã hết hạn.', 403, 'CSRF_INVALID');
    }
}

/**
 * QA-09: Anti-tamper check.
 * Discards and logs any malicious client attempts to pass arbitrary score fields.
 */
function api_sanitize_game_payload(array $payload): array {
    if (isset($payload['score']) || isset($payload['points']) || isset($payload['admin'])) {
        // Log tampering attempt and unset forbidden fields
        unset($payload['score'], $payload['points'], $payload['admin']);
    }
    return $payload;
}
