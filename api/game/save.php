<?php
declare(strict_types=1);

/**
 * api/game/save.php
 * Persists simulation snapshot with optimistic locking and idempotency.
 */

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Chỉ chấp nhận phương thức POST.', 405, 'METHOD_NOT_ALLOWED');
}

api_verify_csrf_token();

$input = api_get_json_input();
$runId = $input['run_id'] ?? null;
$revision = (int)($input['revision'] ?? 0);
$idempotencyKey = $input['idempotency_key'] ?? null;
$snapshot = $input['snapshot'] ?? null;

if (!$runId || $revision <= 0 || !$snapshot) {
    api_error('Thiếu thông tin lưu (run_id, revision, snapshot).', 400, 'INVALID_PAYLOAD');
}

$now = date('Y-m-d H:i:s');

try {
    // 1. Check idempotency key for network retry safety (QA-10)
    if ($idempotencyKey) {
        $stmtIdem = $pdo->prepare("
            SELECT revision, saved_at FROM game_save_snapshots 
            WHERE run_id = ? AND idempotency_key = ?
        ");
        $stmtIdem->execute([$runId, $idempotencyKey]);
        $existing = $stmtIdem->fetch();
        if ($existing) {
            api_json_response([
                'success' => true,
                'message' => 'Bản lưu đã tồn tại (Idempotent response).',
                'data' => [
                    'run_id' => $runId,
                    'revision' => (int)$existing['revision'],
                    'saved_at' => $existing['saved_at']
                ]
            ]);
        }
    }

    // 2. Optimistic locking check
    $stmtLastRev = $pdo->prepare("SELECT MAX(revision) AS max_rev FROM game_save_snapshots WHERE run_id = ?");
    $stmtLastRev->execute([$runId]);
    $lastRev = (int)($stmtLastRev->fetch()['max_rev'] ?? 0);

    if ($revision !== $lastRev + 1) {
        api_error("Xung đột phiên bản lưu. Server đang ở revision={$lastRev}, nhận revision={$revision}.", 409, 'REVISION_CONFLICT');
    }

    // 3. Insert snapshot
    $stmtInsert = $pdo->prepare("
        INSERT INTO game_save_snapshots (run_id, revision, idempotency_key, snapshot_json, saved_at)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmtInsert->execute([
        $runId,
        $revision,
        $idempotencyKey,
        json_encode($snapshot, JSON_UNESCAPED_UNICODE),
        $now
    ]);

    // Update run updated_at
    $stmtUpdateRun = $pdo->prepare("UPDATE game_runs SET updated_at = ? WHERE id = ?");
    $stmtUpdateRun->execute([$now, $runId]);

    api_json_response([
        'success' => true,
        'data' => [
            'run_id' => $runId,
            'revision' => $revision,
            'saved_at' => $now
        ]
    ]);
} catch (Exception $e) {
    api_error('Lỗi khi lưu tiến trình: ' . $e->getMessage(), 500, 'DB_ERROR');
}
