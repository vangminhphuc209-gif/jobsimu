<?php
declare(strict_types=1);

/**
 * api/game/events.php
 * Records player actions and validates sequential event logs.
 */

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Chỉ chấp nhận phương thức POST.', 405, 'METHOD_NOT_ALLOWED');
}

api_verify_csrf_token();

$input = api_get_json_input();
$runId = $input['run_id'] ?? null;
$eventSeq = (int)($input['event_seq'] ?? 0);
$eventId = $input['event_id'] ?? null;
$actionId = $input['action_id'] ?? null;
$payload = api_sanitize_game_payload($input['payload'] ?? []);

if (!$runId || $eventSeq <= 0 || !$eventId || !$actionId) {
    api_error('Thiếu tham số sự kiện bắt buộc (run_id, event_seq, event_id, action_id).', 400, 'INVALID_PAYLOAD');
}

$now = date('Y-m-d H:i:s');

try {
    // 1. Verify sequence order
    $stmtLastSeq = $pdo->prepare("SELECT MAX(event_seq) AS max_seq FROM game_events WHERE run_id = ?");
    $stmtLastSeq->execute([$runId]);
    $lastSeq = (int)($stmtLastSeq->fetch()['max_seq'] ?? 0);

    if ($eventSeq !== $lastSeq + 1) {
        api_error("Thứ tự sự kiện không hợp lệ. Mong đợi seq=" . ($lastSeq + 1) . ", nhận được seq={$eventSeq}.", 409, 'SEQUENCE_MISMATCH');
    }

    // 2. Insert event
    $stmtInsert = $pdo->prepare("
        INSERT INTO game_events (run_id, event_seq, event_id, action_id, payload_json, created_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmtInsert->execute([
        $runId,
        $eventSeq,
        $eventId,
        $actionId,
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        $now
    ]);

    // 3. Update NPC metrics or memory flags if present in payload
    if (!empty($payload['npc_id']) && !empty($payload['npc_mutations'])) {
        $npcId = $payload['npc_id'];
        $stmtGetNPC = $pdo->prepare("SELECT state_json FROM game_npc_states WHERE run_id = ? AND npc_id = ?");
        $stmtGetNPC->execute([$runId, $npcId]);
        $existing = $stmtGetNPC->fetch();

        $stateData = $existing ? json_decode($existing['state_json'], true) : [
            'id' => $npcId,
            'relationshipMetrics' => ['trust' => 0, 'cooperation' => 0, 'confidence' => 0],
            'memoryFlags' => []
        ];

        foreach ($payload['npc_mutations'] as $k => $v) {
            if (str_starts_with($k, 'metric_')) {
                $metricKey = substr($k, 7);
                $stateData['relationshipMetrics'][$metricKey] = ($stateData['relationshipMetrics'][$metricKey] ?? 0) + $v;
            } else {
                $stateData['memoryFlags'][$k] = $v;
            }
        }

        $stmtSaveNPC = $pdo->prepare("
            INSERT INTO game_npc_states (run_id, npc_id, state_json, updated_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), updated_at = VALUES(updated_at)
        ");
        $stmtSaveNPC->execute([$runId, $npcId, json_encode($stateData, JSON_UNESCAPED_UNICODE), $now]);
    }

    api_json_response([
        'success' => true,
        'data' => [
            'run_id' => $runId,
            'event_seq' => $eventSeq,
            'recorded_at' => $now
        ]
    ]);
} catch (Exception $e) {
    api_error('Lỗi khi ghi nhận sự kiện: ' . $e->getMessage(), 500, 'DB_ERROR');
}
