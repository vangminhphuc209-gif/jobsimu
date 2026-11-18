<?php
declare(strict_types=1);

$me = user();
$site = setting('site_name', cfg('app_name', 'Hướng Nghiệp 360'));
$navCurrentPage = is_string($page ?? null) ? $page : (is_string($_GET['page'] ?? null) ? $_GET['page'] : 'home');
$currentPage = $navCurrentPage;

// Detect active journey categories
$isDiscoverActive = in_array($navCurrentPage, ['discover', 'assessment-hub', 'assessment', 'test-big5', 'test-caas', 'test-mbti', 'test-eq', 'assessment-report', 'constraints', 'result', 'soka-mirror'], true);
$isLookupActive = in_array($navCurrentPage, ['lookup', 'majors', 'major-detail', 'university-explorer', 'university-compare', 'knowledge-hub', 'careers', 'career'], true);
$isCompareActive = ($navCurrentPage === 'compare');
$isPlanActive = in_array($navCurrentPage, ['plan', 'plan-share', 'ai-roadmap', 'ai-chat', 'simulations', 'simulation', 'simulation-result', 'simulation-3d', 'dashboard', 'profile'], true);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Nền tảng khám phá bản thân và định hướng nghề nghiệp bằng dữ liệu mở, trắc nghiệm chuẩn hóa và trải nghiệm thực tế dành cho học sinh THPT Việt Nam">
  <meta name="theme-color" content="#6D28D9">
  <title><?=e($pageTitle ?? $site)?> · <?=e($site)?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?=url('assets/css/app.css')?>">
</head>
<body>
  <!-- Skip link for keyboard accessibility -->
  <a href="#main-content" class="skip-link">Chuyển đến nội dung chính</a>

  <header class="nav-header">
    <div class="nav-container">
      <!-- Brand Logo -->
      <a class="brand-link" href="<?=url('index.php')?>">
        <span class="brand-icon" aria-hidden="true">✦</span>
        <span><?=e($site)?></span>
      </a>

<?php
$useJourney4StepNav = function_exists('journey_feature_enabled') ? journey_feature_enabled('journey_4step') : true;
?>
      <!-- Desktop Primary Navigation -->
      <nav class="desktop-nav" aria-label="Điều hướng chính">
        <?php if (!$useJourney4StepNav): ?>
          <!-- Legacy 4-Hub Navigation (Fallback when journey_4step flag is OFF) -->
          <div class="nav-item has-dropdown <?= $isLookupActive ? 'active' : '' ?>">
            <button type="button" class="nav-link-btn" aria-expanded="false" aria-haspopup="true" aria-controls="menu-legacy-explore">
              <span>Khám phá</span>
              <svg class="chevron" aria-hidden="true" viewBox="0 0 16 16" fill="currentColor"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg>
            </button>
            <div id="menu-legacy-explore" class="dropdown-menu" role="menu">
              <a href="<?=url('index.php?page=careers')?>" class="dropdown-item" role="menuitem"><div><strong>Nghề nghiệp</strong><span class="item-desc">27 hồ sơ nghề &amp; yêu cầu thực tế</span></div></a>
              <a href="<?=url('index.php?page=majors')?>" class="dropdown-item" role="menuitem"><div><strong>Ngành học (358 ngành)</strong><span class="item-desc">Danh mục ngành đào tạo toàn quốc</span></div></a>
              <a href="<?=url('index.php?page=university-explorer')?>" class="dropdown-item" role="menuitem"><div><strong>Trường học &amp; Điểm chuẩn</strong><span class="item-desc">Tra cứu trường &amp; điểm chuẩn</span></div></a>
              <a href="<?=url('index.php?page=university-compare')?>" class="dropdown-item" role="menuitem"><div><strong>So sánh trường đại học</strong><span class="item-desc">Đối chiếu chương trình &amp; điểm chuẩn</span></div></a>
              <a href="<?=url('index.php?page=knowledge-hub')?>" class="dropdown-item" role="menuitem"><div><strong>Bài viết &amp; Cẩm nang</strong><span class="item-desc">Kiến thức hướng nghiệp &amp; tuyển sinh</span></div></a>
            </div>
          </div>
          <div class="nav-item has-dropdown <?= $isDiscoverActive ? 'active' : '' ?>">
            <button type="button" class="nav-link-btn" aria-expanded="false" aria-haspopup="true" aria-controls="menu-legacy-self">
              <span>Hiểu mình</span>
              <svg class="chevron" aria-hidden="true" viewBox="0 0 16 16" fill="currentColor"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg>
            </button>
            <div id="menu-legacy-self" class="dropdown-menu" role="menu">
              <a href="<?=url('index.php?page=assessment-hub')?>" class="dropdown-item" role="menuitem"><div><strong>Các bài đánh giá</strong><span class="item-desc">Holland, Big Five, CAAS, MBTI, EQ, SOKA</span></div></a>
              <a href="<?=url('index.php?page=assessment-report')?>" class="dropdown-item" role="menuitem"><div><strong>Báo cáo tổng hợp</strong><span class="item-desc">Hồ sơ tâm lý &amp; năng lực</span></div></a>
            </div>
          </div>
          <div class="nav-item has-dropdown">
            <button type="button" class="nav-link-btn" aria-expanded="false" aria-haspopup="true" aria-controls="menu-legacy-exp">
              <span>Trải nghiệm</span>
              <svg class="chevron" aria-hidden="true" viewBox="0 0 16 16" fill="currentColor"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg>
            </button>
            <div id="menu-legacy-exp" class="dropdown-menu" role="menu">
              <a href="<?=url('index.php?page=simulations')?>" class="dropdown-item" role="menuitem"><div><strong>Mô phỏng nghề (2D &amp; 3D)</strong><span class="item-desc">Tình huống thực tế</span></div></a>
              <a href="<?=url('index.php?page=ai-roadmap#career-conversations')?>" class="dropdown-item" role="menuitem"><div><strong>Góc người trong nghề</strong><span class="item-desc">Trò chuyện &amp; kinh nghiệm</span></div></a>
              <a href="<?=url('index.php?page=simulations#activity')?>" class="dropdown-item" role="menuitem"><div><strong>Nhật ký trải nghiệm</strong><span class="item-desc">Lịch sử mô phỏng</span></div></a>
            </div>
          </div>
          <div class="nav-item has-dropdown <?= $isPlanActive ? 'active' : '' ?>">
            <button type="button" class="nav-link-btn" aria-expanded="false" aria-haspopup="true" aria-controls="menu-legacy-my">
              <span>Lộ trình của tôi</span>
              <svg class="chevron" aria-hidden="true" viewBox="0 0 16 16" fill="currentColor"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg>
            </button>
            <div id="menu-legacy-my" class="dropdown-menu" role="menu">
              <a href="<?=url('index.php?page=dashboard')?>" class="dropdown-item" role="menuitem"><div><strong>Tổng quan cá nhân</strong><span class="item-desc">Hồ sơ &amp; lịch sử đánh giá</span></div></a>
              <a href="<?=url('index.php?page=ai-roadmap')?>" class="dropdown-item" role="menuitem"><div><strong>Kế hoạch hành động</strong><span class="item-desc">Bản đồ hành trình cá nhân</span></div></a>
              <a href="<?=url('index.php?page=dashboard#favorites')?>" class="dropdown-item" role="menuitem"><div><strong>Nội dung đã lưu</strong><span class="item-desc">Nghề nghiệp yêu thích</span></div></a>
            </div>
          </div>
        <?php else: ?>
        <!-- 1. Khám phá bản thân -->
        <div class="nav-item has-dropdown <?= $isDiscoverActive ? 'active' : '' ?>">
          <button type="button" class="nav-link-btn" aria-expanded="false" aria-haspopup="true" aria-controls="menu-discover" <?= $isDiscoverActive ? 'aria-current="true"' : '' ?>>
            <span>Khám phá bản thân</span>
            <svg class="chevron" aria-hidden="true" viewBox="0 0 16 16" fill="currentColor">
              <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
            </svg>
          </button>
          <div id="menu-discover" class="dropdown-menu" role="menu">
            <?php if (journey_feature_enabled('discover_quick')): ?>
            <a href="<?=url('index.php?page=discover')?>" class="dropdown-item" role="menuitem" <?= $navCurrentPage === 'discover' ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Khám phá nhanh (5 câu · bổ sung)</strong>
                <span class="item-desc">Gợi mở nhanh môn học, hoạt động &amp; ưu tiên cá nhân</span>
              </div>
            </a>
            <?php endif; ?>
            <a href="<?=url('index.php?page=assessment-hub')?>" class="dropdown-item" role="menuitem" <?= in_array($navCurrentPage, ['assessment-hub', 'assessment', 'test-big5', 'test-caas', 'test-mbti', 'test-eq', 'constraints', 'soka-mirror'], true) ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Các bài đánh giá chuẩn hóa</strong>
                <span class="item-desc">Holland RIASEC, Big Five, CAAS, MBTI, EQ &amp; SOKA 360°</span>
              </div>
            </a>
            <a href="<?=url('index.php?page=assessment-report')?>" class="dropdown-item" role="menuitem" <?= in_array($navCurrentPage, ['assessment-report', 'result'], true) ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Báo cáo tổng hợp</strong>
                <span class="item-desc">Hồ sơ tâm lý &amp; kết quả đánh giá đã lưu</span>
              </div>
            </a>
          </div>
        </div>

        <!-- 2. Tra cứu -->
        <div class="nav-item has-dropdown <?= $isLookupActive ? 'active' : '' ?>">
          <button type="button" class="nav-link-btn" aria-expanded="false" aria-haspopup="true" aria-controls="menu-lookup" <?= $isLookupActive ? 'aria-current="true"' : '' ?>>
            <span>Tra cứu</span>
            <svg class="chevron" aria-hidden="true" viewBox="0 0 16 16" fill="currentColor">
              <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
            </svg>
          </button>
          <div id="menu-lookup" class="dropdown-menu" role="menu">
            <?php if (journey_feature_enabled('lookup_hub')): ?>
            <a href="<?=url('index.php?page=lookup')?>" class="dropdown-item" role="menuitem" <?= ($navCurrentPage === 'lookup' && empty($_GET['tab'])) ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Trung tâm Tra cứu (Ngành · Trường · Tổ hợp)</strong>
                <span class="item-desc">Lọc nhanh theo 3 lựa chọn kèm nguồn xác minh</span>
              </div>
            </a>
            <?php endif; ?>
            <a href="<?=url('index.php?page=university-explorer')?>" class="dropdown-item" role="menuitem" <?= $navCurrentPage === 'university-explorer' ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Trường học &amp; Điểm chuẩn (Đầy đủ)</strong>
                <span class="item-desc">Khám phá trường đại học, học phí &amp; thông báo</span>
              </div>
            </a>
            <a href="<?=url('index.php?page=majors')?>" class="dropdown-item" role="menuitem" <?= in_array($navCurrentPage, ['majors', 'major-detail'], true) ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Danh mục 358 ngành ĐH/CĐ</strong>
                <span class="item-desc">Kho ngành đào tạo mở rộng toàn quốc</span>
              </div>
            </a>
            <a href="<?=url('index.php?page=careers')?>" class="dropdown-item" role="menuitem" <?= in_array($navCurrentPage, ['careers', 'career'], true) ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Thư viện nghề nghiệp</strong>
                <span class="item-desc">27 hồ sơ nghề &amp; yêu cầu thực tế</span>
              </div>
            </a>
            <a href="<?=url('index.php?page=knowledge-hub')?>" class="dropdown-item" role="menuitem" <?= $navCurrentPage === 'knowledge-hub' ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Bài viết &amp; Cẩm nang</strong>
                <span class="item-desc">Kiến thức hướng nghiệp &amp; tuyển sinh</span>
              </div>
            </a>
          </div>
        </div>

        <!-- 3. So sánh (phân biệt rõ So sánh ưu tiên mới và So sánh trường cũ) -->
        <div class="nav-item has-dropdown <?= ($isCompareActive || $navCurrentPage === 'university-compare') ? 'active' : '' ?>">
          <button type="button" class="nav-link-btn" aria-expanded="false" aria-haspopup="true" aria-controls="menu-compare" <?= ($isCompareActive || $navCurrentPage === 'university-compare') ? 'aria-current="true"' : '' ?>>
            <span>So sánh</span>
            <svg class="chevron" aria-hidden="true" viewBox="0 0 16 16" fill="currentColor">
              <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
            </svg>
          </button>
          <div id="menu-compare" class="dropdown-menu" role="menu">
            <?php if (journey_feature_enabled('compare_priority')): ?>
            <a href="<?=url('index.php?page=compare')?>" class="dropdown-item" role="menuitem" <?= $isCompareActive ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Hiểu mình cần gì (So sánh ưu tiên · mới)</strong>
                <span class="item-desc">20 câu hỏi trọng số trên thang 100 điểm cho 2–7 phương án</span>
              </div>
            </a>
            <?php endif; ?>
            <a href="<?=url('index.php?page=university-compare')?>" class="dropdown-item" role="menuitem" <?= $navCurrentPage === 'university-compare' ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>So sánh trường đại học &amp; điểm chuẩn</strong>
                <span class="item-desc">Đối chiếu trực tiếp chương trình, học phí &amp; điểm chuẩn</span>
              </div>
            </a>
          </div>
        </div>

        <!-- 4. Kế hoạch & Trải nghiệm -->
        <div class="nav-item has-dropdown <?= $isPlanActive ? 'active' : '' ?>">
          <button type="button" class="nav-link-btn" aria-expanded="false" aria-haspopup="true" aria-controls="menu-plan" <?= $isPlanActive ? 'aria-current="true"' : '' ?>>
            <span>Kế hoạch</span>
            <svg class="chevron" aria-hidden="true" viewBox="0 0 16 16" fill="currentColor">
              <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
            </svg>
          </button>
          <div id="menu-plan" class="dropdown-menu" role="menu">
            <?php if (journey_feature_enabled('action_plan')): ?>
            <a href="<?=url('index.php?page=plan')?>" class="dropdown-item" role="menuitem" <?= $navCurrentPage === 'plan' ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Lập kế hoạch theo tháng/năm (mới)</strong>
                <span class="item-desc">Mốc công việc chỉnh sửa được, chia sẻ &amp; xuất PDF</span>
              </div>
            </a>
            <?php endif; ?>
            <a href="<?=url('index.php?page=ai-roadmap')?>" class="dropdown-item" role="menuitem" <?= $navCurrentPage === 'ai-roadmap' ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Bản đồ hành trình &amp; Kế hoạch cũ</strong>
                <span class="item-desc">Mở lại kế hoạch hiện có &amp; theo dõi tiến độ</span>
              </div>
            </a>
            <a href="<?=url('index.php?page=ai-roadmap#career-conversations')?>" class="dropdown-item" role="menuitem">
              <div>
                <strong>Góc người trong nghề</strong>
                <span class="item-desc">Chia sẻ thực tế từ các ngành nghề</span>
              </div>
            </a>
            <a href="<?=url('index.php?page=simulations')?>" class="dropdown-item" role="menuitem" <?= in_array($navCurrentPage, ['simulations', 'simulation', 'simulation-3d'], true) ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Trải nghiệm nghề (2D &amp; 3D)</strong>
                <span class="item-desc">Mô phỏng tình huống &amp; nhật ký trải nghiệm</span>
              </div>
            </a>
            <a href="<?=url('index.php?page=dashboard')?>" class="dropdown-item" role="menuitem" <?= $navCurrentPage === 'dashboard' ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Không gian cá nhân &amp; Đã lưu</strong>
                <span class="item-desc">Hồ sơ, lịch sử đánh giá &amp; nghề yêu thích</span>
              </div>
            </a>
          </div>
        </div>
        <?php endif; ?>
      </nav>

      <!-- Right Action Area -->
      <div class="nav-actions">
        <?php if ($me): ?>
          <!-- Logged In Account Dropdown -->
          <div class="nav-item account-menu-item">
            <button type="button" class="account-btn" aria-expanded="false" aria-haspopup="true" aria-controls="menu-account" aria-label="Menu tài khoản của <?=e($me['name'])?>">
              <span class="account-avatar" style="background:<?=e($me['avatar_color'] ?: '#6D28D9')?>">
                <?=e(mb_strtoupper(mb_substr($me['name'], 0, 1)))?>
              </span>
              <span class="account-name"><?=e($me['name'])?></span>
              <svg class="chevron" aria-hidden="true" width="12" height="12" viewBox="0 0 16 16" fill="currentColor">
                <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
              </svg>
            </button>
            <div id="menu-account" class="dropdown-menu account-dropdown" role="menu">
              <div class="account-dropdown-header">
                <div class="account-dropdown-name"><?=e($me['name'])?></div>
                <div class="account-dropdown-role"><?=e($me['email'])?> · <?=e($me['grade'] ?: 'Học sinh')?></div>
              </div>
              <a href="<?=url('index.php?page=dashboard')?>" class="dropdown-item" role="menuitem">
                <span>👤</span>
                <div>
                  <strong>Tổng quan của tôi</strong>
                </div>
              </a>
              <a href="<?=url('index.php?page=dashboard&tab=settings')?>" class="dropdown-item" role="menuitem">
                <span>⚙️</span>
                <div>
                  <strong>Cài đặt hồ sơ</strong>
                </div>
              </a>
              <?php if ($me['role'] === 'admin'): ?>
                <div class="account-dropdown-divider"></div>
                <a href="<?=url('admin.php')?>" class="dropdown-item" role="menuitem">
                  <span>🛡️</span>
                  <div>
                    <strong>Quản trị hệ thống</strong>
                  </div>
                </a>
              <?php endif; ?>
              <div class="account-dropdown-divider"></div>
              <form method="post" action="<?=url('index.php?page=logout')?>" style="margin:0">
                <?=csrf_field()?>
                <button type="submit" class="dropdown-item dropdown-logout-btn" role="menuitem">
                  <span>🚪</span>
                  <strong>Đăng xuất</strong>
                </button>
              </form>
            </div>
          </div>
        <?php else: ?>
          <!-- Guest Navigation: Clean login / register -->
          <a href="<?=url('index.php?page=login')?>" class="nav-auth-link">Đăng nhập</a>
          <a href="<?=url('index.php?page=register')?>" class="btn btn-primary nav-auth-btn">Đăng ký</a>
        <?php endif; ?>

        <!-- Mobile Drawer Toggle Button -->
        <button class="nav-mobile-toggle" type="button" aria-label="Mở menu điều hướng" aria-expanded="false" aria-controls="mobileNavDrawer">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
      </div>
    </div>
  </header>

  <!-- Mobile Navigation Drawer -->
  <div id="mobileNavDrawer" class="mobile-nav-drawer" role="dialog" aria-modal="true" aria-label="Menu điều hướng" aria-hidden="true">
    <div class="mobile-drawer-content">
      <div style="display:flex;align-items:center;justify-content:space-between;padding-bottom:12px;border-bottom:1px solid var(--border-default)">
        <a class="brand-link" href="<?=url('index.php')?>">
          <span class="brand-icon">✦</span>
          <span><?=e($site)?></span>
        </a>
        <button class="mobile-drawer-close" type="button" aria-label="Đóng menu">✕</button>
      </div>

      <?php if (!$useJourney4StepNav): ?>
        <div>
          <div class="mobile-nav-group-title">Khám phá</div>
          <a href="<?=url('index.php?page=careers')?>" class="mobile-nav-link <?= $currentPage === 'careers' ? 'active' : '' ?>">Nghề nghiệp</a>
          <a href="<?=url('index.php?page=majors')?>" class="mobile-nav-link <?= in_array($currentPage, ['majors', 'major-detail'], true) ? 'active' : '' ?>">Ngành học (358 ngành)</a>
          <a href="<?=url('index.php?page=university-explorer')?>" class="mobile-nav-link <?= $currentPage === 'university-explorer' ? 'active' : '' ?>">Trường học &amp; Điểm chuẩn</a>
          <a href="<?=url('index.php?page=university-compare')?>" class="mobile-nav-link <?= $currentPage === 'university-compare' ? 'active' : '' ?>">So sánh trường đại học</a>
          <a href="<?=url('index.php?page=knowledge-hub')?>" class="mobile-nav-link <?= $currentPage === 'knowledge-hub' ? 'active' : '' ?>">Bài viết &amp; Cẩm nang</a>
        </div>
        <div>
          <div class="mobile-nav-group-title">Hiểu mình</div>
          <a href="<?=url('index.php?page=assessment-hub')?>" class="mobile-nav-link <?= in_array($currentPage, ['assessment-hub', 'assessment'], true) ? 'active' : '' ?>">Các bài đánh giá</a>
          <a href="<?=url('index.php?page=assessment-report')?>" class="mobile-nav-link <?= in_array($currentPage, ['assessment-report', 'result'], true) ? 'active' : '' ?>">Báo cáo tổng hợp</a>
        </div>
        <div>
          <div class="mobile-nav-group-title">Trải nghiệm &amp; Lộ trình</div>
          <a href="<?=url('index.php?page=simulations')?>" class="mobile-nav-link <?= in_array($currentPage, ['simulations', 'simulation', 'simulation-3d'], true) ? 'active' : '' ?>">Mô phỏng nghề (2D &amp; 3D)</a>
          <a href="<?=url('index.php?page=ai-roadmap#career-conversations')?>" class="mobile-nav-link">Góc người trong nghề</a>
          <a href="<?=url('index.php?page=ai-roadmap')?>" class="mobile-nav-link <?= $currentPage === 'ai-roadmap' ? 'active' : '' ?>">Kế hoạch hành động</a>
          <a href="<?=url('index.php?page=dashboard')?>" class="mobile-nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">Tổng quan cá nhân</a>
        </div>
      <?php else: ?>
      <!-- Mobile Category 1: Khám phá bản thân -->
      <div>
        <div class="mobile-nav-group-title">1. Khám phá bản thân</div>
        <?php if (journey_feature_enabled('discover_quick')): ?>
        <a href="<?=url('index.php?page=discover')?>" class="mobile-nav-link <?= $currentPage === 'discover' ? 'active' : '' ?>">Khám phá nhanh (5 câu · bổ sung)</a>
        <?php endif; ?>
        <a href="<?=url('index.php?page=assessment-hub')?>" class="mobile-nav-link <?= in_array($currentPage, ['assessment-hub', 'assessment'], true) ? 'active' : '' ?>">Các bài đánh giá chuẩn hóa</a>
        <a href="<?=url('index.php?page=assessment-report')?>" class="mobile-nav-link <?= in_array($currentPage, ['assessment-report', 'result'], true) ? 'active' : '' ?>">Báo cáo tổng hợp</a>
      </div>

      <!-- Mobile Category 2: Tra cứu -->
      <div>
        <div class="mobile-nav-group-title">2. Tra cứu</div>
        <?php if (journey_feature_enabled('lookup_hub')): ?>
        <a href="<?=url('index.php?page=lookup')?>" class="mobile-nav-link <?= $currentPage === 'lookup' ? 'active' : '' ?>">Ngành · Trường · Tổ hợp</a>
        <?php endif; ?>
        <a href="<?=url('index.php?page=university-explorer')?>" class="mobile-nav-link <?= $currentPage === 'university-explorer' ? 'active' : '' ?>">Trường học &amp; Điểm chuẩn</a>
        <a href="<?=url('index.php?page=majors')?>" class="mobile-nav-link <?= in_array($currentPage, ['majors', 'major-detail'], true) ? 'active' : '' ?>">Danh mục 358 ngành</a>
        <a href="<?=url('index.php?page=careers')?>" class="mobile-nav-link <?= $currentPage === 'careers' ? 'active' : '' ?>">Thư viện nghề nghiệp</a>
        <a href="<?=url('index.php?page=knowledge-hub')?>" class="mobile-nav-link <?= $currentPage === 'knowledge-hub' ? 'active' : '' ?>">Bài viết &amp; Cẩm nang</a>
      </div>

      <!-- Mobile Category 3: So sánh -->
      <div>
        <div class="mobile-nav-group-title">3. So sánh</div>
        <?php if (journey_feature_enabled('compare_priority')): ?>
        <a href="<?=url('index.php?page=compare')?>" class="mobile-nav-link <?= $currentPage === 'compare' ? 'active' : '' ?>">Hiểu mình cần gì (So sánh ưu tiên · 20 câu)</a>
        <?php endif; ?>
        <a href="<?=url('index.php?page=university-compare')?>" class="mobile-nav-link <?= $currentPage === 'university-compare' ? 'active' : '' ?>">So sánh trường đại học &amp; điểm chuẩn</a>
      </div>

      <!-- Mobile Category 4: Kế hoạch & Trải nghiệm -->
      <div>
        <div class="mobile-nav-group-title">4. Kế hoạch &amp; Trải nghiệm</div>
        <?php if (journey_feature_enabled('action_plan')): ?>
        <a href="<?=url('index.php?page=plan')?>" class="mobile-nav-link <?= $currentPage === 'plan' ? 'active' : '' ?>">Lập kế hoạch theo tháng/năm (mới)</a>
        <?php endif; ?>
        <a href="<?=url('index.php?page=ai-roadmap')?>" class="mobile-nav-link <?= $currentPage === 'ai-roadmap' ? 'active' : '' ?>">Bản đồ hành trình &amp; Kế hoạch cũ</a>
        <a href="<?=url('index.php?page=ai-roadmap#career-conversations')?>" class="mobile-nav-link">Góc người trong nghề</a>
        <a href="<?=url('index.php?page=simulations')?>" class="mobile-nav-link <?= in_array($currentPage, ['simulations', 'simulation', 'simulation-3d'], true) ? 'active' : '' ?>">Trải nghiệm nghề (2D &amp; 3D)</a>
        <a href="<?=url('index.php?page=dashboard')?>" class="mobile-nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">Không gian cá nhân &amp; Đã lưu</a>
      </div>
      <?php endif; ?>

      <!-- Mobile User Footer -->
      <div style="margin-top:auto;padding-top:16px;border-top:1px solid var(--border-default)">
        <?php if ($me): ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
            <span class="account-avatar" style="background:<?=e($me['avatar_color'] ?: '#6366f1')?>"><?=e(mb_strtoupper(mb_substr($me['name'], 0, 1)))?></span>
            <div>
              <div style="font-weight:700;font-size:0.9375rem"><?=e($me['name'])?></div>
              <div style="font-size:0.75rem;color:var(--text-subtle)"><?=e($me['email'])?></div>
            </div>
          </div>
          <?php if ($me['role'] === 'admin'): ?>
            <a href="<?=url('admin.php')?>" class="mobile-nav-link" style="color:#4f46e5">🛡️ Quản trị hệ thống</a>
          <?php endif; ?>
          <form method="post" action="<?=url('index.php?page=logout')?>">
            <?=csrf_field()?>
            <button type="submit" class="btn btn-outline" style="width:100%;color:#dc2626;margin-top:8px">Đăng xuất</button>
          </form>
        <?php else: ?>
          <div style="display:grid;gap:8px">
            <a href="<?=url('index.php?page=login')?>" class="btn btn-outline" style="width:100%;text-align:center">Đăng nhập</a>
            <a href="<?=url('index.php?page=register')?>" class="btn btn-primary" style="width:100%;text-align:center">Đăng ký tài khoản</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <main id="main-content" tabindex="-1">
  <?php foreach(pull_flashes() as $f): ?>
    <div class="container" style="margin-top:16px">
      <div class="alert alert-<?=e($f['type'])?>" role="alert"><?=e($f['message'])?></div>
    </div>
  <?php endforeach; ?>
