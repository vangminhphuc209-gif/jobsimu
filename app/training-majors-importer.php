<?php
declare(strict_types=1);

/**
 * Deterministic, transactional, idempotent importer for jobsimu_hnv_data.
 * Supports dry-run previews, separated status preservation (source, description,
 * official code with evidence, link admission status, review issues, mappings),
 * duplicate/mismatch auditing, and non-fuzzy mapping evidence/conflict recording.
 */

function hnv_valid_http_url(?string $url, bool $required = false): ?string
{
    if ($url === null) {
        if ($required) {
            throw new InvalidArgumentException('Thiếu URL nguồn bắt buộc.');
        }
        return null;
    }
    $trimmed = trim($url);
    if ($trimmed === '') {
        if ($required) {
            throw new InvalidArgumentException('URL nguồn không được để trống.');
        }
        return null;
    }
    if (strlen($trimmed) > 500 || !filter_var($trimmed, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('URL nguồn không hợp lệ: ' . $trimmed);
    }
    $scheme = strtolower((string)parse_url($trimmed, PHP_URL_SCHEME));
    $host = (string)parse_url($trimmed, PHP_URL_HOST);
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        throw new InvalidArgumentException('URL nguồn chỉ chấp nhận HTTP/HTTPS: ' . $trimmed);
    }
    return $trimmed;
}

function hnv_compute_major_issues(array $major): array
{
    $issues = [];
    $detailStatus = (string)($major['detail_status'] ?? '');
    $hasBody = !empty($major['has_article_body']);
    $candCode = $major['source_major_code_candidate'] ?? null;
    $observedCodes = is_array($major['observed_major_codes'] ?? null) ? $major['observed_major_codes'] : [];
    $schoolExtracted = $major['school_listing_count_extracted'] ?? null;

    if ($detailStatus === 'not_linked_by_source') {
        $issues[] = 'SOURCE_HAS_NO_DETAIL_LINK';
    } elseif ($detailStatus !== 'fetched') {
        $issues[] = 'DETAIL_FETCH_NOT_SUCCESSFUL';
    } elseif (!$hasBody) {
        $issues[] = 'EMPTY_ARTICLE_BODY';
    }
    if ($candCode === null || trim((string)$candCode) === '') {
        $issues[] = 'NO_SINGLE_MAJOR_CODE_CANDIDATE';
    }
    if (count($observedCodes) > 1) {
        $issues[] = 'MULTIPLE_CODES_IN_SOURCE_TABLES';
    }
    if ($detailStatus === 'fetched' && empty($schoolExtracted)) {
        $issues[] = 'NO_SCHOOL_LISTINGS_EXTRACTED';
    }
    return $issues;
}

/**
 * Detect level-label vs code prefix contradictions inside extracted admission links.
 * E.g. level_label = "Hệ Tiến sỹ" while code starts with 5/6/7 or label says "Đại học".
 */
function hnv_detect_link_level_mismatches(array $linksForMajor): array
{
    $notes = [];
    foreach ($linksForMajor as $link) {
        $level = mb_strtolower(hnv_to_nfc((string)($link['level_label'] ?? '')), 'UTF-8');
        $instLabel = mb_strtolower(hnv_to_nfc((string)($link['institution_label'] ?? '')), 'UTF-8');
        $codes = is_array($link['observed_major_codes'] ?? null) ? $link['observed_major_codes'] : [];
        $year = $link['source_news_year'] ?? null;
        $ctx = trim((string)($link['institution_label'] ?? 'Nguồn trường')) . ($year ? " ($year)" : '');

        if ($level !== '' && (str_contains($level, 'tiến sỹ') || str_contains($level, 'tiến sĩ') || str_contains($level, 'thạc sỹ') || str_contains($level, 'thạc sĩ'))) {
            $nonPostgrad = array_values(array_filter($codes, static fn($c) => preg_match('/^[567]\d{6}$/', (string)$c) === 1));
            if ($nonPostgrad !== [] || str_contains($instLabel, 'đại học chính quy') || str_contains($instLabel, 'cao đẳng')) {
                $notes[] = "$ctx: level_label=\"" . ($link['level_label'] ?? '') . '" mâu thuẫn với tên/mã (' . implode(', ', $nonPostgrad ?: ['nhãn ĐH/CĐ']) . ').';
            }
        } elseif (str_contains($level, 'đại học') && !str_contains($level, 'cao đẳng') && !str_contains($level, 'trung cấp')) {
            $lowerCodes = array_values(array_filter($codes, static fn($c) => preg_match('/^[56]\d{6}$/', (string)$c) === 1));
            if ($lowerCodes !== []) {
                $notes[] = "$ctx: bảng Hệ Đại học có kèm mã trình độ khác (" . implode(', ', $lowerCodes) . ').';
            }
        }
    }
    return array_values(array_unique($notes));
}

function hnv_read_review_queue_csv(string $csvPath): array
{
    if (!is_file($csvPath) || !is_readable($csvPath)) {
        return [];
    }
    $fp = fopen($csvPath, 'rb');
    if (!$fp) {
        return [];
    }
    $map = [];
    try {
        $headers = fgetcsv($fp, 0, ',', '"', '');
        if (!$headers) {
            return [];
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$headers[0]);
        $headers = array_map('trim', $headers);
        while (($row = fgetcsv($fp, 0, ',', '"', '')) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }
            $assoc = array_combine($headers, $row);
            $majorId = trim((string)($assoc['major_id'] ?? ''));
            if ($majorId === '') {
                continue;
            }
            $issues = json_decode((string)($assoc['issues'] ?? '[]'), true);
            $map[$majorId] = [
                'major_id' => $majorId,
                'issues' => is_array($issues) ? array_values($issues) : [],
                'source_url' => hnv_valid_http_url($assoc['source_url'] ?? null),
            ];
        }
    } finally {
        fclose($fp);
    }
    return $map;
}

function hnv_encode_json_list($value): string
{
    if (!is_array($value) || $value === []) {
        return '[]';
    }
    return json_encode(array_values($value), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

/**
 * Load and validate HNV dataset from directory or JSON file.
 * Checks uniqueness, foreign keys, URL validity, duplicate candidates, and level mismatches.
 */
function hnv_load_dataset(string $sourcePath): array
{
    $dir = is_dir($sourcePath) ? rtrim($sourcePath, '/\\') : dirname($sourcePath);
    $jsonFile = is_dir($sourcePath) ? ($dir . '/jobsimu_hnv.json') : $sourcePath;

    if (!is_file($jsonFile) || !is_readable($jsonFile)) {
        throw new InvalidArgumentException('Không tìm thấy tệp dữ liệu nguồn: ' . $jsonFile);
    }

    $rawJson = file_get_contents($jsonFile);
    if ($rawJson === false) {
        throw new RuntimeException('Không thể đọc tệp dữ liệu: ' . $jsonFile);
    }
    $data = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new InvalidArgumentException('Định dạng JSON không hợp lệ.');
    }

    foreach (['groups', 'subgroups', 'majors', 'institution_source_records', 'major_institution_links'] as $requiredKey) {
        if (!isset($data[$requiredKey]) || !is_array($data[$requiredKey])) {
            throw new InvalidArgumentException('Thiếu khóa dữ liệu bắt buộc: ' . $requiredKey);
        }
    }

    $groupsById = [];
    foreach ($data['groups'] as $g) {
        $id = trim((string)($g['id'] ?? ''));
        $name = trim((string)($g['name'] ?? ''));
        if ($id === '' || $name === '') {
            throw new InvalidArgumentException('Nhóm lĩnh vực thiếu id hoặc name.');
        }
        if (isset($groupsById[$id])) {
            throw new InvalidArgumentException('Trùng id lĩnh vực: ' . $id);
        }
        $groupsById[$id] = [
            'id' => $id,
            'name' => $name,
            'source_url' => hnv_valid_http_url((string)($g['source_url'] ?? ''), true),
        ];
    }

    $subgroupsById = [];
    foreach ($data['subgroups'] as $sg) {
        $id = trim((string)($sg['id'] ?? ''));
        $name = trim((string)($sg['name'] ?? ''));
        $groupId = trim((string)($sg['group_id'] ?? ''));
        if ($id === '' || $name === '' || !isset($groupsById[$groupId])) {
            throw new InvalidArgumentException('Nhóm ngành không hợp lệ hoặc thiếu tham chiếu lĩnh vực: ' . $id);
        }
        if (isset($subgroupsById[$id])) {
            throw new InvalidArgumentException('Trùng id nhóm ngành: ' . $id);
        }
        $subgroupsById[$id] = [
            'id' => $id,
            'field_id' => $groupId,
            'name' => $name,
            'source_url' => hnv_valid_http_url((string)($sg['source_url'] ?? ''), true),
        ];
    }

    $majorsById = [];
    $candToMajors = [];
    $urlToMajors = [];
    foreach ($data['majors'] as $m) {
        $id = trim((string)($m['id'] ?? ''));
        $name = trim((string)($m['name'] ?? ''));
        $groupId = trim((string)($m['group_id'] ?? ''));
        $subgroupId = trim((string)($m['subgroup_id'] ?? ''));
        if ($id === '' || $name === '' || !isset($groupsById[$groupId]) || !isset($subgroupsById[$subgroupId])) {
            throw new InvalidArgumentException('Ngành đào tạo không hợp lệ hoặc sai tham chiếu nhóm: ' . $id);
        }
        if ($subgroupsById[$subgroupId]['field_id'] !== $groupId) {
            throw new InvalidArgumentException("Ngành $id có subgroup $subgroupId không thuộc group $groupId.");
        }
        if (isset($majorsById[$id])) {
            throw new InvalidArgumentException('Trùng id ngành đào tạo: ' . $id);
        }
        $sUrl = isset($m['source_url']) ? hnv_valid_http_url($m['source_url']) : null;
        if (isset($m['catalogue_url'])) {
            hnv_valid_http_url($m['catalogue_url']);
        }
        if (isset($m['final_url'])) {
            hnv_valid_http_url($m['final_url']);
        }
        $majorsById[$id] = $m;
        $cand = isset($m['source_major_code_candidate']) ? trim((string)$m['source_major_code_candidate']) : '';
        if ($cand !== '') {
            $candToMajors[$cand][] = ['id' => $id, 'name' => $name];
        }
        if ($sUrl !== null) {
            $urlToMajors[$sUrl][] = ['id' => $id, 'name' => $name];
        }
    }

    $instById = [];
    foreach ($data['institution_source_records'] as $inst) {
        $id = trim((string)($inst['id'] ?? ''));
        $url = hnv_valid_http_url((string)($inst['source_url'] ?? ''), true);
        if ($id === '') {
            throw new InvalidArgumentException('Bản ghi nguồn trường thiếu id.');
        }
        if (isset($instById[$id])) {
            throw new InvalidArgumentException('Trùng id nguồn trường: ' . $id);
        }
        $instById[$id] = [
            'id' => $id,
            'source_label' => isset($inst['source_label']) && trim((string)$inst['source_label']) !== '' ? trim((string)$inst['source_label']) : null,
            'source_url' => $url,
        ];
    }

    $seenLinks = [];
    $links = [];
    $linksByMajor = [];
    foreach ($data['major_institution_links'] as $link) {
        $majorId = trim((string)($link['major_id'] ?? ''));
        $instId = trim((string)($link['institution_source_id'] ?? ''));
        $year = isset($link['source_news_year']) && $link['source_news_year'] !== null ? (int)$link['source_news_year'] : null;
        $yearKey = $year ?? 0;
        if (!isset($majorsById[$majorId])) {
            throw new InvalidArgumentException('Liên kết trỏ tới ngành không tồn tại: ' . $majorId);
        }
        if (!isset($instById[$instId])) {
            throw new InvalidArgumentException('Liên kết trỏ tới nguồn trường không tồn tại: ' . $instId);
        }
        hnv_valid_http_url((string)($link['evidence_url'] ?? ''), true);
        hnv_valid_http_url((string)($link['institution_source_url'] ?? ''), true);
        $compositeKey = $majorId . '|' . $instId . '|' . $yearKey;
        if (isset($seenLinks[$compositeKey])) {
            throw new InvalidArgumentException('Trùng liên kết ngành-nguồn trường-năm tin: ' . $compositeKey);
        }
        $seenLinks[$compositeKey] = true;
        $links[] = $link;
        $linksByMajor[$majorId][] = $link;
    }

    $csvQueue = hnv_read_review_queue_csv($dir . '/review_queue.csv');
    $issueLabels = hnv_review_issue_labels();
    $warnings = [];
    $reviewQueue = [];
    $reviewIssuesByMajor = [];
    $initialCodeConflicts = [];

    foreach ($majorsById as $majorId => $m) {
        $computed = hnv_compute_major_issues($m);
        $sourceWarnings = $csvQueue[$majorId]['issues'] ?? $computed;
        if (isset($csvQueue[$majorId]) && $csvQueue[$majorId]['issues'] !== $computed) {
            $warnings[] = "Khác biệt danh sách vấn đề review_queue.csv ở $majorId; ưu tiên review_queue.csv cho cảnh báo nguồn ban đầu.";
        }

        $cand = isset($m['source_major_code_candidate']) ? trim((string)$m['source_major_code_candidate']) : '';
        $dupOthers = [];
        if ($cand !== '' && count($candToMajors[$cand] ?? []) > 1) {
            foreach ($candToMajors[$cand] as $peer) {
                if ($peer['id'] !== $majorId) {
                    $dupOthers[] = $peer['name'] . ' (' . $peer['id'] . ')';
                }
            }
        }
        $levelMismatches = hnv_detect_link_level_mismatches($linksByMajor[$majorId] ?? []);
        if ($dupOthers !== [] || $levelMismatches !== []) {
            $initialCodeConflicts[$majorId] = [
                'duplicate_peers' => $dupOthers,
                'level_mismatches' => $levelMismatches,
            ];
        }

        $srcUrl = $csvQueue[$majorId]['source_url'] ?? hnv_valid_http_url($m['source_url'] ?? ($m['catalogue_url'] ?? null));
        $reviewQueue[$majorId] = [
            'major_id' => $majorId,
            'source_warnings' => array_values($sourceWarnings),
            'source_url' => $srcUrl,
        ];

        $issuesForMajor = [];
        foreach ($sourceWarnings as $code) {
            $meta = $issueLabels[$code] ?? ['label' => $code, 'category' => 'source'];
            $issuesForMajor[$code] = [
                'issue_code' => $code,
                'issue_category' => $meta['category'],
                'origin' => 'initial_source',
                'detail_note' => $meta['label'],
            ];
        }
        if ($dupOthers !== []) {
            $issuesForMajor['DUPLICATE_CANDIDATE_CODE'] = [
                'issue_code' => 'DUPLICATE_CANDIDATE_CODE',
                'issue_category' => 'code',
                'origin' => 'importer_audit',
                'detail_note' => "Mã ứng viên $cand cũng xuất hiện ở: " . implode(', ', $dupOthers),
            ];
        }
        if ($levelMismatches !== []) {
            $issuesForMajor['LEVEL_CODE_MISMATCH'] = [
                'issue_code' => 'LEVEL_CODE_MISMATCH',
                'issue_category' => 'code',
                'origin' => 'importer_audit',
                'detail_note' => implode(' | ', array_slice($levelMismatches, 0, 5)),
            ];
        }
        $issuesForMajor['MISSING_JOBSIMU_DESCRIPTION'] = [
            'issue_code' => 'MISSING_JOBSIMU_DESCRIPTION',
            'issue_category' => 'description',
            'origin' => 'editorial_workflow',
            'detail_note' => 'Chưa có mô tả ngành do JobSimu biên tập và duyệt công khai.',
        ];
        $issuesForMajor['UNVERIFIED_OFFICIAL_CODE'] = [
            'issue_code' => 'UNVERIFIED_OFFICIAL_CODE',
            'issue_category' => 'code',
            'origin' => 'editorial_workflow',
            'detail_note' => 'Mã ngành chính thức (Bộ GD&ĐT) chưa được xác minh kèm bằng chứng nguồn.',
        ];

        $reviewIssuesByMajor[$majorId] = array_values($issuesForMajor);
    }

    $qualityReport = null;
    $qrFile = $dir . '/quality_report.json';
    if (is_file($qrFile) && is_readable($qrFile)) {
        $decodedQr = json_decode((string)file_get_contents($qrFile), true);
        if (is_array($decodedQr)) {
            $qualityReport = $decodedQr;
            if (($decodedQr['catalogue_items'] ?? null) !== count($majorsById)
                || ($decodedQr['groups'] ?? null) !== count($groupsById)
                || ($decodedQr['subgroups'] ?? null) !== count($subgroupsById)
                || ($decodedQr['major_institution_links'] ?? null) !== count($links)
            ) {
                $warnings[] = 'Số liệu quality_report.json khác với số bản ghi thực tế trong JSON; sử dụng số liệu thực tế từ JSON.';
            }
        }
    }

    return [
        'source_name' => basename($dir) ?: 'jobsimu_hnv_data',
        'source_url' => (string)($data['source'] ?? 'https://huongnghiepviet.com/nganh-nghe'),
        'schema_version' => (string)($data['schema_version'] ?? '1.0.0'),
        'retrieved_at' => isset($data['retrieved_at']) ? (string)$data['retrieved_at'] : null,
        'groups' => array_values($groupsById),
        'subgroups' => array_values($subgroupsById),
        'majors' => array_values($majorsById),
        'institution_source_records' => array_values($instById),
        'links' => $links,
        'links_by_major' => $linksByMajor,
        'candidate_to_majors' => $candToMajors,
        'url_to_majors' => $urlToMajors,
        'initial_code_conflicts' => $initialCodeConflicts,
        'review_queue' => $reviewQueue,
        'review_issues_by_major' => $reviewIssuesByMajor,
        'quality_report' => $qualityReport,
        'warnings' => $warnings,
    ];
}

/** Determine whether a training_majors DB row carries human curation/verification that must be protected. */
function hnv_major_has_protected_curation(array $cur): bool
{
    $descStatus = (string)($cur['description_status'] ?? 'not_edited');
    $codeStatus = (string)($cur['code_verification_status'] ?? 'unverified');
    $legacyStatus = (string)($cur['editorial_status'] ?? 'unverified');

    if ((int)($cur['is_editorially_locked'] ?? 0) === 1) {
        return true;
    }
    if (in_array($descStatus, ['draft', 'approved'], true) || ($cur['editorial_description'] !== null && trim((string)$cur['editorial_description']) !== '') || !empty($cur['description_updated_at'])) {
        return true;
    }
    if ($codeStatus === 'verified' || ($cur['official_major_code'] !== null && trim((string)$cur['official_major_code']) !== '') || !empty($cur['code_checked_at']) || !empty($cur['code_verified_by'])) {
        return true;
    }
    if ($codeStatus === 'conflict' && (($cur['code_verification_note'] !== null && trim((string)$cur['code_verification_note']) !== '') || !empty($cur['code_evidence_url']))) {
        return true;
    }
    if (in_array($legacyStatus, ['editorial_verified', 'officially_verified'], true) || !empty($cur['reviewed_at'])) {
        return true;
    }
    return false;
}

/**
 * Import or dry-run HNV dataset into JobSimu database.
 * - dry_run=true executes zero writes and returns full diff counters.
 * - dry_run=false wraps all writes in a database transaction and logs runs in dataset_import_runs.
 */
function hnv_import_dataset(PDO $db, string $sourcePath, array $options = []): array
{
    $dryRun = !empty($options['dry_run']);
    $initiatedBy = isset($options['initiated_by']) ? (int)$options['initiated_by'] : null;
    $startedAt = gmdate('Y-m-d H:i:s');
    $now = date('Y-m-d H:i:s');

    if (!training_majors_tables_ready($db) || !training_majors_columns_ready($db)) {
        throw new RuntimeException('Các bảng hoặc cột mới của danh mục ngành đào tạo chưa được khởi tạo. Hãy chạy migration trước.');
    }

    $dataset = null;
    $startedTx = false;

    try {
        $dataset = hnv_load_dataset($sourcePath);
        $retrievedAt = $dataset['retrieved_at'];

        $counters = [
            'major_fields' => ['inserted' => 0, 'updated' => 0, 'skipped' => 0],
            'major_subgroups' => ['inserted' => 0, 'updated' => 0, 'skipped' => 0],
            'training_majors' => ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'preserved_editorial' => 0],
            'institution_source_records' => ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'preserved_editorial' => 0],
            'major_institution_source_links' => ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'preserved_editorial' => 0],
            'major_review_queue' => ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'preserved_editorial' => 0],
            'major_review_issues' => ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'preserved_editorial' => 0],
            'training_major_mappings' => ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'preserved_editorial' => 0],
        ];

        $existingFields = [];
        foreach ($db->query('SELECT id, name, normalized_name, source_url, retrieved_at FROM major_fields')->fetchAll() as $row) {
            $existingFields[$row['id']] = $row;
        }

        $existingSubgroups = [];
        foreach ($db->query('SELECT id, field_id, name, normalized_name, source_url, retrieved_at FROM major_subgroups')->fetchAll() as $row) {
            $existingSubgroups[$row['id']] = $row;
        }

        $existingMajors = [];
        foreach ($db->query('SELECT * FROM training_majors')->fetchAll() as $row) {
            $existingMajors[$row['id']] = $row;
        }

        $existingInst = [];
        foreach ($db->query('SELECT id, source_label, normalized_label, source_url, official_institution_id, retrieved_at FROM institution_source_records')->fetchAll() as $row) {
            $existingInst[$row['id']] = $row;
        }

        $existingLinks = [];
        foreach ($db->query('SELECT id, major_id, institution_source_id, source_news_year, source_news_year_key, level_label, observed_major_codes_json, observed_table_rows_json, observed_subject_combination_codes_json, evidence_url, verification_status, admission_status, link_checked_at, retrieved_at, raw_payload_json FROM major_institution_source_links')->fetchAll() as $row) {
            $k = $row['major_id'] . '|' . $row['institution_source_id'] . '|' . (int)$row['source_news_year_key'];
            $existingLinks[$k] = $row;
        }

        $existingQueue = [];
        foreach ($db->query('SELECT id, major_id, issues_json, source_warnings_json, issue_count, open_issue_count, source_url, review_status, reviewer_note, reviewed_at FROM major_review_queue')->fetchAll() as $row) {
            $existingQueue[$row['major_id']] = $row;
        }

        $existingIssues = [];
        foreach ($db->query('SELECT id, major_id, issue_code, issue_category, origin, status, detail_note, resolution_note, resolved_by, resolved_at FROM major_review_issues')->fetchAll() as $row) {
            $existingIssues[$row['major_id'] . '|' . $row['issue_code']] = $row;
        }

        $existingStarterMajors = $db->query('SELECT id, code, name FROM majors')->fetchAll();
        $existingMappings = [];
        foreach ($db->query('SELECT id, training_major_id, existing_major_id, match_basis, mapping_status, evidence_json, conflict_notes, has_conflict FROM training_major_mappings')->fetchAll() as $row) {
            $existingMappings[$row['training_major_id'] . '|' . (int)$row['existing_major_id']] = $row;
        }

        if (!$dryRun) {
            $db->beginTransaction();
            $startedTx = true;
        }
        // 1. Fields
        $insField = !$dryRun ? $db->prepare('INSERT INTO major_fields(id,name,normalized_name,source_system,source_url,retrieved_at) VALUES(?,?,?,?,?,?)') : null;
        $updField = !$dryRun ? $db->prepare('UPDATE major_fields SET name=?,normalized_name=?,source_url=?,retrieved_at=? WHERE id=?') : null;
        foreach ($dataset['groups'] as $g) {
            $norm = hnv_strip_vi_accents($g['name']);
            $cur = $existingFields[$g['id']] ?? null;
            if (!$cur) {
                $counters['major_fields']['inserted']++;
                if (!$dryRun) {
                    $insField->execute([$g['id'], $g['name'], $norm, 'hnv', $g['source_url'], $retrievedAt]);
                }
            } elseif ($cur['name'] === $g['name'] && $cur['source_url'] === $g['source_url'] && (string)$cur['retrieved_at'] === (string)$retrievedAt) {
                $counters['major_fields']['skipped']++;
            } else {
                $counters['major_fields']['updated']++;
                if (!$dryRun) {
                    $updField->execute([$g['name'], $norm, $g['source_url'], $retrievedAt, $g['id']]);
                }
            }
        }

        // 2. Subgroups
        $insSub = !$dryRun ? $db->prepare('INSERT INTO major_subgroups(id,field_id,name,normalized_name,source_system,source_url,retrieved_at) VALUES(?,?,?,?,?,?,?)') : null;
        $updSub = !$dryRun ? $db->prepare('UPDATE major_subgroups SET field_id=?,name=?,normalized_name=?,source_url=?,retrieved_at=? WHERE id=?') : null;
        foreach ($dataset['subgroups'] as $sg) {
            $norm = hnv_strip_vi_accents($sg['name']);
            $cur = $existingSubgroups[$sg['id']] ?? null;
            if (!$cur) {
                $counters['major_subgroups']['inserted']++;
                if (!$dryRun) {
                    $insSub->execute([$sg['id'], $sg['field_id'], $sg['name'], $norm, 'hnv', $sg['source_url'], $retrievedAt]);
                }
            } elseif ($cur['field_id'] === $sg['field_id'] && $cur['name'] === $sg['name'] && $cur['source_url'] === $sg['source_url'] && (string)$cur['retrieved_at'] === (string)$retrievedAt) {
                $counters['major_subgroups']['skipped']++;
            } else {
                $counters['major_subgroups']['updated']++;
                if (!$dryRun) {
                    $updSub->execute([$sg['field_id'], $sg['name'], $norm, $sg['source_url'], $retrievedAt, $sg['id']]);
                }
            }
        }

        // 3. Training majors (strictly separates source vs description_status vs code_verification_status)
        $insMajor = !$dryRun ? $db->prepare(
            'INSERT INTO training_majors (
                id, field_id, subgroup_id, name, normalized_name, source_label, entity_type, source_system,
                source_url, catalogue_url, final_url, source_page_title, detail_status, http_status,
                has_article_body, body_sha256, content_topics_json, listed_school_count,
                listed_school_count_year, school_listing_count_extracted, source_major_code_candidate,
                official_major_code, major_code_basis, major_code_requires_official_verification,
                observed_major_codes_json, source_dom_code, verification_status,
                career_mapping_status, assessment_mapping_status, normalization_note,
                editorial_description, description_status, code_verification_status,
                editorial_status, is_editorially_locked, retrieved_at, raw_payload_json
            ) VALUES (' . implode(',', array_fill(0, 37, '?')) . ')'
        ) : null;

        // Only updates uncurated/source fields; NEVER touches description_*, code_verified*, official_major_code, is_editorially_locked
        $updMajorSource = !$dryRun ? $db->prepare(
            'UPDATE training_majors SET
                field_id=?, subgroup_id=?, name=?, normalized_name=?, source_label=?,
                source_url=?, catalogue_url=?, final_url=?, source_page_title=?, detail_status=?, http_status=?,
                has_article_body=?, body_sha256=?, content_topics_json=?, listed_school_count=?,
                listed_school_count_year=?, school_listing_count_extracted=?, source_major_code_candidate=?,
                major_code_basis=?, major_code_requires_official_verification=?, observed_major_codes_json=?,
                source_dom_code=?, verification_status=?, career_mapping_status=?, assessment_mapping_status=?,
                normalization_note=?, code_verification_status=?, retrieved_at=?, raw_payload_json=?
             WHERE id=?'
        ) : null;

        foreach ($dataset['majors'] as $m) {
            $id = trim((string)$m['id']);
            $name = trim((string)$m['name']);
            $normName = hnv_strip_vi_accents($name);
            $sourceLabel = trim((string)($m['source_label'] ?? $name));
            $entityType = (string)($m['entity_type'] ?? 'training_major');
            $sourceUrl = isset($m['source_url']) ? hnv_valid_http_url($m['source_url']) : null;
            $catalogueUrl = isset($m['catalogue_url']) ? hnv_valid_http_url($m['catalogue_url']) : null;
            $finalUrl = isset($m['final_url']) ? hnv_valid_http_url($m['final_url']) : null;
            $sourceTitle = isset($m['source_page_title']) && $m['source_page_title'] !== null ? (string)$m['source_page_title'] : null;
            $detailStatus = (string)($m['detail_status'] ?? 'pending');
            $httpStatus = isset($m['http_status']) && $m['http_status'] !== null ? (int)$m['http_status'] : null;
            $hasArticleBody = !empty($m['has_article_body']) ? 1 : 0;
            $bodySha = isset($m['body_sha256']) && $m['body_sha256'] !== null ? (string)$m['body_sha256'] : null;
            $topicsJson = hnv_encode_json_list($m['content_topics_present'] ?? []);
            $listedCount = isset($m['listed_school_count']) && $m['listed_school_count'] !== null ? (int)$m['listed_school_count'] : null;
            $listedYear = isset($m['listed_school_count_year']) && $m['listed_school_count_year'] !== null ? (int)$m['listed_school_count_year'] : null;
            $extractedCount = isset($m['school_listing_count_extracted']) && $m['school_listing_count_extracted'] !== null ? (int)$m['school_listing_count_extracted'] : null;
            $candCode = isset($m['source_major_code_candidate']) && trim((string)$m['source_major_code_candidate']) !== ''
                ? trim((string)$m['source_major_code_candidate'])
                : null;
            $codeBasis = isset($m['major_code_basis']) && $m['major_code_basis'] !== null ? (string)$m['major_code_basis'] : null;
            $requiresVerify = array_key_exists('major_code_requires_official_verification', $m)
                ? (!empty($m['major_code_requires_official_verification']) ? 1 : 0)
                : 1;
            $observedJson = hnv_encode_json_list($m['observed_major_codes'] ?? []);
            $domCode = isset($m['source_dom_code']) && $m['source_dom_code'] !== null ? (string)$m['source_dom_code'] : null;
            $verifStatus = (string)($m['verification_status'] ?? 'source_extracted_not_officially_verified');
            $careerStatus = (string)($m['career_mapping_status'] ?? 'not_inferred_from_training_major');
            $assessStatus = (string)($m['assessment_mapping_status'] ?? 'not_scored');
            $normNote = isset($m['normalization_note']) && $m['normalization_note'] !== null ? (string)$m['normalization_note'] : null;
            $mRetrievedAt = isset($m['retrieved_at']) ? (string)$m['retrieved_at'] : $retrievedAt;
            $payloadJson = json_encode($m, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $autoCodeStatus = isset($dataset['initial_code_conflicts'][$id]) ? 'conflict' : 'unverified';

            $cur = $existingMajors[$id] ?? null;
            if (!$cur) {
                $counters['training_majors']['inserted']++;
                if (!$dryRun) {
                    $insMajor->execute([
                        $id, $m['group_id'], $m['subgroup_id'], $name, $normName, $sourceLabel, $entityType, 'hnv',
                        $sourceUrl, $catalogueUrl, $finalUrl, $sourceTitle, $detailStatus, $httpStatus,
                        $hasArticleBody, $bodySha, $topicsJson, $listedCount,
                        $listedYear, $extractedCount, $candCode,
                        null, // official_major_code is NEVER auto-filled from source_major_code_candidate
                        $codeBasis, $requiresVerify,
                        $observedJson, $domCode, $verifStatus,
                        $careerStatus, $assessStatus, $normNote,
                        null, // editorial_description is NEVER fabricated from source
                        'not_edited', $autoCodeStatus,
                        'unverified', 0, $mRetrievedAt, $payloadJson,
                    ]);
                }
                continue;
            }

            $isProtected = hnv_major_has_protected_curation($cur);
            if ($isProtected) {
                // Curated descriptions, verified codes, evidence, and locks are strictly protected
                $counters['training_majors']['preserved_editorial']++;
                $counters['training_majors']['skipped']++;
                continue;
            }

            $targetCodeStatus = $cur['code_verification_status'] === 'verified' ? 'verified' : $autoCodeStatus;
            $unchanged = $cur['field_id'] === $m['group_id']
                && $cur['subgroup_id'] === $m['subgroup_id']
                && $cur['name'] === $name
                && (string)$cur['source_url'] === (string)$sourceUrl
                && $cur['detail_status'] === $detailStatus
                && (int)$cur['has_article_body'] === $hasArticleBody
                && (string)$cur['source_major_code_candidate'] === (string)$candCode
                && $cur['observed_major_codes_json'] === $observedJson
                && (string)$cur['code_verification_status'] === $targetCodeStatus
                && (string)$cur['retrieved_at'] === (string)$mRetrievedAt
                && $cur['raw_payload_json'] === $payloadJson;

            if ($unchanged) {
                $counters['training_majors']['skipped']++;
            } else {
                $counters['training_majors']['updated']++;
                if (!$dryRun) {
                    $updMajorSource->execute([
                        $m['group_id'], $m['subgroup_id'], $name, $normName, $sourceLabel,
                        $sourceUrl, $catalogueUrl, $finalUrl, $sourceTitle, $detailStatus, $httpStatus,
                        $hasArticleBody, $bodySha, $topicsJson, $listedCount,
                        $listedYear, $extractedCount, $candCode,
                        $codeBasis, $requiresVerify, $observedJson,
                        $domCode, $verifStatus, $careerStatus, $assessStatus,
                        $normNote, $targetCodeStatus, $mRetrievedAt, $payloadJson, $id,
                    ]);
                }
            }
        }

        // 4. Institution source records (never merged automatically into institutions)
        $insInst = !$dryRun ? $db->prepare('INSERT INTO institution_source_records(id,source_label,normalized_label,source_url,official_institution_id,source_system,verification_status,retrieved_at) VALUES(?,?,?,?,NULL,?,?,?)') : null;
        $updInst = !$dryRun ? $db->prepare('UPDATE institution_source_records SET source_label=?,normalized_label=?,source_url=?,retrieved_at=? WHERE id=?') : null;
        foreach ($dataset['institution_source_records'] as $inst) {
            $normLabel = $inst['source_label'] !== null ? hnv_strip_vi_accents($inst['source_label']) : null;
            $cur = $existingInst[$inst['id']] ?? null;
            if (!$cur) {
                $counters['institution_source_records']['inserted']++;
                if (!$dryRun) {
                    $insInst->execute([$inst['id'], $inst['source_label'], $normLabel, $inst['source_url'], 'hnv', 'unmerged_source_url', $retrievedAt]);
                }
            } else {
                if (!empty($cur['official_institution_id'])) {
                    $counters['institution_source_records']['preserved_editorial']++;
                }
                if ((string)$cur['source_label'] === (string)$inst['source_label'] && $cur['source_url'] === $inst['source_url'] && (string)$cur['retrieved_at'] === (string)$retrievedAt) {
                    $counters['institution_source_records']['skipped']++;
                } else {
                    $counters['institution_source_records']['updated']++;
                    if (!$dryRun) {
                        $updInst->execute([$inst['source_label'], $normLabel, $inst['source_url'], $retrievedAt, $inst['id']]);
                    }
                }
            }
        }

        // 5. Major <-> Institution source links (preserve admission_status & manual checks)
        $insLink = !$dryRun ? $db->prepare(
            'INSERT INTO major_institution_source_links(
                major_id, institution_source_id, source_news_year, source_news_year_key, level_label,
                observed_major_codes_json, observed_table_rows_json, observed_subject_combination_codes_json,
                evidence_url, verification_status, admission_status, retrieved_at, raw_payload_json
            ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)'
        ) : null;
        $updLink = !$dryRun ? $db->prepare(
            'UPDATE major_institution_source_links SET
                level_label=?, observed_major_codes_json=?, observed_table_rows_json=?,
                observed_subject_combination_codes_json=?, evidence_url=?, verification_status=?,
                retrieved_at=?, raw_payload_json=?
             WHERE id=?'
        ) : null;

        foreach ($dataset['links'] as $link) {
            $majorId = trim((string)$link['major_id']);
            $instId = trim((string)$link['institution_source_id']);
            $year = isset($link['source_news_year']) && $link['source_news_year'] !== null ? (int)$link['source_news_year'] : null;
            $yearKey = $year ?? 0;
            $levelLabel = isset($link['level_label']) && trim((string)$link['level_label']) !== '' ? trim((string)$link['level_label']) : null;
            $obsMajorsJson = hnv_encode_json_list($link['observed_major_codes'] ?? []);
            $obsRowsJson = isset($link['observed_table_rows']) ? hnv_encode_json_list($link['observed_table_rows']) : '[]';
            $obsCombsJson = hnv_encode_json_list($link['observed_subject_combination_codes'] ?? []);
            $evidenceUrl = (string)$link['evidence_url'];
            $verifStatus = (string)($link['verification_status'] ?? 'historical_source_listing_not_current_admission_confirmation');
            $lRetrievedAt = isset($link['retrieved_at']) ? (string)$link['retrieved_at'] : $retrievedAt;
            $payloadJson = json_encode($link, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $compositeKey = $majorId . '|' . $instId . '|' . $yearKey;
            $cur = $existingLinks[$compositeKey] ?? null;
            if (!$cur) {
                $counters['major_institution_source_links']['inserted']++;
                if (!$dryRun) {
                    $insLink->execute([
                        $majorId, $instId, $year, $yearKey, $levelLabel,
                        $obsMajorsJson, $obsRowsJson, $obsCombsJson,
                        $evidenceUrl, $verifStatus, 'historical_unverified', $lRetrievedAt, $payloadJson,
                    ]);
                }
            } else {
                $hasCuratedStatus = ((string)($cur['admission_status'] ?? 'historical_unverified') !== 'historical_unverified')
                    || !empty($cur['link_checked_at']);
                if ($hasCuratedStatus) {
                    $counters['major_institution_source_links']['preserved_editorial']++;
                }
                $unchanged = (string)$cur['level_label'] === (string)$levelLabel
                    && $cur['observed_major_codes_json'] === $obsMajorsJson
                    && (string)($cur['observed_table_rows_json'] ?? '[]') === $obsRowsJson
                    && $cur['observed_subject_combination_codes_json'] === $obsCombsJson
                    && $cur['evidence_url'] === $evidenceUrl
                    && $cur['verification_status'] === $verifStatus
                    && (string)$cur['retrieved_at'] === (string)$lRetrievedAt
                    && $cur['raw_payload_json'] === $payloadJson;
                if ($unchanged) {
                    $counters['major_institution_source_links']['skipped']++;
                } else {
                    $counters['major_institution_source_links']['updated']++;
                    if (!$dryRun) {
                        $updLink->execute([
                            $levelLabel, $obsMajorsJson, $obsRowsJson, $obsCombsJson,
                            $evidenceUrl, $verifStatus, $lRetrievedAt, $payloadJson, (int)$cur['id'],
                        ]);
                    }
                }
            }
        }

        // 6. Review queue (immutable source_warnings_json + per-issue rows in major_review_issues)
        $insQueue = !$dryRun ? $db->prepare('INSERT INTO major_review_queue(major_id,issues_json,source_warnings_json,issue_count,open_issue_count,source_url,review_status,last_detected_at) VALUES(?,?,?,?,?,?,?,?)') : null;
        $updQueueBackfill = !$dryRun ? $db->prepare('UPDATE major_review_queue SET source_warnings_json=?,issue_count=?,source_url=?,last_detected_at=? WHERE major_id=?') : null;
        $insIssue = !$dryRun ? $db->prepare('INSERT INTO major_review_issues(major_id,issue_code,issue_category,origin,status,detail_note,resolved_at) VALUES(?,?,?,?,?,?,?)') : null;
        $updIssueOpen = !$dryRun ? $db->prepare('UPDATE major_review_issues SET detail_note=?,issue_category=?,origin=? WHERE id=?') : null;

        $openReviewMajors = 0;
        foreach ($dataset['review_queue'] as $majorId => $rq) {
            $sourceWarningsJson = hnv_encode_json_list($rq['source_warnings']);
            $warningCount = count($rq['source_warnings']);
            $srcUrl = $rq['source_url'];
            $curQueue = $existingQueue[$majorId] ?? null;
            $curMajor = $existingMajors[$majorId] ?? [];
            $issuesForMajor = $dataset['review_issues_by_major'][$majorId] ?? [];

            $openIssueCodes = [];
            foreach ($issuesForMajor as $iss) {
                $iKey = $majorId . '|' . $iss['issue_code'];
                $curIss = $existingIssues[$iKey] ?? null;

                if (!$curIss) {
                    $initialStatus = 'open';
                    $resolvedAt = null;
                    if ($iss['issue_code'] === 'MISSING_JOBSIMU_DESCRIPTION' && ($curMajor['description_status'] ?? 'not_edited') === 'approved') {
                        $initialStatus = 'resolved';
                        $resolvedAt = $curMajor['description_updated_at'] ?? $now;
                    } elseif ($iss['issue_code'] === 'UNVERIFIED_OFFICIAL_CODE' && ($curMajor['code_verification_status'] ?? 'unverified') === 'verified') {
                        $initialStatus = 'resolved';
                        $resolvedAt = $curMajor['code_checked_at'] ?? $now;
                    }
                    $counters['major_review_issues']['inserted']++;
                    if ($initialStatus === 'open') {
                        $openIssueCodes[] = $iss['issue_code'];
                    }
                    if (!$dryRun) {
                        $insIssue->execute([
                            $majorId, $iss['issue_code'], $iss['issue_category'], $iss['origin'],
                            $initialStatus, $iss['detail_note'], $resolvedAt,
                        ]);
                    }
                } else {
                    if (in_array((string)$curIss['status'], ['resolved', 'deferred'], true)) {
                        // Never overwrite manually resolved or deferred review issues
                        $counters['major_review_issues']['preserved_editorial']++;
                        $counters['major_review_issues']['skipped']++;
                    } else {
                        $openIssueCodes[] = $iss['issue_code'];
                        if ((string)$curIss['detail_note'] === (string)$iss['detail_note'] && (string)$curIss['issue_category'] === (string)$iss['issue_category']) {
                            $counters['major_review_issues']['skipped']++;
                        } else {
                            $counters['major_review_issues']['updated']++;
                            if (!$dryRun) {
                                $updIssueOpen->execute([$iss['detail_note'], $iss['issue_category'], $iss['origin'], (int)$curIss['id']]);
                            }
                        }
                    }
                }
            }

            // Include any extra manual issues (e.g. CODE_CONFLICT) that might already be open in DB
            foreach ($existingIssues as $iKey => $eiRow) {
                if (str_starts_with($iKey, $majorId . '|') && $eiRow['status'] === 'open' && !in_array($eiRow['issue_code'], $openIssueCodes, true)) {
                    $openIssueCodes[] = $eiRow['issue_code'];
                }
            }
            if ($openIssueCodes !== []) {
                $openReviewMajors++;
            }

            if (!$curQueue) {
                $counters['major_review_queue']['inserted']++;
                if (!$dryRun) {
                    $insQueue->execute([$majorId, $sourceWarningsJson, $sourceWarningsJson, $warningCount, count($openIssueCodes), $srcUrl, 'pending', $now]);
                }
            } else {
                if ((string)$curQueue['review_status'] !== 'pending' || !empty($curQueue['reviewed_at']) || !empty($curQueue['reviewer_note'])) {
                    $counters['major_review_queue']['preserved_editorial']++;
                }
                $needsBackfill = ($curQueue['source_warnings_json'] === null || trim((string)$curQueue['source_warnings_json']) === '' || ((string)$curQueue['source_warnings_json'] === '[]' && $warningCount > 0));
                $needsOpenSync = (int)$curQueue['open_issue_count'] !== count($openIssueCodes);
                if (!$needsBackfill && !$needsOpenSync && (string)$curQueue['source_url'] === (string)$srcUrl) {
                    $counters['major_review_queue']['skipped']++;
                } else {
                    $counters['major_review_queue']['updated']++;
                    if (!$dryRun) {
                        if ($needsBackfill) {
                            $updQueueBackfill->execute([$sourceWarningsJson, $warningCount, $srcUrl, $now, $majorId]);
                        }
                    }
                }
            }

            if (!$dryRun) {
                hnv_recompute_queue_status($db, $majorId);
            }
        }

        // 7. Non-fuzzy mapping table against existing curated `majors` table (with evidence & conflict notes)
        $starterByTitle = [];
        $starterByCode = [];
        foreach ($existingStarterMajors as $em) {
            $starterByTitle[hnv_normalize_exact_title((string)$em['name'])][] = $em;
            if (trim((string)$em['code']) !== '') {
                $starterByCode[trim((string)$em['code'])] = $em;
            }
        }
        $candToMajors = $dataset['candidate_to_majors'];
        $urlToMajors = $dataset['url_to_majors'];

        $insMap = !$dryRun ? $db->prepare('INSERT INTO training_major_mappings(training_major_id,existing_major_id,match_basis,mapping_status,confidence_note,evidence_json,conflict_notes,has_conflict) VALUES(?,?,?,?,?,?,?,?)') : null;
        $updMap = !$dryRun ? $db->prepare('UPDATE training_major_mappings SET match_basis=?,confidence_note=?,evidence_json=?,conflict_notes=?,has_conflict=? WHERE id=?') : null;
        $pendingMappings = 0;

        foreach ($dataset['majors'] as $m) {
            $tmId = (string)$m['id'];
            $tmName = (string)$m['name'];
            $exactTitle = hnv_normalize_exact_title($tmName);
            $cand = isset($m['source_major_code_candidate']) ? trim((string)$m['source_major_code_candidate']) : '';
            $obsCodes = is_array($m['observed_major_codes'] ?? null) ? array_values($m['observed_major_codes']) : [];
            $domCode = isset($m['source_dom_code']) ? trim((string)$m['source_dom_code']) : '';
            $sUrl = isset($m['source_url']) ? trim((string)$m['source_url']) : '';
            $extractedCount = (int)($m['school_listing_count_extracted'] ?? 0);

            $matchedPairs = [];
            foreach ($starterByTitle[$exactTitle] ?? [] as $em) {
                $emId = (int)$em['id'];
                if ($cand !== '' && $cand === trim((string)$em['code'])) {
                    $matchedPairs[$emId] = ['exact_name_and_candidate_code', 'Trùng tên chuẩn hóa và mã ứng viên nguồn khớp bảng starter; cần duyệt thủ công và KHÔNG thay thế xác minh mã chính thức.'];
                } else {
                    $matchedPairs[$emId] = ['exact_name_without_candidate_code', 'Trùng tên chuẩn hóa nhưng HNV chưa có mã ứng viên đơn nhất khớp; không tự gộp, chờ đối chiếu.'];
                }
            }
            if ($cand !== '' && count($candToMajors[$cand] ?? []) === 1 && isset($starterByCode[$cand])) {
                $em = $starterByCode[$cand];
                $emId = (int)$em['id'];
                if (!isset($matchedPairs[$emId])) {
                    $matchedPairs[$emId] = ['unique_candidate_code_name_differs', 'Mã ứng viên khớp bảng starter nhưng tên ngành khác (' . $em['name'] . '); chờ kiểm tra.'];
                }
            }

            foreach ($matchedPairs as $emId => [$basis, $note]) {
                $em = null;
                foreach ($existingStarterMajors as $rowEm) {
                    if ((int)$rowEm['id'] === $emId) {
                        $em = $rowEm;
                        break;
                    }
                }
                $emCode = trim((string)($em['code'] ?? ''));
                $emName = trim((string)($em['name'] ?? ''));

                $conflicts = [];
                if (mb_strtolower(hnv_to_nfc($tmName), 'UTF-8') !== mb_strtolower(hnv_to_nfc($emName), 'UTF-8')) {
                    $conflicts[] = "Khác biệt chính tả/ký tự tên ngành: HNV=\"$tmName\" vs bảng hiện có=\"$emName\".";
                }
                if ($cand === '') {
                    $conflicts[] = 'HNV chưa trích xuất được mã ngành ứng viên đơn nhất (source_major_code_candidate trống).';
                } elseif ($cand !== $emCode) {
                    $conflicts[] = "Mã ứng viên HNV ($cand) khác mã trong bảng starter ($emCode).";
                }
                if (count($obsCodes) > 1) {
                    $conflicts[] = 'Trang HNV liệt kê nhiều mã trong các bảng tuyển sinh: ' . implode(', ', $obsCodes) . '.';
                }
                if ($cand !== '' && count($candToMajors[$cand] ?? []) > 1) {
                    $conflicts[] = "Mã ứng viên $cand xuất hiện ở nhiều ngành trong bộ HNV.";
                }
                if (($m['detail_status'] ?? '') === 'fetched' && $extractedCount === 0) {
                    $conflicts[] = 'Trang chi tiết HNV chưa trích xuất được liên kết tuyển sinh trường nào (0 liên kết).';
                }
                if ($sUrl !== '' && count($urlToMajors[$sUrl] ?? []) > 1) {
                    $peers = array_map(static fn($p) => $p['name'], array_filter($urlToMajors[$sUrl], static fn($p) => $p['id'] !== $tmId));
                    $conflicts[] = 'URL nguồn HNV dùng chung với ngành khác: ' . implode(', ', $peers) . '.';
                }
                if (!empty($dataset['initial_code_conflicts'][$tmId]['level_mismatches'])) {
                    $conflicts[] = 'Bảng nguồn trường của ngành HNV có sai khác nhãn trình độ / đầu mã.';
                }

                $evidence = [
                    'training_major_id' => $tmId,
                    'training_major_name' => $tmName,
                    'existing_major_id' => $emId,
                    'existing_major_code' => $emCode,
                    'existing_major_name' => $emName,
                    'source_url' => $sUrl !== '' ? $sUrl : null,
                    'source_dom_code' => $domCode !== '' ? $domCode : null,
                    'source_major_code_candidate' => $cand !== '' ? $cand : null,
                    'major_code_basis' => $m['major_code_basis'] ?? null,
                    'observed_major_codes' => $obsCodes,
                    'school_listing_count_extracted' => $extractedCount,
                    'candidate_code_matches_starter' => ($cand !== '' && $cand === $emCode),
                ];
                $evidenceJson = json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $conflictText = $conflicts ? implode(' | ', $conflicts) : null;
                $hasConflict = $conflicts !== [] ? 1 : 0;

                $mKey = $tmId . '|' . $emId;
                $cur = $existingMappings[$mKey] ?? null;
                if (!$cur) {
                    $counters['training_major_mappings']['inserted']++;
                    $pendingMappings++;
                    if (!$dryRun) {
                        $insMap->execute([$tmId, $emId, $basis, 'pending_review', $note, $evidenceJson, $conflictText, $hasConflict]);
                    }
                } else {
                    if ($cur['mapping_status'] === 'pending_review') {
                        $pendingMappings++;
                    }
                    if (in_array((string)$cur['mapping_status'], ['approved', 'confirmed', 'rejected'], true)) {
                        $counters['training_major_mappings']['preserved_editorial']++;
                        $counters['training_major_mappings']['skipped']++;
                    } elseif ($cur['match_basis'] === $basis
                        && (string)($cur['evidence_json'] ?? '') === $evidenceJson
                        && (string)($cur['conflict_notes'] ?? '') === (string)$conflictText
                        && (int)($cur['has_conflict'] ?? 0) === $hasConflict
                    ) {
                        $counters['training_major_mappings']['skipped']++;
                    } else {
                        $counters['training_major_mappings']['updated']++;
                        if (!$dryRun) {
                            $updMap->execute([$basis, $note, $evidenceJson, $conflictText, $hasConflict, (int)$cur['id']]);
                        }
                    }
                }
            }
        }

        $totals = [
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'preserved_editorial' => 0,
            'needs_review' => $openReviewMajors,
            'pending_mappings' => $pendingMappings,
        ];
        foreach ($counters as $tblStats) {
            $totals['inserted'] += (int)($tblStats['inserted'] ?? 0);
            $totals['updated'] += (int)($tblStats['updated'] ?? 0);
            $totals['skipped'] += (int)($tblStats['skipped'] ?? 0);
            $totals['preserved_editorial'] += (int)($tblStats['preserved_editorial'] ?? 0);
        }

        $sourceWarningCounts = [];
        foreach ($dataset['review_queue'] as $rq) {
            foreach ($rq['source_warnings'] as $iss) {
                $sourceWarningCounts[$iss] = ($sourceWarningCounts[$iss] ?? 0) + 1;
            }
        }
        arsort($sourceWarningCounts);

        $report = [
            'mode' => $dryRun ? 'dry_run' : 'import',
            'source_name' => $dataset['source_name'],
            'source_url' => $dataset['source_url'],
            'schema_version' => $dataset['schema_version'],
            'retrieved_at' => $dataset['retrieved_at'],
            'dataset_counts' => [
                'fields' => count($dataset['groups']),
                'subgroups' => count($dataset['subgroups']),
                'majors' => count($dataset['majors']),
                'institution_source_records' => count($dataset['institution_source_records']),
                'major_institution_links' => count($dataset['links']),
                'review_queue' => count($dataset['review_queue']),
                'majors_with_initial_source_warnings' => count(array_filter($dataset['review_queue'], static fn($r) => !empty($r['source_warnings']))),
                'majors_with_open_review_issues' => $openReviewMajors,
            ],
            'tables' => $counters,
            'totals' => $totals,
            'missing_data_breakdown' => $sourceWarningCounts,
            'warnings' => $dataset['warnings'],
            'started_at' => $startedAt,
            'finished_at' => gmdate('Y-m-d H:i:s'),
        ];

        if (!$dryRun) {
            $runStmt = $db->prepare('INSERT INTO dataset_import_runs(source_name,source_version,source_retrieved_at,mode,status,summary_json,initiated_by,started_at,finished_at) VALUES(?,?,?,?,?,?,?,?,?)');
            $runStmt->execute([
                $dataset['source_name'],
                $dataset['schema_version'],
                $dataset['retrieved_at'],
                'import',
                'completed',
                json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $initiatedBy,
                $startedAt,
                $report['finished_at'],
            ]);
            $db->commit();
            $startedTx = false;
        }

        return $report;
    } catch (Throwable $e) {
        if ($startedTx && $db->inTransaction()) {
            $db->rollBack();
        }
        if (!$dryRun) {
            try {
                $failStmt = $db->prepare('INSERT INTO dataset_import_runs(source_name,source_version,source_retrieved_at,mode,status,summary_json,initiated_by,started_at,finished_at) VALUES(?,?,?,?,?,?,?,?,?)');
                $failStmt->execute([
                    $dataset['source_name'] ?? (basename($sourcePath) ?: 'jobsimu_hnv_data'),
                    $dataset['schema_version'] ?? null,
                    $dataset['retrieved_at'] ?? null,
                    'import',
                    'failed',
                    json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
                    $initiatedBy,
                    $startedAt,
                    gmdate('Y-m-d H:i:s'),
                ]);
            } catch (Throwable $ignored) {
            }
        }
        throw $e;
    }
}
