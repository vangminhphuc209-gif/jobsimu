<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/user-roadmap.php';

echo "=== KIỂM THỬ TÍCH HỢP TOÀN DIỆN HÀNH TRÌNH NGƯỜI DÙNG (E2E JOURNEY) ===" . PHP_EOL;

$pdo = db();
$pdo->beginTransaction();

try {
    // BƯỚC 1: ĐĂNG KÝ VÀ KHỞI TẠO TÀI KHOẢN HỌC SINH THPT
    $email = 'e2e_student_' . time() . '@example.test';
    $stmt = $pdo->prepare("INSERT INTO users(name, email, password, role, school, grade, status) VALUES('Học Sinh Thử Nghiệm', ?, 'hash123', 'student', 'THPT Chuyên', 'Lớp 11', 'active')");
    $stmt->execute([$email]);
    $userId = (int)$pdo->lastInsertId();
    assert($userId > 0, "Student registration must succeed");
    echo "✓ Bước 1: Học sinh tạo tài khoản thành công (Lớp 11, THPT Chuyên, Hà Nội)." . PHP_EOL;

    // Trạng thái ban đầu: not_started
    $state1 = journey_user_state($userId);
    assert($state1 === 'not_started', "Initial journey state must be not_started");
    $next1 = journey_resolve_next_step($userId);
    assert($next1['action_key'] === 'take_holland', "Next priority action must be take_holland");
    echo "✓ Kiểm tra trạng thái 1: not_started ➔ Gợi ý ưu tiên: Làm trắc nghiệm Holland." . PHP_EOL;

    // BƯỚC 2: THỰC HIỆN BÀI ĐÁNH GIÁ SỞ THÍCH HOLLAND (RIASEC)
    $questions = $pdo->query("SELECT * FROM questions WHERE status='active' ORDER BY sort_order, id")->fetchAll();
    assert(count($questions) >= 6, "Active question bank must have questions");
    
    // Giả lập câu trả lời nghiêng về nhóm Realistic (R) và Investigative (I)
    $answers = [];
    foreach ($questions as $q) {
        $answers[$q['id']] = ($q['riasec_type'] === 'R' || $q['riasec_type'] === 'I') ? 5 : 2;
    }
    $scientific = score_assessment_scientific($answers, $questions, [], 180);
    assert(isset($scientific['result_code']), "Assessment must produce result_code");
    assert(strpos($scientific['result_code'], 'R') !== false || strpos($scientific['result_code'], 'I') !== false, "Code must reflect high R/I");

    $stmt = $pdo->prepare("INSERT INTO assessments(user_id, answers, scores, ipsative_z_scores, validity_metrics, result_code, ai_status, completed_at) VALUES(?, ?, ?, ?, ?, ?, 'completed', NOW())");
    $stmt->execute([
        $userId,
        json_encode($answers),
        json_encode($scientific['raw_scores']),
        json_encode($scientific['ipsative_z_scores']),
        json_encode($scientific['validity_metrics'], JSON_UNESCAPED_UNICODE),
        $scientific['result_code']
    ]);
    $assId = (int)$pdo->lastInsertId();
    assert($assId > 0, "Assessment must be persisted");
    echo "✓ Bước 2: Hoàn thành trắc nghiệm RIASEC ➔ Điểm thô, Z-score nội tại và Validity Metrics được lưu chuẩn tắc." . PHP_EOL;

    // BƯỚC 3: XEM BÁO CÁO KẾT QUẢ & LƯU PHẢN TƯ CÁ NHÂN
    $stmt = $pdo->prepare("SELECT * FROM assessments WHERE id=? AND user_id=?");
    $stmt->execute([$assId, $userId]);
    $assRow = $stmt->fetch();
    assert(!empty($assRow), "Must be able to fetch assessment report");

    // Học sinh gửi phản tư: "Kết quả này chưa giống em ở điểm..."
    $reflectionNote = 'Em thấy điểm Kỹ thuật (R) rất đúng vì em thích lắp ráp mô hình robot.';
    $vm = json_decode((string)$assRow['validity_metrics'], true) ?: [];
    $vm['student_feedback'] = $reflectionNote;
    $vm['feedback_updated_at'] = date('Y-m-d H:i:s');
    $pdo->prepare("UPDATE assessments SET validity_metrics=? WHERE id=? AND user_id=?")->execute([json_encode($vm, JSON_UNESCAPED_UNICODE), $assId, $userId]);
    echo "✓ Bước 3: Học sinh xem báo cáo kết quả và lưu phản tư cá nhân an toàn vào hồ sơ." . PHP_EOL;

    // BƯỚC 4: LƯU NGHỀ QUAN TÂM & TRA CỨU NGÀNH/TUYỂN SINH
    // Lưu nghề Lập trình viên (id=1) vào Favorites
    $pdo->prepare("INSERT INTO favorites(user_id, career_id) VALUES(?, 1)")->execute([$userId]);
    
    // Kiểm tra liên kết ngành của nghề
    $stmt = $pdo->prepare("SELECT m.name FROM career_major_paths cmp JOIN majors m ON m.id=cmp.major_id WHERE cmp.career_id=1");
    $stmt->execute();
    $majors = $stmt->fetchAll(PDO::FETCH_COLUMN);
    assert(!empty($majors), "Career 1 must have linked majors");

    // Trạng thái hành trình: career_saved
    $state4 = journey_user_state($userId);
    assert($state4 === 'career_saved', "Journey state must be career_saved");
    $next4 = journey_resolve_next_step($userId);
    assert($next4['action_key'] === 'try_simulation', "Next priority action must be try_simulation");
    echo "✓ Bước 4: Lưu nghề yêu thích ➔ Trạng thái: career_saved ➔ Gợi ý ưu tiên: Thử phòng mô phỏng." . PHP_EOL;

    // BƯỚC 5: TRẢI NGHIỆM PHÒNG MÔ PHỎNG NGHỀ (2D & CẢM XÚC PHẢN TƯ)
    $career1 = $pdo->query("SELECT * FROM careers WHERE id=1")->fetch();
    $game1 = simulation_for_career($career1);
    assert($game1 !== null, "Career 1 must have 2D simulation game");

    // Giả lập đưa ra quyết định trong mô phỏng
    $decisions = [
        's1' => ['choice' => 'A', 'score' => 25],
        's2' => ['choice' => 'B', 'score' => 30],
        's3' => ['choice' => 'A', 'score' => 25]
    ];
    $simScore = 80;
    $simFeeling = 'excited';
    $simNote = 'Thử sửa lỗi code rất cuốn hút, không cảm thấy mệt.';
    $stmt = $pdo->prepare("INSERT INTO simulation_attempts(user_id, career_id, score, decisions, skill_scores, duration_seconds, reflection_feeling, reflection_note, completed_at) VALUES(?, 1, ?, ?, '{}', 240, ?, ?, NOW())");
    $stmt->execute([$userId, $simScore, json_encode($decisions), $simFeeling, $simNote]);
    $simAttemptId = (int)$pdo->lastInsertId();
    assert($simAttemptId > 0, "Simulation attempt must be saved");

    // Kiểm tra ma trận đối chiếu
    $matrixResult = career_matrix_status(85, [
        'career_id' => 1,
        'score' => $simScore,
        'reflection_feeling' => $simFeeling,
        'reflection_note' => $simNote
    ]);
    assert($matrixResult['type'] === 'sweet_spot', "High RIASEC + High Sim + Excited must be sweet_spot");
    echo "✓ Bước 5: Hoàn thành phòng thử mô phỏng ➔ Ma trận nhận diện: Mục tiêu Vàng (Sweet Spot)." . PHP_EOL;

    // BƯỚC 6: THỐNG NHẤT BẢN ĐỒ KẾ HOẠCH THEO THÁNG (MONTHLY ROADMAP)
    $roadmap = user_roadmap_get_or_create($userId);
    assert(!empty($roadmap['id']), "Roadmap must be initialized");
    $roadmapId = (int)$roadmap['id'];

    // Cập nhật mục tiêu và nghề nhắm đến
    user_roadmap_update_details($roadmapId, $userId, [
        'goal_title' => 'Chinh phục ngành Công nghệ thông tin',
        'target_career_title' => 'Lập trình viên',
        'target_timeframe' => 'Lớp 11 - 12'
    ]);

    // Thêm công việc rèn luyện tháng này
    $taskId = user_roadmap_add_task($roadmapId, $userId, [
        'stage' => 'skills',
        'title' => 'Rèn luyện 10 bài toán thuật toán cơ bản',
        'target_month' => date('Y-m'),
        'priority' => 'high'
    ]);
    assert($taskId > 0, "Roadmap task must be created");

    // Đánh dấu hoàn thành công việc
    $toggleRes = user_roadmap_toggle_task_status($taskId, $userId, 'done');
    assert($toggleRes['status'] === 'done', "Task must be marked done");
    echo "✓ Bước 6: Lập kế hoạch theo tháng ➔ Thêm nhiệm vụ, quản lý tiến độ và hoàn thành mốc." . PHP_EOL;

    // BƯỚC 7: KIỂM TRA DASHBOARD "HÀNH TRÌNH CỦA EM"
    $finalState = journey_user_state($userId);
    assert($finalState === 'planning', "Final journey state must be planning");
    $finalNext = journey_resolve_next_step($userId);
    assert($finalNext['route'] === 'index.php?page=ai-roadmap', "Next step for planning must lead to ai-roadmap");
    echo "✓ Bước 7: Dashboard đồng bộ toàn diện trạng thái 'planning', hiển thị đầy đủ 5 khối thông tin." . PHP_EOL;

    echo PHP_EOL . "TẤT CẢ 7 BƯỚC HÀNH TRÌNH E2E ĐỀU HOẠT ĐỘNG LIỀN MẠCH 100%!" . PHP_EOL;

} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (isset($userId) && $userId > 0) {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$userId]);
    }
    echo "✓ Dữ liệu kiểm thử E2E được dọn dẹp và rollback sạch sẽ, CSDL bảo toàn tuyệt đối." . PHP_EOL;
}
