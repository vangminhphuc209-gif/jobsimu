<?php
declare(strict_types=1);

/**
 * api/game/state.php
 * Fetches the latest game state snapshot and NPC states for a run.
 */

require_once __DIR__ . '/bootstrap.php';

$runId = $_GET['run_id'] ?? null;
if (!$runId) {
    api_error('Thiếu tham số run_id.', 400, 'MISSING_PARAM');
}

try {
    // 1. Verify run exists
    $stmtRun = $pdo->prepare("SELECT * FROM game_runs WHERE id = ?");
    $stmtRun->execute([$runId]);
    $run = $stmtRun->fetch();

    if (!$run) {
        api_error('Không tìm thấy phiên chơi tương ứng.', 404, 'NOT_FOUND');
    }

    // 2. Fetch latest snapshot
    $stmtSnapshot = $pdo->prepare("
        SELECT * FROM game_save_snapshots 
        WHERE run_id = ? 
        ORDER BY revision DESC 
        LIMIT 1
    ");
    $stmtSnapshot->execute([$runId]);
    $snapshot = $stmtSnapshot->fetch();

    // 3. Fetch NPC states
    $stmtNPC = $pdo->prepare("SELECT npc_id, state_json FROM game_npc_states WHERE run_id = ?");
    $stmtNPC->execute([$runId]);
    $npcRows = $stmtNPC->fetchAll();
    $npcStates = [];
    foreach ($npcRows as $row) {
        $npcStates[$row['npc_id']] = json_decode($row['state_json'], true);
    }

    // 4. Fetch quests
    $stmtQuests = $pdo->prepare("SELECT quest_id, status, progress_json FROM game_quests WHERE run_id = ?");
    $stmtQuests->execute([$runId]);
    $questRows = $stmtQuests->fetchAll();
    $quests = [];
    foreach ($questRows as $row) {
        $quests[$row['quest_id']] = [
            'status' => $row['status'],
            'progress' => json_decode($row['progress_json'] ?? '{}', true)
        ];
    }

    api_json_response([
        'success' => true,
        'data' => [
            'run' => $run,
            'snapshot' => $snapshot ? json_decode($snapshot['snapshot_json'], true) : null,
            'revision' => $snapshot ? (int)$snapshot['revision'] : 0,
            'npc_states' => $npcStates,
            'quests' => $quests,
            'csrf_token' => csrf_token()
        ]
    ]);
} catch (Exception $e) {
    api_error('Lỗi khi truy vấn trạng thái: ' . $e->getMessage(), 500, 'DB_ERROR');
}
