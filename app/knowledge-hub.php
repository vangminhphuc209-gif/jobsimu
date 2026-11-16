<?php
declare(strict_types=1);

$articleCategories = ['admissions' => 'Tuyển sinh', 'trend' => 'Xu hướng nghề', 'guide' => 'Cẩm nang'];
$articleSlug = is_string($_GET['article'] ?? null) ? trim($_GET['article']) : '';

// 1. Article Single View
if ($articleSlug !== '') {
    $stmt = db()->prepare("SELECT * FROM knowledge_articles WHERE slug=? AND status='published'");
    $stmt->execute([$articleSlug]);
    $article = $stmt->fetch();
    if (!$article) http_response_code(404);
    $pageTitle = $article ? $article['title'] . ' · Bài viết' : 'Không tìm thấy bài viết';
    require __DIR__ . '/header.php';
    ?>
    <section class="page-head">
      <div class="container">
        <nav class="breadcrumb mb-3" aria-label="Đường dẫn trang">
          <a href="<?=url('index.php?page=home')?>">Trang chủ</a>
          <span class="breadcrumb-sep">/</span>
          <a href="<?=url('index.php?page=knowledge-hub&tab=articles')?>">Bài viết & Cẩm nang</a>
          <span class="breadcrumb-sep">/</span>
          <span aria-current="page"><?=e($article ? $article['title'] : 'Không tìm thấy')?></span>
        </nav>
        <div style="max-width:760px;margin:0 auto">
          <?php if($article): ?>
            <span class="badge badge-purple mb-2"><?=e($articleCategories[$article['category']] ?? 'Cẩm nang')?></span>
            <h1 class="page-title text-balance"><?=e($article['title'])?></h1>
            <p class="page-subtitle"><?=e(date('d/m/Y', strtotime($article['updated_at'])))?> · Tài liệu định hướng tham khảo</p>
          <?php else: ?>
            <h1 class="page-title">Bài viết không tồn tại hoặc đã gỡ bỏ</h1>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <div class="container py-8">
      <?php if (!$article): ?>
        <div class="empty-state">
          <div class="empty-icon">📰</div>
          <h2 class="text-xl font-bold mb-2">Bài viết không còn được công khai</h2>
          <p class="text-muted mb-4">Hãy quay lại mục Bài viết & Cẩm nang để tìm đọc các nội dung khác.</p>
          <a class="btn btn-primary" href="<?=url('index.php?page=knowledge-hub&tab=articles')?>">← Quay lại danh sách bài viết</a>
        </div>
      <?php else: ?>
        <div class="article-reader">
          <?php if(!empty($article['summary'])): ?>
            <div class="article-lead">
              <p><?=e($article['summary'])?></p>
            </div>
          <?php endif; ?>

          <div class="article-body">
            <?=nl2br(e($article['content']))?>
          </div>

          <?php 
          $source = safe_external_url($article['source_url'] ?? '');
          if ($source !== ''): 
          ?>
            <div class="article-source-box">
              <div class="flex items-start gap-3">
                <span class="text-xl">📎</span>
                <div>
                  <h3 class="font-bold text-sm text-slate-800">Nguồn tham khảo</h3>
                  <p class="text-sm text-slate-600 mb-1"><?=e($article['source_name'] ?: 'Nguồn công bố chính thức')?></p>
                  <a href="<?=e($source)?>" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-brand-600 hover:underline">
                    Mở tài liệu nguồn trên website đối tác ↗
                  </a>
                  <p class="text-xs text-slate-400 mt-1">Lưu ý: Liên kết sẽ mở website bên ngoài trong tab mới.</p>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!empty($article['related_career_slug'])): 
            $stmt = db()->prepare("SELECT id,title,icon,short_description FROM careers WHERE slug=? AND status='published'");
            $stmt->execute([$article['related_career_slug']]);
            $related = $stmt->fetch();
            if ($related): 
          ?>
            <div class="related-career-card mt-6">
              <span class="eyebrow">Nghề nghiệp liên quan</span>
              <div class="flex items-center gap-3 mt-2">
                <span class="text-3xl"><?=e($related['icon'] ?: '💼')?></span>
                <div class="flex-1">
                  <h4 class="font-bold text-base text-slate-900"><?=e($related['title'])?></h4>
                  <p class="text-sm text-slate-600 line-clamp-2"><?=e($related['short_description'])?></p>
                </div>
                <a class="btn btn-outline btn-sm" href="<?=url('index.php?page=career&id='.(int)$related['id'])?>">
                  Khám phá nghề →
                </a>
              </div>
            </div>
          <?php endif; endif; ?>

          <div class="mt-8 pt-6 border-t border-slate-200 flex items-center justify-between">
            <a class="btn btn-outline" href="<?=url('index.php?page=knowledge-hub&tab=articles')?>">
              ← Tất cả bài viết
            </a>
            <a class="btn btn-primary" href="<?=url('index.php?page=careers')?>">
              Khám phá thư viện nghề →
            </a>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <?php 
    require __DIR__ . '/footer.php'; 
    exit;
}

// 2. Hub List View (Tabs: careers, articles, portals)
$activeTab = $_GET['tab'] ?? '';
if (!in_array($activeTab, ['careers', 'articles', 'portals'], true)) {
    $activeTab = ($page === 'careers') ? 'careers' : 'careers';
}

$q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
if (!array_key_exists($type, riasec_labels())) $type = '';
$category = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
if (!array_key_exists($category, $articleCategories)) $category = '';

// Articles Query
$stmt = db()->prepare("SELECT * FROM knowledge_articles WHERE status='published'" . ($category ? ' AND category=?' : '') . ' ORDER BY featured DESC,updated_at DESC,id DESC LIMIT 30');
$stmt->execute($category ? [$category] : []);
$articles = $stmt->fetchAll();

// Careers Query
$stmt = db()->prepare("SELECT * FROM careers WHERE status='published' ORDER BY featured DESC,title");
$stmt->execute();
$allCareers = $stmt->fetchAll();
$requirements = career_requirements_catalog();
$careerGroups = db()->query('SELECT id,name FROM career_groups ORDER BY sort_order,name')->fetchAll();
$groups = array_values(array_unique(array_column($requirements, 'group')));
sort($groups);
$group = is_string($_GET['group'] ?? null) && in_array($_GET['group'], $groups, true) ? $_GET['group'] : '';

$careers = array_filter($allCareers, static function(array $career) use ($q, $type, $group, $requirements): bool {
    if ($group !== '' && ($requirements[$career['slug']]['group'] ?? '') !== $group) return false;
    if ($q !== '' && mb_stripos($career['title'].' '.$career['short_description'].' '.$career['subjects'], $q) === false) return false;
    $weights = json_decode($career['riasec_weights'], true);
    if (!is_array($weights)) $weights = [];
    arsort($weights);
    return $type === '' || in_array($type, array_slice(array_keys($weights), 0, 2), true);
});

// Portals Query
$stmt = db()->prepare("SELECT * FROM external_portals WHERE status='active' ORDER BY sort_order,id");
$stmt->execute();
$portals = $stmt->fetchAll();

$pageTitle = ($activeTab === 'careers') ? 'Thư viện nghề nghiệp' : (($activeTab === 'articles') ? 'Bài viết & Cẩm nang' : 'Cổng thông tin tuyển sinh');
require __DIR__ . '/header.php';
?>
<section class="page-head">
  <div class="container">
    <nav class="breadcrumb mb-3" aria-label="Đường dẫn trang">
      <a href="<?=url('index.php?page=home')?>">Trang chủ</a>
      <span class="breadcrumb-sep">/</span>
      <span aria-current="page">Khám phá</span>
    </nav>
    <h1 class="page-title">Khám phá nghề & Kiến thức hướng nghiệp</h1>
    <p class="page-subtitle">Tìm hiểu thế giới việc làm thực tế, bài viết định hướng và nguồn thông tin tuyển sinh đáng tin cậy.</p>

    <!-- Clean Tab Navigation -->
    <div class="hub-tabs" role="tablist" aria-label="Phân nhóm nội dung">
      <a href="<?=url('index.php?page=careers&tab=careers')?>" 
         class="hub-tab <?=$activeTab==='careers'?'active':''?>" 
         role="tab" 
         aria-selected="<?=$activeTab==='careers'?'true':'false'?>">
        💼 Thư viện nghề (<?=count($allCareers)?>)
      </a>
      <a href="<?=url('index.php?page=knowledge-hub&tab=articles')?>" 
         class="hub-tab <?=$activeTab==='articles'?'active':''?>" 
         role="tab" 
         aria-selected="<?=$activeTab==='articles'?'true':'false'?>">
        📰 Bài viết & Cẩm nang (<?=count($articles)?>)
      </a>
      <a href="<?=url('index.php?page=knowledge-hub&tab=portals')?>" 
         class="hub-tab <?=$activeTab==='portals'?'active':''?>" 
         role="tab" 
         aria-selected="<?=$activeTab==='portals'?'true':'false'?>">
        🌐 Cổng thông tin uy tín (<?=count($portals)?>)
      </a>
    </div>
  </div>
</section>

<div class="container py-8">

  <?php if ($activeTab === 'careers'): ?>
  <!-- ================= TAB: CAREERS ================= -->
  <section aria-labelledby="careers-heading">
    <div class="filter-card mb-6">
      <form method="get" action="<?=url('index.php')?>" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        <input type="hidden" name="page" value="careers">
        <input type="hidden" name="tab" value="careers">
        
        <div class="md:col-span-5">
          <label for="careerQuery" class="form-label">Tìm theo nghề hoặc môn học</label>
          <input class="form-input" id="careerQuery" name="q" maxlength="100" value="<?=e($q)?>" placeholder="Ví dụ: lập trình viên, giáo viên, sinh học...">
        </div>

        <div class="md:col-span-3">
          <label for="careerType" class="form-label">Sở thích RIASEC</label>
          <select id="careerType" name="type" class="form-select">
            <option value="">Tất cả nhóm</option>
            <?php foreach(riasec_labels() as $k => $label): ?>
              <option value="<?=e($k)?>" <?=$k===$type?'selected':''?>><?=e($k.' · '.$label)?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="md:col-span-3">
          <label for="careerGroup" class="form-label">Lĩnh vực nghề</label>
          <select id="careerGroup" name="group" class="form-select">
            <option value="">Tất cả lĩnh vực</option>
            <?php foreach($groups as $label): ?>
              <option value="<?=e($label)?>" <?=$group===$label?'selected':''?>><?=e($label)?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="md:col-span-1">
          <button type="submit" class="btn btn-primary w-full">Lọc</button>
        </div>
      </form>

      <?php if ($q !== '' || $type !== '' || $group !== ''): ?>
        <div class="filter-chips-bar mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center gap-2">
          <span class="text-xs text-slate-500 font-semibold">Đang lọc:</span>
          <?php if ($q !== ''): ?>
            <a href="<?=url('index.php?page=careers&tab=careers'.($type?'&type='.urlencode($type):'').($group?'&group='.urlencode($group):''))?>" class="filter-chip">
              Từ khóa: "<?=e($q)?>" <span class="chip-remove">✕</span>
            </a>
          <?php endif; ?>
          <?php if ($type !== ''): ?>
            <a href="<?=url('index.php?page=careers&tab=careers'.($q?'&q='.urlencode($q):'').($group?'&group='.urlencode($group):''))?>" class="filter-chip">
              RIASEC: <?=e($type)?> <span class="chip-remove">✕</span>
            </a>
          <?php endif; ?>
          <?php if ($group !== ''): ?>
            <a href="<?=url('index.php?page=careers&tab=careers'.($q?'&q='.urlencode($q):'').($type?'&type='.urlencode($type):''))?>" class="filter-chip">
              Lĩnh vực: <?=e($group)?> <span class="chip-remove">✕</span>
            </a>
          <?php endif; ?>
          <a href="<?=url('index.php?page=careers&tab=careers')?>" class="text-xs text-brand-600 hover:underline font-semibold ml-2">
            Xóa tất cả
          </a>
        </div>
      <?php endif; ?>
    </div>

    <div class="flex items-center justify-between mb-4">
      <p class="text-sm font-semibold text-slate-600" role="status">
        Tìm thấy <span class="text-brand-600 font-bold"><?=count($careers)?></span> nghề phù hợp
      </p>
      <span class="text-xs text-slate-400">Dữ liệu định hướng tham khảo</span>
    </div>

    <?php if (!$careers): ?>
      <div class="empty-state">
        <div class="empty-icon">🔍</div>
        <h3 class="text-lg font-bold text-slate-800 mb-1">Không tìm thấy nghề phù hợp</h3>
        <p class="text-sm text-slate-500 mb-4">Thử thay đổi từ khóa hoặc xóa bớt tiêu chí lọc để xem thêm các nghề khác.</p>
        <a class="btn btn-outline" href="<?=url('index.php?page=careers&tab=careers')?>">Xem toàn bộ nghề</a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($careers as $career): 
          $weights = json_decode($career['riasec_weights'], true);
          if (!is_array($weights)) $weights = [];
          arsort($weights);
          $topTypes = array_slice(array_keys($weights), 0, 2);
          $hasSim = !empty($career['simulation_url']) || in_array($career['slug'], ['giao-vien-thcs','bac-si-da-khoa','lap-trinh-vien-frontend','chuyen-vien-an-ninh-mang'], true);
        ?>
          <div class="card career-card-clean">
            <div class="flex items-start justify-between gap-3 mb-2">
              <span class="text-3xl p-2 bg-slate-50 rounded-xl border border-slate-100"><?=e($career['icon'] ?: '💼')?></span>
              <div class="flex flex-wrap gap-1 justify-end">
                <?php foreach ($topTypes as $k): ?>
                  <span class="badge badge-subtle"><?=e($k.' · '.(riasec_labels()[$k] ?? $k))?></span>
                <?php endforeach; ?>
              </div>
            </div>

            <h3 class="font-bold text-lg text-slate-900 mb-1">
              <a href="<?=url('index.php?page=career&id='.(int)$career['id'])?>" class="hover:text-brand-600 transition-colors">
                <?=e($career['title'])?>
              </a>
            </h3>

            <p class="text-sm text-slate-600 line-clamp-2 mb-3 flex-1">
              <?=e($career['short_description'])?>
            </p>

            <?php if (!empty($career['subjects'])): ?>
              <p class="text-xs text-slate-500 mb-4">
                <span class="font-semibold text-slate-700">Môn học liên quan:</span> <?=e($career['subjects'])?>
              </p>
            <?php endif; ?>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
              <?php if ($hasSim): ?>
                <span class="text-emerald-700 font-semibold flex items-center gap-1">
                  <span>🎮</span> Có mô phỏng
                </span>
              <?php else: ?>
                <span class="text-slate-400">Hồ sơ nghề nghiệp</span>
              <?php endif; ?>
              <a href="<?=url('index.php?page=career&id='.(int)$career['id'])?>" class="text-brand-600 font-bold hover:underline">
                Chi tiết nghề →
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php elseif ($activeTab === 'articles'): ?>
  <!-- ================= TAB: ARTICLES ================= -->
  <section aria-labelledby="articles-heading">
    <div class="filter-card mb-6">
      <form method="get" action="<?=url('index.php')?>" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="page" value="knowledge-hub">
        <input type="hidden" name="tab" value="articles">
        
        <label for="articleCategory" class="text-sm font-semibold text-slate-700">Chuyên mục:</label>
        <select name="category" id="articleCategory" class="form-select" style="max-width:220px">
          <option value="">Tất cả bài viết</option>
          <?php foreach ($articleCategories as $key => $label): ?>
            <option value="<?=e($key)?>" <?=$key===$category?'selected':''?>><?=e($label)?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline btn-sm">Lọc</button>
        <?php if ($category !== ''): ?>
          <a href="<?=url('index.php?page=knowledge-hub&tab=articles')?>" class="text-xs text-brand-600 hover:underline">Xóa lọc</a>
        <?php endif; ?>
      </form>
    </div>

    <?php if (!$articles): ?>
      <div class="empty-state">
        <div class="empty-icon">📰</div>
        <h3 class="text-lg font-bold text-slate-800 mb-1">Chưa có bài viết trong chuyên mục này</h3>
        <p class="text-sm text-slate-500 mb-4">Mời bạn chọn chuyên mục khác để tiếp tục đọc.</p>
        <a class="btn btn-outline" href="<?=url('index.php?page=knowledge-hub&tab=articles')?>">Xem tất cả bài viết</a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($articles as $article): ?>
          <article class="card article-card-clean">
            <div class="flex items-center justify-between text-xs mb-2">
              <span class="badge badge-purple"><?=e($articleCategories[$article['category']] ?? 'Cẩm nang')?></span>
              <span class="text-slate-400"><?=e(date('d/m/Y', strtotime($article['updated_at'])))?></span>
            </div>
            
            <h3 class="font-bold text-base text-slate-900 mb-2">
              <a href="<?=url('index.php?page=knowledge-hub&article='.rawurlencode($article['slug']))?>" class="hover:text-brand-600 transition-colors">
                <?=e($article['title'])?>
              </a>
            </h3>

            <p class="text-sm text-slate-600 line-clamp-3 mb-4 flex-1">
              <?=e($article['summary'])?>
            </p>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
              <span class="text-xs text-slate-400">Tài liệu tham khảo</span>
              <a class="text-xs font-bold text-brand-600 hover:underline" href="<?=url('index.php?page=knowledge-hub&article='.rawurlencode($article['slug']))?>">
                Đọc bài viết →
              </a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php elseif ($activeTab === 'portals'): ?>
  <!-- ================= TAB: PORTALS ================= -->
  <section aria-labelledby="portals-heading">
    <div class="notice-box mb-6">
      <div class="flex items-start gap-3">
        <span class="text-xl">ℹ️</span>
        <div>
          <h3 class="font-bold text-sm text-slate-900 mb-1">Lưu ý khi tra cứu ngoài hệ thống</h3>
          <p class="text-sm text-slate-600">
            Các liên kết dưới đây dẫn tới cổng thông tin chính thức của các cơ quan quản lý và đơn vị khảo thí. Hãy luôn xác minh các mốc thời gian và hướng dẫn tuyển sinh từ nguồn chính thức của đúng năm dự tuyển.
          </p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <?php foreach ($portals as $portal): 
        $target = safe_external_url($portal['target_url'] ?? '');
        if ($target === '') continue;
        $host = parse_url($target, PHP_URL_HOST);
      ?>
        <article class="card portal-card-clean">
          <div class="flex items-center gap-3 mb-3">
            <span class="text-2xl p-2 bg-slate-50 rounded-lg border border-slate-100"><?=e($portal['icon'] ?: '🌐')?></span>
            <div>
              <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block"><?=e($portal['organization'] ?: 'Đơn vị uy tín')?></span>
              <h3 class="font-bold text-base text-slate-900"><?=e($portal['title'])?></h3>
            </div>
          </div>

          <p class="text-sm text-slate-600 mb-4 flex-1">
            <?=e($portal['description'])?>
          </p>

          <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
            <span class="text-xs text-slate-400 truncate max-w-[150px]"><?=e($host)?></span>
            <a class="btn btn-outline btn-sm" href="<?=e($target)?>" target="_blank" rel="noopener noreferrer">
              Mở cổng thông tin ↗
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

</div>
<?php 
require __DIR__ . '/footer.php'; 
exit;

