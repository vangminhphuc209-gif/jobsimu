<?php
declare(strict_types=1);
$jobs = db()->query("SELECT * FROM careers WHERE status='published' ORDER BY featured DESC,title")->fetchAll();
$playable = []; foreach($jobs as $job) { $game=simulation_for_career($job); if($game) $playable[]=['career'=>$job,'game'=>$game]; }
$attempts=[]; $activity=[];
if(user()) {
    $s=db()->prepare('SELECT career_id,MAX(score) best_score,COUNT(*) attempt_count FROM simulation_attempts WHERE user_id=? GROUP BY career_id');
    $s->execute([user()['id']]); foreach($s->fetchAll() as $x) $attempts[$x['career_id']]=$x;
    $activity=four_hub_activity((int)user()['id']);
}
$pageTitle='Phòng trải nghiệm nghề'; require ROOT_PATH.'/app/header.php';
?>
<section class="page-head"><div class="container"><span class="eyebrow">TRUNG TÂM 03 · TRẢI NGHIỆM & PHẢN TƯ</span><h1>Thử một nghề. Hiểu thêm về mình.</h1><p>Quan sát, ra quyết định và nhìn lại cảm xúc sau mỗi tình huống.</p></div></section>
<div class="container hub-section"><section class="hub-spotlight"><div><span class="hub-kicker">KHÔNG GIAN 3D · NGHỀ GIÁO VIÊN</span><h2>Người thầy sau<br>tiếng trống trường</h2><p>Bước vào trường học, chuẩn bị bài giảng và xử lý những tình huống trong một tiết dạy.</p><a class="btn btn-primary" href="<?=url('index.php?page=simulation-3d&career=giao-vien')?>"><?=is_file(ROOT_PATH.'/game/dist/index.html')?'Vào trường học 3D →':'Xem không gian 3D →'?></a><p class="hub-small">Bản chơi thử · Bàn phím & chuột · Lưu trên trình duyệt, chưa đồng bộ tài khoản</p></div><div class="hub-school-mark" aria-hidden="true">3D<small>MỘT TIẾT HỌC<br>NHIỀU CÁCH GIẢI QUYẾT</small></div></section>
<div class="hub-section-heading"><div><span class="eyebrow">TÌNH HUỐNG TƯƠNG TÁC 2D</span><h2><?=count($playable)?> phòng trải nghiệm nghề</h2></div><span class="muted">Khoảng 4–6 phút/lượt</span></div><div class="toolbar"><label for="hub-simulation-search" class="muted">Tìm nghề</label><input id="hub-simulation-search" class="search" data-career-search placeholder="Nhập tên nghề hoặc vai trò..."></div><div class="grid-3">
<?php foreach($playable as $entry):$job=$entry['career'];$game=$entry['game'];$done=$attempts[$job['id']]??null;?>
<a data-career class="card career-card" href="<?=url('index.php?page=simulation&id='.$job['id'])?>"><div class="hub-card-top"><div class="career-icon"><?=e($job['icon'])?></div><?php if($done):?><span class="sim-done">Phản xạ cao nhất <?=(int)$done['best_score']?>%</span><?php endif;?></div><h3><?=e($game['title'])?></h3><p><?=e($game['brief'])?></p><div class="meta"><span><?=e($game['role'])?></span><b><?=$done?'Trải nghiệm lại':'Bắt đầu'?> →</b></div></a>
<?php endforeach;?></div>
<section class="hub-section" id="activity"><span class="eyebrow">NHẬT KÝ TRẢI NGHIỆM</span><h2>Điều bạn đã thử, điều bạn cảm nhận</h2><p class="muted">Lịch sử đã lưu của tài khoản. Báo cáo 3D local được xem trong trò chơi và chưa xuất hiện ở đây.</p>
<?php if(!user()):?><div class="card"><a href="<?=url('index.php?page=login')?>">Đăng nhập để xem nhật ký của bạn →</a></div>
<?php elseif(!$activity):?><div class="card">Chưa có trải nghiệm được lưu. Hãy chọn một nghề để bắt đầu.</div>
<?php else: foreach($activity as $item):?><article class="card hub-activity"><div class="hub-card-top"><h3><?=e($item['title'])?></h3><span class="tag"><?=e($item['mode'])?></span></div><time><?=e($item['completed_at'])?></time>
<?php $feelings=['excited'=>'🤩 Hào hứng','challenged'=>'🤔 Thử thách','unsuited'=>'😐 Chưa hợp']; if(isset($feelings[$item['reflection_feeling']??''])):?><p><?=e($feelings[$item['reflection_feeling']])?></p><?php endif;?>
<?php if($item['reflection_note']):?><p><?=nl2br(e($item['reflection_note']))?></p><?php endif;?>
<?php if($item['mode']==='2D'):?><div class="hub-skills"><?php foreach(simulation_skill_labels() as $key=>$label):$value=isset($item['skills'][$key])?max(0,min(100,(int)$item['skills'][$key])):null;?><div><span><?=e($label)?></span><?php if($value!==null):?><progress max="100" value="<?=$value?>"><?=$value?>%</progress><small><?=$value?>%</small><?php else:?><small>Chưa có dữ liệu</small><?php endif;?></div><?php endforeach;?></div><a href="<?=url('index.php?page=simulation-result&id='.(int)$item['id'])?>">Xem báo cáo →</a>
<?php else:?><p>Báo cáo 3D dựa trên bằng chứng hành động, không quy đổi thành điểm tổng.</p><?php $dimensions=['analytical'=>'Phân tích','creative'=>'Sáng tạo','empathetic'=>'Thấu cảm','decisive'=>'Ra quyết định','accurate'=>'Chính xác']; foreach($dimensions as $key=>$label):$evidence=$item['competencies'][$key]??null;?><div><b><?=e($label)?>:</b> <?=e(is_string($evidence)?$evidence:(is_array($evidence)?($evidence['level']??$evidence['scoreLabel']??'Bằng chứng đã lưu'):'Chưa có bằng chứng trong báo cáo'))?><?php if(is_array($evidence) && is_array($evidence['evidence']??null)):?><ul><?php foreach(array_slice($evidence['evidence'],0,10) as $note):if(!is_string($note))continue;?><li><?=e($note)?></li><?php endforeach;?></ul><?php endif;?></div><?php endforeach; endif;?></article><?php endforeach; endif;?></section></div>
<?php require ROOT_PATH.'/app/footer.php'; exit;
