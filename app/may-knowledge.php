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
 * Search training majors from `training_majors` (358 HNV majors) and `majors`.
 */
function may_search_majors(string $query, int $limit = 4, ?PDO $pdo = null): array {
    $pdo = $pdo ?? db();
    $tokens = may_tokenize_query($query);
    if (empty($tokens)) return [];

    $results = [];
    $lower = mb_strtolower(trim($query));

    // Detect general catalog inquiry
    $isGeneral = in_array($lower, [
        'ngành đào tạo', 'ngành', 'các ngành', 'danh sách ngành', 'tìm ngành',
        'có những ngành nào', 'các ngành học', 'những ngành nào', 'bao nhiêu ngành',
        'danh mục ngành', 'tất cả ngành', 'ngành học', 'học ngành gì'
    ], true) || preg_match('/(có những ngành nào|bao nhiêu ngành|danh sách ngành|các ngành đào tạo|danh mục ngành|tổng hợp ngành)/iu', $query);

    if ($isGeneral) {
        $results[] = [
            'id' => 'majors-catalog-overview',
            'type' => 'major',
            'title' => 'Tổng quan Danh mục 358 Ngành đào tạo JobSimu',
            'source' => 'Danh mục ngành đào tạo JobSimu (HNV)',
            'year' => '2024-2025',
            'status' => 'verified',
            'summary' => 'Hệ thống JobSimu hiện có tổng cộng 358 ngành đào tạo đại học/cao đẳng thuộc 23 lĩnh vực (như Máy tính & CNTT, Kinh doanh & Quản lý, Pháp luật, Kỹ thuật, Khoa học sức khỏe, Sư phạm...) và 76 nhóm ngành, kết nối với 248 trường đại học trên cả nước và hơn 2.700 dữ liệu tuyển sinh liên kết. Học sinh có thể tra cứu toàn bộ danh mục tại mục [action:explore_majors].',
            'url' => 'index.php?page=majors',
            'major_code' => null
        ];
    }

    // 1. Search in training_majors (358 HNV training majors)
    try {
        $rawTerm = '%' . trim($query) . '%';
        $clauses = [];
        $params = [];
        foreach ($tokens as $t) {
            $clauses[] = "(tm.name LIKE ? OR COALESCE(tm.source_major_code_candidate, '') LIKE ? OR COALESCE(tm.official_major_code, '') LIKE ? OR f.name LIKE ? OR s.name LIKE ?)";
            $term = '%' . $t . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql = "SELECT tm.id, tm.name, tm.official_major_code, tm.source_major_code_candidate,
                       tm.listed_school_count, tm.editorial_description,
                       f.name AS field_name, s.name AS subgroup_name,
                       CASE
                           WHEN tm.name = ? THEN 100
                           WHEN tm.name LIKE ? THEN 80
                           WHEN tm.source_major_code_candidate = ? OR tm.official_major_code = ? THEN 95
                           ELSE 20
                       END AS match_score
                FROM training_majors tm
                LEFT JOIN major_fields f ON f.id = tm.field_id
                LEFT JOIN major_subgroups s ON s.id = tm.subgroup_id
                WHERE (" . implode(' OR ', $clauses) . ")
                ORDER BY match_score DESC, tm.listed_school_count DESC
                LIMIT " . (int)$limit;

        $stmt = $pdo->prepare($sql);
        $execParams = array_merge([trim($query), $rawTerm, trim($query), trim($query)], $params);
        $stmt->execute($execParams);
        $tmRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tmRows as $r) {
            $code = $r['official_major_code'] ?: ($r['source_major_code_candidate'] ?: '');
            $summary = 'Lĩnh vực: ' . ($r['field_name'] ?: 'Chưa phân loại') . ' · Nhóm ngành: ' . ($r['subgroup_name'] ?: 'Đại học');
            if (!empty($r['listed_school_count'])) {
                $summary .= ' · Có ' . $r['listed_school_count'] . ' trường đại học/cao đẳng đào tạo';
            }
            if (!empty($r['editorial_description'])) {
                $summary .= ' | ' . $r['editorial_description'];
            }
            $results[] = [
                'id' => 'tm-' . $r['id'],
                'type' => 'major',
                'title' => 'Ngành ' . $r['name'] . ($code ? ' (Mã: ' . $code . ')' : ''),
                'source' => 'Danh mục ngành đào tạo JobSimu (HNV)',
                'year' => '2024-2025',
                'status' => 'verified',
                'summary' => $summary,
                'url' => 'index.php?page=majors&id=' . rawurlencode($r['id']),
                'major_code' => $code
            ];
            if (count($results) >= $limit) return $results;
        }
    } catch (Throwable $e) {
        // Fallback gracefully if training_majors table is not available
    }

    // 2. Also search legacy majors table if space remains
    if (count($results) < $limit) {
        try {
            $mClauses = [];
            $mParams = [];
            foreach ($tokens as $t) {
                $mClauses[] = "(m.name LIKE ? OR m.code LIKE ? OR m.description LIKE ?)";
                $mTerm = '%' . $t . '%';
                $mParams[] = $mTerm;
                $mParams[] = $mTerm;
                $mParams[] = $mTerm;
            }
            $mSql = "SELECT m.id, m.code, m.name, m.description,
                            GROUP_CONCAT(DISTINCT c.title SEPARATOR ', ') AS related_careers
                     FROM majors m
                     LEFT JOIN career_major_paths cmp ON cmp.major_id = m.id
                     LEFT JOIN careers c ON c.id = cmp.career_id AND c.status = 'published'
                     WHERE (" . implode(' OR ', $mClauses) . ")
                     GROUP BY m.id
                     LIMIT " . (int)($limit - count($results));
            $mStmt = $pdo->prepare($mSql);
            $mStmt->execute($mParams);
            foreach ($mStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $code = $r['code'] ?: '';
                $alreadyAdded = false;
                foreach ($results as $ex) {
                    if ((!empty($code) && $ex['major_code'] === $code) || str_contains($ex['title'], $r['name'])) {
                        $alreadyAdded = true;
                        break;
                    }
                }
                if ($alreadyAdded) continue;

                $summary = $r['description'] ?: 'Ngành đào tạo chuyên môn cung cấp nền tảng kiến thức nghề nghiệp.';
                if (!empty($r['related_careers'])) {
                    $summary .= ' (Hướng nghề nghiệp liên quan: ' . $r['related_careers'] . ')';
                }
                $results[] = [
                    'id' => 'major-' . ($r['code'] ?: $r['id']),
                    'type' => 'major',
                    'title' => 'Ngành ' . $r['name'] . ($code ? ' (Mã: ' . $code . ')' : ''),
                    'source' => 'Danh mục ngành đào tạo JobSimu',
                    'year' => '2024-2025',
                    'status' => 'verified',
                    'summary' => $summary,
                    'url' => 'index.php?page=majors&q=' . urlencode($r['code'] ?: $r['name']),
                    'major_code' => $code
                ];
                if (count($results) >= $limit) return $results;
            }
        } catch (Throwable $e) {
            // Ignore
        }
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
        $stmt = $pdo->prepare("SELECT tm.id, tm.name, tm.official_major_code, tm.source_major_code_candidate, tm.listed_school_count, tm.editorial_description, f.name as field_name, s.name as subgroup_name
                FROM training_majors tm
                LEFT JOIN major_fields f ON f.id = tm.field_id
                LEFT JOIN major_subgroups s ON s.id = tm.subgroup_id
                WHERE tm.id = ? OR tm.official_major_code = ? OR tm.source_major_code_candidate = ?");
        $stmt->execute([$entityId, $entityId, $entityId]);
        if ($row = $stmt->fetch()) {
            $code = $row['official_major_code'] ?: ($row['source_major_code_candidate'] ?: '');
            $summary = 'Lĩnh vực: ' . ($row['field_name'] ?: 'Chưa phân loại') . ' · Nhóm ngành: ' . ($row['subgroup_name'] ?: 'Đại học');
            if (!empty($row['listed_school_count'])) {
                $summary .= ' · Có ' . $row['listed_school_count'] . ' trường đại học/cao đẳng đào tạo';
            }
            if (!empty($row['editorial_description'])) {
                $summary .= ' | ' . $row['editorial_description'];
            }
            $items[] = [
                'id' => 'tm-' . $row['id'],
                'type' => 'major',
                'title' => 'Ngành ' . $row['name'] . ($code ? ' (Mã: ' . $code . ')' : '') . ' (Trang bạn đang xem)',
                'source' => 'Danh mục ngành đào tạo JobSimu (HNV)',
                'year' => '2024-2025',
                'status' => 'verified',
                'summary' => $summary,
                'url' => 'index.php?page=majors&id=' . rawurlencode($row['id']),
                'major_code' => $code
            ];
        } else {
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
                    'url' => 'index.php?page=majors&q=' . urlencode($row['code'] ?: $row['name'])
                ];
            }
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
