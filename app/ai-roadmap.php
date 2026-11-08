<?php
declare(strict_types=1);
require_login();
require_once __DIR__ . '/four-hubs-ai.php';
$userId = (int)user()['id'];
if ($page === 'ai-chat') {
    if (!is_post()) redirect('index.php?page=ai-roadmap#career-chat');
    verify_csrf();
    $message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
    if ($message === '' || mb_strlen($message) > 2000) {
        flash('error', 'Hãy nhập câu hỏi từ 1 đến 2.000 ký tự.');
        redirect('index.php?page=ai-roadmap#career-chat');
    }
    $nonce = is_string($_POST['chat_nonce'] ?? null) ? $_POST['chat_nonce'] : '';
    if (!$nonce || !hash_equals($_SESSION['four_hub_chat_nonce'] ?? '', $nonce)) {
        flash('warning', 'Câu hỏi đã được gửi hoặc phiên nhập đã thay đổi. Hãy kiểm tra hội thoại trước khi gửi lại.');
        redirect('index.php?page=ai-roadmap#career-chat');
    }
    $context = four_hub_ai_context($userId);
    try {
        $messageId = four_hub_reserve_chat($userId, $message, $context);
        unset($_SESSION['four_hub_chat_nonce']);
        $answer = four_hub_chat_answer($userId, $messageId, $context, $message);
        $s = db()->prepare("INSERT INTO ai_chat_messages(user_id,role,message,model,latency_ms,created_at) VALUES(?,'assistant',?,?,?,?)");
        $s->execute([$userId, $answer['message'], $answer['model'], $answer['latency_ms'], date('Y-m-d H:i:s')]);
    } catch (Throwable $error) {
        flash('error', $error->getCode() === 429 ? $error->getMessage() : 'Chưa thể hoàn tất câu trả lời. Kiểm tra lịch sử trước khi thử lại; lượt đã gửi vẫn được tính trong hạn mức.');
    }
    redirect('index.php?page=ai-roadmap#career-chat');
}
$context = four_hub_ai_context($userId);
$used = four_hub_chat_count($userId);
$s = db()->prepare('SELECT role,message,model,created_at FROM ai_chat_messages WHERE user_id=? ORDER BY id DESC LIMIT 20');
$s->execute([$userId]);
$messages = array_reverse($s->fetchAll());
if (empty($_SESSION['four_hub_chat_nonce'])) $_SESSION['four_hub_chat_nonce'] = bin2hex(random_bytes(24));
$primaryCareer = $context['suggested_careers'][0] ?? null;
$pageTitle = 'AI & Bản đồ hành trình';
require __DIR__ . '/header.php';
?>
<section class="page-head no-print"><div class="container"><span class="eyebrow">Trung tâm 4 · Kết nối hiểu biết với hành động</span><h1>AI & Bản đồ hành trình</h1><p>Mỗi trải nghiệm bổ sung một góc nhìn. Bạn là người quyết định bước tiếp theo.</p></div></section>
<div class="container">
<section class="card no-print" aria-labelledby="profile-summary"><h2 id="profile-summary">Hồ sơ đa chiều của bạn</h2>
<div class="grid-3">
<div><h3>Holland</h3><p><?=e($context['holland']['code'] ?? 'Chưa có kết quả')?></p><a href="<?=url('index.php?page=assessment-report')?>">Xem báo cáo đánh giá →</a></div>
<div><h3>Xu hướng tính cách & EQ</h3><p>MBTI: <?=e($context['psychometrics']['mbti']['code'] ?? 'Chưa thực hiện')?></p><p>EQ: <?=isset($context['psychometrics']['eq'])?'Đã có hồ sơ tự nhận xét':'Chưa thực hiện'?></p></div>
<div><h3>Bằng chứng trải nghiệm</h3><p><?=count($context['experiences'])?> trải nghiệm gần nhất được tổng hợp (tối đa 5).</p><a href="<?=url('index.php?page=simulations')?>">Mở nhật ký trải nghiệm →</a></div>
</div><p class="muted">Các kết quả chỉ hỗ trợ khám phá, không phải chẩn đoán hay thước đo giá trị của bạn. Chưa có dữ liệu theo dõi thói quen học tập; trợ lý không tự suy đoán.</p></section>

<section id="career-roadmap" class="card roadmap-print" aria-labelledby="roadmap-title">
<div class="flex flex-wrap items-center justify-between gap-3"><div><span class="eyebrow">Bản đồ hành trình cá nhân</span><h2 id="roadmap-title"><?=e(user()['name'])?> · <?=e($context['grade'] ?: 'Lộ trình khám phá')?></h2></div><button type="button" class="btn btn-outline no-print" id="print-roadmap">🖨️ Xuất Bản Đồ Hành Trình (PDF)</button></div>
<p>Ngày lập: <?=date('d/m/Y')?> · Hướng khám phá: <?=e($primaryCareer['title'] ?? 'Chọn nghề sau khi đánh giá bản thân')?></p>
<ol class="roadmap-steps">
<li><h3>1. Khám phá</h3><p><?= $context['holland'] ? 'Đối chiếu mã Holland ' . e($context['holland']['code']) . ' với 2–3 nghề bạn muốn tìm hiểu. Ghi lại điều hấp dẫn và điều còn băn khoăn.' : 'Hoàn thành Holland và khảo sát điều kiện học tập để có gợi ý đầu tiên. MBTI và EQ bổ sung góc nhìn tự nhận xét.' ?></p><a class="no-print" href="<?=url('index.php?page=assessment-hub')?>">Mở bộ đánh giá →</a></li>
<li><h3>2. Môn học & lộ trình đào tạo</h3><p><?=e($primaryCareer['subjects'] ?? 'Chọn một hồ sơ nghề để xem các môn học cần tập trung.')?></p><p><?=e($primaryCareer['education_path'] ?? 'Trao đổi với giáo viên về lựa chọn học tập phù hợp với điều kiện của bạn.')?></p><p class="muted">Môn học tham khảo không phải tổ hợp xét tuyển đã xác minh. Kiểm tra đề án chính thức của từng trường, từng năm.</p></li>
<li><h3>3. Rèn luyện kỹ năng</h3><p><?=e($primaryCareer['skills'] ?? 'Chọn một kỹ năng như tìm kiếm thông tin, giao tiếp hoặc hợp tác và luyện tập qua một nhiệm vụ nhỏ.')?></p><p><?=!empty($context['experiences'])?'Đọc lại phản tư từ lần mô phỏng gần nhất, chọn một việc muốn xử lý khác và thử lại.':'Thử một mô phỏng nghề rồi ghi cảm xúc, cách xử lý và điều cần hỗ trợ.'?></p><a class="no-print" href="<?=url('index.php?page=simulations')?>">Vào phòng mô phỏng →</a></li>
<li><h3>4. Thử thách 15 phút đời thực</h3><p>Dành 5 phút tìm một nhiệm vụ thường ngày của <?=e($primaryCareer['title'] ?? 'nghề bạn quan tâm')?>, 5 phút thử giải thích cách làm cho một người bạn và 5 phút viết lại điều hào hứng, điều khó, câu hỏi tiếp theo. Không thực hiện nhiệm vụ cần giấy phép hoặc có rủi ro.</p></li>
</ol><p class="muted">Đây là kế hoạch gợi ý từ hồ sơ hiện có, không phải cam kết tuyển sinh hay kết luận năng lực. Cập nhật sau mỗi trải nghiệm và trao đổi cùng giáo viên/người lớn tin cậy.</p>
</section>

<section id="career-chat" class="card no-print" aria-labelledby="chat-title"><h2 id="chat-title">Trao đổi với trợ lý hướng nghiệp</h2>
<p class="muted">Khi AI được cấu hình, câu hỏi, hội thoại gần đây và tóm tắt đánh giá/trải nghiệm được gửi tới OpenAI để trả lời. Không nhập địa chỉ, số điện thoại hoặc thông tin riêng tư của người khác. Không có AI thì hệ thống hiển thị gợi ý dự phòng.</p>
<p><strong><?=max(0,10-$used)?> / 10 lượt còn lại hôm nay</strong> · Đặt lại lúc 00:00 theo giờ Việt Nam. Lượt dùng dự phòng vẫn tính vào hạn mức.</p>
<div class="chat-history" aria-label="Lịch sử hội thoại">
<?php if (!$messages): ?><p class="muted">Bắt đầu bằng một câu hỏi về hồ sơ hoặc bước tiếp theo của bạn.</p><?php endif; ?>
<?php foreach ($messages as $chat): ?><article class="card chat-message"><strong><?=$chat['role']==='user'?'Bạn':($chat['model']==='fallback'?'Gợi ý dự phòng':'Trợ lý AI')?></strong><small class="muted"> · <?=e($chat['created_at'])?></small><p style="white-space:pre-wrap;overflow-wrap:anywhere"><?=e($chat['message'])?></p></article><?php endforeach; ?>
</div>
<div class="flex flex-wrap gap-3" aria-label="Câu hỏi gợi ý"><?php foreach (['Tôi nên tập trung môn học nào?', 'MBTI và EQ có ý nghĩa gì với tôi?', 'Gợi ý thử thách 15 phút từ hồ sơ của tôi.'] as $chip): ?><button type="button" class="btn btn-outline" data-chat-prompt="<?=e($chip)?>"><?=e($chip)?></button><?php endforeach; ?></div>
<form method="post" action="<?=url('index.php?page=ai-chat')?>" class="mt-4" id="career-chat-form"><?=csrf_field()?><input type="hidden" name="chat_nonce" value="<?=e($_SESSION['four_hub_chat_nonce'])?>"><div class="form-group"><label for="career-question">Câu hỏi của bạn</label><textarea class="input" id="career-question" name="message" rows="4" maxlength="2000" required <?=$used>=10?'disabled':''?>></textarea></div><button class="btn btn-primary" type="submit" <?=$used>=10?'disabled':''?>>Gửi câu hỏi</button><p id="chat-send-status" role="status"></p></form>
</section></div>
<script>
document.getElementById('print-roadmap').addEventListener('click',()=>window.print());
document.querySelectorAll('[data-chat-prompt]').forEach(button=>button.addEventListener('click',()=>{const field=document.getElementById('career-question');field.value=button.dataset.chatPrompt;field.focus();}));
document.getElementById('career-chat-form').addEventListener('submit',function(){this.querySelector('button[type=submit]').disabled=true;document.getElementById('chat-send-status').textContent='Đang tổng hợp hồ sơ và trả lời…';});
</script>
<?php require __DIR__ . '/footer.php'; exit;
