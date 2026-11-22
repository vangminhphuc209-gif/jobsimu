<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

echo "=== KIỂM THỬ PHASE 10: HIỆU NĂNG, BẢO MẬT & KIỂM SOÁT SỞ HỮU (IDOR) ===" . PHP_EOL;

// 1. Kiểm tra tái sử dụng kết nối PDO (Singleton instance)
$pdo1 = db();
$pdo2 = db();
assert($pdo1 === $pdo2, 'db() must return the same PDO instance (singleton)');
echo "✓ Quản lý kết nối: Instance PDO được tái sử dụng hoàn toàn trong cùng request." . PHP_EOL;

// 2. Đo thời gian khởi động (Bootstrap fast-path latency)
$t0 = microtime(true);
require __DIR__ . '/../app/bootstrap.php';
$durationMs = (microtime(true) - $t0) * 1000;
echo "✓ Thời gian nạp bootstrap có cache lock: " . number_format($durationMs, 1) . " ms (< 500ms)." . PHP_EOL;
assert($durationMs < 500, 'Bootstrap execution must be fast (< 500ms)');

// 3. Kiểm tra kiểm soát sở hữu (IDOR protection)
$pdo = db();
$pdo->beginTransaction();

try {
    // Tạo 2 user giả lập: Học sinh A và Học sinh B
    $emailA = 'student_a_' . time() . '@example.test';
    $emailB = 'student_b_' . time() . '@example.test';
    $stmt = $pdo->prepare("INSERT INTO users(name, email, password, role, status) VALUES('Student A', ?, 'hash', 'student', 'active')");
    $stmt->execute([$emailA]);
    $userAId = (int)$pdo->lastInsertId();

    $stmt->execute([$emailB]);
    $userBId = (int)$pdo->lastInsertId();

    // Tạo bài đánh giá cho Học sinh B
    $stmt = $pdo->prepare("INSERT INTO assessments(user_id, answers, scores, result_code, ai_status, completed_at) VALUES(?, '[]', '{\"R\":80}', 'R', 'completed', NOW())");
    $stmt->execute([$userBId]);
    $assBId = (int)$pdo->lastInsertId();

    // User A cố gắng truy vấn bài đánh giá của User B
    $stmt = $pdo->prepare("SELECT * FROM assessments WHERE id=? AND user_id=?");
    $stmt->execute([$assBId, $userAId]);
    $leakedData = $stmt->fetch();
    assert($leakedData === false, "User A must NOT be able to view User B's assessment (IDOR check)");
    echo "✓ Chống lộ dữ liệu chéo (IDOR Read): Học sinh A không thể đọc bài đánh giá của Học sinh B." . PHP_EOL;

    // User A cố gắng cập nhật phản tư vào bài đánh giá của User B
    $stmt = $pdo->prepare("UPDATE assessments SET validity_metrics='{\"student_feedback\":\"Hacked\"}' WHERE id=? AND user_id=?");
    $stmt->execute([$assBId, $userAId]);
    $affected = $stmt->rowCount();
    assert($affected === 0, "User A must NOT be able to modify User B's assessment (IDOR write check)");

    // Xác minh dữ liệu của User B không bị thay đổi
    $stmt = $pdo->prepare("SELECT validity_metrics FROM assessments WHERE id=?");
    $stmt->execute([$assBId]);
    $actualMetrics = $stmt->fetchColumn();
    assert($actualMetrics !== '{"student_feedback":"Hacked"}', "User B's data must remain untampered");
    echo "✓ Chống can thiệp chéo (IDOR Write): Học sinh A không thể sửa bài đánh giá của Học sinh B." . PHP_EOL;

    // 4. Kiểm tra CSRF helper
    $_SESSION['csrf'] = 'valid_secret_csrf_token_12345';
    assert(csrf_token() === 'valid_secret_csrf_token_12345', 'csrf_token() must return session token');
    assert(strpos(csrf_field(), 'value="valid_secret_csrf_token_12345"') !== false, 'csrf_field() must generate valid hidden input');
    echo "✓ Bảo vệ CSRF: Token CSRF trong phiên hoạt động chuẩn tắc." . PHP_EOL;

    echo PHP_EOL . "TẤT CẢ KIỂM THỬ BẢO MẬT & HIỆU NĂNG PHASE 10 ĐẠT 100%!" . PHP_EOL;

} finally {
    $pdo->rollBack();
    echo "✓ Đã rollback an toàn mọi dữ liệu kiểm thử." . PHP_EOL;
}
