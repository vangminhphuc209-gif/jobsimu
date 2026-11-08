<?php
declare(strict_types=1);

/** Exploratory self-report questions, not a standardized MBTI or clinical EQ instrument. */
function psychometric_questions(string $type): array {
    if ($type === 'mbti') {
        return [
            'm01' => ['axis'=>'EI', 'pole'=>'E', 'text'=>'Sau một buổi học, trò chuyện cùng nhóm bạn thường giúp tôi nạp lại năng lượng.'],
            'm02' => ['axis'=>'EI', 'pole'=>'I', 'text'=>'Trước khi phát biểu, tôi thích có thời gian suy nghĩ một mình.'],
            'm03' => ['axis'=>'EI', 'pole'=>'E', 'text'=>'Khi tham gia nhóm mới, tôi thường chủ động bắt chuyện.'],
            'm04' => ['axis'=>'SN', 'pole'=>'S', 'text'=>'Khi học điều mới, tôi thích bắt đầu từ ví dụ cụ thể và cách làm từng bước.'],
            'm05' => ['axis'=>'SN', 'pole'=>'N', 'text'=>'Tôi thường hứng thú với những khả năng mới và mối liên hệ giữa các ý tưởng.'],
            'm06' => ['axis'=>'SN', 'pole'=>'S', 'text'=>'Khi giải thích một việc, tôi thường dựa vào chi tiết mình đã quan sát.'],
            'm07' => ['axis'=>'TF', 'pole'=>'T', 'text'=>'Khi phải lựa chọn, tôi thường bắt đầu bằng việc so sánh lý lẽ và tiêu chí nhất quán.'],
            'm08' => ['axis'=>'TF', 'pole'=>'F', 'text'=>'Khi nhóm bất đồng, tôi thường cân nhắc cảm xúc và nhu cầu của từng người trước.'],
            'm09' => ['axis'=>'TF', 'pole'=>'T', 'text'=>'Khi góp ý một phương án, tôi thường tập trung trước vào tính hợp lý của phương án.'],
            'm10' => ['axis'=>'JP', 'pole'=>'J', 'text'=>'Tôi thấy thoải mái khi có kế hoạch và mốc hoàn thành rõ ràng.'],
            'm11' => ['axis'=>'JP', 'pole'=>'P', 'text'=>'Tôi thích để kế hoạch mở để điều chỉnh khi xuất hiện thông tin mới.'],
            'm12' => ['axis'=>'JP', 'pole'=>'J', 'text'=>'Tôi thường thích hoàn tất một việc trước khi chuyển sang việc khác.'],
        ];
    }
    if ($type === 'eq') {
        return [
            'e01'=>['axis'=>'awareness','text'=>'Khi nhận một kết quả học tập khác mong đợi, tôi có thể gọi tên cảm xúc của mình trước khi phản ứng.'],
            'e02'=>['axis'=>'awareness','text'=>'Khi thấy khó tập trung, tôi nhận ra dấu hiệu mình đang mệt, lo hoặc cần hỗ trợ.'],
            'e03'=>['axis'=>'regulation','text'=>'Khi tranh luận trở nên căng thẳng, tôi có thể tạm dừng để bình tĩnh rồi tiếp tục.'],
            'e04'=>['axis'=>'regulation','text'=>'Khi nhiều bài tập đến cùng lúc, tôi chia việc thành các bước vừa sức.'],
            'e05'=>['axis'=>'regulation','text'=>'Khi gặp khó khăn quá sức, tôi tìm một người đáng tin cậy để nhờ hỗ trợ.'],
            'e06'=>['axis'=>'motivation','text'=>'Khi tìm thấy một chủ đề mình thích, tôi chủ động dành thời gian tìm hiểu thêm.'],
            'e07'=>['axis'=>'motivation','text'=>'Khi thử một kỹ năng mới chưa thành công, tôi thử cách khác hoặc xin góp ý.'],
            'e08'=>['axis'=>'empathy','text'=>'Khi một bạn ít nói trong nhóm, tôi tạo cơ hội để bạn chia sẻ theo cách bạn thấy thoải mái.'],
            'e09'=>['axis'=>'empathy','text'=>'Khi ý kiến của bạn khác tôi, tôi hỏi thêm để hiểu góc nhìn của bạn.'],
            'e10'=>['axis'=>'empathy','text'=>'Khi nhóm gặp hiểu lầm, tôi lắng nghe các bên và cùng tìm cách giải quyết.'],
        ];
    }
    throw new InvalidArgumentException('Bài đánh giá không hợp lệ.');
}

function psychometric_eq_labels(): array {
    return ['awareness'=>'Tự nhận thức','regulation'=>'Tự điều chỉnh','motivation'=>'Động lực & Đam mê','empathy'=>'Thấu cảm & Hợp tác'];
}

function psychometric_validate_answers(string $type, array $answers): array {
    $bank = psychometric_questions($type);
    if (count($answers) !== count($bank) || array_diff_key($answers, $bank) || array_diff_key($bank, $answers)) {
        throw new InvalidArgumentException('Vui lòng trả lời đầy đủ và đúng bộ câu hỏi.');
    }
    $valid = [];
    foreach ($bank as $key => $question) {
        $value = $answers[$key];
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-5]$/D', (string)$value)) {
            throw new InvalidArgumentException('Mỗi câu cần một lựa chọn từ 1 đến 5.');
        }
        $valid[$key] = (int)$value;
    }
    return $valid;
}

function score_psychometric(string $type, array $answers): array {
    $answers = psychometric_validate_answers($type, $answers);
    $bank = psychometric_questions($type);
    $totals = [];
    $counts = [];
    foreach ($bank as $key => $question) {
        $axis = $question['axis'];
        $value = $answers[$key] - 3;
        if ($type === 'mbti' && $question['pole'] !== $axis[0]) $value *= -1;
        $totals[$axis] = ($totals[$axis] ?? 0) + $value;
        $counts[$axis] = ($counts[$axis] ?? 0) + 1;
    }
    $scores = [];
    $code = '';
    foreach ($totals as $axis => $total) {
        $percent = (int)round(50 + 25 * $total / $counts[$axis]);
        if ($type === 'mbti') {
            $scores[$axis] = [$axis[0]=>$percent, $axis[1]=>100-$percent];
            // At exact balance, X makes the uncertainty visible instead of inventing a preference.
            $code .= $total === 0 ? 'X' : ($total > 0 ? $axis[0] : $axis[1]);
        } else {
            $scores[$axis] = $percent;
        }
    }
    if ($type === 'eq') $code = 'EQ-REFLECTION';
    return ['answers'=>$answers, 'scores'=>$scores, 'result_code'=>$code,
        'summary_label'=>$type === 'mbti' ? 'Xu hướng tự mô tả '.$code : 'Hồ sơ tự phản tư cảm xúc'];
}

function psychometric_style_descriptions(string $code): array {
    $texts = [
        'E'=>'Bạn tự mô tả xu hướng trao đổi để phát triển ý tưởng; thử học qua thảo luận.',
        'I'=>'Bạn tự mô tả nhu cầu suy nghĩ riêng; thử chuẩn bị ý tưởng trước khi thảo luận.',
        'S'=>'Bạn thiên về ví dụ cụ thể; thử học qua quan sát và thực hành từng bước.',
        'N'=>'Bạn thiên về khả năng và liên hệ mới; thử sơ đồ ý tưởng rồi kiểm chứng bằng ví dụ.',
        'T'=>'Bạn thiên về lý lẽ và tiêu chí; thử giải thích quyết định và lắng nghe tác động tới người khác.',
        'F'=>'Bạn chú ý nhu cầu con người; thử kết hợp sự đồng cảm với tiêu chí quyết định rõ ràng.',
        'J'=>'Bạn thích cấu trúc; thử kế hoạch ngắn có khoảng trống để điều chỉnh.',
        'P'=>'Bạn thích linh hoạt; thử đặt mốc nhỏ để ý tưởng đi tới hành động.',
        'X'=>'Chiều này đang cân bằng; hãy quan sát thêm trong nhiều hoàn cảnh.'
    ];
    $result = [];
    foreach (str_split($code) as $letter) if (isset($texts[$letter])) $result[] = $texts[$letter];
    return $result;
}
