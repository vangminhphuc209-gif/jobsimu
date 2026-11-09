<?php
require_login();
$assessmentId = (int)($_GET['id'] ?? $_POST['assessment_id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM assessments WHERE id=? AND user_id=?');
$stmt->execute([$assessmentId, user()['id']]);
$assessment = $stmt->fetch();
if (!$assessment) { http_response_code(404); exit('Không tìm thấy bài đánh giá.'); }

if (is_post()) {
    verify_csrf();
    if (!empty($assessment['ai_generated_at']) && time() - strtotime((string)$assessment['ai_generated_at']) < 180) {
        flash('warning', 'Vui lòng đợi 3 phút giữa các lần tạo tư vấn AI.');
        redirect('index.php?page=result&id='.$assessmentId);
    }
    $allowed = [
        'budget' => ['0','1','2','3','unknown'],
        'study_path' => ['any','university','college','vocational'],
        'study_years' => ['any','short','long'],
        'activity' => ['any','low','medium','high'],
        'scholarship' => ['yes','no','unknown'],
        'portfolio' => ['yes','no','unknown'],
    ];
    $answers = [];
    $skipSurvey = !empty($_POST['skip_survey']);
    foreach ($allowed as $key => $choices) {
        $defaultVal = ($key === 'budget' || $key === 'scholarship' || $key === 'portfolio') ? 'unknown' : 'any';
        $value = $skipSurvey ? $defaultVal : (string)($_POST[$key] ?? $defaultVal);
        if (!in_array($value, $choices, true)) {
            http_response_code(422);
            exit('Một hoặc nhiều câu trả lời không hợp lệ.');
        }
        $answers[$key] = $value;
    }

    // Realistic Job Sacrifice Checklist (v3.0.0)
    $validSacrificeKeys = array_keys(realistic_sacrifice_labels());
    $avoidSacrifices = [];
    if (!$skipSurvey && is_array($_POST['avoid_sacrifices'] ?? null)) {
        foreach (array_slice($_POST['avoid_sacrifices'], 0, 3) as $sac) {
            $sacStr = (string)$sac;
            if (in_array($sacStr, $validSacrificeKeys, true)) {
                $avoidSacrifices[] = $sacStr;
            }
        }
    }
    $answers['avoid_sacrifices'] = array_values(array_unique($avoidSacrifices));

    // Task-Specific Self-Efficacy (SCCT Dual-Matrix: 1..5 per RIASEC domain)
    $rawAnswers = json_decode($assessment['answers'] ?? '{}', true) ?: [];
    $questions = db()->query("SELECT * FROM questions WHERE status='active' ORDER BY sort_order,id")->fetchAll();
    $postedEfficacy = (!$skipSurvey && is_array($_POST['efficacy'] ?? null)) ? $_POST['efficacy'] : [];
    $cleanEfficacy = [];
    foreach (array_keys(riasec_labels()) as $rType) {
        if (isset($postedEfficacy[$rType]) && preg_match('/^[1-5]$/D', (string)$postedEfficacy[$rType])) {
            $cleanEfficacy[$rType] = (int)$postedEfficacy[$rType];
        }
    }

    $existingValidity = json_decode($assessment['validity_metrics'] ?? '{}', true) ?: [];
    $durationSec = (int)($existingValidity['duration_seconds'] ?? 0);
    $scientific = score_assessment_scientific($rawAnswers, $questions, $cleanEfficacy, $durationSec);

    $latestPsych = four_hub_latest_psychometrics((int)user()['id']);
    $big5Scores = $latestPsych['big5']['scores'] ?? [];

    $matches = career_matches(
        json_decode($assessment['scores'], true) ?: $scientific['raw_scores'],
        6,
        $answers,
        [
            'ipsative_scores' => $scientific['ipsative_scores'],
            'efficacy_scores' => $scientific['efficacy_scores'],
            'big5_scores' => $big5Scores,
        ]
    );

    db()->beginTransaction();
    try {
        $save = db()->prepare('UPDATE assessments SET constraint_answers=?,efficacy_scores=?,ipsative_z_scores=?,validity_metrics=?,ai_advice=NULL,ai_status=\'pending\',ai_model=NULL,ai_error=NULL,ai_generated_at=NULL WHERE id=? AND user_id=?');
        $save->execute([
            json_encode($answers, JSON_UNESCAPED_UNICODE),
            json_encode($scientific['efficacy_scores'], JSON_UNESCAPED_UNICODE),
            json_encode($scientific['ipsative_z_scores'], JSON_UNESCAPED_UNICODE),
            json_encode($scientific['validity_metrics'], JSON_UNESCAPED_UNICODE),
            $assessmentId,
            user()['id']
        ]);
        db()->prepare('DELETE FROM assessment_matches WHERE assessment_id=?')->execute([$assessmentId]);
        $insert = db()->prepare('INSERT INTO assessment_matches(assessment_id,career_id,match_percent,rank_order) VALUES(?,?,?,?)');
        foreach ($matches as $i => $career) {
            $insert->execute([$assessmentId, $career['id'], $career['match_percent'], $i + 1]);
        }
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
    generate_assessment_ai($assessmentId, (int)user()['id']);
    audit('Hoàn thành khảo sát điều kiện & tự hiệu quả SCCT', 'assessment', $assessmentId);
    redirect('index.php?page=result&id='.$assessmentId);
}

$pageTitle = 'Tự hiệu quả SCCT & Điều kiện thực tế';
require __DIR__.'/header.php';
?>
<div class="container constraints-page"><div class="assessment-wrap"><div class="mb-5"><span class="ai-badge">Bước 2 / 2 · Mô hình Nhận thức Xã hội (SCCT) & Đánh đổi Thực tế</span><h1 class="mt-3 text-3xl font-black">Tự tin năng lực & Điều kiện chọn ngành</h1><p class="muted">Theo Lý thuyết Nhận thức Xã hội về Nghề nghiệp (SCCT — Lent, Brown & Hackett), sở thích cần đi kèm niềm tin năng lực (Self-Efficacy) và sự chấp nhận đánh đổi thực tế.</p></div>
<form method="post" data-submit-loading><?=csrf_field()?><input type="hidden" name="assessment_id" value="<?=$assessmentId?>">

<section class="card constraints-section">
    <h2>1. Tự đánh giá Mức độ Tự tin Năng lực (Task Self-Efficacy)</h2>
    <p class="muted">Khác với việc "thích", hãy đánh giá mức độ bạn <b>tự tin mình có thể thực hiện tốt hoặc rèn luyện thành thạo</b> từng nhóm kỹ năng dưới đây (1 = Rất thiếu tự tin, 5 = Rất tự tin):</p>
    <div class="form-row">
    <?php foreach (riasec_labels() as $rKey => $rLabel): $meta = riasec_question_meta($rKey, 1); ?>
        <div class="form-group">
            <label for="eff-<?=e($rKey)?>"><?=e($meta['icon'].' '.$rKey.' · '.$rLabel)?> — <small class="muted"><?=e($meta['efficacy_prompt'])?></small></label>
            <select class="input" id="eff-<?=e($rKey)?>" name="efficacy[<?=e($rKey)?>]">
                <option value="3" selected>3 · Tự tin trung bình / Đang học hỏi</option>
                <option value="1">1 · Rất thiếu tự tin / Ngại thực hiện</option>
                <option value="2">2 · Còn lúng túng khi làm thực tế</option>
                <option value="4">4 · Khá tự tin có thể làm tốt</option>
                <option value="5">5 · Rất tự tin & có kinh nghiệm thực hành</option>
            </select>
        </div>
    <?php endforeach; ?>
    </div>
</section>

<section class="card constraints-section">
    <h2>2. Cổng Đánh đổi Thực tế (Realistic Job Sacrifice Gate)</h2>
    <p class="muted">Mỗi nghề đều có "chi phí cơ hội" và mặt trái đặc thù. Hãy đánh dấu những áp lực mà bạn <b>CẢM THẤY KHÔNG PHÙ HỢP HOẶC MUỐN TRÁNH</b> (chọn tối đa 2 mục):</p>
    <div style="display:grid;gap:10px;margin-top:12px">
    <?php foreach (realistic_sacrifice_labels() as $sKey => $sLabel): ?>
        <label style="display:flex;align-items:flex-start;gap:10px;padding:12px;border:1px solid var(--line);border-radius:12px;cursor:pointer">
            <input type="checkbox" name="avoid_sacrifices[]" value="<?=e($sKey)?>" style="margin-top:4px">
            <span><?=e($sLabel)?></span>
        </label>
    <?php endforeach; ?>
    </div>
</section>

<section class="card constraints-section"><h2>3. Khả năng đầu tư và lộ trình đào tạo</h2><div class="form-row"><div class="form-group"><label for="budget">Gia đình có thể dự trù học phí khoảng bao nhiêu mỗi năm?</label><select class="input" id="budget" name="budget"><option value="unknown">Chưa rõ / chưa muốn trả lời</option><option value="0">Dưới 20 triệu đồng</option><option value="1">20–50 triệu đồng</option><option value="2">50–100 triệu đồng</option><option value="3">Trên 100 triệu đồng</option></select></div><div class="form-group"><label for="scholarship">Bạn có muốn ưu tiên học bổng hoặc vừa học vừa làm?</label><select class="input" id="scholarship" name="scholarship"><option value="unknown">Chưa rõ</option><option value="yes">Có, hãy gợi ý thêm phương án</option><option value="no">Chưa cần</option></select></div></div><div class="form-row"><div class="form-group"><label for="study_path">Bạn đang nghiêng về lộ trình nào?</label><select class="input" id="study_path" name="study_path"><option value="any">Chưa quyết định</option><option value="university">Đại học</option><option value="college">Cao đẳng</option><option value="vocational">Trung cấp / học nghề</option></select></div><div class="form-group"><label for="study_years">Bạn sẵn sàng dành bao lâu cho đào tạo ban đầu?</label><select class="input" id="study_years" name="study_years"><option value="any">Linh hoạt / chưa rõ</option><option value="short">Khoảng 1–3 năm</option><option value="long">4 năm trở lên</option></select></div></div><div class="form-row"><div class="form-group"><label for="activity">Bạn thấy thoải mái với công việc vận động thực địa ở mức nào?</label><select class="input" id="activity" name="activity"><option value="any">Chưa rõ</option><option value="low">Ưu tiên vận động nhẹ</option><option value="medium">Có thể đứng/đi lại thường xuyên</option><option value="high">Thoải mái với vận động cường độ cao</option></select></div><div class="form-group"><label for="portfolio">Bạn sẵn sàng luyện năng khiếu hoặc làm portfolio nếu ngành yêu cầu?</label><select class="input" id="portfolio" name="portfolio"><option value="unknown">Chưa rõ</option><option value="yes">Có, mình sẵn sàng luyện</option><option value="no">Chưa muốn theo lộ trình này</option></select></div></div><p class="form-hint">Mức đầu tư trong gợi ý nghề chỉ là ước lượng tương đối, không phải học phí của một trường cụ thể.</p></section>

<div class="constraints-actions"><a class="btn btn-outline" href="<?=url('index.php?page=assessment')?>">← Làm lại Holland</a><button class="btn btn-outline" type="submit" name="skip_survey" value="1">Bỏ qua khảo sát</button><button class="btn btn-primary btn-lg" type="submit">Phân tích Ma trận SCCT & Xem kết quả →</button></div></form><p class="form-hint mt-4">Danh mục nhóm ngành tham khảo: <a href="https://huongnghiepviet.com/nganh-nghe" target="_blank" rel="noopener">Hướng Nghiệp Việt</a>.</p></div></div>
<?php require __DIR__.'/footer.php'; exit; ?>
