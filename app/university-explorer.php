<?php
declare(strict_types=1);

/** University Explorer: publicly accessible verified-first catalog. */
$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100));
$province = trim(mb_substr((string)($_GET['province'] ?? ''), 0, 100));
$selectedGroup = (int)($_GET['group'] ?? 0);
$schoolId = max(0, (int)($_GET['school'] ?? 0));
$programId = max(0, (int)($_GET['program'] ?? 0));

$groups = db()->query('SELECT id,name FROM career_groups ORDER BY sort_order,name')->fetchAll();
$provinces = db()->query("SELECT DISTINCT province FROM institutions WHERE province IS NOT NULL AND province<>'' ORDER BY province")->fetchAll(PDO::FETCH_COLUMN);

$school = null;
$program = null;
$records = [];
$fees = [];
$requirements = [];
$events = [];
$schoolPrograms = [];

if ($schoolId) {
    $s = db()->prepare('SELECT * FROM institutions WHERE id=?');
    $s->execute([$schoolId]);
    $school = $s->fetch();
    if (!$school) {
        http_response_code(404);
    } else {
        $s = db()->prepare('SELECT p.*,m.name AS major_name,m.code AS major_code FROM programs p JOIN majors m ON m.id=p.major_id WHERE p.institution_id=? ORDER BY m.name,p.name');
        $s->execute([$schoolId]);
        $schoolPrograms = $s->fetchAll();
    }
}

if ($programId) {
    $s = db()->prepare('SELECT p.*,i.name AS school_name,i.id AS school_id,i.code AS school_code,i.province AS school_province,i.website,m.name AS major_name,m.code AS major_code FROM programs p JOIN institutions i ON i.id=p.institution_id JOIN majors m ON m.id=p.major_id WHERE p.id=?');
    $s->execute([$programId]);
    $program = $s->fetch();
    if ($program) {
        if (!$schoolId || $schoolId !== (int)$program['school_id']) {
            $schoolId = (int)$program['school_id'];
            $s = db()->prepare('SELECT * FROM institutions WHERE id=?');
            $s->execute([$schoolId]);
            $school = $s->fetch();
            $s = db()->prepare('SELECT p.*,m.name AS major_name,m.code AS major_code FROM programs p JOIN majors m ON m.id=p.major_id WHERE p.institution_id=? ORDER BY m.name,p.name');
            $s->execute([$schoolId]);
            $schoolPrograms = $s->fetchAll();
        }
        foreach ([
            'records' => 'SELECT * FROM admission_records WHERE program_id=? ORDER BY admission_year DESC,method,combination',
            'fees' => 'SELECT * FROM tuition_records WHERE program_id=? ORDER BY academic_year DESC',
            'requirements' => 'SELECT * FROM admission_requirements WHERE program_id=? ORDER BY admission_year DESC',
            'events' => 'SELECT * FROM admission_events WHERE program_id=? ORDER BY admission_year DESC,starts_at'
        ] as $key => $sql) {
            $s = db()->prepare($sql);
            $s->execute([$programId]);
            $$key = $s->fetchAll();
        }
    } else {
        http_response_code(404);
    }
}

$schoolNotices = [];
if ($school) {
    $noticeStmt = db()->prepare('SELECT * FROM institution_admission_notices WHERE institution_id=? ORDER BY admission_year DESC,track,notice_key');
    $noticeStmt->execute([(int)$school['id']]);
    $schoolNotices = $noticeStmt->fetchAll();
}

$tuitionBands = [];
if ($school) {
    $stmt = db()->prepare('SELECT * FROM institution_tuition_bands WHERE institution_id=? ORDER BY program_group,academic_year');
    $stmt->execute([(int)$school['id']]);
    $tuitionBands = $stmt->fetchAll();
}

// Directory Query when not viewing school/program
$schools = [];
if (!$schoolId && !$programId) {
    $sql = 'SELECT i.*,COUNT(DISTINCT p.id) AS program_count FROM institutions i LEFT JOIN programs p ON p.institution_id=i.id';
    $where = [];
    $args = [];
    if ($q !== '') {
        $where[] = '(i.name LIKE ? OR i.code LIKE ? OR EXISTS(SELECT 1 FROM programs p2 JOIN majors m2 ON m2.id=p2.major_id WHERE p2.institution_id=i.id AND m2.name LIKE ?))';
        $term = '%' . $q . '%';
        array_push($args, $term, $term, $term);
    }
    if ($province !== '') {
        $where[] = 'i.province=?';
        $args[] = $province;
    }
    if ($selectedGroup > 0) {
        $where[] = 'EXISTS(SELECT 1 FROM programs p3 JOIN career_major_paths cmp ON cmp.major_id=p3.major_id JOIN careers c ON c.id=cmp.career_id WHERE p3.institution_id=i.id AND c.career_group_id=?)';
        $args[] = $selectedGroup;
    }
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' GROUP BY i.id ORDER BY i.name LIMIT 100';
    $s = db()->prepare($sql);
    $s->execute($args);
    $schools = $s->fetchAll();
}

// Title determination
if ($program) {
    $pageTitle = $program['name'] . ' · ' . $program['school_name'];
} elseif ($school) {
    $pageTitle = $school['name'] . ' · Tra cứu trường';
} else {
    $pageTitle = 'Tra cứu trường đại học & cao đẳng';
}

require __DIR__ . '/header.php';
?>

<?php if ($program): ?>
<!-- ================= VIEW 1: PROGRAM DETAIL ================= -->
<section class="page-head">
  <div class="container">
    <nav class="breadcrumb mb-3" aria-label="Đường dẫn trang">
      <a href="<?=url('index.php?page=home')?>">Trang chủ</a>
      <span class="breadcrumb-sep">/</span>
      <a href="<?=url('index.php?page=university-explorer')?>">Tra cứu trường</a>
      <span class="breadcrumb-sep">/</span>
      <a href="<?=url('index.php?page=university-explorer&school='.(int)$school['id'])?>"><?=e($school['name'])?></a>
      <span class="breadcrumb-sep">/</span>
      <span aria-current="page"><?=e($program['name'])?></span>
    </nav>
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <span class="badge badge-purple mb-2">Chương trình đào tạo</span>
        <h1 class="page-title mb-1"><?=e($program['name'])?></h1>
        <p class="page-subtitle">
          Ngành: <strong><?=e($program['major_name'])?></strong> <?=!empty($program['major_code'])?'('.e($program['major_code']).')':''?> · 
          Cơ sở: <strong><?=e($program['school_name'])?></strong> (<?=e($program['school_province'] ?: 'Việt Nam')?>)
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a class="btn btn-outline btn-sm" href="<?=url('index.php?page=university-compare&programs[]='.(int)$program['id'])?>">
          ⚖️ So sánh chương trình này
        </a>
        <a class="btn btn-outline btn-sm" href="<?=url('index.php?page=university-explorer&school='.(int)$school['id'])?>">
          ← Về hồ sơ trường
        </a>
      </div>
    </div>
  </div>
</section>

<div class="container py-8 space-y-6">
  <!-- Program Info Summary -->
  <div class="card p-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
      <div class="p-3 bg-slate-50 rounded-xl">
        <span class="text-xs text-slate-500 font-semibold block uppercase">Tổng số tín chỉ</span>
        <span class="text-lg font-bold text-slate-900 mt-0.5 block">
          <?=isset($program['credits_required']) && $program['credits_required'] !== null ? (int)$program['credits_required'] . ' tín chỉ' : 'Chưa có dữ liệu'?>
        </span>
      </div>
      <div class="p-3 bg-slate-50 rounded-xl">
        <span class="text-xs text-slate-500 font-semibold block uppercase">Cơ sở đào tạo</span>
        <span class="text-base font-bold text-slate-900 mt-0.5 block truncate">
          <?=e($program['school_name'])?> (<?=e($program['school_code'])?>)
        </span>
      </div>
      <div class="p-3 bg-slate-50 rounded-xl">
        <span class="text-xs text-slate-500 font-semibold block uppercase">Đề án tuyển sinh</span>
        <?php $official = safe_external_url($program['official_url'] ?? ''); ?>
        <?php if ($official): ?>
          <a href="<?=e($official)?>" target="_blank" rel="noopener noreferrer" class="text-sm font-bold text-brand-600 hover:underline mt-1 block">
            Xem văn bản chính thức ↗
          </a>
        <?php else: ?>
          <span class="text-sm text-slate-500 mt-1 block">Đang cập nhật link</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Admission Cutoff Scores -->
  <div class="card p-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-lg font-bold text-slate-900">Điểm chuẩn tuyển sinh lịch sử</h2>
        <p class="text-xs text-slate-500">Dữ liệu tham khảo theo từng năm, phương thức xét tuyển và thang điểm.</p>
      </div>
    </div>

    <?php if (!$records): ?>
      <div class="p-4 bg-slate-50 rounded-xl text-center text-sm text-slate-500">
        Chưa có dữ liệu điểm chuẩn xác thực cho chương trình này.
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Năm tuyển sinh</th>
              <th>Phương thức xét tuyển</th>
              <th>Tổ hợp xét tuyển</th>
              <th>Đối tượng thí sinh</th>
              <th>Điểm trúng tuyển</th>
              <th>Nguồn công bố</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $r): ?>
              <tr>
                <td class="font-bold text-slate-900"><?=(int)$r['admission_year']?></td>
                <td><?=e($r['method'] ?: 'Theo đề án')?></td>
                <td><span class="badge badge-subtle"><?=e($r['combination'] ?: 'Điểm chung')?></span></td>
                <td><?=e($r['candidate_group'] ?: 'Chung')?></td>
                <td>
                  <?php if ($r['cutoff_score'] !== null): ?>
                    <strong class="text-brand-600 text-base"><?=e((string)$r['cutoff_score'])?></strong>
                    <span class="text-xs text-slate-500">/ <?=e($r['score_scale'] ?: '?')?></span>
                  <?php else: ?>
                    <span class="text-slate-400">Chưa có dữ liệu</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php $src = safe_external_url($r['source_url'] ?? ''); ?>
                  <?php if ($src): ?>
                    <a href="<?=e($src)?>" target="_blank" rel="noopener noreferrer" class="text-xs text-brand-600 hover:underline">
                      Nguồn gốc ↗
                    </a>
                  <?php else: ?>
                    <span class="text-xs text-slate-400">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <p class="text-xs text-slate-400 mt-3">
      * Lưu ý: Điểm giữa các phương thức xét tuyển và thang điểm khác nhau không có giá trị quy đổi ngang hàng. Hãy kiểm tra đề án chính thức của năm đăng ký.
    </p>
  </div>

  <!-- Tuition by Year -->
  <div class="card p-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-lg font-bold text-slate-900">Học phí theo năm học</h2>
        <p class="text-xs text-slate-500">Mức học phí công bố chính thức cho chương trình này.</p>
      </div>
    </div>

    <?php if (!$fees): ?>
      <div class="p-4 bg-slate-50 rounded-xl text-center text-sm text-slate-500">
        Chưa có dữ liệu học phí xác thực cho chương trình này.
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Năm học</th>
              <th>Cơ sở tính phí</th>
              <th>Mức học phí</th>
              <th>Văn bản công bố</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($fees as $f): ?>
              <tr>
                <td class="font-bold text-slate-900"><?=e($f['academic_year'])?></td>
                <td><?=e($f['charge_basis'] ?: 'Theo kỳ')?></td>
                <td>
                  <?php if ($f['amount_vnd'] !== null): ?>
                    <strong class="text-emerald-700 font-bold"><?=number_format((float)$f['amount_vnd'], 0, ',', '.')?> ₫</strong>
                  <?php else: ?>
                    <span class="text-slate-400">Chưa có dữ liệu</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php $src = safe_external_url($f['source_url'] ?? ''); ?>
                  <?php if ($src): ?>
                    <a href="<?=e($src)?>" target="_blank" rel="noopener noreferrer" class="text-xs text-brand-600 hover:underline">
                      Văn bản trường ↗
                    </a>
                  <?php else: ?>
                    <span class="text-xs text-slate-400">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Requirements & Events -->
  <?php if ($requirements || $events): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <?php if ($requirements): ?>
        <div class="card p-6">
          <h2 class="text-base font-bold text-slate-900 mb-3">Điều kiện tuyển sinh đặc thù</h2>
          <div class="space-y-3">
            <?php foreach ($requirements as $r): ?>
              <div class="p-3 bg-slate-50 rounded-xl text-sm border border-slate-100">
                <div class="flex items-center justify-between mb-1">
                  <span class="badge badge-purple"><?=(int)$r['admission_year']?></span>
                  <span class="font-bold text-xs text-slate-700"><?=e($r['requirement_type'])?></span>
                </div>
                <p class="text-slate-600 text-xs leading-relaxed"><?=e($r['details'])?></p>
                <?php $src = safe_external_url($r['source_url'] ?? ''); if ($src): ?>
                  <a href="<?=e($src)?>" target="_blank" rel="noopener noreferrer" class="text-[11px] text-brand-600 hover:underline mt-1 inline-block">Nguồn ↗</a>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($events): ?>
        <div class="card p-6">
          <h2 class="text-base font-bold text-slate-900 mb-3">Mốc thời gian tuyển sinh</h2>
          <div class="space-y-3">
            <?php foreach ($events as $event): ?>
              <div class="p-3 bg-slate-50 rounded-xl text-sm border border-slate-100">
                <div class="flex items-center justify-between mb-1">
                  <span class="font-bold text-slate-800 text-xs"><?=e($event['event_name'])?></span>
                  <span class="badge badge-subtle"><?=(int)$event['admission_year']?></span>
                </div>
                <p class="text-xs text-slate-600">
                  Thời gian: <strong><?=e($event['starts_at'] ?: 'Chưa công bố')?></strong> – <strong><?=e($event['ends_at'] ?: 'Chưa công bố')?></strong>
                </p>
                <?php $src = safe_external_url($event['source_url'] ?? ''); if ($src): ?>
                  <a href="<?=e($src)?>" target="_blank" rel="noopener noreferrer" class="text-[11px] text-brand-600 hover:underline mt-1 inline-block">Nguồn ↗</a>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>

<?php elseif ($school): ?>
<!-- ================= VIEW 2: SCHOOL PROFILE ================= -->
<section class="page-head">
  <div class="container">
    <nav class="breadcrumb mb-3" aria-label="Đường dẫn trang">
      <a href="<?=url('index.php?page=home')?>">Trang chủ</a>
      <span class="breadcrumb-sep">/</span>
      <a href="<?=url('index.php?page=university-explorer')?>">Tra cứu trường</a>
      <span class="breadcrumb-sep">/</span>
      <span aria-current="page"><?=e($school['name'])?></span>
    </nav>
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-2">
          <span class="badge badge-purple"><?=e($school['code'])?></span>
          <span class="text-sm text-slate-500 font-semibold"><?=e($school['province'] ?: 'Việt Nam')?></span>
        </div>
        <h1 class="page-title mb-1"><?=e($school['name'])?></h1>
        <p class="page-subtitle">Hồ sơ tuyển sinh và chương trình đào tạo chính thức</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <?php $home = safe_external_url($school['website'] ?? ''); ?>
        <?php if ($home): ?>
          <a class="btn btn-outline btn-sm" href="<?=e($home)?>" target="_blank" rel="noopener noreferrer">
            Website chính thức ↗
          </a>
        <?php endif; ?>
        <a class="btn btn-outline btn-sm" href="<?=url('index.php?page=university-explorer')?>">
          ← Danh sách trường
        </a>
      </div>
    </div>
  </div>
</section>

<div class="container py-8 space-y-8">
  <!-- 1. Linked Programs -->
  <section aria-labelledby="programs-heading">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 id="programs-heading" class="text-xl font-bold text-slate-900">Chương trình đào tạo đã liên kết</h2>
        <p class="text-xs text-slate-500">Các chương trình hiện đã có hồ sơ điểm chuẩn và thông tin tuyển sinh.</p>
      </div>
      <span class="text-xs font-semibold text-slate-500"><?=count($schoolPrograms)?> chương trình</span>
    </div>

    <?php if (!$schoolPrograms): ?>
      <div class="p-6 bg-slate-50 rounded-2xl text-center text-sm text-slate-500 border border-slate-100">
        Chưa có chương trình đào tạo nào được liên kết cho trường này trên hệ thống. Đây là trạng thái liên kết dữ liệu, không có nghĩa trường không có ngành tuyển sinh.
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($schoolPrograms as $sp): ?>
          <div class="card p-4 flex flex-col justify-between hover:border-brand-200 transition-all">
            <div>
              <div class="flex items-center justify-between text-xs mb-1">
                <span class="badge badge-subtle"><?=e($sp['major_code'] ?: 'Ngành')?></span>
              </div>
              <h3 class="font-bold text-base text-slate-900 mb-1">
                <a href="<?=url('index.php?page=university-explorer&school='.(int)$school['id'].'&program='.(int)$sp['id'])?>" class="hover:text-brand-600">
                  <?=e($sp['name'])?>
                </a>
              </h3>
              <p class="text-xs text-slate-600 line-clamp-1 mb-3">
                Thuộc ngành: <strong><?=e($sp['major_name'])?></strong>
              </p>
            </div>
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
              <span class="text-xs text-slate-400">
                <?=isset($sp['credits_required']) && $sp['credits_required'] !== null ? (int)$sp['credits_required'] . ' tín chỉ' : 'Chưa có dữ liệu'?>
              </span>
              <a class="text-xs font-bold text-brand-600 hover:underline" href="<?=url('index.php?page=university-explorer&school='.(int)$school['id'].'&program='.(int)$sp['id'])?>">
                Chi tiết tuyển sinh →
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- 2. Tuition Bands by Group -->
  <?php if ($tuitionBands): ?>
    <section class="card p-6" aria-labelledby="tuition-heading">
      <div class="mb-4">
        <h2 id="tuition-heading" class="text-lg font-bold text-slate-900">Học phí tham khảo theo nhóm chương trình</h2>
        <div class="p-3 bg-amber-50/70 border border-amber-200/80 rounded-xl text-xs text-amber-900 mt-2">
          ⚠️ <strong>Lưu ý quan trọng:</strong> Đây là khoảng học phí tham khảo theo nhóm ngành/chương trình do nhà trường công bố, <strong>không phải học phí chính xác của từng ngành cụ thể</strong>. Học phí thực tế phụ thuộc vào khóa học và khối lượng tín chỉ đăng ký hàng kỳ.
        </div>
      </div>

      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Năm học</th>
              <th>Nhóm chương trình</th>
              <th>Khoảng học phí / học kỳ</th>
              <th>Biến động so với năm trước</th>
              <th>Văn bản công bố</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $previousBands = [];
            foreach ($tuitionBands as $band):
              $key = $band['program_group'] . '|' . $band['charge_basis'];
              $change = isset($previousBands[$key]) ? admissions_p22_band_change($previousBands[$key], $band) : null;
              $previousBands[$key] = $band;
            ?>
              <tr>
                <td class="font-bold text-slate-900"><?=e($band['academic_year'])?></td>
                <td><span class="font-semibold text-slate-800"><?=e($band['program_group'])?></span></td>
                <td>
                  <strong class="text-emerald-700">
                    <?=number_format((float)$band['minimum_vnd'], 0, ',', '.')?> – <?=number_format((float)$band['maximum_vnd'], 0, ',', '.')?> ₫
                  </strong>
                  <span class="text-xs text-slate-500">/ học kỳ</span>
                </td>
                <td>
                  <?php if ($change === null): ?>
                    <span class="text-xs text-slate-400">Chưa có dữ liệu so sánh</span>
                  <?php else: ?>
                    <span class="text-xs font-semibold text-slate-700">
                      <?=e(number_format($change['min_percent'], 2, ',', '.'))?>% / <?=e(number_format($change['max_percent'], 2, ',', '.'))?>%
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php $src = safe_external_url($band['source_url'] ?? ''); ?>
                  <?php if ($src): ?>
                    <a href="<?=e($src)?>" rel="noopener noreferrer" target="_blank" class="text-xs text-brand-600 hover:underline">
                      Xem công bố ↗
                    </a>
                  <?php else: ?>
                    <span class="text-xs text-slate-400">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>

  <!-- 3. Admission Notices -->
  <?php if ($schoolNotices): ?>
    <section class="card p-6" aria-labelledby="notices-heading">
      <h2 id="notices-heading" class="text-lg font-bold text-slate-900 mb-2">Thông báo tuyển sinh và điều kiện đặc thù</h2>
      <p class="text-xs text-slate-500 mb-4">Dữ liệu thông báo từ nhà trường theo từng năm tuyển sinh.</p>

      <div class="space-y-3">
        <?php foreach ($schoolNotices as $notice): ?>
          <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
              <strong class="text-sm text-slate-900"><?=e($notice['title'])?></strong>
              <div class="flex items-center gap-1.5">
                <span class="badge badge-purple"><?=(int)$notice['admission_year']?></span>
                <span class="badge badge-subtle"><?=e($notice['track'])?></span>
              </div>
            </div>
            <p class="text-xs text-slate-600 mt-1 leading-relaxed"><?=e($notice['description'])?></p>
            <div class="mt-2 pt-2 border-t border-slate-200/60 flex items-center justify-between text-xs text-slate-400">
              <span>
                <?php if ($notice['starts_on'] || $notice['ends_on']): ?>
                  Mốc thời gian: <?=e($notice['starts_on'] ?: 'Chưa rõ')?> – <?=e($notice['ends_on'] ?: 'Chưa rõ')?>
                <?php else: ?>
                  Theo đề án chính thức
                <?php endif; ?>
              </span>
              <?php $src = safe_external_url($notice['source_url'] ?? ''); ?>
              <?php if ($src): ?>
                <a href="<?=e($src)?>" rel="noopener noreferrer" target="_blank" class="text-brand-600 hover:underline font-semibold">
                  Xem thông báo gốc ↗
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>

<?php else: ?>
<!-- ================= VIEW 3: SCHOOL DIRECTORY ================= -->
<section class="page-head">
  <div class="container">
    <nav class="breadcrumb mb-3" aria-label="Đường dẫn trang">
      <a href="<?=url('index.php?page=home')?>">Trang chủ</a>
      <span class="breadcrumb-sep">/</span>
      <span aria-current="page">Tra cứu trường</span>
    </nav>
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="page-title">Tra cứu trường đại học & cao đẳng</h1>
        <p class="page-subtitle">Tìm kiếm cơ sở đào tạo, tra cứu chương trình đào tạo và đối chiếu thông tin tuyển sinh chính thức.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a class="btn btn-outline btn-sm" href="<?=url('index.php?page=majors')?>">
          Khám phá danh mục ngành →
        </a>
        <a class="btn btn-outline btn-sm" href="<?=url('index.php?page=university-compare')?>">
          So sánh chương trình →
        </a>
      </div>
    </div>
  </div>
</section>

<div class="container py-8">
  <!-- Search & Filter Card -->
  <div class="filter-card mb-6">
    <form method="get" action="<?=url('index.php')?>" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
      <input type="hidden" name="page" value="university-explorer">

      <div class="md:col-span-5">
        <label for="uniq" class="form-label">Tên trường, mã trường hoặc ngành đào tạo</label>
        <input id="uniq" class="form-input" type="search" name="q" value="<?=e($q)?>" placeholder="Ví dụ: Bách khoa, Y Hà Nội, Kinh tế...">
      </div>

      <div class="md:col-span-3">
        <label for="unip" class="form-label">Tỉnh / Thành phố</label>
        <select id="unip" name="province" class="form-select">
          <option value="">Toàn quốc</option>
          <?php foreach ($provinces as $p): ?>
            <option value="<?=e($p)?>" <?=$province===$p?'selected':''?>><?=e($p)?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="md:col-span-3">
        <label for="unig" class="form-label">Lĩnh vực nghề liên quan</label>
        <select id="unig" name="group" class="form-select">
          <option value="0">Tất cả lĩnh vực</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?=(int)$g['id']?>" <?=$selectedGroup===(int)$g['id']?'selected':''?>><?=e($g['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="md:col-span-1">
        <button type="submit" class="btn btn-primary w-full">Tìm</button>
      </div>
    </form>

    <?php if ($q !== '' || $province !== '' || $selectedGroup > 0): ?>
      <div class="filter-chips-bar mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center gap-2">
        <span class="text-xs text-slate-500 font-semibold">Đang lọc:</span>
        <?php if ($q !== ''): ?>
          <a href="<?=url('index.php?page=university-explorer'.($province?'&province='.urlencode($province):'').($selectedGroup?'&group='.$selectedGroup:''))?>" class="filter-chip">
            Từ khóa: "<?=e($q)?>" <span class="chip-remove">✕</span>
          </a>
        <?php endif; ?>
        <?php if ($province !== ''): ?>
          <a href="<?=url('index.php?page=university-explorer'.($q?'&q='.urlencode($q):'').($selectedGroup?'&group='.$selectedGroup:''))?>" class="filter-chip">
            Tỉnh thành: <?=e($province)?> <span class="chip-remove">✕</span>
          </a>
        <?php endif; ?>
        <?php if ($selectedGroup > 0): 
          $gName = ''; foreach($groups as $g){ if((int)$g['id']===$selectedGroup){$gName=$g['name'];break;} }
        ?>
          <a href="<?=url('index.php?page=university-explorer'.($q?'&q='.urlencode($q):'').($province?'&province='.urlencode($province):''))?>" class="filter-chip">
            Lĩnh vực: <?=e($gName)?> <span class="chip-remove">✕</span>
          </a>
        <?php endif; ?>
        <a href="<?=url('index.php?page=university-explorer')?>" class="text-xs text-brand-600 hover:underline font-semibold ml-2">
          Xóa tất cả
        </a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Results Count -->
  <div class="flex items-center justify-between mb-4">
    <p class="text-sm font-semibold text-slate-600" role="status">
      Tìm thấy <span class="text-brand-600 font-bold"><?=count($schools)?></span> cơ sở đào tạo phù hợp
    </p>
    <span class="text-xs text-slate-400">Dữ liệu tuyển sinh chính thức theo nguồn công bố</span>
  </div>

  <!-- School Cards Grid -->
  <?php if (!$schools): ?>
    <div class="empty-state">
      <div class="empty-icon">🏛️</div>
      <h3 class="text-lg font-bold text-slate-800 mb-1">Không tìm thấy cơ sở đào tạo phù hợp</h3>
      <p class="text-sm text-slate-500 mb-4">Hãy thử tìm với tên ngắn gọn hơn hoặc chọn tìm trên phạm vi toàn quốc.</p>
      <a class="btn btn-outline" href="<?=url('index.php?page=university-explorer')?>">Xem tất cả trường</a>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <?php foreach ($schools as $s): ?>
        <article class="card school-card-clean">
          <div class="flex items-start justify-between gap-2 mb-2">
            <span class="badge badge-purple"><?=e($s['code'])?></span>
            <span class="text-xs font-semibold text-slate-500 truncate max-w-[140px]"><?=e($s['province'] ?: 'Việt Nam')?></span>
          </div>

          <h3 class="font-bold text-base text-slate-900 mb-2">
            <a href="<?=url('index.php?page=university-explorer&school='.(int)$s['id'])?>" class="hover:text-brand-600 transition-colors">
              <?=e($s['name'])?>
            </a>
          </h3>

          <p class="text-xs text-slate-500 mb-4">
            <span class="font-semibold text-slate-700"><?=(int)$s['program_count']?></span> chương trình đào tạo đã liên kết
          </p>

          <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
            <?php $web = safe_external_url($s['website'] ?? ''); ?>
            <?php if ($web): ?>
              <a href="<?=e($web)?>" target="_blank" rel="noopener noreferrer" class="text-xs text-slate-400 hover:text-slate-600">
                Website ↗
              </a>
            <?php else: ?>
              <span class="text-xs text-slate-400">Hồ sơ trường</span>
            <?php endif; ?>
            <a class="btn btn-outline btn-sm" href="<?=url('index.php?page=university-explorer&school='.(int)$s['id'])?>">
              Xem hồ sơ trường →
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Discovery Helper Box -->
  <div class="mt-10 p-6 bg-slate-50 rounded-2xl border border-slate-200/80 flex flex-col md:flex-row items-center justify-between gap-4">
    <div>
      <h3 class="font-bold text-base text-slate-900">Muốn tìm kiếm theo ngành học trước?</h3>
      <p class="text-sm text-slate-600 mt-1">
        Khám phá hơn 150 ngành đào tạo đại học, cao đẳng kèm số lượng trường đào tạo trong Danh mục ngành học.
      </p>
    </div>
    <a href="<?=url('index.php?page=majors')?>" class="btn btn-primary whitespace-nowrap">
      Mở danh mục ngành học →
    </a>
  </div>
</div>
<?php endif; ?>

<?php 
require __DIR__ . '/footer.php'; 
exit;

