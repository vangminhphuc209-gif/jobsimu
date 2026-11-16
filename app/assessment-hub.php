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
        $scored = recommendations_save_psychometric($studentId, $testType, $submittedAnswers, $durationSec);
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

$currentRecommendations = $page === 'assessment-report' ? recommendations_refresh_current($studentId) : null;
$latestPsych = four_hub_latest_psychometrics($studentId);
$statement = db()->prepare('SELECT * FROM assessments WHERE user_id=? ORDER BY completed_at DESC,id DESC LIMIT 1');
$statement->execute([$studentId]);
$latestHolland = $statement->fetch() ?: null;
$sokaRespCount = 0;
try {
    $sokaCountStmt = db()->prepare('SELECT COUNT(*) FROM soka_mirror_responses WHERE user_id=?');
    $sokaCountStmt->execute([$studentId]);
    $sokaRespCount = (int)$sokaCountStmt->fetchColumn();
} catch (Throwable $e) {}

$titleByType = [
    'big5' => 'Phong cách làm việc & Tính cách (Big Five)',
    'caas' => 'Khả năng Thích ứng nghề nghiệp (CAAS)',
    'mbti' => 'Xu hướng tương tác & Ra quyết định (MBTI)',
    'eq' => 'Cảm xúc & Động lực bản thân (EQ)',
];
$pageTitle = $testType !== null ? $titleByType[$testType] : ($page === 'assessment-report' ? 'Báo cáo đánh giá tổng quan' : 'Đánh giá bản thân');
require __DIR__.'/header.php';
?>
<section class="page-head">
  <div class="container">
    <nav class="breadcrumb mb-3" aria-label="Đường dẫn trang">
      <a href="<?=url('index.php?page=home')?>">Trang chủ</a>
      <span class="breadcrumb-sep">/</span>
      <a href="<?=url('index.php?page=assessment-hub')?>">Hiểu bản thân</a>
      <?php if ($testType !== null): ?>
        <span class="breadcrumb-sep">/</span>
        <span aria-current="page"><?=e($titleByType[$testType])?></span>
      <?php elseif ($page === 'assessment-report'): ?>
        <span class="breadcrumb-sep">/</span>
        <span aria-current="page">Báo cáo tổng hợp</span>
      <?php endif; ?>
    </nav>
    <h1 class="page-title"><?=e($pageTitle)?></h1>
    <p class="page-subtitle">
      Khám phá sở thích, phong cách học tập và tiềm năng nghề nghiệp của bạn qua các công cụ đo lường tiêu chuẩn.
    </p>
  </div>
</section>

<div class="container py-8">

<?php if ($page === 'assessment-hub'): ?>
  <!-- 6 Student-Friendly Assessment Cards Grid -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php foreach ([
      [
        'title' => 'Sở thích & Thế mạnh nghề nghiệp',
        'sub' => 'Khảo sát Holland (RIASEC)',
        'desc' => 'Khám phá 6 nhóm sở thích nghề nghiệp và mức độ tự tin của bạn, từ đó gợi ý danh sách ngành nghề tương đồng nhất.',
        'duration' => '⏱ 5–7 phút (30 câu)',
        'route' => 'assessment',
        'done' => $latestHolland && !empty($latestHolland['constraint_answers']),
        'result_text' => $latestHolland ? 'Mã ' . $latestHolland['result_code'] : '',
        'tag' => 'Bài đánh giá cốt lõi',
        'icon' => '🧭'
      ],
      [
        'title' => 'Phong cách làm việc & Tính cách',
        'sub' => 'Mô hình Big Five (Mini-IPIP)',
        'desc' => 'Nhận diện 5 nét tính cách cốt lõi: Cởi mở, Chỉn chu, Hòa đồng, Dễ chịu và Cách bạn đối diện với áp lực công việc.',
        'duration' => '⏱ 4–5 phút (20 câu)',
        'route' => 'test-big5',
        'done' => isset($latestPsych['big5']),
        'result_text' => isset($latestPsych['big5']) ? $latestPsych['big5']['result_code'] : '',
        'tag' => 'Đo lường tính cách',
        'icon' => '🧩'
      ],
      [
        'title' => 'Khả năng Thích ứng nghề nghiệp',
        'sub' => 'Thang đo CAAS-SF',
        'desc' => 'Đo lường mức độ quan tâm tương lai, tính chủ động, sự tò mò và lòng tự tin khi bạn đứng trước các quyết định chọn trường, chọn nghề.',
        'duration' => '⏱ 2–3 phút (12 câu)',
        'route' => 'test-caas',
        'done' => isset($latestPsych['caas']),
        'result_text' => isset($latestPsych['caas']) ? $latestPsych['caas']['result_code'] : '',
        'tag' => 'Độ sẵn sàng',
        'icon' => '🌱'
      ],
      [
        'title' => 'Góc nhìn 360° từ người xung quanh',
        'sub' => 'Gương soi SOKA 360°',
        'desc' => 'Gửi liên kết ẩn danh để bạn bè, thầy cô hoặc người thân chia sẻ cảm nhận thực tế về điểm mạnh và tiềm năng của bạn.',
        'duration' => '⏱ 2 phút gửi liên kết',
        'route' => 'soka-mirror',
        'done' => $sokaRespCount > 0,
        'result_text' => $sokaRespCount > 0 ? $sokaRespCount . ' người đã nhận xét' : '',
        'tag' => 'Góc nhìn khách quan',
        'icon' => '🪞'
      ],
      [
        'title' => 'Xu hướng tương tác & Ra quyết định',
        'sub' => 'MBTI rút gọn (Tự phản tư)',
        'desc' => 'Tìm hiểu phong cách nạp năng lượng, cách tiếp nhận dữ liệu và thói quen ra quyết định trong học tập và sinh hoạt hàng ngày.',
        'duration' => '⏱ 3 phút (12 câu)',
        'route' => 'test-mbti',
        'done' => isset($latestPsych['mbti']),
        'result_text' => isset($latestPsych['mbti']) ? $latestPsych['mbti']['result_code'] : '',
        'tag' => 'Tự phản tư',
        'icon' => '💡'
      ],
      [
        'title' => 'Cảm xúc & Động lực bản thân',
        'sub' => 'Chỉ số cảm xúc EQ (Tự phản tư)',
        'desc' => 'Lắng nghe cách bạn nhận diện cảm xúc cá nhân, giữ vững động lực vượt khó và thấu cảm khi làm việc nhóm.',
        'duration' => '⏱ 2 phút (10 tình huống)',
        'route' => 'test-eq',
        'done' => isset($latestPsych['eq']),
        'result_text' => isset($latestPsych['eq']) ? 'Đã hoàn thành' : '',
        'tag' => 'Tự phản tư',
        'icon' => '❤️'
      ]
    ] as $card): ?>
      <div class="card p-5 flex flex-col justify-between hover:border-brand-300 transition-all">
        <div>
          <div class="flex items-start justify-between gap-2 mb-3">
            <span class="text-3xl p-2 bg-slate-50 rounded-xl border border-slate-100"><?=$card['icon']?></span>
            <?php if ($card['done']): ?>
              <span class="badge badge-success">✓ Đã làm <?=$card['result_text'] ? '('.e($card['result_text']).')' : ''?></span>
            <?php else: ?>
              <span class="badge badge-subtle"><?=e($card['tag'])?></span>
            <?php endif; ?>
          </div>

          <h3 class="font-bold text-lg text-slate-900 mb-1">
            <?=e($card['title'])?>
          </h3>
          <p class="text-xs font-semibold text-slate-400 mb-2"><?=e($card['sub'])?></p>
          <p class="text-sm text-slate-600 leading-relaxed mb-4">
            <?=e($card['desc'])?>
          </p>
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-3">
          <span class="text-xs text-slate-500 font-medium"><?=e($card['duration'])?></span>
          <a class="btn <?=$card['done']?'btn-outline':'btn-primary'?> btn-sm shrink-0 whitespace-nowrap" href="<?=url('index.php?page='.$card['route'])?>">
            <?=$card['done'] ? 'Xem / Làm lại' : 'Bắt đầu làm bài'?> →
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Big Banner to Full Report -->
  <div class="mt-8 p-6 bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-100 rounded-2xl flex flex-col md:flex-row items-center justify-between gap-4">
    <div>
      <span class="badge badge-purple mb-1">Báo cáo tổng hợp</span>
      <h3 class="font-bold text-lg text-slate-900">Hồ sơ năng lực & Báo cáo đối chiếu toàn diện</h3>
      <p class="text-sm text-slate-600 mt-0.5">
        Đối chiếu kết quả các bài trắc nghiệm với hành vi ra quyết định trong Phòng mô phỏng để nhận diện điểm mạnh thực chất.
      </p>
    </div>
    <a class="btn btn-primary whitespace-nowrap" href="<?=url('index.php?page=assessment-report')?>">
      Xem báo cáo tổng hợp →
    </a>
  </div>

  <!-- Collapsible Scientific Methodology -->
  <details class="mt-8 p-5 bg-slate-50 border border-slate-200/80 rounded-2xl text-sm text-slate-700">
    <summary class="cursor-pointer font-bold text-slate-900 flex items-center justify-between">
      <span class="flex items-center gap-2">
        <span>🔬</span> Tìm hiểu phương pháp khoa học & Chuẩn hóa đo lường
      </span>
      <span class="text-xs text-brand-600 font-normal">Bấm để xem chi tiết ↓</span>
    </summary>
    <div class="mt-4 pt-4 border-t border-slate-200/60 space-y-3 text-xs leading-relaxed text-slate-600">
      <p>
        <strong>1. Thang đo Holland RIASEC & O*NET:</strong> Dựa trên lý thuyết sở thích nghề nghiệp của John Holland kết hợp khung chuẩn hóa phân loại nghề của Bộ Lao động Hoa Kỳ (O*NET). Để hạn chế hiện tượng đánh dấu điểm cao cho tất cả câu, hệ thống áp dụng kỹ thuật chuẩn hóa nội tại (Ipsative Z-score).
      </p>
      <p>
        <strong>2. Tự hiệu quả SCCT (Social Cognitive Career Theory):</strong> Đo lường niềm tin năng lực tự thân của Robert Lent & Steven Brown, giúp phân biệt giữa điều bạn "thích" với điều bạn "tin mình có thể làm tốt".
      </p>
      <p>
        <strong>3. Mô hình Big Five (Mini-IPIP):</strong> Bộ câu hỏi 20 mục từ International Personality Item Pool với các câu hỏi đảo chiều nhằm kiểm định độ nhất quán phản hồi.
      </p>
      <p>
        <strong>4. Khả năng thích ứng CAAS (Career Adapt-Abilities Scale):</strong> Phát triển bởi Mark Savickas & Porfeli, đo 4 nguồn lực tâm lý: Quan tâm (Concern), Kiểm soát (Control), Tò mò (Curiosity) và Tự tin (Confidence).
      </p>
      <p>
        <em>Tuyên bố an toàn: Các bài đo nhằm mục đích kích hoạt phản tư và mở rộng góc nhìn học tập; không thay thế đánh giá chuyên sâu và không gán nhãn cố định cho bất kỳ học sinh nào.</em>
      </p>
    </div>
  </details>

<?php elseif ($testType !== null):
    $bank = psychometric_questions($testType);
    $draftKey = 'jobsimu_psychometric_v1_'.$studentId.'_'.$testType;
?>
  <div class="mb-6 flex items-center justify-between gap-4">
    <a class="btn btn-outline btn-sm" href="<?=url('index.php?page=assessment-hub')?>">
      ← Tất cả bài đánh giá
    </a>
    <div class="text-right">
      <span id="psychDraftStatus" class="text-xs text-slate-400 font-semibold" role="status" aria-live="polite">
        Tiến độ được lưu trên thiết bị
      </span>
    </div>
  </div>

  <div class="notice-box mb-6">
    <p class="text-xs text-slate-600 leading-relaxed">
      💡 <strong>Hướng dẫn làm bài:</strong> Hãy đọc kỹ từng mệnh đề và chọn mức độ phản ánh sát nhất cảm nhận hoặc thói quen thực tế của bạn trong thời gian gần đây. Không có câu trả lời đúng hay sai.
    </p>
  </div>

  <?php if ($testError): ?>
    <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-800 mb-6" role="alert">
      ⚠️ <?=e($testError)?>
    </div>
  <?php endif; ?>

  <form method="post" id="psychometricForm" action="<?=url('index.php?page=test-'.$testType)?>">
    <?=csrf_field()?>
    <input type="hidden" name="duration_seconds" id="psychDurationSeconds" value="0">
    
    <div class="space-y-4">
      <?php $questionNumber = 0; foreach ($bank as $key => $question): $questionNumber++; ?>
        <fieldset class="card p-5 border border-slate-200">
          <legend class="font-bold text-base text-slate-900 px-2 mb-3">
            <span class="text-brand-600 mr-1"><?=str_pad((string)$questionNumber, 2, '0', STR_PAD_LEFT)?>.</span> 
            <?=e($question['text'])?>
          </legend>
          
          <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <?php foreach ([
              1 => 'Hoàn toàn không',
              2 => 'Ít đúng',
              3 => 'Phân vân',
              4 => 'Khá đúng',
              5 => 'Rất đúng'
            ] as $value => $label): 
              $checked = isset($submittedAnswers[$key]) && is_scalar($submittedAnswers[$key]) && (string)$submittedAnswers[$key] === (string)$value;
            ?>
              <label class="flex flex-col items-center justify-center p-3 text-center rounded-xl border border-slate-200 hover:border-brand-300 hover:bg-slate-50 cursor-pointer transition-all has-[:checked]:border-brand-500 has-[:checked]:bg-purple-50/50 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/20">
                <input type="radio" name="answers[<?=e($key)?>]" value="<?=$value?>" data-answer-key="<?=e($key)?>" class="sr-only" required <?=$checked ? 'checked' : ''?>>
                <span class="text-lg font-black text-slate-700 block mb-0.5"><?=$value?></span>
                <span class="text-xs text-slate-500 leading-tight"><?=e($label)?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endforeach; ?>
    </div>

    <div class="mt-8 pt-6 border-t border-slate-200 flex flex-wrap items-center justify-between gap-4">
      <button class="btn btn-primary btn-lg" type="submit">
        Lưu & xem kết quả phân tích →
      </button>
      <button class="btn btn-outline" type="button" id="psychReset">
        Làm lại từ đầu
      </button>
    </div>
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
          try { 
              localStorage.setItem(key, JSON.stringify(answers)); 
              status.textContent = '✓ Đã tự động lưu'; 
              setTimeout(function(){ status.textContent = 'Tiến độ được lưu trên thiết bị'; }, 1500);
          } catch (error) { 
              storageAvailable = false; 
          }
      }
      try {
          const draft = JSON.parse(localStorage.getItem(key) || '{}');
          if (draft && typeof draft === 'object' && !Array.isArray(draft) && !<?=json_encode($testError !== '')?>) {
              fields.forEach(function (field) { field.checked = String(draft[field.dataset.answerKey]) === field.value; });
          }
      } catch (error) { storageAvailable = false; }
      form.addEventListener('change', save);
      form.addEventListener('submit', function () {
          if (durationInput) durationInput.value = String(Math.max(1, Math.round((Date.now() - startedAt) / 1000)));
      });
      document.getElementById('psychReset').addEventListener('click', function () {
          if (!window.confirm('Xóa các câu trả lời nháp và làm lại từ đầu?')) return;
          fields.forEach(function (field) { field.checked = false; });
          try { localStorage.removeItem(key); } catch (error) {}
          status.textContent = 'Đã xóa bản nháp.';
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

<section class="card" aria-labelledby="current-recommendations-title">
    <h2 id="current-recommendations-title">Gợi ý từ hồ sơ hiện tại</h2>
    <?php if ($currentRecommendations): ?>
        <p>Đã cập nhật lúc <?=e($currentRecommendations['updated_at'])?>. Gợi ý có thể giữ nguyên nếu thông tin mới củng cố hướng khám phá trước đó.</p>
        <p>Thứ tự gợi ý dựa trên Holland, điều kiện đã khai và Big Five khi có kết quả đủ điều kiện sử dụng. CAAS, MBTI và EQ bổ sung phần tự phản tư; chưa được dùng để cộng điểm xếp hạng nghề.</p>
        <ul>
        <?php foreach ($currentRecommendations['sources'] as $source):
            $roleLabels = ['ranking'=>'Đã đưa vào tính gợi ý', 'reflection'=>'Đã cập nhật phần tự phản tư', 'missing'=>'Chưa có kết quả', 'review_needed'=>'Có lưu ý chất lượng trả lời; chưa đưa vào tính gợi ý']; ?>
            <li><strong><?=e($source['label'])?>:</strong> <?=e($roleLabels[$source['role']])?><?php if ($source['completed_at']): ?> · <?=e($source['completed_at'])?><?php endif; ?></li>
        <?php endforeach; ?>
        </ul>
        <?php
        $matchQuery = db()->prepare("SELECT c.id,c.title FROM assessment_matches m JOIN careers c ON c.id=m.career_id WHERE m.assessment_id=? AND c.status='published' ORDER BY m.rank_order");
        $matchQuery->execute([$currentRecommendations['assessment_id']]);
        $currentMatches = $matchQuery->fetchAll();
        ?>
        <?php if ($currentMatches): ?><ol><?php foreach ($currentMatches as $match): ?>
            <li><a href="<?=e(url('index.php?page=career&id='.(int)$match['id']))?>"><?=e($match['title'])?></a></li>
        <?php endforeach; ?></ol><?php else: ?><p>Hiện chưa có nghề đã công bố để gợi ý.</p><?php endif; ?>
        <p>Đây là những hướng để tìm hiểu thêm, không phải kết luận năng lực hay bảo đảm phù hợp nghề.</p>
    <?php else: ?>
        <p>Các bài bạn đã làm vẫn được lưu và trình bày bên dưới. Phần xếp hạng nghề hiện cần kết quả Holland cùng bước điều kiện học tập; thông tin còn thiếu không bị tính thành điểm thấp.</p>
    <?php endif; ?>
</section>

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
            <p>Chưa phát hiện độ lệch lớn, hoặc bạn cần hoàn thành thêm bài Big Five / CAAS, nhận đánh giá 360° từ bạn bè và ít nhất 1 Phòng mô phỏng nghề để hệ thống đối chiếu Tự khai với Hành vi thực tế.</p>
        <?php endif; ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
            <a class="btn btn-primary" href="<?=e(url('index.php?page=simulations'))?>">Kiểm chứng tại Phòng mô phỏng →</a>
            <a class="btn btn-outline" href="<?=e(url('index.php?page=soka-mirror'))?>">Mời đánh giá 360° (SOKA) →</a>
        </div>
        <p class="mt-3"><a href="<?=e(url('index.php?page=ai-roadmap'))?>">Mở AI Phản biện & Bản đồ hành trình →</a></p>
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
