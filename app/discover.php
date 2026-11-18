<?php
declare(strict_types=1);

/**
 * Step 1 / 4: Quick Self-Discovery (5 student-friendly questions).
 * Complements existing standardized assessments (Holland, Big Five, CAAS, MBTI, EQ)
 * without altering any standardized scale or algorithm.
 */

const DISCOVER_QUESTION_VERSION = 'discover_v1';

function discover_questions_catalog(): array
{
    return [
        [
            'id' => 'q1_subjects',
            'index' => 1,
            'type' => 'multi',
            'max' => 3,
            'text' => 'Bạn thích và học tốt những môn nào nhất?',
            'helper' => 'Chọn tối đa 3 môn bạn thấy hứng thú hoặc dễ tiếp thu. Môn bạn thích không nhất thiết phải là môn có điểm cao nhất.',
            'options' => [
                ['id' => 'toan', 'label' => 'Toán'],
                ['id' => 'ngu_van', 'label' => 'Ngữ văn'],
                ['id' => 'ngoai_ngu', 'label' => 'Ngoại ngữ'],
                ['id' => 'vat_ly', 'label' => 'Vật lý'],
                ['id' => 'hoa_hoc', 'label' => 'Hóa học'],
                ['id' => 'sinh_hoc', 'label' => 'Sinh học'],
                ['id' => 'lich_su', 'label' => 'Lịch sử'],
                ['id' => 'dia_ly', 'label' => 'Địa lý'],
                ['id' => 'gdktpl', 'label' => 'Giáo dục kinh tế và pháp luật'],
                ['id' => 'tin_hoc', 'label' => 'Tin học'],
                ['id' => 'cong_nghe', 'label' => 'Công nghệ'],
                ['id' => 'my_thuat', 'label' => 'Mỹ thuật'],
                ['id' => 'am_nhac', 'label' => 'Âm nhạc'],
            ],
            'exclusive_option' => ['id' => 'chua_ro', 'label' => 'Mình chưa rõ'],
            'allow_other' => false,
        ],
        [
            'id' => 'q2_activities',
            'index' => 2,
            'type' => 'multi',
            'max' => 3,
            'text' => 'Ngoài giờ học, bạn thường thích làm việc gì?',
            'helper' => 'Chọn tối đa 3 hoạt động gần với thói quen hoặc sở thích của bạn ngoài giờ lên lớp.',
            'options' => [
                ['id' => 'may_moc_cong_nghe', 'label' => 'Tìm hiểu máy móc, công nghệ'],
                ['id' => 'viet_ve_sang_tao', 'label' => 'Viết, vẽ hoặc sáng tạo nội dung'],
                ['id' => 'giup_do_huong_dan', 'label' => 'Giúp đỡ, hướng dẫn người khác'],
                ['id' => 'tinh_toan_phan_tich', 'label' => 'Tính toán, phân tích thông tin'],
                ['id' => 'to_chuc_nhom', 'label' => 'Tổ chức hoạt động, làm việc nhóm'],
                ['id' => 'thuc_hanh_san_pham', 'label' => 'Làm việc thực hành, tạo ra sản phẩm'],
            ],
            'exclusive_option' => ['id' => 'chua_ro', 'label' => 'Mình chưa rõ'],
            'allow_other' => true,
            'other_placeholder' => 'Ví dụ: chụp ảnh, nấu ăn, sửa đồ điện...',
        ],
        [
            'id' => 'q3_team_role',
            'index' => 3,
            'type' => 'single',
            'max' => 1,
            'text' => 'Khi làm một việc cùng nhóm, bạn thấy mình thoải mái nhất ở vai trò nào?',
            'helper' => 'Không có vai trò nào tốt hơn vai trò nào — hãy chọn cách bạn thấy tự nhiên nhất.',
            'options' => [
                ['id' => 'len_y_tuong', 'label' => 'Lên ý tưởng'],
                ['id' => 'tim_hieu_phan_tich', 'label' => 'Tìm hiểu và phân tích'],
                ['id' => 'trinh_bay_ket_noi', 'label' => 'Trình bày, kết nối mọi người'],
                ['id' => 'sap_xep_cong_viec', 'label' => 'Sắp xếp công việc'],
                ['id' => 'truc_tiep_thuc_hien', 'label' => 'Trực tiếp thực hiện'],
                ['id' => 'muon_thu_them', 'label' => 'Mình muốn thử thêm mới biết'],
            ],
            'exclusive_option' => null,
            'allow_other' => false,
        ],
        [
            'id' => 'q4_values',
            'index' => 4,
            'type' => 'multi',
            'max' => 3,
            'text' => 'Điều gì quan trọng với bạn khi nghĩ về việc học và công việc sau này?',
            'helper' => 'Chọn tối đa 3 yếu tố bạn ưu tiên hơn cả ở thời điểm hiện tại.',
            'options' => [
                ['id' => 'hung_thu', 'label' => 'Hứng thú'],
                ['id' => 'thu_nhap', 'label' => 'Thu nhập'],
                ['id' => 'on_dinh', 'label' => 'Ổn định'],
                ['id' => 'sang_tao', 'label' => 'Sáng tạo'],
                ['id' => 'giup_ich', 'label' => 'Giúp ích cho người khác'],
                ['id' => 'thoi_gian_ca_nhan', 'label' => 'Thời gian cá nhân'],
                ['id' => 'co_hoi_phat_trien', 'label' => 'Cơ hội phát triển'],
                ['id' => 'gan_gia_dinh', 'label' => 'Gần gia đình'],
            ],
            'exclusive_option' => null,
            'allow_other' => false,
        ],
        [
            'id' => 'q5_concern',
            'index' => 5,
            'type' => 'single',
            'max' => 1,
            'text' => 'Điều gì đang khiến bạn phân vân nhất?',
            'helper' => 'Chia sẻ băn khoăn lớn nhất giúp JobSimu gợi ý bước tìm hiểu tiếp theo phù hợp.',
            'options' => [
                ['id' => 'chua_biet_thich_gi', 'label' => 'Chưa biết mình thích gì'],
                ['id' => 'chua_ro_the_manh', 'label' => 'Chưa rõ thế mạnh'],
                ['id' => 'chua_hieu_nganh', 'label' => 'Chưa hiểu ngành học'],
                ['id' => 'lo_hoc_phi_noi_hoc', 'label' => 'Lo về học phí hoặc nơi học'],
                ['id' => 'chua_thong_nhat_gia_dinh', 'label' => 'Chưa thống nhất với gia đình'],
                ['id' => 'phan_van_vai_lua_chon', 'label' => 'Đang phân vân giữa vài lựa chọn'],
            ],
            'exclusive_option' => null,
            'allow_other' => true,
            'other_placeholder' => 'Ghi ngắn điều bạn đang băn khoăn...',
        ],
    ];
}

function discover_sanitize_answers(array $raw): array
{
    $catalog = discover_questions_catalog();
    $clean = [];

    foreach ($catalog as $q) {
        $qid = $q['id'];
        $entry = is_array($raw[$qid] ?? null) ? $raw[$qid] : [];
        $validIds = array_column($q['options'], 'id');
        $exclusiveId = $q['exclusive_option']['id'] ?? null;

        $selected = [];
        $rawSel = is_array($entry['selected'] ?? null) ? $entry['selected'] : [];
        foreach ($rawSel as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $val = trim((string)$item);
            if ($exclusiveId !== null && $val === $exclusiveId) {
                $selected = [$exclusiveId];
                break;
            }
            if ($q['allow_other'] && $val === 'khac') {
                if (!in_array('khac', $selected, true)) {
                    $selected[] = 'khac';
                }
                continue;
            }
            if (in_array($val, $validIds, true) && !in_array($val, $selected, true)) {
                $selected[] = $val;
            }
        }

        if ($exclusiveId !== null && in_array($exclusiveId, $selected, true)) {
            $selected = [$exclusiveId];
        } else {
            $selected = array_slice($selected, 0, (int)$q['max']);
        }

        $otherText = '';
        if ($q['allow_other'] && in_array('khac', $selected, true)) {
            $otherText = mb_substr(trim((string)($entry['other_text'] ?? '')), 0, 120);
        }

        $skipped = !empty($entry['skipped']) && empty($selected);

        $clean[$qid] = [
            'selected' => $selected,
            'other_text' => $otherText,
            'skipped' => $skipped,
        ];
    }

    return $clean;
}

/**
 * Deterministic, transparent exploration directions based on the 5 quick answers.
 * Never returns suitability percentages or definitive career verdicts.
 */
function discover_build_summary(array $answers): array
{
    $clean = discover_sanitize_answers($answers);
    $catalog = discover_questions_catalog();
    $labelMap = [];
    foreach ($catalog as $q) {
        foreach ($q['options'] as $opt) {
            $labelMap[$q['id']][$opt['id']] = $opt['label'];
        }
        if ($q['exclusive_option']) {
            $labelMap[$q['id']][$q['exclusive_option']['id']] = $q['exclusive_option']['label'];
        }
        if ($q['allow_other']) {
            $labelMap[$q['id']]['khac'] = 'Khác';
        }
    }

    $formatList = static function (string $qid) use ($clean, $labelMap): array {
        $entry = $clean[$qid] ?? ['selected' => [], 'other_text' => ''];
        $out = [];
        foreach ($entry['selected'] as $id) {
            if ($id === 'khac' && $entry['other_text'] !== '') {
                $out[] = 'Khác: ' . $entry['other_text'];
            } elseif (isset($labelMap[$qid][$id])) {
                $out[] = $labelMap[$qid][$id];
            }
        }
        return $out;
    };

    $subjects = $clean['q1_subjects']['selected'];
    $activities = $clean['q2_activities']['selected'];
    $role = $clean['q3_team_role']['selected'][0] ?? '';
    $values = $clean['q4_values']['selected'];
    $concern = $clean['q5_concern']['selected'][0] ?? '';

    // Deterministic rule-based exploration clusters (no randomness, no single percentage score)
    $clusters = [
        'tech_data' => [
            'title' => 'Công nghệ, Dữ liệu & Kỹ thuật số',
            'lookup_query' => 'Công nghệ thông tin',
            'lookup_tab' => 'majors',
            'sim_url' => 'index.php?page=simulations',
            'sim_label' => 'Trải nghiệm mô phỏng công việc CNTT',
            'reasons' => [],
            'score' => 0,
        ],
        'engineering' => [
            'title' => 'Kỹ thuật, Cơ khí & Xây dựng',
            'lookup_query' => 'Kỹ thuật',
            'lookup_tab' => 'majors',
            'sim_url' => 'index.php?page=simulations',
            'sim_label' => 'Xem phòng mô phỏng kỹ thuật',
            'reasons' => [],
            'score' => 0,
        ],
        'health_bio' => [
            'title' => 'Y tế, Sức khỏe & Khoa học sự sống',
            'lookup_query' => 'Y khoa',
            'lookup_tab' => 'majors',
            'sim_url' => 'index.php?page=simulations',
            'sim_label' => 'Trải nghiệm tình huống ngành y tế',
            'reasons' => [],
            'score' => 0,
        ],
        'edu_social' => [
            'title' => 'Giáo dục, Tâm lý & Cộng đồng',
            'lookup_query' => 'Sư phạm',
            'lookup_tab' => 'majors',
            'sim_url' => 'index.php?page=simulation-3d&career=giao-vien',
            'sim_label' => 'Thử trải nghiệm nghề Giáo viên 3D',
            'reasons' => [],
            'score' => 0,
        ],
        'business_law' => [
            'title' => 'Kinh doanh, Tài chính, Quản trị & Luật',
            'lookup_query' => 'Marketing',
            'lookup_tab' => 'majors',
            'sim_url' => 'index.php?page=simulations',
            'sim_label' => 'Thử mô phỏng Marketing / Tài chính / Luật',
            'reasons' => [],
            'score' => 0,
        ],
        'creative_media' => [
            'title' => 'Thiết kế, Kiến trúc, Truyền thông & Nghệ thuật',
            'lookup_query' => 'Kiến trúc',
            'lookup_tab' => 'majors',
            'sim_url' => 'index.php?page=simulations',
            'sim_label' => 'Thử mô phỏng Thiết kế / Báo chí',
            'reasons' => [],
            'score' => 0,
        ],
    ];

    $addSignal = static function (string $key, int $weight, string $reason) use (&$clusters): void {
        $clusters[$key]['score'] += $weight;
        if (!in_array($reason, $clusters[$key]['reasons'], true)) {
            $clusters[$key]['reasons'][] = $reason;
        }
    };

    foreach ($subjects as $sub) {
        if ($sub === 'tin_hoc') $addSignal('tech_data', 3, 'bạn quan tâm môn Tin học');
        if ($sub === 'toan') {
            $addSignal('tech_data', 2, 'bạn chọn môn Toán');
            $addSignal('business_law', 1, 'bạn chọn môn Toán');
        }
        if ($sub === 'vat_ly' || $sub === 'cong_nghe') {
            $addSignal('engineering', 3, 'bạn chọn môn ' . ($labelMap['q1_subjects'][$sub] ?? $sub));
            $addSignal('tech_data', 1, 'bạn chọn môn ' . ($labelMap['q1_subjects'][$sub] ?? $sub));
        }
        if ($sub === 'hoa_hoc' || $sub === 'sinh_hoc') {
            $addSignal('health_bio', 3, 'bạn chọn môn ' . ($labelMap['q1_subjects'][$sub] ?? $sub));
        }
        if ($sub === 'ngu_van' || $sub === 'lich_su' || $sub === 'dia_ly') {
            $addSignal('edu_social', 2, 'bạn chọn môn ' . ($labelMap['q1_subjects'][$sub] ?? $sub));
            $addSignal('creative_media', 1, 'bạn chọn môn ' . ($labelMap['q1_subjects'][$sub] ?? $sub));
        }
        if ($sub === 'gdktpl') $addSignal('business_law', 3, 'bạn chọn môn Giáo dục kinh tế và pháp luật');
        if ($sub === 'ngoai_ngu') {
            $addSignal('business_law', 2, 'bạn chọn môn Ngoại ngữ');
            $addSignal('edu_social', 1, 'bạn chọn môn Ngoại ngữ');
        }
        if ($sub === 'my_thuat' || $sub === 'am_nhac') {
            $addSignal('creative_media', 3, 'bạn chọn môn ' . ($labelMap['q1_subjects'][$sub] ?? $sub));
        }
    }

    foreach ($activities as $act) {
        if ($act === 'may_moc_cong_nghe') {
            $addSignal('tech_data', 3, 'bạn thích tìm hiểu máy móc, công nghệ');
            $addSignal('engineering', 2, 'bạn thích tìm hiểu máy móc, công nghệ');
        }
        if ($act === 'viet_ve_sang_tao') $addSignal('creative_media', 3, 'bạn thích viết, vẽ hoặc sáng tạo nội dung');
        if ($act === 'giup_do_huong_dan') {
            $addSignal('edu_social', 3, 'bạn thích giúp đỡ, hướng dẫn người khác');
            $addSignal('health_bio', 2, 'bạn thích giúp đỡ, hướng dẫn người khác');
        }
        if ($act === 'tinh_toan_phan_tich') {
            $addSignal('tech_data', 2, 'bạn thích tính toán, phân tích thông tin');
            $addSignal('business_law', 2, 'bạn thích tính toán, phân tích thông tin');
        }
        if ($act === 'to_chuc_nhom') $addSignal('business_law', 3, 'bạn thích tổ chức hoạt động, làm việc nhóm');
        if ($act === 'thuc_hanh_san_pham') {
            $addSignal('engineering', 3, 'bạn thích làm việc thực hành, tạo ra sản phẩm');
            $addSignal('creative_media', 1, 'bạn thích làm việc thực hành, tạo ra sản phẩm');
        }
    }

    if ($role === 'len_y_tuong') $addSignal('creative_media', 2, 'bạn thoải mái ở vai trò lên ý tưởng');
    if ($role === 'tim_hieu_phan_tich') $addSignal('tech_data', 2, 'bạn thoải mái ở vai trò tìm hiểu và phân tích');
    if ($role === 'trinh_bay_ket_noi') $addSignal('edu_social', 2, 'bạn thoải mái ở vai trò trình bày, kết nối mọi người');
    if ($role === 'sap_xep_cong_viec') $addSignal('business_law', 2, 'bạn thoải mái ở vai trò sắp xếp công việc');
    if ($role === 'truc_tiep_thuc_hien') $addSignal('engineering', 2, 'bạn thoải mái ở vai trò trực tiếp thực hiện');

    if (in_array('sang_tao', $values, true)) $addSignal('creative_media', 1, 'bạn coi trọng sự sáng tạo');
    if (in_array('giup_ich', $values, true)) $addSignal('edu_social', 1, 'bạn coi trọng việc giúp ích cho người khác');

    $matched = array_values(array_filter($clusters, static fn(array $c): bool => $c['score'] > 0));
    usort($matched, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    $topDirections = array_slice($matched, 0, 3);

    $needsBroadExploration = empty($topDirections) || in_array('chua_ro', $subjects, true) && in_array('chua_ro', $activities, true);
    if ($needsBroadExploration) {
        $topDirections = [
            [
                'title' => 'Bắt đầu mở rộng góc nhìn qua 3 nhóm ngành phổ biến',
                'lookup_query' => '',
                'lookup_tab' => 'majors',
                'sim_url' => 'index.php?page=simulations',
                'sim_label' => 'Thử 1 tình huống mô phỏng nghề ngắn',
                'reasons' => ['bạn đang ở giai đoạn chưa rõ sở thích — đọc mô tả ngành và thử trải nghiệm ngắn là cách tốt nhất để thu hẹp lựa chọn'],
                'score' => 0,
            ],
        ];
    }

    $nextTipByConcern = [
        'chua_biet_thich_gi' => 'Bạn có thể đọc nhanh 2–3 ngành khác nhau ở trang Tra cứu hoặc làm thêm khảo sát Holland để mở rộng gợi ý.',
        'chua_ro_the_manh' => 'Hãy kết hợp quan sát môn học, hoạt động ngoại khóa và bộ đánh giá Big Five / Gương soi SOKA 360°.',
        'chua_hieu_nganh' => 'Mở mục Tra cứu Ngành học để xem ngành đó học môn gì, làm công việc nào và có trường nào đào tạo.',
        'lo_hoc_phi_noi_hoc' => 'Dùng mục Tra cứu Trường đào tạo để lọc theo tỉnh/thành phố và xem khoảng học phí có nguồn công bố.',
        'chua_thong_nhat_gia_dinh' => 'Dùng công cụ "Hiểu mình cần gì" (So sánh) để đặt các lựa chọn cạnh nhau và trao đổi cụ thể với gia đình.',
        'phan_van_vai_lua_chon' => 'Chuyển thẳng sang bước "Hiểu mình cần gì" (So sánh) để cân nhắc từng tiêu chí cho các phương án bạn đang phân vân.',
    ];

    return [
        'version' => DISCOVER_QUESTION_VERSION,
        'subjects_labels' => $formatList('q1_subjects'),
        'activities_labels' => $formatList('q2_activities'),
        'role_labels' => $formatList('q3_team_role'),
        'values_labels' => $formatList('q4_values'),
        'concern_labels' => $formatList('q5_concern'),
        'directions' => $topDirections,
        'next_step_tip' => $nextTipByConcern[$concern] ?? 'Hãy chọn 2–3 ngành khiến bạn tò mò nhất để xem chi tiết ở trang Tra cứu.',
    ];
}

function discover_load_user_session(?array $me): ?array
{
    if (!$me || empty($me['id'])) {
        return null;
    }
    try {
        $stmt = db()->prepare('SELECT id, question_version, answers_json, summary_json, completed, updated_at FROM user_discovery_sessions WHERE user_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1');
        $stmt->execute([(int)$me['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'id' => (int)$row['id'],
            'question_version' => (string)$row['question_version'],
            'answers' => json_decode((string)$row['answers_json'], true) ?: [],
            'summary' => json_decode((string)$row['summary_json'], true) ?: null,
            'completed' => (int)$row['completed'] === 1,
            'updated_at' => (string)$row['updated_at'],
        ];
    } catch (Throwable $ignored) {
        return null;
    }
}

function discover_handle_api(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if (!is_post()) {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Yêu cầu phương thức POST.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $raw = file_get_contents('php://input');
    $payload = $raw ? (json_decode($raw, true) ?: []) : $_POST;
    $csrf = (string)($payload['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(419);
        echo json_encode(['ok' => false, 'error' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $answers = discover_sanitize_answers(is_array($payload['answers'] ?? null) ? $payload['answers'] : []);
    $completed = !empty($payload['completed']);
    $summary = discover_build_summary($answers);

    $me = user();
    $savedToServer = false;
    if ($me && !empty($me['id'])) {
        $existing = discover_load_user_session($me);
        $answersJson = json_encode($answers, JSON_UNESCAPED_UNICODE);
        $summaryJson = json_encode($summary, JSON_UNESCAPED_UNICODE);
        if ($existing) {
            $stmt = db()->prepare('UPDATE user_discovery_sessions SET question_version=?, answers_json=?, summary_json=?, completed=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=?');
            $stmt->execute([DISCOVER_QUESTION_VERSION, $answersJson, $summaryJson, $completed ? 1 : 0, $existing['id'], (int)$me['id']]);
        } else {
            $stmt = db()->prepare('INSERT INTO user_discovery_sessions(user_id, question_version, answers_json, summary_json, completed) VALUES(?, ?, ?, ?, ?)');
            $stmt->execute([(int)$me['id'], DISCOVER_QUESTION_VERSION, $answersJson, $summaryJson, $completed ? 1 : 0]);
        }
        $savedToServer = true;
    }

    echo json_encode([
        'ok' => true,
        'saved_to_server' => $savedToServer,
        'answers' => $answers,
        'summary' => $summary,
    ], JSON_UNESCAPED_UNICODE);
}

function discover_render_page(): void
{
    $me = user();
    $saved = discover_load_user_session($me);
    $questions = discover_questions_catalog();
    $storageKey = 'jobsimu_discover_v1_' . ($me ? ('u' . (int)$me['id']) : 'guest');

    $pageTitle = 'Khám phá bản thân · 5 câu hỏi nhanh';
    require __DIR__ . '/header.php';
    ?>
    <section class="page-head journey-head">
      <div class="container journey-narrow">
        <div class="journey-step-row">
          <span class="journey-step-pill">BƯỚC 1 / 4</span>
          <span class="journey-step-note">Khám phá nhanh · Không chấm điểm đúng sai</span>
        </div>
        <h1 class="page-title">Khám phá bản thân</h1>
        <p class="page-subtitle">Trả lời 5 câu hỏi gần gũi để gọi tên môn học yêu thích, điều bạn coi trọng và hướng ngành đáng tìm hiểu trước.</p>
        <p class="text-xs text-slate-500 mt-2">
          Đây là bước gợi mở bổ sung — không thay thế hay ghi đè kết quả các bài đánh giá chuẩn hóa (Holland, Big Five, CAAS, MBTI, EQ, SOKA 360°).
          <a href="<?=url('index.php?page=assessment-hub')?>" class="font-semibold text-brand-600 hover:underline">Mở toàn bộ bài đánh giá chuẩn hóa →</a>
        </p>
      </div>
    </section>

    <div class="container journey-narrow py-8">
      <div
        id="discoverApp"
        class="journey-card"
        data-api-url="<?=e(url('index.php?page=discover-api'))?>"
        data-storage-key="<?=e($storageKey)?>"
        data-logged-in="<?=$me ? '1' : '0'?>"
        data-csrf="<?=e(csrf_token())?>"
      >
        <!-- Quiz View -->
        <div id="discoverQuizView">
          <div class="journey-progress-head">
            <strong id="discoverStepLabel">Câu 1/5</strong>
            <span id="discoverSaveHint" class="journey-save-hint">
              <?=$me ? 'Tự động lưu vào tài khoản của bạn' : 'Lưu tạm trên trình duyệt thiết bị này'?>
            </span>
          </div>
          <div class="journey-progress-track" role="progressbar" aria-valuemin="1" aria-valuemax="5" aria-valuenow="1" id="discoverProgressBarWrap">
            <div id="discoverProgressFill" class="journey-progress-fill" style="width:20%"></div>
          </div>

          <div id="discoverQuestionBody" class="discover-q-body" aria-live="polite"></div>

          <div class="journey-actions-bar">
            <button type="button" id="discoverPrevBtn" class="btn btn-outline" disabled>← Quay lại</button>
            <div class="journey-actions-right">
              <button type="button" id="discoverSkipBtn" class="btn btn-ghost">Bỏ qua câu này</button>
              <button type="button" id="discoverNextBtn" class="btn btn-primary">Tiếp tục →</button>
            </div>
          </div>
        </div>

        <!-- Result View -->
        <div id="discoverResultView" hidden>
          <div class="discover-result-head">
            <span class="badge badge-purple">Gợi mở hướng khám phá</span>
            <h2 class="text-2xl font-black text-slate-900 mt-2">Bản tóm tắt sở thích &amp; ưu tiên của bạn</h2>
            <p class="text-sm text-slate-600 mt-1">Đây là gợi ý bước đầu dựa trên chia sẻ hiện tại của bạn — không phải kết luận cố định hay điểm đánh giá năng lực.</p>
          </div>

          <div id="discoverSummaryContent" class="discover-summary-content"></div>

          <div class="discover-result-actions">
            <a class="btn btn-primary btn-lg" id="discoverPrimaryCta" href="<?=url('index.php?page=lookup&tab=majors')?>">Tìm hiểu ngành học →</a>
            <button type="button" class="btn btn-outline" id="discoverEditBtn">Sửa câu trả lời</button>
            <a class="btn btn-ghost" href="<?=url('index.php?page=compare')?>">Sang bước So sánh</a>
          </div>
        </div>
      </div>

      <!-- Link to Standardized Assessments (never altered) -->
      <div class="journey-secondary-box mt-6">
        <div>
          <strong class="text-sm text-slate-900">Muốn đánh giá chuyên sâu hơn?</strong>
          <p class="text-xs text-slate-600 mt-1">Bạn vẫn có thể làm các công cụ chuẩn hóa như Holland RIASEC (30 câu), Big Five, CAAS, MBTI, EQ hoặc SOKA 360° bất cứ lúc nào.</p>
        </div>
        <a class="btn btn-outline btn-sm" href="<?=url('index.php?page=assessment-hub')?>">Mở bộ đánh giá chuẩn →</a>
      </div>
    </div>

    <script id="discoverBootstrapData" type="application/json"><?=json_encode([
        'questions' => $questions,
        'initial' => $saved,
        'urls' => [
            'lookup_majors' => url('index.php?page=lookup&tab=majors'),
            'compare' => url('index.php?page=compare'),
            'simulations' => url('index.php?page=simulations'),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)?></script>
    <script src="<?=url('assets/js/discover.js')?>"></script>
    <?php
    require __DIR__ . '/footer.php';
}
