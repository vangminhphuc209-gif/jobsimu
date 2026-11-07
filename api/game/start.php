<?php
declare(strict_types=1);

/**
 * api/game/start.php
 * Initializes a new JOBSIMU 3D game session (Day 1 - Teacher Sim).
 */

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Chỉ chấp nhận phương thức POST.', 405, 'METHOD_NOT_ALLOWED');
}

api_verify_csrf_token();

$userId = $_SESSION['user_id'] ?? null;
$runId = 'run_' . bin2hex(random_bytes(16));
$now = date('Y-m-d H:i:s');

try {
    $stmt = $pdo->prepare("
        INSERT INTO game_runs (id, user_id, career_id, content_version, day_index, status, started_at, updated_at)
        VALUES (?, ?, 'giao-vien', '1.0.0', 1, 'active', ?, ?)
    ");
    $stmt->execute([$runId, $userId, $now, $now]);

    // Initial quest progress
    $initialQuest = [
        'currentCheckpoint' => 'CP-01',
        'activeObjective' => 'Đến phòng hội đồng giáo viên và trao đổi với Cô Mai.',
        'completedObjectives' => [],
        'flags' => [
            'minh_trust' => 0,
            'mentor_support' => 1,
            'colleague_cooperation' => 0,
            'parent_confidence' => 0
        ]
    ];

    $stmtQuest = $pdo->prepare("
        INSERT INTO game_quests (run_id, quest_id, status, progress_json, updated_at)
        VALUES (?, 'CP-01', 'active', ?, ?)
    ");
    $stmtQuest->execute([$runId, json_encode($initialQuest, JSON_UNESCAPED_UNICODE), $now]);

    api_json_response([
        'success' => true,
        'data' => [
            'run_id' => $runId,
            'career_id' => 'giao-vien',
            'day_index' => 1,
            'clock' => [
                'hour' => 6,
                'minute' => 45,
                'timeString' => '06:45',
                'periodName' => 'Đến trường & chuẩn bị đầu ngày'
            ],
            'activeCheckpoint' => 'CP-01',
            'quest' => $initialQuest,
            'csrf_token' => csrf_token()
        ]
    ]);
} catch (Exception $e) {
    api_error('Không thể khởi tạo phiên chơi: ' . $e->getMessage(), 500, 'DB_ERROR');
}
