<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/chat-providers.php';
require_once __DIR__ . '/may-knowledge.php';
require_once __DIR__ . '/may-actions.php';
require_once __DIR__ . '/may-prompt.php';
require_once __DIR__ . '/may-telemetry.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed', 'message' => 'Yêu cầu phương thức POST.']);
    exit;
}

// 1. Parse JSON or POST payload
$raw = file_get_contents('php://input');
$data = [];
if (!empty($raw)) {
    $data = json_decode($raw, true) ?: [];
}
if (empty($data)) {
    $data = $_POST;
}

// 2. CSRF Verification
$clientCsrf = $data['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($_SESSION['csrf']) || !is_string($clientCsrf) || !hash_equals($_SESSION['csrf'], $clientCsrf)) {
    http_response_code(419);
    echo json_encode([
        'ok' => false,
        'error' => 'csrf_invalid',
        'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang để tiếp tục.'
    ]);
    exit;
}

$currentUser = user();
$remoteIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// 3. Rate Limiter
$rateCheck = may_check_rate_limit($currentUser, $remoteIp);
if (!$rateCheck['allowed']) {
    http_response_code(429);
    echo json_encode([
        'ok' => false,
        'error' => 'quota_exceeded',
        'message' => 'Bạn đã sử dụng hết hạn mức trò chuyện trong ngày (' . $rateCheck['limit'] . ' câu). Hãy quay lại vào ngày mai nhé!',
        'quota_remaining' => 0,
        'quota_limit' => $rateCheck['limit']
    ]);
    exit;
}

// 4. Input Validation
$userMessage = trim((string)($data['message'] ?? ''));
if ($userMessage === '' || mb_strlen($userMessage) > 1500) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'error' => 'invalid_message',
        'message' => 'Tin nhắn không được để trống và tối đa 1500 ký tự.'
    ]);
    exit;
}

// 5. Context Sanitization
$rawContext = is_array($data['context'] ?? null) ? $data['context'] : [];
$allowedPages = [
    'home', 'discover', 'lookup', 'compare', 'plan',
    'knowledge-hub', 'careers', 'career', 'majors', 'major-detail',
    'university-explorer', 'university-compare', 'assessment-hub', 'assessment-report',
    'ai-roadmap', 'simulations', 'dashboard', 'constraints', 'result'
];
$pageId = in_array($rawContext['page_id'] ?? '', $allowedPages, true) ? $rawContext['page_id'] : '';
$entityId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($rawContext['entity_id'] ?? ''));
$allowProfileContext = !empty($rawContext['allow_profile_context']);

$sanitizedContext = [
    'page_id' => $pageId,
    'entity_id' => $entityId,
    'allow_profile_context' => $allowProfileContext
];

// 6. User Profile Context (strictly opt-in and authenticated only)
$userProfile = null;
if ($currentUser && $allowProfileContext) {
    try {
        $stmt = db()->prepare('SELECT result_code, scores FROM assessments WHERE user_id = ? ORDER BY completed_at DESC LIMIT 1');
        $stmt->execute([(int)$currentUser['id']]);
        $latestAssessment = $stmt->fetch();

        $topTraits = [];
        if ($latestAssessment && !empty($latestAssessment['scores'])) {
            $scores = json_decode((string)$latestAssessment['scores'], true) ?: [];
            arsort($scores);
            $topTypes = array_slice(array_keys($scores), 0, 3);
            $labels = riasec_labels();
            foreach ($topTypes as $t) {
                if (isset($labels[$t])) $topTraits[] = $labels[$t] . " ({$t})";
            }
        }

        $favStmt = db()->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = ?');
        $favStmt->execute([(int)$currentUser['id']]);
        $favCount = (int)$favStmt->fetchColumn();

        $userProfile = [
            'result_code' => $latestAssessment['result_code'] ?? null,
            'top_traits' => $topTraits,
            'favorites_count' => $favCount
        ];
    } catch (Throwable $e) {
        $userProfile = null;
    }
}

// 7. Grounded Retrieval
$evidence = may_retrieve_evidence($userMessage, $sanitizedContext);

// 8. Action Matching
$preActions = may_detect_actions($userMessage, $currentUser, $sanitizedContext);

// 9. Conversation History Validation
$rawHistory = is_array($data['history'] ?? null) ? $data['history'] : [];
$validatedHistory = [];
foreach ($rawHistory as $h) {
    if (is_array($h) && isset($h['role'], $h['message'])) {
        $role = $h['role'] === 'assistant' ? 'assistant' : 'user';
        $text = trim((string)$h['message']);
        if ($text !== '') {
            $validatedHistory[] = ['role' => $role, 'message' => mb_substr($text, 0, 1000)];
        }
    }
}

// 10. System Prompt & LLM Invocation
$systemPrompt = may_build_system_prompt($evidence, $userProfile, $sanitizedContext);
$chatMessages = may_build_chat_messages($systemPrompt, $validatedHistory, $userMessage);

$response = chat_provider_answer($chatMessages);

$modelAnswer = $response['message'] ?? '';
$providerModel = $response['model'] ?? 'unavailable';
$providerName = $response['provider'] ?? 'none';
$latencyMs = (int)($response['latency_ms'] ?? 0);
$httpStatus = (int)($response['http_status'] ?? 200);

// 11. Parse actions from AI response & combine with pre-detected actions
$postActions = may_detect_actions($modelAnswer, $currentUser, $sanitizedContext);
$mergedActions = [];
foreach (array_merge($preActions, $postActions) as $btn) {
    if ($btn && !isset($mergedActions[$btn['id']])) {
        $mergedActions[$btn['id']] = $btn;
    }
}
$finalActions = array_values(array_slice($mergedActions, 0, 3));

$cleanAnswer = may_clean_action_tags($modelAnswer);

// 12. Record telemetry (zero sensitive data)
$fallbackReason = $response['fallback_reason'] ?? '';
may_log_telemetry([
    'user_type' => $currentUser ? 'Học sinh #' . $currentUser['id'] : 'Khách',
    'provider' => $providerName,
    'model' => $providerModel,
    'latency_ms' => $latencyMs,
    'http_status' => $httpStatus,
    'status' => ($providerModel === 'unavailable' ? 'fallback' : 'completed'),
    'fallback_reason' => $fallbackReason,
    'error' => ($providerModel === 'unavailable' ? ($fallbackReason ?: 'All providers unavailable') : '')
]);

// 13. Increment rate limit counter
may_increment_rate_limit($rateCheck['key']);
$quotaRemaining = max(0, $rateCheck['remaining'] - 1);

// 14. Save chat message to database for logged in user (optional history)
if ($currentUser && !empty($currentUser['id'])) {
    try {
        $ins = db()->prepare('INSERT INTO ai_chat_messages(user_id, role, message, model, latency_ms) VALUES(?, ?, ?, ?, ?)');
        $ins->execute([(int)$currentUser['id'], 'user', mb_substr($userMessage, 0, 1500), 'user', 0]);
        $ins->execute([(int)$currentUser['id'], 'assistant', mb_substr($cleanAnswer, 0, 3000), $providerModel, $latencyMs]);
    } catch (Throwable $e) {
        // Silently continue if DB logging fails
    }
}

// 15. Return Response
echo json_encode([
    'ok' => true,
    'answer' => $cleanAnswer,
    'actions' => $finalActions,
    'provider' => $providerModel,
    'latency_ms' => $latencyMs,
    'quota_remaining' => $quotaRemaining,
    'quota_limit' => $rateCheck['limit']
], JSON_UNESCAPED_UNICODE);
