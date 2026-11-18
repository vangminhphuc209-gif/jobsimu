<?php
declare(strict_types=1);

/**
 * Step 3 / 4: "Hiểu mình cần gì" (20-question weighted decision comparison).
 * - Question bank, weights (total = 100), and version (compare_v1) separated from UI.
 * - Pure deterministic scoring: sum(scores) + unallocated === 100, never normalized up to 100.
 * - Zero AI involvement in scoring.
 */

const COMPARE_QUESTION_VERSION = 'compare_v1';

function compare_questions_catalog(): array
{
    return [
        ['id' => 1,  'weight' => 8, 'category' => 'Hứng thú',      'info_topic' => null,         'text' => 'Nếu được làm việc mỗi ngày, phương án nào khiến bạn hào hứng nhất?'],
        ['id' => 2,  'weight' => 7, 'category' => 'Tò mò tự nhiên', 'info_topic' => null,         'text' => 'Bạn muốn tự tìm hiểu thêm về phương án nào, ngay cả khi không ai nhắc?'],
        ['id' => 3,  'weight' => 6, 'category' => 'Công việc',      'info_topic' => 'work_env',   'text' => 'Bạn thích những công việc thường ngày của phương án nào hơn?'],
        ['id' => 4,  'weight' => 5, 'category' => 'Thế mạnh',       'info_topic' => null,         'text' => 'Ở phương án nào, bạn thấy mình có thể tận dụng tốt những điều đang làm tốt?'],
        ['id' => 5,  'weight' => 4, 'category' => 'Kiên trì',       'info_topic' => 'curriculum', 'text' => 'Nếu phải bắt đầu từ những kiến thức khó, bạn muốn kiên trì với phương án nào?'],
        ['id' => 6,  'weight' => 6, 'category' => 'Nội dung học',   'info_topic' => 'curriculum', 'text' => 'Nội dung học của phương án nào khiến bạn muốn thử sức nhất?'],
        ['id' => 7,  'weight' => 5, 'category' => 'Rèn luyện',      'info_topic' => null,         'text' => 'Với phương án nào, bạn sẵn sàng dành thời gian luyện tập mỗi tuần?'],
        ['id' => 8,  'weight' => 5, 'category' => 'Trải nghiệm',    'info_topic' => null,         'text' => 'Qua những gì đã tìm hiểu hoặc trải nghiệm, phương án nào gần với mong muốn của bạn nhất?'],
        ['id' => 9,  'weight' => 5, 'category' => 'Môi trường',     'info_topic' => 'work_env',   'text' => 'Môi trường học tập và làm việc của phương án nào khiến bạn thấy thoải mái hơn?'],
        ['id' => 10, 'weight' => 4, 'category' => 'Thách thức',     'info_topic' => 'work_env',   'text' => 'Những khó khăn thường gặp ở phương án nào là điều bạn sẵn sàng đối mặt hơn?'],
        ['id' => 11, 'weight' => 5, 'category' => 'Giá trị sống',   'info_topic' => null,         'text' => 'Phương án nào phù hợp hơn với điều bạn coi trọng trong cuộc sống?'],
        ['id' => 12, 'weight' => 4, 'category' => 'Đóng góp',       'info_topic' => null,         'text' => 'Phương án nào giúp bạn tạo ra đóng góp mà bạn thấy có ý nghĩa?'],
        ['id' => 13, 'weight' => 4, 'category' => 'Nhịp sống',      'info_topic' => 'work_env',   'text' => 'Nhịp sống và thời gian làm việc của phương án nào phù hợp với bạn hơn?'],
        ['id' => 14, 'weight' => 4, 'category' => 'Địa điểm',       'info_topic' => null,         'text' => 'Phương án nào phù hợp hơn với nơi bạn muốn học và sinh sống?'],
        ['id' => 15, 'weight' => 3, 'category' => 'Tự chủ',         'info_topic' => null,         'text' => 'Nếu không chịu áp lực từ kỳ vọng của người khác, bạn nghiêng về phương án nào?'],
        ['id' => 16, 'weight' => 6, 'category' => 'Chi phí',        'info_topic' => 'cost',       'text' => 'Sau khi xem học phí và chi phí sinh hoạt, phương án nào khả thi hơn với bạn?'],
        ['id' => 17, 'weight' => 5, 'category' => 'Lộ trình học',   'info_topic' => 'curriculum', 'text' => 'Với năng lực hiện tại và thời gian chuẩn bị, phương án nào có lộ trình học tập phù hợp hơn?'],
        ['id' => 18, 'weight' => 5, 'category' => 'Phát triển',     'info_topic' => null,         'text' => 'Phương án nào cho bạn những hướng phát triển tiếp theo mà bạn muốn theo đuổi?'],
        ['id' => 19, 'weight' => 5, 'category' => 'Linh hoạt',      'info_topic' => null,         'text' => 'Nếu dự định ban đầu thay đổi, phương án nào vẫn có những hướng đi khác bạn chấp nhận được?'],
        ['id' => 20, 'weight' => 4, 'category' => 'Hành động ngay', 'info_topic' => null,         'text' => 'Nếu bắt đầu một việc nhỏ ngay tuần này, bạn muốn làm việc đó cho phương án nào?'],
    ];
}

function compare_sanitize_options(array $rawOptions): array
{
    $clean = [];
    $seenIds = [];
    $seenCustomLabels = [];

    foreach ($rawOptions as $opt) {
        if (!is_array($opt)) continue;
        $label = trim(preg_replace('/\s+/u', ' ', (string)($opt['label'] ?? '')));
        if ($label === '') continue;
        $label = mb_substr($label, 0, 120);

        $type = in_array($opt['type'] ?? '', ['major', 'program', 'custom'], true) ? (string)$opt['type'] : 'custom';
        $rawId = trim((string)($opt['id'] ?? ''));
        if ($rawId === '' || !preg_match('/^[a-zA-Z0-9:_-]{2,80}$/', $rawId)) {
            $rawId = 'custom:' . substr(hash('sha256', $label . '|' . count($clean)), 0, 12);
        }
        if (isset($seenIds[$rawId])) continue;

        if ($type === 'custom') {
            $normLabel = mb_strtolower($label, 'UTF-8');
            if (isset($seenCustomLabels[$normLabel])) continue;
            $seenCustomLabels[$normLabel] = true;
        }

        $seenIds[$rawId] = true;
        $clean[] = [
            'id' => $rawId,
            'label' => $label,
            'type' => $type,
            'major_id' => !empty($opt['major_id']) && is_numeric($opt['major_id']) ? (int)$opt['major_id'] : null,
            'program_id' => !empty($opt['program_id']) && is_numeric($opt['program_id']) ? (int)$opt['program_id'] : null,
        ];

        if (count($clean) >= 7) break;
    }

    return $clean;
}

/**
 * Pure deterministic scoring engine.
 * Invariant: sum(option.score) + unallocated === 100.
 * Never normalizes answered scores up to 100.
 */
function compare_score_session(array $options, array $answers, ?array $questions = null): array
{
    $qs = $questions ?? compare_questions_catalog();
    $totalWeight = 0;
    foreach ($qs as $q) {
        $totalWeight += (int)$q['weight'];
    }

    $optionMap = [];
    $scores = [];
    $contributions = [];
    foreach ($options as $opt) {
        $oid = (string)$opt['id'];
        $optionMap[$oid] = $opt;
        $scores[$oid] = 0;
        $contributions[$oid] = [];
    }

    $answeredCount = 0;
    $answeredWeight = 0;
    $unallocated = 0;
    $skippedQuestions = [];

    foreach ($qs as $q) {
        $qid = (int)$q['id'];
        $w = (int)$q['weight'];
        $chosen = $answers[$qid] ?? ($answers[(string)$qid] ?? null);
        $chosenStr = is_scalar($chosen) ? trim((string)$chosen) : '';

        if ($chosenStr !== '' && isset($optionMap[$chosenStr])) {
            $scores[$chosenStr] += $w;
            $contributions[$chosenStr][] = [
                'question_id' => $qid,
                'weight' => $w,
                'category' => (string)$q['category'],
                'text' => (string)$q['text'],
            ];
            $answeredCount++;
            $answeredWeight += $w;
        } else {
            $unallocated += $w;
            $skippedQuestions[] = [
                'question_id' => $qid,
                'weight' => $w,
                'category' => (string)$q['category'],
                'text' => (string)$q['text'],
            ];
        }
    }

    $allSkipped = ($answeredWeight === 0);
    $isPreliminary = (!$allSkipped && $answeredWeight < 70);

    $ranked = [];
    foreach ($options as $idx => $opt) {
        $oid = (string)$opt['id'];
        $ranked[] = [
            'id' => $oid,
            'label' => (string)$opt['label'],
            'type' => (string)$opt['type'],
            'major_id' => $opt['major_id'] ?? null,
            'program_id' => $opt['program_id'] ?? null,
            'score' => $scores[$oid],
            'contributions' => $contributions[$oid],
            'original_order' => $idx,
            'rank' => null,
        ];
    }

    $closeRace = false;
    $topGap = null;

    if (!$allSkipped) {
        usort($ranked, static function (array $a, array $b): int {
            if ($b['score'] !== $a['score']) {
                return $b['score'] <=> $a['score'];
            }
            return $a['original_order'] <=> $b['original_order'];
        });

        // Competition ranking (tied scores share the exact same rank)
        $prevScore = null;
        $currentRank = 0;
        foreach ($ranked as $i => &$row) {
            if ($prevScore === null || $row['score'] < $prevScore) {
                $currentRank = $i + 1;
                $prevScore = $row['score'];
            }
            $row['rank'] = $currentRank;
        }
        unset($row);

        if (count($ranked) >= 2) {
            $topGap = $ranked[0]['score'] - $ranked[1]['score'];
            $closeRace = ($topGap <= 5);
        }
    }

    $scoreSum = array_sum($scores);

    return [
        'version' => COMPARE_QUESTION_VERSION,
        'total_weight' => $totalWeight,
        'answered_count' => $answeredCount,
        'total_questions' => count($qs),
        'answered_weight' => $answeredWeight,
        'unallocated' => $unallocated,
        'invariant_ok' => (($scoreSum + $unallocated) === $totalWeight),
        'all_skipped' => $allSkipped,
        'is_preliminary' => $isPreliminary,
        'close_race' => $closeRace,
        'top_gap' => $topGap,
        'ranked_options' => $ranked,
        'skipped_questions' => $skippedQuestions,
    ];
}

function compare_load_reference_snippets(array $options, ?PDO $pdo = null): array
{
    $conn = $pdo ?? db();
    $out = [];
    foreach ($options as $opt) {
        $oid = (string)$opt['id'];
        $type = (string)($opt['type'] ?? 'custom');
        if ($type === 'custom') {
            $out[$oid] = [
                'has_source' => false,
                'curriculum' => 'Phương án tự nhập — chưa gắn với dữ liệu đào tạo nào. Bạn có thể lưu câu hỏi cần tìm hiểu để kiểm tra sau.',
                'work_env' => 'Phương án tự nhập — chưa có mô tả môi trường làm việc từ thư viện.',
                'cost' => 'Phương án tự nhập — chưa có dữ liệu học phí có nguồn.',
            ];
            continue;
        }

        $majorId = !empty($opt['major_id']) ? (int)$opt['major_id'] : 0;
        $progId = !empty($opt['program_id']) ? (int)$opt['program_id'] : 0;
        $curriculum = 'Chưa có dữ liệu chương trình học chi tiết.';
        $workEnv = 'Chưa có mô tả môi trường làm việc cụ thể.';
        $cost = 'Chưa có dữ liệu học phí xác minh.';
        $hasSource = false;

        if ($majorId > 0) {
            $mStmt = $conn->prepare('SELECT name, code, description FROM majors WHERE id = ?');
            $mStmt->execute([$majorId]);
            $m = $mStmt->fetch(PDO::FETCH_ASSOC);
            if ($m && !empty($m['description'])) {
                $curriculum = $m['description'];
                $hasSource = true;
            }
            $cStmt = $conn->prepare('SELECT c.title, c.work_environment, c.skills FROM career_major_paths cmp JOIN careers c ON c.id=cmp.career_id WHERE cmp.major_id=? LIMIT 2');
            $cStmt->execute([$majorId]);
            $cs = $cStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($cs)) {
                $envs = array_filter(array_map(static fn($x) => $x['work_environment'] ?: '', $cs));
                if (!empty($envs)) {
                    $workEnv = implode(' · ', $envs);
                    $hasSource = true;
                }
            }
        }

        if ($progId > 0) {
            $pStmt = $conn->prepare('SELECT p.institution_id, i.name AS inst_name FROM programs p JOIN institutions i ON i.id=p.institution_id WHERE p.id=?');
            $pStmt->execute([$progId]);
            $p = $pStmt->fetch(PDO::FETCH_ASSOC);
            if ($p) {
                $tStmt = $conn->prepare('SELECT academic_year, program_group, minimum_vnd, maximum_vnd, source_title FROM institution_tuition_bands WHERE institution_id=? ORDER BY academic_year DESC LIMIT 2');
                $tStmt->execute([(int)$p['institution_id']]);
                $bands = $tStmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($bands)) {
                    $parts = [];
                    foreach ($bands as $b) {
                        $minM = number_format((float)$b['minimum_vnd'] / 1000000, 1);
                        $maxM = number_format((float)$b['maximum_vnd'] / 1000000, 1);
                        $parts[] = "{$b['program_group']} ({$b['academic_year']}): {$minM}–{$maxM} triệu/học kỳ [{$b['source_title']}]";
                    }
                    $cost = implode('; ', $parts);
                    $hasSource = true;
                }
            }
        }

        $out[$oid] = [
            'has_source' => $hasSource,
            'curriculum' => $curriculum,
            'work_env' => $workEnv,
            'cost' => $cost,
        ];
    }
    return $out;
}

function compare_load_by_uid(string $uid, ?array $me, ?PDO $pdo = null): ?array
{
    if ($uid === '' || !preg_match('/^[a-f0-9-]{8,36}$/i', $uid)) return null;
    $conn = $pdo ?? db();
    $stmt = $conn->prepare('SELECT * FROM user_comparisons WHERE uid = ? LIMIT 1');
    $stmt->execute([$uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    // Enforce account isolation: never expose another user's comparison
    if ($row['user_id'] !== null) {
        if (!$me || (int)$row['user_id'] !== (int)$me['id']) {
            return null;
        }
    } else {
        $guestUids = is_array($_SESSION['guest_comparison_uids'] ?? null) ? $_SESSION['guest_comparison_uids'] : [];
        if (!in_array($uid, $guestUids, true)) {
            return null;
        }
    }

    return [
        'id' => (int)$row['id'],
        'uid' => (string)$row['uid'],
        'question_version' => (string)$row['question_version'],
        'options' => json_decode((string)$row['options_json'], true) ?: [],
        'answers' => json_decode((string)$row['answers_json'], true) ?: [],
        'research_notes' => json_decode((string)$row['research_notes_json'], true) ?: [],
        'result' => json_decode((string)$row['result_json'], true) ?: null,
        'primary_option_id' => $row['primary_option_id'] ? (string)$row['primary_option_id'] : null,
        'backup_option_id' => $row['backup_option_id'] ? (string)$row['backup_option_id'] : null,
        'status' => (string)$row['status'],
        'updated_at' => (string)$row['updated_at'],
    ];
}

function compare_load_latest_for_user(?array $me, ?PDO $pdo = null): ?array
{
    if (!$me || empty($me['id'])) return null;
    $conn = $pdo ?? db();
    $stmt = $conn->prepare('SELECT uid FROM user_comparisons WHERE user_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1');
    $stmt->execute([(int)$me['id']]);
    $uid = $stmt->fetchColumn();
    return $uid ? compare_load_by_uid((string)$uid, $me, $conn) : null;
}

function compare_handle_api(): void
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

    if ($action === 'info') {
        $opts = compare_sanitize_options(is_array($payload['options'] ?? null) ? $payload['options'] : []);
        echo json_encode(['ok' => true, 'info' => compare_load_reference_snippets($opts, $conn)], JSON_UNESCAPED_UNICODE);
        return;
    }

    $options = compare_sanitize_options(is_array($payload['options'] ?? null) ? $payload['options'] : []);
    if (count($options) < 2) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Cần ít nhất 2 phương án để so sánh.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $rawAnswers = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];
    $validOptionIds = array_column($options, 'id');
    $cleanAnswers = [];
    foreach ($rawAnswers as $qKey => $optId) {
        $qid = (int)$qKey;
        if ($qid < 1 || $qid > 20) continue;
        $oid = is_scalar($optId) ? trim((string)$optId) : '';
        if ($oid !== '' && in_array($oid, $validOptionIds, true)) {
            $cleanAnswers[$qid] = $oid;
        }
    }

    $rawNotes = is_array($payload['research_notes'] ?? null) ? $payload['research_notes'] : [];
    $cleanNotes = [];
    foreach ($rawNotes as $n) {
        $txt = trim(mb_substr((string)$n, 0, 220));
        if ($txt !== '' && !in_array($txt, $cleanNotes, true)) {
            $cleanNotes[] = $txt;
        }
        if (count($cleanNotes) >= 20) break;
    }

    $result = compare_score_session($options, $cleanAnswers);
    $status = in_array($payload['status'] ?? '', ['draft', 'completed', 'confirmed'], true) ? (string)$payload['status'] : 'draft';

    $primaryId = is_scalar($payload['primary_option_id'] ?? null) ? trim((string)$payload['primary_option_id']) : '';
    $backupId = is_scalar($payload['backup_option_id'] ?? null) ? trim((string)$payload['backup_option_id']) : '';
    if ($primaryId !== '' && !in_array($primaryId, $validOptionIds, true)) $primaryId = '';
    if ($backupId !== '' && (!in_array($backupId, $validOptionIds, true) || $backupId === $primaryId)) $backupId = '';

    if ($status === 'confirmed' && $primaryId === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Vui lòng chọn phương án ưu tiên trước khi lập kế hoạch.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $uid = is_scalar($payload['uid'] ?? null) ? trim((string)$payload['uid']) : '';
    $existing = $uid !== '' ? compare_load_by_uid($uid, $me, $conn) : null;

    $optionsJson = json_encode($options, JSON_UNESCAPED_UNICODE);
    $answersJson = json_encode($cleanAnswers, JSON_UNESCAPED_UNICODE);
    $notesJson = json_encode($cleanNotes, JSON_UNESCAPED_UNICODE);
    $resultJson = json_encode($result, JSON_UNESCAPED_UNICODE);

    if ($existing) {
        $stmt = $conn->prepare('UPDATE user_comparisons SET options_json=?, answers_json=?, research_notes_json=?, result_json=?, primary_option_id=?, backup_option_id=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE uid=?');
        $stmt->execute([
            $optionsJson,
            $answersJson,
            $notesJson,
            $resultJson,
            $primaryId !== '' ? $primaryId : null,
            $backupId !== '' ? $backupId : null,
            $status,
            $existing['uid'],
        ]);
        $savedUid = $existing['uid'];
    } else {
        $savedUid = journey_uuid4();
        $stmt = $conn->prepare('INSERT INTO user_comparisons(uid, user_id, question_version, options_json, answers_json, research_notes_json, result_json, primary_option_id, backup_option_id, status) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $savedUid,
            $me ? (int)$me['id'] : null,
            COMPARE_QUESTION_VERSION,
            $optionsJson,
            $answersJson,
            $notesJson,
            $resultJson,
            $primaryId !== '' ? $primaryId : null,
            $backupId !== '' ? $backupId : null,
            $status,
        ]);
        if (!$me) {
            if (!is_array($_SESSION['guest_comparison_uids'] ?? null)) {
                $_SESSION['guest_comparison_uids'] = [];
            }
            $_SESSION['guest_comparison_uids'][] = $savedUid;
        }
    }

    echo json_encode([
        'ok' => true,
        'uid' => $savedUid,
        'status' => $status,
        'options' => $options,
        'answers' => $cleanAnswers,
        'research_notes' => $cleanNotes,
        'result' => $result,
        'plan_url' => url('index.php?page=plan&comparison_id=' . urlencode($savedUid)),
    ], JSON_UNESCAPED_UNICODE);
}

function compare_render_page(): void
{
    $me = user();
    $conn = db();
    $uidParam = is_string($_GET['uid'] ?? null) ? trim($_GET['uid']) : '';
    $saved = $uidParam !== '' ? compare_load_by_uid($uidParam, $me, $conn) : compare_load_latest_for_user($me, $conn);

    $majors = $conn->query('SELECT m.id, m.code, m.name, COALESCE(GROUP_CONCAT(DISTINCT cg.name SEPARATOR ", "), "") AS group_name FROM majors m LEFT JOIN career_major_paths cmp ON cmp.major_id=m.id LEFT JOIN careers c ON c.id=cmp.career_id LEFT JOIN career_groups cg ON cg.id=c.career_group_id GROUP BY m.id, m.code, m.name ORDER BY m.name ASC')->fetchAll(PDO::FETCH_ASSOC);

    $userScope = $me ? ('u' . (int)$me['id']) : 'guest';
    $storageKey = 'jobsimu_compare_v1_' . $userScope;
    $basketKey = 'jobsimu_compare_basket_' . $userScope;

    $pageTitle = 'Hiểu mình cần gì · So sánh lựa chọn';
    require __DIR__ . '/header.php';
    ?>
    <section class="page-head journey-head">
      <div class="container journey-narrow">
        <div class="journey-step-row">
          <span class="journey-step-pill">BƯỚC 3 / 4</span>
          <span class="journey-step-note">20 câu hỏi trọng số · Tổng thang 100 điểm</span>
        </div>
        <h1 class="page-title">Hiểu mình cần gì</h1>
        <p class="page-subtitle">Đặt các lựa chọn cạnh nhau để nhìn rõ điều bạn ưu tiên.</p>
        <p class="text-xs text-slate-500 mt-2">
          Thang 100 điểm ưu tiên được lưu riêng, không thay đổi hay quy đổi sang điểm bài đánh giá chuẩn hóa (Holland, MBTI, Big Five, EQ, CAAS).
          Muốn đối chiếu trực tiếp chỉ tiêu/học phí/điểm chuẩn giữa các trường?
          <a href="<?=url('index.php?page=university-compare')?>" class="font-semibold text-brand-600 hover:underline">Mở So sánh trường đại học →</a>
        </p>
      </div>
    </section>

    <div
      class="container journey-narrow py-8"
      id="compareApp"
      data-api-url="<?=e(url('index.php?page=compare-api'))?>"
      data-storage-key="<?=e($storageKey)?>"
      data-basket-key="<?=e($basketKey)?>"
      data-logged-in="<?=$me ? '1' : '0'?>"
      data-csrf="<?=e(csrf_token())?>"
      data-plan-base="<?=e(url('index.php?page=plan'))?>"
      data-lookup-url="<?=e(url('index.php?page=lookup'))?>"
      data-sim-url="<?=e(url('index.php?page=simulations'))?>"
    >
      <!-- STEP A: CHỌN PHƯƠNG ÁN -->
      <section id="compareStepPicker" class="journey-card">
        <div class="mb-5">
          <h2 class="text-xl font-black text-slate-900">Bước A · Chọn các phương án bạn đang phân vân</h2>
          <p class="text-sm text-slate-600 mt-1">Cần ít nhất 2 phương án để bắt đầu (khuyến nghị 2–4 để dễ cân nhắc; tối đa 7 phương án). Hai chương trình cùng ngành ở hai trường khác nhau đều là hai phương án hợp lệ.</p>
        </div>

        <!-- Selected options manager -->
        <div class="compare-selected-box">
          <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
            <strong class="text-sm text-slate-900" id="compareSelectedCount">Đã chọn 0 / 7 phương án</strong>
            <span class="text-xs text-slate-500">Bạn có thể đổi tên hiển thị hoặc xóa trước khi bắt đầu</span>
          </div>
          <div id="compareSelectedList" class="compare-selected-list"></div>
          <p id="compareLimitMsg" class="field-error" hidden></p>
        </div>

        <!-- 1. Add from existing majors -->
        <div class="mt-6">
          <label class="form-label" for="compareMajorSearch">1. Chọn ngành có sẵn trên JobSimu</label>
          <input id="compareMajorSearch" type="search" class="form-input" placeholder="Tìm nhanh tên ngành hoặc mã ngành (có dấu hoặc không dấu)...">
          <div id="compareMajorList" class="compare-major-picker mt-3"></div>
        </div>

        <!-- 2. Add custom option -->
        <div class="mt-6 pt-5 border-t border-slate-200">
          <label class="form-label" for="compareCustomInput">2. Hoặc tự nhập phương án của bạn</label>
          <div class="compare-custom-row">
            <input id="compareCustomInput" type="text" class="form-input" maxlength="120" placeholder="Ví dụ: Ngôn ngữ Anh — trường mình đang tìm hiểu">
            <button type="button" id="compareCustomAddBtn" class="btn btn-outline">Thêm phương án</button>
          </div>
          <p id="compareCustomHint" class="field-hint">Phương án tự nhập sẽ không tự động gắn dữ liệu tuyển sinh khi chưa được bạn xác nhận.</p>
          <p id="compareCustomError" class="field-error" role="alert" hidden></p>
          <p id="compareSchoolOnlyTip" class="compare-soft-tip" hidden></p>
        </div>

        <div class="mt-6 pt-5 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
          <p id="compareStartReason" class="text-xs font-semibold text-slate-500">Cần chọn ít nhất 2 phương án để bắt đầu trả lời 20 câu hỏi.</p>
          <button type="button" id="compareStartBtn" class="btn btn-primary btn-lg" disabled>Bắt đầu trả lời 20 câu hỏi →</button>
        </div>
      </section>

      <!-- STEP B: 20 CÂU HỎI -->
      <section id="compareStepQuiz" class="journey-card" hidden>
        <div class="journey-progress-head">
          <strong id="compareQuizProgress">Câu 1/20</strong>
          <span id="compareQuizWeight" class="compare-weight-pill">Trọng số: 8 điểm / 100</span>
        </div>
        <div class="journey-progress-track" role="progressbar" aria-valuemin="1" aria-valuemax="20" aria-valuenow="1" id="compareProgressWrap">
          <div id="compareProgressFill" class="journey-progress-fill" style="width:5%"></div>
        </div>

        <div id="compareQuizMetaRow" class="flex flex-wrap items-center justify-between gap-2 mt-3">
          <span id="compareQuizCategory" class="badge badge-subtle">Hứng thú</span>
          <span id="compareDraftStatus" class="journey-save-hint">
            <?=$me ? 'Bản nháp được lưu vào tài khoản của bạn' : 'Bản nháp được lưu tạm trên trình duyệt này'?>
          </span>
        </div>

        <h2 id="compareQuestionText" class="compare-q-title"></h2>

        <!-- Optional grounded reference drawer for cost/curriculum/work_env -->
        <div id="compareInfoWrap" class="compare-info-box" hidden>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-xs font-semibold text-slate-700">Chưa rõ thông tin để so sánh ở câu này?</span>
            <div class="flex flex-wrap gap-2">
              <button type="button" id="compareToggleInfoBtn" class="btn btn-outline btn-sm">Xem thông tin nguồn</button>
              <button type="button" id="compareSaveResearchBtn" class="btn btn-ghost btn-sm">Lưu câu hỏi cần tìm hiểu &amp; bỏ qua</button>
            </div>
          </div>
          <div id="compareInfoBody" class="compare-info-body" hidden></div>
        </div>

        <div id="compareQuizOptions" class="compare-quiz-options" role="radiogroup" aria-labelledby="compareQuestionText"></div>

        <div class="journey-actions-bar">
          <button type="button" id="comparePrevBtn" class="btn btn-outline">← Quay lại</button>
          <div class="journey-actions-right">
            <button type="button" id="compareSkipBtn" class="btn btn-ghost">Mình chưa rõ, bỏ qua</button>
            <button type="button" id="compareNextBtn" class="btn btn-primary" disabled>Tiếp tục →</button>
          </div>
        </div>

        <div class="mt-5 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
          <button type="button" id="compareEditOptionsBtn" class="text-slate-600 hover:text-brand-600 font-semibold underline">Thay đổi danh sách phương án</button>
          <span class="text-slate-400">Điểm được tính lại từ dữ liệu gốc khi bạn sửa câu trả lời.</span>
        </div>
      </section>

      <!-- STEP D: KẾT QUẢ & XÁC NHẬN -->
      <section id="compareStepResult" class="journey-card" hidden>
        <div class="mb-5">
          <span class="badge badge-purple">Tổng hợp ưu tiên cá nhân</span>
          <h2 class="text-2xl font-black text-slate-900 mt-2">Kết quả “Hiểu mình cần gì”</h2>
          <p class="text-sm text-slate-600 mt-1">
            Đây là <strong>điểm ưu tiên tương đối</strong> trong lần so sánh này (trên tổng 100 điểm trọng số), không phải phần trăm hợp nghề, đánh giá năng lực hay xác suất trúng tuyển.
          </p>
        </div>

        <div id="compareResultNotices" class="space-y-2 mb-5"></div>
        <div id="compareResultStats" class="compare-stats-row mb-5"></div>
        <div id="compareResultBars" class="space-y-4 mb-6"></div>

        <!-- Contributions & Skipped Criteria -->
        <div id="compareBreakdownWrap" class="space-y-4 mb-6"></div>

        <!-- User Explicitly Confirms Primary & Backup Options -->
        <div class="compare-confirm-box" id="compareConfirmBox">
          <h3 class="text-base font-bold text-slate-900">Xác nhận lựa chọn của bạn để lập kế hoạch</h3>
          <p class="text-xs text-slate-600 mt-1">Hệ thống chỉ gợi ý theo điểm ưu tiên; bạn mới là người quyết định phương án ưu tiên và phương án dự phòng.</p>

          <div class="compare-confirm-grid mt-4">
            <div class="form-group">
              <label class="form-label" for="comparePrimarySelect">Phương án ưu tiên <span class="req-mark">*</span></label>
              <select id="comparePrimarySelect" class="form-select"></select>
            </div>
            <div class="form-group">
              <label class="form-label" for="compareBackupSelect">Phương án dự phòng (tùy chọn)</label>
              <select id="compareBackupSelect" class="form-select"></select>
            </div>
          </div>

          <p id="compareConfirmError" class="field-error" role="alert" hidden></p>

          <div class="mt-5 flex flex-wrap items-center gap-3">
            <button type="button" id="compareGoPlanBtn" class="btn btn-primary btn-lg">Lập kế hoạch cho lựa chọn này →</button>
            <button type="button" id="compareReviewAnswersBtn" class="btn btn-outline">Xem / sửa câu trả lời</button>
            <a class="btn btn-ghost" href="<?=url('index.php?page=lookup')?>">Tìm hiểu thêm</a>
            <a class="btn btn-ghost" href="<?=url('index.php?page=simulations')?>">Trải nghiệm nghề</a>
          </div>
        </div>
      </section>
    </div>

    <script id="compareBootstrapData" type="application/json"><?=json_encode([
        'version' => COMPARE_QUESTION_VERSION,
        'questions' => compare_questions_catalog(),
        'majors' => $majors,
        'saved' => $saved,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)?></script>
    <script src="<?=url('assets/js/compare.js')?>"></script>
    <?php
    require __DIR__ . '/footer.php';
}
