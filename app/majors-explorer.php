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
        <div class="container" style="padding:36px 0">
            <a class="btn btn-outline" href="<?= url('index.php?page=majors') ?>">← Quay lại Danh mục Ngành đào tạo</a>
            <div class="card empty" style="margin-top:20px">
                <h1>Không tìm thấy ngành đào tạo</h1>
                <p>Mã ngành yêu cầu không có trong danh mục hiện tại hoặc chưa được nhập dữ liệu.</p>
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
    <section class="page-head">
        <div class="container">
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:14px">
                <a class="btn btn-outline" href="<?= url('index.php?page=majors') ?>">← Danh mục ngành</a>
                <a class="btn btn-outline" href="<?= url('index.php?page=majors&field=' . rawurlencode((string)$major['field_id'])) ?>">Lĩnh vực: <?= e($major['field_name']) ?></a>
                <a class="btn btn-outline" href="<?= url('index.php?page=majors&field=' . rawurlencode((string)$major['field_id']) . '&subgroup=' . rawurlencode((string)$major['subgroup_id'])) ?>">Nhóm: <?= e($major['subgroup_name']) ?></a>
            </div>
            <span class="eyebrow">Ngành đào tạo (chương trình học) · Không phải chức danh nghề nghiệp</span>
            <h1 style="margin-top:12px"><?= e(hnv_to_nfc((string)$major['name'])) ?></h1>
            <?php if (!empty($major['source_label']) && hnv_to_nfc((string)$major['source_label']) !== hnv_to_nfc((string)$major['name'])): ?>
                <p class="muted" style="margin-top:8px">Tên hiển thị tại nguồn tham khảo: <strong><?= e($major['source_label']) ?></strong></p>
            <?php endif; ?>
            <?php if (!empty($major['normalization_note'])): ?>
                <p class="muted" style="margin-top:6px"><small>Ghi chú chuẩn hóa: <?= e($major['normalization_note']) ?></small></p>
            <?php endif; ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px">
                <span class="status-pill status-pill-<?= e($sourceMeta['tone']) ?>"><?= e($sourceMeta['label']) ?></span>
                <span class="status-pill status-pill-<?= e($descMeta['tone']) ?>"><?= e($descMeta['label']) ?></span>
                <?php if ($hasVerifiedCode): ?>
                    <span class="status-pill status-pill-ok">Mã ngành chính thức đã xác minh: <?= e($major['official_major_code']) ?></span>
                <?php elseif ($codeStatus === 'conflict'): ?>
                    <span class="status-pill status-pill-error"><?= e($codeMeta['label']) ?></span>
                <?php elseif (!empty($major['source_major_code_candidate'])): ?>
                    <span class="status-pill status-pill-info">Mã tham khảo (chưa xác minh): <?= e($major['source_major_code_candidate']) ?></span>
                <?php else: ?>
                    <span class="status-pill status-pill-warn">Chưa có mã ngành chính thức được xác minh</span>
                <?php endif; ?>
                <span class="status-pill status-pill-info"><?= count($allLinks) ?> liên kết tin tuyển sinh (<?= count($distinctSourceUrls) ?> trang nguồn trường)</span>
            </div>
        </div>
    </section>

    <div class="container" style="padding-top:24px;padding-bottom:36px">
        <div class="card">
            <h2>Thông tin phân loại &amp; xác minh độc lập</h2>
            <div class="hnv-meta-grid">
                <div class="hnv-meta-item">
                    <small>Lĩnh vực đào tạo</small>
                    <strong><a class="text-brand-600 hover:underline" href="<?= url('index.php?page=majors&field=' . rawurlencode((string)$major['field_id'])) ?>"><?= e($major['field_name']) ?></a></strong>
                    <div class="muted"><small>Phân nhóm nguồn tham khảo</small></div>
                </div>
                <div class="hnv-meta-item">
                    <small>Nhóm ngành</small>
                    <strong><a class="text-brand-600 hover:underline" href="<?= url('index.php?page=majors&field=' . rawurlencode((string)$major['field_id']) . '&subgroup=' . rawurlencode((string)$major['subgroup_id'])) ?>"><?= e($major['subgroup_name']) ?></a></strong>
                    <div class="muted"><small>Thuộc lĩnh vực <?= e($major['field_name']) ?></small></div>
                </div>
                <div class="hnv-meta-item">
                    <small>Mã ngành chính thức (Bộ GD&amp;ĐT / cơ sở thẩm quyền)</small>
                    <?php if ($hasVerifiedCode): ?>
                        <strong><?= e($major['official_major_code']) ?></strong>
                        <div class="muted" style="margin-top:4px"><small>
                            <?= e($codeMeta['label']) ?>
                            <?php if (!empty($major['code_effective_year']) || !empty($major['code_effective_scope'])): ?>
                                · Phạm vi: <?= e(trim(($major['code_effective_scope'] ?? '') . (!empty($major['code_effective_year']) ? ' (' . (int)$major['code_effective_year'] . ')' : ''))) ?>
                            <?php endif; ?>
                            <?php if (!empty($major['code_checked_at'])): ?>
                                · Kiểm tra ngày <?= e(hnv_format_vn_datetime($major['code_checked_at'])) ?>
                            <?php endif; ?>
                        </small></div>
                        <?php if ($codeEvidenceUrl !== ''): ?>
                            <div style="margin-top:4px"><small><a class="text-brand-600 hover:underline" href="<?= e($codeEvidenceUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($major['code_evidence_title'] ?: 'Mở tài liệu chứng minh ↗') ?></a></small></div>
                        <?php endif; ?>
                    <?php elseif ($codeStatus === 'conflict'): ?>
                        <strong>Chưa chốt mã chính thức — đang có xung đột</strong>
                        <div class="muted"><small>Dữ liệu trích xuất có nhiều mã hoặc trùng mã với ngành khác nên hệ thống tạm giữ trạng thái cần đối chiếu.</small></div>
                    <?php else: ?>
                        <strong>Chưa được xác minh</strong>
                        <div class="muted"><small>Hệ thống không tự chuyển mã trích xuất từ nguồn thành mã ngành chính thức.</small></div>
                    <?php endif; ?>
                </div>
                <div class="hnv-meta-item">
                    <small>Mã tham khảo trích từ nguồn</small>
                    <?php if (!empty($major['source_major_code_candidate'])): ?>
                        <strong><?= e($major['source_major_code_candidate']) ?></strong>
                        <div class="muted"><small>Cơ sở trích xuất: <?= e(hnv_code_basis_label($major['major_code_basis'] ?? null)) ?> · Cần đối chiếu quy định hiện hành</small></div>
                    <?php else: ?>
                        <span>Chưa trích được mã đơn nhất</span>
                        <div class="muted"><small>Trang nguồn chưa ghi nhận một mã duy nhất cho ngành này.</small></div>
                    <?php endif; ?>
                </div>
                <div class="hnv-meta-item">
                    <small>Các mã từng xuất hiện trong bảng trường tại nguồn</small>
                    <?php if ($observedCodes): ?>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px">
                            <?php foreach ($observedCodes as $oc): ?>
                                <span class="tag"><?= e($oc) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span class="muted">Chưa ghi nhận mã nào trong bảng trường</span>
                    <?php endif; ?>
                </div>
                <div class="hnv-meta-item">
                    <small>Khả năng truy cập nguồn &amp; thời điểm thu thập</small>
                    <div><strong><?= e($sourceMeta['label']) ?></strong></div>
                    <div class="muted" style="margin-top:4px"><small>Thu thập nguồn tham khảo lúc <?= e(hnv_format_utc_datetime($major['retrieved_at'])) ?></small></div>
                </div>
            </div>
            <p class="muted" style="margin-top:14px">
                <small>Nguyên tắc trung thực: Ngành đào tạo là chương trình học tại cơ sở giáo dục, không tự đồng nhất với một chức danh nghề nghiệp hay điểm phù hợp Holland / MBTI / EQ. Các thông tin học phí, điểm chuẩn và chỉ tiêu không được điền thay khi thiếu.</small>
            </p>
        </div>

        <div class="card" style="margin-top:20px">
            <h2>Mô tả ngành đào tạo (Nội dung biên tập JobSimu)</h2>
            <?php if ($hasApprovedDesc): ?>
                <div style="margin-bottom:10px">
                    <span class="status-pill status-pill-ok">Đã duyệt công khai trên JobSimu</span>
                    <?php if (!empty($major['description_updated_at'])): ?>
                        <small class="muted" style="margin-left:8px">Cập nhật: <?= e(hnv_format_vn_datetime($major['description_updated_at'])) ?></small>
                    <?php endif; ?>
                </div>
                <div style="white-space:pre-wrap;line-height:1.75"><?= e($major['editorial_description']) ?></div>
            <?php else: ?>
                <div class="alert alert-warning" role="note">
                    <?php if ($descStatus === 'draft'): ?>
                        <strong>Mô tả JobSimu đang ở trạng thái bản nháp nội bộ, chưa được duyệt công khai.</strong> Bạn có thể tham khảo trực tiếp liên kết gốc bên dưới trong khi ban biên tập hoàn tất rà soát.
                    <?php elseif ($major['detail_status'] === 'not_linked_by_source'): ?>
                        <strong>Chưa có mô tả cho ngành này.</strong> Nguồn tham khảo chỉ liệt kê tên ngành ở trang danh mục và chưa cung cấp trang chi tiết.
                    <?php elseif ($major['detail_status'] !== 'fetched'): ?>
                        <strong>Chưa có mô tả cho ngành này.</strong> Trang chi tiết tại nguồn hiện chưa đọc được đầy đủ cấu trúc (<?= e($sourceMeta['label']) ?>).
                    <?php elseif (empty($major['has_article_body'])): ?>
                        <strong>Chưa có mô tả cho ngành này.</strong> Trang nguồn có đường dẫn nhưng chưa có phần nội dung giới thiệu đọc được.
                    <?php else: ?>
                        <strong>Chưa có mô tả được biên tập và duyệt trên JobSimu.</strong> Hệ thống không sao chép toàn văn bài viết từ trang bên thứ ba và không dùng AI tạo mô tả giả khi thiếu dữ liệu biên tập. Bạn có thể đọc trực tiếp bài giới thiệu gốc tại liên kết nguồn bên dưới.
                    <?php endif; ?>
                </div>
                <?php if ($topicsPresent): ?>
                    <div style="margin-top:10px">
                        <span class="muted"><small>Chủ đề được ghi nhận có mặt tại trang nguồn tham khảo:</small></span>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
                            <?php foreach ($topicsPresent as $tCode): ?>
                                <span class="tag"><?= e($topicLabels[$tCode] ?? $tCode) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="card" style="margin-top:20px">
            <h2>Nguồn tham khảo &amp; tình trạng dữ liệu</h2>
            <div style="display:grid;gap:10px;margin-top:10px">
                <?php if ($sourceUrl !== ''): ?>
                    <div>
                        <small class="muted">Trang chi tiết ngành tại nguồn tham khảo:</small><br>
                        <a class="btn btn-primary" style="margin-top:6px" href="<?= e($sourceUrl) ?>" target="_blank" rel="noopener noreferrer">Mở trang nguồn ngành ↗</a>
                        <div class="muted hnv-url-break" style="margin-top:6px"><small><?= e($sourceUrl) ?></small></div>
                    </div>
                    <?php if ($finalUrl !== '' && $finalUrl !== $sourceUrl): ?>
                        <div class="hnv-url-break">
                            <small class="muted">Địa chỉ đích sau chuyển hướng: <a class="text-brand-600 hover:underline" href="<?= e($finalUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($finalUrl) ?> ↗</a></small>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="muted">Nguồn tham khảo chưa cung cấp trang chi tiết riêng cho ngành này.</p>
                <?php endif; ?>
                <?php if ($catalogueUrl !== ''): ?>
                    <div class="hnv-url-break">
                        <small class="muted">Trang danh mục nguồn: <a class="text-brand-600 hover:underline" href="<?= e($catalogueUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($catalogueUrl) ?> ↗</a></small>
                    </div>
                <?php endif; ?>
                <?php if ($openIssues): ?>
                    <div style="margin-top:6px">
                        <small class="muted">Các điểm dữ liệu hiện còn đang chờ kiểm tra:</small>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
                            <?php foreach ($openIssues as $iss):
                                $code = (string)$iss['issue_code'];
                                $meta = $issueLabels[$code] ?? ['label' => 'Đang kiểm tra bổ sung', 'severity' => 'info'];
                            ?>
                                <span class="status-pill status-pill-<?= e($meta['severity']) ?>"><?= e($meta['label']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php
        $approvedMappings = array_values(array_filter($major['mappings'] ?? [], static function (array $mp): bool {
            return in_array((string)($mp['mapping_status'] ?? ''), ['approved', 'confirmed'], true);
        }));
        ?>
        <?php if ($approvedMappings): ?>
            <div class="card" style="margin-top:20px">
                <h2>Liên kết đã duyệt sang danh mục ngành tuyển sinh trên JobSimu</h2>
                <p class="muted"><small>Lưu ý: việc ánh xạ bản ghi giúp tra cứu trường trong hệ thống JobSimu, không đồng nghĩa tự động xác minh mã ngành chính thức của Bộ GD&amp;ĐT nếu mục trên chưa được xác minh riêng.</small></p>
                <?php foreach ($approvedMappings as $mp): ?>
                    <div style="margin-top:10px;padding:12px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0">
                        <strong><?= e($mp['existing_name']) ?> (Mã tham chiếu danh mục cũ: <?= e($mp['existing_code']) ?>)</strong>
                        <?php if (!empty($mp['existing_description'])): ?>
                            <p style="margin-top:6px"><?= e($mp['existing_description']) ?></p>
                        <?php endif; ?>
                        <p style="margin-top:8px"><a class="btn btn-outline" href="<?= url('index.php?page=university-explorer&q=' . rawurlencode((string)$mp['existing_name'])) ?>">Tra cứu trường ở University Explorer →</a></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="card" style="margin-top:20px" id="school-links">
            <h2>Liên kết nguồn trường &amp; tin tuyển sinh (<?= count($allLinks) ?> liên kết)</h2>
            <div class="alert alert-warning" role="note">
                <strong>Lưu ý dữ liệu lịch sử theo từng năm tin:</strong> Mỗi liên kết bên dưới phản ánh một tin tuyển sinh tại nguồn tham khảo theo đúng năm tin ghi nhận. Đây <strong>không phải xác nhận tuyển sinh hiện hành</strong> và mỗi trang nguồn trường không đồng nghĩa một trường độc lập (một trường có thể xuất hiện qua nhiều tin/năm hoặc nhiều hệ đào tạo). Hãy kiểm tra trực tiếp tại nguồn chính thức của cơ sở đào tạo trước khi quyết định.
            </div>

            <?php if ($allLinks): ?>
                <div class="hnv-year-pills" aria-label="Lọc theo năm tin nguồn">
                    <a class="hnv-year-pill <?= $selectedYear === '' ? 'active' : '' ?>" href="<?= url('index.php?page=majors&id=' . rawurlencode((string)$major['id']) . '#school-links') ?>">Tất cả (<?= count($allLinks) ?>)</a>
                    <?php foreach ($yearCounts as $yr => $cnt): ?>
                        <a class="hnv-year-pill <?= $selectedYear === (string)$yr ? 'active' : '' ?>" href="<?= url('index.php?page=majors&id=' . rawurlencode((string)$major['id']) . '&year=' . rawurlencode((string)$yr) . '#school-links') ?>">
                            <?= $yr === 'unknown' ? 'Chưa rõ năm tin' : 'Năm tin ' . e($yr) ?> (<?= (int)$cnt ?>)
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="table-wrap hnv-school-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Tên nguồn trường / Tuyển sinh</th>
                                <th>Hệ &amp; Năm tin</th>
                                <th>Trạng thái xác minh tin tuyển sinh</th>
                                <th>Mã &amp; tổ hợp quan sát</th>
                                <th>Liên kết nguồn gốc</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($filteredLinks as $lnk):
                            $instUrl = safe_external_url($lnk['institution_source_url'] ?? '');
                            $evUrl = safe_external_url($lnk['evidence_url'] ?? '');
                            $linkMajCodes = hnv_decode_json_list($lnk['observed_major_codes_json'] ?? '[]');
                            $linkCombCodes = hnv_decode_json_list($lnk['observed_subject_combination_codes_json'] ?? '[]');
                            $admMeta = hnv_admission_link_status_meta((string)($lnk['admission_status'] ?? 'historical_unverified'));
                        ?>
                            <tr>
                                <td>
                                    <strong><?= e($lnk['source_label'] ?: 'Chưa rõ tên nguồn') ?></strong>
                                    <?php if (!empty($lnk['official_institution_id']) && !empty($lnk['official_institution_name'])): ?>
                                        <div style="margin-top:4px"><small class="status-pill status-pill-ok">Đã đối chiếu trường: <a href="<?= url('index.php?page=university-explorer&school=' . (int)$lnk['official_institution_id']) ?>"><?= e($lnk['official_institution_name']) ?></a></small></div>
                                    <?php else: ?>
                                        <div><small class="muted">Trang nguồn riêng — chưa gộp pháp nhân trường</small></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?= e($lnk['level_label'] ?: 'Chưa ghi nhận hệ') ?></div>
                                    <div style="margin-top:4px">
                                        <?php if ($lnk['source_news_year'] !== null): ?>
                                            <span class="tag">Tin năm <?= (int)$lnk['source_news_year'] ?></span>
                                        <?php else: ?>
                                            <span class="muted"><small>Chưa rõ năm tin</small></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-pill status-pill-<?= e($admMeta['tone']) ?>"><?= e($admMeta['label']) ?></span>
                                    <?php if (!empty($lnk['link_checked_at'])): ?>
                                        <div><small class="muted">Kiểm tra: <?= e(hnv_format_vn_datetime($lnk['link_checked_at'])) ?></small></div>
                                    <?php endif; ?>
                                    <?php if (!empty($lnk['link_check_note'])): ?>
                                        <div><small class="muted"><?= e($lnk['link_check_note']) ?></small></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($linkMajCodes): ?>
                                        <div><small class="muted">Mã quan sát:</small> <?php foreach ($linkMajCodes as $mc): ?><span class="tag"><?= e($mc) ?></span><?php endforeach; ?></div>
                                    <?php endif; ?>
                                    <?php if ($linkCombCodes): ?>
                                        <div><small class="muted">Tổ hợp quan sát:</small> <?php foreach ($linkCombCodes as $cc): ?><span class="tag"><?= e($cc) ?></span><?php endforeach; ?></div>
                                    <?php endif; ?>
                                    <?php if (!$linkMajCodes && !$linkCombCodes): ?>
                                        <span class="muted">Không ghi nhận trong bảng trích</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex;flex-direction:column;gap:6px">
                                        <?php if ($instUrl !== ''): ?>
                                            <a class="btn btn-outline" style="padding:6px 10px" href="<?= e($instUrl) ?>" target="_blank" rel="noopener noreferrer">Trang trường tại nguồn ↗</a>
                                        <?php endif; ?>
                                        <?php if ($evUrl !== ''): ?>
                                            <a class="muted hnv-url-break" href="<?= e($evUrl) ?>" target="_blank" rel="noopener noreferrer"><small>Nguồn ngành trích xuất ↗</small></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="hnv-school-cards">
                    <?php foreach ($filteredLinks as $lnk):
                        $instUrl = safe_external_url($lnk['institution_source_url'] ?? '');
                        $evUrl = safe_external_url($lnk['evidence_url'] ?? '');
                        $linkMajCodes = hnv_decode_json_list($lnk['observed_major_codes_json'] ?? '[]');
                        $linkCombCodes = hnv_decode_json_list($lnk['observed_subject_combination_codes_json'] ?? '[]');
                        $admMeta = hnv_admission_link_status_meta((string)($lnk['admission_status'] ?? 'historical_unverified'));
                    ?>
                        <article class="card" style="padding:1rem">
                            <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;align-items:center">
                                <span class="tag"><?= $lnk['source_news_year'] !== null ? 'Năm tin ' . (int)$lnk['source_news_year'] . ' (lịch sử)' : 'Chưa rõ năm tin' ?></span>
                                <small class="muted"><?= e($lnk['level_label'] ?: 'Chưa ghi nhận hệ') ?></small>
                            </div>
                            <h3 style="font-size:1rem;margin:8px 0 6px"><?= e($lnk['source_label'] ?: 'Chưa rõ tên nguồn') ?></h3>
                            <div style="margin-bottom:6px">
                                <span class="status-pill status-pill-<?= e($admMeta['tone']) ?>"><?= e($admMeta['label']) ?></span>
                            </div>
                            <?php if (!empty($lnk['link_check_note'])): ?>
                                <p><small class="muted"><?= e($lnk['link_check_note']) ?></small></p>
                            <?php endif; ?>
                            <?php if ($linkMajCodes): ?>
                                <p style="margin-top:6px"><small>Mã quan sát: <?= e(implode(', ', $linkMajCodes)) ?></small></p>
                            <?php endif; ?>
                            <?php if ($linkCombCodes): ?>
                                <p style="margin-top:4px"><small>Tổ hợp quan sát: <?= e(implode(', ', $linkCombCodes)) ?></small></p>
                            <?php endif; ?>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">
                                <?php if ($instUrl !== ''): ?>
                                    <a class="btn btn-outline" href="<?= e($instUrl) ?>" target="_blank" rel="noopener noreferrer">Mở trang trường tại nguồn ↗</a>
                                <?php endif; ?>
                                <?php if ($evUrl !== ''): ?>
                                    <a class="btn btn-outline" href="<?= e($evUrl) ?>" target="_blank" rel="noopener noreferrer">Trang trích xuất ↗</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="muted">Chưa có liên kết nguồn trường tuyển sinh được trích xuất cho ngành này từ dữ liệu nguồn. Điều này không đồng nghĩa không có trường đào tạo ngành này.</p>
            <?php endif; ?>
        </section>
    </div>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

// Catalog list mode
$q = is_string($_GET['q'] ?? null) ? trim(mb_substr($_GET['q'], 0, 120)) : '';
$fieldId = is_string($_GET['field'] ?? null) ? trim(mb_substr($_GET['field'], 0, 32)) : '';
$subgroupId = is_string($_GET['subgroup'] ?? null) ? trim(mb_substr($_GET['subgroup'], 0, 32)) : '';
$statusFilter = is_string($_GET['status'] ?? null) ? trim($_GET['status']) : 'all';
$allowedStatuses = ['all', 'ready', 'description_approved', 'source_ready', 'missing_description', 'code_verified', 'has_school_links', 'missing_detail', 'needs_review'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 24;

$stats = hnv_get_catalog_stats($db);
$fields = hnv_get_fields_with_counts($db);
$allSubgroups = hnv_get_subgroups_with_counts($db);

$validFieldIds = array_column($fields, 'id');
if ($fieldId !== '' && !in_array($fieldId, $validFieldIds, true)) {
    $fieldId = '';
}
$subgroupsById = [];
foreach ($allSubgroups as $sg) {
    $subgroupsById[$sg['id']] = $sg;
}
if ($subgroupId !== '' && !isset($subgroupsById[$subgroupId])) {
    $subgroupId = '';
}
if ($fieldId !== '' && $subgroupId !== '' && $subgroupsById[$subgroupId]['field_id'] !== $fieldId) {
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
], $pageNum, $perPage);

$majors = $result['items'];
$total = $result['total'];
$currentPage = $result['page'];
$totalPages = $result['total_pages'];

$buildCatalogUrl = static function (array $overrides = []) use ($q, $fieldId, $subgroupId, $statusFilter): string {
    $params = array_filter([
        'page' => 'majors',
        'q' => $overrides['q'] ?? $q,
        'field' => $overrides['field'] ?? $fieldId,
        'subgroup' => $overrides['subgroup'] ?? $subgroupId,
        'status' => ($overrides['status'] ?? $statusFilter) !== 'all' ? ($overrides['status'] ?? $statusFilter) : '',
        'p' => isset($overrides['p']) && (int)$overrides['p'] > 1 ? (string)(int)$overrides['p'] : '',
    ], static fn($v): bool => $v !== '');
    return url('index.php?' . http_build_query($params));
};

$pageTitle = 'Danh mục Ngành đào tạo & Nguồn trường';
require __DIR__ . '/header.php';
?>
<section class="page-head">
    <div class="container">
        <span class="eyebrow">JOBSIMU · Khám phá Ngành đào tạo</span>
        <h1>Danh mục Ngành đào tạo &amp; Nguồn trường tuyển sinh</h1>
        <p>Tra cứu các ngành đào tạo theo lĩnh vực, nhóm ngành và liên kết nguồn tuyển sinh tham khảo. Đây là danh mục <strong>ngành học</strong> (chưa phải chức danh nghề nghiệp hay bảng chấm điểm phù hợp cá nhân).</p>
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:14px">
            <a class="btn btn-outline" href="<?= url('index.php?page=university-explorer') ?>">Tra cứu trường xác thực (University Explorer) →</a>
            <a class="btn btn-outline" href="<?= url('index.php?page=knowledge-hub') ?>">← Thư viện nghề &amp; kiến thức</a>
        </div>
        <div class="majors-stats-bar">
            <div class="majors-stat-box"><strong><?= number_format($stats['majors'], 0, ',', '.') ?></strong><span>Ngành đào tạo (<?= number_format($stats['fields']) ?> lĩnh vực · <?= number_format($stats['subgroups']) ?> nhóm)</span></div>
            <div class="majors-stat-box"><strong><?= number_format($stats['description_approved_count'], 0, ',', '.') ?> / <?= number_format($stats['majors'], 0, ',', '.') ?></strong><span>Đã duyệt mô tả JobSimu (còn <?= number_format($stats['without_approved_description'], 0, ',', '.') ?> chưa duyệt)</span></div>
            <div class="majors-stat-box"><strong><?= number_format($stats['code_verified_count'], 0, ',', '.') ?> / <?= number_format($stats['majors'], 0, ',', '.') ?></strong><span>Đã xác minh mã ngành chính thức</span></div>
            <div class="majors-stat-box"><strong><?= number_format($stats['links'], 0, ',', '.') ?></strong><span>Liên kết tin tuyển sinh 2019–2025 (<?= number_format($stats['institution_sources'], 0, ',', '.') ?> URL)</span></div>
        </div>
    </div>
</section>

<div class="container" style="padding-bottom:40px">
    <form method="get" class="card majors-filter-card" id="majorsFilterForm">
        <input type="hidden" name="page" value="majors">
        <div class="majors-filter-grid">
            <div class="form-group">
                <label for="majorSearch">Tìm ngành (tiếng Việt có/không dấu hoặc mã ngành)</label>
                <input id="majorSearch" class="input" type="search" name="q" value="<?= e($q) ?>" maxlength="120" placeholder="Ví dụ: cong nghe, Mỹ thuật, su pham, 7480101">
            </div>
            <div class="form-group">
                <label for="majorField">Lĩnh vực (<?= count($fields) ?>)</label>
                <select id="majorField" name="field">
                    <option value="">Tất cả lĩnh vực</option>
                    <?php foreach ($fields as $f): ?>
                        <option value="<?= e($f['id']) ?>" <?= $fieldId === (string)$f['id'] ? 'selected' : '' ?>>
                            <?= e($f['name']) ?> (<?= (int)$f['major_count'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="majorSubgroup">Nhóm ngành (<?= count($allSubgroups) ?>)</label>
                <select id="majorSubgroup" name="subgroup">
                    <option value="">Tất cả nhóm ngành</option>
                    <?php if ($fieldId !== ''): ?>
                        <?php foreach ($visibleSubgroups as $sg): ?>
                            <option value="<?= e($sg['id']) ?>" data-field="<?= e($sg['field_id']) ?>" <?= $subgroupId === (string)$sg['id'] ? 'selected' : '' ?>>
                                <?= e($sg['name']) ?> (<?= (int)$sg['major_count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php
                        $grouped = [];
                        foreach ($visibleSubgroups as $sg) {
                            $grouped[$sg['field_name']][] = $sg;
                        }
                        foreach ($grouped as $gName => $items):
                        ?>
                            <optgroup label="<?= e($gName) ?>">
                                <?php foreach ($items as $sg): ?>
                                    <option value="<?= e($sg['id']) ?>" data-field="<?= e($sg['field_id']) ?>" <?= $subgroupId === (string)$sg['id'] ? 'selected' : '' ?>>
                                        <?= e($sg['name']) ?> (<?= (int)$sg['major_count'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="majorStatus">Tình trạng biên tập &amp; nguồn</label>
                <select id="majorStatus" name="status">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tất cả mục (kể cả thiếu dữ liệu)</option>
                    <option value="ready" <?= in_array($statusFilter, ['ready', 'description_approved'], true) ? 'selected' : '' ?>>Đã duyệt mô tả JobSimu</option>
                    <option value="missing_description" <?= $statusFilter === 'missing_description' ? 'selected' : '' ?>>Chưa có mô tả JobSimu được duyệt</option>
                    <option value="code_verified" <?= $statusFilter === 'code_verified' ? 'selected' : '' ?>>Đã xác minh mã ngành chính thức</option>
                    <option value="source_ready" <?= $statusFilter === 'source_ready' ? 'selected' : '' ?>>Đọc được bài gốc tại nguồn</option>
                    <option value="has_school_links" <?= $statusFilter === 'has_school_links' ? 'selected' : '' ?>>Có liên kết trường (2019–2025)</option>
                    <option value="missing_detail" <?= $statusFilter === 'missing_detail' ? 'selected' : '' ?>>Thiếu trang chi tiết / bài giới thiệu</option>
                    <option value="needs_review" <?= $statusFilter === 'needs_review' ? 'selected' : '' ?>>Còn vấn đề chờ kiểm tra</option>
                </select>
            </div>
        </div>
        <div class="majors-filter-actions">
            <button class="btn btn-primary" type="submit">Lọc &amp; Tìm ngành</button>
            <a class="btn btn-outline" href="<?= url('index.php?page=majors') ?>">Xóa bộ lọc</a>
            <span class="muted" role="status" style="margin-left:auto">
                <?php if ($stats['majors'] === 0): ?>
                    Chưa có dữ liệu ngành đào tạo được nhập.
                <?php else: ?>
                    Tìm thấy <strong><?= number_format($total) ?></strong> ngành phù hợp · Trang <?= (int)$currentPage ?> / <?= (int)$totalPages ?>
                <?php endif; ?>
            </span>
        </div>
    </form>

    <div class="alert alert-warning" role="note" style="margin-bottom:20px">
        <strong>Lưu ý nguồn dữ liệu:</strong> Danh mục phân biệt rõ thông tin từ nguồn tham khảo thứ cấp (thu thập ngày 11/11/2026 UTC) với nội dung đã được JobSimu biên tập hoặc xác minh mã chính thức. Các liên kết trường là <strong>dữ liệu lịch sử giai đoạn 2019–2025</strong>; vui lòng mở liên kết nguồn để kiểm tra thông tin hiện hành trước khi sử dụng.
    </div>

    <?php if ($stats['majors'] === 0): ?>
        <div class="card empty">
            <h2>Danh mục ngành đào tạo chưa được nhập dữ liệu</h2>
            <p>Quản trị viên cần chạy công cụ import dữ liệu nguồn trước khi tra cứu trên website.</p>
        </div>
    <?php elseif (!$majors): ?>
        <div class="card empty">
            <h2>Không tìm thấy ngành đào tạo phù hợp</h2>
            <p>Thử từ khóa ngắn hơn (có hoặc không dấu), hoặc bỏ bớt bộ lọc lĩnh vực / nhóm ngành.</p>
            <p style="margin-top:14px"><a class="btn btn-outline" href="<?= url('index.php?page=majors') ?>">Xem toàn bộ <?= number_format($stats['majors']) ?> ngành</a></p>
        </div>
    <?php else: ?>
        <div class="grid-3">
            <?php foreach ($majors as $m):
                $srcMeta = hnv_source_access_meta((string)$m['detail_status'], !empty($m['has_article_body']));
                $descStatus = (string)($m['description_status'] ?? 'not_edited');
                $codeStatus = (string)($m['code_verification_status'] ?? 'unverified');
                $hasApprovedDesc = ($descStatus === 'approved' && trim((string)($m['editorial_description'] ?? '')) !== '');
                $hasVerifiedCode = ($codeStatus === 'verified' && trim((string)($m['official_major_code'] ?? '')) !== '');
                $linkCount = (int)$m['link_count'];
                $srcUrlCount = (int)$m['source_url_count'];
                $detailHref = url('index.php?page=majors&id=' . rawurlencode((string)$m['id']));
            ?>
                <article class="card card-hover major-card">
                    <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
                        <span class="tag"><?= e($m['field_name']) ?></span>
                        <span class="tag"><?= e($m['subgroup_name']) ?></span>
                    </div>
                    <h3><a class="hover:underline" href="<?= $detailHref ?>"><?= e(hnv_to_nfc((string)$m['name'])) ?></a></h3>
                    <div style="display:flex;flex-wrap:wrap;gap:6px">
                        <span class="status-pill status-pill-<?= e($srcMeta['tone']) ?>"><?= e($srcMeta['label']) ?></span>
                        <?php if ($hasVerifiedCode): ?>
                            <span class="status-pill status-pill-ok">Mã chính thức: <?= e($m['official_major_code']) ?></span>
                        <?php elseif ($codeStatus === 'conflict'): ?>
                            <span class="status-pill status-pill-error">Mã nguồn có xung đột</span>
                        <?php elseif (!empty($m['source_major_code_candidate'])): ?>
                            <span class="status-pill status-pill-info">Mã tham khảo: <?= e($m['source_major_code_candidate']) ?></span>
                        <?php else: ?>
                            <span class="status-pill status-pill-warn">Chưa có mã đơn nhất</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($hasApprovedDesc): ?>
                        <p><small><?= e(mb_substr(trim((string)$m['editorial_description']), 0, 140)) ?>…</small></p>
                    <?php elseif ($descStatus === 'draft'): ?>
                        <p class="muted"><small>Mô tả JobSimu đang soạn thảo nội bộ (chưa duyệt công khai).</small></p>
                    <?php else: ?>
                        <p class="muted"><small>Chưa có mô tả JobSimu được duyệt<?= $m['detail_status'] === 'fetched' ? ' · Xem bài tham khảo ở trang chi tiết' : ' · Thiếu trang chi tiết nguồn' ?>.</small></p>
                    <?php endif; ?>
                    <div class="major-card-footer">
                        <small class="muted">
                            <?php if ($linkCount > 0): ?>
                                <?= $linkCount ?> liên kết tin tuyển sinh (<?= $srcUrlCount ?> URL · <?= $m['min_year'] && $m['max_year'] ? ((int)$m['min_year'] === (int)$m['max_year'] ? (int)$m['min_year'] : (int)$m['min_year'] . '–' . (int)$m['max_year']) : 'lịch sử' ?>)
                            <?php else: ?>
                                Chưa trích xuất được liên kết trường từ nguồn
                            <?php endif; ?>
                        </small>
                        <a class="btn btn-outline" href="<?= $detailHref ?>">Xem chi tiết &amp; nguồn trường →</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Phân trang danh mục ngành">
                <?php if ($currentPage > 1): ?>
                    <a class="btn btn-outline" href="<?= $buildCatalogUrl(['p' => $currentPage - 1]) ?>">← Trước</a>
                <?php endif; ?>
                <?php
                $window = 2;
                $startPage = max(1, $currentPage - $window);
                $endPage = min($totalPages, $currentPage + $window);
                if ($startPage > 1) {
                    echo '<a class="btn btn-outline" href="' . $buildCatalogUrl(['p' => 1]) . '">1</a>';
                    if ($startPage > 2) {
                        echo '<span class="muted">…</span>';
                    }
                }
                for ($p = $startPage; $p <= $endPage; $p++):
                ?>
                    <a class="btn <?= $p === $currentPage ? 'active' : 'btn-outline' ?>" href="<?= $buildCatalogUrl(['p' => $p]) ?>" <?= $p === $currentPage ? 'aria-current="page"' : '' ?>><?= $p ?></a>
                <?php endfor;
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<span class="muted">…</span>';
                    }
                    echo '<a class="btn btn-outline" href="' . $buildCatalogUrl(['p' => $totalPages]) . '">' . $totalPages . '</a>';
                }
                ?>
                <?php if ($currentPage < $totalPages): ?>
                    <a class="btn btn-outline" href="<?= $buildCatalogUrl(['p' => $currentPage + 1]) ?>">Sau →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(() => {
    const fieldSel = document.getElementById('majorField');
    const subSel = document.getElementById('majorSubgroup');
    if (!fieldSel || !subSel) return;
    const allSubgroups = <?= json_encode(array_map(static fn(array $sg): array => [
        'id' => (string)$sg['id'],
        'field_id' => (string)$sg['field_id'],
        'name' => (string)$sg['name'],
        'field_name' => (string)$sg['field_name'],
        'major_count' => (int)$sg['major_count'],
    ], $allSubgroups), JSON_UNESCAPED_UNICODE) ?>;

    fieldSel.addEventListener('change', () => {
        const selectedField = fieldSel.value;
        const currentSub = subSel.value;
        subSel.innerHTML = '';
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = 'Tất cả nhóm ngành';
        subSel.appendChild(defaultOpt);

        const filtered = selectedField
            ? allSubgroups.filter(s => s.field_id === selectedField)
            : allSubgroups;

        if (selectedField) {
            filtered.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = `${s.name} (${s.major_count})`;
                if (s.id === currentSub) opt.selected = true;
                subSel.appendChild(opt);
            });
        } else {
            const byField = new Map();
            filtered.forEach(s => {
                if (!byField.has(s.field_name)) byField.set(s.field_name, []);
                byField.get(s.field_name).push(s);
            });
            byField.forEach((items, fname) => {
                const grp = document.createElement('optgroup');
                grp.label = fname;
                items.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = `${s.name} (${s.major_count})`;
                    if (s.id === currentSub) opt.selected = true;
                    grp.appendChild(opt);
                });
                subSel.appendChild(grp);
            });
        }
    });
})();
</script>
<?php
require __DIR__ . '/footer.php';
exit;
