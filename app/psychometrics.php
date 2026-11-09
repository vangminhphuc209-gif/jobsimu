<?php
declare(strict_types=1);

/**
 * Scientifically grounded psychometric engine (v3.0.0).
 *
 * Includes:
 * - big5: 20-item Mini-IPIP Five-Factor Model (Donnellan et al., 2006; Goldberg, 1999; https://ipip.ori.org/MiniIPIPKey.htm)
 *   with balanced positive (+) and reverse-keyed (-) items per domain.
 * - caas: 12-item Career Adapt-Abilities Scale Short Form (Savickas & Porfeli, 2012; Maggiori et al., 2015)
 *   with reverse-keyed attention/acquiescence checks across Concern, Control, Curiosity, and Confidence.
 * - Legacy support for mbti (12 items) and eq (10 items) to preserve backward compatibility and existing tests.
 */
function psychometric_questions(string $type): array {
    if ($type === 'big5') {
        return [
            'b01' => ['axis' => 'E', 'pole' => '+', 'text' => 'Trong các buổi thảo luận nhóm hoặc hoạt động tập thể, tôi thường là người khuấy động không khí.'],
            'b02' => ['axis' => 'A', 'pole' => '+', 'text' => 'Tôi dễ dàng đồng cảm và nhận ra cảm xúc buồn vui của những người bạn xung quanh.'],
            'b03' => ['axis' => 'C', 'pole' => '+', 'text' => 'Khi nhận một nhiệm vụ học tập, tôi thường bắt tay vào xử lý các chi tiết ngay thay vì trì hoãn.'],
            'b04' => ['axis' => 'N', 'pole' => '-', 'text' => 'Tâm trạng của tôi khá thất thường và dễ bị ảnh hưởng nặng nề bởi những chuyện không như ý.'],
            'b05' => ['axis' => 'O', 'pole' => '+', 'text' => 'Tôi có trí tưởng tượng phong phú và thường nghĩ ra những góc nhìn khác lạ cho một vấn đề.'],
            'b06' => ['axis' => 'E', 'pole' => '-', 'text' => 'Khi ở trong một tập thể đông người lạ, tôi thường giữ im lặng và ít khi chủ động lên tiếng.'],
            'b07' => ['axis' => 'A', 'pole' => '-', 'text' => 'Tôi thường ít để tâm đến những rắc rối cá nhân của người khác nếu việc đó không liên quan đến mình.'],
            'b08' => ['axis' => 'C', 'pole' => '-', 'text' => 'Tôi hay quên cất đồ dùng đúng chỗ hoặc để tài liệu học tập khá lộn xộn.'],
            'b09' => ['axis' => 'N', 'pole' => '+', 'text' => 'Khi gặp áp lực thi cử hoặc thay đổi bất ngờ, tôi thường giữ được tâm thế bình tĩnh và ít bị dao động.'],
            'b10' => ['axis' => 'O', 'pole' => '-', 'text' => 'Tôi ít có hứng thú với các khái niệm trừu tượng hoặc những cuộc thảo luận mang tính lý thuyết sâu.'],
            'b11' => ['axis' => 'E', 'pole' => '+', 'text' => 'Tôi cảm thấy thoải mái khi bắt chuyện với nhiều bạn khác nhau trong các sự kiện của trường.'],
            'b12' => ['axis' => 'A', 'pole' => '+', 'text' => 'Khi bạn bè cần sự sẻ chia, tôi luôn dành thời gian lắng nghe và tạo cảm giác ấm áp cho họ.'],
            'b13' => ['axis' => 'C', 'pole' => '+', 'text' => 'Tôi thích làm việc theo trật tự, có danh sách công việc và mốc thời gian rõ ràng.'],
            'b14' => ['axis' => 'N', 'pole' => '-', 'text' => 'Tôi rất dễ rơi vào trạng thái lo âu hoặc bực bội khi mọi việc vượt ngoài dự tính.'],
            'b15' => ['axis' => 'O', 'pole' => '+', 'text' => 'Tôi thích khám phá các ý tưởng mới mẻ, nghệ thuật hoặc các nguyên lý khoa học phức tạp.'],
            'b16' => ['axis' => 'E', 'pole' => '-', 'text' => 'Tôi thích đứng ở hậu trường hơn là trở thành tâm điểm chú ý của đám đông.'],
            'b17' => ['axis' => 'A', 'pole' => '-', 'text' => 'Đôi khi tôi thấy khó kết nối với những băn khoăn cảm xúc của người khác.'],
            'b18' => ['axis' => 'C', 'pole' => '-', 'text' => 'Khi gặp việc phức tạp, đôi lúc tôi làm qua loa cho xong thay vì kiểm tra kỹ từng bước.'],
            'b19' => ['axis' => 'N', 'pole' => '+', 'text' => 'Sau một tình huống căng thẳng, tôi có thể nhanh chóng lấy lại sự thư thái và tập trung tiếp.'],
            'b20' => ['axis' => 'O', 'pole' => '-', 'text' => 'Tôi thích những công việc quen thuộc, cụ thể hơn là phải suy nghĩ sáng tạo hay tưởng tượng nhiều.'],
        ];
    }
    if ($type === 'caas') {
        return [
            'c01' => ['axis' => 'concern', 'pole' => '+', 'text' => 'Tôi thường hình dung rõ những điều mình cần chuẩn bị từ bây giờ cho mục tiêu nghề nghiệp sau này.'],
            'c02' => ['axis' => 'concern', 'pole' => '+', 'text' => 'Tôi nhận thức rằng những lựa chọn môn học và kỹ năng hôm nay sẽ định hình tương lai của mình.'],
            'c03' => ['axis' => 'concern', 'pole' => '-', 'text' => 'Tôi hiếm khi nghĩ trước về kế hoạch sau khi tốt nghiệp mà để đến đâu tính đến đó.'],
            'c04' => ['axis' => 'control', 'pole' => '+', 'text' => 'Khi đứng trước các lựa chọn học tập, tôi chủ động tự tìm hiểu và chịu trách nhiệm với quyết định của mình.'],
            'c05' => ['axis' => 'control', 'pole' => '+', 'text' => 'Tôi kiên định bảo vệ những giá trị và mục tiêu học tập phù hợp với bản thân.'],
            'c06' => ['axis' => 'control', 'pole' => '-', 'text' => 'Tôi thường để người khác quyết định thay hướng đi vì thấy mình khó tự đưa ra lựa chọn.'],
            'c07' => ['axis' => 'curiosity', 'pole' => '+', 'text' => 'Tôi chủ động tìm kiếm cơ hội quan sát thực tế hoặc thử sức trước khi kết luận mình hợp nghề nào.'],
            'c08' => ['axis' => 'curiosity', 'pole' => '+', 'text' => 'Khi tìm hiểu một ngành nghề, tôi luôn đặt câu hỏi sâu về cả cơ hội lẫn những góc khuất khó khăn của nghề đó.'],
            'c09' => ['axis' => 'curiosity', 'pole' => '-', 'text' => 'Tôi ngại thử nghiệm những cách học mới hoặc tìm hiểu các lĩnh vực ngoài vùng quen thuộc của mình.'],
            'c10' => ['axis' => 'confidence', 'pole' => '+', 'text' => 'Khi gặp một kỹ năng mới chưa làm tốt, tôi tin mình có thể rèn luyện từng bước để làm chủ được nó.'],
            'c11' => ['axis' => 'confidence', 'pole' => '+', 'text' => 'Tôi chú trọng làm việc cẩn trọng, đúng quy trình và nỗ lực tìm giải pháp khi gặp trở ngại.'],
            'c12' => ['axis' => 'confidence', 'pole' => '-', 'text' => 'Khi vấp phải một bài toán hay thử thách khó, tôi thường dễ nản lòng và nghi ngờ khả năng của bản thân.'],
        ];
    }
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

function psychometric_big5_labels(): array {
    return [
        'O' => 'Cởi mở & Sáng tạo (Openness)',
        'C' => 'Tận tâm & Kỷ luật (Conscientiousness)',
        'E' => 'Hướng ngoại & Tương tác (Extraversion)',
        'A' => 'Hòa đồng & Thấu cảm (Agreeableness)',
        'N' => 'Vững vàng Cảm xúc (Emotional Stability)',
    ];
}

function psychometric_caas_labels(): array {
    return [
        'concern' => 'Quan tâm & Chuẩn bị tương lai (Concern)',
        'control' => 'Tự chủ Quyết định (Control)',
        'curiosity' => 'Tò mò Khám phá thực tế (Curiosity)',
        'confidence' => 'Tự tin Vượt khó (Confidence)',
    ];
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

/**
 * Evaluates response quality (Acquiescence/Flatline, Longstring, Speeding, and Reverse-Keyed Contradictions).
 * Reference: Meade & Craig (2012), Identifying Careless Responses in Survey Data; Wetzel et al. (2016).
 */
function psychometric_validate_quality(string $type, array $answers, int $durationSeconds = 0): array {
    $answers = psychometric_validate_answers($type, $answers);
    $bank = psychometric_questions($type);
    $n = count($answers);
    $values = array_values($answers);

    $mean = array_sum($values) / max(1, $n);
    $varianceSum = 0.0;
    foreach ($values as $v) {
        $varianceSum += (($v - $mean) ** 2);
    }
    $stdDev = round(sqrt($varianceSum / max(1, $n)), 3);

    $maxLongstring = 0;
    $currentStreak = 0;
    $prev = null;
    foreach ($values as $v) {
        if ($v === $prev) {
            $currentStreak++;
        } else {
            $currentStreak = 1;
            $prev = $v;
        }
        if ($currentStreak > $maxLongstring) {
            $maxLongstring = $currentStreak;
        }
    }

    // Check contradictions between positive and reverse-keyed items on the same axis
    $axisPos = [];
    $axisNeg = [];
    foreach ($bank as $key => $q) {
        $axis = $q['axis'];
        $raw = $answers[$key];
        $pole = $q['pole'] ?? '+';
        $isReverse = ($pole === '-') || ($type === 'mbti' && $pole !== $axis[0]);
        if ($isReverse) {
            $axisNeg[$axis][] = $raw;
        } else {
            $axisPos[$axis][] = $raw;
        }
    }

    $contradictions = 0;
    foreach ($axisPos as $axis => $posVals) {
        if (empty($axisNeg[$axis])) continue;
        $posAvg = array_sum($posVals) / count($posVals);
        $negAvg = array_sum($axisNeg[$axis]) / count($axisNeg[$axis]);
        // If both positive and reverse-keyed items are endorsed strongly in the same raw direction (>=4 or <=2)
        if (($posAvg >= 4.0 && $negAvg >= 4.0) || ($posAvg <= 2.0 && $negAvg <= 2.0)) {
            $contradictions++;
        }
    }

    $flag = 'valid';
    $warning = null;
    $minExpectedSeconds = max(10, (int)round($n * 1.2));

    if ($durationSeconds > 0 && $durationSeconds < $minExpectedSeconds) {
        $flag = 'rushed';
        $warning = 'Thời gian hoàn thành rất nhanh (' . $durationSeconds . ' giây). Kết quả có thể chưa phản ánh đầy đủ suy nghĩ của bạn.';
    } elseif ($contradictions >= 2) {
        $flag = 'inconsistent';
        $warning = 'Phát hiện một số câu trả lời thuận và đảo chiều có xu hướng mâu thuẫn nhau (thiên kiến đồng thuận). Hãy đọc kỹ từng mệnh đề nếu làm lại.';
    } elseif ($stdDev < 0.20 && $mean !== 3.0) {
        $flag = 'flatline';
        $warning = 'Hầu hết các câu trả lời đều ở cùng một mức độ (' . round($mean, 1) . '/5), làm giảm khả năng phân hóa điểm mạnh riêng biệt.';
    }

    return [
        'validity_flag' => $flag,
        'std_dev' => $stdDev,
        'mean_raw' => round($mean, 2),
        'max_longstring' => $maxLongstring,
        'contradictions' => $contradictions,
        'duration_seconds' => max(0, $durationSeconds),
        'warning_message' => $warning,
    ];
}

function score_psychometric(string $type, array $answers, int $durationSeconds = 0): array {
    $answers = psychometric_validate_answers($type, $answers);
    $bank = psychometric_questions($type);
    $quality = psychometric_validate_quality($type, $answers, $durationSeconds);
    $totals = [];
    $counts = [];

    foreach ($bank as $key => $question) {
        $axis = $question['axis'];
        $value = $answers[$key] - 3;
        if ($type === 'mbti' && $question['pole'] !== $axis[0]) {
            $value *= -1;
        } elseif (in_array($type, ['big5', 'caas'], true) && ($question['pole'] ?? '+') === '-') {
            $value *= -1;
        }
        $totals[$axis] = ($totals[$axis] ?? 0) + $value;
        $counts[$axis] = ($counts[$axis] ?? 0) + 1;
    }

    $scores = [];
    $code = '';
    foreach ($totals as $axis => $total) {
        $percent = (int)round(50 + 25 * $total / $counts[$axis]);
        if ($type === 'mbti') {
            $scores[$axis] = [$axis[0] => $percent, $axis[1] => 100 - $percent];
            // At exact balance, X makes the uncertainty visible instead of inventing a preference.
            $code .= $total === 0 ? 'X' : ($total > 0 ? $axis[0] : $axis[1]);
        } else {
            $scores[$axis] = max(0, min(100, $percent));
        }
    }

    if ($type === 'eq') {
        $code = 'EQ-REFLECTION';
        $summaryLabel = 'Hồ sơ tự phản tư cảm xúc';
    } elseif ($type === 'big5') {
        $sorted = $scores;
        arsort($sorted);
        $topKeys = array_slice(array_keys($sorted), 0, 2);
        $maxScore = reset($sorted);
        $minScore = end($sorted);
        $code = ($maxScore === $minScore) ? 'BIG5-BALANCED' : ('BIG5-' . implode('', $topKeys));
        $summaryLabel = 'Hồ sơ Nhân cách Big Five (Mini-IPIP) · ' . $code;
    } elseif ($type === 'caas') {
        $avg = (int)round(array_sum($scores) / max(1, count($scores)));
        $level = $avg >= 75 ? 'HIGH' : ($avg >= 50 ? 'MOD' : 'DEV');
        $code = 'CAAS-' . $level;
        $summaryLabel = 'Chỉ số Thích ứng Nghề nghiệp (CAAS) · ' . $avg . '%';
    } else {
        $summaryLabel = 'Xu hướng tự mô tả ' . $code;
    }

    return [
        'answers' => $answers,
        'scores' => $scores,
        'result_code' => $code,
        'summary_label' => $summaryLabel,
        'validity_flag' => $quality['validity_flag'],
        'validity' => $quality,
    ];
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

function psychometric_big5_insights(array $scores): array {
    $insights = [];
    foreach (['O', 'C', 'E', 'A', 'N'] as $trait) {
        $val = (int)($scores[$trait] ?? 50);
        if ($trait === 'O') {
            $insights[$trait] = $val >= 65
                ? 'Bạn cởi mở với ý tưởng trừu tượng, thích sáng tạo và khám phá góc nhìn mới (phù hợp môi trường nghiên cứu, thiết kế, đổi mới).'
                : ($val <= 35 ? 'Bạn ưu tiên sự thực tế, quy trình quen thuộc và ứng dụng cụ thể hơn là lý thuyết trừu tượng.' : 'Bạn cân bằng giữa tư duy sáng tạo mới mẻ và tính ứng dụng thực tiễn.');
        } elseif ($trait === 'C') {
            $insights[$trait] = $val >= 65
                ? 'Bạn có tính kỷ luật tự giác, làm việc có kế hoạch và chú trọng độ chính xác (nền tảng mạnh cho các ngành đòi hỏi chuẩn mực cao).'
                : ($val <= 35 ? 'Bạn làm việc linh hoạt theo cảm hứng nhưng dễ bị trễ hạn hoặc bỏ sót chi tiết; cần công cụ nhắc việc và chia nhỏ mục tiêu.' : 'Bạn giữ mức độ tổ chức vừa phải, kết hợp giữa kế hoạch và sự linh hoạt.');
        } elseif ($trait === 'E') {
            $insights[$trait] = $val >= 65
                ? 'Bạn nạp năng lượng từ tương tác xã hội, dễ thích nghi với môi trường làm việc nhóm năng động hoặc thuyết phục.'
                : ($val <= 35 ? 'Bạn làm việc sâu và tập trung tốt nhất trong không gian độc lập, yên tĩnh trước khi chia sẻ ra bên ngoài.' : 'Bạn linh hoạt giữa làm việc độc lập và phối hợp nhóm tùy nhiệm vụ.');
        } elseif ($trait === 'A') {
            $insights[$trait] = $val >= 65
                ? 'Bạn giàu lòng thấu cảm, ưu tiên sự hài hòa và hỗ trợ con người (lợi thế trong giáo dục, y tế, tham vấn, nhân sự).'
                : ($val <= 35 ? 'Bạn thiên về phản biện thẳng thắn, khách quan và đặt tiêu chí công việc lên trên sự cả nể.' : 'Bạn biết lắng nghe hợp tác nhưng vẫn giữ được sự khách quan khi cần tranh luận.');
        } elseif ($trait === 'N') {
            $insights[$trait] = $val >= 65
                ? 'Bạn có độ vững vàng cảm xúc tốt, bình tĩnh trước áp lực thời gian và biến cố bất ngờ.'
                : ($val <= 35 ? 'Bạn nhạy cảm với áp lực và dễ lo âu khi quá tải; nên rèn kỹ năng điều hòa cảm xúc và cân nhắc kỹ những nghề có khủng hoảng liên tục.' : 'Bạn phản ứng tự nhiên trước áp lực và có khả năng phục hồi khi có sự chuẩn bị.');
        }
    }
    return $insights;
}

function psychometric_caas_insights(array $scores): array {
    $insights = [];
    foreach (['concern', 'control', 'curiosity', 'confidence'] as $dim) {
        $val = (int)($scores[$dim] ?? 50);
        if ($dim === 'concern') {
            $insights[$dim] = $val >= 65 ? 'Bạn có ý thức chuẩn bị sớm và nhìn thấy mối liên hệ giữa việc học hiện tại với tương lai.' : 'Bạn cần dành thêm thời gian phác thảo các cột mốc ngắn hạn (6–12 tháng) để không bị động khi chọn trường.';
        } elseif ($dim === 'control') {
            $insights[$dim] = $val >= 65 ? 'Bạn có tính tự chủ cao, dám đưa ra quyết định và chịu trách nhiệm với lựa chọn của mình.' : 'Bạn còn dễ bị chi phối bởi ý kiến xung quanh; hãy tập tự phân tích ưu/nhược điểm của từng phương án.';
        } elseif ($dim === 'curiosity') {
            $insights[$dim] = $val >= 65 ? 'Bạn chủ động tìm hiểu sâu và kiểm chứng thông tin nghề nghiệp qua trải nghiệm thực tế.' : 'Hãy tăng cường bước vào các phòng mô phỏng và thử thách 15 phút đời thực thay vì chỉ đọc mô tả nghề.';
        } elseif ($dim === 'confidence') {
            $insights[$dim] = $val >= 65 ? 'Bạn tin vào khả năng học hỏi và kiên trì tháo gỡ khó khăn khi gặp kỹ năng mới.' : 'Hãy bắt đầu từ những nhiệm vụ nhỏ vừa sức để tích lũy trải nghiệm thành công (Mastery Experience).';
        }
    }
    return $insights;
}
