<?php
declare(strict_types=1);

/**
 * Admin console for HNV Training Majors Catalog:
 * - Separates source availability, JobSimu editorial description, official code verification (with evidence),
 *   individual review issues, per-link historical admission checks, and audited starter mappings.
 * - Loaded from admin.php after require_admin().
 */

require_once __DIR__ . '/training-majors.php';
training_majors_migrate();

$db = db();

if (is_post()) {
    verify_csrf();
    $intent = (string)($_POST['intent'] ?? '');
    $majorIdRedirect = trim((string)($_POST['major_id'] ?? ''));

    try {
        if ($intent === 'save_description') {
            $majorId = trim((string)($_POST['major_id'] ?? ''));
            $stmt = $db->prepare('SELECT id, is_editorially_locked FROM training_majors WHERE id = ?');
            $stmt->execute([$majorId]);
            $curMajor = $stmt->fetch();
            if (!$curMajor) {
                throw new InvalidArgumentException('Không tìm thấy ngành đào tạo.');
            }

            $descStatus = (string)($_POST['description_status'] ?? 'not_edited');
            if (!in_array($descStatus, ['not_edited', 'draft', 'approved'], true)) {
                throw new InvalidArgumentException('Trạng thái mô tả không hợp lệ.');
            }

            $editorialDescRaw = trim((string)($_POST['editorial_description'] ?? ''));
            $editorialDesc = $editorialDescRaw !== '' ? mb_substr($editorialDescRaw, 0, 10000) : null;
            if ($descStatus === 'approved' && $editorialDesc === null) {
                throw new InvalidArgumentException('Không thể duyệt công khai mô tả JobSimu khi nội dung đang trống.');
            }
            if ($descStatus === 'not_edited' && $editorialDesc !== null) {
                throw new InvalidArgumentException('Khi đã nhập nội dung mô tả, vui lòng chọn trạng thái "Bản nháp" hoặc "Đã duyệt công khai".');
            }

            $editorialNotesRaw = trim((string)($_POST['editorial_notes'] ?? ''));
            $editorialNotes = $editorialNotesRaw !== '' ? mb_substr($editorialNotesRaw, 0, 2000) : null;
            $locked = !empty($_POST['is_editorially_locked']) || in_array($descStatus, ['draft', 'approved'], true) ? 1 : (int)$curMajor['is_editorially_locked'];

            $now = date('Y-m-d H:i:s');
            $adminId = (int)user()['id'];

            $db->beginTransaction();
            $upd = $db->prepare(
                'UPDATE training_majors
                 SET editorial_description = ?, description_status = ?, description_updated_by = ?,
                     description_updated_at = ?, editorial_notes = ?, is_editorially_locked = ?,
                     reviewed_by = ?, reviewed_at = ?
                 WHERE id = ?'
            );
            $upd->execute([$editorialDesc, $descStatus, $adminId, $now, $editorialNotes, $locked, $adminId, $now, $majorId]);

            // Resolve ONLY MISSING_JOBSIMU_DESCRIPTION; never close code or source issues
            $issCheck = $db->prepare('SELECT id FROM major_review_issues WHERE major_id = ? AND issue_code = ?');
            $issCheck->execute([$majorId, 'MISSING_JOBSIMU_DESCRIPTION']);
            $issId = $issCheck->fetchColumn();
            if ($descStatus === 'approved') {
                if ($issId) {
                    $db->prepare('UPDATE major_review_issues SET status = ?, resolution_note = ?, resolved_by = ?, resolved_at = ? WHERE id = ?')
                        ->execute(['resolved', 'Đã biên tập và duyệt mô tả JobSimu.', $adminId, $now, (int)$issId]);
                } else {
                    $db->prepare('INSERT INTO major_review_issues(major_id,issue_code,issue_category,origin,status,detail_note,resolution_note,resolved_by,resolved_at) VALUES(?,?,?,?,?,?,?,?,?)')
                        ->execute([$majorId, 'MISSING_JOBSIMU_DESCRIPTION', 'description', 'editorial_workflow', 'resolved', 'Chưa có mô tả JobSimu được duyệt công khai.', 'Đã biên tập và duyệt mô tả JobSimu.', $adminId, $now]);
                }
            } else {
                if ($issId) {
                    $db->prepare('UPDATE major_review_issues SET status = ?, resolution_note = NULL, resolved_by = NULL, resolved_at = NULL WHERE id = ?')
                        ->execute(['open', (int)$issId]);
                } else {
                    $db->prepare('INSERT INTO major_review_issues(major_id,issue_code,issue_category,origin,status,detail_note) VALUES(?,?,?,?,?,?)')
                        ->execute([$majorId, 'MISSING_JOBSIMU_DESCRIPTION', 'description', 'editorial_workflow', 'open', 'Chưa có mô tả JobSimu được duyệt công khai.']);
                }
            }

            hnv_recompute_queue_status($db, $majorId);
            $db->commit();

            audit('Cập nhật mô tả JobSimu cho ngành ' . $majorId . ' -> ' . $descStatus, 'training_major', null);
            flash('success', 'Đã lưu mô tả JobSimu (' . hnv_description_status_meta($descStatus)['label'] . '). Chỉ đóng riêng mục kiểm tra mô tả.');
            redirect('admin.php?section=majors-review&edit=' . rawurlencode($majorId) . '#description-editor');
        }

        if ($intent === 'save_code_verification') {
            $majorId = trim((string)($_POST['major_id'] ?? ''));
            $stmt = $db->prepare('SELECT id, is_editorially_locked FROM training_majors WHERE id = ?');
            $stmt->execute([$majorId]);
            $curMajor = $stmt->fetch();
            if (!$curMajor) {
                throw new InvalidArgumentException('Không tìm thấy ngành đào tạo.');
            }

            $codeStatus = (string)($_POST['code_verification_status'] ?? 'unverified');
            if (!in_array($codeStatus, ['unverified', 'verified', 'conflict'], true)) {
                throw new InvalidArgumentException('Trạng thái xác minh mã ngành không hợp lệ.');
            }

            $officialCodeRaw = trim((string)($_POST['official_major_code'] ?? ''));
            if ($officialCodeRaw !== '' && !preg_match('/^[A-Za-z0-9_.-]{2,30}$/D', $officialCodeRaw)) {
                throw new InvalidArgumentException('Mã ngành chính thức chỉ chấp nhận 2–30 ký tự chữ, số, dấu gạch hoặc chấm.');
            }

            $evidenceUrlRaw = trim((string)($_POST['code_evidence_url'] ?? ''));
            $evidenceUrl = null;
            if ($evidenceUrlRaw !== '') {
                $scheme = strtolower((string)parse_url($evidenceUrlRaw, PHP_URL_SCHEME));
                $host = (string)parse_url($evidenceUrlRaw, PHP_URL_HOST);
                if (!filter_var($evidenceUrlRaw, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true) || $host === '' || strlen($evidenceUrlRaw) > 500) {
                    throw new InvalidArgumentException('URL bằng chứng nguồn phải là địa chỉ HTTP/HTTPS hợp lệ.');
                }
                $evidenceUrl = $evidenceUrlRaw;
            }

            $evidenceTitleRaw = trim((string)($_POST['code_evidence_title'] ?? ''));
            $evidenceTitle = $evidenceTitleRaw !== '' ? mb_substr($evidenceTitleRaw, 0, 250) : null;

            $checkedDateRaw = trim((string)($_POST['code_checked_at'] ?? ''));
            $codeCheckedAt = null;
            if ($checkedDateRaw !== '') {
                $parsedTs = strtotime($checkedDateRaw);
                if ($parsedTs === false) {
                    throw new InvalidArgumentException('Ngày kiểm tra mã ngành không hợp lệ.');
                }
                $codeCheckedAt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkedDateRaw) ? ($checkedDateRaw . ' 00:00:00') : date('Y-m-d H:i:s', $parsedTs);
            }

            $scopeRaw = trim((string)($_POST['code_effective_scope'] ?? ''));
            $effectiveScope = $scopeRaw !== '' ? mb_substr($scopeRaw, 0, 180) : null;

            $yearRaw = trim((string)($_POST['code_effective_year'] ?? ''));
            $effectiveYear = null;
            if ($yearRaw !== '') {
                if (!ctype_digit($yearRaw) || (int)$yearRaw < 1990 || (int)$yearRaw > 2100) {
                    throw new InvalidArgumentException('Năm áp dụng của mã ngành phải từ 1990 đến 2100.');
                }
                $effectiveYear = (int)$yearRaw;
            }

            $codeNoteRaw = trim((string)($_POST['code_verification_note'] ?? ''));
            $codeNote = $codeNoteRaw !== '' ? mb_substr($codeNoteRaw, 0, 2000) : null;

            if ($codeStatus === 'verified') {
                if ($officialCodeRaw === '') {
                    throw new InvalidArgumentException('Khi chọn "Đã xác minh mã ngành", bắt buộc nhập mã ngành chính thức.');
                }
                if ($evidenceUrl === null) {
                    throw new InvalidArgumentException('Khi xác minh mã ngành, bắt buộc ghi nhận URL nguồn/văn bản chứng minh.');
                }
                if ($codeCheckedAt === null) {
                    throw new InvalidArgumentException('Khi xác minh mã ngành, bắt buộc nhập ngày kiểm tra.');
                }
            } elseif ($codeStatus === 'conflict' && $codeNote === null) {
                throw new InvalidArgumentException('Khi đánh dấu xung đột mã ngành, vui lòng ghi rõ nội dung xung đột để tiện đối chiếu.');
            }

            // Never leave official_major_code populated while unverified or conflict
            $officialCode = $codeStatus === 'verified' ? $officialCodeRaw : null;
            $adminId = (int)user()['id'];
            $now = date('Y-m-d H:i:s');
            $locked = in_array($codeStatus, ['verified', 'conflict'], true) ? 1 : (int)$curMajor['is_editorially_locked'];

            $db->beginTransaction();
            $upd = $db->prepare(
                'UPDATE training_majors
                 SET official_major_code = ?, code_verification_status = ?, code_evidence_url = ?,
                     code_evidence_title = ?, code_checked_at = ?, code_effective_scope = ?,
                     code_effective_year = ?, code_verified_by = ?, code_verification_note = ?,
                     is_editorially_locked = ?, reviewed_by = ?, reviewed_at = ?
                 WHERE id = ?'
            );
            $upd->execute([
                $officialCode, $codeStatus, $evidenceUrl, $evidenceTitle, $codeCheckedAt,
                $effectiveScope, $effectiveYear, $adminId, $codeNote,
                $locked, $adminId, $now, $majorId,
            ]);

            // Resolve ONLY UNVERIFIED_OFFICIAL_CODE (and CODE_CONFLICT when verified); do NOT close description/link/source issues
            $issUnverStmt = $db->prepare('SELECT id FROM major_review_issues WHERE major_id = ? AND issue_code = ?');
            $issUnverStmt->execute([$majorId, 'UNVERIFIED_OFFICIAL_CODE']);
            $issUnverId = $issUnverStmt->fetchColumn();

            $issConfStmt = $db->prepare('SELECT id FROM major_review_issues WHERE major_id = ? AND issue_code = ?');
            $issConfStmt->execute([$majorId, 'CODE_CONFLICT']);
            $issConfId = $issConfStmt->fetchColumn();

            if ($codeStatus === 'verified') {
                $resMsg = 'Đã xác minh mã ' . $officialCode . ' (kiểm tra ' . substr((string)$codeCheckedAt, 0, 10) . ').';
                if ($issUnverId) {
                    $db->prepare('UPDATE major_review_issues SET status = ?, resolution_note = ?, resolved_by = ?, resolved_at = ? WHERE id = ?')
                        ->execute(['resolved', $resMsg, $adminId, $now, (int)$issUnverId]);
                }
                if ($issConfId) {
                    $db->prepare('UPDATE major_review_issues SET status = ?, resolution_note = ?, resolved_by = ?, resolved_at = ? WHERE id = ?')
                        ->execute(['resolved', $resMsg, $adminId, $now, (int)$issConfId]);
                }
            } elseif ($codeStatus === 'conflict') {
                if ($issUnverId) {
                    $db->prepare('UPDATE major_review_issues SET status = ?, resolution_note = NULL, resolved_by = NULL, resolved_at = NULL WHERE id = ?')
                        ->execute(['open', (int)$issUnverId]);
                }
                if ($issConfId) {
                    $db->prepare('UPDATE major_review_issues SET status = ?, detail_note = ?, resolution_note = NULL, resolved_by = NULL, resolved_at = NULL WHERE id = ?')
                        ->execute(['open', $codeNote, (int)$issConfId]);
                } else {
                    $db->prepare('INSERT INTO major_review_issues(major_id,issue_code,issue_category,origin,status,detail_note) VALUES(?,?,?,?,?,?)')
                        ->execute([$majorId, 'CODE_CONFLICT', 'code', 'editorial_workflow', 'open', $codeNote]);
                }
            } else {
                if ($issUnverId) {
                    $db->prepare('UPDATE major_review_issues SET status = ?, resolution_note = NULL, resolved_by = NULL, resolved_at = NULL WHERE id = ?')
                        ->execute(['open', (int)$issUnverId]);
                }
            }

            hnv_recompute_queue_status($db, $majorId);
            $db->commit();

            audit('Cập nhật xác minh mã ngành ' . $majorId . ' -> ' . $codeStatus, 'training_major', null);
            flash('success', 'Đã lưu xác minh mã ngành kèm bằng chứng cho ' . $majorId . '. Các vấn đề mô tả hoặc nguồn trường vẫn giữ trạng thái riêng.');
            redirect('admin.php?section=majors-review&edit=' . rawurlencode($majorId) . '#code-verification-editor');
        }

        if ($intent === 'issue_status') {
            $majorId = trim((string)($_POST['major_id'] ?? ''));
            $issueId = max(1, (int)($_POST['issue_id'] ?? 0));
            $newStatus = (string)($_POST['new_status'] ?? 'open');
            if (!in_array($newStatus, ['open', 'resolved', 'deferred'], true)) {
                throw new InvalidArgumentException('Trạng thái xử lý vấn đề không hợp lệ.');
            }
            $resNoteRaw = trim((string)($_POST['resolution_note'] ?? ''));
            $resNote = $resNoteRaw !== '' ? mb_substr($resNoteRaw, 0, 1000) : null;
            $adminId = (int)user()['id'];
            $now = date('Y-m-d H:i:s');

            $db->beginTransaction();
            $chk = $db->prepare('SELECT id, issue_code FROM major_review_issues WHERE id = ? AND major_id = ?');
            $chk->execute([$issueId, $majorId]);
            $issRow = $chk->fetch();
            if (!$issRow) {
                throw new InvalidArgumentException('Không tìm thấy vấn đề cần cập nhật.');
            }
            if ($newStatus === 'open') {
                $db->prepare('UPDATE major_review_issues SET status = ?, resolution_note = ?, resolved_by = NULL, resolved_at = NULL WHERE id = ?')
                    ->execute(['open', $resNote, $issueId]);
            } else {
                $db->prepare('UPDATE major_review_issues SET status = ?, resolution_note = ?, resolved_by = ?, resolved_at = ? WHERE id = ?')
                    ->execute([$newStatus, $resNote, $adminId, $now, $issueId]);
            }
            $db->prepare('UPDATE major_review_queue SET reviewed_by = ?, reviewed_at = ? WHERE major_id = ?')
                ->execute([$adminId, $now, $majorId]);
            hnv_recompute_queue_status($db, $majorId);
            $db->commit();

            audit('Cập nhật vấn đề ' . $issRow['issue_code'] . ' (' . $majorId . ') -> ' . $newStatus, 'major_review_issue', $issueId);
            flash('success', 'Đã cập nhật riêng vấn đề "' . $issRow['issue_code'] . '" -> ' . $newStatus . '. Các vấn đề khác của ngành giữ nguyên.');
            redirect('admin.php?section=majors-review&edit=' . rawurlencode($majorId) . '#issues-editor');
        }

        if ($intent === 'link_status') {
            $majorId = trim((string)($_POST['major_id'] ?? ''));
            $linkId = max(1, (int)($_POST['link_id'] ?? 0));
            $admStatus = (string)($_POST['admission_status'] ?? 'historical_unverified');
            $allowedAdm = ['historical_unverified', 'source_verified_year', 'outdated_historical', 'source_unavailable', 'disputed'];
            if (!in_array($admStatus, $allowedAdm, true)) {
                throw new InvalidArgumentException('Trạng thái liên kết tuyển sinh không hợp lệ.');
            }
            $noteRaw = trim((string)($_POST['link_check_note'] ?? ''));
            $note = $noteRaw !== '' ? mb_substr($noteRaw, 0, 1000) : null;
            $adminId = (int)user()['id'];
            $now = date('Y-m-d H:i:s');

            $upd = $db->prepare('UPDATE major_institution_source_links SET admission_status = ?, link_check_note = ?, link_checked_by = ?, link_checked_at = ? WHERE id = ? AND major_id = ?');
            $upd->execute([$admStatus, $note, $adminId, $now, $linkId, $majorId]);
            if ($upd->rowCount() < 1) {
                throw new InvalidArgumentException('Không tìm thấy liên kết trường cần cập nhật.');
            }

            audit('Cập nhật trạng thái tin tuyển sinh #' . $linkId . ' -> ' . $admStatus, 'major_institution_source_link', $linkId);
            flash('success', 'Đã lưu trạng thái kiểm tra tin tuyển sinh #' . $linkId . '.');
            redirect('admin.php?section=majors-review&edit=' . rawurlencode($majorId) . '#links-editor');
        }

        if ($intent === 'mapping_decision') {
            $mappingId = max(1, (int)($_POST['mapping_id'] ?? 0));
            $decision = (string)($_POST['decision'] ?? '');
            if (!in_array($decision, ['approved', 'rejected', 'pending_review'], true)) {
                throw new InvalidArgumentException('Trạng thái ánh xạ không hợp lệ.');
            }
            $revNoteRaw = trim((string)($_POST['review_note'] ?? ''));
            $revNote = $revNoteRaw !== '' ? mb_substr($revNoteRaw, 0, 1000) : null;

            // Never modify training_majors.official_major_code when approving/rejecting a mapping!
            $upd = $db->prepare('UPDATE training_major_mappings SET mapping_status = ?, review_note = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?');
            $upd->execute([$decision, $revNote, (int)user()['id'], date('Y-m-d H:i:s'), $mappingId]);
            if ($upd->rowCount() < 1) {
                throw new InvalidArgumentException('Không tìm thấy bản ghi ánh xạ.');
            }
            audit('Cập nhật ánh xạ ngành đào tạo #' . $mappingId . ' -> ' . $decision, 'training_major_mapping', $mappingId);
            flash('success', 'Đã cập nhật quyết định ánh xạ #' . $mappingId . ' (chỉ áp dụng liên kết bản ghi, không thay đổi trạng thái xác minh mã chính thức).');
            redirect('admin.php?section=majors-review#mappings');
        }
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        flash('error', 'Không thể lưu thay đổi: ' . $ex->getMessage());
        $back = 'admin.php?section=majors-review' . ($majorIdRedirect !== '' ? '&edit=' . rawurlencode($majorIdRedirect) : '');
        redirect($back);
    }
}

$stats = hnv_get_catalog_stats($db);
$issueLabels = hnv_review_issue_labels();
$editId = is_string($_GET['edit'] ?? null) ? trim($_GET['edit']) : '';
$editingMajor = $editId !== '' ? hnv_get_major_detail($db, $editId) : null;

$q = is_string($_GET['q'] ?? null) ? trim(mb_substr($_GET['q'], 0, 120)) : '';
$issueFilter = is_string($_GET['issue'] ?? null) ? trim($_GET['issue']) : '';
$descFilter = is_string($_GET['desc_status'] ?? null) ? trim($_GET['desc_status']) : '';
$codeFilter = is_string($_GET['code_status'] ?? null) ? trim($_GET['code_status']) : '';
$reviewStatusFilter = is_string($_GET['review_status'] ?? null) ? trim($_GET['review_status']) : '';
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 25;

$qWhere = [];
$qArgs = [];
if ($issueFilter !== '' && array_key_exists($issueFilter, $issueLabels)) {
    $qWhere[] = "EXISTS (SELECT 1 FROM major_review_issues mri WHERE mri.major_id = m.id AND mri.issue_code = ? AND mri.status = 'open')";
    $qArgs[] = $issueFilter;
}
if (in_array($descFilter, ['not_edited', 'draft', 'approved'], true)) {
    $qWhere[] = 'm.description_status = ?';
    $qArgs[] = $descFilter;
} else {
    $descFilter = '';
}
if (in_array($codeFilter, ['unverified', 'verified', 'conflict'], true)) {
    $qWhere[] = 'm.code_verification_status = ?';
    $qArgs[] = $codeFilter;
} else {
    $codeFilter = '';
}
if ($reviewStatusFilter !== '' && in_array($reviewStatusFilter, ['pending', 'in_review', 'verified', 'deferred'], true)) {
    $qWhere[] = 'q.review_status = ?';
    $qArgs[] = $reviewStatusFilter;
} else {
    $reviewStatusFilter = '';
}
$qAscii = hnv_strip_vi_accents($q);
if ($qAscii !== '') {
    $qWhere[] = "(m.normalized_name LIKE ? OR m.id LIKE ? OR COALESCE(m.source_major_code_candidate, '') LIKE ? OR COALESCE(m.official_major_code, '') LIKE ?)";
    $qArgs[] = '%' . $qAscii . '%';
    $qArgs[] = '%' . $q . '%';
    $qArgs[] = '%' . $q . '%';
    $qArgs[] = '%' . $q . '%';
}
$qWhereSql = $qWhere ? (' WHERE ' . implode(' AND ', $qWhere)) : '';

$countStmt = $db->prepare('SELECT COUNT(*) FROM major_review_queue q JOIN training_majors m ON m.id = q.major_id' . $qWhereSql);
$countStmt->execute($qArgs);
$totalQueue = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalQueue / $perPage));
$pageNum = min($pageNum, $totalPages);
$offset = ($pageNum - 1) * $perPage;

$queueSql = 'SELECT q.*, m.name, m.detail_status, m.has_article_body, m.source_major_code_candidate,
                    m.official_major_code, m.description_status, m.code_verification_status,
                    m.is_editorially_locked, f.name AS field_name, s.name AS subgroup_name
             FROM major_review_queue q
             JOIN training_majors m ON m.id = q.major_id
             JOIN major_fields f ON f.id = m.field_id
             JOIN major_subgroups s ON s.id = m.subgroup_id'
             . $qWhereSql . ' ORDER BY q.open_issue_count DESC, q.issue_count DESC, m.name ASC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
$queueStmt = $db->prepare($queueSql);
$queueStmt->execute($qArgs);
$queueRows = $queueStmt->fetchAll();

// Preload open issues for the displayed queue page
$openIssuesByMajor = [];
if ($queueRows) {
    $majorIdsPage = array_column($queueRows, 'major_id');
    $inPh = implode(',', array_fill(0, count($majorIdsPage), '?'));
    $oiStmt = $db->prepare("SELECT major_id, issue_code, status FROM major_review_issues WHERE status = 'open' AND major_id IN ($inPh) ORDER BY id ASC");
    $oiStmt->execute($majorIdsPage);
    foreach ($oiStmt->fetchAll() as $oiRow) {
        $openIssuesByMajor[$oiRow['major_id']][] = $oiRow['issue_code'];
    }
}

$mappings = $db->query(
    'SELECT mm.*, tm.name AS training_major_name, tm.source_major_code_candidate, tm.official_major_code,
            tm.code_verification_status, tm.source_dom_code, tm.observed_major_codes_json, tm.source_url AS tm_source_url,
            em.code AS existing_code, em.name AS existing_name,
            u.name AS reviewer_name
     FROM training_major_mappings mm
     JOIN training_majors tm ON tm.id = mm.training_major_id
     JOIN majors em ON em.id = mm.existing_major_id
     LEFT JOIN users u ON u.id = mm.reviewed_by
     ORDER BY CASE mm.mapping_status WHEN \'pending_review\' THEN 0 WHEN \'approved\' THEN 1 WHEN \'confirmed\' THEN 1 ELSE 2 END, mm.has_conflict DESC, mm.id ASC'
)->fetchAll();

$importRuns = $db->query('SELECT r.*, u.name AS initiator_name FROM dataset_import_runs r LEFT JOIN users u ON u.id = r.initiated_by ORDER BY r.id DESC LIMIT 20')->fetchAll();

$usersById = [];
foreach ($db->query('SELECT id, name FROM users')->fetchAll() as $uRow) {
    $usersById[(int)$uRow['id']] = $uRow['name'];
}

$buildAdminQueueUrl = static function (array $overrides = []) use ($q, $issueFilter, $descFilter, $codeFilter, $reviewStatusFilter): string {
    $params = array_filter([
        'section' => 'majors-review',
        'q' => $overrides['q'] ?? $q,
        'issue' => $overrides['issue'] ?? $issueFilter,
        'desc_status' => $overrides['desc_status'] ?? $descFilter,
        'code_status' => $overrides['code_status'] ?? $codeFilter,
        'review_status' => $overrides['review_status'] ?? $reviewStatusFilter,
        'p' => isset($overrides['p']) && (int)$overrides['p'] > 1 ? (string)(int)$overrides['p'] : '',
    ], static fn($v): bool => $v !== '');
    return url('admin.php?' . http_build_query($params));
};

$queueStatusMeta = static function (string $st): array {
    return match ($st) {
        'verified' => ['label' => 'Đã đóng hết vấn đề', 'tone' => 'on'],
        'in_review' => ['label' => 'Đang xử lý dở', 'tone' => 'off'],
        'deferred' => ['label' => 'Tạm hoãn còn lại', 'tone' => 'off'],
        default => ['label' => 'Chưa xử lý', 'tone' => 'off'],
    };
};
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Kiểm toán &amp; Xác minh Ngành đào tạo · Admin JOBSIMU</title>
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/admin.css') ?>">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <a class="brand" href="<?= url('admin.php') ?>"><span>✦</span>Admin 360</a>
        <nav>
            <a class="active" href="<?= url('admin.php?section=majors-review') ?>">🎓 Ngành &amp; Xác minh</a>
            <a href="<?= url('admin.php?section=admissions') ?>">🏫 Tuyển sinh</a>
            <a href="<?= url('admin.php?section=knowledge-articles') ?>">📚 Bài viết</a>
            <a href="<?= url('admin.php?section=knowledge-portals') ?>">🌐 Cổng thông tin</a>
            <a href="<?= url('admin.php?section=dashboard') ?>">▦ Tổng quan</a>
            <a href="<?= url('index.php?page=majors') ?>">↗ Trang public Ngành đào tạo</a>
        </nav>
    </aside>

    <main class="admin-main">
        <header class="admin-top">
            <div>
                <span class="muted">Quản trị dữ liệu nguồn HNV · Tách biệt Nguồn / Mô tả JobSimu / Mã chính thức / Ánh xạ</span>
                <h1>Kiểm toán Dữ liệu Ngành &amp; Xác minh Đa tầng</h1>
            </div>
            <div class="user-chip">
                <span class="avatar" style="background:<?= e(user()['avatar_color']) ?>"><?= e(mb_strtoupper(mb_substr(user()['name'], 0, 1))) ?></span>
                <b><?= e(user()['name']) ?></b>
            </div>
        </header>

        <?php foreach (pull_flashes() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>" role="alert"><?= e($f['message']) ?></div>
        <?php endforeach; ?>

        <div class="admin-stats">
            <div class="card">
                <span class="muted">Ngành chưa có mô tả JobSimu</span>
                <strong><?= number_format($stats['without_approved_description']) ?> / <?= number_format($stats['majors']) ?></strong>
                <small class="muted">Đã duyệt: <?= number_format($stats['description_approved_count']) ?> · Nháp: <?= number_format($stats['description_draft_count']) ?> · Chưa biên tập: <?= number_format($stats['description_not_edited_count']) ?></small>
            </div>
            <div class="card">
                <span class="muted">Xác minh mã ngành chính thức</span>
                <strong><?= number_format($stats['code_verified_count']) ?> / <?= number_format($stats['majors']) ?></strong>
                <small class="muted">Chưa xác minh: <?= number_format($stats['code_unverified_count']) ?> · Xung đột: <?= number_format($stats['code_conflict_count']) ?></small>
            </div>
            <div class="card">
                <span class="muted">Vấn đề còn cần xử lý</span>
                <strong><?= number_format($stats['open_issues_total']) ?></strong>
                <small class="muted"><?= number_format($stats['review_pending']) ?> ngành còn mục mở · Đã giải quyết: <?= number_format($stats['resolved_issues_total']) ?></small>
            </div>
            <div class="card">
                <span class="muted">Liên kết trường &amp; Gợi ý ánh xạ</span>
                <strong><?= number_format($stats['links']) ?> / <?= number_format($stats['pending_mappings']) ?> chờ duyệt</strong>
                <small class="muted"><?= number_format($stats['institution_sources']) ?> URL nguồn · <?= number_format($stats['without_school_links']) ?> ngành chưa có link</small>
            </div>
        </div>

        <section class="card" style="margin-top:20px">
            <h2>1. Cảnh báo nguồn ban đầu vs Vấn đề hiện còn cần xử lý</h2>
            <div style="display:grid;gap:18px;margin-top:12px">
                <div>
                    <h3 style="font-size:.98rem;margin-bottom:6px">A. Vấn đề hiện còn mở trong quy trình kiểm toán (lọc trực tiếp hàng chờ bên dưới)</h3>
                    <div style="display:flex;flex-wrap:wrap;gap:8px">
                        <?php foreach ($stats['open_issue_counts'] as $code => $cnt):
                            $meta = $issueLabels[$code] ?? ['label' => $code, 'severity' => 'info'];
                            $active = $issueFilter === $code;
                        ?>
                            <a class="status-pill status-pill-<?= e($meta['severity']) ?>" style="<?= $active ? 'outline:2px solid #4f46e5' : '' ?>" href="<?= $buildAdminQueueUrl(['issue' => $active ? '' : $code, 'p' => 1]) ?>">
                                <?= e($meta['label']) ?>: <strong><?= number_format($cnt) ?></strong>
                            </a>
                        <?php endforeach; ?>
                        <?php if (!$stats['open_issue_counts']): ?>
                            <span class="muted">Không có vấn đề nào đang mở.</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <h3 style="font-size:.98rem;margin-bottom:6px">B. Ảnh chụp cảnh báo nguồn ban đầu từ HNV (chỉ để đối chiếu nguồn, không thay đổi khi xử lý)</h3>
                    <div style="display:flex;flex-wrap:wrap;gap:8px">
                        <?php foreach ($stats['source_warning_counts'] as $code => $cnt):
                            $meta = $issueLabels[$code] ?? ['label' => $code, 'severity' => 'info'];
                        ?>
                            <span class="tag"><?= e($meta['label']) ?>: <strong><?= number_format($cnt) ?></strong></span>
                        <?php endforeach; ?>
                        <?php if (!$stats['source_warning_counts']): ?>
                            <span class="muted">Chưa ghi nhận dữ liệu nguồn.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($stats['years']): ?>
                <p class="muted" style="margin-top:12px"><small>Phân bố tin tuyển sinh lịch sử:
                    <?php foreach ($stats['years'] as $yr => $cnt): ?>
                        <span class="tag"><?= $yr === 'unknown' ? 'Chưa rõ năm' : 'Năm tin ' . e((string)$yr) ?>: <?= number_format($cnt) ?></span>
                    <?php endforeach; ?>
                </small></p>
            <?php endif; ?>
        </section>

        <?php if ($editingMajor):
            $srcMeta = hnv_source_access_meta((string)$editingMajor['detail_status'], !empty($editingMajor['has_article_body']));
            $descStatus = (string)($editingMajor['description_status'] ?? 'not_edited');
            $descMeta = hnv_description_status_meta($descStatus);
            $codeStatus = (string)($editingMajor['code_verification_status'] ?? 'unverified');
            $codeMeta = hnv_code_verification_meta($codeStatus);
            $srcWarnings = hnv_decode_json_list($editingMajor['source_warnings_json'] ?? '[]');
            $observedCodes = hnv_decode_json_list($editingMajor['observed_major_codes_json'] ?? '[]');
            $srcUrl = safe_external_url($editingMajor['source_url'] ?? '');
            $codeCheckedDate = !empty($editingMajor['code_checked_at']) ? substr((string)$editingMajor['code_checked_at'], 0, 10) : date('Y-m-d');
        ?>
            <section class="card" style="margin-top:20px;border:2px solid #818cf8" id="verify-editor">
                <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;align-items:center">
                    <div>
                        <span class="eyebrow">Hồ sơ kiểm toán ngành</span>
                        <h2 style="margin-top:8px"><?= e(hnv_to_nfc((string)$editingMajor['name'])) ?> (<code><?= e($editingMajor['id']) ?></code>)</h2>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <a class="btn btn-outline" href="<?= url('index.php?page=majors&id=' . rawurlencode((string)$editingMajor['id'])) ?>" target="_blank" rel="noopener noreferrer">Xem trang public ↗</a>
                        <a class="btn btn-outline" href="<?= $buildAdminQueueUrl() ?>">Đóng khung sửa</a>
                    </div>
                </div>

                <div style="margin-top:12px;padding:12px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0">
                    <div><strong>Thông tin nguồn tham khảo HNV (chỉ đọc):</strong></div>
                    <p class="muted" style="margin-top:4px">
                        Lĩnh vực: <strong><?= e($editingMajor['field_name']) ?></strong> · Nhóm: <strong><?= e($editingMajor['subgroup_name']) ?></strong> ·
                        Nguồn: <span class="status-pill status-pill-<?= e($srcMeta['tone']) ?>"><?= e($srcMeta['label']) ?></span>
                    </p>
                    <p class="muted" style="margin-top:4px">
                        Mã ứng viên nguồn: <strong><?= e($editingMajor['source_major_code_candidate'] ?: 'Chưa trích được') ?></strong>
                        (<?= e(hnv_code_basis_label($editingMajor['major_code_basis'] ?? null)) ?>) ·
                        Mã DOM: <strong><?= e($editingMajor['source_dom_code'] ?: 'Không có') ?></strong> ·
                        Mã quan sát trong bảng trường: <strong><?= $observedCodes ? e(implode(', ', $observedCodes)) : 'Không ghi nhận' ?></strong>
                    </p>
                    <?php if ($srcUrl !== ''): ?>
                        <p style="margin-top:4px"><a href="<?= e($srcUrl) ?>" target="_blank" rel="noopener noreferrer">Mở URL nguồn HNV để đối chiếu ↗</a></p>
                    <?php endif; ?>
                    <?php if ($srcWarnings): ?>
                        <div style="margin-top:6px"><small class="muted">Cảnh báo nguồn ban đầu:</small>
                            <?php foreach ($srcWarnings as $wc): $wm = $issueLabels[$wc] ?? ['label' => $wc, 'severity' => 'info']; ?>
                                <span class="tag"><?= e($wm['label']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Block A: Independent JobSimu Description Workflow -->
                <div class="card" style="margin-top:16px" id="description-editor">
                    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;align-items:center">
                        <h3>Khối A. Mô tả JobSimu</h3>
                        <span class="status-pill status-pill-<?= e($descMeta['tone']) ?>"><?= e($descMeta['label']) ?></span>
                    </div>
                    <p class="muted"><small>Chỉ nội dung ở trạng thái <strong>Đã duyệt công khai</strong> mới hiển thị cho học sinh. Thao tác này không làm thay đổi trạng thái mã ngành chính thức hay các vấn đề nguồn khác.</small></p>
                    <form method="post" class="admin-form" style="margin-top:10px">
                        <?= csrf_field() ?>
                        <input type="hidden" name="intent" value="save_description">
                        <input type="hidden" name="major_id" value="<?= e($editingMajor['id']) ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="ed-desc-status">Trạng thái mô tả JobSimu</label>
                                <select id="ed-desc-status" name="description_status">
                                    <?php foreach (['not_edited' => 'Chưa biên tập', 'draft' => 'Bản nháp (lưu nội bộ)', 'approved' => 'Đã duyệt công khai'] as $k => $lbl): ?>
                                        <option value="<?= e($k) ?>" <?= $descStatus === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="ed-notes">Ghi chú biên tập nội bộ</label>
                                <input id="ed-notes" class="input" name="editorial_notes" value="<?= e($editingMajor['editorial_notes'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="ed-desc">Nội dung mô tả ngành trên JobSimu</label>
                            <textarea id="ed-desc" name="editorial_description" rows="4" placeholder="Nhập mô tả chính xác về mục tiêu đào tạo, môn học cốt lõi..."><?= e($editingMajor['editorial_description'] ?? '') ?></textarea>
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                            <input type="checkbox" name="is_editorially_locked" value="1" <?= !empty($editingMajor['is_editorially_locked']) ? 'checked' : '' ?>>
                            <span>Khóa bảo vệ khi chạy import lại</span>
                        </label>
                        <button class="btn btn-primary" type="submit">Lưu mô tả JobSimu</button>
                    </form>
                </div>

                <!-- Block B: Independent Official Code Verification with Evidence -->
                <div class="card" style="margin-top:16px" id="code-verification-editor">
                    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;align-items:center">
                        <h3>Khối B. Xác minh Mã ngành chính thức (kèm bằng chứng)</h3>
                        <span class="status-pill status-pill-<?= e($codeMeta['tone']) ?>"><?= e($codeMeta['label']) ?></span>
                    </div>
                    <?php if (!empty($editingMajor['code_verified_by']) && !empty($usersById[(int)$editingMajor['code_verified_by']])): ?>
                        <p class="muted"><small>Người xác minh gần nhất: <strong><?= e($usersById[(int)$editingMajor['code_verified_by']]) ?></strong> lúc <?= e(hnv_format_vn_datetime($editingMajor['code_checked_at'] ?? '')) ?></small></p>
                    <?php endif; ?>
                    <form method="post" class="admin-form" style="margin-top:10px">
                        <?= csrf_field() ?>
                        <input type="hidden" name="intent" value="save_code_verification">
                        <input type="hidden" name="major_id" value="<?= e($editingMajor['id']) ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="cv-status">Trạng thái xác minh mã ngành</label>
                                <select id="cv-status" name="code_verification_status">
                                    <?php foreach (['unverified' => 'Chưa xác minh', 'verified' => 'Đã xác minh mã chính thức', 'conflict' => 'Có xung đột cần đối chiếu'] as $k => $lbl): ?>
                                        <option value="<?= e($k) ?>" <?= $codeStatus === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="cv-code">Mã ngành chính thức (bắt buộc khi xác minh; tự xóa nếu chọn Xung đột/Chưa xác minh)</label>
                                <input id="cv-code" class="input" name="official_major_code" value="<?= e($editingMajor['official_major_code'] ?? '') ?>" placeholder="Ví dụ: 7480101">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="cv-ev-url">URL tài liệu/nguồn chứng minh (bắt buộc khi xác minh)</label>
                                <input id="cv-ev-url" class="input" type="url" name="code_evidence_url" value="<?= e($editingMajor['code_evidence_url'] ?? '') ?>" placeholder="https://moet.gov.vn/...">
                            </div>
                            <div class="form-group">
                                <label for="cv-ev-title">Tên văn bản / nguồn trích dẫn</label>
                                <input id="cv-ev-title" class="input" name="code_evidence_title" value="<?= e($editingMajor['code_evidence_title'] ?? '') ?>" placeholder="Ví dụ: Thông tư 09/2022/TT-BGDĐT">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="cv-date">Ngày kiểm tra (bắt buộc khi xác minh)</label>
                                <input id="cv-date" class="input" type="date" name="code_checked_at" value="<?= e($codeCheckedDate) ?>">
                            </div>
                            <div class="form-group">
                                <label for="cv-scope">Phạm vi áp dụng</label>
                                <input id="cv-scope" class="input" name="code_effective_scope" value="<?= e($editingMajor['code_effective_scope'] ?? '') ?>" placeholder="Ví dụ: Toàn quốc · Đại học chính quy">
                            </div>
                            <div class="form-group">
                                <label for="cv-year">Năm hiệu lực</label>
                                <input id="cv-year" class="input" type="number" min="1990" max="2100" name="code_effective_year" value="<?= e((string)($editingMajor['code_effective_year'] ?? '')) ?>" placeholder="2026">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="cv-note">Ghi chú kiểm tra / mô tả xung đột mã</label>
                            <textarea id="cv-note" name="code_verification_note" rows="2" placeholder="Ghi rõ cơ sở xác minh hoặc lý do xung đột (ví dụ: bảng nguồn chứa mã chuyên ngành 7540102A)..."><?= e($editingMajor['code_verification_note'] ?? '') ?></textarea>
                        </div>
                        <button class="btn btn-primary" type="submit">Lưu xác minh mã ngành</button>
                    </form>
                </div>

                <!-- Block C: Individual Review Issues -->
                <div class="card" style="margin-top:16px" id="issues-editor">
                    <h3>Khối C. Từng vấn đề đang theo dõi (độc lập — đóng 1 mục không tự đóng các mục khác)</h3>
                    <div class="table-wrap" style="margin-top:10px">
                        <table>
                            <thead>
                                <tr>
                                    <th>Vấn đề</th>
                                    <th>Chi tiết / Nguồn gốc</th>
                                    <th>Trạng thái</th>
                                    <th>Cập nhật riêng mục này</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($editingMajor['issues'] ?? [] as $iss):
                                $ic = (string)$iss['issue_code'];
                                $im = $issueLabels[$ic] ?? ['label' => $ic, 'severity' => 'info'];
                                $ist = (string)$iss['status'];
                            ?>
                                <tr>
                                    <td>
                                        <span class="status-pill status-pill-<?= e($im['severity']) ?>"><?= e($im['label']) ?></span><br>
                                        <small class="muted"><code><?= e($ic) ?></code></small>
                                    </td>
                                    <td>
                                        <div><?= e($iss['detail_note'] ?: '') ?></div>
                                        <?php if (!empty($iss['resolution_note'])): ?>
                                            <div><small class="muted">Ghi chú xử lý: <?= e($iss['resolution_note']) ?></small></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status status-<?= $ist === 'resolved' ? 'on' : 'off' ?>"><?= e($ist === 'resolved' ? 'Đã xử lý' : ($ist === 'deferred' ? 'Tạm hoãn' : 'Đang mở')) ?></span>
                                    </td>
                                    <td>
                                        <form method="post" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="intent" value="issue_status">
                                            <input type="hidden" name="major_id" value="<?= e($editingMajor['id']) ?>">
                                            <input type="hidden" name="issue_id" value="<?= (int)$iss['id'] ?>">
                                            <input class="input" style="max-width:190px;padding:6px 8px" name="resolution_note" value="<?= e($iss['resolution_note'] ?? '') ?>" placeholder="Ghi chú xử lý...">
                                            <?php if ($ist !== 'resolved'): ?>
                                                <button class="btn" type="submit" name="new_status" value="resolved">Đã xử lý</button>
                                            <?php endif; ?>
                                            <?php if ($ist !== 'deferred'): ?>
                                                <button class="btn btn-outline" type="submit" name="new_status" value="deferred">Tạm hoãn</button>
                                            <?php endif; ?>
                                            <?php if ($ist !== 'open'): ?>
                                                <button class="btn btn-outline" type="submit" name="new_status" value="open">Mở lại</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Block D: Per-Link Admission Verification -->
                <div class="card" style="margin-top:16px" id="links-editor">
                    <h3>Khối D. Trạng thái tin tuyển sinh theo từng nguồn trường và năm (<?= count($editingMajor['links'] ?? []) ?>)</h3>
                    <div class="table-wrap" style="margin-top:10px">
                        <table>
                            <thead>
                                <tr><th>Nguồn trường &amp; Năm tin</th><th>Hệ &amp; Mã quan sát</th><th>Trạng thái xác minh hiện tại</th><th>Cập nhật</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($editingMajor['links'] ?? [] as $lnk):
                                $admMeta = hnv_admission_link_status_meta((string)($lnk['admission_status'] ?? 'historical_unverified'));
                                $instUrl = safe_external_url($lnk['institution_source_url'] ?? '');
                                $obsLinkCodes = hnv_decode_json_list($lnk['observed_major_codes_json'] ?? '[]');
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= e($lnk['source_label'] ?: 'Chưa rõ tên nguồn') ?></strong><br>
                                        <span class="tag"><?= $lnk['source_news_year'] !== null ? 'Năm tin ' . (int)$lnk['source_news_year'] : 'Chưa rõ năm' ?></span>
                                        <?php if ($instUrl !== ''): ?>
                                            <a href="<?= e($instUrl) ?>" target="_blank" rel="noopener noreferrer"><small>URL nguồn ↗</small></a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?= e($lnk['level_label'] ?: 'Chưa ghi nhận hệ') ?></div>
                                        <?php if ($obsLinkCodes): ?>
                                            <div><small class="muted">Mã quan sát: <?= e(implode(', ', $obsLinkCodes)) ?></small></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-pill status-pill-<?= e($admMeta['tone']) ?>"><?= e($admMeta['label']) ?></span>
                                        <?php if (!empty($lnk['link_checked_at'])): ?>
                                            <div><small class="muted"><?= e(hnv_format_vn_datetime($lnk['link_checked_at'])) ?></small></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="intent" value="link_status">
                                            <input type="hidden" name="major_id" value="<?= e($editingMajor['id']) ?>">
                                            <input type="hidden" name="link_id" value="<?= (int)$lnk['id'] ?>">
                                            <select name="admission_status" style="max-width:210px">
                                                <?php foreach (['historical_unverified', 'source_verified_year', 'outdated_historical', 'source_unavailable', 'disputed'] as $ak):
                                                    $am = hnv_admission_link_status_meta($ak);
                                                ?>
                                                    <option value="<?= e($ak) ?>" <?= ($lnk['admission_status'] ?? 'historical_unverified') === $ak ? 'selected' : '' ?>><?= e($am['label']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input class="input" style="max-width:160px;padding:6px 8px" name="link_check_note" value="<?= e($lnk['link_check_note'] ?? '') ?>" placeholder="Ghi chú kiểm tra...">
                                            <button class="btn" type="submit">Lưu</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($editingMajor['links'])): ?>
                                <tr><td colspan="4" class="muted">Ngành này chưa có liên kết nguồn trường nào được trích xuất.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="card" style="margin-top:20px">
            <h2>2. Danh sách hàng chờ xác minh (<?= number_format($totalQueue) ?> mục)</h2>
            <form method="get" class="majors-filter-grid" style="margin-top:12px;margin-bottom:16px">
                <input type="hidden" name="section" value="majors-review">
                <div class="form-group">
                    <label for="rq-q">Tìm tên, ID hoặc mã ngành</label>
                    <input id="rq-q" class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Ví dụ: su pham, 7540102">
                </div>
                <div class="form-group">
                    <label for="rq-issue">Vấn đề còn mở</label>
                    <select id="rq-issue" name="issue">
                        <option value="">Tất cả vấn đề</option>
                        <?php foreach ($issueLabels as $code => $meta): ?>
                            <option value="<?= e($code) ?>" <?= $issueFilter === $code ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="rq-desc">Mô tả JobSimu</label>
                    <select id="rq-desc" name="desc_status">
                        <option value="">Tất cả</option>
                        <?php foreach (['not_edited' => 'Chưa biên tập', 'draft' => 'Bản nháp', 'approved' => 'Đã duyệt công khai'] as $k => $lbl): ?>
                            <option value="<?= e($k) ?>" <?= $descFilter === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="rq-code">Xác minh mã chính thức</label>
                    <select id="rq-code" name="code_status">
                        <option value="">Tất cả</option>
                        <?php foreach (['unverified' => 'Chưa xác minh', 'verified' => 'Đã xác minh', 'conflict' => 'Có xung đột'] as $k => $lbl): ?>
                            <option value="<?= e($k) ?>" <?= $codeFilter === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
                    <button class="btn btn-primary" type="submit">Lọc</button>
                    <a class="btn btn-outline" href="<?= url('admin.php?section=majors-review') ?>">Xóa lọc</a>
                </div>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Ngành đào tạo &amp; Nguồn</th>
                            <th>Mô tả JobSimu &amp; Mã chính thức</th>
                            <th>Vấn đề còn mở / Cảnh báo nguồn ban đầu</th>
                            <th>Tiến độ xử lý</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($queueRows as $row):
                        $mId = (string)$row['major_id'];
                        $openList = $openIssuesByMajor[$mId] ?? [];
                        $srcWarnings = hnv_decode_json_list($row['source_warnings_json'] ?? '[]');
                        $srcUrl = safe_external_url($row['source_url'] ?? '');
                        $descM = hnv_description_status_meta((string)($row['description_status'] ?? 'not_edited'));
                        $codeM = hnv_code_verification_meta((string)($row['code_verification_status'] ?? 'unverified'));
                        $qMeta = $queueStatusMeta((string)$row['review_status']);
                    ?>
                        <tr>
                            <td>
                                <strong><?= e(hnv_to_nfc((string)$row['name'])) ?></strong><br>
                                <small class="muted"><?= e($row['field_name']) ?> · <?= e($row['subgroup_name']) ?></small><br>
                                <small class="muted"><code><?= e($mId) ?></code></small>
                                <?php if ($srcUrl !== ''): ?>
                                    <a href="<?= e($srcUrl) ?>" target="_blank" rel="noopener noreferrer"><small>URL nguồn ↗</small></a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-pill status-pill-<?= e($descM['tone']) ?>"><?= e($descM['label']) ?></span><br>
                                <span class="status-pill status-pill-<?= e($codeM['tone']) ?>" style="margin-top:4px"><?= e($codeM['label']) ?></span>
                                <div style="margin-top:4px"><small>Ứng viên:</small> <strong><?= e($row['source_major_code_candidate'] ?: 'Chưa trích được') ?></strong> · <small>Chính thức:</small> <strong><?= e($row['official_major_code'] ?: 'Chưa xác minh') ?></strong></div>
                            </td>
                            <td>
                                <div><small class="muted">Còn mở (<?= count($openList) ?>):</small></div>
                                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:2px">
                                    <?php foreach ($openList as $iss): $im = $issueLabels[$iss] ?? ['label' => $iss, 'severity' => 'info']; ?>
                                        <span class="status-pill status-pill-<?= e($im['severity']) ?>"><?= e($im['label']) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (!$openList): ?>
                                        <span class="status-pill status-pill-ok">Không còn mục mở</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($srcWarnings): ?>
                                    <div style="margin-top:6px"><small class="muted">Cảnh báo nguồn ban đầu: <?= e(implode(', ', array_map(static fn($wc) => $issueLabels[$wc]['label'] ?? $wc, $srcWarnings))) ?></small></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status status-<?= e($qMeta['tone']) ?>"><?= e($qMeta['label']) ?></span>
                                <?php if (!empty($row['is_editorially_locked'])): ?>
                                    <br><small class="muted">🔒 Khóa bảo vệ re-import</small>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a class="btn" href="<?= url('admin.php?section=majors-review&edit=' . rawurlencode($mId) . '#verify-editor') ?>">Biên tập &amp; Xác minh</a>
                                <a class="btn btn-outline" href="<?= url('index.php?page=majors&id=' . rawurlencode($mId)) ?>" target="_blank" rel="noopener noreferrer">Public ↗</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$queueRows): ?>
                        <tr><td colspan="5" class="muted">Không có mục nào khớp bộ lọc.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($pageNum > 1): ?>
                        <a class="btn btn-outline" href="<?= $buildAdminQueueUrl(['p' => $pageNum - 1]) ?>">← Trước</a>
                    <?php endif; ?>
                    <span class="muted">Trang <?= $pageNum ?> / <?= $totalPages ?></span>
                    <?php if ($pageNum < $totalPages): ?>
                        <a class="btn btn-outline" href="<?= $buildAdminQueueUrl(['p' => $pageNum + 1]) ?>">Sau →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card" style="margin-top:20px" id="mappings">
            <h2>3. Kiểm toán từng gợi ý ánh xạ sang danh mục cũ (<?= count($mappings) ?> cặp · không duyệt hàng loạt)</h2>
            <p class="muted">Duyệt ánh xạ chỉ liên kết bản ghi HNV với bảng <code>majors</code> cũ để phục vụ đối chiếu tra cứu. <strong>Việc duyệt ánh xạ KHÔNG tự xác minh mã ngành chính thức</strong>; từng cặp bên dưới hiển thị đầy đủ bằng chứng và xung đột để kiểm tra thủ công.</p>
            <div class="table-wrap" style="margin-top:12px">
                <table>
                    <thead>
                        <tr>
                            <th>Ngành HNV &amp; Nguồn</th>
                            <th>Ngành starter (JobSimu cũ)</th>
                            <th>Bằng chứng &amp; Xung đột phát hiện</th>
                            <th>Trạng thái</th>
                            <th>Quyết định từng cặp</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mappings as $mp):
                        $ev = json_decode((string)($mp['evidence_json'] ?? ''), true) ?: [];
                        $mpMeta = hnv_mapping_status_meta((string)$mp['mapping_status']);
                        $obsCodes = is_array($ev['observed_major_codes'] ?? null) ? $ev['observed_major_codes'] : hnv_decode_json_list($mp['observed_major_codes_json'] ?? '[]');
                        $tmUrl = safe_external_url($mp['tm_source_url'] ?? ($ev['source_url'] ?? ''));
                    ?>
                        <tr>
                            <td>
                                <strong><?= e($mp['training_major_name']) ?></strong><br>
                                <small class="muted"><code><?= e($mp['training_major_id']) ?></code></small><br>
                                <small class="muted">Ứng viên: <strong><?= e($mp['source_major_code_candidate'] ?: 'Chưa trích được') ?></strong> · DOM: <?= e($mp['source_dom_code'] ?: 'Không có') ?></small>
                                <?php if ($tmUrl !== ''): ?>
                                    <br><a href="<?= e($tmUrl) ?>" target="_blank" rel="noopener noreferrer"><small>Nguồn HNV ↗</small></a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($mp['existing_name']) ?></strong><br>
                                <small>Mã starter: <strong><?= e($mp['existing_code']) ?></strong></small>
                            </td>
                            <td>
                                <div><strong><?= e(hnv_match_basis_label((string)$mp['match_basis'])) ?></strong></div>
                                <small class="muted">Mã quan sát bảng nguồn: <?= $obsCodes ? e(implode(', ', $obsCodes)) : 'Không có' ?> · Số link trường: <?= (int)($ev['school_listing_count_extracted'] ?? 0) ?></small>
                                <?php if (!empty($mp['has_conflict']) || !empty($mp['conflict_notes'])): ?>
                                    <div style="margin-top:6px">
                                        <span class="status-pill status-pill-error">Xung đột / Cần kiểm tra</span>
                                        <div style="margin-top:4px"><small><?= e($mp['conflict_notes']) ?></small></div>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-top:6px"><span class="status-pill status-pill-info">Không thấy xung đột tự động (vẫn chờ xác minh thủ công)</span></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-pill status-pill-<?= e($mpMeta['tone']) ?>"><?= e($mpMeta['label']) ?></span>
                                <?php if (!empty($mp['reviewer_name'])): ?>
                                    <div><small class="muted">Bởi <?= e($mp['reviewer_name']) ?> · <?= e(hnv_format_vn_datetime($mp['reviewed_at'])) ?></small></div>
                                <?php endif; ?>
                                <?php if (!empty($mp['review_note'])): ?>
                                    <div><small class="muted">Ghi chú: <?= e($mp['review_note']) ?></small></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" style="display:flex;flex-direction:column;gap:6px;min-width:190px">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="intent" value="mapping_decision">
                                    <input type="hidden" name="mapping_id" value="<?= (int)$mp['id'] ?>">
                                    <input class="input" style="padding:6px 8px" name="review_note" value="<?= e($mp['review_note'] ?? '') ?>" placeholder="Ghi chú duyệt cặp...">
                                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                                        <button class="btn" type="submit" name="decision" value="approved">Duyệt ánh xạ</button>
                                        <button class="btn btn-danger" type="submit" name="decision" value="rejected">Từ chối</button>
                                        <?php if ($mp['mapping_status'] !== 'pending_review'): ?>
                                            <button class="btn btn-outline" type="submit" name="decision" value="pending_review">Đặt lại chờ</button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$mappings): ?>
                        <tr><td colspan="5" class="muted">Chưa có gợi ý ánh xạ nào.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card" style="margin-top:20px" id="import-history">
            <h2>4. Lịch sử chạy Import thực tế</h2>
            <p class="muted">Kiểm tra xem trước (dry-run): <code>php scripts/import_hnv_majors.php --dry-run</code> · Nhập chính thức: <code>php scripts/import_hnv_majors.php</code></p>
            <div class="table-wrap" style="margin-top:12px">
                <table>
                    <thead>
                        <tr><th>Lần chạy</th><th>Nguồn &amp; Phiên bản</th><th>Trạng thái</th><th>Kết quả từng lần chạy</th><th>Người chạy &amp; Thời gian (UTC)</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($importRuns as $run):
                        $sum = json_decode((string)$run['summary_json'], true) ?: [];
                        $tot = $sum['totals'] ?? [];
                        $tables = $sum['tables'] ?? [];
                        $isOk = ($run['status'] ?? '') === 'completed';
                    ?>
                        <tr>
                            <td>#<?= (int)$run['id'] ?></td>
                            <td>
                                <strong><?= e($run['source_name']) ?></strong> (v<?= e($run['source_version'] ?: '?') ?>)<br>
                                <small class="muted">Thu thập nguồn: <?= e(hnv_format_utc_datetime($run['source_retrieved_at'])) ?></small>
                            </td>
                            <td>
                                <span class="status status-<?= $isOk ? 'on' : 'off' ?>"><?= e($isOk ? 'Hoàn tất' : 'Thất bại') ?></span><br>
                                <small class="muted"><?= e($run['mode'] === 'dry_run' ? 'Chạy thử' : 'Nhập thật') ?></small>
                            </td>
                            <td>
                                <?php if ($isOk): ?>
                                    <div>
                                        Thêm: <strong><?= number_format((int)($tot['inserted'] ?? 0)) ?></strong> ·
                                        Cập nhật: <strong><?= number_format((int)($tot['updated'] ?? 0)) ?></strong> ·
                                        Bỏ qua: <strong><?= number_format((int)($tot['skipped'] ?? 0)) ?></strong> ·
                                        Bảo toàn nội dung khóa/duyệt: <strong><?= number_format((int)($tot['preserved_editorial'] ?? 0)) ?></strong>
                                    </div>
                                    <?php if ($tables): ?>
                                        <small class="muted">
                                            Ngành (+<?= (int)($tables['training_majors']['inserted'] ?? 0) ?>/bảo toàn <?= (int)($tables['training_majors']['preserved_editorial'] ?? 0) ?>) ·
                                            Link (+<?= (int)($tables['major_institution_source_links']['inserted'] ?? 0) ?>) ·
                                            Vấn đề (+<?= (int)($tables['major_review_issues']['inserted'] ?? 0) ?>)
                                        </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="status-pill status-pill-error">Lỗi: <?= e((string)($sum['error'] ?? 'Không xác định')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= e($run['initiator_name'] ?: 'CLI / Hệ thống') ?></div>
                                <small class="muted">Bắt đầu: <?= e((string)$run['started_at']) ?><?= !empty($run['finished_at']) ? ' → ' . e((string)$run['finished_at']) : '' ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$importRuns): ?>
                        <tr><td colspan="5" class="muted">Chưa có lần chạy import thực tế nào được ghi nhận trong bảng <code>dataset_import_runs</code>.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
<?php exit;
