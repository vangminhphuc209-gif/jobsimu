<?php
declare(strict_types=1);
require_once __DIR__.'/psychometrics.php';
require_login();
$studentId = (int)user()['id'];
$testType = $page === 'test-mbti' ? 'mbti' : ($page === 'test-eq' ? 'eq' : null);
$testError = '';
$submittedAnswers = [];
if ($testType !== null && is_post()) {
    verify_csrf();
    try {
        if (!is_array($_POST['answers'] ?? null)) throw new InvalidArgumentException('Vui lòng trả lời đầy đủ các câu hỏi.');
        $submittedAnswers = $_POST['answers'];
        $scored = score_psychometric($testType, $submittedAnswers);
        $statement = db()->prepare('INSERT INTO user_psychometrics(user_id,test_type,answers_json,scores_json,result_code,summary_label) VALUES(?,?,?,?,?,?)');
        $statement->execute([$studentId, $testType, json_encode($scored['answers'], JSON_UNESCAPED_UNICODE), json_encode($scored['scores'], JSON_UNESCAPED_UNICODE), $scored['result_code'], $scored['summary_label']]);
        $_SESSION['psychometric_saved'] = ['user_id'=>$studentId, 'type'=>$testType];
        flash('success', 'Đã lưu kết quả. Bạn có thể đối chiếu với trải nghiệm thực tế.');
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
$pageTitle = $testType === 'mbti' ? 'MBTI rút gọn · Khám phá xu hướng' : ($testType === 'eq' ? 'EQ · Tự phản tư cảm xúc' : ($page === 'assessment-report' ? 'Báo cáo đánh giá tổng quan' : 'Đánh giá bản thân'));
require __DIR__.'/header.php';
?>
<section class="page-head"><div class="container"><span class="eyebrow">Hub 2 · Hiểu mình qua nhiều góc nhìn</span><h1><?=e($pageTitle)?></h1><p>Kết hợp sở thích, cách học và trải nghiệm để chọn bước tiếp theo phù hợp với bạn.</p></div></section>
<div class="container">
<div class="alert alert-warning" role="note">Các bài tự đánh giá chỉ hỗ trợ khám phá. MBTI rút gọn và EQ ở đây là câu hỏi tự phản tư do nền tảng biên soạn, chưa được chuẩn hóa; không chẩn đoán, xếp hạng giá trị hay đo năng lực thực tế. Kết quả có thể thay đổi theo hoàn cảnh.</div>
<?php if ($page === 'assessment-hub'): ?>
<div class="grid-3">
<?php foreach ([
    ['title'=>'Holland · Sở thích nghề nghiệp','desc'=>'30 câu hỏi và khảo sát điều kiện học tập. Đây là dữ liệu chính để gợi ý nghề.','route'=>'assessment','done'=>$latestHolland && !empty($latestHolland['constraint_answers'])],
    ['title'=>'MBTI rút gọn · Xu hướng tính cách','desc'=>'12 câu tự mô tả về cách tương tác, tiếp nhận thông tin và lựa chọn.','route'=>'test-mbti','done'=>isset($latestPsych['mbti'])],
    ['title'=>'EQ · Đam mê & Tự phản tư','desc'=>'10 tình huống tự phản tư về cảm xúc, động lực và hợp tác.','route'=>'test-eq','done'=>isset($latestPsych['eq'])],
    ['title'=>'Báo cáo đánh giá tổng quan','desc'=>'Đối chiếu Holland, MBTI, EQ và ma trận Sở thích & Trải nghiệm.','route'=>'assessment-report','done'=>false]
] as $card): ?>
<article class="card"><span class="tag"><?=$card['done']?'Đã có kết quả':'Khám phá'?></span><h2><?=e($card['title'])?></h2><p><?=e($card['desc'])?></p><a class="btn btn-primary" href="<?=e(url('index.php?page='.$card['route']))?>"><?=$card['done']?'Xem / làm lại':'Bắt đầu'?> →</a></article>
<?php endforeach; ?>
</div>
<?php if ($latestHolland && empty($latestHolland['constraint_answers'])): ?><p><a class="btn btn-outline" href="<?=e(url('index.php?page=constraints&id='.(int)$latestHolland['id']))?>">Hoàn tất khảo sát điều kiện học tập →</a></p><?php endif; ?>
<?php elseif ($testType !== null):
    $bank = psychometric_questions($testType);
    $draftKey = 'jobsimu_psychometric_v1_'.$studentId.'_'.$testType;
?>
<p><a href="<?=e(url('index.php?page=assessment-hub'))?>">← Tất cả bài đánh giá</a></p>
<p>Hãy nghĩ về vài tuần gần đây. Chọn mức độ phù hợp với trải nghiệm của bạn; không có đáp án đúng hoặc sai.</p>
<?php if ($testError): ?><div class="alert alert-error" role="alert"><?=e($testError)?></div><?php endif; ?>
<p id="psychDraftStatus" role="status" aria-live="polite">Bản nháp được lưu trên thiết bị này khi trình duyệt cho phép.</p>
<form method="post" id="psychometricForm" action="<?=e(url('index.php?page=test-'.$testType))?>">
<?=csrf_field()?>
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
    const form = document.getElementById('psychometricForm');
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
    document.getElementById('psychReset').addEventListener('click', function () {
        if (!window.confirm('Xóa câu trả lời nháp trên thiết bị này? Kết quả đã gửi vẫn được giữ.')) return;
        fields.forEach(function (field) { field.checked = false; });
        try { localStorage.removeItem(key); } catch (error) { storageAvailable = false; }
        status.textContent = storageAvailable ? 'Đã xóa bản nháp.' : 'Đã xóa các lựa chọn đang hiển thị; không truy cập được bộ nhớ trình duyệt.';
    });
    // Keep draft until the server confirms a successful insert on the report page.
})();
</script>
<?php elseif ($page === 'assessment-report'):
    $saved = $_SESSION['psychometric_saved'] ?? null;
    if (is_array($saved) && ($saved['user_id'] ?? null) === $studentId && in_array($saved['type'] ?? '', ['mbti','eq'], true)):
        unset($_SESSION['psychometric_saved']);
?>
<script>try { localStorage.removeItem(<?=json_encode('jobsimu_psychometric_v1_'.$studentId.'_'.$saved['type'])?>); } catch (error) {}</script>
<?php endif; ?>
<p><a href="<?=e(url('index.php?page=assessment-hub'))?>">← Bộ công cụ đánh giá</a></p>
<section class="card"><h2>Holland · 6 nhóm sở thích</h2>
<?php if ($latestHolland): $hollandScores=json_decode($latestHolland['scores'],true) ?: []; ?>
<p>Mã gần nhất: <strong><?=e($latestHolland['result_code'])?></strong> · <?=e($latestHolland['completed_at'])?></p>
<div class="score-grid"><?php foreach (riasec_labels() as $axis=>$label): $percent=max(0,min(100,(int)($hollandScores[$axis]??0))); ?><div class="score-item"><div class="score-ring" style="--v:<?=$percent?>;--c:<?=e(riasec_colors()[$axis])?>"><span><?=$percent?>%</span></div><b><?=e($label)?></b><small><?=e($axis)?></small></div><?php endforeach; ?></div>
<p><a class="btn btn-outline" href="<?=e(url('index.php?page=result&id='.(int)$latestHolland['id']))?>">Xem kết quả Holland và điều kiện học tập</a></p>
<?php else: ?><p>Chưa có kết quả Holland. Các phần còn thiếu sẽ không được suy đoán.</p><a class="btn btn-primary" href="<?=e(url('index.php?page=assessment'))?>">Làm bài Holland</a><?php endif; ?></section>
<div class="grid-3">
<section class="card"><h2>MBTI · Xu hướng tự mô tả</h2>
<?php if (!empty($latestPsych['mbti'])): $mbti=$latestPsych['mbti']; $mbtiScores=json_decode($mbti['scores_json'],true)?:[]; ?>
<p style="font-size:2rem;font-weight:800"><?=e($mbti['result_code'])?></p><p>X = chưa nghiêng rõ về bên nào. Tỷ lệ thể hiện câu trả lời, không phải độ chắc chắn của một kiểu tính cách.</p>
<?php foreach (['EI','SN','TF','JP'] as $axis): $value=max(0,min(100,(int)($mbtiScores[$axis][$axis[0]]??50))); ?><p><b><?=e($axis[0])?> <?=$value?>% / <?=e($axis[1])?> <?=100-$value?>%</b></p><?php endforeach; ?>
<ul><?php foreach (psychometric_style_descriptions($mbti['result_code']) as $description): ?><li><?=e($description)?></li><?php endforeach; ?></ul><small><?=e($mbti['completed_at'])?></small>
<?php else: ?><p>Chưa có kết quả MBTI rút gọn.</p><?php endif; ?><p><a href="<?=e(url('index.php?page=test-mbti'))?>">Làm bài MBTI →</a></p></section>
<section class="card"><h2>EQ · Bốn góc tự phản tư</h2>
<?php if (!empty($latestPsych['eq'])): $eq=$latestPsych['eq']; $eqScores=json_decode($eq['scores_json'],true)?:[]; ?>
<?php foreach (psychometric_eq_labels() as $axis=>$label): $value=max(0,min(100,(int)($eqScores[$axis]??0))); ?><p><label for="eq-<?=e($axis)?>"><?=e($label)?> · <?=$value?>%</label></p><meter id="eq-<?=e($axis)?>" min="0" max="100" value="<?=$value?>" style="width:100%;height:20px"><?=$value?>%</meter><?php endforeach; ?>
<p>Các tỷ lệ chỉ tóm tắt mức tự mô tả, không phải điểm EQ chuẩn hóa hay kết luận về năng lực.</p><small><?=e($eq['completed_at'])?></small>
<?php else: ?><p>Chưa có hồ sơ EQ tự phản tư.</p><?php endif; ?><p><a href="<?=e(url('index.php?page=test-eq'))?>">Làm bài EQ →</a></p></section>
<section class="card"><h2>Đối chiếu bằng trải nghiệm</h2><p>Hãy thử một nghề, ghi lại điều khiến bạn hứng thú và điều bạn muốn rèn luyện. Một bài test hay một lượt chơi không quyết định tương lai của bạn.</p><a class="btn btn-primary" href="<?=e(url('index.php?page=simulations'))?>">Đến phòng mô phỏng →</a><p><a href="<?=e(url('index.php?page=ai-roadmap'))?>">Xây dựng bản đồ hành trình →</a></p></section>
</div>
<section class="card"><h2>Ma trận Sở thích & Trải nghiệm</h2><p>Gợi ý nghề dựa trên Holland và điều kiện học tập; nhãn ma trận kế thừa kết quả mô phỏng tình huống 2D, chỉ để tham khảo.</p>
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
