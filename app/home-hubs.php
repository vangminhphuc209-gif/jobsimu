<?php
declare(strict_types=1);
$me = user();
$completedTests = 0; $activityCount = 0; $hasRoadmap = false;
if ($me) {
    $latest = four_hub_latest_psychometrics((int)$me['id']);
    $s = db()->prepare('SELECT id FROM assessments WHERE user_id=? AND constraint_answers IS NOT NULL ORDER BY completed_at DESC,id DESC LIMIT 1');
    $s->execute([$me['id']]); $hasHolland = (bool)$s->fetchColumn();
    $completedTests = count($latest) + (int)$hasHolland;
    $s = db()->prepare('SELECT COUNT(*) FROM simulation_attempts WHERE user_id=?'); $s->execute([$me['id']]);
    $activityCount = (int)$s->fetchColumn(); $hasRoadmap = $hasHolland;
}
$hubs = [
    ['01', 'knowledge-hub', 'Kiến thức & Tuyển sinh', 'Hiểu nghề trước khi chọn nghề.', 'Đọc hồ sơ nghề, tìm hiểu lộ trình học và tra cứu các nguồn tuyển sinh.', 'Bắt đầu khám phá', '◈'],
    ['02', 'assessment-hub', 'Đánh giá bản thân', 'Lắng nghe sở thích của mình.', 'Holland, xu hướng tính cách và tự phản tư cảm xúc giúp bạn có thêm góc nhìn.', $me ? $completedTests . '/3 bài đánh giá đã có kết quả' : 'Khám phá 3 góc nhìn', '◎'],
    ['03', 'simulations', 'Trải nghiệm nghề', 'Thử làm, rồi tự cảm nhận.', 'Một tiết dạy trong không gian 3D và các tình huống tương tác của nhiều nghề.', $me ? $activityCount . ' lượt mô phỏng 2D đã lưu' : 'Mô phỏng 3D & tình huống 2D', '◇'],
    ['04', 'ai-roadmap', 'AI & Bản đồ hành trình', 'Biến khám phá thành bước đi.', 'Đối chiếu kết quả, trao đổi với trợ lý và tạo lộ trình có thể in ra để cùng thảo luận.', $hasRoadmap ? 'Đã có dữ liệu Holland cho lộ trình' : 'Lộ trình theo dữ liệu bạn cung cấp', '✦'],
];
$pageTitle = 'Hành trình hướng nghiệp 360'; require ROOT_PATH.'/app/header.php';
?>
<section class="hub-hero"><div class="container hub-hero-layout"><div><span class="hub-kicker">HƯỚNG NGHIỆP 360 · DÀNH CHO HỌC SINH VIỆT NAM</span><h1>Hiểu mình hôm nay.<br><em>Chủ động ngày mai.</em></h1><p>Mỗi lựa chọn bắt đầu từ một lần khám phá. Tìm hiểu nghề, nhìn lại bản thân, thử trải nghiệm và xây dựng hành trình của riêng bạn.</p><a class="btn btn-primary btn-lg" href="<?=url('index.php?page=knowledge-hub')?>">Bắt đầu từ khám phá nghề →</a><a class="btn btn-outline btn-lg" href="<?=url('index.php?page=assessment-hub')?>">Khám phá bản thân</a></div><aside class="hub-compass" aria-label="Bốn bước khám phá nghề nghiệp"><span class="hub-kicker">HÀNH TRÌNH CỦA BẠN</span><?php foreach($hubs as $hub):?><a href="<?=url('index.php?page='.$hub[1])?>"><span><?=$hub[0]?></span><div><strong><?=e($hub[2])?></strong><small><?=e($hub[3])?></small></div><b aria-hidden="true">↗</b></a><?php endforeach;?></aside></div></section>
<section class="container hub-section"><div class="hub-section-heading"><div><span class="eyebrow">4 TRUNG TÂM · MỘT HÀNH TRÌNH</span><h2><?= $me ? 'Tiếp tục khám phá, '.e($me['name']) : 'Bạn muốn tìm hiểu điều gì hôm nay?' ?></h2></div><?php if($me):?><a href="<?=url('index.php?page=dashboard')?>">Hồ sơ & lịch sử →</a><?php endif;?></div><div class="hub-grid"><?php foreach($hubs as $hub):?><a class="card hub-card" href="<?=url('index.php?page='.$hub[1])?>"><div class="hub-card-top"><span class="hub-symbol"><?=$hub[6]?></span><span class="muted">/ <?=$hub[0]?></span></div><h3><?=e($hub[2])?></h3><p><?=e($hub[4])?></p><div class="hub-card-status"><?=e($hub[5])?> <span aria-hidden="true">→</span></div></a><?php endforeach;?></div><p class="hub-notice">Các bài đánh giá và mô phỏng là công cụ khám phá, không quyết định thay bạn. Hãy trao đổi thêm với gia đình, giáo viên và người trong nghề.</p></section>
<?php require ROOT_PATH.'/app/footer.php'; exit;
