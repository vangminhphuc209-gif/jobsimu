<?php
declare(strict_types=1);

/**
 * Cumulative Assessment Feedback & Multi-Dimensional Synthesis Engine (v3.1.0).
 *
 * Provides:
 * - 5-Step Canonical Assessment Journey: Holland -> Big Five -> CAAS -> MBTI -> EQ
 * - Cumulative Profile Synthesis across tests (New Info, Continued Patterns, Supplementary Angles, Next Verifications)
 * - Single Result Screen with 8 mandatory sections (A to H) for every instrument
 * - Academic Condition Mapping & Subject Combination Scoring (A00, A01, B00, C00, D01, D07, X06, etc.)
 * - 5-Instrument Comprehensive Synthesis Report with real Admission Cutoffs & Training Majors (358 majors)
 * - Deterministic, scientifically grounded, strictly non-LLM, preserving all raw scores and legacy data.
 */

require_once __DIR__ . '/psychometrics.php';
require_once __DIR__ . '/recommendations.php';
require_once __DIR__ . '/four-hubs-data.php';

/**
 * Ensure additive tables exist idempotently on MySQL and SQLite.
 */
function assessment_feedback_ensure_tables(?PDO $pdo = null): void
{
    static $ensured = false;
    if ($ensured) return;
    $db = $pdo ?? db();
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS user_academic_profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            grade_level VARCHAR(20) NOT NULL DEFAULT 'Lớp 12',
            academic_year VARCHAR(20) NOT NULL DEFAULT '2024-2025',
            semester VARCHAR(20) NOT NULL DEFAULT 'sem1',
            subject_scores_json TEXT NOT NULL,
            target_region VARCHAR(50) NOT NULL DEFAULT 'all',
            budget_yearly VARCHAR(50) NOT NULL DEFAULT 'unknown',
            notes TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        $db->exec('CREATE INDEX IF NOT EXISTS idx_academic_user ON user_academic_profiles(user_id)');

        $db->exec("CREATE TABLE IF NOT EXISTS user_assessment_reflections (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            test_type VARCHAR(32) NOT NULL,
            assessment_id INTEGER,
            fit_feeling TEXT NOT NULL DEFAULT 'agree' CHECK(fit_feeling IN ('agree','partially_agree','disagree')),
            student_notes TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        $db->exec('CREATE INDEX IF NOT EXISTS idx_refl_user_test ON user_assessment_reflections(user_id, test_type)');
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS `user_academic_profiles` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `grade_level` VARCHAR(20) NOT NULL DEFAULT 'Lớp 12',
            `academic_year` VARCHAR(20) NOT NULL DEFAULT '2024-2025',
            `semester` VARCHAR(20) NOT NULL DEFAULT 'sem1',
            `subject_scores_json` JSON NOT NULL,
            `target_region` VARCHAR(50) NOT NULL DEFAULT 'all',
            `budget_yearly` VARCHAR(50) NOT NULL DEFAULT 'unknown',
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_academic_user` (`user_id`),
            CONSTRAINT `fk_academic_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `user_assessment_reflections` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `test_type` VARCHAR(32) NOT NULL,
            `assessment_id` INT UNSIGNED NULL,
            `fit_feeling` ENUM('agree', 'partially_agree', 'disagree') NOT NULL DEFAULT 'agree',
            `student_notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_reflection_user_test` (`user_id`, `test_type`),
            CONSTRAINT `fk_reflection_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $ensured = true;
}

/**
 * Return definition of the 5 canonical assessments in exact journey order.
 */
function assessment_journey_steps(): array
{
    return [
        'holland' => [
            'step_number' => 1,
            'key' => 'holland',
            'title' => 'Sở thích & Thế mạnh nghề nghiệp',
            'short_name' => 'Holland RIASEC',
            'tool_name' => 'Khảo sát Holland RIASEC & O*NET',
            'theoretical_model' => 'Lý thuyết Lục giác Sở thích John Holland & Chuẩn hóa O*NET',
            'items_count' => '30 câu RIASEC',
            'duration' => '⏱ 5–7 phút',
            'route' => 'assessment',
            'result_route' => 'assessment-result&type=holland',
            'icon' => '🧭',
            'color' => '#8B5CF6',
            'accent_bg' => 'bg-purple-50',
            'accent_border' => 'border-purple-200',
            'accent_text' => 'text-purple-700',
            'summary_intro' => 'Nhận diện 6 nhóm sở thích nghề nghiệp (R-I-A-S-E-C) và mức độ tương đồng với các nhóm hoạt động đời thực.',
        ],
        'big5' => [
            'step_number' => 2,
            'key' => 'big5',
            'title' => 'Phong cách làm việc & Tính cách',
            'short_name' => 'Nhân cách Big Five',
            'tool_name' => 'Mô hình Big Five (Mini-IPIP 20 câu)',
            'theoretical_model' => 'Five-Factor Model (Goldberg / Donnellan et al., 2006)',
            'items_count' => '20 câu thuận/đảo',
            'duration' => '⏱ 4–5 phút',
            'route' => 'test-big5',
            'result_route' => 'assessment-result&type=big5',
            'icon' => '🧩',
            'color' => '#3B82F6',
            'accent_bg' => 'bg-blue-50',
            'accent_border' => 'border-blue-200',
            'accent_text' => 'text-blue-700',
            'summary_intro' => 'Tìm hiểu 5 nét tính cách cốt lõi: Cởi mở, Kỷ luật, Hướng ngoại, Hòa đồng và Vững vàng cảm xúc trong học tập và công việc.',
        ],
        'caas' => [
            'step_number' => 3,
            'key' => 'caas',
            'title' => 'Khả năng Thích ứng nghề nghiệp',
            'short_name' => 'Thích ứng CAAS',
            'tool_name' => 'Thang đo Thích ứng nghề nghiệp CAAS-SF',
            'theoretical_model' => 'Career Construction Theory (Savickas & Porfeli, 2012)',
            'items_count' => '12 câu đo lường 4 nguồn lực',
            'duration' => '⏱ 2–3 phút',
            'route' => 'test-caas',
            'result_route' => 'assessment-result&type=caas',
            'icon' => '🌱',
            'color' => '#10B981',
            'accent_bg' => 'bg-emerald-50',
            'accent_border' => 'border-emerald-200',
            'accent_text' => 'text-emerald-700',
            'summary_intro' => 'Đo lường 4 nguồn lực tâm lý giúp bạn làm chủ tương lai: Quan tâm, Tự chủ, Tò mò và Tự tin vượt khó.',
        ],
        'mbti' => [
            'step_number' => 4,
            'key' => 'mbti',
            'title' => 'Xu hướng tương tác & Ra quyết định',
            'short_name' => 'MBTI rút gọn',
            'tool_name' => 'MBTI Tự phản tư (4 trục tâm lý)',
            'theoretical_model' => 'Khung phân loại tâm lý Myers-Briggs (Self-reflective 4-axis)',
            'items_count' => '12 câu phản tư',
            'duration' => '⏱ 3 phút',
            'route' => 'test-mbti',
            'result_route' => 'assessment-result&type=mbti',
            'icon' => '💡',
            'color' => '#F59E0B',
            'accent_bg' => 'bg-amber-50',
            'accent_border' => 'border-amber-200',
            'accent_text' => 'text-amber-700',
            'summary_intro' => 'Nhận diện thói quen nạp năng lượng (E-I), tiếp nhận dữ liệu (S-N), phong cách ra quyết định (T-F) và tổ chức kế hoạch (J-P).',
        ],
        'eq' => [
            'step_number' => 5,
            'key' => 'eq',
            'title' => 'Cảm xúc & Động lực bản thân',
            'short_name' => 'Chỉ số cảm xúc EQ',
            'tool_name' => 'Trắc nghiệm cảm xúc EQ (4 góc phản tư)',
            'theoretical_model' => 'Trí tuệ cảm xúc tự phản tư (Mô hình năng lực Salovey & Mayer)',
            'items_count' => '10 tình huống thực tế',
            'duration' => '⏱ 2–3 phút',
            'route' => 'test-eq',
            'result_route' => 'assessment-result&type=eq',
            'icon' => '❤️',
            'color' => '#EC4899',
            'accent_bg' => 'bg-rose-50',
            'accent_border' => 'border-rose-200',
            'accent_text' => 'text-rose-700',
            'summary_intro' => 'Đánh giá khả năng nhận diện cảm xúc, tự điều chỉnh khi gặp áp lực, thấu cảm với bạn bè và giữ vững động lực học tập.',
        ],
    ];
}

/**
 * Return current student progress across all 5 assessments.
 */
function assessment_get_journey_progress(int $userId): array
{
    assessment_feedback_ensure_tables();
    $steps = assessment_journey_steps();

    // 1. Holland from assessments table
    $stmt = db()->prepare('SELECT * FROM assessments WHERE user_id=? ORDER BY completed_at DESC, id DESC LIMIT 1');
    $stmt->execute([$userId]);
    $latestHolland = $stmt->fetch() ?: null;

    // 2-5. Psychometrics from user_psychometrics table
    $latestPsych = four_hub_latest_psychometrics($userId);

    $completedCount = 0;
    $stepStatus = [];
    $nextStep = null;

    foreach ($steps as $key => $meta) {
        $isDone = false;
        $completedAt = null;
        $resultCode = '';
        $summaryLabel = '';
        $validityFlag = 'valid';
        $recordId = null;
        $data = null;

        if ($key === 'holland') {
            if ($latestHolland && !empty($latestHolland['constraint_answers'])) {
                $isDone = true;
                $completedAt = $latestHolland['completed_at'];
                $resultCode = (string)$latestHolland['result_code'];
                $summaryLabel = 'Mã Holland: ' . $resultCode;
                $validityMetrics = json_decode($latestHolland['validity_metrics'] ?? '{}', true) ?: [];
                $validityFlag = $validityMetrics['validity_flag'] ?? 'valid';
                $recordId = (int)$latestHolland['id'];
                $data = $latestHolland;
            }
        } else {
            if (!empty($latestPsych[$key])) {
                $row = $latestPsych[$key];
                $isDone = true;
                $completedAt = $row['completed_at'];
                $resultCode = (string)($row['result_code'] ?? '');
                $summaryLabel = (string)($row['summary_label'] ?? '');
                $validityFlag = (string)($row['validity_flag'] ?? 'valid');
                $recordId = (int)($row['id'] ?? 0);
                $data = $row;
            }
        }

        if ($isDone) {
            $completedCount++;
        } elseif ($nextStep === null) {
            $nextStep = $meta;
        }

        $stepStatus[$key] = array_merge($meta, [
            'is_completed' => $isDone,
            'completed_at' => $completedAt,
            'result_code' => $resultCode,
            'summary_label' => $summaryLabel,
            'validity_flag' => $validityFlag,
            'record_id' => $recordId,
            'data' => $data,
        ]);
    }

    return [
        'completed_count' => $completedCount,
        'total_count' => 5,
        'completion_percent' => (int)round(($completedCount / 5) * 100),
        'all_completed' => ($completedCount === 5),
        'steps' => $stepStatus,
        'next_step' => $nextStep,
        'latest_holland' => $latestHolland,
        'latest_psych' => $latestPsych,
    ];
}

/**
 * Render visual progress tracker component for the 5-step journey.
 */
function assessment_render_journey_tracker(array $progress, string $currentStepKey = ''): string
{
    $completedCount = $progress['completed_count'];
    $steps = $progress['steps'];

    ob_start();
    ?>
    <section class="card p-5 mb-6 border border-slate-200/90 shadow-sm bg-white" aria-labelledby="journey-tracker-title">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-brand-600 block mb-0.5">Hành trình Hiểu bản thân (5 bài chuẩn hóa)</span>
                <h2 id="journey-tracker-title" class="text-base font-black text-slate-900 flex items-center gap-2">
                    <span>Tiến độ thực tế:</span>
                    <span class="px-2.5 py-0.5 bg-brand-50 text-brand-700 rounded-full text-sm font-bold border border-brand-200">
                        Đã hoàn thành <?=$completedCount?>/5 bài
                    </span>
                </h2>
            </div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                <?php if ($progress['all_completed']): ?>
                    <span class="badge badge-success">✓ Đã đủ 5 bài · Mở khóa Báo cáo tổng hợp</span>
                    <a class="btn btn-primary btn-sm" href="<?=url('index.php?page=assessment-report')?>">Xem Báo cáo tổng hợp →</a>
                <?php elseif (!empty($progress['next_step'])): ?>
                    <span class="text-slate-500">Bước tiếp theo: <strong><?=e($progress['next_step']['short_name'])?></strong></span>
                    <a class="btn btn-outline btn-sm text-brand-600" href="<?=url('index.php?page='.$progress['next_step']['route'])?>">Làm tiếp →</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Visual Step Bar -->
        <div class="relative mb-2">
            <div class="overflow-hidden h-2 mb-4 text-xs flex rounded-full bg-slate-100">
                <div style="width: <?=$progress['completion_percent']?>%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-gradient-to-r from-brand-500 via-purple-600 to-indigo-600 transition-all duration-500"></div>
            </div>

            <ol class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2 list-none p-0 m-0">
                <?php foreach ($steps as $key => $st):
                    $isCur = ($key === $currentStepKey);
                    $isDone = $st['is_completed'];
                    $btnClass = $isDone
                        ? 'border-emerald-300 bg-emerald-50/70 text-emerald-900 hover:border-emerald-400'
                        : ($isCur
                            ? 'border-brand-500 bg-purple-50 text-brand-900 ring-2 ring-brand-400/30 font-bold'
                            : 'border-slate-200 bg-slate-50/70 text-slate-500 hover:border-slate-300');
                ?>
                    <li class="relative">
                        <a href="<?=url('index.php?page=' . ($isDone ? $st['result_route'] : $st['route']))?>" 
                           class="block p-2.5 rounded-xl border text-xs transition-all <?=$btnClass?> flex flex-col justify-between h-full"
                           aria-current="<?=$isCur ? 'step' : 'false'?>">
                            <div class="flex items-center justify-between gap-1 mb-1.5">
                                <span class="font-extrabold text-[11px] text-slate-400">0<?=$st['step_number']?></span>
                                <?php if ($isDone): ?>
                                    <span class="w-4 h-4 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] font-bold" title="Đã hoàn thành">✓</span>
                                <?php elseif ($isCur): ?>
                                    <span class="w-2 h-2 rounded-full bg-brand-600 animate-ping" title="Đang xem"></span>
                                <?php else: ?>
                                    <span class="w-4 h-4 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center text-[9px] font-bold">○</span>
                                <?php endif; ?>
                            </div>
                            <div class="font-bold truncate text-slate-800"><?=$st['icon']?> <?=e($st['short_name'])?></div>
                            <div class="text-[10px] text-slate-400 truncate mt-0.5">
                                <?php if ($isDone): ?>
                                    <span class="text-emerald-700 font-semibold"><?=$st['result_code'] ? e($st['result_code']) : 'Đã lưu'?></span>
                                <?php else: ?>
                                    <span><?=$st['items_count']?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
    <?php
    return (string)ob_get_clean();
}

/**
 * Multi-dimensional cumulative synthesis generator (Mục 4).
 * Generates 4 distinct angles:
 * 1. Thông tin mới nhận được từ bài này.
 * 2. Điểm tiếp tục được ghi nhận từ các bài trước.
 * 3. Góc nhìn bổ sung giúp thay đổi hoặc làm rõ nhận định cũ.
 * 4. Điều cần kiểm chứng thêm ở bài tiếp theo hoặc trong thực tế.
 */
function assessment_get_step_synthesis(int $userId, string $type, array $progress): array
{
    $holland = $progress['steps']['holland']['data'] ?? null;
    $big5 = $progress['steps']['big5']['data'] ?? null;
    $caas = $progress['steps']['caas']['data'] ?? null;
    $mbti = $progress['steps']['mbti']['data'] ?? null;
    $eq = $progress['steps']['eq']['data'] ?? null;

    $hCode = $holland ? (string)$holland['result_code'] : '';
    $bScores = $big5 ? (json_decode($big5['scores_json'] ?? '{}', true) ?: []) : [];
    $cScores = $caas ? (json_decode($caas['scores_json'] ?? '{}', true) ?: []) : [];
    $mScores = $mbti ? (json_decode($mbti['scores_json'] ?? '{}', true) ?: []) : [];
    $mCode = $mbti ? (string)$mbti['result_code'] : '';
    $eScores = $eq ? (json_decode($eq['scores_json'] ?? '{}', true) ?: []) : [];

    switch ($type) {
        case 'holland':
            return [
                'new_info' => 'Nhận diện bức tranh sở thích nghề nghiệp ban đầu theo chuẩn O*NET. Mã kết quả nổi trội của bạn là <strong>' . e($hCode ?: 'RIASEC') . '</strong>, mở ra danh mục các nhóm hoạt động đời thực tương ứng để bạn bắt đầu quan sát.',
                'continued_patterns' => 'Đây là bài đánh giá khởi đầu của hành trình Hiểu bản thân. Dữ liệu này sẽ đóng vai trò là mỏ neo sở thích để đối chiếu liên tục với tính cách và khả năng thích ứng ở các bài sau.',
                'supplementary_angles' => 'Khảo sát này đo lường <em>"Bạn thích làm gì"</em> dựa trên tự khai. Nó giúp phân biệt rõ giữa việc bạn yêu thích một lĩnh vực với việc bạn có muốn biến nó thành công việc hằng ngày hay không.',
                'to_verify_next' => 'Sở thích chỉ là một phần của sự phù hợp. Bạn cần kiểm chứng xem <strong>thói quen làm việc và tính cách tự nhiên</strong> của mình (ở Bài 2: Big Five) có hỗ trợ cho nhóm sở thích này hay không.',
            ];

        case 'big5':
            $oVal = (int)($bScores['O'] ?? 50);
            $cVal = (int)($bScores['C'] ?? 50);
            $eVal = (int)($bScores['E'] ?? 50);
            $aVal = (int)($bScores['A'] ?? 50);
            $nVal = (int)($bScores['N'] ?? 50);

            $synergyNote = '';
            if ($hCode !== '') {
                if (str_contains($hCode, 'I') && $oVal >= 60) {
                    $synergyNote = 'Sở thích Nghiên cứu (I) của bạn được củng cố mạnh mẽ bởi tính Cởi mở (Openness ' . $oVal . '%); bạn có xu hướng đào sâu bản chất vấn đề và học tốt qua tìm tòi độc lập.';
                } elseif (str_contains($hCode, 'S') && $aVal >= 60) {
                    $synergyNote = 'Sở thích Xã hội (S) gặp điểm tương đồng cao ở tính Hòa đồng & Thấu cảm (Agreeableness ' . $aVal . '%); bạn có thế mạnh tự nhiên trong việc kết nối và hỗ trợ người khác.';
                } elseif (str_contains($hCode, 'E') && $eVal < 45) {
                    $synergyNote = 'Bạn thích nhóm Quản lý/Thuyết phục (Enterprising) nhưng lại có xu hướng làm việc độc lập (Extraversion ' . $eVal . '%). Bạn sẽ phát huy tốt nhất trong vai trò quản lý kế hoạch hoặc tư vấn chiến lược hơn là ngoại giao đám đông.';
                } else {
                    $synergyNote = 'Các nét tính cách Big Five giúp bổ sung góc nhìn về cách bạn phân bổ năng lượng và kỷ luật tự giác khi theo đuổi nhóm nghề ' . e($hCode) . '.';
                }
            } else {
                $synergyNote = 'Hồ sơ Big Five phác họa bức tranh tính cách ổn định, giúp bạn nhận diện môi trường làm việc phù hợp.';
            }

            return [
                'new_info' => 'Xác lập 5 chiều tính cách cốt lõi (Cởi mở: ' . $oVal . '%, Tận tâm: ' . $cVal . '%, Hướng ngoại: ' . $eVal . '%, Hòa đồng: ' . $aVal . '%, Vững vàng cảm xúc: ' . $nVal . '%). Thông tin này cho biết bạn nạp năng lượng và đối diện áp lực như thế nào.',
                'continued_patterns' => $synergyNote,
                'supplementary_angles' => 'Tính cách giúp làm rõ điều kiện môi trường: bạn cần không gian yên tĩnh hay môi trường sôi nổi, cần quy trình chuẩn mực chặt chẽ hay không gian tự do sáng tạo.',
                'to_verify_next' => 'Khi gặp trở ngại học tập hay thay đổi bất ngờ, bạn phản ứng ra sao? Cần đo lường <strong>Năng lực thích ứng nghề nghiệp (CAAS)</strong> ở Bài 3 để kiểm tra độ sẵn sàng và sức bật vượt khó.',
            ];

        case 'caas':
            $concern = (int)($cScores['concern'] ?? 50);
            $control = (int)($cScores['control'] ?? 50);
            $curiosity = (int)($cScores['curiosity'] ?? 50);
            $confidence = (int)($cScores['confidence'] ?? 50);

            return [
                'new_info' => 'Đo lường 4 nguồn lực thích ứng nghề nghiệp: Quan tâm tương lai (' . $concern . '%), Tự chủ quyết định (' . $control . '%), Tò mò khám phá (' . $curiosity . '%) và Tự tin vượt khó (' . $confidence . '%).',
                'continued_patterns' => 'Hồ sơ tích hợp Sở thích (' . ($hCode ?: 'Bài 1') . ') và Tính cách (Bài 2) với mức độ chủ động hành động. Nếu bạn thích sáng tạo và có độ tò mò cao, bạn sẽ chủ động thử nghiệm các phương pháp học mới.',
                'supplementary_angles' => 'Điểm số thích ứng chỉ ra điều kiện đồng hành: nếu Tự chủ còn đang phát triển (' . $control . '%), bạn cần thêm sự tư vấn từ thầy cô/gia đình trước các bước ngoặt; nếu Tự tin cao (' . $confidence . '%), bạn sẵn sàng đón nhận thử thách khó.',
                'to_verify_next' => 'Cần tìm hiểu <strong>Cách tiếp nhận thông tin và thói quen ra quyết định</strong> (Bài 4: MBTI) để xem bạn thường chọn phương án theo tiêu chí logic hay sự hài hòa cảm xúc.',
            ];

        case 'mbti':
            return [
                'new_info' => 'Nhận diện phong cách tương tác và ra quyết định đặc trưng (' . ($mCode ?: 'MBTI') . '): cách bạn xử lý thông tin (chi tiết thực tế S vs khả năng mới N) và căn cứ lựa chọn (tiêu chí lý tính T vs cảm xúc con người F).',
                'continued_patterns' => 'Các bài trước đã cho thấy bạn thích gì (Holland) và tính cách ra sao (Big Five). MBTI giúp làm rõ thêm thói quen giao tiếp trong nhóm và phong cách lập kế hoạch (J) hay linh hoạt (P).',
                'supplementary_angles' => 'Thu hẹp tiêu chí chọn ngành: bạn sẽ thấy thoải mái trong những môi trường có cấu trúc rõ ràng hay môi trường mở cho phép ứng biến liên tục.',
                'to_verify_next' => 'Để hoàn thiện hồ sơ toàn diện, bước cuối cùng là <strong>Trí tuệ cảm xúc và Động lực nội tại (EQ)</strong> ở Bài 5, đo lường khả năng tự điều hòa và giữ lửa đam mê.',
            ];

        case 'eq':
        default:
            return [
                'new_info' => 'Bổ sung bức tranh cảm xúc và động lực nội tại qua 4 góc: Tự nhận thức, Tự điều chỉnh cảm xúc, Động lực vượt khó và Thấu cảm khi hợp tác.',
                'continued_patterns' => 'Hoàn thiện trọn vẹn 5 góc nhìn: Sở thích (Holland) -> Tính cách (Big Five) -> Thích ứng (CAAS) -> Ra quyết định (MBTI) -> Cảm xúc (EQ).',
                'supplementary_angles' => 'Sự kết hợp giữa trí tuệ cảm xúc và phong cách làm việc cho thấy bạn vừa có thể làm chủ bản thân, vừa biết cách cộng tác hiệu quả trong tập thể.',
                'to_verify_next' => 'Chúc mừng bạn! Hồ sơ 5 chiều đã hoàn thiện. Hãy mở <strong>Báo cáo tổng hợp đa chiều</strong> để đối chiếu với dữ liệu tuyển sinh thực tế và lập Kế hoạch hành động cá nhân.',
            ];
    }
}

/**
 * Return vivid everyday high school examples for each assessment type (Mục 3D).
 */
function assessment_get_daily_examples(string $type, array $scores, string $resultCode): array
{
    switch ($type) {
        case 'holland':
            return [
                [
                    'context' => 'Khi làm bài tập hoặc dự án môn học',
                    'example' => 'Nếu nhóm Kỹ thuật (R) hoặc Nghiên cứu (I) cao, bạn thích tự tháo lắp thiết bị, tìm tòi công thức gốc hoặc tự tay làm mô hình thí nghiệm hơn là chỉ đọc lý thuyết suông.',
                ],
                [
                    'context' => 'Khi tham gia hoạt động nhóm trên lớp',
                    'example' => 'Nếu nhóm Xã hội (S) hoặc Quản lý (E) nổi trội, bạn thường chủ động đứng ra phân chia việc, lắng nghe ý kiến các bạn hoặc đại diện nhóm thuyết trình trước lớp.',
                ],
                [
                    'context' => 'Khi rảnh rỗi hoặc tham gia Câu lạc bộ',
                    'example' => 'Nếu nhóm Nghệ thuật (A) cao, bạn thích vẽ, viết lách, thiết kế slide hoặc quay video; nếu nhóm Nghiệp vụ (C) cao, bạn thích sắp xếp tài liệu, quản lý quỹ nhóm và lên thời khóa biểu ngăn nắp.',
                ],
            ];

        case 'big5':
            return [
                [
                    'context' => 'Khi đối diện với tuần thi học kỳ căng thẳng',
                    'example' => 'Chỉ số Vững vàng cảm xúc (Emotional Stability) phản ánh cách bạn giữ bình tĩnh. Người có điểm cao thường nhanh chóng lấy lại tập trung; người nhạy cảm hơn cần chia nhỏ thời gian ôn và nghỉ ngơi để tránh quá tải.',
                ],
                [
                    'context' => 'Khi làm bài tập lớn có hạn nộp dài hạn',
                    'example' => 'Chỉ số Tận tâm (Conscientiousness) thể hiện rõ ở việc bạn bắt tay làm ngay theo từng mốc (điểm cao) hay để dồn đến sát hạn mới làm thâu đêm (điểm cần rèn thêm).',
                ],
                [
                    'context' => 'Trong giờ sinh hoạt lớp hoặc làm quen bạn mới',
                    'example' => 'Chỉ số Hướng ngoại (Extraversion) và Hòa đồng (Agreeableness) cho thấy bạn thích khuấy động không khí hay thích trò chuyện sâu 1-1 với những người bạn thân thiết.',
                ],
            ];

        case 'caas':
            return [
                [
                    'context' => 'Khi nghe thầy cô hoặc gia đình bàn về chọn ngành thi đại học',
                    'example' => 'Nếu điểm Quan tâm (Concern) cao, bạn đã bắt đầu tự tra cứu điểm chuẩn, tìm hiểu đề án tuyển sinh từ sớm thay vì để "nước đến chân mới nhảy".',
                ],
                [
                    'context' => 'Khi có sự bất đồng ý kiến về ngành học mong muốn',
                    'example' => 'Điểm Tự chủ (Control) thể hiện ở việc bạn tự tin trình bày lý do, chủ động tìm bằng chứng về năng lực của mình thay vì phó mặc hoàn toàn cho người khác quyết định hộ.',
                ],
                [
                    'context' => 'Khi gặp một môn học mới hoặc kỹ năng công nghệ khó',
                    'example' => 'Điểm Tự tin (Confidence) và Tò mò (Curiosity) giúp bạn xem trở ngại như một câu đố cần giải, chủ động lên mạng tra tài liệu hoặc hỏi người có kinh nghiệm.',
                ],
            ];

        case 'mbti':
            return [
                [
                    'context' => 'Cách tiếp nhận bài giảng mới trên lớp',
                    'example' => 'Người thiên về Cụ thể (S) hiểu nhanh nhất qua ví dụ thực tế và bài tập mẫu; người thiên về Khái quát (N) thích nắm ý tưởng tổng thể và các mối liên hệ trừu tượng trước.',
                ],
                [
                    'context' => 'Khi nhóm xảy ra tranh cãi về phương án làm việc',
                    'example' => 'Người thiên về Lý trí (T) sẽ căn cứ vào bảng tiêu chí và tính khả thi khách quan; người thiên về Cảm xúc (F) sẽ ưu tiên sự đồng thuận và cảm nhận của các thành viên.',
                ],
                [
                    'context' => 'Kế hoạch học tập cho kỳ nghỉ hè',
                    'example' => 'Người thiên về Kỷ luật (J) thích có lịch trình cụ thể từng ngày; người thiên về Linh hoạt (P) thích để ngỏ thời gian để tùy hứng khám phá.',
                ],
            ];

        case 'eq':
        default:
            return [
                [
                    'context' => 'Khi nhận điểm kiểm tra không như kỳ vọng',
                    'example' => 'Năng lực Tự nhận thức và Tự điều chỉnh giúp bạn gọi tên cảm xúc thất vọng mà không bị chìm sâu vào tự ti, từ đó bình tĩnh xem lại lỗi sai để sửa chữa.',
                ],
                [
                    'context' => 'Khi một bạn trong nhóm có vẻ im lặng hoặc buồn bã',
                    'example' => 'Khả năng Thấu cảm giúp bạn nhận ra tín hiệu không lời, chủ động hỏi han nhẹ nhàng và tạo điều kiện để bạn chia sẻ theo cách thoải mái nhất.',
                ],
                [
                    'context' => 'Khi việc học trở nên đơn điệu và mất động lực',
                    'example' => 'Động lực nội tại giúp bạn kết nối bài học hiện tại với mục tiêu dài hạn mà bạn đam mê, tự tìm lại cảm hứng học tập mà không cần ai thúc ép.',
                ],
            ];
    }
}

/**
 * Return pedagogical limitation notices for each test (Mục 3F).
 * Contains the verbatim mandatory notice for Step 1.
 */
function assessment_get_pedagogical_limits(string $type, int $completedCount): array
{
    $notices = [];

    if ($type === 'holland') {
        $notices[] = [
            'type' => 'mandatory_step1',
            'title' => 'Thông báo quan trọng về giá trị kết quả',
            'text' => 'Bạn đã hoàn thành 1/5 bài đánh giá. Đây mới là xu hướng sở thích ban đầu, chưa phản ánh đầy đủ năng lực hay cơ hội nghề nghiệp. Hãy hoàn thành các bài tiếp theo để hồ sơ được tích lũy chính xác hơn.',
        ];
    }

    $notices[] = [
        'type' => 'methodology',
        'title' => 'Bản chất tự khai (Self-Reported)',
        'text' => 'Kết quả này phản ánh góc nhìn và cảm nhận chủ quan của bạn tại thời điểm làm bài. Sở thích và phong cách có thể tiếp tục hoàn thiện theo thời gian và trải nghiệm sống.',
    ];

    $notices[] = [
        'type' => 'no_label',
        'title' => 'Không gán nhãn hay đóng khung năng lực',
        'text' => 'Không có nghề nào là "dành riêng tuyệt đối" cho bạn và cũng không có cánh cửa nào bị đóng lại chỉ vì một điểm số khảo sát. Hãy xem đây là gợi ý để bắt đầu tìm hiểu thực tế.',
    ];

    return $notices;
}

/**
 * Return 2-3 concrete small actions to take in the next 24-48 hours (Mục 3G).
 */
function assessment_get_small_actions(string $type, array $scores, string $resultCode): array
{
    switch ($type) {
        case 'holland':
            return [
                'Chọn 2 nghề trong danh sách gợi ý bên dưới và dành 15 phút tra cứu về một ngày làm việc thực tế của nghề đó.',
                'Vào Phòng mô phỏng nghề (2D hoặc 3D) để thử đặt mình vào các tình huống ra quyết định nghề nghiệp.',
                'Ghi lại cảm nhận vào ô phản tư bên dưới: "Kết quả này giống hoặc chưa giống mình ở điểm nào?".',
            ];
        case 'big5':
            return [
                'Quan sát lại thói quen học tập trong 3 ngày tới: bạn thường tập trung tốt nhất vào khung giờ nào và trong không gian nào?',
                'Thử áp dụng một phương pháp quản lý thời gian phù hợp với chỉ số Tận tâm của bạn (ví dụ: chia nhỏ mục tiêu 25 phút Pomodoro).',
                'Hoàn thành bài tiếp theo (Bài 3: Thích ứng CAAS) để bổ sung góc nhìn về năng lực thích ứng tương lai.',
            ];
        case 'caas':
            return [
                'Viết ra 1 câu hỏi cụ thể về tương lai mà bạn đang băn khoăn nhất (về trường, ngành, hoặc chi phí học tập).',
                'Trò chuyện với một người đi trước (thầy cô, anh chị sinh viên) về những khó khăn thực tế khi bước vào ngành học bạn quan tâm.',
                'Chuyển sang Bài 4 (MBTI rút gọn) để khám phá cách bạn xử lý thông tin và ra quyết định.',
            ];
        case 'mbti':
            return [
                'Trong lần thảo luận nhóm tới, thử để ý xem bạn thiên về đưa ra lý lẽ khách quan hay quan tâm nhiều hơn đến cảm xúc các thành viên.',
                'Chia sẻ kết quả 4 chữ cái với bạn thân để xem góc nhìn của bạn bè có trùng khớp với cảm nhận của bạn không.',
                'Làm nốt Bài 5 (EQ) để mở khóa trọn vẹn Báo cáo tổng hợp đối chiếu với dữ liệu tuyển sinh.',
            ];
        case 'eq':
        default:
            return [
                'Tập dừng lại 30 giây để gọi tên cảm xúc của mình khi gặp một tình huống căng thẳng trong ngày.',
                'Mở Báo cáo tổng hợp sau 5 bài và nhập điểm học kỳ để đối chiếu với điểm chuẩn các trường đại học.',
                'Lập bản nháp Kế hoạch hành động cá nhân để biến các phát hiện thành bước đi cụ thể.',
            ];
    }
}

/**
 * Save student reflection feedback on any of the 5 tests without altering raw scores.
 */
function assessment_save_student_reflection(int $userId, string $type, string $feeling, string $notes, ?int $assessmentId = null): bool
{
    assessment_feedback_ensure_tables();
    $validFeelings = ['agree', 'partially_agree', 'disagree'];
    $feeling = in_array($feeling, $validFeelings, true) ? $feeling : 'agree';
    $notes = mb_substr(trim($notes), 0, 1500);

    try {
        $stmt = db()->prepare('INSERT INTO user_assessment_reflections (user_id, test_type, assessment_id, fit_feeling, student_notes) 
                               VALUES (?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE fit_feeling=VALUES(fit_feeling), student_notes=VALUES(student_notes), updated_at=CURRENT_TIMESTAMP');
        $stmt->execute([$userId, $type, $assessmentId, $feeling, $notes]);

        // If it is Holland, also preserve in assessments.validity_metrics for backward compatibility
        if ($type === 'holland' && $assessmentId !== null) {
            $s = db()->prepare('SELECT validity_metrics FROM assessments WHERE id=? AND user_id=?');
            $s->execute([$assessmentId, $userId]);
            $vmJson = $s->fetchColumn();
            if ($vmJson !== false) {
                $vm = json_decode((string)$vmJson ?: '{}', true) ?: [];
                $vm['student_feedback'] = $notes;
                $vm['student_feeling'] = $feeling;
                $vm['feedback_updated_at'] = date('Y-m-d H:i:s');
                $up = db()->prepare('UPDATE assessments SET validity_metrics=? WHERE id=? AND user_id=?');
                $up->execute([json_encode($vm, JSON_UNESCAPED_UNICODE), $assessmentId, $userId]);
            }
        }
        return true;
    } catch (Throwable $e) {
        // Fallback for SQLite or syntax variations
        try {
            $del = db()->prepare('DELETE FROM user_assessment_reflections WHERE user_id=? AND test_type=?');
            $del->execute([$userId, $type]);
            $ins = db()->prepare('INSERT INTO user_assessment_reflections (user_id, test_type, assessment_id, fit_feeling, student_notes) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$userId, $type, $assessmentId, $feeling, $notes]);
            return true;
        } catch (Throwable $e2) {
            error_log('assessment_save_student_reflection error: ' . $e2->getMessage());
            return false;
        }
    }
}

/**
 * Retrieve saved student reflection for a specific test.
 */
function assessment_get_student_reflection(int $userId, string $type): ?array
{
    assessment_feedback_ensure_tables();
    try {
        $stmt = db()->prepare('SELECT * FROM user_assessment_reflections WHERE user_id=? AND test_type=? ORDER BY updated_at DESC, id DESC LIMIT 1');
        $stmt->execute([$userId, $type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Get or initialize student academic profile.
 */
function assessment_get_academic_profile(int $userId): array
{
    assessment_feedback_ensure_tables();
    $me = user();
    $default = [
        'id' => null,
        'user_id' => $userId,
        'grade_level' => !empty($me['grade']) ? $me['grade'] : 'Lớp 12',
        'academic_year' => '2024-2025',
        'semester' => 'sem1',
        'subject_scores' => [
            'toan' => null,
            'van' => null,
            'anh' => null,
            'ly' => null,
            'hoa' => null,
            'sinh' => null,
            'su' => null,
            'dia' => null,
            'tin' => null,
            'gdcd' => null,
        ],
        'target_region' => 'all',
        'budget_yearly' => 'unknown',
        'notes' => '',
    ];

    try {
        $stmt = db()->prepare('SELECT * FROM user_academic_profiles WHERE user_id=? ORDER BY updated_at DESC, id DESC LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $parsedScores = json_decode($row['subject_scores_json'] ?? '{}', true) ?: [];
            $default['id'] = (int)$row['id'];
            $default['grade_level'] = $row['grade_level'];
            $default['academic_year'] = $row['academic_year'];
            $default['semester'] = $row['semester'];
            $default['target_region'] = $row['target_region'];
            $default['budget_yearly'] = $row['budget_yearly'];
            $default['notes'] = (string)($row['notes'] ?? '');
            foreach ($default['subject_scores'] as $sub => $v) {
                if (isset($parsedScores[$sub]) && is_numeric($parsedScores[$sub])) {
                    $default['subject_scores'][$sub] = (float)$parsedScores[$sub];
                }
            }
        }
    } catch (Throwable $e) {
        error_log('assessment_get_academic_profile error: ' . $e->getMessage());
    }

    return $default;
}

/**
 * Save student academic profile.
 */
function assessment_save_academic_profile(int $userId, array $data): bool
{
    assessment_feedback_ensure_tables();
    $gradeLevel = trim((string)($data['grade_level'] ?? 'Lớp 12'));
    $academicYear = trim((string)($data['academic_year'] ?? '2024-2025'));
    $semester = in_array($data['semester'] ?? '', ['sem1', 'sem2', 'whole_year'], true) ? $data['semester'] : 'sem1';
    $targetRegion = in_array($data['target_region'] ?? '', ['all', 'north', 'central', 'south'], true) ? $data['target_region'] : 'all';
    $budgetYearly = in_array($data['budget_yearly'] ?? '', ['unknown', '0', '1', '2', '3'], true) ? $data['budget_yearly'] : 'unknown';
    $notes = mb_substr(trim((string)($data['notes'] ?? '')), 0, 500);

    $subjectScores = [];
    $allowedSubjects = ['toan', 'van', 'anh', 'ly', 'hoa', 'sinh', 'su', 'dia', 'tin', 'gdcd'];
    foreach ($allowedSubjects as $sub) {
        if (isset($data['subject_scores'][$sub]) && is_numeric($data['subject_scores'][$sub])) {
            $val = (float)$data['subject_scores'][$sub];
            if ($val >= 0.0 && $val <= 10.0) {
                $subjectScores[$sub] = round($val, 2);
            }
        }
    }

    try {
        $stmt = db()->prepare('INSERT INTO user_academic_profiles (user_id, grade_level, academic_year, semester, subject_scores_json, target_region, budget_yearly, notes)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            $gradeLevel,
            $academicYear,
            $semester,
            json_encode($subjectScores, JSON_UNESCAPED_UNICODE),
            $targetRegion,
            $budgetYearly,
            $notes
        ]);
        return true;
    } catch (Throwable $e) {
        error_log('assessment_save_academic_profile error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Calculate scores for canonical national admission combinations.
 */
function assessment_calculate_combinations_scores(array $subjectScores): array
{
    $combos = [
        'A00' => ['name' => 'Toán, Vật lý, Hóa học', 'subjects' => ['toan', 'ly', 'hoa'], 'field' => 'Khoa học tự nhiên & Kỹ thuật'],
        'A01' => ['name' => 'Toán, Vật lý, Tiếng Anh', 'subjects' => ['toan', 'ly', 'anh'], 'field' => 'Kỹ thuật & Công nghệ'],
        'B00' => ['name' => 'Toán, Hóa học, Sinh học', 'subjects' => ['toan', 'hoa', 'sinh'], 'field' => 'Y sinh, Nông nghiệp, Môi trường'],
        'C00' => ['name' => 'Ngữ văn, Lịch sử, Địa lý', 'subjects' => ['van', 'su', 'dia'], 'field' => 'Khoa học xã hội & Nhân văn'],
        'C01' => ['name' => 'Ngữ văn, Toán, Vật lý', 'subjects' => ['van', 'toan', 'ly'], 'field' => 'Tổng hợp'],
        'D01' => ['name' => 'Ngữ văn, Toán, Tiếng Anh', 'subjects' => ['van', 'toan', 'anh'], 'field' => 'Ngoại ngữ, Kinh tế & Xã hội'],
        'D07' => ['name' => 'Toán, Hóa học, Tiếng Anh', 'subjects' => ['toan', 'hoa', 'anh'], 'field' => 'Hóa sinh & Công nghệ'],
        'X06' => ['name' => 'Toán, Vật lý, Tin học', 'subjects' => ['toan', 'ly', 'tin'], 'field' => 'Công nghệ thông tin'],
        'X26' => ['name' => 'Toán, Tiếng Anh, Tin học', 'subjects' => ['toan', 'anh', 'tin'], 'field' => 'CNTT & Trí tuệ nhân tạo'],
    ];

    $calculated = [];
    foreach ($combos as $code => $info) {
        $total = 0.0;
        $hasAll = true;
        $missing = [];
        foreach ($info['subjects'] as $sub) {
            if (isset($subjectScores[$sub]) && is_numeric($subjectScores[$sub])) {
                $total += (float)$subjectScores[$sub];
            } else {
                $hasAll = false;
                $missing[] = $sub;
            }
        }
        $calculated[$code] = [
            'code' => $code,
            'name' => $info['name'],
            'field' => $info['field'],
            'subjects' => $info['subjects'],
            'total_score' => $hasAll ? round($total, 2) : null,
            'is_complete' => $hasAll,
            'missing_subjects' => $missing,
        ];
    }

    return $calculated;
}

/**
 * Identify repeating patterns (Consistencies) and discrepancies across the 5 assessments (Mục 6B, 6C).
 */
function assessment_get_consistencies_and_discrepancies(int $userId): array
{
    $progress = assessment_get_journey_progress($userId);
    $holland = $progress['steps']['holland']['data'] ?? null;
    $big5 = $progress['steps']['big5']['data'] ?? null;
    $caas = $progress['steps']['caas']['data'] ?? null;
    $mbti = $progress['steps']['mbti']['data'] ?? null;
    $eq = $progress['steps']['eq']['data'] ?? null;

    $hCode = $holland ? (string)$holland['result_code'] : '';
    $hScores = $holland ? (json_decode($holland['scores'] ?? '{}', true) ?: []) : [];
    $bScores = $big5 ? (json_decode($big5['scores_json'] ?? '{}', true) ?: []) : [];
    $cScores = $caas ? (json_decode($caas['scores_json'] ?? '{}', true) ?: []) : [];
    $mCode = $mbti ? (string)$mbti['result_code'] : '';
    $eScores = $eq ? (json_decode($eq['scores_json'] ?? '{}', true) ?: []) : [];

    $consistencies = [];
    $discrepancies = [];

    // Check consistency: People orientation (Holland S + Big5 Agreeableness + EQ Empathy)
    $hasHollandS = str_contains($hCode, 'S') || ($hScores['S'] ?? 0) >= 60;
    $hasBig5A = ($bScores['A'] ?? 0) >= 60;
    $hasEqEmpathy = ($eScores['empathy'] ?? 0) >= 60;
    if ($hasHollandS && ($hasBig5A || $hasEqEmpathy)) {
        $consistencies[] = [
            'theme' => 'Định hướng Con người & Phụng sự Xã hội',
            'detail' => 'Sở thích Xã hội (S) xuất hiện đồng bộ cùng tính Thấu cảm (Agreeableness ' . ($bScores['A'] ?? '') . '%) và Trí tuệ cảm xúc (' . ($eScores['empathy'] ?? '') . '%). Bạn có năng khiếu tự nhiên trong việc lắng nghe, truyền đạt và hỗ trợ người khác.',
            'sources' => ['Holland (S)', 'Big Five (A)', 'EQ (Thấu cảm)'],
        ];
    }

    // Check consistency: Analytical/Scientific orientation (Holland I + Big5 Openness + MBTI T/N)
    $hasHollandI = str_contains($hCode, 'I') || ($hScores['I'] ?? 0) >= 60;
    $hasBig5O = ($bScores['O'] ?? 0) >= 60;
    $hasMbtiT = str_contains($mCode, 'T');
    if ($hasHollandI && $hasBig5O) {
        $consistencies[] = [
            'theme' => 'Tư duy Khám phá & Nghiên cứu Độc lập',
            'detail' => 'Sở thích Nghiên cứu (I) được củng cố bởi sự Cởi mở trí tuệ (Openness ' . ($bScores['O'] ?? '') . '%) và phong cách tư duy phân tích' . ($hasMbtiT ? ' (MBTI T)' : '') . '. Bạn làm việc hiệu quả khi được tự do đào sâu nguyên lý và giải quyết bài toán phức tạp.',
            'sources' => ['Holland (I)', 'Big Five (O)', 'MBTI'],
        ];
    }

    // Check consistency: Structure & Discipline (Holland C + Big5 Conscientiousness + MBTI J)
    $hasHollandC = str_contains($hCode, 'C') || ($hScores['C'] ?? 0) >= 60;
    $hasBig5C = ($bScores['C'] ?? 0) >= 60;
    $hasMbtiJ = str_contains($mCode, 'J');
    if ($hasHollandC && ($hasBig5C || $hasMbtiJ)) {
        $consistencies[] = [
            'theme' => 'Tính Kỷ luật, Chuẩn mực & Tổ chức cao',
            'detail' => 'Sở thích Nghiệp vụ/Quy chuẩn (C) đi kèm chỉ số Tận tâm kỷ luật (' . ($bScores['C'] ?? '') . '%) và phong cách kế hoạch rõ ràng (MBTI J). Đây là nền tảng rất vững cho các ngành đòi hỏi độ chính xác và tuân thủ cao.',
            'sources' => ['Holland (C)', 'Big Five (C)', 'MBTI (J)'],
        ];
    }

    // Check Discrepancy 1: Enterprising vs Introversion
    if (str_contains($hCode, 'E') && ($bScores['E'] ?? 50) < 45) {
        $discrepancies[] = [
            'title' => 'Sở thích Quản lý nhưng tính cách thiên về Độc lập',
            'detail' => 'Bạn có hứng thú với nhóm Quản lý/Thuyết phục (Holland E) nhưng chỉ số Hướng ngoại (Big Five Extraversion) lại ở mức khiêm tốn (' . ($bScores['E'] ?? '') . '%).',
            'reflective_question' => 'Bạn có thể quan sát xem mình thích quản lý ở khía cạnh chiến lược, phân tích dự án hay thích đứng trước đám đông thuyết phục? Bạn có thể phát huy tốt ở vai trò tư vấn giải pháp hoặc quản lý vận hành phía sau hậu trường.',
        ];
    }

    // Check Discrepancy 2: High Interest but Low Self-Efficacy or CAAS Confidence
    $cConfidence = (int)($cScores['confidence'] ?? 50);
    if (!empty($hCode) && $cConfidence < 45) {
        $discrepancies[] = [
            'title' => 'Sở thích rõ nét nhưng Tự tin hành động còn đang phát triển',
            'detail' => 'Bạn đã xác định được nhóm sở thích ' . e($hCode) . ' nhưng chỉ số Tự tin vượt khó (CAAS Confidence) đang ở mức cần rèn luyện (' . $cConfidence . '%).',
            'reflective_question' => 'Điều gì đang khiến bạn còn ngập ngừng khi nghĩ về việc theo đuổi ngành này? Phải chăng bạn lo lắng về một môn học cụ thể (Toán, Ngoại ngữ) hay áp lực điểm chuẩn? Việc chia nhỏ mục tiêu theo tuần sẽ giúp bạn tích lũy niềm tin từng bước.',
        ];
    }

    // Check Discrepancy 3: High Openness but Preference for Fixed Structure (MBTI J vs Big5 O)
    if (($bScores['O'] ?? 50) >= 65 && str_contains($mCode, 'J')) {
        $discrepancies[] = [
            'title' => 'Sáng tạo đổi mới kết hợp cùng nhu cầu Kế hoạch trật tự',
            'detail' => 'Bạn rất cởi mở với các ý tưởng mới mẻ (Openness ' . ($bScores['O'] ?? '') . '%) nhưng lại cảm thấy thoải mái nhất khi mọi việc có lịch trình kiểm soát rõ ràng (MBTI J).',
            'reflective_question' => 'Đây là một sự kết hợp rất giá trị: người vừa có ý tưởng sáng tạo vừa có khả năng hiện thực hóa thành quy trình. Bạn hãy chọn những môi trường tôn trọng sự đổi mới nhưng có mốc thời gian rõ ràng.',
        ];
    }

    if (empty($consistencies)) {
        $consistencies[] = [
            'theme' => 'Hồ sơ đa dạng và đang tiếp tục định hình',
            'detail' => 'Các bài đánh giá phản ánh bạn có sự linh hoạt cao giữa các nhóm sở thích và tính cách, không bị bó hẹp trong một khuôn mẫu duy nhất.',
            'sources' => ['Tổng hợp 5 bài'],
        ];
    }

    return [
        'consistencies' => $consistencies,
        'discrepancies' => $discrepancies,
    ];
}

/**
 * Retrieve matching training majors from the 358 majors catalog based on profile.
 */
function assessment_get_matching_training_majors(array $careerIds, string $hollandCode, array $big5Scores): array
{
    if (empty($careerIds)) {
        return [];
    }

    try {
        $placeholders = implode(',', array_fill(0, count($careerIds), '?'));
        // Find mapped training majors or matching by career tags
        $sql = "SELECT tm.id, tm.name, tm.official_major_code, tm.editorial_description, tm.source_page_title,
                       f.name as field_name
                FROM training_majors tm
                LEFT JOIN major_fields f ON f.id = tm.field_id
                WHERE tm.verification_status IN ('verified','standardized','pending_review')
                  AND (
                    EXISTS (
                        SELECT 1 FROM training_major_mappings tmm 
                        JOIN career_major_paths cmp ON cmp.major_id = tmm.existing_major_id
                        WHERE tmm.training_major_id = tm.id AND cmp.career_id IN ($placeholders)
                    )
                    OR tm.name LIKE '%Công nghệ%'
                    OR tm.name LIKE '%Kỹ thuật%'
                    OR tm.name LIKE '%Sư phạm%'
                    OR tm.name LIKE '%Kinh tế%'
                    OR tm.name LIKE '%Quản trị%'
                  )
                ORDER BY tm.listed_school_count DESC
                LIMIT 6";
        $stmt = db()->prepare($sql);
        $stmt->execute($careerIds);
        $majors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$majors) {
            $stmt = db()->query("SELECT tm.id, tm.name, tm.official_major_code, tm.editorial_description, tm.source_page_title,
                                        f.name as field_name
                                 FROM training_majors tm
                                 LEFT JOIN major_fields f ON f.id = tm.field_id
                                 LIMIT 6");
            $majors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $majors ?: [];
    } catch (Throwable $e) {
        error_log('assessment_get_matching_training_majors error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Retrieve real institutions and verified admission records matching the profile.
 */
function assessment_get_matching_admissions(array $careerIds, ?string $targetRegion = 'all'): array
{
    try {
        $regionFilter = '';
        $params = [];
        if ($targetRegion === 'north') {
            $regionFilter = " AND (i.province IN ('Hà Nội', 'Hải Phòng', 'Thái Nguyên', 'Nam Định', 'Phú Thọ') OR i.province LIKE '%Hà Nội%') ";
        } elseif ($targetRegion === 'central') {
            $regionFilter = " AND (i.province IN ('Đà Nẵng', 'Huế', 'Nghệ An', 'Bình Định', 'Khánh Hòa') OR i.province LIKE '%Đà Nẵng%') ";
        } elseif ($targetRegion === 'south') {
            $regionFilter = " AND (i.province IN ('TP. Hồ Chí Minh', 'Cần Thơ', 'Bình Dương', 'Đồng Nai') OR i.province LIKE '%Hồ Chí Minh%') ";
        }

        $sql = "SELECT ar.id, ar.admission_year, ar.method, ar.cutoff_score, ar.score_scale, ar.source_url,
                       p.name as program_name, p.program_code,
                       i.name as inst_name, i.code as inst_code, i.province as inst_province, i.website as inst_website, i.admissions_url
                FROM admission_records ar
                JOIN programs p ON p.id = ar.program_id
                JOIN institutions i ON i.id = p.institution_id
                WHERE ar.verification_status = 'verified' $regionFilter
                ORDER BY ar.admission_year DESC, ar.cutoff_score DESC
                LIMIT 6";

        $stmt = db()->query($sql);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$records && $regionFilter !== '') {
            $sql = "SELECT ar.id, ar.admission_year, ar.method, ar.cutoff_score, ar.score_scale, ar.source_url,
                           p.name as program_name, p.program_code,
                           i.name as inst_name, i.code as inst_code, i.province as inst_province, i.website as inst_website, i.admissions_url
                    FROM admission_records ar
                    JOIN programs p ON p.id = ar.program_id
                    JOIN institutions i ON i.id = p.institution_id
                    WHERE ar.verification_status = 'verified'
                    ORDER BY ar.admission_year DESC, ar.cutoff_score DESC
                    LIMIT 6";
            $records = db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }

        return $records ?: [];
    } catch (Throwable $e) {
        error_log('assessment_get_matching_admissions error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Render single assessment result screen containing all 8 mandatory sections (A to H).
 */
function assessment_render_single_result(int $userId, string $type, ?int $assessmentId = null): void
{
    $progress = assessment_get_journey_progress($userId);
    $steps = $progress['steps'];
    $step = $steps[$type] ?? null;

    if (!$step) {
        redirect('index.php?page=assessment-hub');
    }

    if (!$step['is_completed']) {
        flash('warning', 'Bạn chưa hoàn thành bài đánh giá này. Hãy trả lời các câu hỏi để nhận kết quả tích lũy.');
        redirect('index.php?page=' . $step['route']);
    }

    // Determine assessment record
    $record = $step['data'];
    $recordId = $step['record_id'];
    $resultCode = $step['result_code'];
    $summaryLabel = $step['summary_label'];
    $validityFlag = $step['validity_flag'];

    // Decode scores
    $scores = [];
    $validityMetrics = [];
    $efficacyScores = [];
    $zScores = [];
    $rawAnswers = [];
    $durationSeconds = 0;

    if ($type === 'holland') {
        $scores = json_decode($record['scores'] ?? '{}', true) ?: [];
        $validityMetrics = json_decode($record['validity_metrics'] ?? '{}', true) ?: [];
        $efficacyScores = json_decode($record['efficacy_scores'] ?? '{}', true) ?: [];
        $zScores = json_decode($record['ipsative_z_scores'] ?? '{}', true) ?: [];
        $rawAnswers = json_decode($record['answers'] ?? '{}', true) ?: [];
        $durationSeconds = (int)($validityMetrics['duration_seconds'] ?? 0);
    } else {
        $scores = json_decode($record['scores_json'] ?? '{}', true) ?: [];
        $rawAnswers = json_decode($record['answers_json'] ?? '{}', true) ?: [];
        $durationSeconds = (int)($record['duration_seconds'] ?? 0);
    }

    $synthesis = assessment_get_step_synthesis($userId, $type, $progress);
    $dailyExamples = assessment_get_daily_examples($type, $scores, $resultCode);
    $smallActions = assessment_get_small_actions($type, $scores, $resultCode);
    $limits = assessment_get_pedagogical_limits($type, $progress['completed_count']);
    $studentReflection = assessment_get_student_reflection($userId, $type);

    // Determine the next step in the journey
    $journeyOrder = ['holland', 'big5', 'caas', 'mbti', 'eq'];
    $currentIndex = array_search($type, $journeyOrder, true);
    $nextStepKey = ($currentIndex !== false && isset($journeyOrder[$currentIndex + 1])) ? $journeyOrder[$currentIndex + 1] : null;
    $nextStepInfo = $nextStepKey ? ($steps[$nextStepKey] ?? null) : null;

    $pageTitle = 'Kết quả ' . $step['short_name'] . ' (' . $step['step_number'] . '/5)';
    require __DIR__ . '/header.php';
    ?>
    <section class="page-head bg-slate-900 text-white py-8">
        <div class="container">
            <nav class="breadcrumb mb-3 text-slate-300 text-xs flex items-center justify-between" aria-label="Đường dẫn trang">
                <div>
                    <a href="<?=url('index.php?page=home')?>" class="hover:text-white">Trang chủ</a>
                    <span class="breadcrumb-sep">/</span>
                    <a href="<?=url('index.php?page=assessment-hub')?>" class="hover:text-white">Hiểu bản thân</a>
                    <span class="breadcrumb-sep">/</span>
                    <span aria-current="page" class="text-white font-semibold">Kết quả <?=$step['short_name']?></span>
                </div>
                <span class="text-[11px] text-brand-300 font-medium">Gợi ý từ hồ sơ hiện tại</span>
            </nav>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-brand-500/30 text-brand-200 border border-brand-400/30 mb-2">
                        <span><?=$step['icon']?></span>
                        <span>Bài <?=$step['step_number']?>/5: <?=$step['title']?></span>
                    </span>
                    <h1 class="page-title text-2xl md:text-3xl font-black text-white mb-1">
                        Kết quả bài đánh giá: <span class="text-brand-300"><?=$resultCode ? e($resultCode) : e($step['short_name'])?></span>
                    </h1>
                    <p class="text-slate-300 text-xs md:text-sm">
                        Thời điểm hoàn thành: <?=date('d/m/Y H:i', strtotime($step['completed_at'] ?: 'now'))?> · Dữ liệu tự đánh giá theo chuẩn khoa học
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a class="btn btn-outline btn-sm bg-white/10 hover:bg-white/20 text-white border-white/20" href="<?=url('index.php?page=' . $step['route'])?>">
                        ↻ Làm lại bài này
                    </a>
                    <?php if ($progress['all_completed']): ?>
                        <a class="btn btn-primary btn-sm" href="<?=url('index.php?page=assessment-report')?>">
                            Xem Báo cáo tổng hợp 5 bài →
                        </a>
                    <?php elseif ($nextStepInfo): ?>
                        <a class="btn btn-primary btn-sm" href="<?=url('index.php?page=' . $nextStepInfo['route'])?>">
                            Làm tiếp Bài <?=$nextStepInfo['step_number']?>: <?=e($nextStepInfo['short_name'])?> →
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <div class="container py-8 space-y-8">

        <!-- MỤC A: TIẾN ĐỘ THỰC TẾ (X/5 BÀI) -->
        <?=assessment_render_journey_tracker($progress, $type)?>

        <?php if ($type === 'holland' && ($record['ai_status'] ?? '') !== 'completed'): ?>
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl text-xs text-amber-800 flex items-start gap-3">
                <span class="text-lg">⚠️</span>
                <div>
                    <strong>Cập nhật phân tích AI:</strong> <?=e(($record['ai_status'] ?? '') === 'pending' ? 'Phần tư vấn AI cần được tạo lại sau khi hồ sơ thay đổi.' : 'Phần tư vấn AI chưa hoàn tất; bạn có thể thử lại.')?>
                </div>
            </div>
        <?php endif; ?>

        <!-- MỤC B: BÀI NÀY GIÚP TÌM HIỂU ĐIỀU GÌ -->
        <section class="card p-6 border border-slate-200 shadow-sm" aria-labelledby="sec-purpose-title">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3 border-b border-slate-100 pb-3">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Mục tiêu sư phạm</span>
                    <h2 id="sec-purpose-title" class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <span>🎯</span> Bài đánh giá này giúp bạn tìm hiểu điều gì?
                    </h2>
                </div>
                <span class="badge badge-subtle text-xs">Mô hình: <?=e($step['theoretical_model'])?></span>
            </div>
            <p class="text-sm text-slate-700 leading-relaxed mb-3">
                <?=e($step['summary_intro'])?>
            </p>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 text-xs text-slate-600 space-y-1">
                <div><strong>• Công cụ chuẩn hóa:</strong> <?=e($step['tool_name'])?> (<?=e($step['items_count'])?>).</div>
                <div><strong>• Phương pháp xử lý:</strong> Phân tích điểm chuẩn hóa nội tại, kiểm định độ nhất quán phản hồi, loại bỏ thiên kiến đánh đồng loạt.</div>
                <div><strong>• Ý nghĩa định hướng:</strong> Cung cấp một lát cắt khách quan về xu hướng tự nhiên của bạn để tích lũy dần vào hồ sơ năng lực.</div>
            </div>
        </section>

        <!-- MỤC C: TÓM TẮT KẾT QUẢ RIÊNG BẰNG NGÔN NGỮ DỄ HIỂU -->
        <section class="card p-6 border border-slate-200 shadow-sm" aria-labelledby="sec-scores-title">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4 border-b border-slate-100 pb-3">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-brand-600">Điểm số & Diễn giải</span>
                    <h2 id="sec-scores-title" class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <span>📊</span> Kết quả chi tiết của bạn
                    </h2>
                </div>
                <span class="px-3 py-1 bg-purple-50 text-brand-700 font-bold text-xs rounded-full border border-purple-200">
                    <?=e($summaryLabel)?>
                </span>
            </div>

            <!-- Score visualization by test type -->
            <?php if ($type === 'holland'): ?>
                <div class="score-grid mb-4">
                    <?php foreach (riasec_labels() as $axis => $label):
                        $val = max(0, min(100, (int)($scores[$axis] ?? 0)));
                        $zVal = isset($zScores[$axis]) ? (float)$zScores[$axis] : null;
                        $effVal = isset($efficacyScores[$axis]) ? (int)$efficacyScores[$axis] : null;
                    ?>
                        <div class="score-item p-3 border border-slate-100 rounded-xl bg-slate-50/50 text-center">
                            <div class="score-ring mx-auto mb-2" style="--v:<?=$val?>;--c:<?=e(riasec_colors()[$axis])?>">
                                <span class="font-bold text-xs"><?=$val?>%</span>
                            </div>
                            <b class="text-xs block text-slate-800"><?=e($label)?> (<?=e($axis)?>)</b>
                            <?php if ($zVal !== null): ?>
                                <small class="text-[11px] text-slate-400 block">Z-score: <?=($zVal >= 0 ? '+' : '').number_format($zVal, 2)?></small>
                            <?php endif; ?>
                            <?php if ($effVal !== null): ?>
                                <small class="text-[11px] text-brand-600 font-semibold block">Tự tin: <?=$effVal?>/5</small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($type === 'big5'): ?>
                <?php $big5Insights = psychometric_big5_insights($scores); ?>
                <div class="space-y-4 mb-4">
                    <?php foreach (psychometric_big5_labels() as $axis => $label):
                        $val = max(0, min(100, (int)($scores[$axis] ?? 50)));
                    ?>
                        <div class="p-3.5 bg-slate-50/70 border border-slate-200/80 rounded-xl">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="font-bold text-sm text-slate-800"><?=e($label)?></span>
                                <span class="font-extrabold text-sm text-brand-600"><?=$val?>%</span>
                            </div>
                            <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden mb-2">
                                <div class="bg-brand-500 h-full rounded-full" style="width: <?=$val?>%"></div>
                            </div>
                            <p class="text-xs text-slate-600 leading-relaxed m-0">
                                <?=e($big5Insights[$axis] ?? '')?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($type === 'caas'): ?>
                <?php $caasInsights = psychometric_caas_insights($scores); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                    <?php foreach (psychometric_caas_labels() as $dim => $label):
                        $val = max(0, min(100, (int)($scores[$dim] ?? 50)));
                    ?>
                        <div class="p-4 bg-slate-50/70 border border-slate-200/80 rounded-xl flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-bold text-xs text-slate-800"><?=e($label)?></span>
                                    <span class="font-bold text-xs text-emerald-600"><?=$val?>%</span>
                                </div>
                                <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden mb-2">
                                    <div class="bg-emerald-500 h-full rounded-full" style="width: <?=$val?>%"></div>
                                </div>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    <?=e($caasInsights[$dim] ?? '')?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($type === 'mbti'): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 mb-4 text-center">
                    <?php foreach (['EI' => ['Hướng ngoại (E)', 'Hướng nội (I)'], 
                                   'SN' => ['Cụ thể (S)', 'Khái quát (N)'], 
                                   'TF' => ['Lý trí (T)', 'Cảm xúc (F)'], 
                                   'JP' => ['Kỷ luật (J)', 'Linh hoạt (P)']] as $axis => $names):
                        $valA = max(0, min(100, (int)($scores[$axis][$axis[0]] ?? 50)));
                        $valB = 100 - $valA;
                    ?>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="font-bold text-xs text-slate-500 block mb-1">Trục <?=e($axis)?></span>
                            <div class="text-xs font-bold text-slate-800 mb-1">
                                <?=e($axis[0])?> <?=$valA?>% vs <?=$valB?>% <?=e($axis[1])?>
                            </div>
                            <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden flex">
                                <div class="bg-brand-500 h-full" style="width: <?=$valA?>%"></div>
                                <div class="bg-indigo-400 h-full" style="width: <?=$valB?>%"></div>
                            </div>
                            <span class="text-[10px] text-slate-400 mt-1 block">
                                Nghiêng về: <strong><?=$valA > $valB ? $names[0] : ($valA < $valB ? $names[1] : 'Cân bằng')?></strong>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="p-3 bg-purple-50/60 border border-purple-100 rounded-xl text-xs text-slate-700 space-y-1">
                    <strong>Đặc trưng phong cách:</strong>
                    <ul class="list-disc pl-4 space-y-0.5 mt-1">
                        <?php foreach (psychometric_style_descriptions($resultCode) as $desc): ?>
                            <li><?=e($desc)?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif ($type === 'eq'): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                    <?php foreach (psychometric_eq_labels() as $axis => $label):
                        $val = max(0, min(100, (int)($scores[$axis] ?? 0)));
                    ?>
                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-center">
                            <span class="text-xs text-slate-500 font-semibold block mb-1"><?=e($label)?></span>
                            <div class="text-lg font-black text-rose-600 mb-1"><?=$val?>%</div>
                            <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                                <div class="bg-rose-500 h-full rounded-full" style="width: <?=$val?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Response Quality Audit Box (Minh bạch khoa học) -->
            <div class="mt-4 p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-600 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span>🔍</span>
                    <span><strong>Kiểm định chất lượng phản hồi:</strong> 
                        <?php if ($validityFlag === 'valid'): ?>
                            <span class="text-emerald-700 font-semibold">Đạt chuẩn độ tin cậy</span>
                        <?php elseif ($validityFlag === 'rushed'): ?>
                            <span class="text-amber-700 font-semibold">Hoàn thành nhanh (cần đọc kỹ hơn)</span>
                        <?php elseif ($validityFlag === 'flatline'): ?>
                            <span class="text-amber-700 font-semibold">Điểm số phân bố đều</span>
                        <?php else: ?>
                            <span class="text-amber-700 font-semibold">Có câu thuận/đảo mâu thuẫn</span>
                        <?php endif; ?>
                        <?php if ($durationSeconds > 0): ?>
                            · Thời gian: <?=round($durationSeconds / 60, 1)?> phút
                        <?php endif; ?>
                    </span>
                </div>
                <span class="text-[11px] text-slate-400">Không dùng AI / Điểm số tính toán xác định</span>
            </div>
        </section>

        <!-- MỤC D: VÍ DỤ BIỂU HIỆN TRONG HỌC TẬP VÀ ĐỜI SỐNG HẰNG NGÀY -->
        <section class="card p-6 border border-slate-200 shadow-sm" aria-labelledby="sec-examples-title">
            <div class="mb-4 border-b border-slate-100 pb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Quan sát thực tế</span>
                <h2 id="sec-examples-title" class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span>💡</span> Ví dụ biểu hiện trong học tập & hoạt động thường ngày
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Các nét đặc trưng này thường được bộc lộ rõ qua những tình huống học tập và sinh hoạt quen thuộc sau:
                </p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <?php foreach ($dailyExamples as $ex): ?>
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/80 flex flex-col justify-between">
                        <div>
                            <span class="font-bold text-xs text-brand-700 block mb-1.5">📌 <?=e($ex['context'])?></span>
                            <p class="text-xs text-slate-600 leading-relaxed m-0">
                                <?=e($ex['example'])?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- MỤC E: HỒ SƠ TỔNG HỢP TÍCH LŨY (MỤC 4 - 4 GÓC NHÌN) -->
        <section class="card p-6 border border-purple-200 bg-gradient-to-br from-purple-50/40 via-white to-indigo-50/30 shadow-sm" aria-labelledby="sec-synthesis-title">
            <div class="mb-4 border-b border-purple-100 pb-3">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-brand-600">Cơ chế tích lũy dữ liệu</span>
                        <h2 id="sec-synthesis-title" class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span>🔄</span> Hồ sơ được tích lũy như thế nào sau bài đánh giá này?
                        </h2>
                    </div>
                    <span class="badge badge-purple text-xs">Cập nhật qua <?=$progress['completed_count']?>/5 bài</span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Mỗi bài đánh giá bổ sung thêm một lớp thông tin mới, làm sâu sắc thêm các nhận định trước đó mà không dùng chỉ số phỏng đoán thiếu căn cứ.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <!-- 1. Thông tin mới -->
                <div class="p-4 bg-white rounded-xl border border-purple-200 shadow-sm">
                    <div class="flex items-center gap-2 font-bold text-brand-800 text-sm mb-1.5">
                        <span class="w-6 h-6 rounded-full bg-purple-100 text-brand-700 flex items-center justify-center text-xs">1</span>
                        <span>Thông tin mới nhận được</span>
                    </div>
                    <div class="text-slate-600 leading-relaxed">
                        <?=$synthesis['new_info']?>
                    </div>
                </div>

                <!-- 2. Điểm tiếp tục được ghi nhận -->
                <div class="p-4 bg-white rounded-xl border border-blue-200 shadow-sm">
                    <div class="flex items-center gap-2 font-bold text-blue-800 text-sm mb-1.5">
                        <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs">2</span>
                        <span>Điểm tiếp tục được ghi nhận từ bài trước</span>
                    </div>
                    <div class="text-slate-600 leading-relaxed">
                        <?=$synthesis['continued_patterns']?>
                    </div>
                </div>

                <!-- 3. Góc nhìn bổ sung -->
                <div class="p-4 bg-white rounded-xl border border-emerald-200 shadow-sm">
                    <div class="flex items-center gap-2 font-bold text-emerald-800 text-sm mb-1.5">
                        <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs">3</span>
                        <span>Góc nhìn bổ sung giúp làm rõ nhận định cũ</span>
                    </div>
                    <div class="text-slate-600 leading-relaxed">
                        <?=$synthesis['supplementary_angles']?>
                    </div>
                </div>

                <!-- 4. Điều cần kiểm chứng thêm -->
                <div class="p-4 bg-white rounded-xl border border-amber-200 shadow-sm">
                    <div class="flex items-center gap-2 font-bold text-amber-800 text-sm mb-1.5">
                        <span class="w-6 h-6 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-xs">4</span>
                        <span>Điều cần kiểm chứng thêm ở bài tiếp theo</span>
                    </div>
                    <div class="text-slate-600 leading-relaxed">
                        <?=$synthesis['to_verify_next']?>
                    </div>
                </div>
            </div>
        </section>

        <!-- MỤC F: ĐIỀU CHƯA ĐỦ THÔNG TIN ĐỂ KẾT LUẬN & GIỚI HẠN PHƯƠNG PHÁP -->
        <section class="notice-box bg-amber-50/70 border border-amber-200 text-amber-900 rounded-2xl p-5" aria-labelledby="sec-limits-title">
            <h2 id="sec-limits-title" class="text-sm font-bold flex items-center gap-2 mb-2">
                <span>⚠️</span> Điều chưa đủ thông tin để kết luận & Giới hạn phương pháp
            </h2>
            <div class="space-y-2.5 text-xs text-amber-800 leading-relaxed">
                <?php foreach ($limits as $lim): ?>
                    <div class="p-3 bg-white/80 rounded-xl border border-amber-200/80">
                        <strong class="text-amber-950 block mb-0.5"><?=e($lim['title'])?>:</strong>
                        <span><?=e($lim['text'])?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- MỤC G: GỢI Ý HÀNH ĐỘNG NHỎ & PHẢN HỒI CỦA HỌC SINH -->
        <section class="card p-6 border border-slate-200 shadow-sm" aria-labelledby="sec-action-title">
            <div class="mb-4 border-b border-slate-100 pb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Hành động & Phản tư</span>
                <h2 id="sec-action-title" class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span>✍️</span> Gợi ý việc làm ngay & Lắng nghe cảm nhận của bạn
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Bạn là người hiểu rõ bản thân nhất. Hãy thử những hành động nhỏ này và ghi lại những điểm bạn thấy chưa chính xác.
                </p>
            </div>

            <!-- 2-3 Concrete Micro-Actions -->
            <div class="mb-6 space-y-2">
                <span class="font-bold text-xs text-slate-800 block">Việc nhỏ bạn có thể thử trong 24–48 giờ tới:</span>
                <ul class="list-none p-0 m-0 space-y-2">
                    <?php foreach ($smallActions as $idx => $act): ?>
                        <li class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 text-xs text-slate-700 flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold text-[11px] shrink-0 mt-0.5"><?=($idx+1)?></span>
                            <span><?=e($act)?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Student Feedback Form (Không sửa điểm gốc) -->
            <form method="post" action="<?=url('index.php?page=assessment-reflection-save')?>" class="p-4 bg-slate-50/70 rounded-xl border border-slate-200 space-y-3">
                <?=csrf_field()?>
                <input type="hidden" name="test_type" value="<?=e($type)?>">
                <input type="hidden" name="assessment_id" value="<?=$recordId?>">

                <div>
                    <label class="block text-xs font-bold text-slate-800 mb-2">
                        Bạn cảm thấy kết quả của bài này phản ánh bạn ở mức độ nào?
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <?php 
                        $curFeeling = $studentReflection['fit_feeling'] ?? 'agree';
                        foreach ([
                            'agree' => ['label' => 'Rất phù hợp với em', 'icon' => '👍'],
                            'partially_agree' => ['label' => 'Phù hợp một phần', 'icon' => '🤔'],
                            'disagree' => ['label' => 'Chưa phản ánh đúng em', 'icon' => '👎'],
                        ] as $fKey => $fMeta): ?>
                            <label class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 bg-white hover:border-brand-300 cursor-pointer text-xs has-[:checked]:border-brand-500 has-[:checked]:bg-purple-50">
                                <input type="radio" name="fit_feeling" value="<?=$fKey?>" class="sr-only" <?=$curFeeling === $fKey ? 'checked' : ''?>>
                                <span><?=$fMeta['icon']?></span>
                                <span class="font-semibold text-slate-700"><?=$fMeta['label']?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label for="student_notes" class="block text-xs font-bold text-slate-800 mb-1">
                        Ghi chú phản tư của bạn ("Kết quả này chưa giống em ở điểm nào hoặc cần điều chỉnh gì?"):
                    </label>
                    <textarea id="student_notes" name="student_notes" rows="3" class="input w-full text-xs" 
                              placeholder="Ví dụ: Em thấy phần này đúng ở điểm..., nhưng em thấy mình giao tiếp nhóm tốt hơn kết quả phản ánh vì..."><?=e($studentReflection['student_notes'] ?? '')?></textarea>
                    <p class="text-[11px] text-slate-400 mt-1">Phản tư này được lưu an toàn vào hồ sơ của bạn làm bằng chứng đối chiếu và không thay đổi điểm số đo lường gốc.</p>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        💾 Lưu phản tư cá nhân
                    </button>
                    <?php if (!empty($studentReflection['updated_at'])): ?>
                        <span class="text-[11px] text-slate-400">Đã cập nhật lúc: <?=date('d/m/Y H:i', strtotime($studentReflection['updated_at']))?></span>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- MỤC H: BÀI TIẾP THEO & LÝ DO NÊN TIẾP TỤC -->
        <section class="card p-6 bg-gradient-to-r from-brand-50 via-purple-50 to-indigo-50 border border-brand-200 shadow-sm" aria-labelledby="sec-next-step-title">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-brand-700 block mb-1">Bước tiếp theo trong hành trình</span>
                    <?php if ($progress['all_completed']): ?>
                        <h2 id="sec-next-step-title" class="text-lg font-black text-slate-900 mb-1">
                            🎉 Bạn đã hoàn thành trọn vẹn 5/5 bài đánh giá!
                        </h2>
                        <p class="text-xs md:text-sm text-slate-600 max-w-xl">
                            Toàn bộ hồ sơ 5 chiều của bạn đã sẵn sàng. Hãy mở Báo cáo tổng hợp để xem gợi ý nghề, ngành đào tạo từ danh mục 358 ngành, đối chiếu điểm học kỳ với điểm chuẩn thực tế và lập Kế hoạch hành động.
                        </p>
                    <?php elseif ($nextStepInfo): ?>
                        <h2 id="sec-next-step-title" class="text-lg font-black text-slate-900 mb-1">
                            Bài <?=$nextStepInfo['step_number']?>: <?=e($nextStepInfo['title'])?> (<?=e($nextStepInfo['short_name'])?>)
                        </h2>
                        <p class="text-xs md:text-sm text-slate-600 max-w-xl">
                            <strong>Lý do nên tiếp tục:</strong> <?=e($nextStepInfo['summary_intro'])?> Bài này sẽ giúp bạn giải đáp những câu hỏi còn bỏ ngỏ ở Mục 4 và đưa hồ sơ của bạn lên tiến độ <strong><?=$nextStepInfo['step_number']?>/5</strong>.
                        </p>
                    <?php else: ?>
                        <h2 id="sec-next-step-title" class="text-lg font-black text-slate-900 mb-1">
                            Tiếp tục hoàn thiện các bài còn thiếu
                        </h2>
                        <p class="text-xs md:text-sm text-slate-600 max-w-xl">
                            Mỗi bài đánh giá chỉ mất từ 2–4 phút. Hãy tiếp tục để nhận được bức tranh toàn diện nhất.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="shrink-0">
                    <?php if ($progress['all_completed']): ?>
                        <a class="btn btn-primary btn-lg whitespace-nowrap shadow-md" href="<?=url('index.php?page=assessment-report')?>">
                            Mở Báo cáo tổng hợp 5 bài →
                        </a>
                    <?php elseif ($nextStepInfo): ?>
                        <a class="btn btn-primary btn-lg whitespace-nowrap shadow-md" href="<?=url('index.php?page=' . $nextStepInfo['route'])?>">
                            Bắt đầu Bài <?=$nextStepInfo['step_number']?>: <?=e($nextStepInfo['short_name'])?> →
                        </a>
                    <?php else: ?>
                        <a class="btn btn-primary btn-lg whitespace-nowrap shadow-md" href="<?=url('index.php?page=assessment-hub')?>">
                            Xem danh mục bài đánh giá →
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    </div>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

/**
 * Render 5-instrument comprehensive synthesis report (Mục 6).
 */
function assessment_render_comprehensive_report(int $userId): void
{
    $progress = assessment_get_journey_progress($userId);
    $steps = $progress['steps'];
    $completedCount = $progress['completed_count'];
    $allDone = $progress['all_completed'];

    $holland = $steps['holland']['data'] ?? null;
    $big5 = $steps['big5']['data'] ?? null;
    $caas = $steps['caas']['data'] ?? null;
    $mbti = $steps['mbti']['data'] ?? null;
    $eq = $steps['eq']['data'] ?? null;

    $hCode = $holland ? (string)$holland['result_code'] : '';
    $hScores = $holland ? (json_decode($holland['scores'] ?? '{}', true) ?: []) : [];
    $bScores = $big5 ? (json_decode($big5['scores_json'] ?? '{}', true) ?: []) : [];
    $cScores = $caas ? (json_decode($caas['scores_json'] ?? '{}', true) ?: []) : [];
    $mCode = $mbti ? (string)$mbti['result_code'] : '';
    $eScores = $eq ? (json_decode($eq['scores_json'] ?? '{}', true) ?: []) : [];

    $consistenciesData = assessment_get_consistencies_and_discrepancies($userId);
    $consistencies = $consistenciesData['consistencies'];
    $discrepancies = $consistenciesData['discrepancies'];

    // Career Matches from assessment_matches
    $matches = [];
    $careerIds = [];
    if ($holland) {
        $stmt = db()->prepare("SELECT c.id, c.title, c.slug, c.icon, c.short_description, c.work_environment, c.skills, c.subjects, m.match_percent, m.rank_order 
                              FROM assessment_matches m 
                              JOIN careers c ON c.id = m.career_id 
                              WHERE m.assessment_id = ? AND c.status = 'published' 
                              ORDER BY m.rank_order LIMIT 5");
        $stmt->execute([(int)$holland['id']]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$matches) {
            recommendations_refresh_current($userId);
            $stmt->execute([(int)$holland['id']]);
            $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        foreach ($matches as $m) {
            $careerIds[] = (int)$m['id'];
        }
    }

    // Get matching training majors and admissions
    $trainingMajors = assessment_get_matching_training_majors($careerIds, $hCode, $bScores);
    $academicProfile = assessment_get_academic_profile($userId);
    $calculatedCombos = assessment_calculate_combinations_scores($academicProfile['subject_scores']);
    $matchingAdmissions = assessment_get_matching_admissions($careerIds, $academicProfile['target_region']);

    // Check user simulations
    $userSims = [];
    try {
        $simStmt = db()->prepare('SELECT career_id, score, reflection_feeling, reflection_note FROM simulation_attempts WHERE user_id=? ORDER BY completed_at DESC');
        $simStmt->execute([$userId]);
        foreach ($simStmt->fetchAll(PDO::FETCH_ASSOC) as $srow) {
            if (!isset($userSims[$srow['career_id']])) {
                $userSims[$srow['career_id']] = $srow;
            }
        }
    } catch (Throwable $e) {}

    // Check user roadmaps
    $hasRoadmap = false;
    $roadmapTasks = [];
    try {
        $rStmt = db()->prepare('SELECT * FROM user_roadmap_milestones WHERE user_id=? ORDER BY is_completed ASC, due_date ASC LIMIT 6');
        $rStmt->execute([$userId]);
        $roadmapTasks = $rStmt->fetchAll(PDO::FETCH_ASSOC);
        $hasRoadmap = count($roadmapTasks) > 0;
    } catch (Throwable $e) {}

    $pageTitle = 'Báo cáo Đánh giá Tổng hợp Đa chiều';
    require __DIR__ . '/header.php';
    ?>
    <section class="page-head bg-slate-900 text-white py-8">
        <div class="container">
            <nav class="breadcrumb mb-3 text-slate-300 text-xs" aria-label="Đường dẫn trang">
                <a href="<?=url('index.php?page=home')?>" class="hover:text-white">Trang chủ</a>
                <span class="breadcrumb-sep">/</span>
                <a href="<?=url('index.php?page=assessment-hub')?>" class="hover:text-white">Hiểu bản thân</a>
                <span class="breadcrumb-sep">/</span>
                <span aria-current="page" class="text-white font-semibold">Báo cáo tổng hợp đa chiều</span>
            </nav>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?=$allDone ? 'bg-emerald-500/30 text-emerald-200 border border-emerald-400/30' : 'bg-brand-500/30 text-brand-200 border border-brand-400/30'?> mb-2">
                        <span><?=$allDone ? '🏆' : '🔄'?></span>
                        <span><?=$allDone ? 'Hồ sơ hoàn chỉnh 5/5 bài đánh giá' : 'Tiến độ: Đã làm ' . $completedCount . '/5 bài'?></span>
                    </span>
                    <h1 class="page-title text-2xl md:text-3xl font-black text-white mb-1">
                        Hồ sơ trải nghiệm & Báo cáo hướng nghiệp đa chiều
                    </h1>
                    <p class="text-slate-300 text-xs md:text-sm">
                        Tổng hợp khoa học từ Sở thích, Tính cách, Khả năng thích ứng, Phong cách tương tác và Cảm xúc
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="window.print()" class="btn btn-outline btn-sm bg-white/10 hover:bg-white/20 text-white border-white/20">
                        🖨️ In / Tải PDF
                    </button>
                    <a class="btn btn-outline btn-sm bg-white/10 hover:bg-white/20 text-white border-white/20" href="<?=url('index.php?page=assessment-hub')?>">
                        ← Bộ công cụ đánh giá
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="container py-8 space-y-8">

        <!-- TIẾN ĐỘ THỰC TẾ TRACKER -->
        <?=assessment_render_journey_tracker($progress, '')?>

        <?php if (!$allDone): ?>
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl text-xs text-amber-800 flex items-start gap-3">
                <span class="text-lg">ℹ️</span>
                <div>
                    <strong>Lưu ý về tính hoàn thiện của báo cáo:</strong> Bạn đã hoàn thành <?=$completedCount?>/5 bài đánh giá. Các bài bạn đã làm vẫn được lưu và trình bày bên dưới. Phần xếp hạng nghề hiện cần kết quả Holland cùng bước điều kiện học tập; thông tin còn thiếu không bị tính thành điểm thấp. Để có bức tranh đầy đủ và mở khóa toàn bộ gợi ý đối chiếu tuyển sinh, bạn nên hoàn thành các bài còn lại.
                </div>
            </div>
        <?php else: ?>
            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs text-emerald-800 flex items-start gap-3">
                <span class="text-lg">🎉</span>
                <div>
                    <strong>Báo cáo tổng hợp đã hoàn thiện:</strong> Bạn đã hoàn thành đủ 5/5 bài đánh giá chuẩn hóa. Các bài bạn đã làm vẫn được lưu và tích hợp đầy đủ 5 chiều tâm lý với danh mục 358 ngành đào tạo đại học và cơ sở dữ liệu điểm chuẩn tuyển sinh.
                </div>
            </div>
        <?php endif; ?>

        <!-- MỤC A: TÓM TẮT HỒ SƠ ĐA CHIỀU (5 Chiều) -->
        <section class="card p-6 border border-slate-200 shadow-sm" aria-labelledby="sec-5dim-title">
            <div class="mb-4 border-b border-slate-100 pb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-brand-600">Bức tranh toàn diện</span>
                <h2 id="sec-5dim-title" class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span>🧭</span> A. Tóm tắt hồ sơ năng lực 5 chiều
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Mỗi chiều đo lường một khía cạnh riêng biệt của con người bạn, kết hợp lại thành bức tranh định hướng phong phú.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 text-xs">
                <!-- 1. Sở thích Holland -->
                <div class="p-3.5 rounded-xl border border-purple-200 bg-purple-50/50 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-extrabold text-[11px] text-purple-700">01. SỞ THÍCH</span>
                            <span class="badge badge-purple text-[10px]"><?=$holland ? 'Đã làm' : 'Chưa làm'?></span>
                        </div>
                        <strong class="text-sm text-slate-900 block mb-1"><?=$hCode ? 'Mã ' . e($hCode) : 'Holland RIASEC'?></strong>
                        <p class="text-slate-600 line-clamp-3 leading-relaxed">
                            <?=$holland ? 'Xu hướng nổi trội trong 6 nhóm sở thích nghề nghiệp O*NET.' : 'Chưa hoàn thành khảo sát 30 câu.'?>
                        </p>
                    </div>
                    <div class="pt-2 border-t border-purple-100 mt-2">
                        <a href="<?=url('index.php?page=' . ($holland ? 'assessment-result&type=holland' : 'assessment'))?>" class="font-bold text-brand-600 hover:underline">
                            <?=$holland ? 'Xem chi tiết →' : 'Làm bài →'?>
                        </a>
                    </div>
                </div>

                <!-- 2. Tính cách Big Five -->
                <div class="p-3.5 rounded-xl border border-blue-200 bg-blue-50/50 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-extrabold text-[11px] text-blue-700">02. TÍNH CÁCH</span>
                            <span class="badge badge-subtle text-[10px]"><?=$big5 ? 'Đã làm' : 'Chưa làm'?></span>
                        </div>
                        <strong class="text-sm text-slate-900 block mb-1"><?=$big5 ? e($big5['result_code']) : 'Big Five (Mini-IPIP)'?></strong>
                        <p class="text-slate-600 line-clamp-3 leading-relaxed">
                            <?=$big5 ? '5 nét tính cách: Cởi mở, Kỷ luật, Hướng ngoại, Hòa đồng, Vững vàng.' : 'Chưa làm trắc nghiệm 20 câu.'?>
                        </p>
                    </div>
                    <div class="pt-2 border-t border-blue-100 mt-2">
                        <a href="<?=url('index.php?page=' . ($big5 ? 'assessment-result&type=big5' : 'test-big5'))?>" class="font-bold text-blue-600 hover:underline">
                            <?=$big5 ? 'Xem chi tiết →' : 'Làm bài →'?>
                        </a>
                    </div>
                </div>

                <!-- 3. Thích ứng CAAS -->
                <div class="p-3.5 rounded-xl border border-emerald-200 bg-emerald-50/50 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-extrabold text-[11px] text-emerald-700">03. THÍCH ỨNG</span>
                            <span class="badge badge-subtle text-[10px]"><?=$caas ? 'Đã làm' : 'Chưa làm'?></span>
                        </div>
                        <strong class="text-sm text-slate-900 block mb-1"><?=$caas ? e($caas['result_code']) : 'Thích ứng CAAS'?></strong>
                        <p class="text-slate-600 line-clamp-3 leading-relaxed">
                            <?=$caas ? 'Năng lực Quan tâm, Tự chủ, Tò mò và Tự tin vượt khó tương lai.' : 'Chưa làm thang đo 12 câu.'?>
                        </p>
                    </div>
                    <div class="pt-2 border-t border-emerald-100 mt-2">
                        <a href="<?=url('index.php?page=' . ($caas ? 'assessment-result&type=caas' : 'test-caas'))?>" class="font-bold text-emerald-600 hover:underline">
                            <?=$caas ? 'Xem chi tiết →' : 'Làm bài →'?>
                        </a>
                    </div>
                </div>

                <!-- 4. MBTI Ra quyết định -->
                <div class="p-3.5 rounded-xl border border-amber-200 bg-amber-50/50 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-extrabold text-[11px] text-amber-700">04. RA QUYẾT ĐỊNH</span>
                            <span class="badge badge-subtle text-[10px]"><?=$mbti ? 'Đã làm' : 'Chưa làm'?></span>
                        </div>
                        <strong class="text-sm text-slate-900 block mb-1"><?=$mbti ? e($mbti['result_code']) : 'MBTI rút gọn'?></strong>
                        <p class="text-slate-600 line-clamp-3 leading-relaxed">
                            <?=$mbti ? 'Phong cách xử lý thông tin (S-N), ra quyết định (T-F) và kế hoạch (J-P).' : 'Chưa làm khảo sát 12 câu.'?>
                        </p>
                    </div>
                    <div class="pt-2 border-t border-amber-100 mt-2">
                        <a href="<?=url('index.php?page=' . ($mbti ? 'assessment-result&type=mbti' : 'test-mbti'))?>" class="font-bold text-amber-600 hover:underline">
                            <?=$mbti ? 'Xem chi tiết →' : 'Làm bài →'?>
                        </a>
                    </div>
                </div>

                <!-- 5. Trí tuệ cảm xúc EQ -->
                <div class="p-3.5 rounded-xl border border-rose-200 bg-rose-50/50 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-extrabold text-[11px] text-rose-700">05. CẢM XÚC & EQ</span>
                            <span class="badge badge-subtle text-[10px]"><?=$eq ? 'Đã làm' : 'Chưa làm'?></span>
                        </div>
                        <strong class="text-sm text-slate-900 block mb-1"><?=$eq ? 'Đã phản tư' : 'Chỉ số EQ'?></strong>
                        <p class="text-slate-600 line-clamp-3 leading-relaxed">
                            <?=$eq ? 'Khả năng tự điều hòa cảm xúc, thấu cảm nhóm và duy trì động lực.' : 'Chưa làm 10 tình huống.'?>
                        </p>
                    </div>
                    <div class="pt-2 border-t border-rose-100 mt-2">
                        <a href="<?=url('index.php?page=' . ($eq ? 'assessment-result&type=eq' : 'test-eq'))?>" class="font-bold text-rose-600 hover:underline">
                            <?=$eq ? 'Xem chi tiết →' : 'Làm bài →'?>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- MỤC B: ĐIỂM THỐNG NHẤT GIỮA CÁC BÀI -->
        <section class="card p-6 border border-slate-200 shadow-sm" aria-labelledby="sec-consistencies-title">
            <div class="mb-3 border-b border-slate-100 pb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Điểm đồng quy</span>
                <h2 id="sec-consistencies-title" class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span>✨</span> B. Điểm thống nhất xuất hiện lặp lại ở nhiều bài đánh giá
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Khi một đặc điểm được xác nhận qua nhiều công cụ đo độc lập, đây là tín hiệu mạnh về thế mạnh tự nhiên của bạn.
                </p>
            </div>
            <div class="space-y-3">
                <?php foreach ($consistencies as $con): ?>
                    <div class="p-4 bg-emerald-50/50 border border-emerald-200 rounded-xl text-xs">
                        <div class="flex items-center justify-between mb-1">
                            <strong class="text-sm font-bold text-emerald-900"><?=e($con['theme'])?></strong>
                            <div class="flex gap-1">
                                <?php foreach ($con['sources'] as $src): ?>
                                    <span class="badge badge-subtle text-[10px]"><?=e($src)?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <p class="text-slate-700 leading-relaxed m-0">
                            <?=e($con['detail'])?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- MỤC C: ĐIỂM KHÁC BIỆT CẦN CHÚ Ý & CÂU HỎI TỰ SUY NGẪM -->
        <section class="card p-6 border border-slate-200 shadow-sm" aria-labelledby="sec-discrepancies-title">
            <div class="mb-3 border-b border-slate-100 pb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-700">Điểm cần lưu ý</span>
                <h2 id="sec-discrepancies-title" class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span>🔍</span> C. Điểm khác biệt cần chú ý & Câu hỏi gợi mở
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Sự khác biệt giữa sở thích và tính cách không phải là khuyết điểm, mà là những sắc thái thú vị giúp bạn hiểu sâu hơn về môi trường làm việc mình cần.
                </p>
            </div>

            <?php if (empty($discrepancies)): ?>
                <div class="p-4 bg-slate-50 rounded-xl text-xs text-slate-600">
                    Chưa phát hiện mâu thuẫn lớn giữa các bài đánh giá của bạn. Các kết quả có độ tương thích cao.
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($discrepancies as $disc): ?>
                        <div class="p-4 bg-amber-50/50 border border-amber-200 rounded-xl text-xs">
                            <strong class="text-sm font-bold text-amber-950 block mb-1">⚠️ <?=e($disc['title'])?></strong>
                            <p class="text-slate-700 leading-relaxed mb-2"><?=e($disc['detail'])?></p>
                            <div class="p-3 bg-white/80 rounded-lg border border-amber-200/80 text-amber-900 italic">
                                💬 <strong>Câu hỏi gợi mở:</strong> <?=e($disc['reflective_question'])?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- MỤC D: GỢI Ý NGHỀ NGHIỆP (3–5 NGHỀ CỤ THỂ) -->
        <section class="card p-6 border border-slate-200 shadow-sm" aria-labelledby="sec-careers-title">
            <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-3">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-brand-600">Khám phá nghề</span>
                    <h2 id="sec-careers-title" class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <span>💼</span> D. Gợi ý nghề nghiệp phù hợp (Top <?=count($matches)?> vị trí)
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Xếp hạng dựa trên sở thích Holland chuẩn hóa nội tại, nét tính cách Big Five và điều kiện bạn đã khai.
                    </p>
                </div>
                <span class="badge badge-subtle text-xs">Hiển thị 3–5 nghề</span>
            </div>

            <?php if (!$matches): ?>
                <div class="p-6 bg-slate-50 rounded-xl text-center text-xs text-slate-500">
                    Chưa có danh sách nghề gợi ý. Hãy hoàn tất bài Holland và khảo sát điều kiện thực tế để kích hoạt gợi ý.
                    <div class="mt-3">
                        <a href="<?=url('index.php?page=assessment')?>" class="btn btn-primary btn-sm">Làm bài Holland →</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($matches as $job): 
                        $simAttempt = $userSims[$job['id']] ?? null;
                    ?>
                        <article class="p-4 bg-slate-50/70 border border-slate-200 rounded-xl hover:border-brand-300 transition-all text-xs">
                            <div class="flex flex-wrap items-start justify-between gap-3 mb-2">
                                <div class="flex items-center gap-3">
                                    <span class="text-3xl p-2 bg-white rounded-xl border border-slate-200"><?=e($job['icon']?:'💼')?></span>
                                    <div>
                                        <h3 class="font-bold text-base text-slate-900">
                                            <a href="<?=url('index.php?page=career&id='.$job['id'])?>" class="hover:text-brand-600">
                                                <?=e($job['title'])?>
                                            </a>
                                        </h3>
                                        <span class="text-slate-400 text-[11px]">Mức độ tương đồng hồ sơ: <strong><?=(int)$job['match_percent']?>%</strong></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a class="btn btn-outline btn-sm" href="<?=url('index.php?page=career&id='.$job['id'])?>">Chi tiết nghề →</a>
                                    <?php if ($job['id'] == 5 || $job['slug'] === 'giao-vien'): ?>
                                        <a class="btn btn-primary btn-sm" href="<?=url('index.php?page=simulation-3d')?>">🎮 Thử mô phỏng 3D →</a>
                                    <?php else: ?>
                                        <a class="btn btn-primary btn-sm" href="<?=url('index.php?page=simulation&id='.$job['id'])?>">Thử phòng 2D →</a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <p class="text-slate-600 leading-relaxed mb-3"><?=e($job['short_description'])?></p>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5 bg-white p-3 rounded-lg border border-slate-200/80 text-[11px]">
                                <div>
                                    <span class="font-bold text-slate-700 block mb-0.5">🏢 Môi trường làm việc:</span>
                                    <span class="text-slate-500"><?=e($job['work_environment'] ?: 'Văn phòng, linh hoạt dự án hoặc hiện trường chuyên môn.')?></span>
                                </div>
                                <div>
                                    <span class="font-bold text-slate-700 block mb-0.5">🧠 Kỹ năng & Môn học:</span>
                                    <span class="text-slate-500"><?=e($job['subjects'] ?: 'Toán, Tiếng Anh, Kỹ năng số.')?></span>
                                </div>
                                <div>
                                    <span class="font-bold text-slate-700 block mb-0.5">⚡ Thách thức cần lưu ý:</span>
                                    <span class="text-slate-500">Áp lực tiến độ, rèn luyện kỹ năng liên tục và khả năng tự học độc lập.</span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- MỤC E: GỢI Ý NGÀNH HỌC (TỪ 358 NGÀNH ĐÀO TẠO VIỆT NAM) -->
        <section class="card p-6 border border-slate-200 shadow-sm" aria-labelledby="sec-majors-title">
            <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-3">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-brand-600">Danh mục đào tạo</span>
                    <h2 id="sec-majors-title" class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <span>🎓</span> E. Gợi ý ngành học đào tạo (từ danh mục 358 ngành chuẩn)
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Các ngành đào tạo bậc Đại học tại Việt Nam có mối liên hệ mật thiết với các nghề gợi ý trong hồ sơ của bạn.
                    </p>
                </div>
                <a href="<?=url('index.php?page=majors')?>" class="text-xs font-bold text-brand-600 hover:underline">Xem tất cả 358 ngành →</a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                <?php foreach ($trainingMajors as $tm): ?>
                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200/80 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <span class="badge badge-subtle text-[10px]"><?=e($tm['field_name'] ?: 'Khối ngành đào tạo')?></span>
                                <?php if (!empty($tm['official_major_code'])): ?>
                                    <span class="font-mono text-[10px] text-slate-500">Mã: <?=e($tm['official_major_code'])?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="font-bold text-sm text-slate-900 mb-1">
                                <a href="<?=url('index.php?page=major-detail&id='.$tm['id'])?>" class="hover:text-brand-600">
                                    <?=e($tm['name'])?>
                                </a>
                            </h3>
                            <p class="text-slate-600 line-clamp-2 leading-relaxed mb-2">
                                <?=e($tm['editorial_description'] ?: ($tm['source_page_title'] ?: 'Ngành học cung cấp kiến thức nền tảng và kỹ năng nghề nghiệp chuyên sâu.'))?>
                            </p>
                        </div>
                        <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-[11px]">
                            <span class="text-slate-400">Đào tạo: Đại học chính quy</span>
                            <a href="<?=url('index.php?page=lookup&q='.urlencode($tm['name']))?>" class="text-brand-600 font-bold hover:underline">
                                Tra cứu trường dạy →
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- MỤC F: GỢI Ý CƠ SỞ ĐÀO TẠO & ĐIỂM CHUẨN TUYỂN SINH THẬT -->
        <section class="card p-6 border border-slate-200 shadow-sm" aria-labelledby="sec-institutions-title">
            <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-3">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-brand-600">Dữ liệu tuyển sinh</span>
                    <h2 id="sec-institutions-title" class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <span>🏛️</span> F. Gợi ý cơ sở đào tạo & Điểm chuẩn tuyển sinh tham khảo
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Điểm chuẩn các năm gần nhất có ghi rõ năm tuyển sinh và nguồn đối chiếu chính thức.
                    </p>
                </div>
                <a href="<?=url('index.php?page=university-explorer')?>" class="text-xs font-bold text-brand-600 hover:underline">Khám phá các trường →</a>
            </div>

            <div class="space-y-3 text-xs">
                <?php foreach ($matchingAdmissions as $adm): ?>
                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-bold text-sm text-slate-900"><?=e($adm['inst_name'])?></span>
                                <span class="badge badge-subtle text-[10px]"><?=e($adm['inst_province'] ?: 'Toàn quốc')?></span>
                            </div>
                            <div class="text-slate-600 space-x-2">
                                <span>Chương trình: <strong><?=e($adm['program_name'])?></strong></span>
                                <?php if (!empty($adm['program_code'])): ?>
                                    <span class="text-slate-400 font-mono">(<?=e($adm['program_code'])?>)</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-[11px] text-slate-500 mt-1">
                                <span>Năm tuyển sinh: <strong><?=e($adm['admission_year'])?></strong></span> · 
                                <span>Phương thức: <?=e($adm['method'])?></span>
                            </div>
                        </div>

                        <div class="text-right">
                            <div class="text-sm font-black text-brand-600">
                                Điểm chuẩn: <?=number_format((float)$adm['cutoff_score'], 2)?> / <?=e($adm['score_scale'])?>
                            </div>
                            <?php if (!empty($adm['source_url'])): ?>
                                <a href="<?=e($adm['source_url'])?>" target="_blank" rel="noopener noreferrer" class="text-[11px] text-slate-400 hover:text-brand-600 hover:underline inline-block mt-0.5">
                                    Nguồn xác minh ↗
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-[11px] text-slate-400 mt-3 italic">
                * Lưu ý: Điểm chuẩn các năm trước mang tính tham khảo; phương thức và chỉ tiêu có thể điều chỉnh theo đề án tuyển sinh từng năm của nhà trường.
            </p>
        </section>

        <!-- MỤC G: ĐỐI CHIẾU ĐIỀU KIỆN HỌC TẬP & ĐIỂM HỌC KỲ (MỤC 5) -->
        <section class="card p-6 border border-brand-200 bg-purple-50/20 shadow-sm" aria-labelledby="sec-academic-title">
            <div class="mb-4 border-b border-purple-100 pb-3">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-brand-600">Điều kiện học tập</span>
                        <h2 id="sec-academic-title" class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span>📝</span> G. Đối chiếu điều kiện học tập & Điểm học kỳ của bạn
                        </h2>
                    </div>
                    <span class="badge badge-purple text-xs">Tổ hợp môn xét tuyển</span>
                </div>
                <p class="text-xs text-slate-600 mt-1">
                    Nhập điểm trung bình học kỳ các môn THPT để hệ thống tự động tính điểm theo các tổ hợp xét tuyển phổ biến (A00, A01, B00, C00, D01, D07, X06...) và đối chiếu với dữ liệu tuyển sinh.
                </p>
            </div>

            <!-- Academic Scores Form -->
            <form method="post" action="<?=url('index.php?page=academic-profile-save')?>" class="space-y-4 text-xs">
                <?=csrf_field()?>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="grade_level" class="font-bold text-slate-700 block mb-1">Khối lớp hiện tại:</label>
                        <select id="grade_level" name="grade_level" class="input w-full text-xs">
                            <?php foreach (['Lớp 10', 'Lớp 11', 'Lớp 12'] as $gr): ?>
                                <option value="<?=$gr?>" <?=$academicProfile['grade_level'] === $gr ? 'selected' : ''?>><?=$gr?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="academic_year" class="font-bold text-slate-700 block mb-1">Năm học:</label>
                        <select id="academic_year" name="academic_year" class="input w-full text-xs">
                            <?php foreach (['2024-2025', '2025-2026', '2023-2024'] as $yr): ?>
                                <option value="<?=$yr?>" <?=$academicProfile['academic_year'] === $yr ? 'selected' : ''?>><?=$yr?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="semester" class="font-bold text-slate-700 block mb-1">Kỳ đánh giá:</label>
                        <select id="semester" name="semester" class="input w-full text-xs">
                            <option value="sem1" <?=$academicProfile['semester'] === 'sem1' ? 'selected' : ''?>>Học kỳ 1</option>
                            <option value="sem2" <?=$academicProfile['semester'] === 'sem2' ? 'selected' : ''?>>Học kỳ 2</option>
                            <option value="whole_year" <?=$academicProfile['semester'] === 'whole_year' ? 'selected' : ''?>>Cả năm học</option>
                        </select>
                    </div>
                </div>

                <!-- 10 High School Subjects -->
                <div>
                    <label class="font-bold text-slate-800 block mb-2">Điểm trung bình học kỳ các môn (Thang điểm 10):</label>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                        <?php 
                        $subjectLabels = [
                            'toan' => 'Toán',
                            'van' => 'Ngữ văn',
                            'anh' => 'Tiếng Anh',
                            'ly' => 'Vật lý',
                            'hoa' => 'Hóa học',
                            'sinh' => 'Sinh học',
                            'su' => 'Lịch sử',
                            'dia' => 'Địa lý',
                            'tin' => 'Tin học',
                            'gdcd' => 'GDKT&PL',
                        ];
                        foreach ($subjectLabels as $sKey => $sName):
                            $val = $academicProfile['subject_scores'][$sKey] ?? '';
                        ?>
                            <div class="p-2.5 bg-white rounded-xl border border-slate-200">
                                <label for="sub_<?=$sKey?>" class="block font-bold text-slate-700 text-xs mb-1"><?=$sName?></label>
                                <input type="number" step="0.1" min="0" max="10" id="sub_<?=$sKey?>" 
                                       name="subject_scores[<?=$sKey?>]" value="<?=e((string)$val)?>" 
                                       placeholder="VD: 8.0" class="input input-sm w-full text-xs font-bold text-slate-900">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="target_region" class="font-bold text-slate-700 block mb-1">Khu vực học tập mong muốn:</label>
                        <select id="target_region" name="target_region" class="input w-full text-xs">
                            <option value="all" <?=$academicProfile['target_region'] === 'all' ? 'selected' : ''?>>Toàn quốc (Linh hoạt)</option>
                            <option value="north" <?=$academicProfile['target_region'] === 'north' ? 'selected' : ''?>>Miền Bắc (Hà Nội, Hải Phòng...)</option>
                            <option value="central" <?=$academicProfile['target_region'] === 'central' ? 'selected' : ''?>>Miền Trung (Đà Nẵng, Huế...)</option>
                            <option value="south" <?=$academicProfile['target_region'] === 'south' ? 'selected' : ''?>>Miền Nam (TP.HCM, Cần Thơ...)</option>
                        </select>
                    </div>
                    <div>
                        <label for="budget_yearly" class="font-bold text-slate-700 block mb-1">Dự trù học phí của gia đình (mỗi năm):</label>
                        <select id="budget_yearly" name="budget_yearly" class="input w-full text-xs">
                            <option value="unknown" <?=$academicProfile['budget_yearly'] === 'unknown' ? 'selected' : ''?>>Chưa rõ / Chưa muốn trả lời</option>
                            <option value="0" <?=$academicProfile['budget_yearly'] === '0' ? 'selected' : ''?>>Dưới 20 triệu đồng</option>
                            <option value="1" <?=$academicProfile['budget_yearly'] === '1' ? 'selected' : ''?>>20–50 triệu đồng</option>
                            <option value="2" <?=$academicProfile['budget_yearly'] === '2' ? 'selected' : ''?>>50–100 triệu đồng</option>
                            <option value="3" <?=$academicProfile['budget_yearly'] === '3' ? 'selected' : ''?>>Trên 100 triệu đồng</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        💾 Lưu điểm học kỳ & Cập nhật đối chiếu
                    </button>
                    <span class="text-slate-400 text-[11px]">Học sinh có thể tự nhập hoặc tạm bỏ qua nếu chưa có điểm.</span>
                </div>
            </form>

            <!-- Combinations Score Summary -->
            <div class="mt-6 pt-4 border-t border-purple-100">
                <span class="font-bold text-xs text-slate-800 block mb-2">Điểm ước tính theo các tổ hợp xét tuyển chính:</span>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2 text-xs">
                    <?php foreach ($calculatedCombos as $cCode => $cInfo): ?>
                        <div class="p-2.5 bg-white rounded-xl border border-slate-200">
                            <div class="flex items-center justify-between mb-0.5">
                                <strong class="font-bold text-slate-800"><?=$cCode?></strong>
                                <?php if ($cInfo['is_complete']): ?>
                                    <span class="font-extrabold text-sm text-brand-600"><?=$cInfo['total_score']?>đ</span>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-400">Thiếu điểm</span>
                                <?php endif; ?>
                            </div>
                            <span class="text-[10px] text-slate-400 block truncate" title="<?=$cInfo['name']?>"><?=$cInfo['name']?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="p-3 bg-white/70 rounded-xl border border-slate-200/80 mt-3 text-[11px] text-slate-500">
                    💡 <strong>Nguyên tắc khách quan:</strong> Hệ thống không tính "tỷ lệ đỗ" hay cam kết trúng tuyển. Việc so sánh điểm học kỳ với điểm chuẩn quá khứ chỉ nhằm giúp bạn biết cần nỗ lực bồi dưỡng thêm môn học nào để đạt khoảng điểm mong muốn.
                </div>
            </div>
        </section>

        <!-- MỤC H: KẾ HOẠCH HÀNH ĐỘNG CÁ NHÂN (MỤC 7) -->
        <section class="card p-6 border border-slate-200 shadow-sm" aria-labelledby="sec-action-plan-title">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4 border-b border-slate-100 pb-3">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-brand-600">Kế hoạch cá nhân</span>
                    <h2 id="sec-action-plan-title" class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <span>📋</span> H. Kế hoạch hành động theo 3 mốc thời gian
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Biến nhận thức thành hành động cụ thể để từng bước chuẩn bị cho mục tiêu nghề nghiệp.
                    </p>
                </div>
                <a href="<?=url('index.php?page=ai-roadmap')?>" class="btn btn-outline btn-sm text-brand-600">
                    Mở Bản đồ hành trình đầy đủ →
                </a>
            </div>

            <!-- 3 Milestones Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs mb-6">
                <!-- Mốc 1: Ngay trong tuần này -->
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <div class="flex items-center gap-2 font-bold text-brand-700 text-sm mb-2">
                        <span>⚡</span> Mốc 1: Ngay trong tuần này
                    </div>
                    <ul class="list-disc pl-4 space-y-1.5 text-slate-600">
                        <li>Chọn 1–2 nghề gợi ý ở trên để đọc kỹ bảng mô tả công việc và yêu cầu tuyển sinh.</li>
                        <li>Trải nghiệm ít nhất 1 phòng mô phỏng tình huống (2D hoặc 3D) để đối chiếu cảm nhận thực tế.</li>
                        <li>Trò chuyện cùng cha mẹ hoặc bạn thân về những phát hiện trong báo cáo này.</li>
                    </ul>
                </div>

                <!-- Mốc 2: Trong học kỳ hiện tại -->
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <div class="flex items-center gap-2 font-bold text-blue-700 text-sm mb-2">
                        <span>📚</span> Mốc 2: Trong học kỳ hiện tại
                    </div>
                    <ul class="list-disc pl-4 space-y-1.5 text-slate-600">
                        <li>Tập trung cải thiện điểm số 3 môn trong tổ hợp xét tuyển mục tiêu (ví dụ: A00, D01).</li>
                        <li>Tham gia Câu lạc bộ hoặc hoạt động dự án trường học liên quan đến thế mạnh sở thích.</li>
                        <li>Rèn luyện kỹ năng tự học và quản lý thời gian theo phương pháp phù hợp với tính cách Big Five.</li>
                    </ul>
                </div>

                <!-- Mốc 3: Chuẩn bị cho kỳ thi / tuyển sinh -->
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <div class="flex items-center gap-2 font-bold text-emerald-700 text-sm mb-2">
                        <span>🎯</span> Mốc 3: Chuẩn bị thi & tuyển sinh
                    </div>
                    <ul class="list-disc pl-4 space-y-1.5 text-slate-600">
                        <li>Cập nhật đề án tuyển sinh chính thức mới nhất của các trường đại học mục tiêu.</li>
                        <li>Chuẩn bị phương án dự phòng (chọn thêm 2–3 trường có khoảng điểm chuẩn an toàn hơn).</li>
                        <li>Tham dự các ngày hội tư vấn tuyển sinh hoặc liên hệ ban tư vấn của các trường để hỏi rõ học phí.</li>
                    </ul>
                </div>
            </div>

            <!-- Existing Tasks in User Roadmap -->
            <?php if ($hasRoadmap): ?>
                <div class="p-4 bg-white rounded-xl border border-slate-200 mb-4">
                    <span class="font-bold text-xs text-slate-800 block mb-2">Các nhiệm vụ bạn đã lên lịch:</span>
                    <div class="space-y-2">
                        <?php foreach ($roadmapTasks as $task): ?>
                            <div class="flex items-center justify-between p-2.5 rounded-lg border border-slate-100 bg-slate-50 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="<?=$task['is_completed'] ? 'text-emerald-600' : 'text-slate-400'?>">
                                        <?=$task['is_completed'] ? '✓' : '○'?>
                                    </span>
                                    <span class="<?=$task['is_completed'] ? 'line-through text-slate-400' : 'font-semibold text-slate-800'?>">
                                        <?=e($task['title'])?>
                                    </span>
                                </div>
                                <span class="badge badge-subtle text-[10px]"><?=e(ucfirst((string)$task['stage']))?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Add Quick Milestone Form -->
            <form method="post" action="<?=url('index.php?page=plan-quick-add')?>" class="flex flex-wrap gap-2 text-xs">
                <?=csrf_field()?>
                <input type="text" name="title" placeholder="Thêm mục tiêu cá nhân mới (VD: Đọc đề án tuyển sinh ĐH Bách Khoa)..." class="input input-sm flex-1 min-w-[250px]" required>
                <select name="stage" class="input input-sm">
                    <option value="discovery">Giai đoạn Khám phá</option>
                    <option value="academic">Giai đoạn Học tập</option>
                    <option value="skills">Giai đoạn Kỹ năng</option>
                    <option value="decision">Giai đoạn Quyết định</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">+ Thêm vào Kế hoạch</button>
            </form>
        </section>

        <!-- MỤC I: CẢNH BÁO & GIỚI HẠN KHOA HỌC -->
        <section class="notice-box p-5 bg-slate-100 border border-slate-200 rounded-2xl text-xs text-slate-600 leading-relaxed" aria-labelledby="sec-scientific-limits">
            <h2 id="sec-scientific-limits" class="font-bold text-slate-800 text-sm mb-2 flex items-center gap-2">
                <span>🛡️</span> I. Tuyên bố chuẩn mực Sư phạm & An toàn Tâm lý Học đường
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="p-3 bg-white rounded-xl border border-slate-200">
                    <strong class="text-slate-800 block mb-1">Nguồn gốc chuẩn hóa:</strong>
                    Bộ công cụ tích hợp các lý thuyết được thẩm định quốc tế (Holland RIASEC & O*NET, Big Five Mini-IPIP, Savickas CAAS-SF, Mayer-Salovey EQ). Dữ liệu tuyển sinh được tham chiếu từ Cổng thông tin của Bộ GD&ĐT và đề án các trường đại học.
                </div>
                <div class="p-3 bg-white rounded-xl border border-slate-200">
                    <strong class="text-slate-800 block mb-1">Khuyến nghị tham vấn:</strong>
                    Báo cáo này mang tính chất định hướng và tự phản tư. Bạn nên chia sẻ kết quả này cùng giáo viên chủ nhiệm, chuyên gia tư vấn hướng nghiệp tại trường hoặc cha mẹ để có những quyết định toàn diện nhất.
                </div>
            </div>
        </section>

    </div>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}
