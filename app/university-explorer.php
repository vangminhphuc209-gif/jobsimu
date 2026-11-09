<?php
declare(strict_types=1);

/** University Explorer: publicly accessible verified-first catalog. */
$q=trim(mb_substr((string)($_GET['q']??''),0,100));
$province=trim(mb_substr((string)($_GET['province']??''),0,100));
$selectedGroup=(int)($_GET['group']??0);
$schoolId=max(0,(int)($_GET['school']??0));
$programId=max(0,(int)($_GET['program']??0));

$groups=db()->query('SELECT id,name FROM career_groups ORDER BY sort_order,name')->fetchAll();
$provinces=db()->query("SELECT DISTINCT province FROM institutions WHERE province IS NOT NULL AND province<>'' ORDER BY province")->fetchAll(PDO::FETCH_COLUMN);
$school=null;$program=null;$records=[];$fees=[];$requirements=[];$events=[];$schoolPrograms=[];
if($schoolId){
  $s=db()->prepare('SELECT * FROM institutions WHERE id=?');$s->execute([$schoolId]);$school=$s->fetch();
  if(!$school){http_response_code(404);}
  else {
    $s=db()->prepare('SELECT p.*,m.name AS major_name,m.code AS major_code FROM programs p JOIN majors m ON m.id=p.major_id WHERE p.institution_id=? ORDER BY m.name,p.name');
    $s->execute([$schoolId]);$schoolPrograms=$s->fetchAll();
  }
}
if($programId) {
  $s=db()->prepare('SELECT p.*,i.name AS school_name,i.id AS school_id,i.website,m.name AS major_name,m.code AS major_code FROM programs p JOIN institutions i ON i.id=p.institution_id JOIN majors m ON m.id=p.major_id WHERE p.id=?');
  $s->execute([$programId]);$program=$s->fetch();
  if($program) {
    if(!$schoolId || $schoolId!=(int)$program['school_id']) {
      $schoolId=(int)$program['school_id'];
      $s=db()->prepare('SELECT * FROM institutions WHERE id=?');$s->execute([$schoolId]);$school=$s->fetch();
      $s=db()->prepare('SELECT p.*,m.name AS major_name,m.code AS major_code FROM programs p JOIN majors m ON m.id=p.major_id WHERE p.institution_id=? ORDER BY m.name,p.name');
      $s->execute([$schoolId]);$schoolPrograms=$s->fetchAll();
    }
    foreach([
      'records'=>'SELECT * FROM admission_records WHERE program_id=? ORDER BY admission_year DESC,method,combination',
      'fees'=>'SELECT * FROM tuition_records WHERE program_id=? ORDER BY academic_year DESC',
      'requirements'=>'SELECT * FROM admission_requirements WHERE program_id=? ORDER BY admission_year DESC',
      'events'=>'SELECT * FROM admission_events WHERE program_id=? ORDER BY admission_year DESC,starts_at'
    ] as $key=>$sql){$s=db()->prepare($sql);$s->execute([$programId]);$$key=$s->fetchAll();}
  } else http_response_code(404);
}
$sql='SELECT i.*,COUNT(DISTINCT p.id) AS program_count FROM institutions i LEFT JOIN programs p ON p.institution_id=i.id';
$where=[];$args=[];
if($q!==''){$where[]='(i.name LIKE ? OR i.code LIKE ? OR EXISTS(SELECT 1 FROM programs p2 JOIN majors m2 ON m2.id=p2.major_id WHERE p2.institution_id=i.id AND m2.name LIKE ?))';$term='%'.$q.'%';array_push($args,$term,$term,$term);}
if($province!==''){$where[]='i.province=?';$args[]=$province;}
if($selectedGroup>0){
  $where[]='EXISTS(SELECT 1 FROM programs p3 JOIN career_major_paths cmp ON cmp.major_id=p3.major_id JOIN careers c ON c.id=cmp.career_id WHERE p3.institution_id=i.id AND c.career_group_id=?)';
  $args[]=$selectedGroup;
}
if($where)$sql.=' WHERE '.implode(' AND ',$where);
$sql.=' GROUP BY i.id ORDER BY i.name LIMIT 100';
$s=db()->prepare($sql);$s->execute($args);$schools=$s->fetchAll();
$majors=db()->query('SELECT m.id,m.code,m.name,COUNT(DISTINCT p.id) AS program_count FROM majors m LEFT JOIN programs p ON p.major_id=m.id GROUP BY m.id ORDER BY m.name LIMIT 150')->fetchAll();
$pageTitle='University Explorer · Tra cứu trường và ngành';
require __DIR__.'/header.php';
?>
<section class="page-head"><div class="container"><span class="eyebrow">JOBSIMU · University Explorer</span><h1>Tra cứu trường đại học & ngành đào tạo</h1><p>Khám phá nghề, ngành học và trường đào tạo. Dữ liệu tuyển sinh chỉ có giá trị tham khảo theo đúng năm và nguồn công bố.</p><a class="btn btn-outline" href="<?=url('index.php?page=university-compare')?>">So sánh trường & chương trình →</a></div></section>
<div class="container">
  <form method="get" class="card toolbar" style="margin-top:24px">
    <input type="hidden" name="page" value="university-explorer">
    <div class="form-group"><label for="uniq">Tên trường, mã trường hoặc ngành</label><input id="uniq" class="input" type="search" name="q" value="<?=e($q)?>" placeholder="Ví dụ: Bách khoa, Y khoa"></div>
    <div class="form-group"><label for="unip">Tỉnh/thành phố</label><select id="unip" name="province"><option value="">Toàn quốc</option><?php foreach($provinces as $p):?><option value="<?=e($p)?>" <?=$province===$p?'selected':''?>><?=e($p)?></option><?php endforeach;?></select></div>
    <div class="form-group"><label for="unig">Nhóm lĩnh vực nghề</label><select id="unig" name="group"><option value="0">Tất cả lĩnh vực</option><?php foreach($groups as $g):?><option value="<?=(int)$g['id']?>" <?=$selectedGroup===(int)$g['id']?'selected':''?>><?=e($g['name'])?></option><?php endforeach;?></select></div>
    <button class="btn btn-primary">Tìm kiếm</button>
    <a href="<?=url('index.php?page=university-explorer')?>" class="btn btn-outline">Xóa lọc</a>
  </form>
  <?php if($school):?>
  <section class="card" style="margin-top:24px"><span class="eyebrow">Hồ sơ cơ sở đào tạo</span><h2><?=e($school['name'])?></h2>
    <p>Mã trường: <strong><?=e($school['code'])?></strong> · <?=e($school['province']?:'Chưa rõ địa điểm')?></p>
    <?php $home=safe_external_url($school['website']);if($home):?><p><a href="<?=e($home)?>" rel="noopener noreferrer" target="_blank">Website chính thức của trường ↗</a></p><?php endif;?>
    <h3>Chương trình đào tạo đã được liên kết</h3>
    <?php if(!$schoolPrograms):?><p class="muted">Chưa có chương trình được xác minh cho trường này. Đây là hồ sơ trường, không có nghĩa trường không đào tạo ngành nào.</p><?php endif;?>
    <div class="grid-3"><?php foreach($schoolPrograms as $sp):?><a class="card" href="<?=url('index.php?page=university-explorer&school='.(int)$schoolId.'&program='.(int)$sp['id'])?>"><b><?=e($sp['name'])?></b><p><?=e($sp['major_name'])?> (<?=e($sp['major_code'])?>)</p><span>Chi tiết tuyển sinh →</span></a><?php endforeach;?></div>
  </section>
  <?php endif;?>
  <?php if($program):?>
  <section class="card" style="margin-top:24px"><span class="eyebrow">Chương trình đào tạo</span><h2><?=e($program['name'])?></h2>
    <p><?=e($program['major_name'])?> (<?=e($program['major_code'])?>) · <?=e($program['school_name'])?></p>
    <p>Tổng tín chỉ: <b><?=isset($program['credits_required']) && $program['credits_required']!==null ? (int)$program['credits_required']:'Chưa có dữ liệu xác thực'?></b></p>
    <?php $official=safe_external_url($program['official_url']);if($official):?><p><a href="<?=e($official)?>" target="_blank" rel="noopener noreferrer">Đề án/chương trình chính thức ↗</a></p><?php endif;?>
    <h3>Điểm chuẩn lịch sử</h3>
    <?php if(!$records):?><p class="muted">Chưa có dữ liệu điểm chuẩn xác thực cho chương trình này.</p><?php else:?><div style="overflow-x:auto"><table style="width:100%"><thead><tr><th>Năm</th><th>Phương thức</th><th>Tổ hợp</th><th>Nhóm thí sinh</th><th>Điểm</th><th>Nguồn</th></tr></thead><tbody>
    <?php foreach($records as $r):?><tr><td><?=(int)$r['admission_year']?></td><td><?=e($r['method'])?></td><td><?=e($r['combination']?:'Theo đề án')?></td><td><?=e($r['candidate_group']?:'Chung')?></td><td><?=($r['cutoff_score']!==null)?e($r['cutoff_score'].' / '.($r['score_scale']?:'?')):'Chưa công bố'?></td><td><a href="<?=e(safe_external_url($r['source_url']))?>" target="_blank" rel="noopener noreferrer">Nguồn ↗</a></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
    <h3>Học phí theo năm học</h3>
    <?php if(!$fees):?><p class="muted">Chưa có dữ liệu học phí xác thực cho chương trình này.</p><?php else:?><div style="overflow-x:auto"><table style="width:100%"><thead><tr><th>Năm học</th><th>Cơ sở tính</th><th>Số tiền</th><th>Nguồn</th></tr></thead><tbody>
    <?php foreach($fees as $f):?><tr><td><?=e($f['academic_year'])?></td><td><?=e($f['charge_basis'])?></td><td><?=($f['amount_vnd']!==null)?number_format((float)$f['amount_vnd'],0,',','.').' ₫':'Chưa công bố'?></td><td><a href="<?=e(safe_external_url($f['source_url']))?>" target="_blank" rel="noopener noreferrer">Nguồn ↗</a></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
    <h3>Điều kiện tuyển sinh đặc thù</h3>
    <?php if(!$requirements):?><p class="muted">Chưa có dữ liệu yêu cầu đặc thù xác thực.</p><?php endif;?>
    <?php foreach($requirements as $r):?><p><b><?=(int)$r['admission_year']?> · <?=e($r['requirement_type'])?></b> — <?=e($r['details'])?> <a href="<?=e(safe_external_url($r['source_url']))?>" target="_blank" rel="noopener noreferrer">Nguồn ↗</a></p><?php endforeach;?>
    <h3>Mốc thời gian tuyển sinh</h3>
    <?php if(!$events):?><p class="muted">Chưa có lịch tuyển sinh được xác thực.</p><?php endif;?>
    <?php foreach($events as $event):?><p><b><?=(int)$event['admission_year']?> · <?=e($event['event_name'])?></b>: <?=e($event['starts_at']?:'Chưa công bố')?> – <?=e($event['ends_at']?:'Chưa công bố')?> <a href="<?=e(safe_external_url($event['source_url']))?>" target="_blank" rel="noopener noreferrer">Nguồn ↗</a></p><?php endforeach;?>
    <p class="muted">Lưu ý: điểm theo từng phương thức và thang điểm không thể so sánh trực tiếp. Hãy đọc đề án tuyển sinh của đúng năm dự tuyển.</p>
  </section><?php endif;?>
  <section style="margin-top:28px"><h2>Danh mục trường đang có trên JOBSIMU</h2><p class="muted"><?=count($schools)?> trường phù hợp bộ lọc. Hồ sơ khởi tạo chưa đồng nghĩa đã có dữ liệu tuyển sinh của tất cả ngành.</p>
  <div class="grid-3"><?php foreach($schools as $s):?><article class="card"><span class="tag"><?=e($s['code'])?></span><h3><?=e($s['name'])?></h3><p><?=e($s['province']?:'Việt Nam')?></p><small class="muted"><?=(int)$s['program_count']?> chương trình đã liên kết</small><p><a class="btn btn-outline" href="<?=url('index.php?page=university-explorer&school='.(int)$s['id'])?>">Xem hồ sơ trường →</a></p></article><?php endforeach;?></div>
  <?php if(!$schools):?><div class="card empty">Chưa có trường phù hợp bộ lọc. Hãy thử tìm rộng hơn.</div><?php endif;?></section>
  <section style="margin:28px 0"><h2>Ngành đào tạo tham khảo</h2><div class="grid-3"><?php foreach($majors as $major):?><article class="card"><span class="tag"><?=e($major['code'])?></span><h3><?=e($major['name'])?></h3><small class="muted"><?=(int)$major['program_count']?> chương trình liên kết</small></article><?php endforeach;?></div></section>
  <p class="muted">Tham khảo thêm: <a href="https://hocvienhuongnghiep.com/thong-tin-tra-cuu-cua-cac-truong-dh-cd/" target="_blank" rel="noopener noreferrer">Danh mục tra cứu trường Học viện Hướng nghiệp ↗</a>. Dữ liệu công bố trong JOBSIMU chỉ nên nhập từ nguồn chính thức.</p>
</div>
<?php require __DIR__.'/footer.php';exit;
