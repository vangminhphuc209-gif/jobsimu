<?php
declare(strict_types=1);

/**
 * Step 2 / 4: Redesigned Public Lookup ("Tra cứu")
 * Three focused entry points: Ngành học, Trường đào tạo, Tổ hợp xét tuyển.
 *
 * Strictly grounded in verified DB records (institutions, majors, programs,
 * admission_records, program_combinations, admission_combinations, careers).
 * - Never restricts university lookup to the 3 high-school signup provinces.
 * - Never fabricates cutoffs, never renders 0 for missing data.
 * - Only affirms program-combination acceptance when verified for the same year & method.
 */

function lookup_score_type_label(string $scoreType): string
{
    $map = [
        'cutoff' => 'Điểm trúng tuyển',
        'floor' => 'Ngưỡng nhận hồ sơ',
        'other' => 'Điểm tham khảo',
    ];
    return $map[$scoreType] ?? 'Điểm trúng tuyển';
}

function lookup_format_score(?string $score, ?string $scale, string $scoreType, int $year, string $method): string
{
    if ($score === null || $score === '' || !is_numeric($score) || (float)$score <= 0) {
        return 'Chưa có dữ liệu xác minh';
    }
    $val = rtrim(rtrim(number_format((float)$score, 2, '.', ''), '0'), '.');
    $scaleStr = trim((string)$scale) !== '' ? trim((string)$scale) : 'chưa ghi thang điểm';
    $typeLabel = lookup_score_type_label($scoreType);
    return "{$typeLabel} {$year} · {$method} · {$val}/{$scaleStr}";
}

function lookup_get_combinations(?PDO $pdo = null): array
{
    $conn = $pdo ?? db();
    $rows = $conn->query('SELECT code, subjects_text, subjects_json, category FROM admission_combinations ORDER BY sort_order ASC, code ASC')->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static function (array $r): array {
        return [
            'code' => (string)$r['code'],
            'subjects_text' => (string)$r['subjects_text'],
            'subjects' => json_decode((string)$r['subjects_json'], true) ?: [],
            'category' => (string)$r['category'],
        ];
    }, $rows);
}

function lookup_get_filter_metadata(?PDO $pdo = null): array
{
    $conn = $pdo ?? db();
    $groups = $conn->query('SELECT id, name FROM career_groups ORDER BY sort_order ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC);
    $provinces = $conn->query("SELECT DISTINCT province FROM institutions WHERE province IS NOT NULL AND TRIM(province) <> '' ORDER BY province ASC")->fetchAll(PDO::FETCH_COLUMN);
    $majors = $conn->query('SELECT id, code, name FROM majors ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
    $years = $conn->query('SELECT DISTINCT admission_year FROM admission_records ORDER BY admission_year DESC')->fetchAll(PDO::FETCH_COLUMN);
    $methods = $conn->query("SELECT DISTINCT method FROM admission_records WHERE method IS NOT NULL AND TRIM(method) <> '' ORDER BY method ASC")->fetchAll(PDO::FETCH_COLUMN);
    $combinations = lookup_get_combinations($conn);

    return [
        'groups' => array_map(static fn($r) => ['id' => (int)$r['id'], 'name' => (string)$r['name']], $groups),
        'provinces' => array_values(array_map('strval', $provinces)),
        'majors' => array_map(static fn($r) => ['id' => (int)$r['id'], 'code' => (string)$r['code'], 'name' => (string)$r['name']], $majors),
        'years' => array_values(array_map('intval', $years)),
        'methods' => array_values(array_map('strval', $methods)),
        'combinations' => $combinations,
    ];
}

/**
 * Query Tab A: Majors lookup with accent-insensitive Vietnamese search, group, combination, year.
 */
function lookup_query_majors(array $params, ?PDO $pdo = null): array
{
    $conn = $pdo ?? db();
    $q = trim((string)($params['q'] ?? ''));
    $qNorm = journey_strip_accents($q);
    $groupId = max(0, (int)($params['group_id'] ?? 0));
    $comb = strtoupper(trim((string)($params['combination'] ?? '')));
    $year = max(0, (int)($params['year'] ?? 0));
    $onlyVerified = !empty($params['only_verified']);
    $page = max(1, (int)($params['p'] ?? 1));
    $perPage = max(1, min(30, (int)($params['per_page'] ?? 12)));

    $majors = $conn->query('SELECT m.id, m.code, m.name, m.description FROM majors m ORDER BY m.name ASC')->fetchAll(PDO::FETCH_ASSOC);

    // Preload linked careers & groups
    $careerLinks = $conn->query(
        'SELECT cmp.major_id, c.id AS career_id, c.title, c.slug, c.skills, c.career_group_id, cg.name AS group_name, c.simulation_enabled
         FROM career_major_paths cmp
         JOIN careers c ON c.id = cmp.career_id
         LEFT JOIN career_groups cg ON cg.id = c.career_group_id'
    )->fetchAll(PDO::FETCH_ASSOC);
    $careersByMajor = [];
    foreach ($careerLinks as $cl) {
        $careersByMajor[(int)$cl['major_id']][] = $cl;
    }

    // Preload programs & verified records & combinations per major
    $progRows = $conn->query(
        'SELECT p.id, p.major_id, p.name, p.program_code, i.id AS institution_id, i.name AS institution_name, i.province
         FROM programs p
         JOIN institutions i ON i.id = p.institution_id'
    )->fetchAll(PDO::FETCH_ASSOC);
    $progsByMajor = [];
    $progToMajor = [];
    foreach ($progRows as $pr) {
        $mid = (int)$pr['major_id'];
        $progsByMajor[$mid][] = $pr;
        $progToMajor[(int)$pr['id']] = $mid;
    }

    $pcRows = $conn->query(
        "SELECT pc.program_id, pc.admission_year, pc.method, pc.combination_code, ac.subjects_text
         FROM program_combinations pc
         JOIN admission_combinations ac ON ac.code = pc.combination_code
         WHERE pc.verification_status = 'verified'"
    )->fetchAll(PDO::FETCH_ASSOC);

    $combsByMajor = [];
    foreach ($pcRows as $pc) {
        $pid = (int)$pc['program_id'];
        if (!isset($progToMajor[$pid])) continue;
        if ($year > 0 && (int)$pc['admission_year'] !== $year) continue;
        $mid = $progToMajor[$pid];
        $combsByMajor[$mid][$pc['combination_code']] = [
            'code' => (string)$pc['combination_code'],
            'subjects_text' => (string)$pc['subjects_text'],
        ];
    }

    $arRows = $conn->query('SELECT program_id, admission_year, cutoff_score FROM admission_records')->fetchAll(PDO::FETCH_ASSOC);
    $recordCountByMajor = [];
    $yearsByMajor = [];
    foreach ($arRows as $ar) {
        $pid = (int)$ar['program_id'];
        if (!isset($progToMajor[$pid])) continue;
        $mid = $progToMajor[$pid];
        $yr = (int)$ar['admission_year'];
        $yearsByMajor[$mid][$yr] = true;
        if ($year === 0 || $yr === $year) {
            if ($ar['cutoff_score'] !== null) {
                $recordCountByMajor[$mid] = ($recordCountByMajor[$mid] ?? 0) + 1;
            }
        }
    }

    $filtered = [];
    foreach ($majors as $m) {
        $mid = (int)$m['id'];
        $linked = $careersByMajor[$mid] ?? [];
        $groupNames = [];
        $groupIds = [];
        $skillsArr = [];
        foreach ($linked as $lk) {
            if (!empty($lk['group_name'])) $groupNames[$lk['group_name']] = true;
            if (!empty($lk['career_group_id'])) $groupIds[(int)$lk['career_group_id']] = true;
            if (!empty($lk['skills'])) {
                foreach (array_slice(array_filter(array_map('trim', explode(',', (string)$lk['skills']))), 0, 3) as $sk) {
                    $skillsArr[$sk] = true;
                }
            }
        }

        if ($groupId > 0 && !isset($groupIds[$groupId])) {
            continue;
        }

        $majorCombs = array_values($combsByMajor[$mid] ?? []);
        if ($comb !== '') {
            $combCodes = array_column($majorCombs, 'code');
            if (!in_array($comb, $combCodes, true)) {
                continue;
            }
        }

        $verifiedRecCount = $recordCountByMajor[$mid] ?? 0;
        if ($onlyVerified && $verifiedRecCount === 0) {
            continue;
        }

        if ($qNorm !== '') {
            $haystack = journey_strip_accents(
                $m['name'] . ' ' . $m['code'] . ' ' . ($m['description'] ?? '') . ' '
                . implode(' ', array_keys($groupNames)) . ' '
                . implode(' ', array_keys($skillsArr))
            );
            if (strpos($haystack, $qNorm) === false) {
                continue;
            }
        }

        $progs = $progsByMajor[$mid] ?? [];
        $yearsList = array_keys($yearsByMajor[$mid] ?? []);
        rsort($yearsList);

        $filtered[] = [
            'id' => $mid,
            'code' => (string)$m['code'],
            'name' => (string)$m['name'],
            'description' => (string)($m['description'] ?? ''),
            'group_name' => !empty($groupNames) ? implode(', ', array_keys($groupNames)) : 'Đa lĩnh vực',
            'skills' => array_slice(array_keys($skillsArr), 0, 4),
            'combinations' => $majorCombs,
            'program_count' => count($progs),
            'verified_record_count' => $verifiedRecCount,
            'years' => $yearsList,
        ];
    }

    $total = count($filtered);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $items = array_slice($filtered, ($page - 1) * $perPage, $perPage);

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
    ];
}

/**
 * Query Tab B: Schools / Programs admission lookup (nationwide universities/colleges).
 * Never restricted to the 3 high-school signup provinces.
 */
function lookup_query_schools(array $params, ?PDO $pdo = null): array
{
    $conn = $pdo ?? db();
    $q = trim((string)($params['q'] ?? ''));
    $qNorm = journey_strip_accents($q);
    $majorId = max(0, (int)($params['major_id'] ?? 0));
    $province = trim((string)($params['province'] ?? ''));
    $comb = strtoupper(trim((string)($params['combination'] ?? '')));
    $year = max(0, (int)($params['year'] ?? 0));
    $method = trim((string)($params['method'] ?? ''));
    $page = max(1, (int)($params['p'] ?? 1));
    $perPage = max(1, min(30, (int)($params['per_page'] ?? 12)));

    // Fetch all programs with institution & major
    $sql = 'SELECT p.id AS program_id, p.name AS program_name, p.program_code, p.degree_level, p.official_url,
                   i.id AS institution_id, i.code AS institution_code, i.name AS institution_name, i.province AS institution_province, i.website AS institution_website,
                   m.id AS major_id, m.code AS major_code, m.name AS major_name
            FROM programs p
            JOIN institutions i ON i.id = p.institution_id
            JOIN majors m ON m.id = p.major_id
            ORDER BY i.name ASC, p.name ASC';
    $programs = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // Fetch admission records & program combinations
    $arRows = $conn->query('SELECT * FROM admission_records ORDER BY admission_year DESC, method ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
    $arByProg = [];
    foreach ($arRows as $ar) {
        $arByProg[(int)$ar['program_id']][] = $ar;
    }

    $pcRows = $conn->query(
        "SELECT pc.program_id, pc.admission_year, pc.method, pc.combination_code, ac.subjects_text, pc.conditions_note, pc.source_url, pc.source_title, pc.publisher, pc.verified_on
         FROM program_combinations pc
         JOIN admission_combinations ac ON ac.code = pc.combination_code
         WHERE pc.verification_status = 'verified'
         ORDER BY pc.combination_code ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
    $pcByProgYearMethod = [];
    foreach ($pcRows as $pc) {
        $k = (int)$pc['program_id'] . '|' . (int)$pc['admission_year'] . '|' . (string)$pc['method'];
        $pcByProgYearMethod[$k][] = $pc;
    }

    $rows = [];
    foreach ($programs as $p) {
        if ($majorId > 0 && (int)$p['major_id'] !== $majorId) continue;
        if ($province !== '' && strcasecmp((string)$p['institution_province'], $province) !== 0) continue;

        $pid = (int)$p['program_id'];
        $records = $arByProg[$pid] ?? [];

        if (empty($records)) {
            if ($year > 0 || $method !== '' || $comb !== '') continue;
            $rows[] = [
                'row_id' => "prog-{$pid}-nodata",
                'institution_id' => (int)$p['institution_id'],
                'institution_code' => (string)$p['institution_code'],
                'institution_name' => (string)$p['institution_name'],
                'campus' => (string)($p['institution_province'] ?: 'Chưa ghi cơ sở'),
                'province' => (string)($p['institution_province'] ?: 'Chưa rõ'),
                'program_id' => $pid,
                'program_code' => (string)($p['program_code'] ?? ''),
                'program_name' => (string)$p['program_name'],
                'major_id' => (int)$p['major_id'],
                'major_code' => (string)$p['major_code'],
                'major_name' => (string)$p['major_name'],
                'admission_year' => null,
                'method' => 'Chưa có dữ liệu xác minh',
                'round_name' => '',
                'combinations' => [],
                'score_type' => 'cutoff',
                'score_display' => 'Chưa có dữ liệu xác minh',
                'cutoff_score' => null,
                'score_scale' => null,
                'conditions_note' => '',
                'source_url' => (string)($p['official_url'] ?: $p['institution_website'] ?: ''),
                'source_title' => 'Trang thông tin chương trình',
                'publisher' => (string)$p['institution_name'],
                'verified_on' => '',
                'verification_status' => 'pending',
            ];
            continue;
        }

        foreach ($records as $rec) {
            $recYear = (int)$rec['admission_year'];
            $recMethod = (string)$rec['method'];
            if ($year > 0 && $recYear !== $year) continue;
            if ($method !== '' && $recMethod !== $method) continue;

            $k = "{$pid}|{$recYear}|{$recMethod}";
            $matchedCombs = $pcByProgYearMethod[$k] ?? [];
            $combList = [];
            foreach ($matchedCombs as $mc) {
                $combList[$mc['combination_code']] = [
                    'code' => (string)$mc['combination_code'],
                    'subjects_text' => (string)$mc['subjects_text'],
                ];
            }
            if (!empty($rec['combination'])) {
                foreach (preg_split('/[,;\/\s]+/', strtoupper(trim((string)$rec['combination']))) as $cCode) {
                    if ($cCode !== '' && !isset($combList[$cCode])) {
                        $combList[$cCode] = ['code' => $cCode, 'subjects_text' => ''];
                    }
                }
            }

            if ($comb !== '' && !isset($combList[$comb])) {
                continue;
            }

            $scoreType = (string)($rec['score_type'] ?? 'cutoff');
            $scoreDisplay = lookup_format_score(
                $rec['cutoff_score'] !== null ? (string)$rec['cutoff_score'] : null,
                $rec['score_scale'] !== null ? (string)$rec['score_scale'] : null,
                $scoreType,
                $recYear,
                $recMethod
            );

            $rows[] = [
                'row_id' => 'ar-' . (int)$rec['id'],
                'institution_id' => (int)$p['institution_id'],
                'institution_code' => (string)$p['institution_code'],
                'institution_name' => (string)$p['institution_name'],
                'campus' => (string)(!empty($rec['campus']) ? $rec['campus'] : ($p['institution_province'] ?: 'Cơ sở chính')),
                'province' => (string)($p['institution_province'] ?: 'Chưa rõ'),
                'program_id' => $pid,
                'program_code' => (string)($p['program_code'] ?? ''),
                'program_name' => (string)$p['program_name'],
                'major_id' => (int)$p['major_id'],
                'major_code' => (string)$p['major_code'],
                'major_name' => (string)$p['major_name'],
                'admission_year' => $recYear,
                'method' => $recMethod,
                'round_name' => (string)($rec['round_name'] ?? ''),
                'combinations' => array_values($combList),
                'score_type' => $scoreType,
                'score_display' => $scoreDisplay,
                'cutoff_score' => $rec['cutoff_score'] !== null ? (float)$rec['cutoff_score'] : null,
                'score_scale' => (string)($rec['score_scale'] ?? ''),
                'conditions_note' => (string)($rec['conditions_note'] ?? ''),
                'source_url' => (string)$rec['source_url'],
                'source_title' => (string)(!empty($rec['source_title']) ? $rec['source_title'] : ('Thông báo tuyển sinh ' . $p['institution_name'] . ' ' . $recYear)),
                'publisher' => (string)(!empty($rec['publisher']) ? $rec['publisher'] : $p['institution_name']),
                'verified_on' => !empty($rec['verified_at']) ? substr((string)$rec['verified_at'], 0, 10) : '',
                'verification_status' => (preg_match('#^https?://#i', trim((string)($rec['source_url'] ?? ''))) === 1 && ($rec['verification_status'] ?? 'verified') === 'verified') ? 'verified' : 'unverified',
            ];
        }
    }

    // Also include verified institutions that have no programs yet if no strict major/comb/year/method filter is active
    if ($majorId === 0 && $comb === '' && $year === 0 && $method === '') {
        $progInstIds = [];
        foreach ($programs as $p) $progInstIds[(int)$p['institution_id']] = true;
        $allInsts = $conn->query('SELECT * FROM institutions ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($allInsts as $inst) {
            if (isset($progInstIds[(int)$inst['id']])) continue;
            if ($province !== '' && strcasecmp((string)$inst['province'], $province) !== 0) continue;
            $rows[] = [
                'row_id' => 'inst-' . (int)$inst['id'],
                'institution_id' => (int)$inst['id'],
                'institution_code' => (string)$inst['code'],
                'institution_name' => (string)$inst['name'],
                'campus' => (string)($inst['province'] ?: 'Chưa ghi cơ sở'),
                'province' => (string)($inst['province'] ?: 'Chưa rõ'),
                'program_id' => 0,
                'program_code' => '',
                'program_name' => 'Chưa có dữ liệu chương trình chi tiết',
                'major_id' => 0,
                'major_code' => '',
                'major_name' => 'Đang cập nhật danh mục chương trình',
                'admission_year' => null,
                'method' => 'Chưa có dữ liệu xác minh',
                'round_name' => '',
                'combinations' => [],
                'score_type' => 'cutoff',
                'score_display' => 'Chưa có dữ liệu xác minh',
                'cutoff_score' => null,
                'score_scale' => null,
                'conditions_note' => 'Trường đã có trong danh mục cơ sở đào tạo; chưa có bản ghi điểm chuẩn xác minh cho từng ngành.',
                'source_url' => (string)($inst['website'] ?? ''),
                'source_title' => 'Cổng thông tin chính thức của trường',
                'publisher' => (string)$inst['name'],
                'verified_on' => '',
                'verification_status' => 'pending',
            ];
        }
    }

    if ($qNorm !== '') {
        $rows = array_values(array_filter($rows, static function (array $r) use ($qNorm): bool {
            $hay = journey_strip_accents(
                $r['institution_name'] . ' ' . $r['institution_code'] . ' ' . $r['province'] . ' '
                . $r['program_name'] . ' ' . $r['program_code'] . ' ' . $r['major_name'] . ' ' . $r['major_code']
            );
            return strpos($hay, $qNorm) !== false;
        }));
    }

    $total = count($rows);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $items = array_slice($rows, ($page - 1) * $perPage, $perPage);

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
    ];
}

/**
 * Query Tab C: Combinations lookup ("Với tổ hợp này, mình có thể tìm hiểu ngành nào và trường nào?").
 * Strictly matches verified (program_id, admission_year, method, combination_code) entries.
 * Supports match_mode = 'any' (default) or 'all' (must accept ALL selected combinations in the same year & method).
 */
function lookup_query_combinations(array $params, ?PDO $pdo = null): array
{
    $conn = $pdo ?? db();
    $rawCodes = $params['codes'] ?? [];
    if (is_string($rawCodes)) {
        $rawCodes = array_filter(array_map('trim', explode(',', $rawCodes)));
    }
    $selectedCodes = [];
    foreach ((array)$rawCodes as $c) {
        $up = strtoupper(trim((string)$c));
        if ($up !== '' && preg_match('/^[A-Z][0-9]{2}$/', $up) && !in_array($up, $selectedCodes, true)) {
            $selectedCodes[] = $up;
        }
    }

    $q = trim((string)($params['q'] ?? ''));
    $qNorm = journey_strip_accents($q);
    $matchMode = (($params['match_mode'] ?? 'any') === 'all') ? 'all' : 'any';
    $year = max(0, (int)($params['year'] ?? 0));

    $allCombinations = lookup_get_combinations($conn);
    $matchedCombCatalog = $allCombinations;
    if ($qNorm !== '') {
        $matchedCombCatalog = array_values(array_filter($allCombinations, static function (array $c) use ($qNorm): bool {
            $hay = journey_strip_accents($c['code'] . ' ' . $c['subjects_text'] . ' ' . $c['category']);
            return strpos($hay, $qNorm) !== false;
        }));
    }

    // Fetch all verified program_combinations joined with program, major, institution
    $pcSql = "SELECT pc.program_id, pc.admission_year, pc.method, pc.combination_code, pc.conditions_note,
                     pc.source_url, pc.source_title, pc.publisher, pc.verified_on,
                     ac.subjects_text,
                     p.name AS program_name, p.program_code,
                     m.id AS major_id, m.code AS major_code, m.name AS major_name,
                     i.id AS institution_id, i.code AS institution_code, i.name AS institution_name, i.province AS institution_province
              FROM program_combinations pc
              JOIN admission_combinations ac ON ac.code = pc.combination_code
              JOIN programs p ON p.id = pc.program_id
              JOIN majors m ON m.id = p.major_id
              JOIN institutions i ON i.id = p.institution_id
              WHERE pc.verification_status = 'verified'
              ORDER BY pc.admission_year DESC, i.name ASC, p.name ASC, pc.combination_code ASC";
    $pcRows = $conn->query($pcSql)->fetchAll(PDO::FETCH_ASSOC);

    // Preload admission_records keyed by (program_id, admission_year, method)
    $arRows = $conn->query('SELECT * FROM admission_records')->fetchAll(PDO::FETCH_ASSOC);
    $arIndex = [];
    foreach ($arRows as $ar) {
        $k = (int)$ar['program_id'] . '|' . (int)$ar['admission_year'] . '|' . (string)$ar['method'];
        $arIndex[$k][] = $ar;
    }

    // Group verified links strictly by (program_id, admission_year, method)
    $grouped = [];
    foreach ($pcRows as $r) {
        if ($year > 0 && (int)$r['admission_year'] !== $year) continue;
        $k = (int)$r['program_id'] . '|' . (int)$r['admission_year'] . '|' . (string)$r['method'];
        if (!isset($grouped[$k])) {
            $grouped[$k] = [
                'program_id' => (int)$r['program_id'],
                'program_code' => (string)($r['program_code'] ?? ''),
                'program_name' => (string)$r['program_name'],
                'major_id' => (int)$r['major_id'],
                'major_code' => (string)$r['major_code'],
                'major_name' => (string)$r['major_name'],
                'institution_id' => (int)$r['institution_id'],
                'institution_code' => (string)$r['institution_code'],
                'institution_name' => (string)$r['institution_name'],
                'province' => (string)($r['institution_province'] ?: 'Chưa rõ'),
                'admission_year' => (int)$r['admission_year'],
                'method' => (string)$r['method'],
                'combinations' => [],
                'conditions_note' => (string)($r['conditions_note'] ?? ''),
                'source_url' => (string)$r['source_url'],
                'source_title' => (string)($r['source_title'] ?? ''),
                'publisher' => (string)($r['publisher'] ?? $r['institution_name']),
                'verified_on' => (string)($r['verified_on'] ?? ''),
                'score_display' => 'Chưa có dữ liệu điểm xác minh',
            ];
            $matchingAr = $arIndex[$k] ?? [];
            if (!empty($matchingAr)) {
                $firstAr = $matchingAr[0];
                $grouped[$k]['score_display'] = lookup_format_score(
                    $firstAr['cutoff_score'] !== null ? (string)$firstAr['cutoff_score'] : null,
                    $firstAr['score_scale'] !== null ? (string)$firstAr['score_scale'] : null,
                    (string)($firstAr['score_type'] ?? 'cutoff'),
                    (int)$firstAr['admission_year'],
                    (string)$firstAr['method']
                );
            }
        }
        $grouped[$k]['combinations'][$r['combination_code']] = [
            'code' => (string)$r['combination_code'],
            'subjects_text' => (string)$r['subjects_text'],
        ];
    }

    $results = [];
    foreach ($grouped as $item) {
        $codesInItem = array_keys($item['combinations']);
        if (!empty($selectedCodes)) {
            if ($matchMode === 'all') {
                $okAll = true;
                foreach ($selectedCodes as $sc) {
                    if (!in_array($sc, $codesInItem, true)) {
                        $okAll = false;
                        break;
                    }
                }
                if (!$okAll) continue;
            } else {
                $hasAny = false;
                foreach ($selectedCodes as $sc) {
                    if (in_array($sc, $codesInItem, true)) {
                        $hasAny = true;
                        break;
                    }
                }
                if (!$hasAny) continue;
            }
        }
        $item['combinations'] = array_values($item['combinations']);
        $results[] = $item;
    }

    return [
        'combinations_catalog' => $matchedCombCatalog,
        'selected_codes' => $selectedCodes,
        'match_mode' => $matchMode,
        'items' => $results,
        'total' => count($results),
    ];
}

function lookup_get_major_detail(int $majorId, ?PDO $pdo = null): ?array
{
    if ($majorId <= 0) return null;
    $conn = $pdo ?? db();
    $stmt = $conn->prepare('SELECT * FROM majors WHERE id = ?');
    $stmt->execute([$majorId]);
    $major = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$major) return null;

    $cStmt = $conn->prepare(
        'SELECT c.id, c.title, c.slug, c.icon, c.short_description, c.skills, c.subjects, c.work_environment, c.education_path, c.simulation_enabled, cg.name AS group_name
         FROM career_major_paths cmp
         JOIN careers c ON c.id = cmp.career_id
         LEFT JOIN career_groups cg ON cg.id = c.career_group_id
         WHERE cmp.major_id = ?'
    );
    $cStmt->execute([$majorId]);
    $careers = $cStmt->fetchAll(PDO::FETCH_ASSOC);

    $pStmt = $conn->prepare(
        'SELECT p.*, i.code AS institution_code, i.name AS institution_name, i.province AS institution_province, i.website AS institution_website
         FROM programs p
         JOIN institutions i ON i.id = p.institution_id
         WHERE p.major_id = ?
         ORDER BY i.name ASC, p.name ASC'
    );
    $pStmt->execute([$majorId]);
    $programs = $pStmt->fetchAll(PDO::FETCH_ASSOC);

    $arStmt = $conn->prepare('SELECT * FROM admission_records WHERE program_id = ? ORDER BY admission_year DESC, method ASC');
    $pcStmt = $conn->prepare(
        "SELECT pc.admission_year, pc.method, pc.combination_code, ac.subjects_text, pc.conditions_note, pc.source_url, pc.verified_on
         FROM program_combinations pc
         JOIN admission_combinations ac ON ac.code = pc.combination_code
         WHERE pc.program_id = ? AND pc.verification_status = 'verified'
         ORDER BY pc.admission_year DESC, pc.combination_code ASC"
    );

    $allCombs = [];
    foreach ($programs as &$prog) {
        $arStmt->execute([(int)$prog['id']]);
        $prog['records'] = $arStmt->fetchAll(PDO::FETCH_ASSOC);
        $pcStmt->execute([(int)$prog['id']]);
        $prog['combinations'] = $pcStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($prog['combinations'] as $pc) {
            $allCombs[$pc['combination_code']] = $pc['subjects_text'];
        }
    }
    unset($prog);

    return [
        'major' => $major,
        'careers' => $careers,
        'programs' => $programs,
        'aggregated_combinations' => $allCombs,
    ];
}

function lookup_handle_api(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $tab = (string)($_GET['tab'] ?? 'majors');
    try {
        if ($tab === 'majors') {
            $data = lookup_query_majors($_GET);
            echo json_encode(['ok' => true, 'tab' => 'majors', 'data' => $data], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($tab === 'schools') {
            $data = lookup_query_schools($_GET);
            echo json_encode(['ok' => true, 'tab' => 'schools', 'data' => $data], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($tab === 'combinations') {
            $data = lookup_query_combinations($_GET);
            echo json_encode(['ok' => true, 'tab' => 'combinations', 'data' => $data], JSON_UNESCAPED_UNICODE);
            return;
        }
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Loại tra cứu không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Không thể tải dữ liệu tra cứu lúc này. Vui lòng thử lại.'], JSON_UNESCAPED_UNICODE);
    }
}

function lookup_render_page(): void
{
    $tab = is_string($_GET['tab'] ?? null) ? trim($_GET['tab']) : '';
    $majorId = max(0, (int)($_GET['major_id'] ?? 0));
    $me = user();
    $basketStorageKey = 'jobsimu_compare_basket_' . ($me ? ('u' . (int)$me['id']) : 'guest');

    // Major detail sub-page
    if ($tab === 'majors' && $majorId > 0) {
        $detail = lookup_get_major_detail($majorId);
        if (!$detail) {
            http_response_code(404);
            $pageTitle = 'Không tìm thấy ngành học · Tra cứu';
            require __DIR__ . '/header.php';
            echo '<div class="container journey-wide py-12"><div class="journey-card text-center"><h1 class="text-2xl font-black">Không tìm thấy ngành học</h1><p class="text-slate-600 mt-2">Ngành học bạn tìm chưa có trong danh mục hiện tại.</p><a class="btn btn-primary mt-4" href="' . e(url('index.php?page=lookup&tab=majors')) . '">← Quay lại Tra cứu ngành</a></div></div>';
            require __DIR__ . '/footer.php';
            return;
        }
        $m = $detail['major'];
        $careers = $detail['careers'];
        $programs = $detail['programs'];
        $aggCombs = $detail['aggregated_combinations'];
        $pageTitle = $m['name'] . ' · Tra cứu ngành học';
        require __DIR__ . '/header.php';
        ?>
        <section class="page-head journey-head">
          <div class="container journey-wide">
            <nav class="breadcrumb mb-3" aria-label="Đường dẫn trang">
              <a href="<?=url('index.php?page=lookup')?>">Tra cứu</a>
              <span class="breadcrumb-sep">/</span>
              <a href="<?=url('index.php?page=lookup&tab=majors')?>">Ngành học</a>
              <span class="breadcrumb-sep">/</span>
              <span aria-current="page"><?=e($m['name'])?></span>
            </nav>
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div>
                <span class="journey-step-pill">Ngành đào tạo · Mã <?=e($m['code'])?></span>
                <h1 class="page-title mt-2"><?=e($m['name'])?></h1>
                <p class="page-subtitle"><?=e($m['description'] ?: 'Chưa có mô tả chi tiết cho ngành này.')?></p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button
                  type="button"
                  class="btn btn-primary"
                  data-add-compare
                  data-compare-id="major:<?=(int)$m['id']?>"
                  data-compare-label="<?=e($m['name'])?>"
                  data-compare-type="major"
                  data-compare-major-id="<?=(int)$m['id']?>"
                >+ Thêm vào so sánh</button>
                <a class="btn btn-outline" href="<?=url('index.php?page=lookup&tab=majors')?>">← Quay lại Tra cứu ngành</a>
              </div>
            </div>
          </div>
        </section>

        <div class="container journey-wide py-8 space-y-6" data-basket-key="<?=e($basketStorageKey)?>">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <section class="journey-card">
              <h2 class="text-base font-bold text-slate-900 mb-2">Học gì trong ngành này?</h2>
              <p class="text-sm text-slate-700 leading-relaxed"><?=e($m['description'] ?: 'Chưa có dữ liệu chương trình khung chi tiết.')?></p>
              <?php if (!empty($aggCombs)): ?>
                <div class="mt-4 pt-3 border-t border-slate-100">
                  <div class="text-xs font-bold text-slate-700 mb-2">Tổ hợp có dữ liệu đối chiếu:</div>
                  <div class="flex flex-wrap gap-1.5">
                    <?php foreach ($aggCombs as $cCode => $cSubj): ?>
                      <span class="lookup-comb-pill"><?=e($cCode)?> · <?=e($cSubj)?></span>
                    <?php endforeach; ?>
                  </div>
                  <p class="text-[11px] text-slate-500 mt-2">Lưu ý: Tổ hợp ở cấp ngành được tổng hợp từ các chương trình có dữ liệu xác minh; không ngụ ý mọi trường đều nhận cùng tổ hợp.</p>
                </div>
              <?php else: ?>
                <p class="text-xs text-slate-500 mt-3">Tổ hợp xét tuyển: Chưa có dữ liệu xác minh ở cấp chương trình.</p>
              <?php endif; ?>
            </section>

            <section class="journey-card">
              <h2 class="text-base font-bold text-slate-900 mb-2">Công việc thường gặp</h2>
              <?php if (empty($careers)): ?>
                <p class="text-sm text-slate-500">Chưa liên kết nghề nghiệp cụ thể trong thư viện.</p>
              <?php else: ?>
                <ul class="space-y-2.5 text-sm">
                  <?php foreach ($careers as $c): ?>
                    <li class="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                      <a class="font-bold text-slate-900 hover:text-brand-600" href="<?=url('index.php?page=career&id='.(int)$c['id'])?>"><?=e(($c['icon'] ?: '💼') . ' ' . $c['title'])?></a>
                      <p class="text-xs text-slate-600 mt-1"><?=e($c['short_description'])?></p>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </section>

            <section class="journey-card">
              <h2 class="text-base font-bold text-slate-900 mb-2">Nên tìm hiểu &amp; trải nghiệm trước</h2>
              <?php
              $simCareers = [];
              foreach ($careers as $c) {
                  $sim = simulation_for_career($c);
                  if ($sim) $simCareers[] = $c;
              }
              ?>
              <?php if (!empty($careers)): ?>
                <p class="text-xs text-slate-600 mb-3">Kỹ năng &amp; môn học liên quan: <strong><?=e($careers[0]['skills'] ?: 'Chưa cập nhật')?></strong></p>
              <?php endif; ?>
              <?php if (!empty($simCareers)): ?>
                <div class="space-y-2">
                  <?php foreach ($simCareers as $sc): ?>
                    <a class="btn btn-outline btn-sm w-full" href="<?=url('index.php?page=simulation&id='.(int)$sc['id'])?>">🎮 Thử mô phỏng: <?=e($sc['title'])?></a>
                    <?php if (($sc['slug'] ?? '') === 'giao-vien'): ?>
                      <a class="btn btn-outline btn-sm w-full" href="<?=url('index.php?page=simulation-3d&career=giao-vien')?>">🏫 Trải nghiệm lớp học 3D (Giáo viên)</a>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="text-xs text-slate-500">Ngành này hiện chưa có mô phỏng nghề riêng; bạn có thể đọc chi tiết nghề hoặc so sánh trực tiếp trong mục So sánh.</p>
              <?php endif; ?>
            </section>
          </div>

          <section class="journey-card">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
              <div>
                <h2 class="text-lg font-bold text-slate-900">Trường &amp; Chương trình đào tạo có dữ liệu xác minh</h2>
                <p class="text-xs text-slate-500">Điểm tham khảo chỉ gắn với đúng chương trình, năm và phương thức đã công bố; không dùng để khẳng định khả năng trúng tuyển.</p>
              </div>
              <a class="btn btn-outline btn-sm" href="<?=url('index.php?page=majors')?>">Xem thêm trong danh mục 358 ngành →</a>
            </div>

            <?php if (empty($programs)): ?>
              <div class="lookup-empty">Chưa có dữ liệu chương trình đào tạo xác minh cho ngành này trong hệ thống. Bạn có thể tra cứu thêm ở <a class="text-brand-600 font-bold underline" href="<?=url('index.php?page=majors')?>">Danh mục 358 ngành</a>.</div>
            <?php else: ?>
              <div class="space-y-4">
                <?php foreach ($programs as $prog): ?>
                  <div class="lookup-program-box">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <h3 class="text-base font-bold text-slate-900"><?=e($prog['institution_name'])?> — <?=e($prog['name'])?></h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                          Mã CT: <?=e($prog['program_code'] ?: 'Chưa ghi mã')?> · Địa phương trường: <?=e($prog['institution_province'] ?: 'Việt Nam')?>
                        </p>
                      </div>
                      <div class="flex flex-wrap gap-2">
                        <button
                          type="button"
                          class="btn btn-outline btn-sm"
                          data-add-compare
                          data-compare-id="program:<?=(int)$prog['id']?>"
                          data-compare-label="<?=e($prog['name'] . ' — ' . $prog['institution_name'])?>"
                          data-compare-type="program"
                          data-compare-major-id="<?=(int)$m['id']?>"
                          data-compare-program-id="<?=(int)$prog['id']?>"
                        >+ Thêm vào so sánh</button>
                      </div>
                    </div>

                    <?php if (!empty($prog['records'])): ?>
                      <div class="lookup-records-list mt-3">
                        <?php foreach ($prog['records'] as $rec): ?>
                          <?php
                            $rYear = (int)$rec['admission_year'];
                            $rMethod = (string)$rec['method'];
                            $rDisp = lookup_format_score(
                                $rec['cutoff_score'] !== null ? (string)$rec['cutoff_score'] : null,
                                $rec['score_scale'] !== null ? (string)$rec['score_scale'] : null,
                                (string)($rec['score_type'] ?? 'cutoff'),
                                $rYear,
                                $rMethod
                            );
                            $rSrc = safe_external_url($rec['source_url'] ?? '');
                            $rCheckDate = !empty($rec['verified_at']) ? substr((string)$rec['verified_at'], 0, 10) : '';
                          ?>
                          <div class="lookup-record-row">
                            <span class="font-semibold text-slate-800"><?=e($rDisp)?></span>
                            <span class="text-xs text-slate-500">
                              <?php if ($rSrc !== ''): ?>
                                <a class="text-brand-600 font-semibold hover:underline" href="<?=e($rSrc)?>" target="_blank" rel="noopener noreferrer">Xem nguồn ↗</a>
                              <?php endif; ?>
                              <?php if ($rCheckDate !== ''): ?>
                                · Kiểm tra: <?=e($rCheckDate)?>
                              <?php endif; ?>
                            </span>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <p class="text-xs text-slate-500 mt-2">Điểm chuẩn: Chưa có dữ liệu xác minh.</p>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        </div>

        <script src="<?=url('assets/js/lookup.js')?>"></script>
        <?php
        require __DIR__ . '/footer.php';
        return;
    }

    // Main Landing or Tab A/B/C
    $validTabs = ['majors', 'schools', 'combinations'];
    $activeTab = in_array($tab, $validTabs, true) ? $tab : '';
    $meta = $activeTab !== '' ? lookup_get_filter_metadata() : null;
    $initialResult = null;
    if ($activeTab === 'majors') {
        $initialResult = lookup_query_majors($_GET);
    } elseif ($activeTab === 'schools') {
        $initialResult = lookup_query_schools($_GET);
    } elseif ($activeTab === 'combinations') {
        $initialResult = lookup_query_combinations($_GET);
    }

    $tabTitles = [
        '' => 'Tra cứu · Ngành học, Trường đào tạo & Tổ hợp',
        'majors' => 'Tra cứu Ngành học',
        'schools' => 'Tra cứu Trường đào tạo',
        'combinations' => 'Tra cứu theo Tổ hợp xét tuyển',
    ];
    $pageTitle = $tabTitles[$activeTab];
    require __DIR__ . '/header.php';
    ?>
    <section class="page-head journey-head">
      <div class="container journey-wide">
        <div class="journey-step-row">
          <span class="journey-step-pill">BƯỚC 2 / 4</span>
          <?php if ($activeTab !== ''): ?>
            <nav class="breadcrumb" aria-label="Đường dẫn trang">
              <a href="<?=url('index.php?page=lookup')?>">Tra cứu</a>
              <span class="breadcrumb-sep">/</span>
              <span aria-current="page"><?=e($activeTab === 'majors' ? 'Ngành học' : ($activeTab === 'schools' ? 'Trường đào tạo' : 'Tổ hợp xét tuyển'))?></span>
            </nav>
          <?php else: ?>
            <span class="journey-step-note">Tra cứu công khai · Không cần đăng nhập hay làm khảo sát</span>
          <?php endif; ?>
        </div>
        <h1 class="page-title"><?=$activeTab === '' ? 'Tra cứu' : e($pageTitle)?></h1>
        <p class="page-subtitle">
          <?php if ($activeTab === ''): ?>
            Tìm hiểu ngành học, trường đào tạo và tổ hợp xét tuyển.
          <?php elseif ($activeTab === 'majors'): ?>
            Ngành này học gì, làm gì và có những trường nào đào tạo?
          <?php elseif ($activeTab === 'schools'): ?>
            Tìm trường đại học/cao đẳng trên cả nước theo ngành, địa phương, tổ hợp và phương thức xét tuyển.
          <?php else: ?>
            Với tổ hợp này, mình có thể tìm hiểu ngành nào và trường nào?
          <?php endif; ?>
        </p>
      </div>
    </section>

    <div class="container journey-wide py-8" id="lookupApp" data-active-tab="<?=e($activeTab)?>" data-api-url="<?=e(url('index.php?page=lookup-api'))?>" data-basket-key="<?=e($basketStorageKey)?>" data-compare-url="<?=e(url('index.php?page=compare'))?>">
      <?php if ($activeTab === ''): ?>
        <!-- LANDING: Exactly 3 focused navigation cards -->
        <div class="lookup-entry-grid">
          <a class="lookup-entry-card" href="<?=url('index.php?page=lookup&tab=majors')?>">
            <span class="lookup-entry-icon" aria-hidden="true">🎓</span>
            <h2>Ngành học</h2>
            <p>Ngành này học gì, làm gì và có những trường nào đào tạo?</p>
            <span class="lookup-entry-cta">Tra cứu ngành →</span>
          </a>

          <a class="lookup-entry-card" href="<?=url('index.php?page=lookup&tab=schools')?>">
            <span class="lookup-entry-icon" aria-hidden="true">🏫</span>
            <h2>Trường đào tạo</h2>
            <p>Tìm trường theo ngành, địa phương và điều kiện xét tuyển.</p>
            <span class="lookup-entry-cta">Tra cứu trường →</span>
          </a>

          <a class="lookup-entry-card" href="<?=url('index.php?page=lookup&tab=combinations')?>">
            <span class="lookup-entry-icon" aria-hidden="true">🧩</span>
            <h2>Tổ hợp xét tuyển</h2>
            <p>Tổ hợp bạn chọn có thể dùng để xét tuyển ngành nào, ở trường nào?</p>
            <span class="lookup-entry-cta">Tra cứu tổ hợp →</span>
          </a>
        </div>

        <div class="journey-secondary-box mt-8">
          <div>
            <strong class="text-sm text-slate-900">Kho dữ liệu mở rộng của JobSimu</strong>
            <p class="text-xs text-slate-600 mt-1">Bạn cũng có thể duyệt toàn bộ 358 ngành đào tạo (248 trường ĐH/CĐ trên cả nước), 27 hồ sơ nghề hoặc chuyển sang so sánh phương án.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <a class="btn btn-outline btn-sm" href="<?=url('index.php?page=majors')?>">Danh mục 358 ngành</a>
            <a class="btn btn-outline btn-sm" href="<?=url('index.php?page=careers')?>">Thư viện nghề</a>
            <a class="btn btn-primary btn-sm" href="<?=url('index.php?page=compare')?>">Sang bước So sánh →</a>
          </div>
        </div>
      <?php else: ?>
        <!-- Sub-navigation switch bar + Compare Basket Bar -->
        <div class="lookup-top-bar">
          <div class="lookup-tab-pills" role="navigation" aria-label="Chuyển mục tra cứu">
            <a class="lookup-tab-pill<?=$activeTab === 'majors' ? ' is-active' : ''?>" href="<?=url('index.php?page=lookup&tab=majors')?>">🎓 Ngành học</a>
            <a class="lookup-tab-pill<?=$activeTab === 'schools' ? ' is-active' : ''?>" href="<?=url('index.php?page=lookup&tab=schools')?>">🏫 Trường đào tạo</a>
            <a class="lookup-tab-pill<?=$activeTab === 'combinations' ? ' is-active' : ''?>" href="<?=url('index.php?page=lookup&tab=combinations')?>">🧩 Tổ hợp xét tuyển</a>
          </div>
          <a class="btn btn-outline btn-sm" id="lookupBasketLink" href="<?=url('index.php?page=compare')?>">
            ⚖️ Danh sách so sánh (<span id="lookupBasketCount">0</span>)
          </a>
        </div>

        <!-- TAB A: MAJORS -->
        <?php if ($activeTab === 'majors'): ?>
          <form id="lookupFilterForm" class="lookup-filter-card" method="get" action="<?=url('index.php')?>">
            <input type="hidden" name="page" value="lookup">
            <input type="hidden" name="tab" value="majors">
            <div class="lookup-filter-grid">
              <div class="form-group">
                <label class="form-label" for="f-q">Từ khóa (có dấu hoặc không dấu)</label>
                <input id="f-q" name="q" class="form-input" type="search" value="<?=e((string)($_GET['q'] ?? ''))?>" placeholder="Ví dụ: cong nghe thong tin, su pham, y khoa...">
              </div>
              <div class="form-group">
                <label class="form-label" for="f-group">Nhóm ngành</label>
                <select id="f-group" name="group_id" class="form-select">
                  <option value="">Tất cả nhóm ngành</option>
                  <?php foreach ($meta['groups'] as $g): ?>
                    <option value="<?=$g['id']?>"<?=((int)($_GET['group_id'] ?? 0) === $g['id']) ? ' selected' : ''?>><?=e($g['name'])?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="f-comb">Tổ hợp xét tuyển</label>
                <select id="f-comb" name="combination" class="form-select">
                  <option value="">Tất cả tổ hợp</option>
                  <?php foreach ($meta['combinations'] as $c): ?>
                    <option value="<?=e($c['code'])?>"<?=(strtoupper((string)($_GET['combination'] ?? '')) === $c['code']) ? ' selected' : ''?>><?=e($c['code'] . ' — ' . $c['subjects_text'])?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="f-year">Năm tuyển sinh</label>
                <select id="f-year" name="year" class="form-select">
                  <option value="">Tất cả các năm có dữ liệu</option>
                  <?php foreach ($meta['years'] as $yr): ?>
                    <option value="<?=$yr?>"<?=((int)($_GET['year'] ?? 0) === $yr) ? ' selected' : ''?>>Năm <?=$yr?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <details class="lookup-more-filters">
              <summary>Lọc thêm</summary>
              <div class="pt-3 flex flex-wrap items-center gap-4">
                <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                  <input type="checkbox" name="only_verified" value="1"<?=!empty($_GET['only_verified']) ? ' checked' : ''?>>
                  <span>Chỉ hiện ngành đã có bản ghi điểm chuẩn xác minh</span>
                </label>
              </div>
            </details>

            <div class="lookup-filter-actions">
              <button type="submit" class="btn btn-primary btn-sm">Áp dụng lọc</button>
              <button type="button" id="lookupResetBtn" class="btn btn-ghost btn-sm">Xóa bộ lọc</button>
              <span id="lookupResultCount" class="lookup-count-label">Tìm thấy <strong><?=(int)$initialResult['total']?></strong> ngành</span>
            </div>
          </form>

          <p class="lookup-disclaimer">
            Lưu ý: Tổ hợp hiển thị ở cấp ngành được tổng hợp từ các chương trình đã có dữ liệu xác minh — không ngụ ý mọi trường đào tạo ngành đó đều nhận cùng tổ hợp.
          </p>

          <div id="lookupStatusBar" class="lookup-status-bar" hidden></div>
          <div id="lookupResultsContainer" class="lookup-majors-grid"></div>
          <div id="lookupPagination" class="lookup-pagination"></div>

        <!-- TAB B: SCHOOLS -->
        <?php elseif ($activeTab === 'schools'): ?>
          <form id="lookupFilterForm" class="lookup-filter-card" method="get" action="<?=url('index.php')?>">
            <input type="hidden" name="page" value="lookup">
            <input type="hidden" name="tab" value="schools">
            <div class="lookup-filter-grid">
              <div class="form-group">
                <label class="form-label" for="f-q">Tên trường / ngành</label>
                <input id="f-q" name="q" class="form-input" type="search" value="<?=e((string)($_GET['q'] ?? ''))?>" placeholder="Ví dụ: Bach khoa, Su pham, IT1...">
              </div>
              <div class="form-group">
                <label class="form-label" for="f-major">Ngành học</label>
                <select id="f-major" name="major_id" class="form-select">
                  <option value="">Tất cả ngành</option>
                  <?php foreach ($meta['majors'] as $mj): ?>
                    <option value="<?=$mj['id']?>"<?=((int)($_GET['major_id'] ?? 0) === $mj['id']) ? ' selected' : ''?>><?=e($mj['name'] . ' (' . $mj['code'] . ')')?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="f-prov">Tỉnh/Thành phố (Trường ĐH/CĐ)</label>
                <select id="f-prov" name="province" class="form-select">
                  <option value="">Tất cả địa phương</option>
                  <?php foreach ($meta['provinces'] as $pv): ?>
                    <option value="<?=e($pv)?>"<?=((string)($_GET['province'] ?? '') === $pv) ? ' selected' : ''?>><?=e($pv)?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="f-comb">Tổ hợp</label>
                <select id="f-comb" name="combination" class="form-select">
                  <option value="">Tất cả tổ hợp</option>
                  <?php foreach ($meta['combinations'] as $c): ?>
                    <option value="<?=e($c['code'])?>"<?=(strtoupper((string)($_GET['combination'] ?? '')) === $c['code']) ? ' selected' : ''?>><?=e($c['code'] . ' — ' . $c['subjects_text'])?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <details class="lookup-more-filters">
              <summary>Lọc thêm (Năm tuyển sinh &amp; Phương thức)</summary>
              <div class="lookup-filter-grid pt-3" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                <div class="form-group">
                  <label class="form-label" for="f-year">Năm tuyển sinh</label>
                  <select id="f-year" name="year" class="form-select">
                    <option value="">Tất cả các năm</option>
                    <?php foreach ($meta['years'] as $yr): ?>
                      <option value="<?=$yr?>"<?=((int)($_GET['year'] ?? 0) === $yr) ? ' selected' : ''?>>Năm <?=$yr?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label" for="f-method">Phương thức xét tuyển</label>
                  <select id="f-method" name="method" class="form-select">
                    <option value="">Tất cả phương thức</option>
                    <?php foreach ($meta['methods'] as $mt): ?>
                      <option value="<?=e($mt)?>"<?=((string)($_GET['method'] ?? '') === $mt) ? ' selected' : ''?>><?=e($mt)?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </details>

            <div class="lookup-filter-actions">
              <button type="submit" class="btn btn-primary btn-sm">Áp dụng lọc</button>
              <button type="button" id="lookupResetBtn" class="btn btn-ghost btn-sm">Xóa bộ lọc</button>
              <span id="lookupResultCount" class="lookup-count-label">Tìm thấy <strong><?=(int)$initialResult['total']?></strong> bản ghi</span>
            </div>
          </form>

          <p class="lookup-disclaimer">
            Không so trực tiếp điểm giữa các phương thức hoặc thang điểm khác nhau. Thiếu dữ liệu hiển thị “Chưa có dữ liệu xác minh” (không hiển thị 0).
          </p>

          <div id="lookupStatusBar" class="lookup-status-bar" hidden></div>
          <div id="lookupResultsContainer"></div>
          <div id="lookupPagination" class="lookup-pagination"></div>

        <!-- TAB C: COMBINATIONS -->
        <?php else: ?>
          <form id="lookupFilterForm" class="lookup-filter-card" method="get" action="<?=url('index.php')?>">
            <input type="hidden" name="page" value="lookup">
            <input type="hidden" name="tab" value="combinations">

            <div class="lookup-filter-grid" style="grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);">
              <div class="form-group">
                <label class="form-label" for="f-q">Tìm theo mã tổ hợp hoặc tên môn (có dấu/không dấu)</label>
                <input id="f-q" name="q" class="form-input" type="search" value="<?=e((string)($_GET['q'] ?? ''))?>" placeholder="Ví dụ: A00, K01, Toan, Vat ly, Tieng Anh...">
              </div>
              <div class="form-group">
                <label class="form-label" for="f-year">Năm tuyển sinh</label>
                <select id="f-year" name="year" class="form-select">
                  <option value="">Tất cả các năm</option>
                  <?php foreach ($meta['years'] as $yr): ?>
                    <option value="<?=$yr?>"<?=((int)($_GET['year'] ?? 0) === $yr) ? ' selected' : ''?>>Năm <?=$yr?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="mt-4">
              <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <span class="form-label m-0">Chọn một hoặc nhiều tổ hợp xét tuyển:</span>
                <div class="lookup-match-toggle" role="radiogroup" aria-label="Quy tắc lọc nhiều tổ hợp">
                  <label><input type="radio" name="match_mode" value="any"<?=(($_GET['match_mode'] ?? 'any') !== 'all') ? ' checked' : ''?>> Nhận ít nhất một tổ hợp đã chọn (mặc định)</label>
                  <label><input type="radio" name="match_mode" value="all"<?=(($_GET['match_mode'] ?? '') === 'all') ? ' checked' : ''?>> Nhận tất cả tổ hợp đã chọn</label>
                </div>
              </div>
              <div id="lookupCombPicker" class="lookup-comb-grid"></div>
            </div>

            <div class="lookup-filter-actions">
              <button type="submit" class="btn btn-primary btn-sm">Tra cứu ngành &amp; trường</button>
              <button type="button" id="lookupResetBtn" class="btn btn-ghost btn-sm">Xóa bộ lọc</button>
              <span id="lookupResultCount" class="lookup-count-label">Tìm thấy <strong><?=(int)$initialResult['total']?></strong> chương trình có dữ liệu xác minh</span>
            </div>
          </form>

          <p class="lookup-disclaimer">
            Chỉ hiển thị chương trình có dữ liệu xác minh chấp nhận tổ hợp tương ứng trong cùng năm và phương thức. Không suy diễn tổ hợp chỉ từ tên ngành.
          </p>

          <div id="lookupStatusBar" class="lookup-status-bar" hidden></div>
          <div id="lookupResultsContainer"></div>
        <?php endif; ?>

        <script id="lookupBootstrapData" type="application/json"><?=json_encode([
            'tab' => $activeTab,
            'initial' => $initialResult,
            'meta' => $meta,
            'urls' => [
                'major_detail_base' => url('index.php?page=lookup&tab=majors&major_id='),
                'compare' => url('index.php?page=compare'),
                'lookup_base' => url('index.php?page=lookup&tab=' . $activeTab),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)?></script>
        <script src="<?=url('assets/js/lookup.js')?>"></script>
      <?php endif; ?>
    </div>
    <?php
    require __DIR__ . '/footer.php';
}
