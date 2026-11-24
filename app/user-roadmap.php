<?php
declare(strict_types=1);

require_once __DIR__ . '/four-hubs-ai.php';
require_once __DIR__ . '/four-hubs-data.php';

/**
 * Ensure roadmap tables and additive columns exist in database.
 * Fully idempotent, safe for both MySQL and SQLite.
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
            start_month VARCHAR(7),
            duration_months INTEGER NOT NULL DEFAULT 6,
            weekly_hours INTEGER,
            monthly_goals TEXT,
            reflections TEXT,
            notes TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        db()->exec('CREATE INDEX IF NOT EXISTS idx_roadmap_user ON user_roadmaps(user_id)');

        try {
            $existingRoadmapCols = [];
            $rColsRes = db()->query("PRAGMA table_info(user_roadmaps)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rColsRes as $rColRow) {
                if (!empty($rColRow['name'])) {
                    $existingRoadmapCols[] = $rColRow['name'];
                }
            }
            $neededRoadmapCols = [
                'start_month' => 'VARCHAR(7)',
                'duration_months' => 'INTEGER NOT NULL DEFAULT 6',
                'weekly_hours' => 'INTEGER',
                'monthly_goals' => 'TEXT',
                'reflections' => 'TEXT',
                'notes' => 'TEXT',
            ];
            foreach ($neededRoadmapCols as $col => $typeDef) {
                if (!in_array($col, $existingRoadmapCols, true)) {
                    db()->exec("ALTER TABLE user_roadmaps ADD COLUMN $col $typeDef");
                }
            }
        } catch (Throwable $e) {}

        db()->exec("CREATE TABLE IF NOT EXISTS user_roadmap_milestones (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            roadmap_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            stage TEXT NOT NULL DEFAULT 'discovery' CHECK(stage IN ('discovery','academic','skills','decision')),
            title VARCHAR(255) NOT NULL,
            description TEXT,
            target_month VARCHAR(7),
            start_date TEXT,
            due_date TEXT,
            estimated_duration VARCHAR(50),
            priority TEXT NOT NULL DEFAULT 'medium',
            status TEXT NOT NULL DEFAULT 'todo',
            expected_outcome TEXT,
            actual_outcome TEXT,
            action_url VARCHAR(255),
            prerequisite_id INTEGER,
            is_completed INTEGER NOT NULL DEFAULT 0,
            completed_at TEXT,
            source TEXT NOT NULL DEFAULT 'suggested' CHECK(source IN ('suggested','assessment','custom','ai')),
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(roadmap_id) REFERENCES user_roadmaps(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        try {
            $existingCols = [];
            $colsRes = db()->query("PRAGMA table_info(user_roadmap_milestones)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($colsRes as $colRow) {
                if (!empty($colRow['name'])) {
                    $existingCols[] = $colRow['name'];
                }
            }
            $neededCols = [
                'target_month' => 'VARCHAR(7)',
                'start_date' => 'TEXT',
                'due_date' => 'TEXT',
                'estimated_duration' => 'VARCHAR(50)',
                'priority' => "TEXT NOT NULL DEFAULT 'medium'",
                'status' => "TEXT NOT NULL DEFAULT 'todo'",
                'expected_outcome' => 'TEXT',
                'actual_outcome' => 'TEXT',
                'action_url' => 'VARCHAR(255)',
                'prerequisite_id' => 'INTEGER',
                'is_completed' => 'INTEGER NOT NULL DEFAULT 0',
                'completed_at' => 'TEXT',
                'source' => "TEXT NOT NULL DEFAULT 'suggested'",
                'sort_order' => 'INTEGER NOT NULL DEFAULT 0',
            ];
            foreach ($neededCols as $col => $typeDef) {
                if (!in_array($col, $existingCols, true)) {
                    db()->exec("ALTER TABLE user_roadmap_milestones ADD COLUMN $col $typeDef");
                }
            }
        } catch (Throwable $e) {}

        db()->exec('CREATE INDEX IF NOT EXISTS idx_milestone_user_stage ON user_roadmap_milestones(user_id,stage,is_completed)');
        db()->exec('CREATE INDEX IF NOT EXISTS idx_milestone_target_month ON user_roadmap_milestones(user_id,target_month)');
    } else {
        // MySQL
        db()->exec("CREATE TABLE IF NOT EXISTS `user_roadmaps` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `target_career_id` INT UNSIGNED NULL,
            `target_career_title` VARCHAR(200) NULL,
            `goal_title` VARCHAR(255) NOT NULL DEFAULT 'Khám phá và định hình hướng đi tương lai',
            `target_timeframe` VARCHAR(100) NOT NULL DEFAULT 'Lớp 10 - 12',
            `start_month` VARCHAR(7) NULL,
            `duration_months` INT NOT NULL DEFAULT 6,
            `weekly_hours` INT NULL,
            `monthly_goals` TEXT NULL,
            `reflections` TEXT NULL,
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
            `target_month` VARCHAR(7) NULL,
            `start_date` DATE NULL,
            `due_date` DATE NULL,
            `estimated_duration` VARCHAR(50) NULL,
            `priority` VARCHAR(20) NOT NULL DEFAULT 'medium',
            `status` VARCHAR(20) NOT NULL DEFAULT 'todo',
            `expected_outcome` TEXT NULL,
            `actual_outcome` TEXT NULL,
            `action_url` VARCHAR(255) NULL,
            `prerequisite_id` BIGINT UNSIGNED NULL,
            `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
            `completed_at` DATETIME NULL,
            `source` ENUM('suggested', 'assessment', 'custom', 'ai') NOT NULL DEFAULT 'suggested',
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_milestone_user_stage` (`user_id`, `stage`, `is_completed`),
            INDEX `idx_milestone_target_month` (`user_id`, `target_month`),
            CONSTRAINT `fk_milestone_roadmap` FOREIGN KEY (`roadmap_id`) REFERENCES `user_roadmaps` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_milestone_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Safe additive column checks for user_roadmaps
        try {
            $rCols = db()->query("SHOW COLUMNS FROM `user_roadmaps`")->fetchAll(PDO::FETCH_COLUMN);
            $neededRoadmap = [
                'start_month' => 'VARCHAR(7) NULL',
                'duration_months' => 'INT NOT NULL DEFAULT 6',
                'weekly_hours' => 'INT NULL',
                'monthly_goals' => 'TEXT NULL',
                'reflections' => 'TEXT NULL'
            ];
            foreach ($neededRoadmap as $col => $def) {
                if (!in_array($col, $rCols, true)) {
                    db()->exec("ALTER TABLE `user_roadmaps` ADD COLUMN `$col` $def");
                }
            }
        } catch (Throwable $e) {}

        // Safe additive column checks for user_roadmap_milestones
        try {
            $mCols = db()->query("SHOW COLUMNS FROM `user_roadmap_milestones`")->fetchAll(PDO::FETCH_COLUMN);
            $neededMilestones = [
                'target_month' => 'VARCHAR(7) NULL',
                'start_date' => 'DATE NULL',
                'due_date' => 'DATE NULL',
                'estimated_duration' => 'VARCHAR(50) NULL',
                'priority' => 'VARCHAR(20) NOT NULL DEFAULT "medium"',
                'status' => 'VARCHAR(20) NOT NULL DEFAULT "todo"',
                'expected_outcome' => 'TEXT NULL',
                'actual_outcome' => 'TEXT NULL',
                'prerequisite_id' => 'BIGINT UNSIGNED NULL'
            ];
            foreach ($neededMilestones as $col => $def) {
                if (!in_array($col, $mCols, true)) {
                    db()->exec("ALTER TABLE `user_roadmap_milestones` ADD COLUMN `$col` $def");
                }
            }
        } catch (Throwable $e) {}
    }
    $ensured = true;
}

/**
 * Standard stages definition (used as classification tags & filters).
 */
function user_roadmap_stages(): array
{
    return [
        'discovery' => [
            'key' => 'discovery',
            'title' => 'Hiểu bản thân',
            'icon' => '🔍',
            'summary' => 'Sở thích, phong cách học/làm việc, ranh giới và điều kiện cá nhân.',
            'color' => '#6366f1',
        ],
        'academic' => [
            'key' => 'academic',
            'title' => 'Khám phá nghề & đường học',
            'icon' => '📚',
            'summary' => 'Công việc thực tế, ngành đào tạo chuẩn, trường học và chỉ tiêu tuyển sinh.',
            'color' => '#0284c7',
        ],
        'skills' => [
            'key' => 'skills',
            'title' => 'Thử sức & rèn luyện',
            'icon' => '🛠️',
            'summary' => 'Mô phỏng thực tế, thử thách 15 phút, kỹ năng chuyển giao và sản phẩm nhỏ.',
            'color' => '#10b981',
        ],
        'decision' => [
            'key' => 'decision',
            'title' => 'Chọn hướng & kế hoạch',
            'icon' => '🎯',
            'summary' => 'So sánh 3 phương án dự phòng, chuẩn bị hồ sơ và thời điểm nhìn lại.',
            'color' => '#8b5cf6',
        ],
    ];
}

/**
 * Generate a sequential list of month keys (YYYY-MM) and labels.
 * Handles month/year transition correctly (e.g. 12/2026 -> 01/2027).
 */
function user_roadmap_months_list(string $startMonth, int $durationMonths = 6): array
{
    $startMonth = trim($startMonth);
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $startMonth)) {
        $startMonth = date('Y-m');
    }
    $durationMonths = max(1, min(24, $durationMonths));

    $months = [];
    $currentDate = new DateTimeImmutable($startMonth . '-01');
    $currentYm = date('Y-m');

    for ($i = 0; $i < $durationMonths; $i++) {
        $ym = $currentDate->format('Y-m');
        $mNum = (int)$currentDate->format('n');
        $yNum = (int)$currentDate->format('Y');

        $months[$ym] = [
            'month_key' => $ym,
            'index' => $i + 1,
            'label' => "Tháng $mNum, $yNum",
            'short_label' => sprintf('T%02d/%d', $mNum, $yNum),
            'month_num' => $mNum,
            'year_num' => $yNum,
            'is_current' => ($ym === $currentYm),
        ];

        $currentDate = $currentDate->modify('+1 month');
    }

    return $months;
}

/**
 * Get or create user's roadmap row.
 * Does NOT auto-overwrite existing user custom roadmaps.
 */
function user_roadmap_get_or_create(int $userId): array
{
    user_roadmap_ensure_tables();
    $stmt = db()->prepare('SELECT * FROM user_roadmaps WHERE user_id=? LIMIT 1');
    $stmt->execute([$userId]);
    $roadmap = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$roadmap) {
        // Derive initial context
        $context = four_hub_ai_context($userId);
        $primaryCareer = $context['suggested_careers'][0] ?? null;
        $careerTitle = $primaryCareer['title'] ?? '';
        $currentMonth = date('Y-m');
        $endMonthDate = (new DateTimeImmutable($currentMonth . '-01'))->modify('+5 months');
        $targetTimeframe = sprintf('%s – %s', date('m/Y'), $endMonthDate->format('m/Y'));

        $goalTitle = $primaryCareer
            ? 'Định hình lộ trình phát triển nghề ' . $careerTitle
            : 'Khám phá và nhận diện hướng đi phù hợp';
        $notes = 'Kế hoạch hành động cá nhân theo nhịp độ của bạn. Tự do điều chỉnh theo thời gian.';

        $defaultMonthlyGoals = user_roadmap_default_monthly_goals(6, $careerTitle);
        $monthlyGoalsJson = json_encode($defaultMonthlyGoals, JSON_UNESCAPED_UNICODE);

        $ins = db()->prepare("INSERT INTO user_roadmaps(user_id, target_career_title, goal_title, target_timeframe, start_month, duration_months, weekly_hours, monthly_goals, notes) VALUES(?, ?, ?, ?, ?, 6, 3, ?, ?)");
        $ins->execute([$userId, $careerTitle, $goalTitle, $targetTimeframe, $currentMonth, $monthlyGoalsJson, $notes]);
        $roadmapId = (int)db()->lastInsertId();

        $stmt = db()->prepare('SELECT * FROM user_roadmaps WHERE id=?');
        $stmt->execute([$roadmapId]);
        $roadmap = $stmt->fetch(PDO::FETCH_ASSOC);

        // Seed initial monthly milestones
        user_roadmap_seed_monthly_starter_milestones($roadmapId, $userId, $currentMonth, $careerTitle);
    }

    // Decode JSON fields safely
    $roadmap['monthly_goals'] = !empty($roadmap['monthly_goals']) ? json_decode((string)$roadmap['monthly_goals'], true) : [];
    if (!is_array($roadmap['monthly_goals'])) $roadmap['monthly_goals'] = [];

    $roadmap['reflections'] = !empty($roadmap['reflections']) ? json_decode((string)$roadmap['reflections'], true) : [];
    if (!is_array($roadmap['reflections'])) $roadmap['reflections'] = [];

    return $roadmap;
}

/**
 * Return pedagogical monthly goal progression for a given duration.
 */
function user_roadmap_default_monthly_goals(int $durationMonths, ?string $careerTitle = null): array
{
    $career = $careerTitle ?: 'nghề quan tâm';
    $goals = [
        1 => 'Hiểu bản thân: Làm trắc nghiệm Holland, Big Five và chọn 2–3 nghề trọng tâm để khám phá.',
        2 => "Tìm hiểu thực tế: Khám phá một ngày làm việc điển hình của $career và trải nghiệm 1 thử việc mô phỏng.",
        3 => 'Khám phá đường học: So sánh 3 chương trình đào tạo chuẩn, đối chiếu tổ hợp môn và điều kiện xét tuyển.',
        4 => 'Thử sức thực tế: Thực hiện thử thách 15 phút đời thực, trao đổi cùng thầy cô/gia đình và rèn kỹ năng tự học.',
        5 => 'Lập phương án: Lập danh mục 3 phương án trường (An toàn - Vừa sức - Thử thách) và tính toán chi phí học tập.',
        6 => 'Nhìn lại & Quyết định: Tổng kết hành trình, đánh giá mức độ phù hợp và sẵn sàng kế hoạch cho năm học tới.',
    ];

    if ($durationMonths === 3) {
        return [
            1 => 'Hiểu bản thân: Xác định mã sở thích RIASEC và lập danh sách 3 nghề tiềm năng.',
            2 => "Trải nghiệm thực tế: Thử thách giải quyết tình huống nghề $career và tìm hiểu trường đào tạo.",
            3 => 'Định hình kế hoạch: Lập danh mục 3 phương án nguyện vọng và đặt mục tiêu điểm số các môn trọng tâm.',
        ];
    }

    $res = [];
    for ($i = 1; $i <= $durationMonths; $i++) {
        $res[$i] = $goals[$i] ?? ('Thực hiện các cột mốc trọng tâm của tháng ' . $i . ' và nhìn lại tiến độ.');
    }
    return $res;
}

/**
 * Seed initial monthly starter milestones.
 * Distributes concrete tasks across months starting from $startMonth.
 */
function user_roadmap_seed_monthly_starter_milestones(int $roadmapId, int $userId, string $startMonth, string $careerTitle): void
{
    $months = array_keys(user_roadmap_months_list($startMonth, 6));
    $m1 = $months[0] ?? $startMonth;
    $m2 = $months[1] ?? $m1;
    $m3 = $months[2] ?? $m2;
    $m4 = $months[3] ?? $m3;
    $m5 = $months[4] ?? $m4;
    $m6 = $months[5] ?? $m5;

    $isTeacher = preg_match('/giáo viên|sư phạm/i', $careerTitle);
    $simTitle = $isTeacher
        ? 'Thực hiện 1 tiết mô phỏng nghề Giáo viên THCS (Lớp 8A)'
        : 'Trải nghiệm 1 tình huống thử việc thực tế tại Phòng mô phỏng nghề';

    $starters = [
        // Month 1
        [
            'target_month' => $m1,
            'stage' => 'discovery',
            'title' => 'Làm bài trắc nghiệm Holland (RIASEC) và Big Five',
            'description' => 'Khám phá 6 nhóm sở thích nghề nghiệp và 5 nét tính cách cốt lõi để nhận diện phong cách học tập/làm việc phù hợp.',
            'action_url' => 'index.php?page=assessment-hub',
            'estimated_duration' => '30 phút',
            'priority' => 'high',
            'expected_outcome' => 'Mã 3 chữ cái RIASEC và danh sách ngành nghề gợi ý ban đầu.',
            'sort_order' => 1,
        ],
        [
            'target_month' => $m1,
            'stage' => 'discovery',
            'title' => 'So sánh công việc hằng ngày của 3 nghề đang quan tâm',
            'description' => 'Đọc mô tả nhiệm vụ thực tế, môi trường làm việc và các điều kiện bắt buộc của từng nghề.',
            'action_url' => 'index.php?page=careers',
            'estimated_duration' => '45 phút',
            'priority' => 'medium',
            'expected_outcome' => 'Bảng so sánh 3 nghề về môi trường làm việc, trách nhiệm chính và mức độ hào hứng.',
            'sort_order' => 2,
        ],
        // Month 2
        [
            'target_month' => $m2,
            'stage' => 'skills',
            'title' => $simTitle,
            'description' => 'Thử thách giải quyết tình huống nghề nghiệp giả định để đối chiếu cảm nhận thực tế với hình dung ban đầu.',
            'action_url' => 'index.php?page=simulations',
            'estimated_duration' => '25 phút',
            'priority' => 'high',
            'expected_outcome' => 'Nhật ký phản tư ghi lại 2 điều thú vị và 1 thử thách khó khăn sau phiên mô phỏng.',
            'sort_order' => 1,
        ],
        [
            'target_month' => $m2,
            'stage' => 'academic',
            'title' => 'Khảo sát 2–3 ngành đào tạo chuẩn trên Danh mục ngành HNV',
            'description' => 'Tra cứu mã ngành chuẩn, tổ hợp môn xét tuyển và các trường đại học/cao đẳng có tuyển sinh.',
            'action_url' => 'index.php?page=majors',
            'estimated_duration' => '40 phút',
            'priority' => 'medium',
            'expected_outcome' => 'Danh sách mã ngành chuẩn, tổ hợp môn tương ứng và danh mục trường tuyển sinh.',
            'sort_order' => 2,
        ],
        // Month 3
        [
            'target_month' => $m3,
            'stage' => 'academic',
            'title' => 'So sánh 3 chương trình đào tạo theo học phí, nội dung học và điều kiện đầu vào',
            'description' => 'Sử dụng công cụ Tra cứu trường đại học để đối chiếu điểm chuẩn 2 năm gần nhất và mức học phí tham khảo.',
            'action_url' => 'index.php?page=university-explorer',
            'estimated_duration' => '60 phút',
            'priority' => 'high',
            'expected_outcome' => 'Bảng đối chiếu 3 trường theo học phí dự kiến, điểm chuẩn và chỉ tiêu tuyển sinh.',
            'sort_order' => 1,
        ],
        // Month 4
        [
            'target_month' => $m4,
            'stage' => 'skills',
            'title' => 'Thực hiện Thử thách 15 phút đời thực và trao đổi cùng người lớn',
            'description' => 'Tìm hiểu một nhiệm vụ thường ngày của người trong nghề, chia sẻ lại cho người thân/thầy cô và ghi nhận nhận xét.',
            'action_url' => 'index.php?page=ai-roadmap#career-chat',
            'estimated_duration' => '30 phút',
            'priority' => 'medium',
            'expected_outcome' => 'Biên bản ghi nhớ 2 điểm lưu ý thực tế từ người có kinh nghiệm.',
            'sort_order' => 1,
        ],
        // Month 5
        [
            'target_month' => $m5,
            'stage' => 'decision',
            'title' => 'Lập danh mục 3 phương án nguyện vọng ưu tiên (An toàn - Vừa sức - Thử thách)',
            'description' => 'Chia nhỏ danh sách trường mục tiêu để đảm bảo luôn có phương án dự phòng chắc chắn trước mùa tuyển sinh.',
            'action_url' => 'index.php?page=university-explorer',
            'estimated_duration' => '45 phút',
            'priority' => 'high',
            'expected_outcome' => 'Bảng danh mục 3 phương án trường rõ ràng kèm điểm chuẩn dự kiến.',
            'sort_order' => 1,
        ],
        // Month 6
        [
            'target_month' => $m6,
            'stage' => 'decision',
            'title' => 'Tổng kết bản kế hoạch hướng nghiệp và nhìn lại điều đã học',
            'description' => 'Đánh giá lại toàn bộ các cột mốc đã hoàn thành, ghi nhận bài học rút ra và chuẩn bị mục tiêu cho năm học tiếp theo.',
            'action_url' => 'javascript:window.print()',
            'estimated_duration' => '30 phút',
            'priority' => 'medium',
            'expected_outcome' => 'Bản kế hoạch hoàn chỉnh kèm các bài học thực tế sẵn sàng chia sẻ cùng gia đình.',
            'sort_order' => 1,
        ],
    ];

    $ins = db()->prepare("INSERT INTO user_roadmap_milestones(roadmap_id, user_id, stage, title, description, target_month, estimated_duration, priority, status, expected_outcome, action_url, source, sort_order) VALUES(?, ?, ?, ?, ?, ?, ?, ?, 'todo', ?, ?, 'suggested', ?)");
    foreach ($starters as $s) {
        $ins->execute([
            $roadmapId,
            $userId,
            $s['stage'],
            $s['title'],
            $s['description'],
            $s['target_month'],
            $s['estimated_duration'],
            $s['priority'],
            $s['expected_outcome'],
            $s['action_url'],
            $s['sort_order'],
        ]);
    }
}

/**
 * Fetch and group all milestones by month, including unscheduled milestones.
 * Fully computes completion counters, overdue flags, blocked status, and outcomes.
 */
function user_roadmap_get_by_month(int $roadmapId, int $userId, array $roadmap, ?string $selectedMonth = null): array
{
    user_roadmap_ensure_tables();
    $startMonth = !empty($roadmap['start_month']) ? $roadmap['start_month'] : date('Y-m');
    $duration = !empty($roadmap['duration_months']) ? (int)$roadmap['duration_months'] : 6;
    $currentYm = date('Y-m');
    $today = date('Y-m-d');

    // Generate standard month list
    $planMonths = user_roadmap_months_list($startMonth, $duration);

    // Fetch all milestones
    $stmt = db()->prepare('SELECT * FROM user_roadmap_milestones WHERE roadmap_id=? AND user_id=? ORDER BY target_month ASC, sort_order ASC, id ASC');
    $stmt->execute([$roadmapId, $userId]);
    $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map tasks by ID for prerequisite lookup
    $tasksById = [];
    foreach ($rawRows as $r) {
        $tasksById[(int)$r['id']] = $r;
    }

    // Include any custom months found in raw rows that aren't in planMonths
    foreach ($rawRows as $r) {
        $tm = trim((string)($r['target_month'] ?? ''));
        if ($tm !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $tm) && !isset($planMonths[$tm])) {
            $date = new DateTimeImmutable($tm . '-01');
            $planMonths[$tm] = [
                'month_key' => $tm,
                'index' => count($planMonths) + 1,
                'label' => 'Tháng ' . $date->format('n') . ', ' . $date->format('Y'),
                'short_label' => sprintf('T%02d/%d', $date->format('n'), $date->format('Y')),
                'month_num' => (int)$date->format('n'),
                'year_num' => (int)$date->format('Y'),
                'is_current' => ($tm === $currentYm),
            ];
        }
    }

    // Sort plan months chronologically
    ksort($planMonths);

    // Prepare grouped structure
    $grouped = [];
    $monthlyGoalsMap = $roadmap['monthly_goals'] ?? [];
    $reflectionsMap = $roadmap['reflections'] ?? [];

    $monthIdx = 1;
    foreach ($planMonths as $ym => $meta) {
        $customGoal = $monthlyGoalsMap[$ym] ?? $monthlyGoalsMap[$monthIdx] ?? '';
        if (!$customGoal) {
            $defaultProgression = user_roadmap_default_monthly_goals($duration, $roadmap['target_career_title'] ?? '');
            $customGoal = $defaultProgression[$monthIdx] ?? ('Thực hiện các công việc trọng tâm của ' . $meta['label']);
        }

        $grouped[$ym] = [
            'month_key' => $ym,
            'meta' => $meta,
            'goal' => $customGoal,
            'reflection' => $reflectionsMap[$ym] ?? null,
            'items' => [],
            'completed_count' => 0,
            'total_count' => 0,
            'percent' => 0,
        ];
        $monthIdx++;
    }

    // Add unscheduled bucket
    $grouped['unscheduled'] = [
        'month_key' => 'unscheduled',
        'meta' => [
            'month_key' => 'unscheduled',
            'index' => 999,
            'label' => 'Chưa xếp lịch',
            'short_label' => 'Chưa xếp',
            'is_current' => false,
        ],
        'goal' => 'Các công việc chưa được xếp vào tháng cụ thể. Hãy xếp các việc này vào kế hoạch để dễ theo dõi tiến độ.',
        'reflection' => null,
        'items' => [],
        'completed_count' => 0,
        'total_count' => 0,
        'percent' => 0,
    ];

    $totalAll = 0;
    $completedAll = 0;

    // Process raw rows
    foreach ($rawRows as $row) {
        $id = (int)$row['id'];
        $tm = trim((string)($row['target_month'] ?? ''));
        $targetBucket = ($tm !== '' && isset($grouped[$tm])) ? $tm : 'unscheduled';

        $isCompleted = ((int)$row['is_completed'] === 1) || ($row['status'] === 'completed');
        $status = $row['status'] ?: ($isCompleted ? 'completed' : 'todo');

        // Check overdue: only if due_date is set, due_date < today, and task is NOT completed
        $dueDate = !empty($row['due_date']) ? $row['due_date'] : null;
        $isOverdue = (!$isCompleted && !empty($dueDate) && $dueDate < $today);

        // Check prerequisite dependency
        $prereqId = !empty($row['prerequisite_id']) ? (int)$row['prerequisite_id'] : 0;
        $isBlocked = false;
        $blockedByTitle = '';
        if ($prereqId > 0 && isset($tasksById[$prereqId])) {
            $prereqTask = $tasksById[$prereqId];
            $prereqDone = ((int)$prereqTask['is_completed'] === 1) || ($prereqTask['status'] === 'completed');
            if (!$prereqDone) {
                $isBlocked = true;
                $blockedByTitle = $prereqTask['title'];
            }
        }

        $actionMeta = user_roadmap_resolve_action($row['action_url'] ?? '');
        $expectedOutcome = !empty($row['expected_outcome']) ? $row['expected_outcome'] : user_roadmap_resolve_outcome($row);

        $enhancedRow = [
            'id' => $id,
            'roadmap_id' => (int)$row['roadmap_id'],
            'user_id' => (int)$row['user_id'],
            'stage' => $row['stage'],
            'title' => $row['title'],
            'description' => $row['description'] ?? '',
            'target_month' => $tm ?: null,
            'start_date' => $row['start_date'] ?? null,
            'due_date' => $dueDate,
            'estimated_duration' => $row['estimated_duration'] ?? '',
            'priority' => $row['priority'] ?: 'medium',
            'status' => $status,
            'is_completed' => $isCompleted,
            'completed_at' => $row['completed_at'] ?? null,
            'is_overdue' => $isOverdue,
            'is_blocked' => $isBlocked,
            'prerequisite_id' => $prereqId,
            'blocked_by_title' => $blockedByTitle,
            'expected_outcome' => $expectedOutcome,
            'actual_outcome' => $row['actual_outcome'] ?? '',
            'action_url' => $row['action_url'] ?? '',
            'action_label' => $actionMeta['label'],
            'source' => $row['source'] ?? 'suggested',
            'sort_order' => (int)$row['sort_order'],
        ];

        $grouped[$targetBucket]['items'][] = $enhancedRow;
        $grouped[$targetBucket]['total_count']++;
        if ($isCompleted) {
            $grouped[$targetBucket]['completed_count']++;
        }

        $totalAll++;
        if ($isCompleted) {
            $completedAll++;
        }
    }

    // Calculate percentages for each month
    foreach ($grouped as $k => &$g) {
        $g['percent'] = $g['total_count'] > 0 ? (int)round(($g['completed_count'] / $g['total_count']) * 100) : 0;
    }
    unset($g);

    // If unscheduled bucket is empty, we still keep it accessible
    $overallPercent = $totalAll > 0 ? (int)round(($completedAll / $totalAll) * 100) : 0;

    // Determine active month
    $activeKey = $selectedMonth;
    if (!$activeKey || !isset($grouped[$activeKey])) {
        // Default to current month if in plan, else first plan month
        if (isset($grouped[$currentYm])) {
            $activeKey = $currentYm;
        } else {
            $firstMonth = array_key_first($planMonths);
            $activeKey = $firstMonth ?: 'unscheduled';
        }
    }

    return [
        'plan_months' => $planMonths,
        'grouped' => $grouped,
        'active_month' => $activeKey,
        'current_month' => $currentYm,
        'total_all' => $totalAll,
        'completed_all' => $completedAll,
        'overall_percent' => $overallPercent,
        'unscheduled_count' => count($grouped['unscheduled']['items']),
    ];
}

/**
 * Determine up to 3 next actionable tasks (Vùng B).
 * Priority:
 * 1. Overdue incomplete tasks.
 * 2. Incomplete tasks due soonest.
 * 3. Incomplete unblocked tasks in current month.
 * 4. Incomplete unblocked tasks in upcoming months.
 * Excludes tasks blocked by incomplete prerequisites!
 */
function user_roadmap_get_next_actions(array $monthData): array
{
    $grouped = $monthData['grouped'] ?? [];
    $currentMonth = $monthData['current_month'] ?? date('Y-m');

    $eligible = [];
    $blockedCount = 0;

    foreach ($grouped as $monthKey => $mGroup) {
        foreach ($mGroup['items'] as $item) {
            if ($item['is_completed']) continue;

            if ($item['is_blocked']) {
                $blockedCount++;
                continue; // Cannot start blocked task per product spec
            }

            // Assign sorting priority score
            $score = 100;
            if ($item['is_overdue']) {
                $score = 10; // Overdue first
            } elseif (!empty($item['due_date'])) {
                $score = 20; // Due date set
            } elseif ($item['target_month'] === $currentMonth) {
                $score = 30; // Current month
            } elseif (!empty($item['target_month'])) {
                $score = 40; // Scheduled future
            } else {
                $score = 50; // Unscheduled
            }

            // Sub-priority
            $priorityWeight = $item['priority'] === 'high' ? 1 : ($item['priority'] === 'medium' ? 2 : 3);

            $eligible[] = [
                'task' => $item,
                'score' => $score,
                'priority_weight' => $priorityWeight,
                'due_date' => $item['due_date'] ?? '9999-99-99',
                'month_label' => $mGroup['meta']['label'] ?? 'Chưa xếp lịch',
            ];
        }
    }

    // Sort: score ASC, due_date ASC, priority_weight ASC, id ASC
    usort($eligible, function($a, $b) {
        if ($a['score'] !== $b['score']) return $a['score'] <=> $b['score'];
        if ($a['due_date'] !== $b['due_date']) return strcmp($a['due_date'], $b['due_date']);
        if ($a['priority_weight'] !== $b['priority_weight']) return $a['priority_weight'] <=> $b['priority_weight'];
        return $a['task']['id'] <=> $b['task']['id'];
    });

    $top3 = array_slice($eligible, 0, 3);
    $results = [];
    foreach ($top3 as $entry) {
        $t = $entry['task'];
        $results[] = [
            'id' => $t['id'],
            'title' => $t['title'],
            'description' => $t['description'],
            'stage' => $t['stage'],
            'target_month' => $t['target_month'],
            'month_label' => $entry['month_label'],
            'due_date' => $t['due_date'],
            'is_overdue' => $t['is_overdue'],
            'expected_outcome' => $t['expected_outcome'],
            'action_label' => $t['action_label'] ?: 'Thực hiện ngay →',
            'action_url' => $t['action_url'],
            'priority' => $t['priority'],
            'estimated_duration' => $t['estimated_duration'],
        ];
    }

    return [
        'tasks' => $results,
        'blocked_count' => $blockedCount,
        'has_unscheduled_only' => (empty($results) && $monthData['unscheduled_count'] > 0),
        'is_all_completed' => ($monthData['total_all'] > 0 && $monthData['completed_all'] === $monthData['total_all']),
    ];
}

/**
 * Add a task to roadmap.
 */
function user_roadmap_add_task(int $roadmapId, int $userId, array $data): int
{
    user_roadmap_ensure_tables();
    $title = trim((string)($data['title'] ?? ''));
    if ($title === '' || mb_strlen($title) > 255) {
        throw new InvalidArgumentException('Tiêu đề công việc phải từ 1 đến 255 ký tự.');
    }
    $desc = trim((string)($data['description'] ?? ''));
    if (mb_strlen($desc) > 2000) $desc = mb_substr($desc, 0, 2000);

    $targetMonth = trim((string)($data['target_month'] ?? ''));
    if ($targetMonth !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $targetMonth)) {
        $targetMonth = null;
    }

    $startDate = trim((string)($data['start_date'] ?? ''));
    if ($startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        $startDate = null;
    }

    $dueDate = trim((string)($data['due_date'] ?? ''));
    if ($dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
        $dueDate = null;
    }

    if ($startDate && $dueDate && $startDate > $dueDate) {
        throw new InvalidArgumentException('Ngày bắt đầu không được sau hạn hoàn thành.');
    }

    $duration = trim((string)($data['estimated_duration'] ?? ''));
    if (mb_strlen($duration) > 50) $duration = mb_substr($duration, 0, 50);

    $stage = in_array($data['stage'] ?? '', ['discovery', 'academic', 'skills', 'decision'], true) ? $data['stage'] : 'discovery';
    $priority = in_array($data['priority'] ?? '', ['high', 'medium', 'low'], true) ? $data['priority'] : 'medium';
    $status = in_array($data['status'] ?? '', ['todo', 'in_progress', 'completed'], true) ? $data['status'] : 'todo';
    $isCompleted = ($status === 'completed') ? 1 : 0;
    $completedAt = $isCompleted ? date('Y-m-d H:i:s') : null;

    $expectedOutcome = trim((string)($data['expected_outcome'] ?? ''));
    if (mb_strlen($expectedOutcome) > 1000) $expectedOutcome = mb_substr($expectedOutcome, 0, 1000);

    $url = trim((string)($data['action_url'] ?? ''));
    if (mb_strlen($url) > 255) $url = mb_substr($url, 0, 255);

    $prereqId = !empty($data['prerequisite_id']) ? (int)$data['prerequisite_id'] : null;

    $source = in_array($data['source'] ?? '', ['suggested', 'assessment', 'custom', 'ai'], true) ? $data['source'] : 'custom';

    $maxOrder = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM user_roadmap_milestones WHERE roadmap_id=? AND COALESCE(target_month, "")=?');
    $maxOrder->execute([$roadmapId, $targetMonth ?? '']);
    $sortOrder = (int)$maxOrder->fetchColumn();

    $stmt = db()->prepare("INSERT INTO user_roadmap_milestones(roadmap_id, user_id, stage, title, description, target_month, start_date, due_date, estimated_duration, priority, status, is_completed, completed_at, expected_outcome, action_url, prerequisite_id, source, sort_order) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $roadmapId,
        $userId,
        $stage,
        $title,
        $desc,
        $targetMonth ?: null,
        $startDate ?: null,
        $dueDate ?: null,
        $duration ?: null,
        $priority,
        $status,
        $isCompleted,
        $completedAt,
        $expectedOutcome ?: null,
        $url ?: null,
        $prereqId ?: null,
        $source,
        $sortOrder,
    ]);

    return (int)db()->lastInsertId();
}

/**
 * Update an existing task.
 */
function user_roadmap_update_task(int $taskId, int $userId, array $data): void
{
    user_roadmap_ensure_tables();
    $stmt = db()->prepare('SELECT * FROM user_roadmap_milestones WHERE id=? AND user_id=? LIMIT 1');
    $stmt->execute([$taskId, $userId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) throw new RuntimeException('Không tìm thấy công việc tương ứng.');

    $title = trim((string)($data['title'] ?? $existing['title']));
    if ($title === '' || mb_strlen($title) > 255) {
        throw new InvalidArgumentException('Tiêu đề công việc phải từ 1 đến 255 ký tự.');
    }
    $desc = isset($data['description']) ? trim((string)$data['description']) : ($existing['description'] ?? '');
    if (mb_strlen($desc) > 2000) $desc = mb_substr($desc, 0, 2000);

    $targetMonth = isset($data['target_month']) ? trim((string)$data['target_month']) : ($existing['target_month'] ?? null);
    if ($targetMonth !== null && $targetMonth !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $targetMonth)) {
        $targetMonth = null;
    }

    $startDate = isset($data['start_date']) ? trim((string)$data['start_date']) : ($existing['start_date'] ?? null);
    if ($startDate !== null && $startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        $startDate = null;
    }

    $dueDate = isset($data['due_date']) ? trim((string)$data['due_date']) : ($existing['due_date'] ?? null);
    if ($dueDate !== null && $dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
        $dueDate = null;
    }

    if ($startDate && $dueDate && $startDate > $dueDate) {
        throw new InvalidArgumentException('Ngày bắt đầu không được sau hạn hoàn thành.');
    }

    $duration = isset($data['estimated_duration']) ? trim((string)$data['estimated_duration']) : ($existing['estimated_duration'] ?? null);
    $stage = in_array($data['stage'] ?? '', ['discovery', 'academic', 'skills', 'decision'], true) ? $data['stage'] : $existing['stage'];
    $priority = in_array($data['priority'] ?? '', ['high', 'medium', 'low'], true) ? $data['priority'] : ($existing['priority'] ?? 'medium');

    $status = in_array($data['status'] ?? '', ['todo', 'in_progress', 'completed'], true) ? $data['status'] : ($existing['status'] ?? 'todo');
    $isCompleted = ($status === 'completed') ? 1 : 0;
    $completedAt = $isCompleted ? ($existing['completed_at'] ?: date('Y-m-d H:i:s')) : null;

    $expectedOutcome = isset($data['expected_outcome']) ? trim((string)$data['expected_outcome']) : ($existing['expected_outcome'] ?? null);
    $actualOutcome = isset($data['actual_outcome']) ? trim((string)$data['actual_outcome']) : ($existing['actual_outcome'] ?? null);
    $url = isset($data['action_url']) ? trim((string)$data['action_url']) : ($existing['action_url'] ?? null);
    $prereqId = isset($data['prerequisite_id']) ? ((int)$data['prerequisite_id'] > 0 ? (int)$data['prerequisite_id'] : null) : ($existing['prerequisite_id'] ?? null);

    $upd = db()->prepare("UPDATE user_roadmap_milestones SET title=?, description=?, target_month=?, start_date=?, due_date=?, estimated_duration=?, stage=?, priority=?, status=?, is_completed=?, completed_at=?, expected_outcome=?, actual_outcome=?, action_url=?, prerequisite_id=? WHERE id=? AND user_id=?");
    $upd->execute([
        $title,
        $desc,
        $targetMonth ?: null,
        $startDate ?: null,
        $dueDate ?: null,
        $duration ?: null,
        $stage,
        $priority,
        $status,
        $isCompleted,
        $completedAt,
        $expectedOutcome ?: null,
        $actualOutcome ?: null,
        $url ?: null,
        $prereqId,
        $taskId,
        $userId,
    ]);
}

/**
 * Move task to another month with clear due date handling.
 * If old due date is outside new month, allows user choice.
 */
function user_roadmap_move_task_month(int $taskId, int $userId, ?string $newMonth, bool $keepDueDate = true, ?string $newDueDate = null): void
{
    user_roadmap_ensure_tables();
    $stmt = db()->prepare('SELECT * FROM user_roadmap_milestones WHERE id=? AND user_id=? LIMIT 1');
    $stmt->execute([$taskId, $userId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) throw new RuntimeException('Không tìm thấy công việc cần chuyển.');

    $targetMonth = trim((string)$newMonth);
    if ($targetMonth !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $targetMonth)) {
        $targetMonth = null;
    }

    $finalDueDate = $task['due_date'];
    if (!$keepDueDate) {
        $finalDueDate = null;
    }
    if ($newDueDate !== null) {
        $trimmed = trim($newDueDate);
        $finalDueDate = ($trimmed !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) ? $trimmed : null;
    }

    $upd = db()->prepare('UPDATE user_roadmap_milestones SET target_month=?, due_date=? WHERE id=? AND user_id=?');
    $upd->execute([$targetMonth ?: null, $finalDueDate, $taskId, $userId]);
}

/**
 * Toggle or set task status (todo, in_progress, completed).
 */
function user_roadmap_toggle_task_status(int $taskId, int $userId, ?string $targetStatus = null): array
{
    user_roadmap_ensure_tables();
    $stmt = db()->prepare('SELECT * FROM user_roadmap_milestones WHERE id=? AND user_id=? LIMIT 1');
    $stmt->execute([$taskId, $userId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) throw new RuntimeException('Không tìm thấy công việc tương ứng.');

    $currentStatus = $task['status'] ?: (((int)$task['is_completed'] === 1) ? 'completed' : 'todo');

    if ($targetStatus && in_array($targetStatus, ['todo', 'in_progress', 'completed'], true)) {
        $newStatus = $targetStatus;
    } else {
        // Toggle cycle: todo/in_progress -> completed, completed -> todo
        $newStatus = ($currentStatus === 'completed') ? 'todo' : 'completed';
    }

    $isCompleted = ($newStatus === 'completed') ? 1 : 0;
    $completedAt = $isCompleted ? date('Y-m-d H:i:s') : null;

    $upd = db()->prepare('UPDATE user_roadmap_milestones SET status=?, is_completed=?, completed_at=? WHERE id=? AND user_id=?');
    $upd->execute([$newStatus, $isCompleted, $completedAt, $taskId, $userId]);

    return [
        'id' => $taskId,
        'status' => $newStatus,
        'is_completed' => $isCompleted === 1,
        'completed_at' => $completedAt,
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
 * Save monthly reflection and handle incomplete tasks batch options.
 */
function user_roadmap_save_monthly_reflection(int $roadmapId, int $userId, string $month, array $answers, array $taskDecisions): void
{
    user_roadmap_ensure_tables();
    $stmt = db()->prepare('SELECT reflections FROM user_roadmaps WHERE id=? AND user_id=?');
    $stmt->execute([$roadmapId, $userId]);
    $rawReflections = $stmt->fetchColumn();
    $reflections = !empty($rawReflections) ? json_decode((string)$rawReflections, true) : [];
    if (!is_array($reflections)) $reflections = [];

    $reflections[$month] = [
        'achieved' => mb_substr(trim((string)($answers['achieved'] ?? '')), 0, 2000),
        'insight' => mb_substr(trim((string)($answers['insight'] ?? '')), 0, 2000),
        'adjust' => mb_substr(trim((string)($answers['adjust'] ?? '')), 0, 2000),
        'saved_at' => date('Y-m-d H:i:s'),
    ];

    $upd = db()->prepare('UPDATE user_roadmaps SET reflections=? WHERE id=? AND user_id=?');
    $upd->execute([json_encode($reflections, JSON_UNESCAPED_UNICODE), $roadmapId, $userId]);

    // Calculate next month
    $nextMonthDate = (new DateTimeImmutable($month . '-01'))->modify('+1 month');
    $nextMonthKey = $nextMonthDate->format('Y-m');

    // Process incomplete task decisions
    foreach ($taskDecisions as $taskId => $decision) {
        $tid = (int)$taskId;
        if ($tid <= 0) continue;

        if ($decision === 'move_next') {
            user_roadmap_move_task_month($tid, $userId, $nextMonthKey, false);
        } elseif ($decision === 'delete') {
            user_roadmap_delete_milestone($tid, $userId);
        }
        // 'keep' leaves task untouched
    }
}

/**
 * Update overall roadmap goal and settings.
 */
function user_roadmap_update_details(int $roadmapId, int $userId, array $data): void
{
    user_roadmap_ensure_tables();
    $goal = trim((string)($data['goal_title'] ?? ''));
    if ($goal === '' || mb_strlen($goal) > 255) {
        throw new InvalidArgumentException('Mục tiêu kế hoạch không được để trống (tối đa 255 ký tự).');
    }
    $careerTitle = trim((string)($data['target_career_title'] ?? ''));
    if (mb_strlen($careerTitle) > 200) $careerTitle = mb_substr($careerTitle, 0, 200);

    $startMonth = trim((string)($data['start_month'] ?? ''));
    if ($startMonth !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $startMonth)) {
        $startMonth = date('Y-m');
    }

    $duration = max(1, min(24, (int)($data['duration_months'] ?? 6)));
    $hours = !empty($data['weekly_hours']) ? max(1, min(40, (int)$data['weekly_hours'])) : null;

    $endMonthDate = (new DateTimeImmutable(($startMonth ?: date('Y-m')) . '-01'))->modify('+' . ($duration - 1) . ' months');
    $timeframe = sprintf('%s – %s', date('m/Y', strtotime($startMonth . '-01')), $endMonthDate->format('m/Y'));

    $notes = trim((string)($data['notes'] ?? ''));
    if (mb_strlen($notes) > 2000) $notes = mb_substr($notes, 0, 2000);

    $stmt = db()->prepare('UPDATE user_roadmaps SET goal_title=?, target_career_title=?, start_month=?, duration_months=?, weekly_hours=?, target_timeframe=?, notes=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=?');
    $stmt->execute([$goal, $careerTitle, $startMonth ?: null, $duration, $hours, $timeframe, $notes, $roadmapId, $userId]);
}

/**
 * Update single month goal.
 */
function user_roadmap_update_month_goal(int $roadmapId, int $userId, string $month, string $goal): void
{
    user_roadmap_ensure_tables();
    $stmt = db()->prepare('SELECT monthly_goals FROM user_roadmaps WHERE id=? AND user_id=?');
    $stmt->execute([$roadmapId, $userId]);
    $rawGoals = $stmt->fetchColumn();
    $goals = !empty($rawGoals) ? json_decode((string)$rawGoals, true) : [];
    if (!is_array($goals)) $goals = [];

    $goals[$month] = mb_substr(trim($goal), 0, 500);

    $upd = db()->prepare('UPDATE user_roadmaps SET monthly_goals=? WHERE id=? AND user_id=?');
    $upd->execute([json_encode($goals, JSON_UNESCAPED_UNICODE), $roadmapId, $userId]);
}

/**
 * Backward compatibility aliases for existing functions.
 */
function user_roadmap_add_milestone(int $roadmapId, int $userId, array $data): int
{
    return user_roadmap_add_task($roadmapId, $userId, $data);
}
function user_roadmap_update_milestone(int $milestoneId, int $userId, array $data): void
{
    user_roadmap_update_task($milestoneId, $userId, $data);
}
function user_roadmap_toggle_milestone(int $milestoneId, int $userId): array
{
    return user_roadmap_toggle_task_status($milestoneId, $userId);
}
function user_roadmap_calculate_progress(array $groupedMilestones): array
{
    $total = 0;
    $completed = 0;
    foreach ($groupedMilestones as $stage) {
        $total += $stage['total_count'] ?? 0;
        $completed += $stage['completed_count'] ?? 0;
    }
    $percent = $total > 0 ? (int)round(($completed / $total) * 100) : 0;
    return ['total' => $total, 'completed' => $completed, 'percent' => $percent];
}
function user_roadmap_get_milestones(int $roadmapId, int $userId): array
{
    user_roadmap_ensure_tables();
    $stmt = db()->prepare('SELECT * FROM user_roadmap_milestones WHERE roadmap_id=? AND user_id=? ORDER BY stage ASC, sort_order ASC, id ASC');
    $stmt->execute([$roadmapId, $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stages = user_roadmap_stages();
    $grouped = [];
    foreach ($stages as $key => $meta) {
        $grouped[$key] = ['meta' => $meta, 'items' => [], 'completed_count' => 0, 'total_count' => 0];
    }
    foreach ($rows as $row) {
        $st = $row['stage'];
        if (!isset($grouped[$st])) {
            $grouped[$st] = ['meta' => ['key' => $st, 'title' => ucfirst($st), 'icon' => '📌', 'summary' => ''], 'items' => [], 'completed_count' => 0, 'total_count' => 0];
        }
        $row['is_completed'] = (int)$row['is_completed'] === 1;
        $grouped[$st]['items'][] = $row;
        $grouped[$st]['total_count']++;
        if ($row['is_completed']) $grouped[$st]['completed_count']++;
    }
    return $grouped;
}

/**
 * Derive assessment insights from previous psychometric tests.
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
        if (isset($rDesc[$primaryLetter])) $strengths[] = 'Sở thích nổi trội: ' . $rDesc[$primaryLetter] . ' (Mã ' . $primaryLetter . ')';
        if (isset($rDesc[$secondaryLetter])) $strengths[] = 'Thiên hướng bổ trợ: ' . $rDesc[$secondaryLetter] . ' (Mã ' . $secondaryLetter . ')';

        if (!empty($suggestedCareers[0])) {
            $c = $suggestedCareers[0];
            $recommendedMilestones[] = [
                'stage' => 'academic',
                'title' => 'Đối chiếu môn học thế mạnh với nghề ' . $c['title'],
                'description' => 'Môn học trọng tâm tham khảo: ' . ($c['subjects'] ?: 'Toán, Tiếng Anh, KHTN') . '. Kiểm tra điểm số hiện tại để lên kế hoạch bồi dưỡng.',
                'action_url' => 'index.php?page=knowledge-hub',
                'estimated_duration' => '30 phút',
                'expected_outcome' => 'Kế hoạch học tập 3 môn then chốt và mục tiêu điểm số kỳ tới.',
                'source' => 'assessment',
            ];
        }
    } else {
        $growthAreas[] = 'Bạn chưa hoàn thành trắc nghiệm Holland (RIASEC). Đây là cơ sở cốt lõi để nhận diện sở thích nghề nghiệp chuẩn mực.';
        $recommendedMilestones[] = [
            'stage' => 'discovery',
            'title' => 'Làm bài trắc nghiệm Holland (30 câu hỏi RIASEC)',
            'description' => 'Hoàn thành bài trắc nghiệm để xác định mã nghề nghiệp và nhận gợi ý nghề phù hợp nhất.',
            'action_url' => 'index.php?page=assessment',
            'estimated_duration' => '20 phút',
            'expected_outcome' => 'Mã RIASEC và danh sách ngành nghề tương ứng.',
            'source' => 'assessment',
        ];
    }

    if (isset($psych['big5']) && is_array($psych['big5']['scores'])) {
        $b5 = $psych['big5']['scores'];
        if (isset($b5['C']) && $b5['C'] >= 60) {
            $strengths[] = 'Tính Tận tâm & Kỷ luật cao (' . $b5['C'] . '/100): Phù hợp với các mục tiêu dài hạn, đòi hỏi sự kiên trì.';
        }
        if (isset($b5['O']) && $b5['O'] >= 65) {
            $strengths[] = 'Tính Cởi mở & Học hỏi cao (' . $b5['O'] . '/100): Thích khám phá kiến thức mới, đa ngành.';
        }
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
 * Standard milestone suggestions library.
 */
function user_roadmap_library_milestones(?string $targetCareer = null): array
{
    $isTeacher = $targetCareer && preg_match('/giáo viên|sư phạm/i', $targetCareer);
    $simTitle = $isTeacher
        ? 'Thực hiện 1 tiết mô phỏng nghề Giáo viên THCS (Lớp 8A)'
        : 'Trải nghiệm tình huống thử việc thực tế tại Phòng mô phỏng nghề';

    return [
        'discovery' => [
            [
                'stage' => 'discovery',
                'title' => 'Làm trắc nghiệm Holland (RIASEC) để tìm mã sở thích',
                'description' => 'Xác định 3 nhóm sở thích nổi trội và nhận gợi ý nghề nghiệp ban đầu.',
                'action_url' => 'index.php?page=assessment',
                'estimated_duration' => '25 phút',
                'expected_outcome' => 'Mã 3 chữ cái RIASEC và danh sách ngành nghề tương ứng.',
            ],
            [
                'stage' => 'discovery',
                'title' => 'Thực hiện bài kiểm tra tính cách Big Five & EQ tự nhận xét',
                'description' => 'Đo lường mức độ tận tâm, cởi mở, hướng ngoại và trí tuệ cảm xúc để chọn môi trường phù hợp.',
                'action_url' => 'index.php?page=assessment-hub',
                'estimated_duration' => '30 phút',
                'expected_outcome' => 'Bản đồ 5 nét tính cách và gợi ý môi trường học tập/làm việc phù hợp.',
            ],
            [
                'stage' => 'discovery',
                'title' => 'Khảo sát điều kiện học tập & Cổng đánh đổi thực tế',
                'description' => 'Làm rõ các yếu tố về tài chính gia đình, thời gian học và những áp lực muốn tránh.',
                'action_url' => 'index.php?page=assessment-hub',
                'estimated_duration' => '20 phút',
                'expected_outcome' => 'Bản ghi rõ những điều kiện muốn tránh (áp lực, di chuyển, học phí) để không bị sốc thực tế.',
            ],
        ],
        'academic' => [
            [
                'stage' => 'academic',
                'title' => 'Tra cứu mã ngành và các trường đào tạo trên Danh mục ngành HNV',
                'description' => 'Khám phá 358 ngành đào tạo chuẩn và các trường đại học/cao đẳng có tuyển sinh.',
                'action_url' => 'index.php?page=majors',
                'estimated_duration' => '40 phút',
                'expected_outcome' => 'Mã ngành đào tạo chuẩn, các trường tuyển sinh và tổ hợp môn tương ứng.',
            ],
            [
                'stage' => 'academic',
                'title' => 'Xác định tổ hợp môn xét tuyển trọng tâm và đặt mục tiêu điểm số',
                'description' => 'Đánh giá điểm số hiện tại của 3 môn then chốt và đặt mục tiêu bứt phá từng kỳ.',
                'action_url' => 'index.php?page=knowledge-hub',
                'estimated_duration' => '30 phút',
                'expected_outcome' => 'Kế hoạch điểm số mục tiêu cho 3 môn then chốt trong học kỳ tới.',
            ],
            [
                'stage' => 'academic',
                'title' => 'Tra cứu điểm chuẩn và chỉ tiêu tuyển sinh 2 năm gần nhất',
                'description' => 'Sử dụng công cụ Tra cứu trường đại học để xem dữ liệu điểm chuẩn công bố chính thức.',
                'action_url' => 'index.php?page=university-explorer',
                'estimated_duration' => '45 phút',
                'expected_outcome' => 'Bảng so sánh điểm chuẩn 2 năm gần nhất của 2–3 trường đào tạo mục tiêu.',
            ],
        ],
        'skills' => [
            [
                'stage' => 'skills',
                'title' => $simTitle,
                'description' => 'Trải nghiệm điều hành thử thách, xử lý tình huống thực tế và phản tư sau phiên làm việc.',
                'action_url' => 'index.php?page=simulations',
                'estimated_duration' => '30 phút',
                'expected_outcome' => 'Nhật ký phản tư ghi nhận phản ứng thực tế trước thử thách nghề nghiệp.',
            ],
            [
                'stage' => 'skills',
                'title' => 'Thực hiện Thử thách 15 phút đời thực',
                'description' => 'Tìm kiếm một nhiệm vụ thường ngày của nghề, giải thích cách làm cho bạn bè và ghi lại cảm xúc.',
                'action_url' => 'index.php?page=ai-roadmap#career-chat',
                'estimated_duration' => '20 phút',
                'expected_outcome' => 'Ghi chép 2 điểm thú vị và 1 băn khoăn sau khi tìm hiểu một ngày làm việc điển hình.',
            ],
            [
                'stage' => 'skills',
                'title' => 'Rèn luyện kỹ năng tự học và quản lý thời gian cá nhân',
                'description' => 'Áp dụng phương pháp Pomodoro và to-do list tuần để duy trì kỷ luật học tập.',
                'action_url' => '',
                'estimated_duration' => '15 phút/ngày',
                'expected_outcome' => 'Quy trình làm việc/học tập cá nhân áp dụng liên tục trong 2 tuần.',
            ],
        ],
        'decision' => [
            [
                'stage' => 'decision',
                'title' => 'Lập danh sách 3 phương án nguyện vọng ưu tiên (An toàn - Vừa sức - Thử thách)',
                'description' => 'Chia nhỏ danh sách trường mục tiêu để đảm bảo luôn có phương án dự phòng chắc chắn.',
                'action_url' => 'index.php?page=university-explorer',
                'estimated_duration' => '45 phút',
                'expected_outcome' => 'Danh mục 3 phương án trường (An toàn, Vừa sức, Thử thách) rõ ràng.',
            ],
            [
                'stage' => 'decision',
                'title' => 'Đánh giá chi phí học tập và các cơ hội học bổng / hỗ trợ tài chính',
                'description' => 'Tính toán học phí dự kiến, sinh hoạt phí và thảo luận cùng gia đình.',
                'action_url' => 'index.php?page=knowledge-hub',
                'estimated_duration' => '40 phút',
                'expected_outcome' => 'Bảng ước tính học phí, sinh hoạt phí và phương án tài chính dự phòng.',
            ],
            [
                'stage' => 'decision',
                'title' => 'Chuẩn bị hồ sơ xét tuyển và theo dõi lịch tuyển sinh chính thức',
                'description' => 'Nắm chắc các mốc thời gian nộp hồ sơ, thi đánh giá năng lực hoặc xét tuyển sớm.',
                'action_url' => 'index.php?page=knowledge-hub',
                'estimated_duration' => '30 phút',
                'expected_outcome' => 'Lịch trình các mốc nộp hồ sơ, thi ĐGNL (Cần kiểm tra lịch chính thức).',
            ],
        ],
    ];
}

/**
 * Map an action_url to a meaningful, human-friendly action button label.
 */
function user_roadmap_resolve_action(?string $url): array
{
    $url = trim((string)$url);
    if ($url === '') return ['label' => '', 'url' => ''];

    if (strpos($url, 'javascript:window.print') !== false) {
        return ['label' => 'In kế hoạch 🖨️', 'url' => $url];
    }
    if (strpos($url, 'assessment-report') !== false) {
        return ['label' => 'Xem báo cáo →', 'url' => $url];
    }
    if (strpos($url, 'page=assessment') !== false || strpos($url, 'assessment-hub') !== false) {
        return ['label' => 'Làm bài trắc nghiệm →', 'url' => $url];
    }
    if (strpos($url, 'page=majors') !== false) {
        return ['label' => 'Khám phá ngành học →', 'url' => $url];
    }
    if (strpos($url, 'university-explorer') !== false) {
        return ['label' => 'Tra cứu trường →', 'url' => $url];
    }
    if (strpos($url, 'simulations') !== false) {
        return ['label' => 'Vào phòng thử việc →', 'url' => $url];
    }
    if (strpos($url, 'knowledge-hub') !== false) {
        return ['label' => 'Đọc bài viết →', 'url' => $url];
    }
    if (strpos($url, 'career-chat') !== false) {
        return ['label' => 'Trò chuyện định hướng →', 'url' => $url];
    }

    return ['label' => 'Xem chi tiết →', 'url' => $url];
}

/**
 * Resolve expected concrete outcome for a milestone.
 */
function user_roadmap_resolve_outcome(array $milestone): string
{
    if (!empty($milestone['expected_outcome'])) {
        return $milestone['expected_outcome'];
    }

    $title = $milestone['title'] ?? '';
    $desc = $milestone['description'] ?? '';
    $url = $milestone['action_url'] ?? '';
    $combined = mb_strtolower($title . ' ' . $desc . ' ' . $url);

    if (mb_strpos($combined, 'holland') !== false || mb_strpos($combined, 'riasec') !== false) {
        return 'Mã 3 chữ cái RIASEC và danh sách ngành nghề tương ứng.';
    }
    if (mb_strpos($combined, 'big five') !== false || mb_strpos($combined, 'mbti') !== false || mb_strpos($combined, 'eq') !== false) {
        return 'Bản đồ nét tính cách và gợi ý môi trường học tập/làm việc phù hợp.';
    }
    if (mb_strpos($combined, 'đánh đổi') !== false || mb_strpos($combined, 'ranh giới') !== false) {
        return 'Bản ghi rõ những điều kiện muốn tránh (áp lực, di chuyển, học phí) để không bị sốc thực tế.';
    }
    if (mb_strpos($combined, 'mô phỏng') !== false || mb_strpos($combined, 'thử việc') !== false) {
        return 'Nhật ký phản tư ghi nhận 2 điểm hứng thú và 1 thử thách thực tế.';
    }
    if (mb_strpos($combined, '15 phút') !== false) {
        return 'Ghi chép 2 điểm thú vị và 1 băn khoăn sau khi tìm hiểu một ngày làm việc điển hình.';
    }
    if (mb_strpos($combined, 'ngành') !== false || mb_strpos($combined, 'majors') !== false) {
        return 'Mã ngành đào tạo chuẩn, các trường tuyển sinh và tổ hợp môn tương ứng.';
    }
    if (mb_strpos($combined, 'tổ hợp môn') !== false || mb_strpos($combined, 'điểm số') !== false) {
        return 'Kế hoạch điểm số mục tiêu cho 3 môn then chốt trong học kỳ tới.';
    }
    if (mb_strpos($combined, 'điểm chuẩn') !== false || mb_strpos($combined, 'trường') !== false) {
        return 'Bảng so sánh điểm chuẩn 2 năm gần nhất của 2–3 trường đào tạo mục tiêu.';
    }
    if (mb_strpos($combined, 'nguyện vọng') !== false || mb_strpos($combined, '3 phương án') !== false) {
        return 'Danh mục 3 phương án trường (An toàn, Vừa sức, Thử thách) rõ ràng.';
    }
    if (mb_strpos($combined, 'chi phí') !== false || mb_strpos($combined, 'học phí') !== false) {
        return 'Bảng ước tính học phí, sinh hoạt phí và ngân sách gia đình.';
    }
    if (mb_strpos($combined, 'tuyển sinh') !== false || mb_strpos($combined, 'hồ sơ') !== false) {
        return 'Lịch trình các mốc nộp hồ sơ, thi ĐGNL (Cần kiểm tra lịch chính thức).';
    }
    if (mb_strpos($combined, 'so sánh 3') !== false) {
        return 'Bảng đối chiếu 3 đối tượng theo các tiêu chí cụ thể.';
    }

    return 'Bản ghi kết quả cụ thể và bài học rút ra để điều chỉnh kế hoạch.';
}

/**
 * AI / Rule-based Suggestions Engine for on-demand prompts:
 * - month_suggestions: 3 focus tasks for the active month.
 * - breakdown: Break down a task into 2-3 smaller steps.
 * - time_adjust: Suggest realistic timeline matching user weekly hours.
 */
function user_roadmap_ai_generate_suggestions(int $userId, array $roadmap, string $month = '', string $type = 'month_suggestions', ?string $taskTitle = null): array
{
    $context = four_hub_ai_context($userId);
    $primaryCareer = !empty($roadmap['target_career_title']) ? $roadmap['target_career_title'] : ($context['suggested_careers'][0]['title'] ?? 'Nghề quan tâm');
    $goal = $roadmap['goal_title'] ?? 'Khám phá nghề nghiệp';
    $hours = !empty($roadmap['weekly_hours']) ? (int)$roadmap['weekly_hours'] : 3;

    // High pedagogical fallback suggestions
    if ($type === 'breakdown' && $taskTitle) {
        $fallbacks = [
            [
                'stage' => 'skills',
                'title' => 'Bước 1: Tìm hiểu 2 nguồn tài liệu uy tín về: ' . $taskTitle,
                'description' => 'Dành 15 phút đọc và gạch đầu dòng các yêu cầu cốt lõi.',
                'estimated_duration' => '15 phút',
                'expected_outcome' => 'Ghi chép 3 ý chính cần nắm rõ trước khi thực hiện.',
                'action_url' => 'index.php?page=knowledge-hub',
                'source' => 'ai',
            ],
            [
                'stage' => 'skills',
                'title' => 'Bước 2: Phác thảo dàn ý hoặc bản nháp đầu tiên',
                'description' => 'Tự tay viết hoặc làm thử một phần công việc mà không cầu toàn.',
                'estimated_duration' => '30 phút',
                'expected_outcome' => 'Một bản nháp hoặc sản phẩm sơ bộ hoàn thành.',
                'action_url' => '',
                'source' => 'ai',
            ],
            [
                'stage' => 'skills',
                'title' => 'Bước 3: Nhờ thầy cô hoặc bạn bè góp ý và hoàn thiện',
                'description' => 'Nhận xét khách quan giúp bạn nhận ra những chỗ cần làm rõ thêm.',
                'estimated_duration' => '20 phút',
                'expected_outcome' => 'Bản hoàn thiện kèm nhận xét góp ý cụ thể.',
                'action_url' => '',
                'source' => 'ai',
            ],
        ];
        return [
            'suggestions' => $fallbacks,
            'model' => 'pedagogical_rules',
            'note' => 'Gợi ý chia nhỏ các bước hành động cụ thể từ chuyên gia sư phạm.',
        ];
    }

    if ($type === 'time_adjust') {
        $fallbacks = [
            [
                'stage' => 'skills',
                'title' => 'Chia nhỏ lịch làm việc: 2 buổi / tuần, mỗi buổi 45 phút',
                'description' => "Phù hợp với quỹ thời gian $hours giờ/tuần của bạn, giúp duy trì nhịp độ ổn định mà không gây áp lực.",
                'estimated_duration' => '45 phút',
                'expected_outcome' => 'Lịch học tập cá nhân cố định 2 buổi/tuần.',
                'action_url' => '',
                'source' => 'ai',
            ],
            [
                'stage' => 'discovery',
                'title' => 'Tập trung vào 1 mục tiêu trọng tâm duy nhất trong tuần',
                'description' => 'Tránh ôm đồm nhiều việc; hoàn thành trọn vẹn 1 việc có kết quả rõ ràng.',
                'estimated_duration' => '60 phút',
                'expected_outcome' => 'Một đầu ra cụ thể hoàn thành mỗi tuần.',
                'action_url' => '',
                'source' => 'ai',
            ],
        ];
        return [
            'suggestions' => $fallbacks,
            'model' => 'pedagogical_rules',
            'note' => "Đề xuất tối ưu theo quỹ thời gian $hours giờ/tuần của bạn.",
        ];
    }

    // Default: month_suggestions
    $fallbacks = [
        [
            'stage' => 'academic',
            'title' => 'So sánh điểm chuẩn 2 năm gần nhất của 3 trường đào tạo ' . $primaryCareer,
            'description' => 'Xem xét chỉ tiêu, phương thức xét tuyển và học phí dự kiến trên Tra cứu trường ĐH.',
            'estimated_duration' => '45 phút',
            'expected_outcome' => 'Bảng đối chiếu điểm chuẩn, học phí và điều kiện xét tuyển 3 trường.',
            'action_url' => 'index.php?page=university-explorer',
            'source' => 'ai',
        ],
        [
            'stage' => 'skills',
            'title' => 'Thực hiện 1 thử việc mô phỏng thực tế liên quan đến ' . $primaryCareer,
            'description' => 'Giải quyết tình huống giả định và ghi lại 2 điểm thú vị cùng 1 thử thách khó khăn.',
            'estimated_duration' => '30 phút',
            'expected_outcome' => 'Nhật ký phản tư ghi nhận trải nghiệm nghề nghiệp thực tế.',
            'action_url' => 'index.php?page=simulations',
            'source' => 'ai',
        ],
        [
            'stage' => 'decision',
            'title' => 'Trao đổi với giáo viên hoặc người thân về 2 phương án đang phân vân',
            'description' => 'Lắng nghe góc nhìn từ người lớn về khả năng tài chính và cơ hội việc làm thực tế.',
            'estimated_duration' => '30 phút',
            'expected_outcome' => 'Ghi chú lời khuyên và điều chỉnh danh sách nguyện vọng.',
            'action_url' => 'index.php?page=ai-roadmap#career-chat',
            'source' => 'ai',
        ],
    ];

    $providers = chat_provider_config();
    $key = setting('openai_api_key');
    if (!$providers && !$key) {
        return [
            'suggestions' => $fallbacks,
            'model' => 'pedagogical_rules',
            'note' => 'Gợi ý mẫu được chọn lọc dựa trên mục tiêu "' . $primaryCareer . '" và hồ sơ của bạn.',
        ];
    }

    // Optional LLM enhancement if configured
    try {
        $prompt = "Gợi ý 3 công việc cụ thể cho tháng này cho học sinh đang hướng tới: $primaryCareer. " .
                  "Mục tiêu kế hoạch: $goal. Trả về JSON mảng 3 object: [{\"title\":\"Động từ + nội dung cụ thể\",\"description\":\"...\",\"stage\":\"discovery|academic|skills|decision\",\"estimated_duration\":\"...\",\"expected_outcome\":\"Kết quả đầu ra cụ thể\"}].";

        $input = [
            ['role' => 'system', 'content' => 'Bạn là chuyên gia tư vấn hướng nghiệp sư phạm THPT. Chỉ trả về JSON hợp lệ, không kèm văn bản giải thích.'],
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
                CURLOPT_TIMEOUT => 20,
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

        $text = trim($ans['message'] ?? '');
        if (preg_match('/\[\s*\{.*?\}\s*\]/s', $text, $match)) {
            $parsed = json_decode($match[0], true);
            if (is_array($parsed) && count($parsed) > 0) {
                $items = [];
                foreach ($parsed as $item) {
                    $st = in_array($item['stage'] ?? '', ['discovery', 'academic', 'skills', 'decision'], true) ? $item['stage'] : 'skills';
                    $items[] = [
                        'stage' => $st,
                        'title' => mb_substr(trim((string)($item['title'] ?? 'Công việc gợi ý')), 0, 255),
                        'description' => mb_substr(trim((string)($item['description'] ?? '')), 0, 1000),
                        'estimated_duration' => mb_substr(trim((string)($item['estimated_duration'] ?? '30 phút')), 0, 50),
                        'expected_outcome' => mb_substr(trim((string)($item['expected_outcome'] ?? 'Kết quả đầu ra cụ thể.')), 0, 500),
                        'action_url' => $st === 'academic' ? 'index.php?page=majors' : ($st === 'skills' ? 'index.php?page=simulations' : 'index.php?page=knowledge-hub'),
                        'source' => 'ai',
                    ];
                }
                return [
                    'suggestions' => $items,
                    'model' => $ans['model'] ?? 'ai',
                    'note' => 'Gợi ý được Trợ lý La Bàn cá nhân hóa theo mục tiêu "' . $primaryCareer . '" của bạn.',
                ];
            }
        }
    } catch (Throwable $e) {}

    return [
        'suggestions' => $fallbacks,
        'model' => 'pedagogical_rules',
        'note' => 'Gợi ý mẫu được chọn lọc dựa trên mục tiêu "' . $primaryCareer . '" và hồ sơ của bạn.',
    ];
}
