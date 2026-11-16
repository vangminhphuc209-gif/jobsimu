<?php
declare(strict_types=1);

/**
 * Public Training Majors Catalog & Detail Explorer.
 * Displays strictly verified/approved JobSimu content separately from raw source data,
 * hides technical enum keys / null / empty-array literals, and preserves exact source years.
 */

require_once __DIR__ . '/training-majors.php';
training_majors_migrate();

$db = db();
$majorId = is_string($_GET['id'] ?? null) ? trim($_GET['id']) : '';

if ($majorId !== '') {
    $major = hnv_get_major_detail($db, $majorId);
    if (!$major) {
        http_response_code(404);
        $pageTitle = 'Không tìm thấy ngành đào tạo';
        require __DIR__ . '/header.php';
        ?>
        <div class="container" style="padding:48px 16px">
            <nav class="breadcrumbs" aria-label="Đường dẫn">
                <a href="<?= url('index.php') ?>">Trang chủ</a>
                <span class="separator">/</span>
                <a href="<?= url('index.php?page=majors') ?>">Ngành học</a>
                <span class="separator">/</span>
                <span>Không tìm thấy</span>
            </nav>
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true">🔍</span>
                <h2>Không tìm thấy ngành đào tạo</h2>
                <p>Mã ngành yêu cầu không có trong danh mục hiện tại hoặc chưa được cập nhật dữ liệu.</p>
                <a class="btn btn-primary" href="<?= url('index.php?page=majors') ?>">← Quay lại Danh mục Ngành học</a>
            </div>
        </div>
        <?php
        require __DIR__ . '/footer.php';
        exit;
    }

    $selectedYear = is_string($_GET['year'] ?? null) ? trim($_GET['year']) : '';
    $allLinks = $major['links'];
    $yearCounts = [];
    foreach ($allLinks as $lnk) {
        $yKey = $lnk['source_news_year'] !== null ? (string)(int)$lnk['source_news_year'] : 'unknown';
        $yearCounts[$yKey] = ($yearCounts[$yKey] ?? 0) + 1;
    }
    krsort($yearCounts);

    $filteredLinks = $allLinks;
    if ($selectedYear !== '' && ctype_digit($selectedYear)) {
        $yearInt = (int)$selectedYear;
        $filteredLinks = array_values(array_filter($allLinks, static function (array $lnk) use ($yearInt): bool {
            return $lnk['source_news_year'] !== null && (int)$lnk['source_news_year'] === $yearInt;
        }));
    } elseif ($selectedYear === 'unknown') {
        $filteredLinks = array_values(array_filter($allLinks, static function (array $lnk): bool {
            return $lnk['source_news_year'] === null;
        }));
    } else {
        $selectedYear = '';
    }

    $observedCodes = hnv_decode_json_list($major['observed_major_codes_json'] ?? '[]');
    $topicsPresent = hnv_decode_json_list($major['content_topics_json'] ?? '[]');
    $openIssues = array_values(array_filter($major['issues'] ?? [], static fn(array $iss): bool => ($iss['status'] ?? '') === 'open'));

    $topicLabels = hnv_topic_labels();
    $issueLabels = hnv_review_issue_labels();
    $sourceMeta = hnv_source_access_meta((string)$major['detail_status'], !empty($major['has_article_body']));
    $descStatus = (string)($major['description_status'] ?? 'not_edited');
    $descMeta = hnv_description_status_meta($descStatus);
    $codeStatus = (string)($major['code_verification_status'] ?? 'unverified');
    $codeMeta = hnv_code_verification_meta($codeStatus);
    $hasApprovedDesc = ($descStatus === 'approved' && trim((string)($major['editorial_description'] ?? '')) !== '');
    $hasVerifiedCode = ($codeStatus === 'verified' && trim((string)($major['official_major_code'] ?? '')) !== '');

    $sourceUrl = safe_external_url($major['source_url'] ?? '');
    $finalUrl = safe_external_url($major['final_url'] ?? '');
    $catalogueUrl = safe_external_url($major['catalogue_url'] ?? '');
    $codeEvidenceUrl = safe_external_url($major['code_evidence_url'] ?? '');

    $distinctSourceUrls = [];
    foreach ($allLinks as $lnk) {
        $distinctSourceUrls[$lnk['institution_source_id']] = true;
    }

    $pageTitle = hnv_to_nfc((string)$major['name']) . ' · Ngành đào tạo';
    require __DIR__ . '/header.php';
    ?>
    <div class="majors-catalog-container">
        <!-- Breadcrumb Navigation -->
        <nav class="breadcrumbs" aria-label="Đường dẫn">
            <a href="<?= url('index.php') ?>">Trang chủ</a>
            <span class="separator">/</span>
            <a href="<?= url('index.php?page=majors') ?>">Ngành học</a>
            <span class="separator">/</span>
            <a href="<?= url('index.php?page=majors&field=' . rawurlencode((string)$major['field_id'])) ?>"><?= e($major['field_name']) ?></a>
            <span class="separator">/</span>
            <span><?= e(hnv_to_nfc((string)$major['name'])) ?></span>
        </nav>

        <!-- Page Heading -->
        <div class="page-heading">
            <span class="eyebrow" style="margin-bottom:8px">Ngành đào tạo</span>
            <h1><?= e(hnv_to_nfc((string)$major['name'])) ?></h1>
            <p class="lead">
                Lĩnh vực: <strong><a class="text-brand-600 hover:underline" href="<?= url('index.php?page=majors&field=' . rawurlencode((string)$major['field_id'])) ?>"><?= e($major['field_name']) ?></a></strong>
                · Nhóm ngành: <strong><a class="text-brand-600 hover:underline" href="<?= url('index.php?page=majors&field=' . rawurlencode((string)$major['field_id']) . '&subgroup=' . rawurlencode((string)$major['subgroup_id'])) ?>"><?= e($major['subgroup_name']) ?></a></strong>
            </p>
            <?php if ($hasVerifiedCode): ?>
                <div style="margin-top:10px">
                    <span class="status-pill status-pill-ok">Mã ngành chính thức (Bộ GD&amp;ĐT): <?= e($major['official_major_code']) ?></span>
                </div>
            <?php elseif (!empty($major['source_major_code_candidate'])): ?>
                <div style="margin-top:10px">
                    <span class="status-pill status-pill-info">Mã tham khảo nguồn: <?= e($major['source_major_code_candidate']) ?> (chưa xác minh độc lập)</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Section 1: Phân tích hướng nghiệp JobSimu -->
        <section class="card" style="margin-bottom:24px">
            <h2>Giới thiệu ngành học</h2>
            <?php if ($hasApprovedDesc): ?>
                <div style="font-size:1.05rem;line-height:1.75;color:var(--text-primary);margin-top:14px">
                    <?= nl2br(e(trim((string)$major['editorial_description']))) ?>
                </div>
                <?php if (!empty($major['editorial_updated_at'])): ?>
                    <p class="muted" style="margin-top:16px;font-size:0.8125rem">
                        Biên tập bởi JobSimu · Cập nhật ngày <?= e(date('d/m/Y', strtotime($major['editorial_updated_at']))) ?>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <div class="source-disclosure-box" style="margin:14px 0 0">
                    <p style="margin:0">
                        Nội dung phân tích chi tiết cho ngành <strong><?= e(hnv_to_nfc((string)$major['name'])) ?></strong> đang được ban biên tập hoàn thiện dựa trên chuẩn chương trình đào tạo. Dưới đây là các dữ liệu tuyển sinh tham khảo lịch sử đã ghi nhận.
                    </p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Section 2: Danh sách cơ sở đào tạo & liên kết tuyển sinh -->
        <section class="card" style="margin-bottom:24px">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px">
                <div>
                    <h2 style="margin:0">Cơ sở đào tạo &amp; Tuyển sinh tham khảo</h2>
                    <p class="muted" style="margin:4px 0 0;font-size:0.875rem">
                        Ghi nhận từ <?= count($allLinks) ?> thông báo tuyển sinh lịch sử (<?= count($distinctSourceUrls) ?> trang nguồn)
                    </p>
                </div>
            </div>

            <!-- Lọc theo năm -->
            <?php if (count($yearCounts) > 1): ?>
                <div style="margin:16px 0">
                    <span style="font-size:0.8125rem;font-weight:700;color:var(--text-secondary);margin-right:8px">Lọc theo năm tin:</span>
                    <div class="hnv-year-pills" style="display:inline-flex">
                        <a href="<?= url('index.php?page=majors&id=' . rawurlencode($majorId)) ?>" class="hnv-year-pill <?= $selectedYear === '' ? 'active' : '' ?>">Tất cả (<?= count($allLinks) ?>)</a>
                        <?php foreach ($yearCounts as $y => $c): ?>
                            <a href="<?= url('index.php?page=majors&id=' . rawurlencode($majorId) . '&year=' . rawurlencode((string)$y)) ?>" class="hnv-year-pill <?= (string)$selectedYear === (string)$y ? 'active' : '' ?>">
                                <?= $y === 'unknown' ? 'Chưa rõ năm' : 'Năm ' . e($y) ?> (<?= $c ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($filteredLinks): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Cơ sở đào tạo</th>
                                <th>Hệ đào tạo</th>
                                <th>Năm tin</th>
                                <th>Mã / Tổ hợp quan sát</th>
                                <th>Liên kết</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredLinks as $lnk):
                                $instUrl = safe_external_url($lnk['institution_source_url'] ?? '');
                                $evUrl = safe_external_url($lnk['evidence_url'] ?? '');
                                $linkMajCodes = hnv_decode_json_list($lnk['observed_major_codes_json'] ?? '[]');
                                $linkCombCodes = hnv_decode_json_list($lnk['observed_subject_combination_codes_json'] ?? '[]');
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= e($lnk['source_label'] ?: 'Cơ sở đào tạo') ?></strong>
                                        <?php if (!empty($lnk['link_check_note'])): ?>
                                            <div class="muted" style="font-size:0.75rem"><?= e($lnk['link_check_note']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($lnk['level_label'] ?: 'Đại học') ?></td>
                                    <td>
                                        <?= $lnk['source_news_year'] !== null ? '<span class="tag">Năm ' . (int)$lnk['source_news_year'] . '</span>' : '<span class="muted">Chưa rõ</span>' ?>
                                    </td>
                                    <td>
                                        <?php if ($linkMajCodes): ?>
                                            <div style="font-size:0.8125rem">Mã: <?= e(implode(', ', $linkMajCodes)) ?></div>
                                        <?php endif; ?>
                                        <?php if ($linkCombCodes): ?>
                                            <div class="muted" style="font-size:0.75rem">Tổ hợp: <?= e(implode(', ', $linkCombCodes)) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                                            <?php if ($instUrl !== ''): ?>
                                                <a class="btn btn-outline" style="padding:4px 8px;font-size:0.8125rem" href="<?= e($instUrl) ?>" target="_blank" rel="noopener noreferrer">Trang trường ↗</a>
                                            <?php endif; ?>
                                            <?php if ($evUrl !== ''): ?>
                                                <a class="btn btn-outline" style="padding:4px 8px;font-size:0.8125rem" href="<?= e($evUrl) ?>" target="_blank" rel="noopener noreferrer">Nguồn ngành ↗</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="muted">Chưa có liên kết tuyển sinh tham khảo nào cho tiêu chí chọn này.</p>
            <?php endif; ?>
        </section>

        <!-- Section 3: Nguồn & Độ tin cậy (Disclosure) -->
        <section class="card" style="margin-bottom:24px">
            <h2 style="font-size:1.15rem;margin-bottom:12px">Nguồn &amp; Độ tin cậy dữ liệu</h2>
            <div class="source-disclosure-box" style="margin:0 0 16px">
                <p style="margin:0 0 8px">
                    <strong>Tuyên bố minh bạch:</strong> Thông tin tuyển sinh, mã ngành tham khảo và danh sách trường được tổng hợp từ dữ liệu mở thứ cấp giai đoạn 2019–2025.
                </p>
                <p style="margin:0">
                    Số liệu và liên kết trên trang này dùng cho mục đích định hướng và tìm hiểu ngành học; <strong>không thay thế đề án tuyển sinh chính thức</strong> được trường công bố cho mùa tuyển sinh hiện hành.
                </p>
            </div>
            <?php if ($sourceUrl !== ''): ?>
                <p style="font-size:0.875rem;margin:0">
                    Trang nguồn danh mục gốc: <a class="text-brand-600 hover:underline" href="<?= e($sourceUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($major['source_label'] ?: $sourceUrl) ?> ↗</a>
                </p>
            <?php endif; ?>
        </section>

        <div style="text-align:center;margin-top:32px">
            <a class="btn btn-outline" href="<?= url('index.php?page=majors') ?>">← Quay lại Danh mục Ngành học</a>
        </div>
    </div>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

// =========================================================================
// CATALOG LIST MODE
// =========================================================================

$q = is_string($_GET['q'] ?? null) ? trim(mb_substr($_GET['q'], 0, 120)) : (is_string($_GET['search'] ?? null) ? trim(mb_substr($_GET['search'], 0, 120)) : '');
$fieldId = is_string($_GET['field'] ?? null) ? trim(mb_substr($_GET['field'], 0, 32)) : '';
$subgroupId = is_string($_GET['subgroup'] ?? null) ? trim(mb_substr($_GET['subgroup'], 0, 32)) : '';
$statusFilter = is_string($_GET['status'] ?? null) ? trim($_GET['status']) : 'all';
$allowedStatuses = ['all', 'ready', 'description_approved', 'source_ready', 'missing_description', 'code_verified', 'has_school_links', 'missing_detail', 'needs_review'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

$sort = is_string($_GET['sort'] ?? null) ? trim($_GET['sort']) : 'az';
$allowedSorts = ['az', 'za', 'popular'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'az';
}

$pageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 24;

$stats = hnv_get_catalog_stats($db);
$fields = hnv_get_fields_with_counts($db);
$allSubgroups = hnv_get_subgroups_with_counts($db);

$fieldsById = [];
foreach ($fields as $f) {
    $fieldsById[$f['id']] = $f;
}
if ($fieldId !== '' && !isset($fieldsById[$fieldId])) {
    $fieldId = '';
}

$subgroupsById = [];
foreach ($allSubgroups as $sg) {
    $subgroupsById[$sg['id']] = $sg;
}
if ($subgroupId !== '' && !isset($subgroupsById[$subgroupId])) {
    $subgroupId = '';
}
if ($fieldId !== '' && $subgroupId !== '' && ($subgroupsById[$subgroupId]['field_id'] ?? '') !== $fieldId) {
    $subgroupId = '';
}

$visibleSubgroups = $fieldId !== ''
    ? array_values(array_filter($allSubgroups, static fn(array $sg): bool => $sg['field_id'] === $fieldId))
    : $allSubgroups;

$result = hnv_search_majors($db, [
    'q' => $q,
    'field' => $fieldId,
    'subgroup' => $subgroupId,
    'status' => $statusFilter,
    'sort' => $sort,
], $pageNum, $perPage);

$majors = $result['items'];
$total = $result['total'];
$currentPage = $result['page'];
$totalPages = $result['total_pages'];

// Helper to construct query URLs while resetting pagination if filters change
$buildCatalogUrl = static function (array $overrides = []) use ($q, $fieldId, $subgroupId, $statusFilter, $sort): string {
    $newQ = array_key_exists('q', $overrides) ? $overrides['q'] : $q;
    $newField = array_key_exists('field', $overrides) ? $overrides['field'] : $fieldId;
    $newSubgroup = array_key_exists('subgroup', $overrides) ? $overrides['subgroup'] : $subgroupId;
    $newStatus = array_key_exists('status', $overrides) ? $overrides['status'] : $statusFilter;
    $newSort = array_key_exists('sort', $overrides) ? $overrides['sort'] : $sort;
    $newPage = array_key_exists('p', $overrides) ? $overrides['p'] : 1;

    $params = ['page' => 'majors'];
    if ($newQ !== '') $params['q'] = $newQ;
    if ($newField !== '') $params['field'] = $newField;
    if ($newSubgroup !== '') $params['subgroup'] = $newSubgroup;
    if ($newStatus !== '' && $newStatus !== 'all') $params['status'] = $newStatus;
    if ($newSort !== '' && $newSort !== 'az') $params['sort'] = $newSort;
    if ((int)$newPage > 1) $params['p'] = (int)$newPage;

    return url('index.php?' . http_build_query($params));
};

$pageTitle = 'Khám phá ngành học · 358 ngành đào tạo chuẩn';
require __DIR__ . '/header.php';
?>

<div class="majors-catalog-container">
    <!-- 1. Tiêu đề “Khám phá ngành học” & 2. Một câu hướng dẫn -->
    <div class="page-heading">
        <span class="eyebrow">Danh mục đào tạo chuẩn Bộ GD&amp;ĐT</span>
        <h1>Khám phá ngành học</h1>
        <p class="lead">
            Tra cứu <?= number_format($stats['majors']) ?> ngành đào tạo đại học và cao đẳng theo lĩnh vực, nhóm ngành cùng thông tin tuyển sinh tham khảo.
        </p>
    </div>

    <!-- 3. Ô tìm kiếm & 4. Bộ lọc chính -->
    <form method="get" class="majors-search-filter-card" id="majorsFilterForm">
        <input type="hidden" name="page" value="majors">
        <div class="filter-row">
            <!-- Ô tìm kiếm theo tên hoặc mã ngành -->
            <div class="form-field">
                <label for="majorSearch">Tìm kiếm ngành học</label>
                <input id="majorSearch" class="input-clean" type="search" name="q" value="<?= e($q) ?>" maxlength="120" placeholder="Nhập tên ngành hoặc mã ngành (ví dụ: công nghệ, 7480101)...">
            </div>

            <!-- Lĩnh vực -->
            <div class="form-field">
                <label for="majorField">Lĩnh vực (<?= count($fields) ?>)</label>
                <select id="majorField" name="field" class="select-clean" onchange="document.getElementById('majorSubgroup').value=''; this.form.submit();">
                    <option value="">Tất cả lĩnh vực</option>
                    <?php foreach ($fields as $f): ?>
                        <option value="<?= e($f['id']) ?>" <?= $fieldId === (string)$f['id'] ? 'selected' : '' ?>>
                            <?= e($f['name']) ?> (<?= (int)$f['major_count'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Nhóm ngành -->
            <div class="form-field">
                <label for="majorSubgroup">Nhóm ngành (<?= count($visibleSubgroups) ?>)</label>
                <select id="majorSubgroup" name="subgroup" class="select-clean" onchange="this.form.submit();">
                    <option value="">Tất cả nhóm ngành</option>
                    <?php foreach ($visibleSubgroups as $sg): ?>
                        <option value="<?= e($sg['id']) ?>" <?= $subgroupId === (string)$sg['id'] ? 'selected' : '' ?>>
                            <?= e($sg['name']) ?> (<?= (int)$sg['major_count'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Thứ tự sắp xếp -->
            <div class="form-field">
                <label for="majorSort">Sắp xếp theo</label>
                <select id="majorSort" name="sort" class="select-clean" onchange="this.form.submit();">
                    <option value="az" <?= $sort === 'az' ? 'selected' : '' ?>>Tên ngành (A → Z)</option>
                    <option value="za" <?= $sort === 'za' ? 'selected' : '' ?>>Tên ngành (Z → A)</option>
                    <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Nhiều cơ sở đào tạo nhất</option>
                </select>
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;margin-top:16px">
            <button type="submit" class="btn btn-primary" style="padding:8px 20px">Tìm kiếm &amp; Lọc</button>
            <?php if ($q !== '' || $fieldId !== '' || $subgroupId !== '' || $sort !== 'az'): ?>
                <a class="btn btn-outline" href="<?= url('index.php?page=majors') ?>" style="padding:8px 16px">Xóa bộ lọc</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- 5. Số kết quả, bộ lọc đang chọn, sắp xếp -->
    <div class="active-filters-bar">
        <div class="results-count-text">
            Tìm thấy <strong><?= number_format($total) ?></strong> ngành đào tạo phù hợp
            <?php if ($totalPages > 1): ?>
                · Trang <?= $currentPage ?> / <?= $totalPages ?>
            <?php endif; ?>
        </div>

        <!-- Active filter chips -->
        <div class="active-chips-wrap">
            <?php if ($q !== ''): ?>
                <a href="<?= $buildCatalogUrl(['q' => '', 'p' => 1]) ?>" class="filter-chip" title="Bỏ lọc từ khóa">
                    <span>Từ khóa: &ldquo;<?= e($q) ?>&rdquo;</span>
                    <span class="filter-chip-remove">✕</span>
                </a>
            <?php endif; ?>

            <?php if ($fieldId !== '' && isset($fieldsById[$fieldId])): ?>
                <a href="<?= $buildCatalogUrl(['field' => '', 'subgroup' => '', 'p' => 1]) ?>" class="filter-chip" title="Bỏ lọc lĩnh vực">
                    <span>Lĩnh vực: <?= e($fieldsById[$fieldId]['name']) ?></span>
                    <span class="filter-chip-remove">✕</span>
                </a>
            <?php endif; ?>

            <?php if ($subgroupId !== '' && isset($subgroupsById[$subgroupId])): ?>
                <a href="<?= $buildCatalogUrl(['subgroup' => '', 'p' => 1]) ?>" class="filter-chip" title="Bỏ lọc nhóm ngành">
                    <span>Nhóm: <?= e($subgroupsById[$subgroupId]['name']) ?></span>
                    <span class="filter-chip-remove">✕</span>
                </a>
            <?php endif; ?>

            <?php if ($sort !== 'az'): ?>
                <a href="<?= $buildCatalogUrl(['sort' => 'az', 'p' => 1]) ?>" class="filter-chip" title="Đặt lại thứ tự mặc định">
                    <span><?= $sort === 'za' ? 'Z → A' : 'Nhiều trường nhất' ?></span>
                    <span class="filter-chip-remove">✕</span>
                </a>
            <?php endif; ?>

            <?php if ($q !== '' || $fieldId !== '' || $subgroupId !== '' || $sort !== 'az'): ?>
                <a href="<?= url('index.php?page=majors') ?>" class="clear-all-filters-btn">Xóa tất cả</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 6. Danh sách ngành -->
    <?php if (!$majors): ?>
        <div class="empty-state">
            <span class="empty-state-icon" aria-hidden="true">🔎</span>
            <h2>Không tìm thấy ngành đào tạo phù hợp</h2>
            <p>Hãy thử tìm bằng từ khóa ngắn hơn (có hoặc không dấu), hoặc điều chỉnh lại các lựa chọn lĩnh vực và nhóm ngành.</p>
            <a class="btn btn-primary" href="<?= url('index.php?page=majors') ?>">Xem toàn bộ <?= number_format($stats['majors']) ?> ngành học</a>
        </div>
    <?php else: ?>
        <div class="majors-grid">
            <?php foreach ($majors as $m):
                $descStatus = (string)($m['description_status'] ?? 'not_edited');
                $codeStatus = (string)($m['code_verification_status'] ?? 'unverified');
                $hasApprovedDesc = ($descStatus === 'approved' && trim((string)($m['editorial_description'] ?? '')) !== '');
                $hasVerifiedCode = ($codeStatus === 'verified' && trim((string)($m['official_major_code'] ?? '')) !== '');
                $linkCount = (int)$m['link_count'];
                $srcUrlCount = (int)$m['source_url_count'];
                $detailHref = url('index.php?page=majors&id=' . rawurlencode((string)$m['id']));
            ?>
                <article class="major-card-clean">
                    <div>
                        <div class="major-card-field">
                            <?= e($m['field_name']) ?> · <?= e($m['subgroup_name']) ?>
                        </div>
                        <h3>
                            <a href="<?= $detailHref ?>"><?= e(hnv_to_nfc((string)$m['name'])) ?></a>
                        </h3>
                        <?php if ($hasVerifiedCode): ?>
                            <span class="status-pill status-pill-ok" style="font-size:0.75rem;margin-bottom:8px">
                                Mã: <?= e($m['official_major_code']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($hasApprovedDesc): ?>
                        <p class="major-card-desc">
                            <?= e(mb_substr(trim((string)$m['editorial_description']), 0, 140)) ?>…
                        </p>
                    <?php else: ?>
                        <!-- Blank space instead of repetitive fake placeholder -->
                        <div style="flex-grow:1"></div>
                    <?php endif; ?>

                    <div class="major-card-footer-clean">
                        <div class="major-card-stat">
                            <?php if ($linkCount > 0): ?>
                                Dữ liệu từ <?= $linkCount ?> nguồn tin <?= $m['min_year'] && $m['max_year'] ? '(' . ((int)$m['min_year'] === (int)$m['max_year'] ? (int)$m['min_year'] : (int)$m['min_year'] . '–' . (int)$m['max_year']) . ')' : '' ?>
                            <?php else: ?>
                                Chưa có dữ liệu nguồn trường
                            <?php endif; ?>
                        </div>
                        <a class="major-card-link" href="<?= $detailHref ?>">Xem ngành →</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- Phân trang -->
        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Phân trang danh mục ngành học">
                <?php if ($currentPage > 1): ?>
                    <a class="btn btn-outline" href="<?= $buildCatalogUrl(['p' => $currentPage - 1]) ?>">← Trang trước</a>
                <?php endif; ?>

                <?php
                $window = 2;
                $startPage = max(1, $currentPage - $window);
                $endPage = min($totalPages, $currentPage + $window);
                if ($startPage > 1) {
                    echo '<a class="btn btn-outline" href="' . $buildCatalogUrl(['p' => 1]) . '">1</a>';
                    if ($startPage > 2) {
                        echo '<span class="muted" style="padding:0 4px">…</span>';
                    }
                }
                for ($p = $startPage; $p <= $endPage; $p++) {
                    $isCur = ($p === $currentPage);
                    echo '<a class="btn ' . ($isCur ? 'btn-primary' : 'btn-outline') . '" href="' . $buildCatalogUrl(['p' => $p]) . '" ' . ($isCur ? 'aria-current="page"' : '') . '>' . $p . '</a>';
                }
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<span class="muted" style="padding:0 4px">…</span>';
                    }
                    echo '<a class="btn btn-outline" href="' . $buildCatalogUrl(['p' => $totalPages]) . '">' . $totalPages . '</a>';
                }
                ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a class="btn btn-outline" href="<?= $buildCatalogUrl(['p' => $currentPage + 1]) ?>">Trang sau →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Lưu ý nguồn dữ liệu trung thực -->
    <div class="source-disclosure-box">
        <strong>Lưu ý nguồn dữ liệu:</strong> Danh mục ngành học và các liên kết thông tin tuyển sinh được tổng hợp từ dữ liệu mở và nguồn các cơ sở đào tạo (giai đoạn 2019–2025). Thông tin mang tính định hướng học tập và không thay thế đề án tuyển sinh chính thức được các trường công bố cho mùa tuyển sinh hiện hành.
    </div>
</div>

<?php
require __DIR__ . '/footer.php';
