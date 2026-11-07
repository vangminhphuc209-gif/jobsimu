<?php
declare(strict_types=1);

function db(): PDO { global $pdo; return $pdo; }
function run_migrations(): void {
    $codeSchemaVersion = '2.1.0';
    try {
        if (db()->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return;
        $currentVersion = setting('db_schema_version', '');
        if ($currentVersion !== '' && version_compare($currentVersion, $codeSchemaVersion, '>=')) {
            return;
        }
        db()->exec("CREATE TABLE IF NOT EXISTS settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $columns = db()->query('SHOW COLUMNS FROM assessments')->fetchAll(PDO::FETCH_COLUMN);
        $alters = [
            'ai_status' => "ADD ai_status ENUM('pending','completed','fallback','failed') NOT NULL DEFAULT 'pending' AFTER ai_advice",
            'ai_model' => 'ADD ai_model VARCHAR(100) NULL AFTER ai_status',
            'ai_error' => 'ADD ai_error VARCHAR(500) NULL AFTER ai_model',
            'ai_generated_at' => 'ADD ai_generated_at DATETIME NULL AFTER ai_error',
        ];
        foreach ($alters as $column => $sql) if (!in_array($column, $columns, true)) db()->exec('ALTER TABLE assessments ' . $sql);
        if (!in_array('constraint_answers', $columns, true)) db()->exec('ALTER TABLE assessments ADD constraint_answers JSON NULL AFTER result_code');
        
        $careerColumns = db()->query('SHOW COLUMNS FROM careers')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('simulation_enabled', $careerColumns, true)) db()->exec("ALTER TABLE careers ADD simulation_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER simulation_label");
        
        $indexes = db()->query("SHOW INDEX FROM questions WHERE Key_name = 'uk_content'")->fetchAll();
        if (empty($indexes)) {
            db()->exec("ALTER TABLE questions ADD UNIQUE KEY uk_content (content(190))");
        }
        
        db()->exec("CREATE TABLE IF NOT EXISTS ai_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,assessment_id INT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,model VARCHAR(100) NULL,status ENUM('completed','fallback','failed') NOT NULL,latency_ms INT UNSIGNED NULL,http_status SMALLINT UNSIGNED NULL,error_message VARCHAR(500) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(assessment_id),INDEX(created_at),CONSTRAINT fk_ai_assessment FOREIGN KEY(assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,CONSTRAINT fk_ai_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        db()->exec("CREATE TABLE IF NOT EXISTS simulation_attempts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,career_id INT UNSIGNED NOT NULL,score TINYINT UNSIGNED NOT NULL,decisions JSON NOT NULL,skill_scores JSON NOT NULL,duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,reflection_feeling ENUM('excited','challenged','unsuited') NULL,reflection_note VARCHAR(300) NULL,completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(user_id),INDEX(career_id),INDEX(completed_at),CONSTRAINT fk_sim_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_sim_career FOREIGN KEY(career_id) REFERENCES careers(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $simCols = db()->query('SHOW COLUMNS FROM simulation_attempts')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('reflection_feeling', $simCols, true)) {
            db()->exec("ALTER TABLE simulation_attempts ADD reflection_feeling ENUM('excited','challenged','unsuited') NULL AFTER duration_seconds");
        }
        if (!in_array('reflection_note', $simCols, true)) {
            db()->exec("ALTER TABLE simulation_attempts ADD reflection_note VARCHAR(300) NULL AFTER reflection_feeling");
        }

        $careerSeed = db()->prepare("INSERT INTO careers(title,slug,icon,short_description,description,work_environment,skills,subjects,education_path,riasec_weights,simulation_enabled,featured,status) VALUES(?,?,?,?,?,?,?,?,?,?,0,0,'published') ON DUPLICATE KEY UPDATE title=VALUES(title),icon=VALUES(icon),short_description=VALUES(short_description),description=VALUES(description),work_environment=VALUES(work_environment),skills=VALUES(skills),subjects=VALUES(subjects),education_path=VALUES(education_path),riasec_weights=VALUES(riasec_weights),simulation_enabled=0");
        foreach (require ROOT_PATH . '/app/career-extensions.php' as $career) $careerSeed->execute([$career[0],$career[1],$career[2],$career[3],$career[4],$career[5],$career[6],$career[7],$career[8],json_encode($career[9],JSON_UNESCAPED_UNICODE)]);
        
        $stmt = db()->prepare("INSERT INTO settings(setting_key, setting_value) VALUES('db_schema_version', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $stmt->execute([$codeSchemaVersion]);
        setting('db_schema_version', $codeSchemaVersion, true);
    } catch (Throwable $ignored) {}
}
function cfg(string $key = '', $default = null) {
    global $config;
    if ($key === '') return $config;
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return rtrim((string)cfg('base_url', ''), '/') . '/' . ltrim($path, '/'); }
function redirect(string $path): void { header('Location: ' . (strpos($path, 'http') === 0 ? $path : url($path))); exit; }
function safe_external_url(?string $value): string {
    $value = trim((string)$value);
    if ($value === '' || !filter_var($value, FILTER_VALIDATE_URL)) return '';
    return in_array(mb_strtolower((string)parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) ? $value : '';
}
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'); } }
function flash(string $type, string $message): void { $_SESSION['flash'][] = compact('type', 'message'); }
function pull_flashes(): array { $items = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $items; }
function user(): ?array {
    static $cached = false;
    if ($cached !== false) return $cached;
    if (empty($_SESSION['user_id'])) return $cached = null;
    $stmt = db()->prepare("SELECT id, name, email, role, grade, school, avatar_color, status FROM users WHERE id=? AND status='active'");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        unset($_SESSION['user_id']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
            session_start();
        }
        return $cached = null;
    }
    return $cached = $user;
}
function require_login(): void { if (!user()) { flash('warning', 'Vui lòng đăng nhập để tiếp tục.'); redirect('index.php?page=login'); } }
function require_admin(): void { require_login(); if ((user()['role'] ?? '') !== 'admin') { http_response_code(403); exit('Bạn không có quyền truy cập.'); } }
function setting(string $key, string $default = '', bool $refresh = false): string {
    static $settings = null;
    if ($settings === null || $refresh) { try { $settings = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR); } catch (Throwable $ignored) { $settings = []; } }
    return (string)($settings[$key] ?? $default);
}
function riasec_labels(): array {
    return ['R'=>'Kỹ thuật','I'=>'Nghiên cứu','A'=>'Nghệ thuật','S'=>'Xã hội','E'=>'Quản lý','C'=>'Nghiệp vụ'];
}
function riasec_colors(): array { return ['R'=>'#5B7CFA','I'=>'#8B5CF6','A'=>'#EC4899','S'=>'#12B981','E'=>'#F59E0B','C'=>'#0EA5E9']; }
function simulation_skill_labels(): array { return ['analysis'=>'Phân tích','creativity'=>'Sáng tạo','empathy'=>'Thấu cảm','decision'=>'Ra quyết định','precision'=>'Chính xác']; }
function simulation_catalog(): array {
    static $catalog;
    if ($catalog === null) $catalog = require ROOT_PATH . '/app/simulations.php';
    return $catalog;
}
function simulation_for_career(array $career): ?array {
    $game = simulation_catalog()[$career['slug'] ?? ''] ?? null;
    if (!$game || array_key_exists('simulation_enabled', $career) && !(int)$career['simulation_enabled']) return null;
    return $game;
}
function calculate_simulation_result(array $game, array $decisions, array $missionKeys = []): array {
    if (isset($decisions['keys'], $decisions['choices'])) {
        $missionKeys = $decisions['keys'];
        $decisions = $decisions['choices'];
    }
    $allMissions = $game['missions'] ?? [];
    $missionsToGrade = [];
    if (!empty($missionKeys)) {
        foreach ($missionKeys as $k) {
            if (isset($allMissions[$k])) $missionsToGrade[] = $allMissions[$k];
        }
    } else {
        $missionsToGrade = count($allMissions) > 4 && count($decisions) <= 4 ? array_slice($allMissions, 0, count($decisions)) : $allMissions;
    }
    if (empty($missionsToGrade)) throw new InvalidArgumentException('Mô phỏng chưa có nhiệm vụ.');

    $skills = array_fill_keys(array_keys(simulation_skill_labels()), 0);
    $chosen = [];
    $points = 0;
    $max = 0;

    foreach ($missionsToGrade as $i => $mission) {
        $choiceIndex = isset($decisions[$i]) ? (int)$decisions[$i] : -1;
        if (!isset($mission['choices'][$choiceIndex])) throw new InvalidArgumentException('Lựa chọn mô phỏng không hợp lệ.');
        $choice = $mission['choices'][$choiceIndex];
        $choicePoints = max(0, min(3, (int)($choice['points'] ?? 0)));
        $points += $choicePoints;
        $max += 3;
        foreach (($choice['skills'] ?? []) as $skill => $value) {
            if (isset($skills[$skill])) $skills[$skill] += max(0, min(3, (int)$value));
        }
        $chosen[] = $choiceIndex;
    }

    $skillMax = max(1, count($missionsToGrade) * 3);
    foreach ($skills as $skill => $value) {
        $skills[$skill] = min(100, (int)round($value / $skillMax * 100));
    }
    arsort($skills);

    return [
        'score' => (int)round($points / $max * 100),
        'skills' => $skills,
        'decisions' => !empty($missionKeys) ? ['keys' => array_values($missionKeys), 'choices' => $chosen] : $chosen
    ];
}

function select_simulation_missions(array $game): array {
    $all = $game['missions'] ?? [];
    if (count($all) <= 4) {
        $picked = $all;
        foreach ($picked as $idx => &$m) {
            $m['original_mission_index'] = $idx;
            $choices = $m['choices'];
            foreach ($choices as $ci => &$c) { $c['original_index'] = $ci; }
            unset($c);
            shuffle($choices);
            $m['choices'] = $choices;
        }
        unset($m);
        return $picked;
    }

    $byStage = [];
    foreach ($all as $idx => $m) {
        $stage = (int)($m['stage'] ?? 1);
        $m['original_mission_index'] = $idx;
        $byStage[$stage][] = $m;
    }
    ksort($byStage);

    $picked = [];
    foreach ($byStage as $stage => $stageMissions) {
        $chosenMission = $stageMissions[array_rand($stageMissions)];
        $choices = $chosenMission['choices'];
        foreach ($choices as $ci => &$c) {
            if (!isset($c['original_index'])) $c['original_index'] = $ci;
        }
        unset($c);
        shuffle($choices);
        $chosenMission['choices'] = $choices;
        $picked[] = $chosenMission;
    }
    return $picked;
}

function riasec_question_meta(string $type, int $sortOrder): array {
    $themes = [
        'R' => ['icon' => '🛠️', 'label' => 'Hoạt động Thực hành & Kỹ thuật', 'context' => 'Hãy hình dung khi bạn trực tiếp lắp ráp, sửa chữa thiết bị, làm việc với công cụ hoặc hoạt động ngoài trời:'],
        'I' => ['icon' => '🔬', 'label' => 'Tư duy Khám phá & Nghiên cứu', 'context' => 'Hãy nhớ lại những lúc bạn tò mò tìm nguyên nhân gốc rễ, phân tích dữ liệu khoa học hoặc giải bài toán hóc búa:'],
        'A' => ['icon' => '🎨', 'label' => 'Góc Sáng tạo & Nghệ thuật', 'context' => 'Hãy cảm nhận mức độ bạn yêu thích việc phác thảo ý tưởng mới, sáng tác hình ảnh/âm nhạc hoặc tự do thể hiện phong cách riêng:'],
        'S' => ['icon' => '🤝', 'label' => 'Kết nối Cộng đồng & Giúp đỡ', 'context' => 'Hãy hình dung khi bạn lắng nghe, chia sẻ, hướng dẫn bạn bè học tập hoặc tham gia các hoạt động xã hội thiện nguyện:'],
        'E' => ['icon' => '💼', 'label' => 'Dẫn dắt & Thuyết phục', 'context' => 'Hãy liên hệ đến những dịp bạn đứng ra tổ chức sự kiện, thuyết phục tập thể, điều phối dự án hoặc thử sức kinh doanh nhỏ:'],
        'C' => ['icon' => '📊', 'label' => 'Tổ chức & Chi tiết Chính xác', 'context' => 'Hãy nghĩ về thói quen lập kế hoạch rõ ràng, quản lý thời gian biểu, sắp xếp tài liệu ngăn nắp và kiểm tra số liệu chuẩn xác:']
    ];
    return $themes[$type] ?? ['icon' => '🧭', 'label' => 'Khám phá Bản thân', 'context' => 'Mức độ câu này mô tả đúng về xu hướng và sở thích tự nhiên của bạn:'];
}

function career_matrix_status(int $matchPercent, ?array $latestAttempt): array {
    $hasAttempt = !empty($latestAttempt);
    $simScore = $hasAttempt ? (int)($latestAttempt['score'] ?? 0) : 0;
    $feeling = $hasAttempt ? ($latestAttempt['reflection_feeling'] ?? '') : '';

    if ($matchPercent >= 75) {
        if ($hasAttempt && $simScore >= 70 && $feeling === 'excited') {
            return [
                'type' => 'sweet_spot',
                'class' => 'matrix-tag matrix-sweet-spot',
                'badge' => '🌟 Mục tiêu Vàng (Sweet Spot)',
                'desc' => 'Sở thích lý thuyết và phản xạ trải nghiệm thực tế đều rất ăn khớp!'
            ];
        }
        return [
            'type' => 'reality_check',
            'class' => 'matrix-tag matrix-reality-check',
            'badge' => '⚠️ Cần Kiểm chứng Thực tế',
            'desc' => $hasAttempt ? 'Sở thích cao nhưng bạn cảm thấy còn áp lực hoặc băn khoăn khi trải nghiệm thực tế.' : 'Sở thích lý thuyết cao, hãy thử trải nghiệm mô phỏng để kiểm chứng!'
        ];
    }

    if ($hasAttempt && $simScore >= 75 && $feeling === 'excited') {
        return [
            'type' => 'hidden_gem',
            'class' => 'matrix-tag matrix-hidden-gem',
            'badge' => '💎 Tiềm năng Ẩn giấu (Hidden Gem)',
            'desc' => 'Dù điểm RIASEC lý thuyết chưa cao, bạn lại có phản xạ và cảm xúc rất tích cực khi trải nghiệm thật!'
        ];
    }

    return [
        'type' => 'neutral',
        'class' => 'matrix-tag matrix-neutral',
        'badge' => 'Khám phá thêm',
        'desc' => 'Tìm hiểu thêm khi bạn muốn mở rộng chân trời nghề nghiệp.'
    ];
}

function slugify(string $text): string {
    $map = ['á'=>'a','à'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a','ă'=>'a','ắ'=>'a','ằ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a','â'=>'a','ấ'=>'a','ầ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a','đ'=>'d','é'=>'e','è'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e','ê'=>'e','ế'=>'e','ề'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e','í'=>'i','ì'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i','ó'=>'o','ò'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o','ô'=>'o','ố'=>'o','ồ'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o','ơ'=>'o','ớ'=>'o','ờ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o','ú'=>'u','ù'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u','ư'=>'u','ứ'=>'u','ừ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u','ý'=>'y','ỳ'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y'];
    $text = strtr(mb_strtolower($text), $map); $text = preg_replace('/[^a-z0-9]+/', '-', $text); return trim($text, '-');
}
function score_assessment(array $answers, array $questions): array {
    $scores = array_fill_keys(array_keys(riasec_labels()), 0); $counts = $scores;
    foreach ($questions as $q) { $type = $q['riasec_type']; $scores[$type] += max(1, min(5, (int)($answers[$q['id']] ?? 1))); $counts[$type]++; }
    foreach ($scores as $type => $score) $scores[$type] = $counts[$type] ? (int)round($score / ($counts[$type] * 5) * 100) : 0;
    arsort($scores); return $scores;
}
function career_matches(array $scores, int $limit = 6, array $constraints = []): array {
    $jobs = db()->query("SELECT * FROM careers WHERE status='published' ORDER BY featured DESC, title")->fetchAll();
    if (empty($jobs)) return [];

    arsort($scores);
    $userTopTypes = array_slice(array_keys($scores), 0, 3);
    $userFirst = $userTopTypes[0] ?? '';

    $userNormSq = 0;
    foreach ($scores as $s) { $userNormSq += ((float)$s) ** 2; }
    $userNorm = sqrt($userNormSq) ?: 1.0;

    foreach ($jobs as &$job) {
        $weights = json_decode($job['riasec_weights'] ?: '{}', true) ?: [];
        arsort($weights);
        $jobTopTypes = array_slice(array_keys($weights), 0, 3);
        $jobFirst = $jobTopTypes[0] ?? '';

        $dotProduct = 0;
        $jobNormSq = 0;
        foreach ($scores as $type => $score) {
            $w = (float)($weights[$type] ?? 0);
            $dotProduct += ((float)$score) * $w;
            $jobNormSq += $w ** 2;
        }
        $jobNorm = sqrt($jobNormSq) ?: 1.0;
        $cosSim = max(0.0, min(1.0, $dotProduct / ($userNorm * $jobNorm)));

        $boost = 0;
        if (!empty($jobFirst) && in_array($jobFirst, $userTopTypes, true)) {
            $boost += 8;
            if ($jobFirst === $userFirst) $boost += 5;
        }
        $jobSecond = $jobTopTypes[1] ?? '';
        if (!empty($jobSecond) && in_array($jobSecond, $userTopTypes, true)) {
            $boost += 5;
        }

        $rawPercent = (int)round($cosSim * 78 + $boost);
        $job['match_percent'] = max(35, min(96, $rawPercent));
        $job['constraint_fit'] = career_constraint_fit($job, $constraints);
        $job['decision_score'] = $job['match_percent'] + $job['constraint_fit']['adjustment'];
    }
    unset($job);

    usort($jobs, fn($a, $b) => $b['decision_score'] <=> $a['decision_score'] ?: $b['match_percent'] <=> $a['match_percent']);
    return array_slice($jobs, 0, $limit);
}
function career_requirements_catalog(): array { static $data; if ($data === null) $data = require ROOT_PATH . '/app/career-requirements.php'; return $data; }
function career_constraint_fit(array $career, array $answers): array {
    $profile = career_requirements_catalog()[$career['slug'] ?? ''] ?? [];
    $adjustment = 0; $notes = [];
    $provided = array_filter($answers, fn($value) => !in_array($value, ['unknown','any','skip'], true));
    if (!$profile || !$provided) return ['label'=>'Chưa đối chiếu điều kiện','class'=>'neutral','notes'=>[],'adjustment'=>0,'group'=>$profile['group'] ?? ''];
    if (isset($answers['budget']) && isset($profile['cost']) && $answers['budget'] !== 'unknown' && ($answers['scholarship'] ?? '') !== 'yes' && (int)$answers['budget'] < (int)$profile['cost']) { $adjustment -= 4; $notes[] = 'Nên tìm học bổng, trường công hoặc phương án vừa học vừa làm.'; }
    if (($answers['study_path'] ?? 'any') !== 'any' && !in_array($answers['study_path'], $profile['paths'] ?? [], true)) { $adjustment -= 3; $notes[] = 'Lộ trình bạn ưu tiên khác với lộ trình phổ biến; hãy kiểm tra phương án tương đương.'; }
    if (($answers['study_years'] ?? 'any') === 'short' && (int)($profile['years'] ?? 0) > 4) { $adjustment -= 2; $notes[] = 'Đây thường là lộ trình học dài; có thể tìm vai trò gần ngành với thời gian đào tạo ngắn hơn.'; }
    if (($answers['activity'] ?? 'any') === 'low' && ($profile['physical'] ?? 'low') === 'high') { $adjustment -= 2; $notes[] = 'Công việc có cường độ vận động cao; thể lực tự khai không phải tiêu chuẩn tuyển sinh.'; }
    if (!empty($profile['selection'])) $notes[] = 'Có thể có sơ tuyển hoặc tiêu chuẩn riêng; cần kiểm tra thông báo chính thức hiện hành.';
    if (!empty($profile['license'])) $notes[] = 'Một số vai trò cần đào tạo hoặc chứng nhận hành nghề; hãy kiểm tra yêu cầu chính thức.';
    if (!empty($profile['portfolio']) && ($answers['portfolio'] ?? '') === 'no') { $adjustment -= 1; $notes[] = 'Một số chương trình yêu cầu năng khiếu hoặc portfolio; có thể bắt đầu luyện và xây hồ sơ.'; }
    return ['label'=>$notes ? 'Có điểm cần chuẩn bị' : 'Khá phù hợp với ưu tiên đã chọn','class'=>$notes ? 'planning' : 'ready','notes'=>$notes,'adjustment'=>$adjustment,'group'=>$profile['group'] ?? ''];
}
function ai_advice(array $scores, array $matches, array $student): array {
    $topHolland = array_slice(array_map(fn($k)=>riasec_labels()[$k], array_keys($scores)), 0, 2);
    $constraintSummary = $student['constraint_summary'] ?? '';
    $fitNotes = [];
    $groups = [];
    foreach (array_slice($matches, 0, 3) as $career) if (!empty($career['constraint_fit']['group'])) $groups[] = $career['constraint_fit']['group'];
    $groups = array_values(array_unique($groups));
    foreach (array_slice($matches, 0, 3) as $career) if (!empty($career['constraint_fit']['notes'][0])) $fitNotes[] = $career['title'] . ': ' . $career['constraint_fit']['notes'][0];
    $sims = $student['sim_telemetry'] ?? [];
    $simNote = '';
    if (!empty($sims)) {
        $firstSim = $sims[0];
        $feelingText = $firstSim['reflection_feeling'] === 'excited' ? 'hào hứng' : ($firstSim['reflection_feeling'] === 'challenged' ? 'áp lực' : 'chưa phù hợp');
        $simNote = ' Bạn đã thử mô phỏng nghề ' . $firstSim['title'] . ' (Chỉ số Phản xạ: ' . $firstSim['score'] . '%, cảm nhận: ' . $feelingText . ').';
    }

    $fallback = [
        'summary' => 'Bạn nổi bật ở nhóm ' . implode(' và ', $topHolland) . '.' . $simNote . ($constraintSummary ? ' Khảo sát điều kiện: ' . $constraintSummary : '') . ($groups ? ' Nhóm ngành gợi ý: ' . implode(', ', $groups) . '.' : '') . ' Đây là nền tảng tốt để khám phá sự giao thoa giữa sở thích, lộ trình học và trải nghiệm thực tế.',
        'strengths' => ['Tổ hợp thế mạnh RIASEC thể hiện rõ xu hướng hành vi tự nhiên', 'Khả năng quan sát và điều chỉnh bản thân qua từng trải nghiệm', 'Sẵn sàng dấn thân cọ xát với các tình huống công việc thực tế'],
        'reality_check' => (!empty($sims) ? 'Bạn đã bắt đầu kiểm chứng sở thích qua thực tế mô phỏng. Hãy chú ý những tình huống khiến bạn thấy hào hứng nhất để định hình chuyên ngành.' : 'Hãy dành thời gian thử nghiệm ít nhất 1 phòng mô phỏng để đo lường cảm xúc thật của bạn trước áp lực công việc.') . ($fitNotes ? ' Điểm cần chuẩn bị: ' . implode(' ', $fitNotes) : ''),
        'next_steps' => ['Xem chi tiết lộ trình học tập của 3 nghề phù hợp nhất', 'Hoàn thành ít nhất một thử thách 15 phút ngoài đời thực', 'Trao đổi kết quả hồ sơ với thầy cô hướng nghiệp hoặc phụ huynh'],
        'note' => 'Kết quả mang tính gợi mở định hướng, không phải là chẩn đoán hay quyết định thay cho bạn.'
    ];
    $key = setting('openai_api_key');
    if (!$key || !function_exists('curl_init')) return ['advice'=>$fallback,'status'=>'fallback','model'=>null,'error'=>$key?'PHP cURL chưa được bật':'Chưa cấu hình OpenAI API key','http_status'=>null,'latency_ms'=>0];
    $model = setting('openai_model', 'gpt-5-mini');
    $careerNames = array_map(fn($career) => $career['title'] . ' (' . ($career['constraint_fit']['label'] ?? 'chưa đối chiếu điều kiện') . ')', array_slice($matches,0,5));
    $simSummary = array_map(fn($x) => [
        'nghe' => $x['title'] ?? '',
        'diem_phan_xa' => $x['score'] ?? 0,
        'cam_nhan' => $x['reflection_feeling'] ?? '',
        'ghi_chu' => $x['reflection_note'] ?? ''
    ], $sims);

    $prompt = 'Bạn là chuyên viên hướng nghiệp cho học sinh Việt Nam. Trả về JSON đúng schema: {"summary":string,"strengths":[string,string,string],"reality_check":string,"next_steps":[string,string,string],"note":string}. Không khẳng định tuyệt đối. Hãy đối chiếu RIASEC với lộ trình học và điều kiện người học tự khai. Chi phí chỉ là ước lượng, không loại nghề; thể lực không thay tiêu chuẩn chính thức. Dữ liệu học sinh: ' . json_encode(['lop'=>$student['grade'] ?? '', 'diem_RIASEC'=>$scores, 'nghe_goi_y'=>$careerNames, 'chi_tiet_dieu_kien_nghe'=>array_map(fn($x)=>['nghe'=>$x['title'],'nhom_nganh'=>$x['constraint_fit']['group'] ?? '', 'nhan'=>$x['constraint_fit']['label'] ?? '', 'ghi_chu'=>$x['constraint_fit']['notes'] ?? []], array_slice($matches,0,5)), 'dieu_kien_hoc_tap'=>$student['constraint_answers'] ?? [], 'dieu_kien_summary'=>$constraintSummary, 'trai_nghiem_mo_phong'=>$simSummary], JSON_UNESCAPED_UNICODE);
    $payload = [
        'model' => $model,
        'input' => [
            ['role' => 'system', 'content' => [['type' => 'input_text', 'text' => 'Tư vấn tích cực, thực tế, ngắn gọn, phù hợp lứa tuổi học sinh, đối chiếu sâu sắc giữa lý thuyết và trải nghiệm mô phỏng, chỉ xuất JSON.']]],
            ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $prompt]]],
        ],
        'text' => ['format' => [
            'type' => 'json_schema', 'name' => 'career_advice', 'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'summary' => ['type' => 'string'],
                    'strengths' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 3, 'maxItems' => 3],
                    'reality_check' => ['type' => 'string'],
                    'next_steps' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 3, 'maxItems' => 3],
                    'note' => ['type' => 'string'],
                ],
                'required' => ['summary', 'strengths', 'reality_check', 'next_steps', 'note'],
                'additionalProperties' => false,
            ],
        ]],
    ];
    $started=microtime(true);$ch = curl_init('https://api.openai.com/v1/responses'); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>45,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE)]);
    $raw = curl_exec($ch); $status = curl_getinfo($ch,CURLINFO_HTTP_CODE);$curlError=curl_error($ch); curl_close($ch);$latency=(int)round((microtime(true)-$started)*1000);
    if ($status < 200 || $status >= 300 || !$raw) {$api=json_decode((string)$raw,true);$error=$api['error']['message']??$curlError?:'OpenAI API không phản hồi';return ['advice'=>$fallback,'status'=>'fallback','model'=>$model,'error'=>mb_substr($error,0,500),'http_status'=>$status?:null,'latency_ms'=>$latency];}
    $data = json_decode($raw,true); $text = '';
    foreach (($data['output'] ?? []) as $output) foreach (($output['content'] ?? []) as $content) {
        if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) { $text = $content['text']; break 2; }
    }
    $parsed = json_decode($text,true);
    if (!is_array($parsed) || !isset($parsed['summary'])) return ['advice'=>$fallback,'status'=>'fallback','model'=>$model,'error'=>'AI trả về dữ liệu không đúng định dạng','http_status'=>$status,'latency_ms'=>$latency];
    return ['advice'=>$parsed,'status'=>'completed','model'=>$model,'error'=>null,'http_status'=>$status,'latency_ms'=>$latency];
}
function generate_assessment_ai(int $assessmentId, int $userId): array {
    $s=db()->prepare('SELECT a.*,u.name,u.grade,u.school FROM assessments a JOIN users u ON u.id=a.user_id WHERE a.id=? AND a.user_id=?');$s->execute([$assessmentId,$userId]);$assessment=$s->fetch();
    if(!$assessment) throw new RuntimeException('Không tìm thấy kết quả đánh giá.');
    $assessment['constraint_answers'] = json_decode($assessment['constraint_answers'] ?? '{}', true) ?: [];
    $assessment['constraint_summary'] = '';
    if ($assessment['constraint_answers']) { $summary = []; if (($assessment['constraint_answers']['study_path'] ?? 'any') !== 'any') $summary[] = ['university'=>'ưu tiên đại học','college'=>'ưu tiên cao đẳng','vocational'=>'ưu tiên học nghề'][$assessment['constraint_answers']['study_path']]; if (($assessment['constraint_answers']['budget'] ?? 'unknown') !== 'unknown') $summary[] = 'đã cho biết khoảng ngân sách năm học'; if (($assessment['constraint_answers']['activity'] ?? 'any') !== 'any') $summary[] = 'đã nêu mức vận động mong muốn'; $assessment['constraint_summary'] = implode(', ', $summary); }

    // Fetch user simulation telemetry
    $simStmt=db()->prepare('SELECT sa.score, sa.reflection_feeling, sa.reflection_note, sa.skill_scores, c.title, c.slug FROM simulation_attempts sa JOIN careers c ON c.id=sa.career_id WHERE sa.user_id=? ORDER BY sa.completed_at DESC LIMIT 5');
    $simStmt->execute([$userId]);
    $assessment['sim_telemetry'] = $simStmt->fetchAll();

    $scores=json_decode($assessment['scores'],true)?:[];$s=db()->prepare('SELECT c.*,m.match_percent,m.rank_order FROM assessment_matches m JOIN careers c ON c.id=m.career_id WHERE m.assessment_id=? ORDER BY m.rank_order');$s->execute([$assessmentId]);$matches=$s->fetchAll();foreach($matches as &$match)$match['constraint_fit']=career_constraint_fit($match,$assessment['constraint_answers']);unset($match);$result=ai_advice($scores,$matches,$assessment);
    $s=db()->prepare('UPDATE assessments SET ai_advice=?,ai_status=?,ai_model=?,ai_error=?,ai_generated_at=CURRENT_TIMESTAMP WHERE id=?');$s->execute([json_encode($result['advice'],JSON_UNESCAPED_UNICODE),$result['status'],$result['model'],$result['error'],$assessmentId]);
    $s=db()->prepare('INSERT INTO ai_logs(assessment_id,user_id,model,status,latency_ms,http_status,error_message) VALUES(?,?,?,?,?,?,?)');$s->execute([$assessmentId,$userId,$result['model'],$result['status'],$result['latency_ms'],$result['http_status'],$result['error']]);return $result;
}
function audit(string $action, string $entity = '', ?int $entityId = null): void {
    try { $stmt=db()->prepare('INSERT INTO audit_logs(user_id,action,entity_type,entity_id,ip_address) VALUES(?,?,?,?,?)'); $stmt->execute([user()['id']??null,$action,$entity,$entityId,$_SERVER['REMOTE_ADDR']??'']); } catch(Throwable $ignored) {}
}
