<?php
declare(strict_types=1);

/** Additive migration. Propagate failures so callers must not mark version 2.2.0 ready. */
function run_four_hub_migrations(): void
{
    $driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
    if (!in_array($driver, ['mysql', 'sqlite'], true)) {
        throw new RuntimeException('Unsupported database driver for four hubs.');
    }
    $locked = false;
    if ($driver === 'mysql') {
        $lock = db()->prepare('SELECT GET_LOCK(?, 10)');
        $lock->execute(['jobsimu_hubs_' . sha1((string)db()->query('SELECT DATABASE()')->fetchColumn())]);
        $locked = (int)$lock->fetchColumn() === 1;
        if (!$locked) throw new RuntimeException('Four-hub migration is busy. Please retry.');
    }
    try {
        if ($driver === 'mysql') {
            $sql = file_get_contents(ROOT_PATH . '/database/migrations/2026_11_four_hubs_upgrade.sql');
            if ($sql === false) throw new RuntimeException('Four-hub migration file is missing.');
            foreach (explode(';', $sql) as $statement) {
                if (trim($statement) !== '') db()->exec($statement);
            }
        } else {
            foreach (four_hub_sqlite_schema() as $statement) db()->exec($statement);
        }
        four_hub_seed_content();
    } finally {
        if ($locked) {
            $release = db()->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute(['jobsimu_hubs_' . sha1((string)db()->query('SELECT DATABASE()')->fetchColumn())]);
        }
    }
}

/** SQLite is for local development; production uses the reviewed MySQL migration. */
function four_hub_sqlite_schema(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS knowledge_articles (id INTEGER PRIMARY KEY AUTOINCREMENT,title VARCHAR(200) NOT NULL,slug VARCHAR(220) NOT NULL UNIQUE,category TEXT NOT NULL DEFAULT 'admissions' CHECK(category IN ('admissions','trend','guide')),summary VARCHAR(400) NOT NULL,content TEXT NOT NULL,source_name VARCHAR(120),source_url VARCHAR(500),related_career_slug VARCHAR(180),featured INTEGER NOT NULL DEFAULT 0,status TEXT NOT NULL DEFAULT 'published' CHECK(status IN ('published','draft')),created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
        'CREATE INDEX IF NOT EXISTS idx_articles_category_status ON knowledge_articles(category,status)',
        "CREATE TABLE IF NOT EXISTS external_portals (id INTEGER PRIMARY KEY AUTOINCREMENT,title VARCHAR(150) NOT NULL,organization VARCHAR(150) NOT NULL,icon VARCHAR(20) NOT NULL DEFAULT '🌐',description VARCHAR(300) NOT NULL,target_url VARCHAR(500) NOT NULL,category TEXT NOT NULL DEFAULT 'career_directory' CHECK(category IN ('official_admissions','career_directory','scholarship','skills')),sort_order INTEGER NOT NULL DEFAULT 0,status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','inactive')),created_at TEXT DEFAULT CURRENT_TIMESTAMP)",
        "CREATE TABLE IF NOT EXISTS user_psychometrics (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL,test_type TEXT NOT NULL CHECK(test_type IN ('mbti','eq')),answers_json TEXT NOT NULL,scores_json TEXT NOT NULL,result_code VARCHAR(32) NOT NULL,summary_label VARCHAR(150) NOT NULL,completed_at TEXT DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)",
        'CREATE INDEX IF NOT EXISTS idx_psych_user_type ON user_psychometrics(user_id,test_type,completed_at)',
        "CREATE TABLE IF NOT EXISTS ai_chat_messages (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL,role TEXT NOT NULL CHECK(role IN ('user','assistant')),message TEXT NOT NULL,context_snapshot TEXT,model VARCHAR(100),latency_ms INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)",
        'CREATE INDEX IF NOT EXISTS idx_chat_user_time ON ai_chat_messages(user_id,created_at)',
    ];
}

function four_hub_seed_content(): void
{
    // Editorial guides are evergreen examples, never represented as current admissions news.
    $articles = [
        ['doc-de-an-tuyen-sinh','Cách đọc một đề án tuyển sinh','guide','Tài liệu tham khảo: kiểm tra ngành, điều kiện, phương thức và mốc thời gian từ nguồn chính thức.','Bắt đầu từ website chính thức của cơ sở đào tạo. Ghi lại tên ngành, mã ngành, cơ sở học, học phí và điều kiện xét tuyển. Đối chiếu phương thức tuyển sinh, tổ hợp môn và thời hạn nộp hồ sơ với đề án của đúng năm bạn dự tuyển. Lưu đường dẫn cùng ngày kiểm tra. Nếu thông tin chưa rõ, liên hệ bộ phận tuyển sinh của trường. Bài hướng dẫn này không công bố điểm chuẩn hoặc lịch tuyển sinh của bất kỳ năm nào.', null, 1],
        ['thu-nghe-truoc-khi-chon','Thử một nhiệm vụ nhỏ trước khi chọn nghề','guide','Tài liệu tham khảo: dùng trải nghiệm và ghi chép để kiểm chứng sở thích nghề nghiệp.','Chọn một nhiệm vụ an toàn kéo dài 15 phút liên quan tới nghề bạn đang tìm hiểu. Với nghề giáo viên, hãy giải thích một kiến thức quen thuộc cho một người bạn tự nguyện tham gia. Hỏi điều gì đã rõ và điều gì cần giải thích lại. Sau đó ghi lại cảm xúc, việc bạn thích làm và một điều muốn thử khác. Một lượt trải nghiệm không đủ để kết luận bạn phù hợp hay không phù hợp với nghề.', 'giao-vien', 1],
        ['doc-thong-tin-xu-huong-nghe','Đọc thông tin xu hướng nghề nghiệp có căn cứ','trend','Tài liệu tham khảo: phân biệt dự báo thị trường với cam kết việc làm.','Khi đọc nhận định về nghề đang tăng nhu cầu, hãy tìm đơn vị công bố, thời điểm khảo sát, địa bàn và nhóm người được khảo sát. Phân biệt số lượng tin tuyển dụng với số việc làm thực tế. Đối chiếu nhiều nguồn và xem công việc hằng ngày, yêu cầu học tập, điều kiện cá nhân trước khi ra quyết định. Không có bài trắc nghiệm hay dự báo nào bảo đảm việc làm trong tương lai.', null, 0],
    ];
    $insert = db()->prepare("INSERT INTO knowledge_articles(slug,title,category,summary,content,related_career_slug,featured,source_name,status) SELECT ?,?,?,?,?,?,?,'JOBSIMU — hướng dẫn tham khảo','published' WHERE NOT EXISTS (SELECT 1 FROM knowledge_articles WHERE slug=?)");
    foreach ($articles as $article) {
        $params = $article;
        $params[] = $article[0];
        $insert->execute($params);
    }
    $portals = [
        ['Bộ Giáo dục và Đào tạo','Bộ Giáo dục và Đào tạo','🏛️','Tra cứu thông tin và thông báo giáo dục từ cơ quan quản lý.','https://moet.gov.vn/','official_admissions',10],
        ['Danh mục ngành nghề Hướng Nghiệp Việt','Hướng Nghiệp Việt','🧭','Tham khảo các nhóm ngành và nội dung khám phá nghề nghiệp.','https://huongnghiepviet.com/nganh-nghe','career_directory',20],
    ];
    $insertPortal = db()->prepare("INSERT INTO external_portals(title,organization,icon,description,target_url,category,sort_order) SELECT ?,?,?,?,?,?,? WHERE NOT EXISTS (SELECT 1 FROM external_portals WHERE target_url=?)");
    foreach ($portals as $portal) {
        $params = $portal;
        $params[] = $portal[4];
        $insertPortal->execute($params);
    }
}

/** Latest saved result for each questionnaire, including decoded answers and scores. */
function four_hub_latest_psychometrics(int $userId): array
{
    $stmt = db()->prepare('SELECT p.* FROM user_psychometrics p WHERE p.user_id=? AND NOT EXISTS (SELECT 1 FROM user_psychometrics newer WHERE newer.user_id=p.user_id AND newer.test_type=p.test_type AND (newer.completed_at>p.completed_at OR (newer.completed_at=p.completed_at AND newer.id>p.id)))');
    $stmt->execute([$userId]);
    $results = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['answers'] = four_hub_json_object($row['answers_json']);
        $row['scores'] = four_hub_json_object($row['scores_json']);
        $results[$row['test_type']] = $row;
    }
    return $results;
}

function four_hub_json_object(?string $json): array
{
    $value = json_decode($json ?? '{}', true);
    return is_array($value) ? $value : [];
}

/** Only allow known optional game tables; never interpolate request-controlled table names. */
function four_hub_game_tables_available(): bool
{
    $driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $stmt = db()->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN (?,?)");
    } else {
        $stmt = db()->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN (?,?)");
    }
    $stmt->execute(['game_runs', 'game_reports']);
    return (int)$stmt->fetchColumn() === 2;
}

/**
 * Read-only timeline. 3D evidence stays qualitative; it is never converted into a score.
 * Each item: mode,id,title,career_slug,completed_at,status,skills,competencies,
 * reflection_feeling,reflection_note,report. Optional game tables may be absent.
 */
function four_hub_activity(int $userId, int $limit = 30): array
{
    $limit = max(1, min(100, $limit));
    $stmt = db()->prepare('SELECT a.*,c.title,c.slug FROM simulation_attempts a JOIN careers c ON c.id=a.career_id WHERE a.user_id=? ORDER BY a.completed_at DESC,a.id DESC LIMIT ' . $limit);
    $stmt->execute([$userId]);
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $skills = [];
        foreach (four_hub_json_object($row['skill_scores']) as $key => $value) {
            if (in_array($key, ['analysis','creativity','empathy','decision','precision'], true) && is_numeric($value)) $skills[$key] = max(0, min(100, (float)$value));
        }
        $items[] = [
            'mode'=>'2D','id'=>(string)$row['id'],'title'=>$row['title'],'career_slug'=>$row['slug'],
            'completed_at'=>$row['completed_at'],'status'=>'completed','skills'=>$skills,'competencies'=>[],
            'reflection_feeling'=>$row['reflection_feeling'] ?? null,'reflection_note'=>$row['reflection_note'] ?? '',
            'report'=>[],
        ];
    }
    if (four_hub_game_tables_available()) {
        $stmt = db()->prepare('SELECT r.id,r.career_id,r.status,r.updated_at,p.summary_json,p.completed_at FROM game_runs r LEFT JOIN game_reports p ON p.run_id=r.id WHERE r.user_id=? ORDER BY COALESCE(p.completed_at,r.updated_at) DESC,r.id DESC LIMIT ' . $limit);
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) {
            $report = four_hub_json_object($row['summary_json']);
            $reflection = $report['pedagogicalReflection']['reflectionInput'] ?? [];
            $reflection = is_array($reflection) ? $reflection : [];
            $feeling = $reflection['reflection_feeling'] ?? $reflection['feeling'] ?? null;
            $note = $reflection['reflection_note'] ?? $reflection['note'] ?? '';
            $items[] = [
                'mode'=>'3D','id'=>$row['id'],'title'=>$row['career_id']==='giao-vien'?'Giáo viên — Người thầy sau tiếng trống trường':$row['career_id'],
                'career_slug'=>$row['career_id'],'completed_at'=>$row['completed_at'] ?? $row['updated_at'],
                'status'=>$row['status'],'skills'=>[],
                'competencies'=>is_array($report['competencies'] ?? null)?$report['competencies']:[],
                'reflection_feeling'=>in_array($feeling,['excited','challenged','unsuited'],true)?$feeling:null,
                'reflection_note'=>is_string($note)?$note:'','report'=>$report,
            ];
        }
    }
    usort($items, static function (array $a, array $b): int {
        $comparison = strcmp($b['completed_at'], $a['completed_at']);
        return $comparison !== 0 ? $comparison : strcmp($b['mode'] . $b['id'], $a['mode'] . $a['id']);
    });
    return array_slice($items, 0, $limit);
}
