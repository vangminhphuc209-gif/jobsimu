<?php
declare(strict_types=1);

require_once __DIR__ . '/career-conversations.php';

/**
 * Tokenize a user query into normalized keywords for searching.
 */
function may_tokenize_query(string $query): array {
    $normalized = mb_strtolower(trim($query));
    // Remove punctuation
    $cleaned = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized);
    $words = preg_split('/\s+/u', (string)$cleaned, -1, PREG_SPLIT_NO_EMPTY);
    
    $stopWords = [
        'là', 'gì', 'như', 'thế', 'nào', 'ở', 'của', 'cho', 'và', 'các', 'những',
        'với', 'tôi', 'em', 'mình', 'bạn', 'có', 'không', 'được', 'này', 'đó',
        'hãy', 'giúp', 'hỏi', 'về', 'trong', 'để', 'làm', 'sao', 'muốn',
        'hoàn', 'toàn', 'tồn', 'tại', 'rất', 'lắm', 'quá', 'luôn', 'chỉ', 'nữa',
        'còn', 'đã', 'đang', 'sẽ', 'biết', 'thấy', 'xem', 'hơn', 'nhất', 'bao',
        'nhiêu', 'đâu', 'nào', 'mới', 'vừa', 'qua', 'lại', 'thêm'
    ];
    $stopMap = array_flip($stopWords);
    
    $tokens = [];
    foreach ($words as $w) {
        if (mb_strlen($w) >= 2 && !isset($stopMap[$w])) {
            $tokens[] = $w;
        }
    }
    return array_values(array_unique($tokens));
}

/**
 * Search published careers from `careers` table.
 */
function may_search_careers(string $query, int $limit = 3, ?PDO $pdo = null): array {
    $pdo = $pdo ?? db();
    $tokens = may_tokenize_query($query);
    if (empty($tokens)) {
        $stmt = $pdo->prepare("SELECT id, title, slug, icon, short_description, skills, salary_range, education_path FROM careers WHERE status = 'published' AND featured = 1 LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
    } else {
        $clauses = [];
        $params = [];
        foreach ($tokens as $t) {
            $clauses[] = "(title LIKE ? OR short_description LIKE ? OR skills LIKE ? OR subjects LIKE ?)";
            $term = '%' . $t . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        $sql = "SELECT id, title, slug, icon, short_description, skills, salary_range, education_path FROM careers WHERE status = 'published' AND (" . implode(' OR ', $clauses) . ") LIMIT " . (int)$limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    }

    $results = [];
    foreach ($rows as $r) {
        $results[] = [
            'id' => 'career-' . $r['id'],
            'type' => 'career',
            'title' => ($r['icon'] ? $r['icon'] . ' ' : '💼 ') . $r['title'],
            'source' => 'Thư viện nghề JobSimu',
            'year' => null,
            'status' => 'published',
            'summary' => $r['short_description'] . ($r['skills'] ? ' | Kỹ năng: ' . $r['skills'] : '') . ($r['salary_range'] ? ' | Thu nhập tham khảo: ' . $r['salary_range'] : ''),
            'url' => 'index.php?page=career&id=' . (int)$r['id'],
            'career_id' => (int)$r['id'],
            'slug' => $r['slug']
        ];
    }
    return $results;
}

/**
 * Search training majors from `majors` and `training_majors`.
 */
function may_search_majors(string $query, int $limit = 3, ?PDO $pdo = null): array {
    $pdo = $pdo ?? db();
    $tokens = may_tokenize_query($query);
    if (empty($tokens)) return [];

    $clauses = [];
    $params = [];
    foreach ($tokens as $t) {
        $clauses[] = "(m.name LIKE ? OR m.code LIKE ? OR m.description LIKE ?)";
        $term = '%' . $t . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $sql = "SELECT m.id, m.code, m.name, m.description,
            GROUP_CONCAT(DISTINCT c.title SEPARATOR ', ') AS related_careers
            FROM majors m
            LEFT JOIN career_major_paths cmp ON cmp.major_id = m.id
            LEFT JOIN careers c ON c.id = cmp.career_id AND c.status = 'published'
            WHERE (" . implode(' OR ', $clauses) . ")
            GROUP BY m.id
            LIMIT " . (int)$limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $results = [];
    foreach ($rows as $r) {
        $summary = $r['description'] ?: 'Ngành đào tạo chuyên môn cung cấp nền tảng kiến thức nghề nghiệp.';
        if (!empty($r['related_careers'])) {
            $summary .= ' (Hướng nghề nghiệp liên quan: ' . $r['related_careers'] . ')';
        }
        $results[] = [
            'id' => 'major-' . ($r['code'] ?: $r['id']),
            'type' => 'major',
            'title' => 'Ngành ' . $r['name'] . ($r['code'] ? ' (Mã: ' . $r['code'] . ')' : ''),
            'source' => 'Danh mục ngành đào tạo JobSimu',
            'year' => '2024-2025',
            'status' => 'verified',
            'summary' => $summary,
            'url' => 'index.php?page=majors&search=' . urlencode($r['code'] ?: $r['name']),
            'major_code' => $r['code']
        ];
    }
    return $results;
}

/**
 * Search admission records (cutoffs) and tuition bands.
 */
function may_search_admissions(string $query, int $limit = 4, ?PDO $pdo = null): array {
    $pdo = $pdo ?? db();
    $tokens = may_tokenize_query($query);
    if (empty($tokens)) return [];

    $results = [];
    $lower = mb_strtolower($query);
    $isCutoffQuery = str_contains($lower, 'điểm chuẩn') || str_contains($lower, 'điểm trúng tuyển') || str_contains($lower, 'xét tuyển') || str_contains($lower, 'tổ hợp') || str_contains($lower, 'điểm');
    $isTuitionQuery = str_contains($lower, 'học phí') || str_contains($lower, 'chi phí') || str_contains($lower, 'tiền học');

    $genericAdmissionTerms = ['trường', 'đại', 'học', 'điểm', 'chuẩn', 'xét', 'tuyển', 'phí', 'khoảng', 'bao', 'nhiêu'];
    $specificTokens = array_values(array_diff($tokens, $genericAdmissionTerms));
    $activeTokens = !empty($specificTokens) ? $specificTokens : $tokens;

    // 1. If cutoff query, search admission_records first
    if ($isCutoffQuery || !$isTuitionQuery) {
        $clauses = [];
        $params = [];
        foreach ($activeTokens as $t) {
            $clauses[] = "(i.name LIKE ? OR i.code LIKE ? OR p.name LIKE ? OR p.program_code LIKE ?)";
            $term = '%' . $t . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        $sql = "SELECT ar.*, i.name as institution_name, i.code as institution_code, p.name as program_name, p.program_code
                FROM admission_records ar
                JOIN programs p ON p.id = ar.program_id
                JOIN institutions i ON i.id = p.institution_id
                WHERE (" . implode(' OR ', $clauses) . ")
                ORDER BY ar.admission_year DESC, ar.cutoff_score DESC
                LIMIT " . (int)$limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $ar) {
            $year = (int)$ar['admission_year'];
            $isHistorical = ($year < 2025);
            $results[] = [
                'id' => 'admission-' . $ar['id'],
                'type' => 'admission',
                'title' => 'Điểm chuẩn ' . $ar['institution_name'] . ' — ' . $ar['program_name'] . ' (' . $year . ')',
                'source' => $ar['source_url'] ?: 'Đề án tuyển sinh ' . $ar['institution_name'],
                'year' => (string)$year,
                'status' => $isHistorical ? 'historical_official' : 'current_official',
                'summary' => 'Năm ' . $year . ' | Phương thức: ' . $ar['method'] . ' | Tổ hợp: ' . $ar['combination'] . ' | Điểm trúng tuyển: ' . $ar['cutoff_score'] . '/' . $ar['score_scale'] . ($isHistorical ? ' (Lưu ý: Điểm năm cũ mang tính tham khảo)' : ''),
                'url' => 'index.php?page=university-explorer'
            ];
            if (count($results) >= $limit) return $results;
        }
    }

    // 2. Search tuition bands if tuition query or if space remains
    if ($isTuitionQuery || count($results) < $limit) {
        $clauses = [];
        $params = [];
        foreach ($activeTokens as $t) {
            $clauses[] = "(i.name LIKE ? OR i.code LIKE ? OR tb.program_group LIKE ?)";
            $term = '%' . $t . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        $sql = "SELECT tb.*, i.name as institution_name, i.code as institution_code
                FROM institution_tuition_bands tb
                JOIN institutions i ON i.id = tb.institution_id
                WHERE (" . implode(' OR ', $clauses) . ")
                ORDER BY tb.academic_year DESC
                LIMIT " . (int)($limit - count($results));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $tb) {
            $results[] = [
                'id' => 'tuition-' . $tb['id'],
                'type' => 'tuition',
                'title' => 'Học phí ' . $tb['institution_name'] . ' (' . $tb['program_group'] . ')',
                'source' => $tb['source_title'] ?: 'Thông báo học phí chính thức ' . $tb['institution_name'],
                'year' => (string)$tb['academic_year'],
                'status' => 'verified',
                'summary' => 'Năm học ' . $tb['academic_year'] . ': ' . number_format((int)$tb['minimum_vnd']) . ' - ' . number_format((int)$tb['maximum_vnd']) . ' VNĐ / ' . $tb['charge_basis'] . ($tb['note'] ? ' (' . $tb['note'] . ')' : '') . ' [Dữ liệu đã xác minh]',
                'url' => 'index.php?page=university-explorer'
            ];
            if (count($results) >= $limit) return $results;
        }
    }

    return $results;
}

/**
 * Search published knowledge articles.
 */
function may_search_articles(string $query, int $limit = 2, ?PDO $pdo = null): array {
    $pdo = $pdo ?? db();
    $tokens = may_tokenize_query($query);
    if (empty($tokens)) return [];

    $clauses = [];
    $params = [];
    foreach ($tokens as $t) {
        $clauses[] = "(title LIKE ? OR summary LIKE ? OR content LIKE ?)";
        $term = '%' . $t . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $sql = "SELECT id, title, slug, summary, source_name, created_at
            FROM knowledge_articles
            WHERE status = 'published' AND (" . implode(' OR ', $clauses) . ")
            LIMIT " . (int)$limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = [];
    foreach ($stmt->fetchAll() as $r) {
        $results[] = [
            'id' => 'article-' . $r['id'],
            'type' => 'article',
            'title' => $r['title'],
            'source' => $r['source_name'] ?: 'JobSimu Knowledge Hub',
            'year' => date('Y', strtotime($r['created_at'])),
            'status' => 'published',
            'summary' => $r['summary'],
            'url' => 'index.php?page=knowledge-hub'
        ];
    }
    return $results;
}

/**
 * Search career scenario discussions from scenarios.json.
 */
function may_search_scenarios(string $query, int $limit = 2): array {
    $tokens = may_tokenize_query($query);
    if (empty($tokens)) return [];

    $catalog = career_conversation_catalog();
    $results = [];

    foreach ($catalog as $sc) {
        $text = mb_strtolower($sc['career'] . ' ' . $sc['title'] . ' ' . $sc['scene'] . ' ' . implode(' ', $sc['discussion']));
        $matches = 0;
        foreach ($tokens as $t) {
            if (str_contains($text, $t)) $matches++;
        }
        if ($matches > 0) {
            $results[] = [
                'score' => $matches,
                'data' => [
                    'id' => 'scenario-' . $sc['id'],
                    'type' => 'scenario',
                    'title' => 'Tình huống nghề ' . $sc['career'] . ': ' . $sc['title'],
                    'source' => ($sc['source']['title'] ?? 'O*NET') . ' (Nhiệm vụ tham khảo)',
                    'year' => '2026',
                    'status' => 'hypothetical',
                    'summary' => $sc['scene'] . ' [Tình huống giả định thảo luận tư duy nghề nghiệp]',
                    'url' => 'index.php?page=ai-roadmap#career-conversations'
                ]
            ];
        }
    }

    usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
    $out = [];
    foreach (array_slice($results, 0, $limit) as $item) {
        $out[] = $item['data'];
    }
    return $out;
}

/**
 * Unified Grounded Retrieval Engine.
 * Gathers relevant evidence items across all catalogs according to query and page context.
 */
function may_retrieve_evidence(string $query, array $context = [], ?PDO $pdo = null): array {
    $pdo = $pdo ?? db();
    $items = [];
    $lower = mb_strtolower($query);

    // Intent detection
    $isAdmissionQuery = str_contains($lower, 'điểm chuẩn') || str_contains($lower, 'học phí') || str_contains($lower, 'trường') || str_contains($lower, 'tuyển sinh') || str_contains($lower, 'xét tuyển') || str_contains($lower, 'tổ hợp');
    $isMajorQuery = str_contains($lower, 'ngành') || str_contains($lower, 'khoa') || str_contains($lower, 'mã ngành');
    $isScenarioQuery = str_contains($lower, 'tình huống') || str_contains($lower, 'giả định') || str_contains($lower, 'xử lý') || str_contains($lower, 'người trong nghề');

    // Contextual boost based on active page
    $pageId = $context['page_id'] ?? '';
    $entityId = $context['entity_id'] ?? '';

    if ($pageId === 'career' && is_numeric($entityId) && (int)$entityId > 0) {
        $stmt = $pdo->prepare("SELECT id, title, slug, icon, short_description, skills, salary_range, education_path FROM careers WHERE id = ? AND status = 'published'");
        $stmt->execute([(int)$entityId]);
        if ($row = $stmt->fetch()) {
            $items[] = [
                'id' => 'career-' . $row['id'],
                'type' => 'career',
                'title' => ($row['icon'] ? $row['icon'] . ' ' : '💼 ') . $row['title'] . ' (Trang bạn đang xem)',
                'source' => 'Thư viện nghề JobSimu',
                'year' => null,
                'status' => 'published',
                'summary' => $row['short_description'] . ' | Kỹ năng: ' . $row['skills'] . ' | Thu nhập tham khảo: ' . $row['salary_range'] . ' | Lộ trình: ' . $row['education_path'],
                'url' => 'index.php?page=career&id=' . (int)$row['id']
            ];
        }
    } elseif ($pageId === 'majors' && $entityId !== '') {
        $stmt = $pdo->prepare("SELECT id, code, name, description FROM majors WHERE code = ? OR id = ?");
        $stmt->execute([$entityId, (int)$entityId]);
        if ($row = $stmt->fetch()) {
            $items[] = [
                'id' => 'major-' . ($row['code'] ?: $row['id']),
                'type' => 'major',
                'title' => 'Ngành ' . $row['name'] . ' (Trang bạn đang xem)',
                'source' => 'Danh mục ngành đào tạo JobSimu',
                'year' => '2024-2025',
                'status' => 'verified',
                'summary' => $row['description'],
                'url' => 'index.php?page=majors&search=' . urlencode($row['code'] ?: $row['name'])
            ];
        }
    }

    // Retrieve according to intent priority
    if ($isAdmissionQuery) {
        $admItems = may_search_admissions($query, 4, $pdo);
        $items = array_merge($items, $admItems);
    }

    if ($isMajorQuery || count($items) < 2) {
        $majorItems = may_search_majors($query, 3, $pdo);
        $items = array_merge($items, $majorItems);
    }

    if (!$isAdmissionQuery || count($items) < 3) {
        $careerItems = may_search_careers($query, 3, $pdo);
        $items = array_merge($items, $careerItems);
    }

    if ($isScenarioQuery) {
        $scenarioItems = may_search_scenarios($query, 2);
        $items = array_merge($items, $scenarioItems);
    }

    if (count($items) < 2) {
        $articleItems = may_search_articles($query, 2, $pdo);
        $items = array_merge($items, $articleItems);
    }

    // Deduplicate by ID
    $deduped = [];
    foreach ($items as $it) {
        if (!isset($deduped[$it['id']])) {
            $deduped[$it['id']] = $it;
        }
        if (count($deduped) >= 5) break;
    }
    $finalItems = array_values($deduped);

    // Missing data detection for admissions queries
    $missingDataNotice = '';
    if ($isAdmissionQuery) {
        $hasAdmissionItem = false;
        foreach ($finalItems as $fi) {
            if ($fi['type'] === 'admission' || $fi['type'] === 'tuition') {
                $hasAdmissionItem = true;
                break;
            }
        }
        if (!$hasAdmissionItem) {
            $missingDataNotice = 'Hiện tại hệ thống JobSimu chưa có dữ liệu điểm chuẩn hoặc học phí chính thức cho truy vấn này. Vui lòng nêu rõ tên trường, ngành hoặc tham khảo trực tiếp đề án tuyển sinh chính thức của trường.';
        }
    }

    return [
        'items' => $finalItems,
        'missing_data_notice' => $missingDataNotice,
        'intent' => $isAdmissionQuery ? 'admission' : ($isMajorQuery ? 'major' : ($isScenarioQuery ? 'scenario' : 'general'))
    ];
}
