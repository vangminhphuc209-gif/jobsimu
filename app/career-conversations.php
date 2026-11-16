<?php
declare(strict_types=1);

function career_conversation_catalog(): array {
    static $catalog = null;
    if ($catalog === null) {
        $rows = json_decode(file_get_contents(__DIR__.'/../data/career-conversations/scenarios.json'), true, 512, JSON_THROW_ON_ERROR);
        $catalog = array_column($rows, null, 'id');
    }
    return $catalog;
}

function career_conversation_select($id): ?array {
    if ($id === '' || $id === null) return null;
    if (!is_string($id) || !isset(career_conversation_catalog()[$id])) {
        throw new InvalidArgumentException('Tình huống không tồn tại.');
    }
    return career_conversation_catalog()[$id];
}

function career_conversation_prompts(array $scenario): array {
    return [$scenario['starter'], 'Nếu chọn một cách khác thì hệ quả có thể là gì?',
        'Người mới vào nghề cần hỏi ai để xử lý tình huống này?',
        'Tôi muốn tự viết cách xử lý và cùng phân tích.',
        'Qua tình huống này, tôi muốn tìm hiểu thêm điều gì về nghề?'];
}

/** Deterministic editorial material, explicitly not a generated answer. */
function career_conversation_fallback(array $scenario): string {
    return "Bản đọc thử biên soạn sẵn — AI hiện không khả dụng; phần này chưa phân tích câu trả lời riêng của bạn.\n\n"
        .$scenario['scene']."\n\nHai hướng để cùng cân nhắc:\n• ".implode("\n• ", $scenario['discussion'])
        ."\n\nCâu hỏi tiếp: ".$scenario['follow_up']
        ."\n\nĐây là tình huống giả định, chưa được người trong nghề thẩm định; không chấm điểm phù hợp nghề.\n"
        .'Tham khảo nhiệm vụ: '.$scenario['source']['url'].' — '.$scenario['source']['scope'];
}

/** Separate contexts without trusting a client-supplied career or transcript. */
function career_conversation_history(int $userId, ?array $scenario, int $throughId = PHP_INT_MAX): array {
    $s = db()->prepare('SELECT role,message,model,created_at,context_snapshot FROM ai_chat_messages WHERE user_id=? AND id<=? ORDER BY id DESC LIMIT 200');
    $s->execute([$userId, $throughId]);
    $rows = [];
    foreach ($s->fetchAll() as $row) {
        $context = json_decode($row['context_snapshot'] ?? '{}', true) ?: [];
        if (($context['career_conversation']['id'] ?? null) !== ($scenario['id'] ?? null)) continue;
        $rows[] = $row;
        if (count($rows) >= 20) break;
    }
    return array_reverse($rows);
}

/** Routes are maintained by the application, never invented by the model. */
function career_website_guides(): array {
    return [
        ['id'=>'report', 'title'=>'Xem lại kết quả', 'keywords'=>['kết quả','báo cáo'], 'path'=>'index.php?page=assessment-report', 'steps'=>'Mở Báo cáo đánh giá để xem các bài đã hoàn thành. Nếu thiếu bài, quay về Bộ đánh giá để tiếp tục.'],
        ['id'=>'assessments', 'title'=>'Khám phá bản thân', 'keywords'=>['bài test','bài đánh giá','holland','làm test'], 'path'=>'index.php?page=assessment-hub', 'steps'=>'Mở Bộ đánh giá, chọn bài muốn làm. Sau khi hoàn thành, xem báo cáo và ghi điều bạn muốn tìm hiểu thêm.'],
        ['id'=>'majors', 'title'=>'Tìm ngành đào tạo', 'keywords'=>['tìm ngành','tra ngành','ngành đào tạo'], 'path'=>'index.php?page=majors', 'steps'=>'Mở Danh mục ngành, tìm theo tên hoặc nhóm ngành rồi đọc chi tiết. Phân biệt ngành đào tạo với nghề làm sau tốt nghiệp.'],
        ['id'=>'schools', 'title'=>'Tra cứu trường', 'keywords'=>['tìm trường','tra cứu trường','tuyển sinh','điểm chuẩn','học phí'], 'path'=>'index.php?page=university-explorer', 'steps'=>'Mở Tra cứu trường, chọn trường quan tâm và đối chiếu nguồn, năm của dữ liệu. Không xem dữ liệu năm cũ là thông báo tuyển sinh hiện hành.'],
    ];
}

function career_website_help(string $question): array {
    $question = mb_strtolower($question);
    foreach (career_website_guides() as $guide) {
        foreach ($guide['keywords'] as $keyword) {
            if (mb_strpos($question, $keyword) !== false) return [$guide];
        }
    }
    return [];
}
