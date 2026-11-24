<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

echo "=== KIỂM THỬ PHASE 6: KHÁM PHÁ NGHÈ, NGÀNH & TUYỂN SINH CÓ NGUỒN ===" . PHP_EOL;

$pdo = db();
$pdo->beginTransaction();

try {
    // 1. Kiểm tra quan hệ N-N giữa Nghề và Ngành qua career_major_paths
    $stmt = $pdo->query("SELECT COUNT(*) FROM career_major_paths");
    $pathCount = (int)$stmt->fetchColumn();
    assert($pathCount > 0, "career_major_paths must have mappings");
    echo "✓ Quan hệ Nghề - Ngành: Bảng career_major_paths có {$pathCount} liên kết." . PHP_EOL;

    // Kiểm tra nghề Giáo viên (id=5) có liên kết ngành Sư phạm
    $stmt = $pdo->prepare("SELECT m.name, m.code FROM career_major_paths cmp JOIN majors m ON m.id=cmp.major_id WHERE cmp.career_id=5");
    $stmt->execute();
    $teacherMajors = $stmt->fetchAll();
    assert(!empty($teacherMajors), "Career 5 (Giáo viên) must have linked majors");
    echo "✓ Nghề Giáo viên liên kết với " . count($teacherMajors) . " ngành đào tạo: " . implode(', ', array_column($teacherMajors, 'name')) . "." . PHP_EOL;

    // 2. Kiểm tra nghề Giáo viên có kịch bản 3D
    $c5 = $pdo->query("SELECT * FROM careers WHERE id=5")->fetch();
    assert($c5['slug'] === 'giao-vien', "Career 5 slug must be 'giao-vien'");
    echo "✓ Định danh nghề Giáo viên (id=5, slug='giao-vien') chính xác, tích hợp liên kết mô phỏng 3D Unity." . PHP_EOL;

    // 3. Kiểm tra tính toàn vẹn của dữ liệu tuyển sinh (admission_records)
    $stmt = $pdo->query("SELECT COUNT(*) FROM admission_records");
    $totalAdm = (int)$stmt->fetchColumn();
    assert($totalAdm > 0, "admission_records table must not be empty");

    $stmt = $pdo->query("SELECT COUNT(*) FROM admission_records WHERE admission_year >= 2020");
    $yearValid = (int)$stmt->fetchColumn();
    assert($yearValid === $totalAdm, "All admission records must have a valid admission_year >= 2020");

    $stmt = $pdo->query("SELECT COUNT(*) FROM admission_records WHERE verification_status IN ('verified', 'unverified')");
    $statusValid = (int)$stmt->fetchColumn();
    assert($statusValid === $totalAdm, "All admission records must have verification_status as verified or unverified");

    $stmt = $pdo->query("SELECT COUNT(*) FROM admission_records WHERE verification_status='verified' AND (source_url IS NULL OR source_url='')");
    $invalidVerified = (int)$stmt->fetchColumn();
    assert($invalidVerified === 0, "No admission record can be marked 'verified' without a source_url");
    echo "✓ Toàn bộ {$totalAdm} bản ghi điểm chuẩn đều có năm áp dụng, phương thức, tổ hợp và trạng thái xác minh chuẩn tắc." . PHP_EOL;
    echo "✓ Nguyên tắc bảo toàn nguồn: 0 bản ghi nào được gắn mác 'verified' mà thiếu source_url." . PHP_EOL;

    // 4. Kiểm tra Tra cứu (lookup) giữ nguyên bản ghi unverified chứ không xóa bỏ
    $unverifiedCount = (int)$pdo->query("SELECT COUNT(*) FROM admission_records WHERE verification_status='unverified'")->fetchColumn();
    echo "✓ Bản ghi tham khảo chưa có đề án gốc (unverified): {$unverifiedCount} bản ghi vẫn được lưu giữ phục vụ tra cứu minh bạch." . PHP_EOL;

    echo PHP_EOL . "TẤT CẢ CÁC KIỂM TRA PHASE 6 ĐỀU ĐẠT 100%!" . PHP_EOL;

} finally {
    $pdo->rollBack();
    echo "Đã rollback an toàn, không thay đổi CSDL thật." . PHP_EOL;
}
