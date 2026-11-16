<?php
declare(strict_types=1);

$me = user();
$site = setting('site_name', cfg('app_name', 'Hướng Nghiệp 360'));
$navCurrentPage = is_string($page ?? null) ? $page : (is_string($_GET['page'] ?? null) ? $_GET['page'] : 'home');
$currentPage = $navCurrentPage;

// Detect active hub categories
$isExploreActive = in_array($navCurrentPage, ['majors', 'major-detail', 'university-explorer', 'university-compare', 'knowledge-hub', 'careers', 'career'], true);
$isSelfActive = in_array($navCurrentPage, ['assessment-hub', 'assessment', 'test-big5', 'test-caas', 'test-mbti', 'test-eq', 'assessment-report', 'constraints', 'result', 'soka-mirror'], true);
$isExperienceActive = in_array($navCurrentPage, ['simulations', 'simulation', 'simulation-result', 'simulation-3d'], true);
$isJourneyActive = in_array($navCurrentPage, ['dashboard', 'ai-roadmap', 'ai-chat', 'profile'], true);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Nền tảng khám phá bản thân và định hướng nghề nghiệp bằng dữ liệu mở, trắc nghiệm chuẩn hóa và trải nghiệm thực tế dành cho học sinh Việt Nam">
  <meta name="theme-color" content="#4F46E5">
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

      <!-- Desktop Primary Navigation (4 Target Categories) -->
      <nav class="desktop-nav" aria-label="Điều hướng chính">
        <!-- 1. Khám phá -->
        <div class="nav-item has-dropdown <?= $isExploreActive ? 'active' : '' ?>">
          <button type="button" class="nav-link-btn" aria-expanded="false" aria-haspopup="true" aria-controls="menu-explore" <?= $isExploreActive ? 'aria-current="true"' : '' ?>>
            <span>Khám phá</span>
            <svg class="chevron" aria-hidden="true" viewBox="0 0 16 16" fill="currentColor">
              <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
            </svg>
          </button>
          <div id="menu-explore" class="dropdown-menu" role="menu">
            <a href="<?=url('index.php?page=careers')?>" class="dropdown-item" role="menuitem" <?= $navCurrentPage === 'careers' ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Nghề nghiệp</strong>
                <span class="item-desc">Thư viện nghề &amp; yêu cầu thực tế</span>
              </div>
            </a>
            <a href="<?=url('index.php?page=majors')?>" class="dropdown-item" role="menuitem" <?= in_array($navCurrentPage, ['majors', 'major-detail'], true) ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Ngành học</strong>
                <span class="item-desc">358 ngành đào tạo đại học, CĐ</span>
              </div>
            </a>
            <a href="<?=url('index.php?page=university-explorer')?>" class="dropdown-item" role="menuitem" <?= in_array($navCurrentPage, ['university-explorer', 'university-compare'], true) ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Trường học &amp; Điểm chuẩn</strong>
                <span class="item-desc">Tra cứu &amp; đối chiếu cơ sở đào tạo</span>
              </div>
            </a>
            <a href="<?=url('index.php?page=knowledge-hub')?>" class="dropdown-item" role="menuitem" <?= $navCurrentPage === 'knowledge-hub' ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Bài viết &amp; Cẩm nang</strong>
                <span class="item-desc">Kiến thức tuyển sinh &amp; hướng dẫn</span>
              </div>
            </a>
          </div>
        </div>

        <!-- 2. Hiểu bản thân -->
        <div class="nav-item has-dropdown <?= $isSelfActive ? 'active' : '' ?>">
          <button type="button" class="nav-link-btn" aria-expanded="false" aria-haspopup="true" aria-controls="menu-self" <?= $isSelfActive ? 'aria-current="true"' : '' ?>>
            <span>Hiểu bản thân</span>
            <svg class="chevron" aria-hidden="true" viewBox="0 0 16 16" fill="currentColor">
              <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
            </svg>
          </button>
          <div id="menu-self" class="dropdown-menu" role="menu">
            <a href="<?=url('index.php?page=assessment-hub')?>" class="dropdown-item" role="menuitem" <?= in_array($navCurrentPage, ['assessment-hub', 'assessment', 'test-big5', 'test-caas', 'test-mbti', 'test-eq', 'constraints', 'soka-mirror'], true) ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Các bài đánh giá</strong>
                <span class="item-desc">Sở thích, tính cách &amp; độ thích ứng</span>
              </div>
            </a>
            <a href="<?=url('index.php?page=assessment-report')?>" class="dropdown-item" role="menuitem" <?= in_array($navCurrentPage, ['assessment-report', 'result'], true) ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Báo cáo tổng hợp</strong>
                <span class="item-desc">Hồ sơ khám phá &amp; gợi mở hướng đi</span>
              </div>
            </a>
          </div>
        </div>

        <!-- 3. Trải nghiệm nghề -->
        <div class="nav-item has-dropdown <?= $isExperienceActive ? 'active' : '' ?>">
          <button type="button" class="nav-link-btn" aria-expanded="false" aria-haspopup="true" aria-controls="menu-experience" <?= $isExperienceActive ? 'aria-current="true"' : '' ?>>
            <span>Trải nghiệm nghề</span>
            <svg class="chevron" aria-hidden="true" viewBox="0 0 16 16" fill="currentColor">
              <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
            </svg>
          </button>
          <div id="menu-experience" class="dropdown-menu" role="menu">
            <a href="<?=url('index.php?page=simulations')?>" class="dropdown-item" role="menuitem" <?= in_array($navCurrentPage, ['simulations', 'simulation', 'simulation-3d'], true) ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Mô phỏng tình huống</strong>
                <span class="item-desc">Thử giải quyết công việc thực tế</span>
              </div>
            </a>
            <a href="<?=url('index.php?page=ai-roadmap#career-conversations')?>" class="dropdown-item" role="menuitem">
              <div>
                <strong>Góc người trong nghề</strong>
                <span class="item-desc">Tương tác tình huống nghề nghiệp</span>
              </div>
            </a>
            <a href="<?=url('index.php?page=simulations#activity')?>" class="dropdown-item" role="menuitem" <?= $navCurrentPage === 'simulation-result' ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Nhật ký trải nghiệm</strong>
                <span class="item-desc">Xem lại cảm xúc và bài học đã lưu</span>
              </div>
            </a>
          </div>
        </div>

        <!-- 4. Hành trình của tôi -->
        <div class="nav-item has-dropdown <?= $isJourneyActive ? 'active' : '' ?>">
          <button type="button" class="nav-link-btn" aria-expanded="false" aria-haspopup="true" aria-controls="menu-journey" <?= $isJourneyActive ? 'aria-current="true"' : '' ?>>
            <span>Hành trình của tôi</span>
            <svg class="chevron" aria-hidden="true" viewBox="0 0 16 16" fill="currentColor">
              <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
            </svg>
          </button>
          <div id="menu-journey" class="dropdown-menu" role="menu">
            <a href="<?=url('index.php?page=dashboard')?>" class="dropdown-item" role="menuitem" <?= $navCurrentPage === 'dashboard' ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Tổng quan cá nhân</strong>
                <span class="item-desc">Tiến trình, hoạt động &amp; việc tiếp theo</span>
              </div>
            </a>
            <a href="<?=url('index.php?page=ai-roadmap')?>" class="dropdown-item" role="menuitem" <?= $navCurrentPage === 'ai-roadmap' ? 'aria-current="page"' : '' ?>>
              <div>
                <strong>Kế hoạch hành động</strong>
                <span class="item-desc">Cột mốc tự chủ &amp; trợ lý hỗ trợ</span>
              </div>
            </a>
            <a href="<?=url('index.php?page=dashboard#favorites')?>" class="dropdown-item" role="menuitem">
              <div>
                <strong>Nội dung đã lưu</strong>
                <span class="item-desc">Nghề quan tâm &amp; liên kết tuyển sinh</span>
              </div>
            </a>
          </div>
        </div>
      </nav>

      <!-- Right Action Area -->
      <div class="nav-actions">
        <?php if ($me): ?>
          <!-- Logged In Account Dropdown -->
          <div class="nav-item account-menu-item">
            <button type="button" class="account-btn" aria-expanded="false" aria-haspopup="true" aria-controls="menu-account" aria-label="Menu tài khoản của <?=e($me['name'])?>">
              <span class="account-avatar" style="background:<?=e($me['avatar_color'] ?: '#6366f1')?>">
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
              <a href="<?=url('index.php?page=dashboard#profile')?>" class="dropdown-item" role="menuitem">
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

      <!-- Mobile Category 1: Khám phá -->
      <div>
        <div class="mobile-nav-group-title">1. Khám phá</div>
        <a href="<?=url('index.php?page=careers')?>" class="mobile-nav-link <?= $currentPage === 'careers' ? 'active' : '' ?>">Nghề nghiệp</a>
        <a href="<?=url('index.php?page=majors')?>" class="mobile-nav-link <?= in_array($currentPage, ['majors', 'major-detail'], true) ? 'active' : '' ?>">Ngành học (358 ngành)</a>
        <a href="<?=url('index.php?page=university-explorer')?>" class="mobile-nav-link <?= in_array($currentPage, ['university-explorer', 'university-compare'], true) ? 'active' : '' ?>">Trường học &amp; Điểm chuẩn</a>
        <a href="<?=url('index.php?page=knowledge-hub')?>" class="mobile-nav-link <?= $currentPage === 'knowledge-hub' ? 'active' : '' ?>">Bài viết &amp; Cẩm nang</a>
      </div>

      <!-- Mobile Category 2: Hiểu bản thân -->
      <div>
        <div class="mobile-nav-group-title">2. Hiểu bản thân</div>
        <a href="<?=url('index.php?page=assessment-hub')?>" class="mobile-nav-link <?= in_array($currentPage, ['assessment-hub', 'assessment'], true) ? 'active' : '' ?>">Các bài đánh giá</a>
        <a href="<?=url('index.php?page=assessment-report')?>" class="mobile-nav-link <?= in_array($currentPage, ['assessment-report', 'result'], true) ? 'active' : '' ?>">Báo cáo tổng hợp</a>
      </div>

      <!-- Mobile Category 3: Trải nghiệm nghề -->
      <div>
        <div class="mobile-nav-group-title">3. Trải nghiệm nghề</div>
        <a href="<?=url('index.php?page=simulations')?>" class="mobile-nav-link <?= in_array($currentPage, ['simulations', 'simulation'], true) ? 'active' : '' ?>">Mô phỏng nghề</a>
        <a href="<?=url('index.php?page=ai-roadmap#career-conversations')?>" class="mobile-nav-link">Góc người trong nghề</a>
        <a href="<?=url('index.php?page=simulations#activity')?>" class="mobile-nav-link">Nhật ký trải nghiệm</a>
      </div>

      <!-- Mobile Category 4: Hành trình của tôi -->
      <div>
        <div class="mobile-nav-group-title">4. Hành trình của tôi</div>
        <a href="<?=url('index.php?page=dashboard')?>" class="mobile-nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">Tổng quan cá nhân</a>
        <a href="<?=url('index.php?page=ai-roadmap')?>" class="mobile-nav-link <?= $currentPage === 'ai-roadmap' ? 'active' : '' ?>">Kế hoạch hành động</a>
        <a href="<?=url('index.php?page=dashboard#favorites')?>" class="mobile-nav-link">Nội dung đã lưu</a>
      </div>

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
