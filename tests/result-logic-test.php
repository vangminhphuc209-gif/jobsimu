<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

echo "=== KIỂM THỬ PHASE 5: BÁO CÁO KẾT QUẢ & ĐỐI CHIẾU MÔ PHỎNG ===" . PHP_EOL;

$pdo = db();
$pdo->beginTransaction();

try {
    // 1. Tạo user test tạm thời
    $email = 'test_p5_' . time() . '@example.test';
    $stmt = $pdo->prepare("INSERT INTO users(name, email, password, role, status) VALUES(?, ?, 'hash', 'student', 'active')");
    $stmt->execute(['Test Phase 5 Student', $email]);
    $userId = (int)$pdo->lastInsertId();

    // 2. Test Case A: Bài đánh giá cũ (Legacy: chỉ có raw scores, không có efficacy_scores, không có z_scores)
    $legacyScores = ['R' => 70, 'I' => 85, 'A' => 60, 'S' => 40, 'E' => 50, 'C' => 65];
    $stmt = $pdo->prepare("INSERT INTO assessments(user_id, answers, scores, result_code, ai_status, completed_at) VALUES(?, '[]', ?, 'IRC', 'completed', NOW())");
    $stmt->execute([$userId, json_encode($legacyScores)]);
    $legacyAssId = (int)$pdo->lastInsertId();

    // Đọc lại bài legacy
    $stmt = $pdo->prepare("SELECT * FROM assessments WHERE id=?");
    $stmt->execute([$legacyAssId]);
    $legacyRow = $stmt->fetch();

    $scores = json_decode($legacyRow['scores'], true);
    $effScores = json_decode($legacyRow['efficacy_scores'] ?? '{}', true) ?: [];
    $zScores = json_decode($legacyRow['ipsative_z_scores'] ?? '{}', true) ?: [];
    $metrics = json_decode($legacyRow['validity_metrics'] ?? '{}', true) ?: [];

    assert(count($scores) === 6, 'Legacy raw scores must have 6 RIASEC types');
    assert(empty($effScores), 'Legacy assessment has empty efficacy_scores');
    assert(empty($zScores), 'Legacy assessment has empty ipsative_z_scores');
    assert(empty($metrics), 'Legacy assessment has empty validity_metrics');
    echo "✓ Test Case A: Bài đánh giá cũ (Legacy) nạp thành công không lỗi, nhận diện đúng dữ liệu thiếu không bị crash." . PHP_EOL;

    // 3. Test Case B: Lưu phản tư của học sinh ("kết quả này chưa giống em ở điểm nào")
    $studentFeedback = 'Em thấy điểm Quản lý (E) của em hơi thấp so với thực tế vì em hay làm trưởng nhóm ở lớp.';
    $metrics['student_feedback'] = $studentFeedback;
    $metrics['feedback_updated_at'] = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("UPDATE assessments SET validity_metrics=? WHERE id=?");
    $stmt->execute([json_encode($metrics, JSON_UNESCAPED_UNICODE), $legacyAssId]);

    $stmt = $pdo->prepare("SELECT validity_metrics FROM assessments WHERE id=?");
    $stmt->execute([$legacyAssId]);
    $savedMetrics = json_decode($stmt->fetchColumn(), true);

    assert($savedMetrics['student_feedback'] === $studentFeedback, 'Student feedback must be saved properly');
    assert(!empty($savedMetrics['feedback_updated_at']), 'Feedback updated timestamp must be present');
    echo "✓ Test Case B: Lưu phản tư của học sinh vào validity_metrics thành công và bảo toàn nguyên vẹn." . PHP_EOL;

    // 4. Test Case C: Bài đánh giá hiện đại (Đầy đủ z-score, efficacy, validity_metrics)
    $modernScores = ['R' => 50, 'I' => 90, 'A' => 75, 'S' => 60, 'E' => 45, 'C' => 55];
    $modernEff = ['R' => 60, 'I' => 85, 'A' => 70, 'S' => 55, 'E' => 50, 'C' => 60];
    $modernZ = ['R' => -0.5, 'I' => 1.5, 'A' => 0.8, 'S' => 0.0, 'E' => -0.7, 'C' => -0.2];
    $modernMetrics = [
        'validity_flag' => 'valid',
        'mean_raw' => 3.8,
        'std_dev' => 0.85,
        'differentiation_index' => 40,
        'hexagon_consistency' => 3,
        'duration_seconds' => 180
    ];
    $stmt = $pdo->prepare("INSERT INTO assessments(user_id, answers, scores, efficacy_scores, ipsative_z_scores, validity_metrics, result_code, ai_status, completed_at) VALUES(?, '[]', ?, ?, ?, ?, 'IAS', 'completed', NOW())");
    $stmt->execute([$userId, json_encode($modernScores), json_encode($modernEff), json_encode($modernZ), json_encode($modernMetrics, JSON_UNESCAPED_UNICODE)]);
    $modernAssId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("SELECT * FROM assessments WHERE id=?");
    $stmt->execute([$modernAssId]);
    $modernRow = $stmt->fetch();

    $mEff = json_decode($modernRow['efficacy_scores'], true);
    $mZ = json_decode($modernRow['ipsative_z_scores'], true);
    assert(count($mEff) === 6, 'Modern assessment must have 6 efficacy scores');
    assert(count($mZ) === 6, 'Modern assessment must have 6 z scores');
    assert(isset($mZ['I']) && $mZ['I'] == 1.5, 'Z-score of I must be 1.5');
    echo "✓ Test Case C: Bài đánh giá hiện đại lưu trữ và đọc đầy đủ 3 chiều thông tin." . PHP_EOL;

    // 5. Test Case D: Đối chiếu với mô phỏng thực tế (2D & 3D) và ma trận tâm lý
    // Career 1: Lập trình viên (có 2D)
    $c1 = db()->query("SELECT * FROM careers WHERE id=1")->fetch();
    $g1 = simulation_for_career($c1);
    assert($g1 !== null, 'Career 1 must have 2D simulation');

    // Career 5: Giáo viên (có 3D THCS Bình Minh)
    $c5 = db()->query("SELECT * FROM careers WHERE id=5")->fetch();
    assert($c5['slug'] === 'giao-vien', 'Career 5 must be Teacher with 3D simulation');

    // Giả lập học sinh đã thử mô phỏng Career 1 với điểm cao và hào hứng
    $stmt = $pdo->prepare("INSERT INTO simulation_attempts(user_id, career_id, score, decisions, skill_scores, duration_seconds, reflection_feeling, reflection_note) VALUES(?, 1, 85, '[]', '{}', 300, 'excited', 'Rất thích phần giải quyết bug')");
    $stmt->execute([$userId]);
    $simAttempt = [
        'career_id' => 1,
        'score' => 85,
        'reflection_feeling' => 'excited',
        'reflection_note' => 'Rất thích phần giải quyết bug'
    ];

    $matrixSweet = career_matrix_status(80, $simAttempt);
    assert($matrixSweet['type'] === 'sweet_spot', 'High interest + high sim score + excited must be sweet_spot');

    // Giả lập học sinh chưa thử Career 5
    $matrixNoAttempt = career_matrix_status(80, null);
    assert($matrixNoAttempt['type'] === 'reality_check', 'High interest with no attempt must prompt reality_check');

    echo "✓ Test Case D: Đối chiếu ma trận giữa Sở thích tự khai và Thực tế mô phỏng hoạt động chính xác." . PHP_EOL;

    echo PHP_EOL . "TẤT CẢ 4 TEST CASE PHASE 5 ĐỀU ĐẠT 100%!" . PHP_EOL;

} finally {
    // Đảm bảo không ghi dữ liệu thử nghiệm vào database
    $pdo->rollBack();
    echo "Đã rollback toàn bộ dữ liệu kiểm thử thành công." . PHP_EOL;
}
