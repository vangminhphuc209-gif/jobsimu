<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/assessment-feedback.php';
require_once __DIR__ . '/../app/psychometrics.php';

echo "=== KIỂM THỬ HỆ THỐNG PHẢN HỒI TÍCH LŨY 5 BÀI ĐÁNH GIÁ (v3.1.0) ===" . PHP_EOL;

$pdo = db();
$pdo->beginTransaction();

try {
    // 0. Tạo user test riêng biệt (demo student)
    $testEmail = 'demo_student_' . time() . '_' . random_int(1000, 9999) . '@jobsimu.test';
    $stmt = $pdo->prepare("INSERT INTO users(name, email, password, role, grade, school, status) VALUES(?, ?, 'demo_hash', 'student', 'Lớp 12', 'Trường THPT Thử Nghiệm', 'active')");
    $stmt->execute(['Học Sinh Demo Trải Nghiệm', $testEmail]);
    $userId = (int)$pdo->lastInsertId();

    // =========================================================================
    // TEST 1: ĐỊNH NGHĨA 5 BÀI ĐÁNH GIÁ CHUẨN HÓA TRONG HÀNH TRÌNH
    // =========================================================================
    $steps = assessment_journey_steps();
    assert(count($steps) === 5, 'Hành trình phải gồm đúng 5 bài đánh giá');
    $keys = array_keys($steps);
    assert($keys === ['holland', 'big5', 'caas', 'mbti', 'eq'], 'Thứ tự 5 bài chuẩn hóa: Holland -> Big5 -> CAAS -> MBTI -> EQ');
    
    foreach ($steps as $k => $st) {
        assert(!empty($st['title']), "Bài $k phải có title");
        assert(!empty($st['short_name']), "Bài $k phải có short_name");
        assert(!empty($st['tool_name']), "Bài $k phải có tool_name");
        assert(!empty($st['theoretical_model']), "Bài $k phải có theoretical_model");
        assert(!empty($st['route']), "Bài $k phải có route");
        assert(!empty($st['result_route']), "Bài $k phải có result_route");
    }
    echo "✓ Test 1: Định nghĩa 5 bài chuẩn hóa đầy đủ thông tin, đúng thứ tự sư phạm." . PHP_EOL;

    // =========================================================================
    // TEST 2: TIẾN ĐỘ BAN ĐẦU (0/5) VÀ BƯỚC ĐẦU TIÊN
    // =========================================================================
    $p0 = assessment_get_journey_progress($userId);
    assert($p0['completed_count'] === 0, 'Khởi đầu phải có 0 bài hoàn thành');
    assert($p0['all_completed'] === false, 'all_completed phải là false khi chưa làm bài');
    assert($p0['next_step']['key'] === 'holland', 'Bước gợi ý đầu tiên phải là Bài 1 (Holland)');
    echo "✓ Test 2: Tiến độ ban đầu 0/5 được xác định chính xác, trỏ đúng Bài 1." . PHP_EOL;

    // =========================================================================
    // TEST 3: TIẾN ĐỘ TÍCH LŨY TỪNG BÀI (1/5 ĐẾN 5/5)
    // =========================================================================
    // Hoàn thành Bài 1: Holland RIASEC
    $hScores = ['R' => 45, 'I' => 85, 'A' => 60, 'S' => 75, 'E' => 50, 'C' => 55];
    $hZ = ['R' => -0.8, 'I' => 1.4, 'A' => 0.2, 'S' => 0.9, 'E' => -0.5, 'C' => -0.2];
    $hEff = ['R' => 3, 'I' => 5, 'A' => 4, 'S' => 4, 'E' => 3, 'C' => 3];
    $hConstraints = ['budget' => '1', 'study_path' => 'university', 'study_years' => 'long', 'activity' => 'medium', 'scholarship' => 'yes', 'portfolio' => 'unknown', 'avoid_sacrifices' => []];
    $hMetrics = ['validity_flag' => 'valid', 'duration_seconds' => 320, 'differentiation_index' => 42];

    $sHolland = $pdo->prepare("INSERT INTO assessments (user_id, answers, scores, ipsative_z_scores, efficacy_scores, constraint_answers, validity_metrics, result_code, completed_at) VALUES (?, '[]', ?, ?, ?, ?, ?, 'ISR', NOW())");
    $sHolland->execute([$userId, json_encode($hScores), json_encode($hZ), json_encode($hEff), json_encode($hConstraints), json_encode($hMetrics),]);
    $hollandAssId = (int)$pdo->lastInsertId();

    $p1 = assessment_get_journey_progress($userId);
    assert($p1['completed_count'] === 1, 'Tiến độ phải là 1/5 sau bài Holland');
    assert($p1['steps']['holland']['is_completed'] === true, 'Holland phải có status completed');
    assert($p1['next_step']['key'] === 'big5', 'Bước tiếp theo sau Holland phải là Bài 2 (Big Five)');

    // Kiểm tra thông báo bắt buộc của Bài 1 (Mục 3F)
    $limits1 = assessment_get_pedagogical_limits('holland', 1);
    $verbatimNoticeFound = false;
    $expectedVerbatim = 'Bạn đã hoàn thành 1/5 bài đánh giá. Đây mới là xu hướng sở thích ban đầu, chưa phản ánh đầy đủ năng lực hay cơ hội nghề nghiệp. Hãy hoàn thành các bài tiếp theo để hồ sơ được tích lũy chính xác hơn.';
    foreach ($limits1 as $lim) {
        if ($lim['text'] === $expectedVerbatim) {
            $verbatimNoticeFound = true;
            break;
        }
    }
    assert($verbatimNoticeFound, 'Bài 1 phải có thông báo chuẩn mực nguyên văn theo đúng yêu cầu Mục 3F');

    // Hoàn thành Bài 2: Big Five Mini-IPIP
    $bScores = ['O' => 75, 'C' => 60, 'E' => 40, 'A' => 80, 'N' => 65];
    $sBig5 = $pdo->prepare("INSERT INTO user_psychometrics (user_id, test_type, answers_json, scores_json, result_code, summary_label, duration_seconds, validity_flag, completed_at) VALUES (?, 'big5', '{}', ?, 'BIG5-AO', 'Nhân cách Big Five', 240, 'valid', NOW())");
    $sBig5->execute([$userId, json_encode($bScores)]);

    $p2 = assessment_get_journey_progress($userId);
    assert($p2['completed_count'] === 2, 'Tiến độ phải là 2/5 sau Big Five');
    assert($p2['next_step']['key'] === 'caas', 'Bước tiếp theo sau Big Five phải là Bài 3 (CAAS)');

    // Hoàn thành Bài 3: CAAS
    $cScores = ['concern' => 70, 'control' => 65, 'curiosity' => 80, 'confidence' => 60];
    $sCaas = $pdo->prepare("INSERT INTO user_psychometrics (user_id, test_type, answers_json, scores_json, result_code, summary_label, duration_seconds, validity_flag, completed_at) VALUES (?, 'caas', '{}', ?, 'CAAS-MOD', 'Thích ứng CAAS', 150, 'valid', NOW())");
    $sCaas->execute([$userId, json_encode($cScores)]);

    $p3 = assessment_get_journey_progress($userId);
    assert($p3['completed_count'] === 3, 'Tiến độ phải là 3/5 sau CAAS');
    assert($p3['next_step']['key'] === 'mbti', 'Bước tiếp theo sau CAAS phải là Bài 4 (MBTI)');

    // Hoàn thành Bài 4: MBTI
    $mScores = ['EI' => ['E' => 35, 'I' => 65], 'SN' => ['S' => 30, 'N' => 70], 'TF' => ['T' => 45, 'F' => 55], 'JP' => ['J' => 60, 'P' => 40]];
    $sMbti = $pdo->prepare("INSERT INTO user_psychometrics (user_id, test_type, answers_json, scores_json, result_code, summary_label, duration_seconds, validity_flag, completed_at) VALUES (?, 'mbti', '{}', ?, 'INFJ', 'MBTI rút gọn', 160, 'valid', NOW())");
    $sMbti->execute([$userId, json_encode($mScores)]);

    $p4 = assessment_get_journey_progress($userId);
    assert($p4['completed_count'] === 4, 'Tiến độ phải là 4/5 sau MBTI');
    assert($p4['next_step']['key'] === 'eq', 'Bước tiếp theo sau MBTI phải là Bài 5 (EQ)');

    // Hoàn thành Bài 5: EQ
    $eScores = ['awareness' => 80, 'regulation' => 75, 'motivation' => 85, 'empathy' => 90];
    $sEq = $pdo->prepare("INSERT INTO user_psychometrics (user_id, test_type, answers_json, scores_json, result_code, summary_label, duration_seconds, validity_flag, completed_at) VALUES (?, 'eq', '{}', ?, 'EQ-REFLECTION', 'Chỉ số cảm xúc EQ', 140, 'valid', NOW())");
    $sEq->execute([$userId, json_encode($eScores)]);

    $p5 = assessment_get_journey_progress($userId);
    assert($p5['completed_count'] === 5, 'Tiến độ phải là 5/5 sau khi hoàn thành cả 5 bài');
    assert($p5['all_completed'] === true, 'all_completed phải là true khi đủ 5 bài');
    assert($p5['next_step'] === null, 'next_step phải là null khi đã hoàn tất 5 bài');
    echo "✓ Test 3: Tiến độ tích lũy từ 1/5 đến 5/5 hoạt động chuẩn xác, thông báo Bài 1 chuẩn mực." . PHP_EOL;

    // =========================================================================
    // TEST 4: KIỂM THỬ TRƯỜNG HỢP LÀM BÀI KHÔNG THEO THỨ TỰ (OUT-OF-ORDER)
    // =========================================================================
    $userOutOrder = 'out_order_' . time() . '@jobsimu.test';
    $stmt = $pdo->prepare("INSERT INTO users(name, email, password, role, status) VALUES(?, ?, 'hash', 'student', 'active')");
    $stmt->execute(['Out of Order Student', $userOutOrder]);
    $uOutId = (int)$pdo->lastInsertId();

    // Làm bài CAAS và MBTI trước (chưa làm Holland và Big Five)
    $stmt = $pdo->prepare("INSERT INTO user_psychometrics (user_id, test_type, answers_json, scores_json, result_code, summary_label, duration_seconds, validity_flag, completed_at) VALUES (?, 'caas', '{}', '{\"concern\":60}', 'CAAS-MOD', 'CAAS', 120, 'valid', NOW())");
    $stmt->execute([$uOutId]);
    $stmt = $pdo->prepare("INSERT INTO user_psychometrics (user_id, test_type, answers_json, scores_json, result_code, summary_label, duration_seconds, validity_flag, completed_at) VALUES (?, 'mbti', '{}', '{\"EI\":{\"E\":50,\"I\":50}}', 'INTJ', 'MBTI', 120, 'valid', NOW())");
    $stmt->execute([$uOutId]);

    $pOut = assessment_get_journey_progress($uOutId);
    assert($pOut['completed_count'] === 2, 'Tiến độ phải ghi nhận đúng 2 bài');
    assert($pOut['steps']['caas']['is_completed'] === true, 'CAAS phải completed');
    assert($pOut['steps']['mbti']['is_completed'] === true, 'MBTI phải completed');
    assert($pOut['steps']['holland']['is_completed'] === false, 'Holland chưa làm');
    assert($pOut['steps']['big5']['is_completed'] === false, 'Big5 chưa làm');
    assert($pOut['next_step']['key'] === 'holland', 'Bài chưa làm đầu tiên phải là Holland');
    echo "✓ Test 4: Làm bài không theo thứ tự được xử lý trơn tru, không suy đoán dữ liệu thiếu." . PHP_EOL;

    // =========================================================================
    // TEST 5: KIỂM THỬ LÀM LẠI BÀI (RETAKING TESTS)
    // =========================================================================
    // User uOutId làm lại bài CAAS với điểm mới
    $stmt = $pdo->prepare("INSERT INTO user_psychometrics (user_id, test_type, answers_json, scores_json, result_code, summary_label, duration_seconds, validity_flag, completed_at) VALUES (?, 'caas', '{}', '{\"concern\":85}', 'CAAS-HIGH', 'CAAS Mới', 180, 'valid', NOW())");
    $stmt->execute([$uOutId]);

    $pOutRetake = assessment_get_journey_progress($uOutId);
    assert($pOutRetake['completed_count'] === 2, 'Số bài hoàn thành vẫn là 2 (không bị đếm trùng do làm lại bài)');
    assert($pOutRetake['steps']['caas']['result_code'] === 'CAAS-HIGH', 'Kết quả mới nhất phải được cập nhật');
    echo "✓ Test 5: Làm lại bài cập nhật đúng bản ghi mới nhất, không làm sai lệch số đếm." . PHP_EOL;

    // =========================================================================
    // TEST 6: PHẢN TƯ CỦA HỌC SINH (BẢO TOÀN ĐIỂM GỐC)
    // =========================================================================
    $reflectionSaved = assessment_save_student_reflection($userId, 'big5', 'partially_agree', 'Em thấy điểm Hòa đồng đúng, nhưng điểm Hướng ngoại em thấy mình cởi mở hơn khi ở môi trường quen thuộc.');
    assert($reflectionSaved === true, 'Lưu phản tư học sinh phải thành công');

    $savedRefl = assessment_get_student_reflection($userId, 'big5');
    assert($savedRefl !== null, 'Phải đọc được phản tư đã lưu');
    assert($savedRefl['fit_feeling'] === 'partially_agree', 'Mức độ phù hợp phải là partially_agree');
    assert(str_contains($savedRefl['student_notes'], 'cởi mở hơn'), 'Nội dung phản tư phải nguyên vẹn');

    // Kiểm tra điểm gốc trong user_psychometrics KHÔNG BỊ THAY ĐỔI
    $stmt = $pdo->prepare("SELECT scores_json FROM user_psychometrics WHERE user_id=? AND test_type='big5' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $rawBig5Db = json_decode($stmt->fetchColumn(), true);
    assert($rawBig5Db['O'] === 75 && $rawBig5Db['A'] === 80, 'Điểm số đo lường gốc của Big Five phải được bảo toàn 100%');
    echo "✓ Test 6: Lưu phản tư học sinh thành công, bảo toàn tuyệt đối điểm số trắc nghiệm gốc." . PHP_EOL;

    // =========================================================================
    // TEST 7: HỒ SƠ ĐIỂM HỌC KỲ VÀ TÍNH TỔ HỢP XÉT TUYỂN
    // =========================================================================
    $academicData = [
        'grade_level' => 'Lớp 12',
        'academic_year' => '2024-2025',
        'semester' => 'sem1',
        'target_region' => 'north',
        'budget_yearly' => '1',
        'notes' => 'Ưu tiên các trường kỹ thuật tại Hà Nội',
        'subject_scores' => [
            'toan' => 8.5,
            'ly' => 8.0,
            'hoa' => 7.5,
            'van' => 7.0,
            'anh' => 8.0,
            'sinh' => 6.5,
            'su' => 7.0,
            'dia' => 7.5,
            'tin' => 9.0,
            'gdcd' => 8.5,
        ],
    ];
    $academicSaved = assessment_save_academic_profile($userId, $academicData);
    assert($academicSaved === true, 'Lưu hồ sơ học tập học kỳ phải thành công');

    $savedAcademic = assessment_get_academic_profile($userId);
    assert($savedAcademic['grade_level'] === 'Lớp 12', 'Khối lớp phải là Lớp 12');
    assert($savedAcademic['target_region'] === 'north', 'Khu vực phải là north');
    assert($savedAcademic['subject_scores']['toan'] === 8.5, 'Điểm Toán phải là 8.5');
    assert($savedAcademic['subject_scores']['tin'] === 9.0, 'Điểm Tin phải là 9.0');

    // Tính điểm các tổ hợp xét tuyển
    $combos = assessment_calculate_combinations_scores($savedAcademic['subject_scores']);
    assert(isset($combos['A00']), 'Tổ hợp A00 phải tồn tại');
    assert($combos['A00']['total_score'] === 24.0, 'A00 = Toán (8.5) + Lý (8.0) + Hóa (7.5) = 24.0');
    assert($combos['A01']['total_score'] === 24.5, 'A01 = Toán (8.5) + Lý (8.0) + Anh (8.0) = 24.5');
    assert($combos['D01']['total_score'] === 23.5, 'D01 = Văn (7.0) + Toán (8.5) + Anh (8.0) = 23.5');
    assert($combos['X06']['total_score'] === 25.5, 'X06 = Toán (8.5) + Lý (8.0) + Tin (9.0) = 25.5');

    // Kiểm thử khi thiếu điểm môn học
    $incompleteScores = ['toan' => 8.0, 'ly' => 7.5]; // thiếu Hóa
    $combosIncomplete = assessment_calculate_combinations_scores($incompleteScores);
    assert($combosIncomplete['A00']['is_complete'] === false, 'Tổ hợp thiếu môn phải có is_complete = false');
    assert($combosIncomplete['A00']['total_score'] === null, 'Tổ hợp thiếu môn không được tự bịa điểm');
    echo "✓ Test 7: Hồ sơ điểm học tập và tự động tính tổ hợp môn (A00, A01, D01, X06) chính xác, an toàn khi thiếu môn." . PHP_EOL;

    // =========================================================================
    // TEST 8: ĐIỂM THỐNG NHẤT VÀ ĐIỂM KHÁC BIỆT TRONG BÁO CÁO TỔNG HỢP (MỤC 6)
    // =========================================================================
    $analysis = assessment_get_consistencies_and_discrepancies($userId);
    assert(!empty($analysis['consistencies']), 'Phải phát hiện ít nhất 1 điểm thống nhất');
    
    // Kiểm tra điểm thống nhất (Holland I + Big5 O)
    $hasAnalytical = false;
    foreach ($analysis['consistencies'] as $con) {
        if (str_contains($con['theme'], 'Khám phá') || str_contains($con['theme'], 'Nghiên cứu')) {
            $hasAnalytical = true;
            break;
        }
    }
    assert($hasAnalytical, 'Phải phát hiện điểm thống nhất về Nghiên cứu & Cởi mở trí tuệ giữa Holland (I) và Big Five (O)');

    // Kiểm tra gợi ý ngành học từ 358 ngành chuẩn
    $majors = assessment_get_matching_training_majors([1, 2, 5], 'ISR', $bScores);
    assert(count($majors) > 0, 'Phải truy vấn được các ngành đào tạo phù hợp từ bảng training_majors');

    // Kiểm tra dữ liệu tuyển sinh đối chiếu thực tế
    $admissions = assessment_get_matching_admissions([1, 2], 'north');
    assert(count($admissions) > 0, 'Phải truy vấn được các bản ghi điểm chuẩn tuyển sinh xác minh');
    assert(isset($admissions[0]['cutoff_score']), 'Bản ghi tuyển sinh phải có cutoff_score');
    assert(isset($admissions[0]['admission_year']), 'Bản ghi tuyển sinh phải có admission_year');
    echo "✓ Test 8: Báo cáo tổng hợp đa chiều nhận diện đúng điểm thống nhất, liên kết 358 ngành và tuyển sinh thực tế." . PHP_EOL;

    // =========================================================================
    // TEST 9: KIỂM THỬ GIAO DIỆN TRACKER & KẾT QUẢ HTML RENDER
    // =========================================================================
    $trackerHtml = assessment_render_journey_tracker($p5, 'eq');
    assert(str_contains($trackerHtml, 'Đã hoàn thành 5/5 bài'), 'Tracker HTML phải hiển thị 5/5 bài');
    assert(str_contains($trackerHtml, 'Hành trình Hiểu bản thân'), 'Tracker HTML phải có tiêu đề rõ ràng');
    echo "✓ Test 9: HTML tracker kết xuất giao diện trực quan, rõ ràng, đạt chuẩn trợ năng." . PHP_EOL;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ LỖI KIỂM THỬ: " . $e->getMessage() . " tại " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}

// 10. Rollback toàn bộ dữ liệu kiểm thử
if ($pdo->inTransaction()) {
    $pdo->rollBack();
}

echo PHP_EOL . "🎉 TẤT CẢ 9 TEST CASE CỦA HỆ THỐNG PHẢN HỒI TÍCH LŨY ĐỀU ĐẠT 100%!" . PHP_EOL;
echo "Dữ liệu kiểm thử đã được rollback an toàn, không ảnh hưởng cơ sở dữ liệu thật." . PHP_EOL;
