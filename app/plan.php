<?php
declare(strict_types=1);

/**
 * Step 4 / 4: Month/Year Action Plan ("Lập kế hoạch")
 * - Accepts confirmed primary/backup choices & research notes via comparison_id.
 * - Grade-personalized templates (Lớp 10, Lớp 11, Lớp 12) with computed Tháng M/YYYY (never past dates).
 * - Distinguishes "Mốc tự đặt" vs "Hạn chính thức (có nguồn)" — never fabricates official deadlines.
 * - Optional AI draft via Trợ lý La Bàn with strict schema validation and preview-before-save.
 * - Full persistence (DB + user-scoped draft), print/PDF, and revocable read-only share links.
 */

function plan_current_month(): string
{
    return date('Y-m');
}

function plan_add_months(string $startMonth, int $offset): string
{
    if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $startMonth, $m)) {
        $startMonth = plan_current_month();
        preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $startMonth, $m);
    }
    $year = (int)$m[1];
    $month = (int)$m[2] + max(0, $offset);
    while ($month > 12) {
        $month -= 12;
        $year++;
    }
    return sprintf('%04d-%02d', $year, $month);
}

function plan_format_month_vi(string $monthKey): string
{
    if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $monthKey, $m)) {
        return $monthKey;
    }
    return 'Tháng ' . (int)$m[2] . '/' . $m[1];
}

function plan_horizon_months(string $horizon): int
{
    $map = [
        '3_months' => 3,
        '6_months' => 6,
        '9_months' => 9,
        '12_months' => 12,
    ];
    return $map[$horizon] ?? 6;
}

/**
 * Grade-personalized starter milestones (Lớp 10, Lớp 11, Lớp 12).
 * Never generates deadlines in the past; never invents official admissions dates.
 */
function plan_build_starter_milestones(
    string $primaryLabel,
    string $backupLabel,
    string $grade,
    string $startMonth,
    string $horizon,
    array $researchItems = []
): array {
    $minMonth = plan_current_month();
    if ($startMonth < $minMonth) {
        $startMonth = $minMonth;
    }
    $monthsCount = plan_horizon_months($horizon);
    $pairLabel = $backupLabel !== '' ? "{$primaryLabel} và {$backupLabel}" : $primaryLabel;
    $firstResearch = '';
    if (!empty($researchItems)) {
        $r0 = $researchItems[0];
        if (is_array($r0)) {
            $firstResearch = trim((string)($r0['note'] ?? $r0['question_text'] ?? $r0['question_title'] ?? ''));
        } else {
            $firstResearch = trim((string)$r0);
        }
    }
    $researchTip = $firstResearch !== ''
        ? ' Bổ sung câu hỏi cần tìm hiểu: ' . mb_substr($firstResearch, 0, 120)
        : '';

    if ($grade === 'Lớp 10') {
        $templates = [
            [
                'offset' => 0,
                'goal' => 'Tìm hiểu tổng quan ' . $pairLabel,
                'tasks' => 'Đọc mô tả ngành, môn học chính ở đại học và ghi lại ít nhất 3 điểm bạn thích cùng 3 điểm còn chưa rõ.' . $researchTip,
                'expected_outcome' => 'Có bảng ghi chú rõ ràng cho từng phương án.',
            ],
            [
                'offset' => 1,
                'goal' => 'Thử trải nghiệm thực tế nhỏ',
                'tasks' => 'Tham gia một tình huống mô phỏng nghề phù hợp trên JobSimu hoặc làm một dự án/bài tập nhỏ liên quan trong 2–3 giờ.',
                'expected_outcome' => 'Ghi lại điều thấy hứng thú, điều thấy khó và kỹ năng muốn rèn thêm.',
            ],
            [
                'offset' => min(2, $monthsCount - 1),
                'goal' => 'Xây nền tảng môn học & thói quen học tập',
                'tasks' => 'Xác định 2–3 môn trọng tâm gắn với hướng đang tìm hiểu và đặt lịch luyện tập đều đặn hàng tuần.',
                'expected_outcome' => 'Duy trì được lịch tự học ổn định trong ít nhất 4 tuần liên tiếp.',
            ],
            [
                'offset' => min(4, $monthsCount - 1),
                'goal' => 'Mở rộng góc nhìn & trao đổi với gia đình/thầy cô',
                'tasks' => 'Chia sẻ ghi chú khám phá của bạn với thầy cô hoặc gia đình để nghe thêm góc nhìn thực tế về ngành.',
                'expected_outcome' => 'Cập nhật lại danh sách phương án quan tâm trước khi lên Lớp 11.',
            ],
        ];
    } elseif ($grade === 'Lớp 12') {
        $templates = [
            [
                'offset' => 0,
                'goal' => 'Đối chiếu điều kiện tuyển sinh & nguồn chính thức',
                'tasks' => 'Kiểm tra đề án/thông báo tuyển sinh chính thức của các trường đào tạo ' . $pairLabel . ' về phương thức, tổ hợp và học phí.' . $researchTip,
                'expected_outcome' => 'Có danh sách ngắn phương án chính và dự phòng kèm link nguồn chính thức.',
            ],
            [
                'offset' => 1,
                'goal' => 'Rà soát lực học & lập lịch ôn tập các môn trọng tâm',
                'tasks' => 'Đối chiếu kết quả bài kiểm tra hiện tại với tổ hợp dự kiến, phân bổ giờ ôn theo phần kiến thức còn hổng.',
                'expected_outcome' => 'Hoàn thành lịch ôn tập theo tuần và ít nhất 2 đề luyện tự chấm.',
            ],
            [
                'offset' => min(2, $monthsCount - 1),
                'goal' => 'Chuẩn bị minh chứng & hồ sơ theo lịch thực tế',
                'tasks' => 'Theo dõi thông báo chính thức từ Bộ GD&ĐT và từng trường để tự điền mốc hạn chính thức khi có công bố (không dùng mốc đoán).',
                'expected_outcome' => 'Kiểm tra đủ giấy tờ/minh chứng cần thiết và cập nhật các hạn chính thức có nguồn.',
            ],
            [
                'offset' => min(4, $monthsCount - 1),
                'goal' => 'Chốt thứ tự nguyện vọng chính & dự phòng',
                'tasks' => 'Xem lại toàn bộ ghi chú điểm tham khảo, học phí và mức độ sẵn sàng của bản thân cùng gia đình.',
                'expected_outcome' => 'Có bảng thứ tự nguyện vọng hợp lý giữa mục tiêu ưu tiên và phương án an toàn.',
            ],
        ];
    } else {
        // Lớp 11 (or default THPT)
        $templates = [
            [
                'offset' => 0,
                'goal' => 'Tìm hiểu sâu ' . $pairLabel,
                'tasks' => 'Đọc chương trình học, công việc thường gặp và ghi rõ điểm thích / điểm còn phân vân của từng lựa chọn.' . $researchTip,
                'expected_outcome' => 'Có bảng ghi chú so sánh chi tiết cho từng phương án.',
            ],
            [
                'offset' => 1,
                'goal' => 'Trải nghiệm thực tế để kiểm chứng hứng thú',
                'tasks' => 'Làm một hoạt động nhỏ, câu lạc bộ hoặc tình huống mô phỏng nghề phù hợp trên JobSimu.',
                'expected_outcome' => 'Ghi lại điều thích, điều khó và những câu hỏi muốn tìm hiểu thêm.',
            ],
            [
                'offset' => min(2, $monthsCount - 1),
                'goal' => 'Đối chiếu trường đào tạo & tổ hợp xét tuyển',
                'tasks' => 'Tra cứu trường đào tạo, tổ hợp xét tuyển, khoảng học phí công bố và nguồn chính thức để thu hẹp lựa chọn.',
                'expected_outcome' => 'Có danh sách phương án chính/dự phòng và tổ hợp môn cần ưu tiên.',
            ],
            [
                'offset' => min(4, $monthsCount - 1),
                'goal' => 'Cải thiện môn học trọng tâm chuẩn bị cho Lớp 12',
                'tasks' => 'Tập trung củng cố kiến thức nền ở 3 môn trong tổ hợp mục tiêu và kiểm tra tiến độ cuối kỳ.',
                'expected_outcome' => 'Cải thiện rõ ở ít nhất 1 môn trọng tâm và chốt hướng ôn tập cho năm cuối cấp.',
            ],
        ];
    }

    $items = [];
    foreach ($templates as $idx => $tpl) {
        $mKey = plan_add_months($startMonth, (int)$tpl['offset']);
        $items[] = [
            'id' => 'tpl_' . ($idx + 1),
            'month_key' => $mKey,
            'due_date' => '',
            'deadline_kind' => 'self',
            'official_source_url' => '',
            'goal' => $tpl['goal'],
            'tasks' => $tpl['tasks'],
            'expected_outcome' => $tpl['expected_outcome'],
            'status' => 'not_started',
            'source' => 'template',
            'user_edited' => false,
        ];
    }

    return $items;
}

/**
 * Validate and sanitize milestones array; prevents past month keys for newly generated items.
 */
function plan_sanitize_items(array $rawItems, string $minMonth = ''): array
{
    $allowedStatus = ['not_started', 'in_progress', 'completed'];
    $allowedKinds = ['self', 'official'];
    $allowedSources = ['manual', 'template', 'ai'];
    $clean = [];

    foreach ($rawItems as $idx => $item) {
        if (!is_array($item)) continue;
        $goal = trim(mb_substr((string)($item['goal'] ?? ''), 0, 255));
        $tasks = trim(mb_substr((string)($item['tasks'] ?? ''), 0, 1500));
        $outcome = trim(mb_substr((string)($item['expected_outcome'] ?? ''), 0, 1000));
        if ($goal === '' && $tasks === '') continue;
        if ($goal === '') $goal = 'Mục tiêu hành động';

        $monthKey = trim((string)($item['month_key'] ?? ''));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthKey)) {
            $monthKey = plan_current_month();
        }
        if ($minMonth !== '' && $monthKey < $minMonth) {
            $monthKey = $minMonth;
        }

        $dueDate = trim((string)($item['due_date'] ?? ''));
        if ($dueDate !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $dueDate)) {
            $dueDate = '';
        }

        $kind = in_array($item['deadline_kind'] ?? '', $allowedKinds, true) ? (string)$item['deadline_kind'] : 'self';
        $sourceUrl = '';
        if ($kind === 'official') {
            $sourceUrl = safe_external_url((string)($item['official_source_url'] ?? ''));
            if ($sourceUrl === '') {
                // Official deadlines must have a valid source URL; otherwise keep as self-set
                $kind = 'self';
            }
        }

        $status = in_array($item['status'] ?? '', $allowedStatus, true) ? (string)$item['status'] : 'not_started';
        $source = in_array($item['source'] ?? '', $allowedSources, true) ? (string)$item['source'] : 'manual';

        $clean[] = [
            'month_key' => $monthKey,
            'due_date' => $dueDate,
            'deadline_kind' => $kind,
            'official_source_url' => $sourceUrl,
            'goal' => $goal,
            'tasks' => $tasks,
            'expected_outcome' => $outcome,
            'status' => $status,
            'source' => $source,
            'user_edited' => !empty($item['user_edited']),
            'sort_order' => $idx,
        ];

        if (count($clean) >= 40) break;
    }

    // Sort chronologically by month_key, then due_date, then original order
    usort($clean, static function (array $a, array $b): int {
        if ($a['month_key'] !== $b['month_key']) {
            return strcmp($a['month_key'], $b['month_key']);
        }
        $da = $a['due_date'] !== '' ? $a['due_date'] : '9999-12-31';
        $db = $b['due_date'] !== '' ? $b['due_date'] : '9999-12-31';
        if ($da !== $db) {
            return strcmp($da, $db);
        }
        return $a['sort_order'] <=> $b['sort_order'];
    });

    return array_values($clean);
}

/**
 * Request a structured AI draft from Trợ lý La Bàn with strict JSON schema validation.
 * Falls back cleanly if AI provider is unavailable or returns invalid schema.
 */
function plan_generate_ai_draft(array $input): array
{
    $primary = trim(mb_substr((string)($input['primary_label'] ?? ''), 0, 120));
    $backup = trim(mb_substr((string)($input['backup_label'] ?? ''), 0, 120));
    $grade = trim((string)($input['grade'] ?? 'Lớp 11'));
    if (!in_array($grade, ['Lớp 10', 'Lớp 11', 'Lớp 12'], true)) {
        $grade = 'Lớp 11';
    }
    $startMonth = trim((string)($input['start_month'] ?? plan_current_month()));
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $startMonth) || $startMonth < plan_current_month()) {
        $startMonth = plan_current_month();
    }
    $horizon = trim((string)($input['target_horizon'] ?? '6_months'));
    $monthsCount = plan_horizon_months($horizon);
    $hours = !empty($input['hours_per_week']) ? max(1, min(40, (int)$input['hours_per_week'])) : null;
    $research = is_array($input['research_items'] ?? null) ? array_slice($input['research_items'], 0, 5) : [];

    if ($primary === '') {
        return [
            'ok' => false,
            'error' => 'Vui lòng nhập hoặc chọn phương án ưu tiên trước khi nhờ Trợ lý La Bàn gợi ý bản nháp.',
        ];
    }

    $aiItems = null;
    $usedAi = false;
    $note = '';

    if (is_file(__DIR__ . '/chat-providers.php')) {
        require_once __DIR__ . '/chat-providers.php';
        if (function_exists('chat_provider_answer')) {
            $sys = "Bạn là Trợ lý La Bàn trên JobSimu. Hãy lập bản nháp kế hoạch hướng nghiệp ngắn gọn cho học sinh THPT bằng tiếng Việt.\n"
                 . "BẮT BUỘC chỉ trả về JSON hợp lệ theo đúng cấu trúc:\n"
                 . "{\"note\":\"...\",\"items\":[{\"month_offset\":0,\"goal\":\"...\",\"tasks\":\"...\",\"expected_outcome\":\"...\"}]}\n"
                 . "Quy tắc bắt buộc:\n"
                 . "- Tạo từ 3 đến 5 mốc với month_offset là số nguyên từ 0 đến " . ($monthsCount - 1) . ".\n"
                 . "- KHÔNG được bịa lịch thi, ngày đăng ký nguyện vọng hay hạn tuyển sinh chính thức.\n"
                 . "- KHÔNG đưa phần trăm phù hợp hay khẳng định đỗ đại học.\n"
                 . "- Phù hợp đúng khối lớp ({$grade}).";

            $usr = "Phương án ưu tiên: {$primary}\n"
                 . ($backup !== '' ? "Phương án dự phòng: {$backup}\n" : '')
                 . "Khối lớp: {$grade}\n"
                 . "Tháng bắt đầu: " . plan_format_month_vi($startMonth) . " ({$monthsCount} tháng)\n"
                 . ($hours ? "Thời gian mỗi tuần: {$hours} giờ\n" : '')
                 . (!empty($research) ? "Điều cần tìm hiểu thêm: " . implode('; ', $research) . "\n" : '');

            try {
                $resp = chat_provider_answer([
                    ['role' => 'system', 'content' => $sys],
                    ['role' => 'user', 'content' => $usr],
                ]);
                $rawText = (string)($resp['message'] ?? '');
                if (($resp['model'] ?? 'unavailable') !== 'unavailable' && $rawText !== '') {
                    if (preg_match('/\{[\s\S]*\}/', $rawText, $m)) {
                        $decoded = json_decode($m[0], true);
                        if (is_array($decoded) && !empty($decoded['items']) && is_array($decoded['items'])) {
                            $validated = [];
                            foreach ($decoded['items'] as $idx => $it) {
                                if (!is_array($it)) continue;
                                $goal = trim(mb_substr((string)($it['goal'] ?? ''), 0, 220));
                                $tasks = trim(mb_substr((string)($it['tasks'] ?? ''), 0, 800));
                                $outcome = trim(mb_substr((string)($it['expected_outcome'] ?? ''), 0, 500));
                                if ($goal === '' || $tasks === '') continue;
                                $off = max(0, min($monthsCount - 1, (int)($it['month_offset'] ?? $idx)));
                                $validated[] = [
                                    'month_key' => plan_add_months($startMonth, $off),
                                    'due_date' => '',
                                    'deadline_kind' => 'self',
                                    'official_source_url' => '',
                                    'goal' => $goal,
                                    'tasks' => $tasks,
                                    'expected_outcome' => $outcome ?: 'Hoàn thành ghi chú và đánh giá lại tiến độ.',
                                    'status' => 'not_started',
                                    'source' => 'ai',
                                    'user_edited' => false,
                                ];
                            }
                            if (count($validated) >= 2) {
                                $aiItems = plan_sanitize_items($validated, $startMonth);
                                $usedAi = true;
                                $note = trim(mb_substr((string)($decoded['note'] ?? ''), 0, 300));
                            }
                        }
                    }
                }
            } catch (Throwable $ignored) {
            }
        }
    }

    if ($aiItems === null) {
        $aiItems = plan_build_starter_milestones($primary, $backup, $grade, $startMonth, $horizon, $research);
        $note = 'Trợ lý La Bàn đang dùng mẫu gợi ý có cấu trúc theo khối ' . $grade . ' (AI trực tuyến tạm chưa phản hồi). Bạn hoàn toàn có thể chỉnh sửa tự do trước khi lưu.';
    } else {
        if ($note === '') {
            $note = 'Bản nháp do Trợ lý La Bàn gợi ý theo khối ' . $grade . '. Hãy xem lại và chỉnh sửa theo lịch học thực tế của bạn trước khi lưu.';
        }
    }

    return [
        'ok' => true,
        'used_ai' => $usedAi,
        'note' => $note,
        'items' => $aiItems,
    ];
}

function plan_load_by_uid(string $uid, ?array $me, ?PDO $pdo = null): ?array
{
    if ($uid === '' || !preg_match('/^[a-f0-9-]{8,36}$/i', $uid)) return null;
    $conn = $pdo ?? db();
    $stmt = $conn->prepare('SELECT * FROM career_action_plans WHERE uid = ? LIMIT 1');
    $stmt->execute([$uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    if ($row['user_id'] !== null) {
        if (!$me || (int)$row['user_id'] !== (int)$me['id']) {
            return null;
        }
    } else {
        $guestUids = is_array($_SESSION['guest_plan_uids'] ?? null) ? $_SESSION['guest_plan_uids'] : [];
        if (!in_array($uid, $guestUids, true)) {
            return null;
        }
    }

    $iStmt = $conn->prepare('SELECT * FROM career_action_plan_items WHERE plan_id = ? ORDER BY month_key ASC, sort_order ASC, id ASC');
    $iStmt->execute([(int)$row['id']]);
    $items = array_map(static function (array $it): array {
        return [
            'id' => (int)$it['id'],
            'month_key' => (string)$it['month_key'],
            'due_date' => (string)($it['due_date'] ?? ''),
            'deadline_kind' => (string)$it['deadline_kind'],
            'official_source_url' => (string)($it['official_source_url'] ?? ''),
            'goal' => (string)$it['goal'],
            'tasks' => (string)$it['tasks'],
            'expected_outcome' => (string)$it['expected_outcome'],
            'status' => (string)$it['status'],
            'source' => (string)$it['source'],
            'user_edited' => (int)$it['user_edited'] === 1,
        ];
    }, $iStmt->fetchAll(PDO::FETCH_ASSOC));

    $shareToken = null;
    if ((int)$row['share_enabled'] === 1) {
        $tStmt = $conn->prepare('SELECT token FROM plan_share_tokens WHERE plan_id = ? AND revoked_at IS NULL ORDER BY id DESC LIMIT 1');
        $tStmt->execute([(int)$row['id']]);
        $shareToken = $tStmt->fetchColumn() ?: null;
    }

    return [
        'id' => (int)$row['id'],
        'uid' => (string)$row['uid'],
        'comparison_uid' => $row['comparison_uid'] ? (string)$row['comparison_uid'] : null,
        'primary_option' => json_decode((string)$row['primary_option_json'], true) ?: ['label' => ''],
        'backup_option' => json_decode((string)$row['backup_option_json'], true) ?: null,
        'grade' => (string)($row['grade'] ?? ''),
        'start_month' => (string)$row['start_month'],
        'target_horizon' => (string)$row['target_horizon'],
        'hours_per_week' => $row['hours_per_week'] !== null ? (int)$row['hours_per_week'] : null,
        'research_items' => json_decode((string)$row['research_items_json'], true) ?: [],
        'share_enabled' => (int)$row['share_enabled'] === 1,
        'share_token' => $shareToken,
        'items' => $items,
        'updated_at' => (string)$row['updated_at'],
    ];
}

function plan_load_latest_for_user(?array $me, ?PDO $pdo = null): ?array
{
    if (!$me || empty($me['id'])) return null;
    $conn = $pdo ?? db();
    $stmt = $conn->prepare('SELECT uid FROM career_action_plans WHERE user_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1');
    $stmt->execute([(int)$me['id']]);
    $uid = $stmt->fetchColumn();
    return $uid ? plan_load_by_uid((string)$uid, $me, $conn) : null;
}

function plan_handle_api(): void
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

    $action = trim((string)($payload['action'] ?? 'save'));
    $me = user();
    $conn = db();

    if ($action === 'ai_draft') {
        echo json_encode(plan_generate_ai_draft($payload), JSON_UNESCAPED_UNICODE);
        return;
    }

    if ($action === 'toggle_share') {
        $uid = trim((string)($payload['uid'] ?? ''));
        $enable = !empty($payload['enable']);
        $plan = plan_load_by_uid($uid, $me, $conn);
        if (!$plan) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Cần lưu kế hoạch trước khi bật chia sẻ.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($enable) {
            $token = $plan['share_token'];
            if (!$token) {
                $token = bin2hex(random_bytes(16));
                $ins = $conn->prepare('INSERT INTO plan_share_tokens(plan_id, token) VALUES(?, ?)');
                $ins->execute([$plan['id'], $token]);
            }
            $conn->prepare('UPDATE career_action_plans SET share_enabled = 1 WHERE id = ?')->execute([$plan['id']]);
            echo json_encode([
                'ok' => true,
                'share_enabled' => true,
                'share_token' => $token,
                'share_url' => url('index.php?page=plan-share&token=' . $token),
            ], JSON_UNESCAPED_UNICODE);
            return;
        } else {
            $conn->prepare('UPDATE plan_share_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE plan_id = ? AND revoked_at IS NULL')->execute([$plan['id']]);
            $conn->prepare('UPDATE career_action_plans SET share_enabled = 0 WHERE id = ?')->execute([$plan['id']]);
            echo json_encode(['ok' => true, 'share_enabled' => false, 'share_token' => null, 'share_url' => null], JSON_UNESCAPED_UNICODE);
            return;
        }
    }

    // Save plan
    $primaryRaw = is_array($payload['primary_option'] ?? null) ? $payload['primary_option'] : ['label' => (string)($payload['primary_label'] ?? '')];
    $primaryLabel = trim(mb_substr((string)($primaryRaw['label'] ?? ''), 0, 120));
    if ($primaryLabel === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Vui lòng nhập phương án ưu tiên cho kế hoạch.'], JSON_UNESCAPED_UNICODE);
        return;
    }
    $primaryOpt = [
        'id' => trim((string)($primaryRaw['id'] ?? 'custom:primary')),
        'label' => $primaryLabel,
        'type' => in_array($primaryRaw['type'] ?? '', ['major', 'program', 'custom'], true) ? (string)$primaryRaw['type'] : 'custom',
        'major_id' => !empty($primaryRaw['major_id']) && is_numeric($primaryRaw['major_id']) ? (int)$primaryRaw['major_id'] : null,
        'program_id' => !empty($primaryRaw['program_id']) && is_numeric($primaryRaw['program_id']) ? (int)$primaryRaw['program_id'] : null,
    ];

    $backupOpt = null;
    $backupRaw = is_array($payload['backup_option'] ?? null) ? $payload['backup_option'] : ['label' => (string)($payload['backup_label'] ?? '')];
    $backupLabel = trim(mb_substr((string)($backupRaw['label'] ?? ''), 0, 120));
    if ($backupLabel !== '') {
        $backupOpt = [
            'id' => trim((string)($backupRaw['id'] ?? 'custom:backup')),
            'label' => $backupLabel,
            'type' => in_array($backupRaw['type'] ?? '', ['major', 'program', 'custom'], true) ? (string)$backupRaw['type'] : 'custom',
            'major_id' => !empty($backupRaw['major_id']) && is_numeric($backupRaw['major_id']) ? (int)$backupRaw['major_id'] : null,
            'program_id' => !empty($backupRaw['program_id']) && is_numeric($backupRaw['program_id']) ? (int)$backupRaw['program_id'] : null,
        ];
    }

    $grade = trim((string)($payload['grade'] ?? ''));
    if ($grade !== '' && !in_array($grade, ['Lớp 10', 'Lớp 11', 'Lớp 12'], true)) {
        $grade = '';
    }

    $startMonth = trim((string)($payload['start_month'] ?? plan_current_month()));
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $startMonth)) {
        $startMonth = plan_current_month();
    }

    $uid = trim((string)($payload['uid'] ?? ''));
    $existing = $uid !== '' ? plan_load_by_uid($uid, $me, $conn) : null;
    if (!$existing && $startMonth < plan_current_month()) {
        $startMonth = plan_current_month();
    }

    $horizon = in_array($payload['target_horizon'] ?? '', ['3_months', '6_months', '9_months', '12_months'], true)
        ? (string)$payload['target_horizon']
        : '6_months';
    $hours = isset($payload['hours_per_week']) && $payload['hours_per_week'] !== '' && is_numeric($payload['hours_per_week'])
        ? max(1, min(40, (int)$payload['hours_per_week']))
        : null;
    $compUid = trim((string)($payload['comparison_uid'] ?? ''));
    if ($compUid !== '' && !preg_match('/^[a-f0-9-]{8,36}$/i', $compUid)) {
        $compUid = '';
    }

    $researchItems = [];
    if (is_array($payload['research_items'] ?? null)) {
        foreach (array_slice($payload['research_items'], 0, 15) as $rItem) {
            $t = trim(mb_substr((string)$rItem, 0, 220));
            if ($t !== '') $researchItems[] = $t;
        }
    }

    $minMonthForItems = $existing ? '' : plan_current_month();
    $cleanItems = plan_sanitize_items(is_array($payload['items'] ?? null) ? $payload['items'] : [], $minMonthForItems);

    $conn->beginTransaction();
    try {
        if ($existing) {
            $upd = $conn->prepare('UPDATE career_action_plans SET comparison_uid=?, primary_option_json=?, backup_option_json=?, grade=?, start_month=?, target_horizon=?, hours_per_week=?, research_items_json=?, updated_at=CURRENT_TIMESTAMP WHERE id=?');
            $upd->execute([
                $compUid !== '' ? $compUid : $existing['comparison_uid'],
                json_encode($primaryOpt, JSON_UNESCAPED_UNICODE),
                $backupOpt ? json_encode($backupOpt, JSON_UNESCAPED_UNICODE) : null,
                $grade !== '' ? $grade : null,
                $startMonth,
                $horizon,
                $hours,
                json_encode($researchItems, JSON_UNESCAPED_UNICODE),
                $existing['id'],
            ]);
            $planId = $existing['id'];
            $savedUid = $existing['uid'];
            $conn->prepare('DELETE FROM career_action_plan_items WHERE plan_id = ?')->execute([$planId]);
        } else {
            $savedUid = journey_uuid4();
            $ins = $conn->prepare('INSERT INTO career_action_plans(uid, user_id, comparison_uid, primary_option_json, backup_option_json, grade, start_month, target_horizon, hours_per_week, research_items_json) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([
                $savedUid,
                $me ? (int)$me['id'] : null,
                $compUid !== '' ? $compUid : null,
                json_encode($primaryOpt, JSON_UNESCAPED_UNICODE),
                $backupOpt ? json_encode($backupOpt, JSON_UNESCAPED_UNICODE) : null,
                $grade !== '' ? $grade : null,
                $startMonth,
                $horizon,
                $hours,
                json_encode($researchItems, JSON_UNESCAPED_UNICODE),
            ]);
            $planId = (int)$conn->lastInsertId();
            if (!$me) {
                if (!is_array($_SESSION['guest_plan_uids'] ?? null)) {
                    $_SESSION['guest_plan_uids'] = [];
                }
                $_SESSION['guest_plan_uids'][] = $savedUid;
            }
        }

        $insItem = $conn->prepare('INSERT INTO career_action_plan_items(plan_id, month_key, due_date, deadline_kind, official_source_url, goal, tasks, expected_outcome, status, source, user_edited, sort_order) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($cleanItems as $idx => $it) {
            $insItem->execute([
                $planId,
                $it['month_key'],
                $it['due_date'] !== '' ? $it['due_date'] : null,
                $it['deadline_kind'],
                $it['official_source_url'] !== '' ? $it['official_source_url'] : null,
                $it['goal'],
                $it['tasks'],
                $it['expected_outcome'],
                $it['status'],
                $it['source'],
                $it['user_edited'] ? 1 : 0,
                $idx,
            ]);
        }

        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Không thể lưu kế hoạch lúc này. Vui lòng thử lại.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $fresh = plan_load_by_uid($savedUid, $me, $conn);
    echo json_encode([
        'ok' => true,
        'plan' => $fresh,
        'saved_at' => date('H:i'),
    ], JSON_UNESCAPED_UNICODE);
}

function plan_render_share_page(): void
{
    $token = is_string($_GET['token'] ?? null) ? trim($_GET['token']) : '';
    $conn = db();
    $planRow = null;
    $items = [];

    if ($token !== '' && preg_match('/^[a-f0-9]{16,64}$/i', $token)) {
        $stmt = $conn->prepare(
            'SELECT p.* FROM plan_share_tokens t
             JOIN career_action_plans p ON p.id = t.plan_id
             WHERE t.token = ? AND t.revoked_at IS NULL AND p.share_enabled = 1
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $planRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($planRow) {
            $iStmt = $conn->prepare('SELECT * FROM career_action_plan_items WHERE plan_id = ? ORDER BY month_key ASC, sort_order ASC, id ASC');
            $iStmt->execute([(int)$planRow['id']]);
            $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if (!$planRow) {
        http_response_code(404);
        $pageTitle = 'Liên kết chia sẻ không tồn tại hoặc đã thu hồi';
        require __DIR__ . '/header.php';
        echo '<div class="container journey-narrow py-12"><div class="journey-card text-center"><h1 class="text-2xl font-black">Liên kết chia sẻ không khả dụng</h1><p class="text-sm text-slate-600 mt-2">Kế hoạch này chưa bật chia sẻ hoặc liên kết đã được chủ sở hữu thu hồi.</p><a class="btn btn-primary mt-4" href="' . e(url('index.php?page=plan')) . '">Tạo kế hoạch của riêng bạn →</a></div></div>';
        require __DIR__ . '/footer.php';
        return;
    }

    $primary = json_decode((string)$planRow['primary_option_json'], true) ?: ['label' => ''];
    $backup = json_decode((string)$planRow['backup_option_json'], true) ?: null;
    $statusLabels = ['not_started' => 'Chưa bắt đầu', 'in_progress' => 'Đang làm', 'completed' => 'Hoàn thành'];

    $pageTitle = 'Kế hoạch định hướng (Chế độ chỉ đọc)';
    require __DIR__ . '/header.php';
    ?>
    <section class="page-head journey-head">
      <div class="container journey-wide">
        <span class="journey-step-pill">Liên kết chia sẻ chỉ đọc</span>
        <h1 class="page-title mt-2">Kế hoạch hành động: <?=e((string)$primary['label'])?></h1>
        <p class="page-subtitle">
          <?=!empty($backup['label']) ? 'Phương án dự phòng: ' . e((string)$backup['label']) . ' · ' : ''?>
          <?=!empty($planRow['grade']) ? e((string)$planRow['grade']) . ' · ' : ''?>
          Bắt đầu: <?=e(plan_format_month_vi((string)$planRow['start_month']))?>
        </p>
      </div>
    </section>

    <div class="container journey-wide py-8">
      <div class="journey-card">
        <?php if (empty($items)): ?>
          <p class="text-sm text-slate-500">Kế hoạch chưa có mốc hành động nào.</p>
        <?php else: ?>
          <div class="lookup-desktop-table-wrap">
            <table class="lookup-table">
              <thead>
                <tr>
                  <th>Tháng/Năm</th>
                  <th>Mục tiêu</th>
                  <th>Việc cụ thể</th>
                  <th>Kết quả cần đạt</th>
                  <th>Trạng thái</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $it): ?>
                  <tr>
                    <td>
                      <strong><?=e(plan_format_month_vi((string)$it['month_key']))?></strong>
                      <div class="text-[11px] text-slate-500"><?=($it['deadline_kind'] === 'official') ? 'Hạn chính thức (có nguồn)' : 'Mốc tự đặt'?></div>
                    </td>
                    <td><strong><?=e((string)$it['goal'])?></strong></td>
                    <td><?=nl2br(e((string)$it['tasks']))?></td>
                    <td><?=nl2br(e((string)$it['expected_outcome']))?></td>
                    <td><span class="badge badge-subtle"><?=e($statusLabels[$it['status']] ?? 'Chưa bắt đầu')?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="lookup-mobile-cards-wrap">
            <?php foreach ($items as $it): ?>
              <article class="lookup-school-mobile-card">
                <div class="text-xs font-bold text-brand-600"><?=e(plan_format_month_vi((string)$it['month_key']))?> · <?=($it['deadline_kind'] === 'official') ? 'Hạn chính thức' : 'Mốc tự đặt'?></div>
                <h3 class="text-base font-bold text-slate-900 mt-1"><?=e((string)$it['goal'])?></h3>
                <p class="text-sm text-slate-700 mt-1"><?=nl2br(e((string)$it['tasks']))?></p>
                <p class="text-xs text-slate-500 mt-2"><strong>Kết quả cần đạt:</strong> <?=e((string)$it['expected_outcome'])?></p>
                <div class="mt-2"><span class="badge badge-subtle"><?=e($statusLabels[$it['status']] ?? 'Chưa bắt đầu')?></span></div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
    require __DIR__ . '/footer.php';
}

function plan_render_page(): void
{
    require_once __DIR__ . '/compare.php';

    $me = user();
    $conn = db();
    $compUid = is_string($_GET['comparison_id'] ?? null) ? trim($_GET['comparison_id']) : '';
    $planUid = is_string($_GET['uid'] ?? null) ? trim($_GET['uid']) : '';

    $existingPlan = $planUid !== ''
        ? plan_load_by_uid($planUid, $me, $conn)
        : plan_load_latest_for_user($me, $conn);

    // If coming directly from a confirmed comparison, prefill from that comparison
    $prefillFromComparison = null;
    if ($compUid !== '') {
        $comp = compare_load_by_uid($compUid, $me, $conn);
        if ($comp) {
            $optById = [];
            foreach ($comp['options'] as $o) {
                $optById[$o['id']] = $o;
            }
            $primary = ($comp['primary_option_id'] && isset($optById[$comp['primary_option_id']]))
                ? $optById[$comp['primary_option_id']]
                : ($comp['options'][0] ?? ['id' => 'custom:primary', 'label' => '', 'type' => 'custom']);
            $backup = ($comp['backup_option_id'] && isset($optById[$comp['backup_option_id']]))
                ? $optById[$comp['backup_option_id']]
                : null;

            $research = is_array($comp['research_notes']) ? $comp['research_notes'] : [];
            if (!empty($comp['result']['skipped_questions'])) {
                foreach (array_slice($comp['result']['skipped_questions'], 0, 4) as $sq) {
                    $txt = 'Tìm hiểu thêm (' . ($sq['category'] ?? '') . '): ' . ($sq['text'] ?? '');
                    if (!in_array($txt, $research, true)) $research[] = $txt;
                }
            }

            $prefillFromComparison = [
                'comparison_uid' => $comp['uid'],
                'primary_option' => $primary,
                'backup_option' => $backup,
                'research_items' => $research,
            ];
        }
    }

    $userGrade = ($me && in_array(trim((string)($me['grade'] ?? '')), ['Lớp 10', 'Lớp 11', 'Lớp 12'], true))
        ? trim((string)$me['grade'])
        : 'Lớp 11';

    $currentMonth = plan_current_month();
    $initialPlan = null;

    if ($prefillFromComparison && (!$existingPlan || $existingPlan['comparison_uid'] !== $prefillFromComparison['comparison_uid'])) {
        $starterItems = plan_build_starter_milestones(
            (string)$prefillFromComparison['primary_option']['label'],
            (string)($prefillFromComparison['backup_option']['label'] ?? ''),
            $userGrade,
            $currentMonth,
            '6_months',
            $prefillFromComparison['research_items']
        );
        $initialPlan = [
            'uid' => '',
            'comparison_uid' => $prefillFromComparison['comparison_uid'],
            'primary_option' => $prefillFromComparison['primary_option'],
            'backup_option' => $prefillFromComparison['backup_option'],
            'grade' => $userGrade,
            'start_month' => $currentMonth,
            'target_horizon' => '6_months',
            'hours_per_week' => 4,
            'research_items' => $prefillFromComparison['research_items'],
            'share_enabled' => false,
            'share_token' => null,
            'items' => $starterItems,
        ];
    } elseif ($existingPlan) {
        $initialPlan = $existingPlan;
    } else {
        $initialPlan = [
            'uid' => '',
            'comparison_uid' => null,
            'primary_option' => ['id' => 'custom:primary', 'label' => '', 'type' => 'custom', 'major_id' => null, 'program_id' => null],
            'backup_option' => null,
            'grade' => $userGrade,
            'start_month' => $currentMonth,
            'target_horizon' => '6_months',
            'hours_per_week' => null,
            'research_items' => [],
            'share_enabled' => false,
            'share_token' => null,
            'items' => plan_build_starter_milestones('Hướng ngành ưu tiên của bạn', '', $userGrade, $currentMonth, '6_months', []),
        ];
    }

    $storageKey = 'jobsimu_plan_v1_' . ($me ? ('u' . (int)$me['id']) : 'guest');
    $pageTitle = 'Lập kế hoạch theo tháng/năm · Hành trình hướng nghiệp';
    require __DIR__ . '/header.php';
    ?>
    <section class="page-head journey-head plan-no-print">
      <div class="container journey-wide">
        <div class="journey-step-row">
          <span class="journey-step-pill">BƯỚC 4 / 4</span>
          <span class="journey-step-note">Mốc theo tháng/năm cụ thể · Chỉnh sửa tự do &amp; In PDF</span>
        </div>
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 class="page-title">Lập kế hoạch hành động</h1>
            <p class="page-subtitle">Biến phương án bạn đã chọn thành các mốc công việc cụ thể theo từng tháng/năm.</p>
            <p class="text-xs text-slate-500 mt-1">
              Kế hoạch cũ trên Bản đồ hành trình vẫn được giữ nguyên (không tự đoán tháng/năm hay ghi đè).
              <a href="<?=url('index.php?page=ai-roadmap')?>" class="font-semibold text-brand-600 hover:underline">Mở kế hoạch cũ (Bản đồ hành trình) →</a>
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button type="button" id="planPrintBtn" class="btn btn-outline btn-sm">🖨️ In / Lưu PDF</button>
            <button type="button" id="planAiDraftBtn" class="btn btn-outline btn-sm">🧭 Nhờ Trợ lý La Bàn gợi ý bản nháp</button>
            <button type="button" id="planSaveBtn" class="btn btn-primary btn-sm">Lưu kế hoạch</button>
          </div>
        </div>
      </div>
    </section>

    <div
      class="container journey-wide py-8"
      id="planApp"
      data-api-url="<?=e(url('index.php?page=plan-api'))?>"
      data-share-base="<?=e(url('index.php?page=plan-share&token='))?>"
      data-storage-key="<?=e($storageKey)?>"
      data-current-month="<?=e($currentMonth)?>"
      data-logged-in="<?=$me ? '1' : '0'?>"
      data-csrf="<?=e(csrf_token())?>"
      data-from-comparison="<?=$compUid !== '' ? '1' : '0'?>"
    >
      <!-- Print Header (visible in PDF/print) -->
      <div class="plan-print-header">
        <h1>Kế hoạch hành động hướng nghiệp — Hướng Nghiệp 360 (JobSimu)</h1>
        <p id="planPrintMeta"></p>
      </div>

      <!-- Save status & privacy notice -->
      <div class="plan-status-strip plan-no-print">
        <span id="planSaveStatus" class="text-xs font-semibold text-slate-600">
          <?=$me ? 'Kế hoạch được lưu vào tài khoản của bạn.' : 'Kế hoạch được lưu tạm trên trình duyệt và phiên làm việc hiện tại.'?>
        </span>
        <span id="planSaveError" class="text-xs font-bold text-rose-700" hidden></span>
      </div>

      <!-- Header Config Card -->
      <section class="journey-card mb-6">
        <div class="plan-header-grid">
          <div class="form-group">
            <label class="form-label" for="planPrimaryInput">Phương án ưu tiên <span class="req-mark">*</span></label>
            <input id="planPrimaryInput" type="text" class="form-input" maxlength="120" value="<?=e((string)($initialPlan['primary_option']['label'] ?? ''))?>" placeholder="Ví dụ: Công nghệ thông tin — ĐHBK Hà Nội">
            <span id="planPrimaryTag" class="field-hint"></span>
          </div>

          <div class="form-group">
            <label class="form-label" for="planBackupInput">Phương án dự phòng (tùy chọn)</label>
            <input id="planBackupInput" type="text" class="form-input" maxlength="120" value="<?=e((string)($initialPlan['backup_option']['label'] ?? ''))?>" placeholder="Ví dụ: Khoa học dữ liệu">
          </div>

          <div class="form-group">
            <label class="form-label" for="planGradeSelect">Khối lớp hiện tại</label>
            <select id="planGradeSelect" class="form-select">
              <?php foreach (['Lớp 10', 'Lớp 11', 'Lớp 12'] as $gOpt): ?>
                <option value="<?=e($gOpt)?>"<?=$initialPlan['grade'] === $gOpt ? ' selected' : ''?>><?=e($gOpt)?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="planStartMonth">Tháng bắt đầu</label>
            <input id="planStartMonth" type="month" class="form-input" min="<?=e($currentMonth)?>" value="<?=e((string)$initialPlan['start_month'])?>">
          </div>

          <div class="form-group">
            <label class="form-label" for="planHorizonSelect">Mốc mục tiêu</label>
            <select id="planHorizonSelect" class="form-select">
              <option value="3_months"<?=$initialPlan['target_horizon'] === '3_months' ? ' selected' : ''?>>Trong 3 tháng</option>
              <option value="6_months"<?=$initialPlan['target_horizon'] === '6_months' ? ' selected' : ''?>>Trong 6 tháng</option>
              <option value="9_months"<?=$initialPlan['target_horizon'] === '9_months' ? ' selected' : ''?>>Trong 9 tháng</option>
              <option value="12_months"<?=$initialPlan['target_horizon'] === '12_months' ? ' selected' : ''?>>Trong 12 tháng</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="planHoursInput">Thời gian mỗi tuần (giờ, tùy chọn)</label>
            <input id="planHoursInput" type="number" min="1" max="40" class="form-input" value="<?=e((string)($initialPlan['hours_per_week'] ?? ''))?>" placeholder="Ví dụ: 4">
          </div>
        </div>

        <div class="plan-template-bar plan-no-print">
          <button type="button" id="planApplyTemplateBtn" class="btn btn-outline btn-sm">Tạo mốc mẫu theo khối lớp (tự lập)</button>
          <span class="text-xs text-slate-500">Phân biệt rõ <strong>Mốc tự đặt</strong> và <strong>Hạn chính thức (có nguồn)</strong> — hệ thống không tự bịa lịch tuyển sinh.</span>
        </div>

        <!-- Research items carried over from Comparison -->
        <div id="planResearchWrap" class="plan-research-box" <?=empty($initialPlan['research_items']) ? 'hidden' : ''?>>
          <strong class="text-xs text-slate-800">Điều bạn đánh dấu cần tìm hiểu thêm từ bước So sánh:</strong>
          <ul id="planResearchList" class="compare-contrib-list mt-1"></ul>
        </div>
      </section>

      <!-- AI Draft Preview Drawer (never silently overwrites edited tasks) -->
      <section id="planAiPreview" class="journey-card plan-ai-preview mb-6 plan-no-print" hidden>
        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
          <div>
            <span class="badge badge-purple">🧭 Bản nháp từ Trợ lý La Bàn</span>
            <p id="planAiNote" class="text-xs text-slate-600 mt-1"></p>
          </div>
          <button type="button" id="planAiCloseBtn" class="btn btn-ghost btn-sm">Đóng</button>
        </div>
        <div id="planAiPreviewList" class="space-y-2 mb-4"></div>
        <div class="flex flex-wrap gap-2">
          <button type="button" id="planAiAppendBtn" class="btn btn-primary btn-sm">Thêm vào kế hoạch (giữ nguyên các mốc đã sửa)</button>
          <button type="button" id="planAiReplaceUntouchedBtn" class="btn btn-outline btn-sm">Thay các mốc chưa tự sửa</button>
        </div>
      </section>

      <!-- Main Editable Milestones Table -->
      <section class="journey-card">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
          <div>
            <h2 class="text-lg font-bold text-slate-900">Các mốc hành động theo tháng/năm</h2>
            <p class="text-xs text-slate-500" id="planProgressSummary">0/0 mốc hoàn thành</p>
          </div>
          <div class="flex flex-wrap gap-2 plan-no-print">
            <button type="button" id="planSortBtn" class="btn btn-ghost btn-sm">↕ Sắp xếp theo thời gian</button>
            <button type="button" id="planAddItemBtn" class="btn btn-primary btn-sm">+ Thêm mốc mới</button>
          </div>
        </div>

        <div id="planItemsWrap" class="plan-items-list"></div>
      </section>

      <!-- Revocable Read-Only Share Controls -->
      <section class="journey-card mt-6 plan-no-print">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 class="text-base font-bold text-slate-900">Chia sẻ kế hoạch (liên kết chỉ đọc)</h3>
            <p class="text-xs text-slate-600 mt-0.5">Chỉ tạo liên kết khi bạn chủ động bật. Liên kết không chứa dữ liệu cá nhân trong URL và có thể thu hồi bất kỳ lúc nào.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button type="button" id="planShareToggleBtn" class="btn btn-outline btn-sm">Bật liên kết chỉ đọc</button>
          </div>
        </div>
        <div id="planShareBox" class="plan-share-box mt-3" hidden>
          <input id="planShareUrlInput" type="text" class="form-input" readonly aria-label="Liên kết chia sẻ chỉ đọc">
          <button type="button" id="planShareCopyBtn" class="btn btn-outline btn-sm">Sao chép link</button>
        </div>
      </section>
    </div>

    <script id="planBootstrapData" type="application/json"><?=json_encode([
        'plan' => $initialPlan,
        'current_month' => $currentMonth,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)?></script>
    <script src="<?=url('assets/js/plan.js')?>"></script>
    <?php
    require __DIR__ . '/footer.php';
}
