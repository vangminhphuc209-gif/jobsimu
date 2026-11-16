<?php
declare(strict_types=1);

/** Side-by-side comparison. Never imply different scales are comparable. */
$raw = $_GET['programs'] ?? [];
if (!is_array($raw)) $raw = [];
$ids = array_values(array_unique(array_filter(array_map('intval', array_slice($raw, 0, 3)), static function($n) { return $n > 0; })));

$all = db()->query('SELECT p.id,p.name AS program_name,i.name AS institution_name,i.province,m.name AS major_name FROM programs p JOIN institutions i ON i.id=p.institution_id JOIN majors m ON m.id=p.major_id ORDER BY i.name,p.name LIMIT 500')->fetchAll();

$selected = [];
$scores = [];
$fees = [];

if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $s = db()->prepare("SELECT p.id,p.name AS program_name,i.name AS institution_name,i.code AS institution_code,i.province,m.name AS major_name,m.code AS major_code,p.credits_required FROM programs p JOIN institutions i ON i.id=p.institution_id JOIN majors m ON m.id=p.major_id WHERE p.id IN ($ph)");
    $s->execute($ids);
    foreach ($s->fetchAll() as $item) {
        $selected[(int)$item['id']] = $item;
    }
    
    $s = db()->prepare("SELECT * FROM admission_records WHERE program_id IN ($ph) ORDER BY admission_year DESC");
    $s->execute($ids);
    $scores = $s->fetchAll();
    
    $s = db()->prepare("SELECT * FROM tuition_records WHERE program_id IN ($ph) ORDER BY academic_year DESC");
    $s->execute($ids);
    $fees = $s->fetchAll();
}

$scoreMatrix = [];
$feeMatrix = [];

foreach ($scores as $r) {
    $key = implode(' • ', [
        (int)$r['admission_year'],
        $r['method'] ?: 'Theo đề án',
        $r['score_scale'] ?: 'Thang chưa rõ',
        $r['combination'] ?: 'Điểm chung',
        $r['candidate_group'] ?: 'Đối tượng chung'
    ]);
    $scoreMatrix[$key][(int)$r['program_id']] = $r;
}

foreach ($fees as $r) {
    $key = $r['academic_year'] . ' • ' . ($r['charge_basis'] ?: 'Theo kỳ');
    $feeMatrix[$key][(int)$r['program_id']] = $r;
}

krsort($scoreMatrix);
krsort($feeMatrix);

$pageTitle = 'So sánh trường và chương trình đào tạo';
require __DIR__ . '/header.php';
?>

<section class="page-head">
  <div class="container">
    <nav class="breadcrumb mb-3" aria-label="Đường dẫn trang">
      <a href="<?=url('index.php?page=home')?>">Trang chủ</a>
      <span class="breadcrumb-sep">/</span>
      <a href="<?=url('index.php?page=university-explorer')?>">Tra cứu trường</a>
      <span class="breadcrumb-sep">/</span>
      <span aria-current="page">So sánh chương trình</span>
    </nav>
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="page-title">So sánh chương trình đào tạo</h1>
        <p class="page-subtitle">
          Đối chiếu chương trình, điểm chuẩn và học phí. Chỉ so sánh điểm số khi cùng năm, phương thức xét tuyển và thang điểm.
        </p>
      </div>
      <a class="btn btn-outline btn-sm" href="<?=url('index.php?page=university-explorer')?>">
        ← Quay lại tra cứu trường
      </a>
    </div>
  </div>
</section>

<div class="container py-8 space-y-6">

  <!-- Selection Bar -->
  <div class="card p-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <div>
        <h2 class="text-base font-bold text-slate-900">
          Danh sách so sánh (<?=count($selected)?>/3 chương trình)
        </h2>
        <p class="text-xs text-slate-500">Bạn có thể chọn tối đa 3 chương trình đào tạo từ cơ sở dữ liệu thật.</p>
      </div>
      <?php if (!empty($selected)): ?>
        <a href="<?=url('index.php?page=university-compare')?>" class="text-xs text-rose-600 hover:underline font-semibold">
          Xóa toàn bộ so sánh
        </a>
      <?php endif; ?>
    </div>

    <!-- Selected Program Chips -->
    <?php if (!empty($selected)): ?>
      <div class="flex flex-wrap items-center gap-3 mb-4 pb-4 border-b border-slate-100">
        <?php foreach ($selected as $progId => $p): 
          // Build URL to remove this program
          $remainingIds = array_diff($ids, [$progId]);
          $removeUrl = url('index.php?page=university-compare' . ($remainingIds ? '&' . http_build_query(['programs' => array_values($remainingIds)]) : ''));
        ?>
          <div class="flex items-center gap-2 p-2 px-3 rounded-xl bg-purple-50 border border-purple-200 text-sm">
            <span class="font-bold text-slate-900"><?=e($p['program_name'])?></span>
            <span class="text-xs text-slate-600">(<?=e($p['institution_name'])?>)</span>
            <a href="<?=e($removeUrl)?>" class="text-slate-400 hover:text-rose-600 font-bold ml-1" title="Bỏ chương trình này">
              ✕
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Add Program Form (if < 3) -->
    <?php if (count($selected) < 3): ?>
      <form method="get" action="<?=url('index.php')?>" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="page" value="university-compare">
        <?php foreach ($ids as $existingId): ?>
          <input type="hidden" name="programs[]" value="<?=(int)$existingId?>">
        <?php endforeach; ?>

        <div class="flex-1 min-w-[280px]">
          <label for="newProgram" class="form-label">Chọn thêm chương trình vào bảng so sánh:</label>
          <select id="newProgram" name="programs[]" class="form-select">
            <option value="">— Chọn một chương trình đào tạo —</option>
            <?php foreach ($all as $opt): 
              if (in_array((int)$opt['id'], $ids, true)) continue;
            ?>
              <option value="<?=(int)$opt['id']?>">
                <?=e($opt['institution_name'].' — '.$opt['program_name'].' ('.$opt['major_name'].')')?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="btn btn-primary">
          + Thêm vào so sánh
        </button>
      </form>
    <?php else: ?>
      <div class="p-3 bg-slate-50 rounded-xl text-xs text-slate-500">
        Đã chọn tối đa 3 chương trình. Hãy bấm dấu ✕ ở trên nếu muốn thay bằng chương trình khác.
      </div>
    <?php endif; ?>
  </div>

  <?php if (empty($selected)): ?>
    <!-- Empty State -->
    <div class="empty-state">
      <div class="empty-icon">⚖️</div>
      <h3 class="text-lg font-bold text-slate-800 mb-1">Chưa có chương trình nào được chọn</h3>
      <p class="text-sm text-slate-500 mb-4 max-w-md">
        Hãy chọn từ danh sách phía trên hoặc vào trang Tra cứu trường để bấm nút "So sánh" trên từng chương trình đào tạo.
      </p>
      <a class="btn btn-primary" href="<?=url('index.php?page=university-explorer')?>">
        Tra cứu trường & chương trình →
      </a>
    </div>

  <?php else: ?>
    <!-- Mobile scroll hint -->
    <div class="table-scroll-hint md:hidden p-2.5 bg-indigo-50 border border-indigo-100 rounded-xl text-xs text-indigo-900 flex items-center gap-2">
      <span>💡</span>
      <span>Vuốt ngang bảng để xem đầy đủ các chương trình đang so sánh.</span>
    </div>

    <!-- Table 1: General Info -->
    <div class="card p-6">
      <h2 class="text-base font-bold text-slate-900 mb-3">1. Thông tin chung chương trình</h2>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th style="min-width:180px">Tiêu chí</th>
              <?php foreach ($selected as $p): ?>
                <th style="min-width:240px">
                  <div class="font-bold text-slate-900 text-sm"><?=e($p['program_name'])?></div>
                  <div class="text-xs font-normal text-slate-500"><?=e($p['institution_name'])?></div>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="font-semibold text-slate-700">Cơ sở đào tạo</td>
              <?php foreach ($selected as $p): ?>
                <td>
                  <a href="<?=url('index.php?page=university-explorer&school='.(int)$p['id'])?>" class="font-bold text-brand-600 hover:underline">
                    <?=e($p['institution_name'])?>
                  </a>
                  <span class="text-xs text-slate-400 block"><?=e($p['institution_code'])?></span>
                </td>
              <?php endforeach; ?>
            </tr>
            <tr>
              <td class="font-semibold text-slate-700">Ngành đào tạo</td>
              <?php foreach ($selected as $p): ?>
                <td>
                  <?=e($p['major_name'])?>
                  <?php if (!empty($p['major_code'])): ?>
                    <span class="badge badge-subtle text-[11px] ml-1"><?=e($p['major_code'])?></span>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
            <tr>
              <td class="font-semibold text-slate-700">Địa điểm</td>
              <?php foreach ($selected as $p): ?>
                <td><?=e($p['province'] ?: 'Chưa có dữ liệu')?></td>
              <?php endforeach; ?>
            </tr>
            <tr>
              <td class="font-semibold text-slate-700">Khối lượng học tập</td>
              <?php foreach ($selected as $p): ?>
                <td>
                  <?=($p['credits_required'] !== null) ? (int)$p['credits_required'] . ' tín chỉ' : 'Chưa có dữ liệu'?>
                </td>
              <?php endforeach; ?>
            </tr>
            <tr>
              <td class="font-semibold text-slate-700">Hồ sơ chi tiết</td>
              <?php foreach ($selected as $p): ?>
                <td>
                  <a href="<?=url('index.php?page=university-explorer&program='.(int)$p['id'])?>" class="text-xs font-bold text-brand-600 hover:underline">
                    Xem toàn bộ đề án →
                  </a>
                </td>
              <?php endforeach; ?>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Table 2: Admission Scores -->
    <div class="card p-6">
      <div class="mb-3">
        <h2 class="text-base font-bold text-slate-900">2. Điểm chuẩn theo tiêu chí tương đương</h2>
        <p class="text-xs text-slate-500">
          Chỉ đặt cạnh nhau khi cùng phương thức, năm, thang điểm, tổ hợp và đối tượng xét tuyển.
        </p>
      </div>

      <?php if (!$scoreMatrix): ?>
        <div class="p-4 bg-slate-50 rounded-xl text-center text-sm text-slate-500">
          Chưa có dữ liệu điểm chuẩn xác thực của các chương trình đã chọn.
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th style="min-width:220px">Năm · Phương thức · Thang · Tổ hợp</th>
                <?php foreach ($selected as $p): ?>
                  <th style="min-width:240px">
                    <div class="font-bold text-slate-900 text-sm"><?=e($p['program_name'])?></div>
                    <div class="text-xs font-normal text-slate-500"><?=e($p['institution_name'])?></div>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($scoreMatrix as $key => $items): ?>
                <tr>
                  <td class="font-semibold text-xs text-slate-700"><?=e($key)?></td>
                  <?php foreach ($selected as $p): $r = $items[(int)$p['id']] ?? null; ?>
                    <td>
                      <?php if (!$r || $r['cutoff_score'] === null): ?>
                        <span class="text-xs text-slate-400">Chưa có dữ liệu</span>
                      <?php else: ?>
                        <strong class="text-base text-brand-600"><?=e((string)$r['cutoff_score'])?></strong>
                        <?php $src = safe_external_url($r['source_url'] ?? ''); ?>
                        <?php if ($src): ?>
                          <a href="<?=e($src)?>" target="_blank" rel="noopener noreferrer" class="text-xs text-brand-600 hover:underline ml-1" title="Xem văn bản nguồn">
                            ↗
                          </a>
                        <?php endif; ?>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Table 3: Tuition Fees -->
    <div class="card p-6">
      <div class="mb-3">
        <h2 class="text-base font-bold text-slate-900">3. Học phí theo năm và đơn vị tính</h2>
        <p class="text-xs text-slate-500">
          Học phí tín chỉ, học kỳ và cả năm không thể so sánh trực tiếp nếu không cùng đơn vị tính.
        </p>
      </div>

      <?php if (!$feeMatrix): ?>
        <div class="p-4 bg-slate-50 rounded-xl text-center text-sm text-slate-500">
          Chưa có dữ liệu học phí xác thực để đối chiếu.
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th style="min-width:180px">Năm học · Đơn vị tính</th>
                <?php foreach ($selected as $p): ?>
                  <th style="min-width:240px">
                    <div class="font-bold text-slate-900 text-sm"><?=e($p['program_name'])?></div>
                    <div class="text-xs font-normal text-slate-500"><?=e($p['institution_name'])?></div>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($feeMatrix as $key => $items): ?>
                <tr>
                  <td class="font-semibold text-xs text-slate-700"><?=e($key)?></td>
                  <?php foreach ($selected as $p): $r = $items[(int)$p['id']] ?? null; ?>
                    <td>
                      <?php if (!$r || $r['amount_vnd'] === null): ?>
                        <span class="text-xs text-slate-400">Chưa có dữ liệu</span>
                      <?php else: ?>
                        <strong class="text-emerald-700 font-bold"><?=number_format((float)$r['amount_vnd'], 0, ',', '.')?> ₫</strong>
                        <?php $src = safe_external_url($r['source_url'] ?? ''); ?>
                        <?php if ($src): ?>
                          <a href="<?=e($src)?>" target="_blank" rel="noopener noreferrer" class="text-xs text-brand-600 hover:underline ml-1">
                            ↗
                          </a>
                        <?php endif; ?>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="notice-box">
      <div class="flex items-start gap-3">
        <span class="text-xl">⚠️</span>
        <div class="text-xs text-slate-600 leading-relaxed">
          <strong>Lưu ý chung:</strong> Điểm chuẩn lịch sử chỉ phản ánh kết quả trúng tuyển của những năm trước và không bảo đảm kết quả xét tuyển của năm tiếp theo. Mọi thông tin cần được đối chiếu lại tại đề án tuyển sinh chính thức do trường công bố.
        </div>
      </div>
    </div>

  <?php endif; ?>

</div>

<?php 
require __DIR__ . '/footer.php'; 
exit;

