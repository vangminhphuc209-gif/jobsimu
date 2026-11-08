<?php
declare(strict_types=1);
require_login();
$career = $_GET['career'] ?? 'giao-vien';
if ($career !== 'giao-vien') { http_response_code(404); exit('Không gian nghề chưa có bản chơi thử.'); }
$pageTitle = 'Một tiết học làm giáo viên';
require ROOT_PATH.'/app/header.php';
?>
<div class="container hub-section"><a href="<?=url('index.php?page=simulations')?>">← Phòng trải nghiệm nghề</a><h1>Người thầy sau tiếng trống trường</h1><p>Bản chơi thử một tiết học. Tiến độ lưu trên trình duyệt này; báo cáo trong trò chơi chưa đồng bộ vào tài khoản.</p>
<?php if(is_file(ROOT_PATH.'/game/dist/index.html')):?>
<p><a class="btn btn-primary" href="<?=url('game/dist/index.html')?>">Mở trò chơi toàn màn hình →</a></p>
<iframe class="hub-game-frame" title="Mô phỏng 3D nghề giáo viên" src="<?=url('game/dist/index.html')?>" allow="fullscreen" allowfullscreen></iframe>
<?php else:?>
<div class="card"><h2>Bản chơi 3D đang được chuẩn bị</h2><p>Bạn có thể trải nghiệm các tình huống nghề nghiệp trong lúc chờ không gian 3D được mở.</p><a class="btn btn-primary" href="<?=url('index.php?page=simulations')?>">Chọn mô phỏng tình huống →</a></div>
<?php endif;?></div>
<?php require ROOT_PATH.'/app/footer.php'; exit;
