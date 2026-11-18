<?php
declare(strict_types=1);

$me = user();
$completedTests = 0; 
$activityCount = 0; 
$hasRoadmap = false;
$latestHolland = null;

if ($me) {
    $latest = four_hub_latest_psychometrics((int)$me['id']);
    $s = db()->prepare('SELECT id,result_code FROM assessments WHERE user_id=? AND constraint_answers IS NOT NULL ORDER BY completed_at DESC,id DESC LIMIT 1');
    $s->execute([$me['id']]); 
    $latestHolland = $s->fetch() ?: null;
    $completedTests = count($latest) + ($latestHolland ? 1 : 0);
    
    $s = db()->prepare('SELECT COUNT(*) FROM simulation_attempts WHERE user_id=?'); 
    $s->execute([$me['id']]);
    $activityCount = (int)$s->fetchColumn(); 
    $hasRoadmap = (bool)$latestHolland;
}

$useJourney4StepHome = function_exists('journey_feature_enabled') ? journey_feature_enabled('journey_4step') : true;

if ($useJourney4StepHome) {
    $hubs = [
        [
            'step' => '01',
            'route' => journey_feature_enabled('discover_quick') ? 'discover' : 'assessment-hub',
            'title' => 'Khám phá bản thân',
            'lead' => 'Bắt đầu bằng 5 câu hỏi nhanh hoặc làm bộ đánh giá chuẩn hóa.',
            'desc' => 'Gọi tên môn học yêu thích, hoạt động ngoài giờ, điều bạn coi trọng — song song với toàn bộ bài test Holland, Big Five, CAAS, MBTI, EQ và SOKA 360°.',
            'status' => $me ? ($completedTests > 0 ? "5 câu nhanh · $completedTests bài chuẩn hóa" : '5 câu nhanh · Bộ đánh giá chuẩn') : '5 câu nhanh · Bộ đánh giá chuẩn',
            'icon' => '🧭'
        ],
        [
            'step' => '02',
            'route' => journey_feature_enabled('lookup_hub') ? 'lookup' : 'university-explorer',
            'title' => 'Tra cứu',
            'lead' => 'Tìm hiểu ngành học, trường đào tạo và tổ hợp xét tuyển.',
            'desc' => 'Bắt đầu từ 3 cửa vào rõ ràng: Ngành học, Trường đào tạo và Tổ hợp xét tuyển kèm nguồn xác minh, cùng danh mục 358 ngành và 27 hồ sơ nghề.',
            'status' => 'Ngành · Trường · Tổ hợp',
            'icon' => '🔎'
        ],
        [
            'step' => '03',
            'route' => journey_feature_enabled('compare_priority') ? 'compare' : 'university-compare',
            'title' => 'Hiểu mình cần gì',
            'lead' => 'Đặt các lựa chọn cạnh nhau để nhìn rõ điều bạn ưu tiên.',
            'desc' => 'Chọn 2–7 phương án, trả lời 20 câu hỏi trọng số trên thang 100 điểm (lưu riêng với điểm bài test chuẩn hóa) và tự chốt phương án ưu tiên / dự phòng.',
            'status' => 'So sánh ưu tiên · 20 câu',
            'icon' => '⚖️'
        ],
        [
            'step' => '04',
            'route' => journey_feature_enabled('action_plan') ? 'plan' : 'ai-roadmap',
            'title' => 'Lập kế hoạch',
            'lead' => 'Chia mục tiêu thành mốc tháng/năm cụ thể và chỉnh sửa được.',
            'desc' => 'Mang phương án đã chọn sang bảng kế hoạch theo khối Lớp 10–12, nhờ Trợ lý La Bàn gợi ý bản nháp, in PDF hoặc mở lại Bản đồ hành trình & mô phỏng nghề.',
            'status' => $me ? ($activityCount > 0 ? "Mốc tháng/năm · $activityCount lượt mô phỏng" : 'Mốc tháng/năm & PDF') : 'Mốc tháng/năm & PDF',
            'icon' => '📅'
        ],
    ];
} else {
    $hubs = [
        [
            'step' => '01',
            'route' => 'assessment-hub',
            'title' => 'Hiểu mình',
            'lead' => 'Đánh giá sở thích, tính cách và năng lực thích ứng.',
            'desc' => 'Hoàn thành bộ trắc nghiệm chuẩn hóa Holland RIASEC, Big Five, CAAS, MBTI, EQ và Gương soi SOKA 360° để xem hồ sơ tổng hợp.',
            'status' => $me ? "$completedTests bài đã hoàn thành" : 'Holland · Big Five · CAAS',
            'icon' => '🧭'
        ],
        [
            'step' => '02',
            'route' => 'careers',
            'title' => 'Khám phá',
            'lead' => 'Tìm hiểu 27 nghề nghiệp, 358 ngành đào tạo và trường ĐH/CĐ.',
            'desc' => 'Đọc mô tả nghề, tra cứu trường đại học & điểm chuẩn, so sánh trường và tham khảo cẩm nang hướng nghiệp.',
            'status' => '27 nghề · 358 ngành',
            'icon' => '🔎'
        ],
        [
            'step' => '03',
            'route' => 'simulations',
            'title' => 'Trải nghiệm',
            'lead' => 'Nhập vai tình huống nghề nghiệp thực tế 2D & 3D.',
            'desc' => 'Kiểm chứng sự phù hợp qua các tình huống mô phỏng nghề nghiệp và lưu lại nhật ký trải nghiệm cá nhân.',
            'status' => $me ? "$activityCount lượt trải nghiệm" : 'Mô phỏng 2D & 3D',
            'icon' => '🎮'
        ],
        [
            'step' => '04',
            'route' => 'ai-roadmap',
            'title' => 'Lộ trình của tôi',
            'lead' => 'Lập bản đồ hành trình và theo dõi tiến độ.',
            'desc' => 'Quản lý mục tiêu học tập, góc người trong nghề và danh sách nghề yêu thích của bạn.',
            'status' => $hasRoadmap ? 'Đang theo dõi lộ trình' : 'Bản đồ hành trình',
            'icon' => '🗺️'
        ],
    ];
}

// Featured careers for guest discovery
$stmt = db()->query("SELECT id,title,icon,short_description FROM careers WHERE status='published' AND slug IN ('giao-vien-thcs','lap-trinh-vien-frontend','bac-si-da-khoa') ORDER BY id LIMIT 3");
$featuredCareers = $stmt->fetchAll();

$pageTitle = 'Hướng Nghiệp 360 · Nền tảng định hướng nghề nghiệp';
require ROOT_PATH . '/app/header.php';
?>

<?php if ($me): ?>
<!-- ================= LOGGED-IN HERO & NEXT STEPS ================= -->
<section class="page-head">
  <div class="container">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <span class="badge badge-purple mb-2">Không gian cá nhân</span>
        <h1 class="page-title mb-1">Chào mừng trở lại, <?=e($me['name'])?> 👋</h1>
        <p class="page-subtitle">Hôm nay bạn muốn tiếp tục hành trình khám phá nào?</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a class="btn btn-primary btn-sm" href="<?=url('index.php?page=dashboard')?>">
          Hồ sơ của tôi →
        </a>
        <a class="btn btn-outline btn-sm" href="<?=url('index.php?page=ai-roadmap')?>">
          Bản đồ hành trình →
        </a>
      </div>
    </div>
  </div>
</section>

<div class="container py-8 space-y-8">
  <!-- Next Recommended Action Card -->
  <div class="card p-6 bg-gradient-to-r from-purple-50/80 to-indigo-50/80 border border-purple-200/80">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
      <div>
        <span class="text-xs font-bold text-brand-700 uppercase tracking-wide flex items-center gap-1.5 mb-1">
          <span>🎯</span> Bước gợi ý tiếp theo cho bạn
        </span>
        <?php if (!$latestHolland): ?>
          <h2 class="text-lg font-bold text-slate-900">Bắt đầu bằng khảo sát sở thích nghề nghiệp (5–7 phút)</h2>
          <p class="text-sm text-slate-600 mt-1 max-w-2xl">
            Hoàn thành 30 câu hỏi để nhận mã Holland (RIASEC) và danh sách các nhóm nghề tương đồng với bạn nhất.
          </p>
        <?php elseif ($activityCount === 0): ?>
          <h2 class="text-lg font-bold text-slate-900">Mã Holland của bạn là <?=e($latestHolland['result_code'])?> · Hãy thử 1 tình huống mô phỏng</h2>
          <p class="text-sm text-slate-600 mt-1 max-w-2xl">
            Thay vì chỉ đọc mô tả, hãy đóng vai và giải quyết 4 tình huống nghề nghiệp trong 5 phút để kiểm chứng mức độ phù hợp.
          </p>
        <?php else: ?>
          <h2 class="text-lg font-bold text-slate-900">Cập nhật Kế hoạch hành động và mục tiêu năm học</h2>
          <p class="text-sm text-slate-600 mt-1 max-w-2xl">
            Bạn đã có kết quả đánh giá và lượt trải nghiệm mô phỏng. Hãy biến khám phá này thành các mốc hành động cụ thể trong Bản đồ hành trình.
          </p>
        <?php endif; ?>
      </div>
      <div>
        <?php if (!$latestHolland): ?>
          <a class="btn btn-primary" href="<?=url('index.php?page=assessment')?>">
            Làm bài khảo sát ngay →
          </a>
        <?php elseif ($activityCount === 0): ?>
          <a class="btn btn-primary" href="<?=url('index.php?page=simulations')?>">
            Thử phòng mô phỏng →
          </a>
        <?php else: ?>
          <a class="btn btn-primary" href="<?=url('index.php?page=ai-roadmap')?>">
            Mở Bản đồ hành trình →
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 4 Pillars Section -->
  <section aria-labelledby="hubs-heading">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 id="hubs-heading" class="text-xl font-bold text-slate-900">4 Giai đoạn định hướng nghề nghiệp</h2>
        <p class="text-xs text-slate-500">Khám phá theo nhịp độ của bạn, không có ràng buộc ép buộc.</p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
      <?php foreach ($hubs as $hub): ?>
        <a class="card p-5 flex flex-col justify-between hover:border-brand-300 transition-all text-decoration-none group" href="<?=url('index.php?page='.$hub['route'])?>">
          <div>
            <div class="flex items-center justify-between text-xs mb-3">
              <span class="text-2xl p-2 bg-slate-50 rounded-xl border border-slate-100"><?=$hub['icon']?></span>
              <span class="badge badge-subtle font-bold">Giai đoạn <?=$hub['step']?></span>
            </div>
            <h3 class="font-bold text-base text-slate-900 group-hover:text-brand-600 transition-colors mb-1">
              <?=e($hub['title'])?>
            </h3>
            <p class="text-xs font-semibold text-brand-600 mb-2"><?=e($hub['lead'])?></p>
            <p class="text-xs text-slate-600 leading-relaxed mb-4">
              <?=e($hub['desc'])?>
            </p>
          </div>
          <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-500 group-hover:text-brand-600">
            <span><?=e($hub['status'])?></span>
            <span>→</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<?php else: ?>
<!-- ================= GUEST WELCOME EXPERIENCE ================= -->
<section class="hub-hero">
  <div class="container py-12 md:py-16">
    <div class="max-w-3xl">
      <span class="badge badge-purple mb-3">Nền tảng hướng nghiệp dành cho học sinh Việt Nam</span>
      <h1 class="page-title text-3xl md:text-5xl font-black text-slate-900 tracking-tight leading-tight mb-4">
        Hiểu mình hôm nay.<br>
        <span class="text-brand-600">Chủ động thiết kế tương lai.</span>
      </h1>
      <p class="text-base md:text-lg text-slate-600 leading-relaxed mb-6">
        Mỗi lựa chọn bắt đầu từ một lần khám phá. Tìm hiểu nghề nghiệp, lắng nghe bản thân qua các công cụ đo lường tiêu chuẩn, thử sức qua tình huống thực tế và xây dựng lộ trình của riêng bạn.
      </p>
      <div class="flex flex-wrap items-center gap-3">
        <a class="btn btn-primary btn-lg" href="<?=url('index.php?page=careers')?>">
          Bắt đầu khám phá nghề →
        </a>
        <a class="btn btn-outline btn-lg" href="<?=url('index.php?page=register')?>">
          Tạo tài khoản miễn phí
        </a>
      </div>
    </div>
  </div>
</section>

<div class="container py-8 space-y-12">
  <!-- 4-Stage Journey Guide -->
  <section aria-labelledby="journey-heading">
    <div class="text-center max-w-xl mx-auto mb-8">
      <span class="badge badge-purple mb-2">Hành trình 4 giai đoạn</span>
      <h2 id="journey-heading" class="text-2xl font-bold text-slate-900 mb-2">Hướng đi rõ ràng cho học sinh</h2>
      <p class="text-sm text-slate-600">
        Từ bước tò mò tìm hiểu đến kế hoạch hành động thực tế, bạn luôn có công cụ hỗ trợ đáng tin cậy.
      </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
      <?php foreach ($hubs as $hub): ?>
        <div class="card p-5 flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between mb-3">
              <span class="text-2xl p-2 bg-slate-50 rounded-xl border border-slate-100"><?=$hub['icon']?></span>
              <span class="badge badge-subtle font-bold">Bước <?=$hub['step']?></span>
            </div>
            <h3 class="font-bold text-base text-slate-900 mb-1"><?=e($hub['title'])?></h3>
            <p class="text-xs font-semibold text-brand-600 mb-2"><?=e($hub['lead'])?></p>
            <p class="text-xs text-slate-600 leading-relaxed mb-4"><?=e($hub['desc'])?></p>
          </div>
          <div class="pt-3 border-t border-slate-100">
            <a class="btn btn-outline btn-sm w-full" href="<?=url('index.php?page='.$hub['route'])?>">
              Khám phá →
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Featured Careers Preview -->
  <?php if (!empty($featuredCareers)): ?>
  <section aria-labelledby="featured-heading">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 id="featured-heading" class="text-xl font-bold text-slate-900">Một số nghề nghiệp tiêu biểu</h2>
        <p class="text-xs text-slate-500">Mỗi hồ sơ nghề đều có mô tả chi tiết, môn học liên quan và tình huống mô phỏng.</p>
      </div>
      <a class="btn btn-outline btn-sm" href="<?=url('index.php?page=careers')?>">
        Xem tất cả 27+ nghề →
      </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
      <?php foreach ($featuredCareers as $fc): ?>
        <div class="card p-5 flex flex-col justify-between hover:border-brand-300 transition-all">
          <div>
            <span class="text-3xl p-2 bg-slate-50 rounded-xl border border-slate-100 inline-block mb-3"><?=e($fc['icon'] ?: '💼')?></span>
            <h3 class="font-bold text-base text-slate-900 mb-1">
              <a href="<?=url('index.php?page=career&id='.(int)$fc['id'])?>" class="hover:text-brand-600">
                <?=e($fc['title'])?>
              </a>
            </h3>
            <p class="text-xs text-slate-600 line-clamp-3 leading-relaxed mb-4">
              <?=e($fc['short_description'])?>
            </p>
          </div>
          <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
            <span class="text-xs text-slate-400">Có mô phỏng thực tế</span>
            <a class="text-xs font-bold text-brand-600 hover:underline" href="<?=url('index.php?page=career&id='.(int)$fc['id'])?>">
              Chi tiết nghề →
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- Honest Disclosure Banner -->
  <div class="notice-box">
    <div class="flex items-start gap-3">
      <span class="text-xl">🌿</span>
      <div class="text-xs text-slate-600 leading-relaxed">
        <strong>Cam kết trung thực sư phạm:</strong> Mọi bài trắc nghiệm và mô phỏng trên JobSimu là công cụ hỗ trợ học sinh tự phản tư và khám phá, <strong>không đưa ra kết luận cố định hay phán quyết thay bạn</strong>. Hãy luôn thảo luận kết quả cùng gia đình, thầy cô và những người đang làm nghề thực tế trước khi đưa ra quyết định quan trọng.
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php 
require ROOT_PATH . '/app/footer.php'; 
exit;

