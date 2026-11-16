<?php
declare(strict_types=1);

require_once __DIR__ . '/four-hubs-ai.php';
require_once __DIR__ . '/four-hubs-data.php';

/**
 * Ensure roadmap tables exist in database.
 */
function user_roadmap_ensure_tables(): void
{
    static $ensured = false;
    if ($ensured) return;
    $driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        db()->exec("CREATE TABLE IF NOT EXISTS user_roadmaps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            target_career_id INTEGER,
            target_career_title VARCHAR(200),
            goal_title VARCHAR(255) NOT NULL DEFAULT 'Khám phá và định hình hướng đi tương lai',
            target_timeframe VARCHAR(100) NOT NULL DEFAULT 'Lớp 10 - 12',
            notes TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        db()->exec('CREATE INDEX IF NOT EXISTS idx_roadmap_user ON user_roadmaps(user_id)');
        db()->exec("CREATE TABLE IF NOT EXISTS user_roadmap_milestones (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            roadmap_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            stage TEXT NOT NULL DEFAULT 'discovery' CHECK(stage IN ('discovery','academic','skills','decision')),
            title VARCHAR(255) NOT NULL,
            description TEXT,
            action_url VARCHAR(255),
            is_completed INTEGER NOT NULL DEFAULT 0,
            completed_at TEXT,
            source TEXT NOT NULL DEFAULT 'suggested' CHECK(source IN ('suggested','assessment','custom','ai')),
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(roadmap_id) REFERENCES user_roadmaps(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        db()->exec('CREATE INDEX IF NOT EXISTS idx_milestone_user_stage ON user_roadmap_milestones(user_id,stage,is_completed)');
    } else {
        // MySQL
        db()->exec("CREATE TABLE IF NOT EXISTS `user_roadmaps` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `target_career_id` INT UNSIGNED NULL,
            `target_career_title` VARCHAR(200) NULL,
            `goal_title` VARCHAR(255) NOT NULL DEFAULT 'Khám phá và định hình hướng đi tương lai',
            `target_timeframe` VARCHAR(100) NOT NULL DEFAULT 'Lớp 10 - 12',
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_roadmap_user` (`user_id`),
            CONSTRAINT `fk_roadmap_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db()->exec("CREATE TABLE IF NOT EXISTS `user_roadmap_milestones` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `roadmap_id` BIGINT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `stage` ENUM('discovery', 'academic', 'skills', 'decision') NOT NULL DEFAULT 'discovery',
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `action_url` VARCHAR(255) NULL,
            `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
            `completed_at` DATETIME NULL,
            `source` ENUM('suggested', 'assessment', 'custom', 'ai') NOT NULL DEFAULT 'suggested',
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_milestone_user_stage` (`user_id`, `stage`, `is_completed`),
            CONSTRAINT `fk_milestone_roadmap` FOREIGN KEY (`roadmap_id`) REFERENCES `user_roadmaps` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_milestone_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $ensured = true;
}

/**
 * Standard stages definition.
 */
function user_roadmap_stages(): array
{
    return [
        'discovery' => [
            'key' => 'discovery',
            'title' => 'Giai đoạn 1: Khám phá & Thấu hiểu bản thân',
            'icon' => '🔍',
            'summary' => 'Làm rõ sở thích, năng lực tiềm ẩn, phong cách hành vi và các ranh giới đánh đổi.',
        ],
        'academic' => [
            'key' => 'academic',
            'title' => 'Giai đoạn 2: Định hình môn học & Tổ hợp đào tạo',
            'icon' => '📚',
            'summary' => 'Đối chiếu tổ hợp môn xét tuyển, chuẩn bị kiến thức nền tảng và chọn trường mục tiêu.',
        ],
        'skills' => [
            'key' => 'skills',
            'title' => 'Giai đoạn 3: Rèn luyện kỹ năng & Trải nghiệm thực tế',
            'icon' => '🛠️',
            'summary' => 'Thực hành mô phỏng nghề 3D/Web, làm thử thách đời thực và kiểm tra năng lực qua góc nhìn đa chiều.',
        ],
        'decision' => [
            'key' => 'decision',
            'title' => 'Giai đoạn 4: Tuyển sinh, Kế hoạch dự phòng & Quyết định',
            'icon' => '🎯',
            'summary' => 'Lập danh sách nguyện vọng ưu tiên, đánh giá phương án chi phí và chuẩn bị hồ sơ ứng tuyển.',
        ],
    ];
}

/**
 * Fetch or initialize a user's roadmap.
 */
function user_roadmap_get_or_create(int $userId): array
{
    user_roadmap_ensure_tables();
    $stmt = db()->prepare('SELECT * FROM user_roadmaps WHERE user_id=? LIMIT 1');
    $stmt->execute([$userId]);
    $roadmap = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$roadmap) {
        // Derive initial target career from assessment if any
        $context = four_hub_ai_context($userId);
        $primaryCareer = $context['suggested_careers'][0] ?? null;
        $careerTitle = $primaryCareer['title'] ?? 'Khám phá nghề nghiệp phù hợp';
        $userGrade = !empty($context['grade']) ? $context['grade'] : 'Lớp 10 - 12';

        $ins = db()->prepare("INSERT INTO user_roadmaps(user_id, target_career_title, goal_title, target_timeframe, notes) VALUES(?, ?, ?, ?, ?)");
        $goalTitle = $primaryCareer ? 'Định hình lộ trình phát triển nghề ' . $careerTitle : 'Khám phá và xây dựng lộ trình nghề nghiệp tương lai';
        $notes = 'Hành trình do chính bạn làm chủ. Cập nhật các cột mốc theo nhịp độ học tập của bạn.';
        $ins->execute([$userId, $careerTitle, $goalTitle, $userGrade, $notes]);
        $roadmapId = (int)db()->lastInsertId();

        $stmt = db()->prepare('SELECT * FROM user_roadmaps WHERE id=?');
        $stmt->execute([$roadmapId]);
        $roadmap = $stmt->fetch(PDO::FETCH_ASSOC);

        // Populate 4 foundational starter milestones
        user_roadmap_seed_starter_milestones($roadmapId, $userId, $context);
    }

    $milestones = user_roadmap_get_milestones((int)$roadmap['id'], $userId);
    $roadmap['milestones'] = $milestones;
    $roadmap['progress'] = user_roadmap_calculate_progress($milestones);
    return $roadmap;
}

/**
 * Seed foundational starter milestones if empty.
 */
function user_roadmap_seed_starter_milestones(int $roadmapId, int $userId, array $context): void
{
    $primaryCareer = $context['suggested_careers'][0] ?? null;
    $careerTitle = $primaryCareer['title'] ?? 'nghề bạn quan tâm';

    $starters = [
        [
            'stage' => 'discovery',
            'title' => 'Hoàn thành bài đánh giá Holland (RIASEC) và Big Five',
            'description' => 'Khám phá 6 nhóm sở thích nghề nghiệp và 5 nét tính cách cốt lõi để nhận diện điểm mạnh tự nhiên.',
            'action_url' => 'index.php?page=assessment-hub',
            'source' => 'suggested',
            'sort_order' => 1,
        ],
        [
            'stage' => 'academic',
            'title' => 'Tìm hiểu 2–3 trường đào tạo ngành liên quan đến ' . $careerTitle,
            'description' => 'Xem xét đề án tuyển sinh, tổ hợp môn xét tuyển và mức học phí tham khảo trên Danh mục ngành.',
            'action_url' => 'index.php?page=majors',
            'source' => 'suggested',
            'sort_order' => 2,
        ],
        [
            'stage' => 'skills',
            'title' => 'Trải nghiệm 1 tình huống mô phỏng nghề tại Phòng mô phỏng',
            'description' => 'Thử thách giải quyết tình huống nghề nghiệp giả định để đối chiếu cảm nhận thực tế với sở thích ban đầu.',
            'action_url' => 'index.php?page=simulations',
            'source' => 'suggested',
            'sort_order' => 3,
        ],
        [
            'stage' => 'decision',
            'title' => 'Thực hiện Thử thách 15 phút đời thực và trao đổi cùng người lớn',
            'description' => 'Dành 15 phút tìm hiểu một ngày làm việc điển hình của nghề và thảo luận cùng giáo viên hoặc người thân.',
            'action_url' => 'index.php?page=ai-roadmap#career-chat',
            'source' => 'suggested',
            'sort_order' => 4,
        ],
    ];

    $ins = db()->prepare("INSERT INTO user_roadmap_milestones(roadmap_id, user_id, stage, title, description, action_url, source, sort_order) VALUES(?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($starters as $m) {
        $ins->execute([$roadmapId, $userId, $m['stage'], $m['title'], $m['description'], $m['action_url'], $m['source'], $m['sort_order']]);
    }
}

/**
 * Fetch all milestones for a roadmap, grouped by stage.
 */
function user_roadmap_get_milestones(int $roadmapId, int $userId): array
{
    user_roadmap_ensure_tables();
    $stmt = db()->prepare('SELECT * FROM user_roadmap_milestones WHERE roadmap_id=? AND user_id=? ORDER BY stage ASC, sort_order ASC, id ASC');
    $stmt->execute([$roadmapId, $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stages = user_roadmap_stages();
    $grouped = [];
    foreach ($stages as $key => $meta) {
        $grouped[$key] = [
            'meta' => $meta,
            'items' => [],
            'completed_count' => 0,
            'total_count' => 0,
        ];
    }

    foreach ($rows as $row) {
        $st = $row['stage'];
        if (!isset($grouped[$st])) {
            $grouped[$st] = [
                'meta' => ['key' => $st, 'title' => ucfirst($st), 'icon' => '📌', 'summary' => ''],
                'items' => [],
                'completed_count' => 0,
                'total_count' => 0,
            ];
        }
        $row['is_completed'] = (int)$row['is_completed'] === 1;
        $grouped[$st]['items'][] = $row;
        $grouped[$st]['total_count']++;
        if ($row['is_completed']) {
            $grouped[$st]['completed_count']++;
        }
    }

    return $grouped;
}

/**
 * Calculate completion percentage.
 */
function user_roadmap_calculate_progress(array $groupedMilestones): array
{
    $total = 0;
    $completed = 0;
    foreach ($groupedMilestones as $stage) {
        $total += $stage['total_count'];
        $completed += $stage['completed_count'];
    }
    $percent = $total > 0 ? (int)round(($completed / $total) * 100) : 0;
    return [
        'total' => $total,
        'completed' => $completed,
        'percent' => $percent,
    ];
}

/**
 * Derive assessment insights and actionable recommendations from previous tests.
 * Directly fulfills requirement 3: "suy ra từ kết quả những bài kiểm tra trước và đưa ra suggest".
 */
function user_roadmap_derive_assessment_insights(int $userId): array
{
    $context = four_hub_ai_context($userId);
    $psych = four_hub_latest_psychometrics($userId);

    $testsCompleted = [];
    if (!empty($context['holland'])) $testsCompleted[] = 'Holland (RIASEC)';
    if (!empty($psych['big5'])) $testsCompleted[] = 'Big Five (Tính cách)';
    if (!empty($psych['mbti'])) $testsCompleted[] = 'MBTI';
    if (!empty($psych['eq'])) $testsCompleted[] = 'EQ (Cảm xúc)';
    if (!empty($psych['caas'])) $testsCompleted[] = 'CAAS (Thích ứng)';
    if (!empty($context['experiences'])) $testsCompleted[] = 'Mô phỏng nghề';

    $holland = $context['holland'];
    $hollandCode = $holland['code'] ?? null;
    $suggestedCareers = $context['suggested_careers'] ?? [];

    $strengths = [];
    $growthAreas = [];
    $recommendedMilestones = [];

    // 1. Holland Analysis
    if ($hollandCode) {
        $rDesc = [
            'R' => 'Kỹ thuật, thao tác thực tế với công cụ và máy móc',
            'I' => 'Tư duy nghiên cứu, phân tích dữ liệu và giải quyết vấn đề logic',
            'A' => 'Sáng tạo nghệ thuật, thẩm mỹ và diễn đạt tự do',
            'S' => 'Giao tiếp xã hội, thấu hiểu, giúp đỡ và đào tạo con người',
            'E' => 'Khởi xướng, lãnh đạo, thuyết phục và kinh doanh',
            'C' => 'Nguyên tắc, quản trị quy trình, chi tiết và tổ chức dữ liệu',
        ];
        $primaryLetter = mb_substr($hollandCode, 0, 1);
        $secondaryLetter = mb_substr($hollandCode, 1, 1);
        if (isset($rDesc[$primaryLetter])) {
            $strengths[] = 'Sở thích nổi trội: ' . $rDesc[$primaryLetter] . ' (Mã ' . $primaryLetter . ')';
        }
        if (isset($rDesc[$secondaryLetter])) {
            $strengths[] = 'Thiên hướng bổ trợ: ' . $rDesc[$secondaryLetter] . ' (Mã ' . $secondaryLetter . ')';
        }

        // Suggest milestone for top matched career
        if (!empty($suggestedCareers[0])) {
            $c = $suggestedCareers[0];
            $recommendedMilestones[] = [
                'stage' => 'academic',
                'title' => 'Đối chiếu môn học thế mạnh với nghề ' . $c['title'],
                'description' => 'Môn học trọng tâm tham khảo: ' . ($c['subjects'] ?: 'Toán, Tiếng Anh, KHTN') . '. Kiểm tra điểm số hiện tại để lên kế hoạch bồi dưỡng.',
                'action_url' => 'index.php?page=knowledge-hub',
                'source' => 'assessment',
            ];
        }
    } else {
        $growthAreas[] = 'Bạn chưa hoàn thành trắc nghiệm Holland (RIASEC). Đây là cơ sở cốt lõi để nhận gợi ý nghề nghiệp chuẩn xác.';
        $recommendedMilestones[] = [
            'stage' => 'discovery',
            'title' => 'Làm bài trắc nghiệm Holland (30 câu hỏi RIASEC)',
            'description' => 'Hoàn thành bài trắc nghiệm để xác định mã nghề nghiệp và nhận gợi ý nghề phù hợp nhất.',
            'action_url' => 'index.php?page=assessment',
            'source' => 'assessment',
        ];
    }

    // 2. Big Five Analysis
    if (isset($psych['big5']) && is_array($psych['big5']['scores'])) {
        $b5 = $psych['big5']['scores'];
        // Conscientiousness
        if (isset($b5['C'])) {
            if ($b5['C'] >= 60) {
                $strengths[] = 'Tính Tận tâm & Kỷ luật cao (' . $b5['C'] . '/100): Phù hợp với các mục tiêu dài hạn, đòi hỏi sự kiên trì và chính xác.';
            } elseif ($b5['C'] < 45) {
                $growthAreas[] = 'Chỉ số Tận tâm (' . $b5['C'] . '/100) ở mức trung bình thấp: Dễ nản khi gặp bài tập dài hoặc công việc chi tiết.';
                $recommendedMilestones[] = [
                    'stage' => 'skills',
                    'title' => 'Rèn luyện thói quen chia nhỏ mục tiêu và theo dõi tiến độ tuần',
                    'description' => 'Sử dụng phương pháp Pomodoro hoặc bảng to-do list hàng ngày để tăng tính kiên trì và kỷ luật học tập.',
                    'action_url' => '',
                    'source' => 'assessment',
                ];
            }
        }
        // Emotional Stability (Neuroticism)
        if (isset($b5['N']) && $b5['N'] < 40) {
            $growthAreas[] = 'Mức độ Ổn định Cảm xúc (' . $b5['N'] . '/100): Dễ chịu áp lực khi gặp bài thi hoặc thay đổi đột ngột.';
            $recommendedMilestones[] = [
                'stage' => 'skills',
                'title' => 'Thực hành kỹ năng giải tỏa áp lực và duy trì tâm lý học đường cân bằng',
                'description' => 'Tập chia sẻ khó khăn với người tin cậy và không gán nhãn giá trị bản thân qua một điểm số tạm thời.',
                'action_url' => '',
                'source' => 'assessment',
            ];
        }
        // Openness
        if (isset($b5['O']) && $b5['O'] >= 65) {
            $strengths[] = 'Tính Cởi mở & Học hỏi cao (' . $b5['O'] . '/100): Thích khám phá kiến thức mới, đa ngành và công nghệ tương lai.';
            $recommendedMilestones[] = [
                'stage' => 'discovery',
                'title' => 'Tìm hiểu 2 lĩnh vực nghề nghiệp liên ngành (ví dụ: AI, Dữ liệu, Công nghệ xanh)',
                'description' => 'Tận dụng trí tò mò tự nhiên để mở rộng góc nhìn trước khi chốt một lựa chọn duy nhất.',
                'action_url' => 'index.php?page=majors',
                'source' => 'assessment',
            ];
        }
    } else {
        $recommendedMilestones[] = [
            'stage' => 'discovery',
            'title' => 'Hoàn thành bài đánh giá tính cách khoa học Big Five',
            'description' => 'Đo lường 5 đặc trưng tính cách chuẩn mực để hiểu rõ phong cách làm việc và môi trường học tập phù hợp.',
            'action_url' => 'index.php?page=assessment-hub',
            'source' => 'assessment',
        ];
    }

    // 3. Learning Conditions & Sacrifices (Cổng đánh đổi)
    $cond = $context['learning_conditions'] ?? [];
    if (!empty($cond['avoid_sacrifices'])) {
        $avoidLabels = [
            'emotional_labor_crisis' => 'Tránh áp lực cảm xúc cao / đối diện xung đột liên tục',
            'strict_physical_discipline' => 'Tránh môi trường kỷ luật thể chất nghiêm ngặt',
            'debugging_detail_grind' => 'Tránh làm việc lặp lại với chi tiết tỉ mỉ hoặc dò lỗi liên tục',
            'high_academic_duration' => 'Tránh lộ trình học thuật quá dài (> 5-6 năm)',
            'frequent_travel_relocation' => 'Tránh công tác xa nhà hoặc di chuyển liên tục',
            'heavy_financial_burden' => 'Tránh ngành có học phí quá cao vượt khả năng tài chính',
        ];
        $avoidTexts = [];
        foreach ($cond['avoid_sacrifices'] as $sacKey) {
            if (isset($avoidLabels[$sacKey])) $avoidTexts[] = $avoidLabels[$sacKey];
        }
        if ($avoidTexts) {
            $growthAreas[] = 'Ranh giới thực tế muốn tránh: ' . implode(', ', $avoidTexts) . '.';
            $recommendedMilestones[] = [
                'stage' => 'decision',
                'title' => 'Kiểm tra mức độ đánh đổi thực tế của nghề mục tiêu',
                'description' => 'Đối chiếu các yêu cầu công việc (giờ giấc, học phí, áp lực) với ranh giới của bạn để không bị sốc thực tế.',
                'action_url' => 'index.php?page=university-explorer',
                'source' => 'assessment',
            ];
        }
    }

    // 4. Simulations (Trải nghiệm thực tế)
    if (empty($context['experiences'])) {
        $growthAreas[] = 'Bạn chưa có dữ liệu từ trải nghiệm mô phỏng. Trải nghiệm thực tế giúp biến sở thích trừu tượng thành cảm nhận cụ thể.';
        $recommendedMilestones[] = [
            'stage' => 'skills',
            'title' => 'Tham gia thử nghiệm 1 tiết học mô phỏng nghề tại Phòng mô phỏng 3D/Web',
            'description' => 'Thực hiện nhiệm vụ thực tế và ghi lại cảm xúc, cách phản ứng sau tiết học.',
            'action_url' => 'index.php?page=simulations',
            'source' => 'assessment',
        ];
    } else {
        $expCount = count($context['experiences']);
        $strengths[] = 'Đã có ' . $expCount . ' trải nghiệm mô phỏng thực tế. Bạn có bằng chứng hành vi để tự phản tư.';
    }

    return [
        'tests_completed' => $testsCompleted,
        'tests_count' => count($testsCompleted),
        'holland_code' => $hollandCode,
        'suggested_careers' => $suggestedCareers,
        'strengths' => $strengths,
        'growth_areas' => $growthAreas,
        'recommended_milestones' => $recommendedMilestones,
    ];
}

/**
 * Standard milestone suggestions library offered by website.
 * Directly fulfills requirement 2: "website chỉ đưa ra những cột mốc, những suggest làm gì".
 */
function user_roadmap_library_milestones(): array
{
    return [
        'discovery' => [
            [
                'stage' => 'discovery',
                'title' => 'Làm trắc nghiệm Holland (RIASEC) để tìm mã sở thích',
                'description' => 'Xác định 3 nhóm sở thích nổi trội và nhận gợi ý nghề nghiệp ban đầu.',
                'action_url' => 'index.php?page=assessment',
            ],
            [
                'stage' => 'discovery',
                'title' => 'Thực hiện bài kiểm tra tính cách Big Five & EQ tự nhận xét',
                'description' => 'Đo lường mức độ tận tâm, cởi mở, hướng ngoại và trí tuệ cảm xúc để chọn môi trường phù hợp.',
                'action_url' => 'index.php?page=assessment-hub',
            ],
            [
                'stage' => 'discovery',
                'title' => 'Khảo sát điều kiện học tập & Cổng đánh đổi thực tế',
                'description' => 'Làm rõ các yếu tố về tài chính gia đình, thời gian học và những áp lực muốn tránh.',
                'action_url' => 'index.php?page=assessment-hub',
            ],
            [
                'stage' => 'discovery',
                'title' => 'Gửi link SOKA 360 Mirror mời bạn bè/thầy cô đánh giá khách quan',
                'description' => 'Thu thập góc nhìn từ người khác để nhận diện vùng mù và tiềm năng ẩn giấu của bạn.',
                'action_url' => 'index.php?page=assessment-hub#soka-section',
            ],
        ],
        'academic' => [
            [
                'stage' => 'academic',
                'title' => 'Tra cứu mã ngành và các trường đào tạo trên Danh mục ngành HNV',
                'description' => 'Khám phá 358 ngành đào tạo chuẩn và các trường đại học/cao đẳng có tuyển sinh.',
                'action_url' => 'index.php?page=majors',
            ],
            [
                'stage' => 'academic',
                'title' => 'Xác định tổ hợp môn xét tuyển trọng tâm (ví dụ: A00, A01, D01, B00...)',
                'description' => 'Đánh giá điểm số hiện tại của 3 môn then chốt và đặt mục tiêu điểm số từng kỳ.',
                'action_url' => 'index.php?page=knowledge-hub',
            ],
            [
                'stage' => 'academic',
                'title' => 'Tra cứu điểm chuẩn và chỉ tiêu tuyển sinh 2 năm gần nhất',
                'description' => 'Sử dụng công cụ Tra cứu trường đại học để xem dữ liệu điểm chuẩn công bố chính thức.',
                'action_url' => 'index.php?page=university-explorer',
            ],
            [
                'stage' => 'academic',
                'title' => 'Trao đổi với giáo viên chủ nhiệm hoặc giáo viên bộ môn về năng lực học tập',
                'description' => 'Nhận góp ý chuyên môn về khả năng bứt phá ở các môn học then chốt.',
                'action_url' => '',
            ],
        ],
        'skills' => [
            [
                'stage' => 'skills',
                'title' => 'Thực hiện 1 tiết mô phỏng nghề Giáo viên THCS (Lớp 8A)',
                'description' => 'Trải nghiệm điều hành lớp học, xử lý tình huống sư phạm và phản tư sau tiết dạy.',
                'action_url' => 'index.php?page=simulations',
            ],
            [
                'stage' => 'skills',
                'title' => 'Thực hiện Thử thách 15 phút đời thực',
                'description' => 'Tìm kiếm một nhiệm vụ thường ngày của nghề, giải thích cách làm cho bạn bè và ghi lại cảm xúc.',
                'action_url' => 'index.php?page=ai-roadmap#career-chat',
            ],
            [
                'stage' => 'skills',
                'title' => 'Rèn luyện kỹ năng tự học và quản lý thời gian cá nhân',
                'description' => 'Áp dụng phương pháp làm việc có kế hoạch, ghi chép tiến độ học tập hàng tuần.',
                'action_url' => '',
            ],
            [
                'stage' => 'skills',
                'title' => 'Tham gia một hoạt động câu lạc bộ hoặc dự án nhỏ liên quan đến nghề',
                'description' => 'Thử sức làm việc nhóm và giao tiếp trong môi trường thực tế.',
                'action_url' => '',
            ],
        ],
        'decision' => [
            [
                'stage' => 'decision',
                'title' => 'Lập danh sách 3 phương án nguyện vọng ưu tiên (An toàn - Vừa sức - Thử thách)',
                'description' => 'Chia nhỏ danh sách trường mục tiêu để đảm bảo luôn có phương án dự phòng chắc chắn.',
                'action_url' => 'index.php?page=university-explorer',
            ],
            [
                'stage' => 'decision',
                'title' => 'Đánh giá chi phí học tập và các cơ hội học bổng / hỗ trợ tài chính',
                'description' => 'Tính toán học phí dự kiến, sinh hoạt phí và thảo luận cùng gia đình.',
                'action_url' => 'index.php?page=knowledge-hub',
            ],
            [
                'stage' => 'decision',
                'title' => 'Chuẩn bị hồ sơ xét tuyển và theo dõi lịch tuyển sinh chính thức',
                'description' => 'Nắm chắc các mốc thời gian nộp hồ sơ, thi đánh giá năng lực hoặc xét tuyển sớm.',
                'action_url' => 'index.php?page=knowledge-hub',
            ],
            [
                'stage' => 'decision',
                'title' => 'Tổng kết bản đồ hành trình và chia sẻ với người cố vấn tin cậy',
                'description' => 'In bản đồ hành trình cá nhân ra PDF để lưu trữ và xin ý kiến thầy cô, phụ huynh.',
                'action_url' => 'javascript:window.print()',
            ],
        ],
    ];
}

/**
 * Add a milestone to user's roadmap.
 */
function user_roadmap_add_milestone(int $roadmapId, int $userId, array $data): int
{
    user_roadmap_ensure_tables();
    $stage = in_array($data['stage'] ?? '', ['discovery', 'academic', 'skills', 'decision'], true) ? $data['stage'] : 'discovery';
    $title = trim((string)($data['title'] ?? ''));
    if ($title === '' || mb_strlen($title) > 255) {
        throw new InvalidArgumentException('Tiêu đề cột mốc phải từ 1 đến 255 ký tự.');
    }
    $desc = trim((string)($data['description'] ?? ''));
    if (mb_strlen($desc) > 2000) $desc = mb_substr($desc, 0, 2000);
    $url = trim((string)($data['action_url'] ?? ''));
    if (mb_strlen($url) > 255) $url = mb_substr($url, 0, 255);
    $source = in_array($data['source'] ?? '', ['suggested', 'assessment', 'custom', 'ai'], true) ? $data['source'] : 'custom';

    $maxOrderStmt = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM user_roadmap_milestones WHERE roadmap_id=? AND stage=?');
    $maxOrderStmt->execute([$roadmapId, $stage]);
    $sortOrder = (int)$maxOrderStmt->fetchColumn();

    $stmt = db()->prepare('INSERT INTO user_roadmap_milestones(roadmap_id, user_id, stage, title, description, action_url, source, sort_order) VALUES(?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$roadmapId, $userId, $stage, $title, $desc, $url, $source, $sortOrder]);
    return (int)db()->lastInsertId();
}

/**
 * Toggle completion status of a milestone.
 */
function user_roadmap_toggle_milestone(int $milestoneId, int $userId): array
{
    user_roadmap_ensure_tables();
    $stmt = db()->prepare('SELECT * FROM user_roadmap_milestones WHERE id=? AND user_id=? LIMIT 1');
    $stmt->execute([$milestoneId, $userId]);
    $m = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$m) throw new RuntimeException('Không tìm thấy cột mốc tương ứng.');

    $newStatus = (int)$m['is_completed'] === 1 ? 0 : 1;
    $completedAt = $newStatus === 1 ? date('Y-m-d H:i:s') : null;

    $upd = db()->prepare('UPDATE user_roadmap_milestones SET is_completed=?, completed_at=? WHERE id=?');
    $upd->execute([$newStatus, $completedAt, $milestoneId]);

    // Recalculate progress
    $milestones = user_roadmap_get_milestones((int)$m['roadmap_id'], $userId);
    $progress = user_roadmap_calculate_progress($milestones);

    return [
        'id' => $milestoneId,
        'is_completed' => $newStatus === 1,
        'completed_at' => $completedAt,
        'progress' => $progress,
    ];
}

/**
 * Delete a milestone.
 */
function user_roadmap_delete_milestone(int $milestoneId, int $userId): void
{
    user_roadmap_ensure_tables();
    $stmt = db()->prepare('DELETE FROM user_roadmap_milestones WHERE id=? AND user_id=?');
    $stmt->execute([$milestoneId, $userId]);
}

/**
 * Update roadmap goal and details.
 */
function user_roadmap_update_details(int $roadmapId, int $userId, array $data): void
{
    user_roadmap_ensure_tables();
    $goal = trim((string)($data['goal_title'] ?? ''));
    if ($goal === '' || mb_strlen($goal) > 255) {
        throw new InvalidArgumentException('Mục tiêu hành trình không được để trống (tối đa 255 ký tự).');
    }
    $careerTitle = trim((string)($data['target_career_title'] ?? ''));
    if (mb_strlen($careerTitle) > 200) $careerTitle = mb_substr($careerTitle, 0, 200);

    $timeframe = trim((string)($data['target_timeframe'] ?? ''));
    if ($timeframe === '' || mb_strlen($timeframe) > 100) $timeframe = 'Lớp 10 - 12';

    $notes = trim((string)($data['notes'] ?? ''));
    if (mb_strlen($notes) > 2000) $notes = mb_substr($notes, 0, 2000);

    $stmt = db()->prepare('UPDATE user_roadmaps SET goal_title=?, target_career_title=?, target_timeframe=?, notes=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=?');
    $stmt->execute([$goal, $careerTitle, $timeframe, $notes, $roadmapId, $userId]);
}

/**
 * AI-assisted milestone generation on demand.
 * Directly fulfills requirement 4: "thêm mục AI vào để hỗ trợ khi user chủ động bấm nút AI".
 */
function user_roadmap_ai_generate_suggestions(int $userId, array $roadmap): array
{
    $context = four_hub_ai_context($userId);
    $primaryCareer = !empty($roadmap['target_career_title']) ? $roadmap['target_career_title'] : ($context['suggested_careers'][0]['title'] ?? 'Nghề quan tâm');
    $timeframe = $roadmap['target_timeframe'] ?? 'Lớp 10 - 12';
    $goal = $roadmap['goal_title'] ?? 'Khám phá nghề nghiệp';

    $prompt = "Dựa trên hồ sơ của học sinh (Holland: " . ($context['holland']['code'] ?? 'chưa rõ') .
        ", Mục tiêu: $goal, Nghề đích: $primaryCareer, Khung thời gian: $timeframe), " .
        "hãy gợi ý chính xác 3 cột mốc hành động thực tế nhất mà học sinh có thể thực hiện trong giai đoạn này. " .
        "Mỗi cột mốc gồm: tên cột mốc ngắn gọn, giai đoạn phù hợp (discovery, academic, skills hoặc decision) và hướng dẫn 1-2 câu.";

    // Use four_hub_chat_answer or deterministic fallbacks
    $fallbackSuggestions = [
        [
            'stage' => 'discovery',
            'title' => 'Đọc kỹ 3 bài viết chuyên ngành về nghề ' . $primaryCareer,
            'description' => 'Tìm hiểu các trách nhiệm thực tế hàng ngày, cơ hội thăng tiến và những khó khăn thường gặp.',
            'action_url' => 'index.php?page=knowledge-hub',
            'source' => 'ai',
        ],
        [
            'stage' => 'academic',
            'title' => 'Tra cứu 2 trường ĐH tại khu vực của bạn có đào tạo ngành ' . $primaryCareer,
            'description' => 'Xem xét điểm chuẩn năm trước và học phí để lập kế hoạch ôn tập phù hợp.',
            'action_url' => 'index.php?page=majors',
            'source' => 'ai',
        ],
        [
            'stage' => 'skills',
            'title' => 'Trải nghiệm 1 nhiệm vụ thực tế 15 phút về ' . $primaryCareer,
            'description' => 'Thử giải quyết một vấn đề nhỏ của nghề và viết lại cảm xúc, điều hứng thú và điều còn e ngại.',
            'action_url' => 'index.php?page=simulations',
            'source' => 'ai',
        ],
    ];

    $providers = chat_provider_config();
    $key = setting('openai_api_key');
    if (!$providers && !$key) {
        return [
            'suggestions' => $fallbackSuggestions,
            'model' => 'fallback',
            'note' => 'Gợi ý từ thuật toán phân tích hồ sơ (chưa cấu hình AI provider ngoài).',
        ];
    }

    try {
        $systemPrompt = "Bạn là chuyên gia hướng nghiệp sư phạm. Nhiệm vụ của bạn là đưa ra 3 cột mốc hành động cá nhân hóa cho học sinh. " .
            "Phải trả về JSON mảng 3 object hợp lệ, mỗi object có dạng: {\"stage\":\"discovery|academic|skills|decision\",\"title\":\"...\",\"description\":\"...\"}. " .
            "Không kèm theo văn bản giải thích ngoài JSON. Lời khuyên phải cụ thể, an toàn và thực tế cho học sinh Việt Nam.";

        $input = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt],
        ];

        if ($providers) {
            $ans = chat_provider_answer($input, $providers);
        } else {
            $model = setting('openai_model', 'gpt-5-mini');
            $payload = ['model' => $model, 'store' => false, 'input' => $input, 'max_output_tokens' => 1000];
            $ch = curl_init('https://api.openai.com/v1/responses');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
            $ans = ['message' => '', 'model' => $model];
            if ($raw) {
                $decoded = json_decode($raw, true);
                foreach (($decoded['output'] ?? []) as $out) {
                    foreach (($out['content'] ?? []) as $c) {
                        if (($c['type'] ?? '') === 'output_text') $ans['message'] .= $c['text'];
                    }
                }
            }
        }

        // Try to parse JSON from AI message
        $text = trim($ans['message'] ?? '');
        if (preg_match('/\[\s*\{.*?\}\s*\]/s', $text, $match)) {
            $parsed = json_decode($match[0], true);
            if (is_array($parsed) && count($parsed) > 0) {
                $items = [];
                foreach ($parsed as $item) {
                    $st = in_array($item['stage'] ?? '', ['discovery', 'academic', 'skills', 'decision'], true) ? $item['stage'] : 'skills';
                    $items[] = [
                        'stage' => $st,
                        'title' => mb_substr(trim((string)($item['title'] ?? 'Cột mốc gợi ý')), 0, 255),
                        'description' => mb_substr(trim((string)($item['description'] ?? '')), 0, 1000),
                        'action_url' => $st === 'academic' ? 'index.php?page=majors' : ($st === 'skills' ? 'index.php?page=simulations' : 'index.php?page=knowledge-hub'),
                        'source' => 'ai',
                    ];
                }
                return [
                    'suggestions' => $items,
                    'model' => $ans['model'] ?? 'ai',
                    'note' => 'Gợi ý thông minh từ AI dựa trên mục tiêu "' . $primaryCareer . '" và hồ sơ của bạn.',
                ];
            }
        }
    } catch (Throwable $e) {
        // Fall back gracefully
    }

    return [
        'suggestions' => $fallbackSuggestions,
        'model' => 'fallback',
        'note' => 'Gợi ý được tổng hợp từ hồ sơ nghề ' . $primaryCareer . '.',
    ];
}
