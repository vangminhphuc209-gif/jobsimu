<?php
declare(strict_types=1);

/**
 * api/game/finish.php
 * Finalizes the Day 1 simulation run and generates an evidence-based report.
 * Strictly enforces NO SINGLE SCORE metric (Invariants #5 and QA-09).
 */

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Chỉ chấp nhận phương thức POST.', 405, 'METHOD_NOT_ALLOWED');
}

api_verify_csrf_token();

$input = api_get_json_input();
$runId = $input['run_id'] ?? null;
$reflection = $input['reflection'] ?? [];

if (!$runId) {
    api_error('Thiếu tham số run_id.', 400, 'MISSING_PARAM');
}

$now = date('Y-m-d H:i:s');

try {
    // 1. Fetch all logged events for this run
    $stmtEvents = $pdo->prepare("SELECT * FROM game_events WHERE run_id = ? ORDER BY event_seq ASC");
    $stmtEvents->execute([$runId]);
    $events = $stmtEvents->fetchAll();

    // 2. Synthesize evidence across 5 competencies
    $competencies = [
        'analytical' => ['evidence' => [], 'count' => 0],
        'creative' => ['evidence' => [], 'count' => 0],
        'empathetic' => ['evidence' => [], 'count' => 0],
        'decisive' => ['evidence' => [], 'count' => 0],
        'accurate' => ['evidence' => [], 'count' => 0],
    ];

    $hasFeedbackCollection = false;

    foreach ($events as $ev) {
        $payload = json_decode($ev['payload_json'] ?? '{}', true);
        if ($ev['event_id'] === 'FEEDBACK-01') {
            $hasFeedbackCollection = true;
        }

        if (!empty($payload['competency']) && isset($competencies[$payload['competency']])) {
            $compKey = $payload['competency'];
            $competencies[$compKey]['count']++;
            if (!empty($payload['note'])) {
                $competencies[$compKey]['evidence'][] = $payload['note'];
            }
        }
    }

    // QA-05 Check: If exit ticket feedback collection was skipped
    $dataSufficiency = $hasFeedbackCollection ? 'Đầy đủ bằng chứng thực tế' : 'Chưa đủ dữ liệu bằng chứng cuối giờ (Thiếu phiếu Exit Ticket)';

    // Build qualitative descriptors (No single number score!)
    $qualitativeReport = [
        'runId' => $runId,
        'completedAt' => $now,
        'dataSufficiency' => $dataSufficiency,
        'totalActionsLogged' => count($events),
        'competencies' => [
            'analytical' => [
                'dimension' => 'Năng lực Phân tích & Đánh giá Sư phạm',
                'level' => $competencies['analytical']['count'] >= 2 ? 'Vững vàng' : 'Cần rèn luyện thêm',
                'evidence' => $competencies['analytical']['evidence']
            ],
            'creative' => [
                'dimension' => 'Năng lực Ứng biến & Sáng tạo Giảng dạy',
                'level' => $competencies['creative']['count'] >= 2 ? 'Linh hoạt' : 'Tiêu chuẩn',
                'evidence' => $competencies['creative']['evidence']
            ],
            'empathetic' => [
                'dimension' => 'Năng lực Thấu cảm & Đồng hành Học sinh',
                'level' => $competencies['empathetic']['count'] >= 2 ? 'Sâu sắc & Chu đáo' : 'Cần thêm thời gian lắng nghe',
                'evidence' => $competencies['empathetic']['evidence']
            ],
            'decisive' => [
                'dimension' => 'Năng lực Ra Quyết định & Quản lý Tình huống',
                'level' => $competencies['decisive']['count'] >= 2 ? 'Dứt khoát, Trách nhiệm' : 'Thận trọng',
                'evidence' => $competencies['decisive']['evidence']
            ],
            'accurate' => [
                'dimension' => 'Năng lực Chuẩn xác & Tuân thủ Quy trình',
                'level' => $competencies['accurate']['count'] >= 2 ? 'Chính xác & Đúng chuẩn' : 'Cơ bản',
                'evidence' => $competencies['accurate']['evidence']
            ]
        ],
        'pedagogicalReflection' => [
            'reflectionInput' => $reflection,
            'summaryComment' => 'Bạn đã hoàn thành trọn vẹn Ngày 1 với tư cách là giáo viên bộ môn tại trường THCS Tuệ Đức. Báo cáo này phản ánh các lựa chọn hành động và thái độ nghề nghiệp thực tế của bạn.'
        ]
    ];

    // 3. Insert into game_reports
    $stmtReport = $pdo->prepare("
        INSERT INTO game_reports (run_id, report_version, summary_json, completed_at)
        VALUES (?, '1.0.0', ?, ?)
        ON DUPLICATE KEY UPDATE summary_json = VALUES(summary_json), completed_at = VALUES(completed_at)
    ");
    $stmtReport->execute([$runId, json_encode($qualitativeReport, JSON_UNESCAPED_UNICODE), $now]);

    // 4. Mark run as completed
    $stmtComplete = $pdo->prepare("UPDATE game_runs SET status = 'completed', updated_at = ? WHERE id = ?");
    $stmtComplete->execute([$now, $runId]);

    api_json_response([
        'success' => true,
        'data' => $qualitativeReport
    ]);
} catch (Exception $e) {
    api_error('Lỗi khi hoàn thành phiên chơi: ' . $e->getMessage(), 500, 'DB_ERROR');
}
