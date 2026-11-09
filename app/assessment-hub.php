<?php
declare(strict_types=1);
require_once __DIR__.'/psychometrics.php';
require_login();
$studentId = (int)user()['id'];
$typeMap = [
    'test-big5' => 'big5',
    'test-caas' => 'caas',
    'test-mbti' => 'mbti',
    'test-eq' => 'eq',
];
$testType = $typeMap[$page] ?? null;
$testError = '';
$submittedAnswers = [];

if ($testType !== null && is_post()) {
    verify_csrf();
    try {
        if (!is_array($_POST['answers'] ?? null)) throw new InvalidArgumentException('Vui lòng trả lời đầy đủ các câu hỏi.');
        $submittedAnswers = $_POST['answers'];
        $durationSec = max(0, min(7200, (int)($_POST['duration_seconds'] ?? 0)));
        $scored = score_psychometric($testType, $submittedAnswers, $durationSec);
        $statement = db()->prepare('INSERT INTO user_psychometrics(user_id,test_type,answers_json,scores_json,result_code,summary_label,duration_seconds,validity_flag) VALUES(?,?,?,?,?,?,?,?)');
        $statement->execute([
            $studentId,
            $testType,
            json_encode($scored['answers'], JSON_UNESCAPED_UNICODE),
            json_encode($scored['scores'], JSON_UNESCAPED_UNICODE),
            $scored['result_code'],
            $scored['summary_label'],
            $durationSec,
            $scored['validity_flag'] ?? 'valid',
        ]);
        $_SESSION['psychometric_saved'] = ['user_id'=>$studentId, 'type'=>$testType];
        if (!empty($scored['validity']['warning_message'])) {
            flash('warning', $scored['validity']['warning_message']);
        } else {
            flash('success', 'Đã lưu kết quả chuẩn hóa. Bạn có thể đối chiếu với trải nghiệm thực tế.');
        }
        redirect('index.php?page=assessment-report');
    } catch (InvalidArgumentException $error) {
        http_response_code(422);
        $testError = $error->getMessage();
    }
}

$latestPsych = four_hub_latest_psychometrics($studentId);
$statement = db()->prepare('SELECT * FROM assessments WHERE user_id=? ORDER BY completed_at DESC,id DESC LIMIT 1');
$statement->execute([$studentId]);
$latestHolland = $statement->fetch() ?: null;

$titleByType = [
    'big5' => 'Big Five (Mini-IPIP) · 5 Nhân tố Tính cách Khoa học',
    'caas' => 'CAAS · Thang đo Khả năng Thích ứng Nghề nghiệp',
    'mbti' => 'MBTI rút gọn · Khám phá xu hướng',
    'eq' => 'EQ · Tự phản tư cảm xúc',
];
$pageTitle = $testType !== null ? $titleByType[$testType] : ($page === 'assessment-report' ? 'Báo cáo đánh giá tổng quan' : 'Đánh giá bản thân');
require __DIR__.'/header.php';
?>
<section class="page-head"><div class="container"><span class="eyebrow">Hub 2 · Đo lường Tâm lý học & Đối chiếu Thực chứng</span><h1><?=e($pageTitle)?></h1><p>Kết hợp Ma trận Sở thích – Tự hiệu quả (SCCT), Nhân cách Big Five (IPIP), Năng lực Thích ứng (CAAS) và Bằng chứng Mô phỏng.</p></div></section>
<div class="container">
<div class="alert alert-warning" role="note">Hệ thống sử dụng các thang đo chuẩn hóa mở (O*NET RIASEC, Mini-IPIP Big Five, Savickas CAAS) có câu hỏi đảo chiều và kiểm định độ nhất quán phản hồi. Kết quả phục vụ khám phá điểm mạnh và điểm mù nhận thức, không thay thế chẩn đoán lâm sàng.</div>
<?php if ($page === 'assessment-hub'): ?>
<div class="grid-3">
<?php foreach ([
    ['title'=>'Holland (SCCT-RIASEC) · Sở thích & Tự hiệu quả','desc'=>'30 câu hỏi có chuẩn hóa nội tại (Ipsative Z-score), kết hợp đánh giá Tự hiệu quả SCCT và Cổng Đánh đổi Thực tế.','route'=>'assessment','done'=>$latestHolland && !empty($latestHolland['constraint_answers']),'tag'=>'Trụ cột chính · O*NET & SCCT'],
    ['title'=>'Big Five (Mini-IPIP) · 5 Nhân tố Tính cách','desc'=>'20 câu chuẩn International Personality Item Pool (O-C-E-A-N) có câu hỏi đảo chiều chống thiên kiến đồng thuận.','route'=>'test-big5','done'=>isset($latestPsych['big5']),'tag'=>'Chuẩn khoa học · IPIP'],
    ['title'=>'CAAS · Khả năng Thích ứng Nghề nghiệp','desc'=>'12 câu đo lường 4 nguồn lực tâm lý xã hội (Concern, Control, Curiosity, Confidence — Savickas & Porfeli).','route'=>'test-caas','done'=>isset($latestPsych['caas']),'tag'=>'Chuẩn khoa học · CAAS-SF'],
    ['title'=>'MBTI rút gọn · Xu hướng tự mô tả (Tham khảo)','desc'=>'12 câu tự mô tả về cách tương tác, tiếp nhận thông tin và lựa chọn.','route'=>'test-mbti','done'=>isset($latestPsych['mbti']),'tag'=>'Tự phản tư'],
    ['title'=>'EQ · Tự phản tư cảm xúc (Tham khảo)','desc'=>'10 tình huống tự phản tư về cảm xúc, động lực và hợp tác.','route'=>'test-eq','done'=>isset($latestPsych['eq']),'tag'=>'Tự phản tư'],
    ['title'=>'Báo cáo & Phát hiện Điểm mù (SOKA / SCCT)','desc'=>'Đối chiếu đa nguồn giữa Tự khai (RIASEC, Big Five, CAAS) và Hành vi thực tế trong Phòng mô phỏng.','route'=>'assessment-report','done'=>false,'tag'=>'Báo cáo tổng hợp'],
] as $card): ?>
<article class="card"><span class="tag"><?=$card['done']?'Đã có kết quả':e($card['tag'])?></span><h2><?=e($card['title'])?></h2><p><?=e($card['desc'])?></p><a class="btn btn-primary" href="<?=e(url('index.php?page='.$card['route']))?>"><?=$card['done']?'Xem / làm lại':'Bắt đầu'?> →</a></article>
<?php endforeach; ?>
</div>
<?php if ($latestHolland && empty($latestHolland['constraint_answers'])): ?><p><a class="btn btn-outline" href="<?=e(url('index.php?page=constraints&id='.(int)$latestHolland['id']))?>">Hoàn tất khảo sát Tự hiệu quả SCCT & Điều kiện học tập →</a></p><?php endif; ?>
<?php elseif ($testType !== null):
    $bank = psychometric_questions($testType);
    $draftKey = 'jobsimu_psychometric_v1_'.$studentId.'_'.$testType;
?>
<p><a href="<?=e(url('index.php?page=assessment-hub'))?>">← Tất cả bài đánh giá</a></p>
<p>Hãy đọc kỹ từng mệnh đề (bao gồm cả các mệnh đề thuận và mệnh đề ngược chiều) và chọn mức độ phản ánh sát nhất trải nghiệm thực tế của bạn trong vài tháng gần đây.</p>
<?php if ($testError): ?><div class="alert alert-error" role="alert"><?=e($testError)?></div><?php endif; ?>
<p id="psychDraftStatus" role="status" aria-live="polite">Bản nháp được lưu trên thiết bị này khi trình duyệt cho phép.</p>
<form method="post" id="psychometricForm" action="<?=e(url('index.php?page=test-'.$testType))?>">
<?=csrf_field()?>
<input type="hidden" name="duration_seconds" id="psychDurationSeconds" value="0">
<?php $questionNumber=0; foreach ($bank as $key=>$question): $questionNumber++; ?>
<fieldset class="card" style="margin-bottom:20px"><legend style="font-weight:700;padding:0 8px"><?=$questionNumber?>. <?=e($question['text'])?></legend>
<div style="display:flex;flex-wrap:wrap;gap:12px">
<?php foreach ([1=>'Hầu như không đúng',2=>'Ít đúng',3=>'Tùy hoàn cảnh',4=>'Khá đúng',5=>'Rất đúng'] as $value=>$label): ?>
<label style="display:flex;align-items:center;gap:8px;padding:12px;border:1px solid var(--line);border-radius:12px;cursor:pointer"><input type="radio" name="answers[<?=e($key)?>]" value="<?=$value?>" data-answer-key="<?=e($key)?>" required <?=isset($submittedAnswers[$key]) && is_scalar($submittedAnswers[$key]) && (string)$submittedAnswers[$key]===(string)$value?'checked':''?>> <?=$value?> · <?=e($label)?></label>
<?php endforeach; ?></div></fieldset>
<?php endforeach; ?>
<div style="display:flex;gap:12px;flex-wrap:wrap;margin:24px 0"><button class="btn btn-primary" type="submit">Lưu & xem báo cáo →</button><button class="btn btn-outline" type="button" id="psychReset">Xóa bản nháp</button></div>
</form>
<script>
(function () {
    'use strict';
    const startedAt = Date.now();
    const form = document.getElementById('psychometricForm');
    const durationInput = document.getElementById('psychDurationSeconds');
    const status = document.getElementById('psychDraftStatus');
    const key = <?=json_encode($draftKey)?>;
    const fields = Array.from(form.querySelectorAll('[data-answer-key]'));
    let storageAvailable = true;
    function save() {
        const answers = {};
        fields.forEach(function (field) { if (field.checked) answers[field.dataset.answerKey] = field.value; });
        try { localStorage.setItem(key, JSON.stringify(answers)); status.textContent = 'Đã lưu bản nháp trên thiết bị này.'; }
        catch (error) { storageAvailable = false; status.textContent = 'Trình duyệt không cho lưu nháp. Bạn vẫn có thể gửi bài bình thường.'; }
    }
    try {
        const draft = JSON.parse(localStorage.getItem(key) || '{}');
        if (draft && typeof draft === 'object' && !Array.isArray(draft) && !<?=json_encode($testError !== '')?>) {
            fields.forEach(function (field) { field.checked = String(draft[field.dataset.answerKey]) === field.value; });
        }
    } catch (error) { storageAvailable = false; status.textContent = 'Không thể khôi phục bản nháp. Bạn vẫn có thể làm bài.'; }
    form.addEventListener('change', save);
    form.addEventListener('submit', function () {
        if (durationInput) durationInput.value = String(Math.max(1, Math.round((Date.now() - startedAt) / 1000)));
    });
    document.getElementById('psychReset').addEventListener('click', function () {
        if (!window.confirm('Xóa câu trả lời nháp trên thiết bị này? Kết quả đã gửi vẫn được giữ.')) return;
        fields.forEach(function (field) { field.checked = false; });
        try { localStorage.removeItem(key); } catch (error) { storageAvailable = false; }
        status.textContent = storageAvailable ? 'Đã xóa bản nháp.' : 'Đã xóa các lựa chọn đang hiển thị; không truy cập được bộ nhớ trình duyệt.';
    });
})();
</script>
<?php elseif ($page === 'assessment-report'):
    $saved = $_SESSION['psychometric_saved'] ?? null;
    if (is_array($saved) && ($saved['user_id'] ?? null) === $studentId && in_array($saved['type'] ?? '', ['big5','caas','mbti','eq'], true)):
        unset($_SESSION['psychometric_saved']);
?>
<script>try { localStorage.removeItem(<?=json_encode('jobsimu_psychometric_v1_'.$studentId.'_'.$saved['type'])?>); } catch (error) {}</script>
<?php endif;
    $discrepancyData = calculate_self_insight_discrepancy($studentId);
?>
<p><a href="<?=e(url('index.php?page=assessment-hub'))?>">← Bộ công cụ đánh giá</a></p>

<section class="card">
    <h2>1. Holland (SCCT-RIASEC) · Sở thích, Chuẩn hóa Nội tại & Tự hiệu quả</h2>
<?php if ($latestHolland):
    $hollandScores = json_decode($latestHolland['scores'], true) ?: [];
    $efficacyScores = json_decode($latestHolland['efficacy_scores'] ?? '{}', true) ?: [];
    $zScores = json_decode($latestHolland['ipsative_z_scores'] ?? '{}', true) ?: [];
    $validityMetrics = json_decode($latestHolland['validity_metrics'] ?? '{}', true) ?: [];
?>
    <p>Mã gần nhất: <strong><?=e($latestHolland['result_code'])?></strong> · <?=e($latestHolland['completed_at'])?>
    <?php if (!empty($validityMetrics['validity_flag']) && $validityMetrics['validity_flag'] !== 'valid'): ?>
        <span class="tag" style="background:#fef3c7;color:#92400e">⚠️ Lưu ý độ phân hóa: <?=e($validityMetrics['validity_flag'])?></span>
    <?php endif; ?>
    </p>
    <?php if (!empty($validityMetrics['warning_message'])): ?>
        <p class="muted"><?=e($validityMetrics['warning_message'])?></p>
    <?php endif; ?>
    <div class="score-grid">
    <?php foreach (riasec_labels() as $axis => $label):
        $percent = max(0, min(100, (int)($hollandScores[$axis] ?? 0)));
        $effPct = isset($efficacyScores[$axis]) ? max(0, min(100, (int)$efficacyScores[$axis])) : null;
        $zVal = isset($zScores[$axis]) ? (float)$zScores[$axis] : null;
    ?>
        <div class="score-item">
            <div class="score-ring" style="--v:<?=$percent?>;--c:<?=e(riasec_colors()[$axis])?>"><span><?=$percent?>%</span></div>
            <b><?=e($label)?> (<?=e($axis)?>)</b>
            <?php if ($zVal !== null): ?><small class="muted">Z nội tại: <?=($zVal >= 0 ? '+' : '').number_format($zVal, 2)?></small><?php endif; ?>
            <?php if ($effPct !== null): ?><small class="muted">Tự hiệu quả: <?=$effPct?>%</small><?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
    <p><a class="btn btn-outline" href="<?=e(url('index.php?page=result&id='.(int)$latestHolland['id']))?>">Xem chi tiết kết quả Holland & Gợi ý nghề</a></p>
<?php else: ?>
    <p>Chưa có kết quả Holland. Các phần còn thiếu sẽ không được suy đoán.</p>
    <a class="btn btn-primary" href="<?=e(url('index.php?page=assessment'))?>">Làm bài Holland</a>
<?php endif; ?>
</section>

<div class="grid-3">
    <section class="card">
        <h2>2. Nhân cách Big Five (Mini-IPIP)</h2>
        <?php if (!empty($latestPsych['big5'])):
            $big5 = $latestPsych['big5'];
            $big5Scores = $big5['scores'] ?? [];
            $big5Insights = psychometric_big5_insights($big5Scores);
        ?>
            <p style="font-size:1.3rem;font-weight:800"><?=e($big5['result_code'])?>
            <?php if (($big5['validity_flag'] ?? 'valid') !== 'valid'): ?>
                <span class="tag" style="background:#fef3c7;color:#92400e">⚠️ <?=e($big5['validity_flag'])?></span>
            <?php endif; ?>
            </p>
            <?php foreach (psychometric_big5_labels() as $axis => $label):
                $val = max(0, min(100, (int)($big5Scores[$axis] ?? 50)));
            ?>
                <p><b><?=e($label)?> · <?=$val?>%</b></p>
                <meter min="0" max="100" value="<?=$val?>" style="width:100%;height:16px"><?=$val?>%</meter>
                <p class="muted" style="font-size:0.85rem;margin-bottom:10px"><?=e($big5Insights[$axis] ?? '')?></p>
            <?php endforeach; ?>
            <small><?=e($big5['completed_at'])?></small>
        <?php else: ?>
            <p>Chưa có hồ sơ Big Five (Mini-IPIP 20 câu chuẩn hóa).</p>
        <?php endif; ?>
        <p><a class="btn btn-outline" href="<?=e(url('index.php?page=test-big5'))?>">Làm bài Big Five (Mini-IPIP) →</a></p>
    </section>

    <section class="card">
        <h2>3. Thích ứng Nghề nghiệp (CAAS)</h2>
        <?php if (!empty($latestPsych['caas'])):
            $caas = $latestPsych['caas'];
            $caasScores = $caas['scores'] ?? [];
            $caasInsights = psychometric_caas_insights($caasScores);
        ?>
            <p style="font-size:1.3rem;font-weight:800"><?=e($caas['result_code'])?>
            <?php if (($caas['validity_flag'] ?? 'valid') !== 'valid'): ?>
                <span class="tag" style="background:#fef3c7;color:#92400e">⚠️ <?=e($caas['validity_flag'])?></span>
            <?php endif; ?>
            </p>
            <?php foreach (psychometric_caas_labels() as $axis => $label):
                $val = max(0, min(100, (int)($caasScores[$axis] ?? 50)));
            ?>
                <p><b><?=e($label)?> · <?=$val?>%</b></p>
                <meter min="0" max="100" value="<?=$val?>" style="width:100%;height:16px"><?=$val?>%</meter>
                <p class="muted" style="font-size:0.85rem;margin-bottom:10px"><?=e($caasInsights[$axis] ?? '')?></p>
            <?php endforeach; ?>
            <small><?=e($caas['completed_at'])?></small>
        <?php else: ?>
            <p>Chưa có hồ sơ Thích ứng Nghề nghiệp CAAS (12 câu).</p>
        <?php endif; ?>
        <p><a class="btn btn-outline" href="<?=e(url('index.php?page=test-caas'))?>">Làm bài CAAS →</a></p>
    </section>

    <section class="card">
        <h2>4. Phát hiện Điểm mù & Độ lệch (SOKA / SCCT)</h2>
        <?php if (!empty($discrepancyData['findings'])): ?>
            <ul>
            <?php foreach ($discrepancyData['findings'] as $f): ?>
                <li style="margin-bottom:10px"><strong><?=e($f['title'])?>:</strong> <?=e($f['detail'])?></li>
            <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Chưa phát hiện độ lệch lớn, hoặc bạn cần hoàn thành thêm bài Big Five / CAAS và ít nhất 1 Phòng mô phỏng nghề để hệ thống đối chiếu Tự khai với Hành vi thực tế.</p>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?=e(url('index.php?page=simulations'))?>">Kiểm chứng tại Phòng mô phỏng →</a>
        <p><a href="<?=e(url('index.php?page=ai-roadmap'))?>">Mở AI Phản biện & Bản đồ hành trình →</a></p>
    </section>
</div>

<div class="grid-3">
    <section class="card"><h2>MBTI · Xu hướng tự mô tả</h2>
    <?php if (!empty($latestPsych['mbti'])): $mbti=$latestPsych['mbti']; $mbtiScores=json_decode($mbti['scores_json'],true)?:[]; ?>
    <p style="font-size:1.6rem;font-weight:800"><?=e($mbti['result_code'])?></p><p class="muted">X = chưa nghiêng rõ về bên nào.</p>
    <?php foreach (['EI','SN','TF','JP'] as $axis): $value=max(0,min(100,(int)($mbtiScores[$axis][$axis[0]]??50))); ?><p><b><?=e($axis[0])?> <?=$value?>% / <?=e($axis[1])?> <?=100-$value?>%</b></p><?php endforeach; ?>
    <ul><?php foreach (psychometric_style_descriptions($mbti['result_code']) as $description): ?><li><?=e($description)?></li><?php endforeach; ?></ul><small><?=e($mbti['completed_at'])?></small>
    <?php else: ?><p>Chưa có kết quả MBTI rút gọn.</p><?php endif; ?><p><a href="<?=e(url('index.php?page=test-mbti'))?>">Làm bài MBTI →</a></p></section>

    <section class="card"><h2>EQ · Bốn góc tự phản tư</h2>
    <?php if (!empty($latestPsych['eq'])): $eq=$latestPsych['eq']; $eqScores=json_decode($eq['scores_json'],true)?:[]; ?>
    <?php foreach (psychometric_eq_labels() as $axis=>$label): $value=max(0,min(100,(int)($eqScores[$axis]??0))); ?><p><label for="eq-<?=e($axis)?>"><?=e($label)?> · <?=$value?>%</label></p><meter id="eq-<?=e($axis)?>" min="0" max="100" value="<?=$value?>" style="width:100%;height:20px"><?=$value?>%</meter><?php endforeach; ?>
    <small><?=e($eq['completed_at'])?></small>
    <?php else: ?><p>Chưa có hồ sơ EQ tự phản tư.</p><?php endif; ?><p><a href="<?=e(url('index.php?page=test-eq'))?>">Làm bài EQ →</a></p></section>
</div>

<section class="card"><h2>Ma trận Sở thích & Trải nghiệm</h2><p>Gợi ý nghề dựa trên Holland chuẩn hóa nội tại, Tự hiệu quả SCCT, Nhân cách Big Five và Điều kiện thực tế; nhãn ma trận đối chiếu với kết quả mô phỏng tình huống 2D.</p>
<?php
$matches=[]; $latestAttempts=[];
if ($latestHolland) {
    $statement=db()->prepare('SELECT c.id,c.title,c.short_description,m.match_percent,m.rank_order FROM assessment_matches m JOIN careers c ON c.id=m.career_id WHERE m.assessment_id=? ORDER BY m.rank_order');
    $statement->execute([(int)$latestHolland['id']]); $matches=$statement->fetchAll();
    $statement=db()->prepare('SELECT career_id,score,reflection_feeling,reflection_note FROM simulation_attempts WHERE user_id=? ORDER BY completed_at DESC,id DESC');
    $statement->execute([$studentId]);
    foreach ($statement->fetchAll() as $attempt) if (!isset($latestAttempts[$attempt['career_id']])) $latestAttempts[$attempt['career_id']]=$attempt;
}
?>
<?php if (!$matches): ?><p>Chưa có danh sách ghép nghề. Hoàn tất Holland và khảo sát điều kiện học tập để tạo gợi ý.</p><?php endif; ?>
<?php foreach ($matches as $job): $matrix=career_matrix_status((int)$job['match_percent'],$latestAttempts[$job['id']]??null); ?>
<article style="padding:18px 0;border-bottom:1px solid var(--line)"><h3><a href="<?=e(url('index.php?page=career&id='.(int)$job['id']))?>"><?=e($job['title'])?></a> · <?=(int)$job['match_percent']?>%</h3><span class="<?=e($matrix['class'])?>"><?=e($matrix['badge'])?></span><p><?=e($matrix['desc'])?></p></article>
<?php endforeach; ?></section>
<?php endif; ?>
</div>
<?php require __DIR__.'/footer.php'; exit;
