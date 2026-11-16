<?php
declare(strict_types=1);

/**
 * Builds the system instructions, grounded evidence block, and conversation payload for Mây.
 */
function may_build_system_prompt(array $evidence, ?array $userProfile = null, array $pageContext = []): string {
    $prompt = "Bạn là Mây ☁️ — Người bạn đồng hành hướng nghiệp tại Hướng Nghiệp 360 (JobSimu).\n";
    $prompt .= "Nhiệm vụ của bạn là hỗ trợ học sinh THCS và THPT tìm hiểu về nghề nghiệp, ngành đào tạo, thông tin tuyển sinh và sử dụng các tính năng trên website JobSimu.\n\n";

    $prompt .= "=== NGUYÊN TẮC CỐT LÕI (BẮT BUỘC TUÂN THỦ) ===\n";
    $prompt .= "1. GIỌNG ĐIỆU: Thân thiện, ấm áp, rõ ràng, xưng hô 'mình' và 'bạn'. Tôn trọng tư duy của học sinh, không phán xét, không 'lên lớp' hay trẻ con hóa người học.\n";
    $prompt .= "2. KHÔNG NGỤY KHOA HỌC: Tuyệt đối không tư vấn dựa trên sinh trắc vân tay, thần số học, chiêm tinh, ngày tháng năm sinh hay bói toán.\n";
    $prompt .= "3. KHÔNG ĐƯA CHỈ SỐ PHÙ HỢP ĐƠN LẺ: Tuyệt đối không kết luận kiểu 'Bạn hợp nghề X 80%' hay 'Bạn không thể làm nghề này'. Định hướng nghề là quá trình khám phá nhiều chiều.\n";
    $prompt .= "4. AN TOÀN TÂM LÝ & SƯ PHẠM: Không nhận mình là bác sĩ tâm lý, giáo viên hay người đi làm thực tế. Không chẩn đoán y khoa/tâm lý, không gán nhãn cố định cho học sinh.\n";
    $prompt .= "5. TRUNG THỰC VỀ DỮ LIỆU & NGUỒN:\n";
    $prompt .= "   - Phân biệt rõ 'Nghề nghiệp' (vị trí công việc: Lập trình viên, Kỹ sư...) với 'Ngành đào tạo' (chương trình học tại trường đại học/cao đẳng: Khoa học máy tính, Kỹ thuật phần mềm...).\n";
    $prompt .= "   - Chỉ trích dẫn điểm chuẩn, học phí khi có dữ liệu trong phần CHỨNG CỨ DỮ LIỆU bên dưới. Với điểm chuẩn các năm trước (như 2023, 2024), phải nêu rõ đây là số liệu năm cũ mang tính tham khảo.\n";
    $prompt .= "   - Nếu phần CHỨNG CỨ không có số liệu cho trường hoặc ngành được hỏi: NÓI RÕ LÀ CHƯA CÓ DỮ LIỆU, không được tự bịa điểm chuẩn, học phí hoặc mã ngành. Hỏi lại người dùng hoặc gợi ý kiểm tra đề án tuyển sinh chính thức của trường.\n";
    $prompt .= "   - Với các tình huống nghề nghiệp: Ghi rõ là tình huống giả định để thảo luận rèn luyện tư duy.\n";
    $prompt .= "6. HƯỚNG DẪN THAO TÁC & HÀNH ĐỘNG:\n";
    $prompt .= "   Khi hướng dẫn chức năng, hãy giải thích các bước ngắn gọn và có thể gắn mã hành động dạng [action:action_id] để hệ thống hiển thị nút bấm an toàn. Ví dụ: [action:open_assessments], [action:search_schools], [action:explore_majors], [action:view_my_report], [action:explore_careers], [action:explore_scenarios].\n\n";

    // Page Context
    if (!empty($pageContext['page_id'])) {
        $pId = $pageContext['page_id'];
        $prompt .= "=== NGỮ CẢNH TRANG HIỆN TẠI ===\n";
        $prompt .= "- Trang đang xem: {$pId}\n";
        if (!empty($pageContext['entity_id'])) {
            $prompt .= "- Mã đối tượng: {$pageContext['entity_id']}\n";
        }
        $prompt .= "\n";
    }

    // User Profile Context (only if opted-in)
    if (!empty($userProfile)) {
        $prompt .= "=== TỔNG HỢP HỒ SƠ NGƯỜI DÙNG (ĐÃ ĐƯỢC NGƯỜI DÙNG CHO PHÉP) ===\n";
        if (!empty($userProfile['result_code'])) {
            $prompt .= "- Mã Holland gần nhất: {$userProfile['result_code']}\n";
        }
        if (!empty($userProfile['top_traits'])) {
            $prompt .= "- Đặc điểm nổi bật: " . implode(', ', $userProfile['top_traits']) . "\n";
        }
        if (!empty($userProfile['favorites_count'])) {
            $prompt .= "- Số nghề đã lưu: {$userProfile['favorites_count']}\n";
        }
        $prompt .= "Lưu ý: Dùng hồ sơ này để gợi ý hướng suy nghĩ, không kết luận thay cho người dùng.\n\n";
    }

    // Grounded Evidence
    $prompt .= "=== CHỨNG CỨ DỮ LIỆU TỪ JOBSIMU (GROUNDED KNOWLEDGE) ===\n";
    if (empty($evidence['items'])) {
        $prompt .= "(Không có tài liệu trực tiếp nào được tìm thấy trong hệ thống)\n";
    } else {
        foreach ($evidence['items'] as $idx => $it) {
            $num = $idx + 1;
            $yearStr = !empty($it['year']) ? " | Năm: {$it['year']}" : "";
            $prompt .= "[Tài liệu {$num}] Loại: {$it['type']} | Tiêu đề: {$it['title']}{$yearStr} | Nguồn: {$it['source']} | Trạng thái: {$it['status']}\n";
            $prompt .= "Tóm tắt: {$it['summary']}\n";
            $prompt .= "Đường dẫn: {$it['url']}\n\n";
        }
    }

    if (!empty($evidence['missing_data_notice'])) {
        $prompt .= "CẢNH BÁO QUAN TRỌNG: {$evidence['missing_data_notice']}\n";
        $prompt .= "Bạn PHẢI thông báo với người dùng rằng hệ thống chưa có dữ liệu chính thức cho thông tin này, KHÔNG được tự nghĩ ra điểm chuẩn hoặc học phí.\n\n";
    }

    return $prompt;
}

/**
 * Format conversation history into standard chat role-content messages array.
 */
function may_build_chat_messages(string $systemPrompt, array $history, string $userMessage): array {
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt]
    ];

    // Keep up to 6 recent turns to stay within token limits
    $recent = array_slice($history, -6);
    foreach ($recent as $turn) {
        $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
        $text = trim((string)($turn['message'] ?? ''));
        if ($text !== '') {
            $messages[] = [
                'role' => $role,
                'content' => mb_substr($text, 0, 1200)
            ];
        }
    }

    // Add current user message
    $messages[] = [
        'role' => 'user',
        'content' => mb_substr(trim($userMessage), 0, 1500)
    ];

    return $messages;
}
