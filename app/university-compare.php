<?php
declare(strict_types=1);
/** Side-by-side comparison. Never imply different scales are comparable. */
$raw=$_GET['programs']??[];
if(!is_array($raw))$raw=[];
$ids=array_values(array_unique(array_filter(array_map('intval',array_slice($raw,0,3)),static function($n){return $n>0;})));
$all=db()->query('SELECT p.id,p.name AS program_name,i.name AS institution_name,i.province,m.name AS major_name FROM programs p JOIN institutions i ON i.id=p.institution_id JOIN majors m ON m.id=p.major_id ORDER BY i.name,p.name LIMIT 500')->fetchAll();
$selected=[];$scores=[];$fees=[];
if($ids){
  $ph=implode(',',array_fill(0,count($ids),'?'));
  $s=db()->prepare("SELECT p.id,p.name AS program_name,i.name AS institution_name,i.province,m.name AS major_name,p.credits_required FROM programs p JOIN institutions i ON i.id=p.institution_id JOIN majors m ON m.id=p.major_id WHERE p.id IN ($ph)");
  $s->execute($ids);foreach($s->fetchAll() as $item)$selected[(int)$item['id']]=$item;
  $s=db()->prepare("SELECT * FROM admission_records WHERE program_id IN ($ph) ORDER BY admission_year DESC");
  $s->execute($ids);$scores=$s->fetchAll();
  $s=db()->prepare("SELECT * FROM tuition_records WHERE program_id IN ($ph) ORDER BY academic_year DESC");
  $s->execute($ids);$fees=$s->fetchAll();
}
$scoreMatrix=[];$feeMatrix=[];
foreach($scores as $r){
  $key=implode(' • ',[(int)$r['admission_year'],$r['method'],$r['score_scale']?:'Thang chưa rõ',$r['combination']?:'Điểm chung',$r['candidate_group']?:'Đối tượng chung']);
  $scoreMatrix[$key][(int)$r['program_id']]=$r;
}
foreach($fees as $r){
  $key=$r['academic_year'].' • '.$r['charge_basis'];
  $feeMatrix[$key][(int)$r['program_id']]=$r;
}
krsort($scoreMatrix);krsort($feeMatrix);
$pageTitle='So sánh trường và chương trình';
require __DIR__.'/header.php';
?>
<section class="page-head"><div class="container"><span class="eyebrow">JOBSIMU · Admissions Intelligence P2</span><h1>So sánh trường và chương trình</h1><p>Chọn tối đa ba chương trình. Chỉ so sánh điểm cùng năm, phương thức, tổ hợp, đối tượng và thang điểm.</p></div></section>
<div class="container">
  <form method="get" class="card" style="margin-top:24px">
    <input type="hidden" name="page" value="university-compare">
    <div class="grid-3">
    <?php for($i=0;$i<3;$i++):?><div class="form-group"><label for="pick<?=$i?>">Chương trình <?=$i+1?></label>
    <select name="programs[]" id="pick<?=$i?>" class="input"><option value="">— Không chọn —</option>
    <?php foreach($all as $opt):?><option value="<?=(int)$opt['id']?>" <?=(isset($ids[$i])&&$ids[$i]===(int)$opt['id'])?'selected':''?>><?=e($opt['institution_name'].' — '.$opt['program_name'])?></option><?php endforeach;?>
    </select></div><?php endfor;?>
    </div><button class="btn btn-primary">So sánh</button> <a class="btn btn-outline" href="<?=url('index.php?page=university-explorer')?>">Tra cứu trường</a>
  </form>
  <?php if(!$selected):?>
    <div class="card" style="margin-top:24px"><p>Chọn từ hai đến ba chương trình để bắt đầu. Một số trường hiện mới có hồ sơ danh mục và chưa được bổ sung chương trình có nguồn xác thực.</p></div>
  <?php else:?>
    <div class="card" style="margin-top:24px;overflow-x:auto">
      <h2>Thông tin chương trình</h2>
      <table style="min-width:700px;width:100%"><thead><tr><th>Tiêu chí</th><?php foreach($selected as $p):?><th><?=e($p['institution_name'])?></th><?php endforeach;?></tr></thead><tbody>
        <tr><th>Chương trình</th><?php foreach($selected as $p):?><td><?=e($p['program_name'])?></td><?php endforeach;?></tr>
        <tr><th>Ngành</th><?php foreach($selected as $p):?><td><?=e($p['major_name'])?></td><?php endforeach;?></tr>
        <tr><th>Địa điểm</th><?php foreach($selected as $p):?><td><?=e($p['province']?:'Chưa xác thực')?></td><?php endforeach;?></tr>
        <tr><th>Tín chỉ</th><?php foreach($selected as $p):?><td><?=($p['credits_required']!==null)?(int)$p['credits_required']:'Chưa có dữ liệu'?></td><?php endforeach;?></tr>
      </tbody></table>
    </div>
    <div class="card" style="margin-top:24px;overflow-x:auto">
      <h2>Điểm chuẩn theo tiêu chí tương đương</h2>
      <p class="muted">Các cột chỉ đối chiếu được khi cùng phương thức, năm, thang điểm, tổ hợp và nhóm đối tượng. Ô trống không có nghĩa điểm bằng 0.</p>
      <?php if(!$scoreMatrix):?><p>Chưa có dữ liệu điểm chuẩn xác thực.</p><?php else:?>
      <table style="min-width:800px;width:100%"><thead><tr><th>Năm · Phương thức · Thang · Tổ hợp</th><?php foreach($selected as $p):?><th><?=e($p['institution_name'])?></th><?php endforeach;?></tr></thead><tbody>
      <?php foreach($scoreMatrix as $key=>$items):?><tr><td><?=e($key)?></td><?php foreach($selected as $p):$r=$items[(int)$p['id']]??null;?><td><?php if(!$r):?>Chưa có dữ liệu<?php else:?>
      <strong><?=e((string)$r['cutoff_score'])?></strong> <a href="<?=e(safe_external_url($r['source_url']))?>" target="_blank" rel="noopener noreferrer" title="Xem nguồn">↗</a>
      <?php endif;?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table><?php endif;?>
    </div>
    <div class="card" style="margin-top:24px;overflow-x:auto">
      <h2>Học phí theo năm và đơn vị tính</h2>
      <p class="muted">Học phí tín chỉ, học kỳ và cả năm không thể so sánh trực tiếp. Số tiền không bao gồm chi phí sinh hoạt nếu trường không công bố.</p>
      <?php if(!$feeMatrix):?><p>Chưa có dữ liệu học phí xác thực để đối chiếu.</p><?php else:?>
      <table style="min-width:800px;width:100%"><thead><tr><th>Năm học · Đơn vị</th><?php foreach($selected as $p):?><th><?=e($p['institution_name'])?></th><?php endforeach;?></tr></thead><tbody>
      <?php foreach($feeMatrix as $key=>$items):?><tr><td><?=e($key)?></td><?php foreach($selected as $p):$r=$items[(int)$p['id']]??null;?><td><?php if(!$r):?>Chưa có dữ liệu<?php else:?>
      <?=($r['amount_vnd']===null)?'Chưa công bố':number_format((float)$r['amount_vnd'],0,',','.').' ₫'?>
      <a href="<?=e(safe_external_url($r['source_url']))?>" target="_blank" rel="noopener noreferrer">↗</a><?php endif;?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table><?php endif;?>
    </div>
  <?php endif;?>
  <p class="muted" style="margin:24px 0">Điểm chuẩn lịch sử không bảo đảm trúng tuyển năm tiếp theo. Mọi dữ kiện phải được đối chiếu với đề án tuyển sinh chính thức.</p>
</div>
<?php require __DIR__.'/footer.php';exit;
