<?php declare(strict_types=1); ?>
<section id="career-conversations" class="card no-print" aria-labelledby="conversation-title">
<span class="eyebrow">BẢN THỬ · 3 NGHỀ · 9 TÌNH HUỐNG</span><h2 id="conversation-title">Góc người trong nghề</h2>
<p>Trò chuyện với AI mô phỏng góc nhìn nghề nghiệp. Các tình huống do dự án biên soạn, chưa được người trong nghề thẩm định; không phải lời kể hay cuộc trò chuyện với người thật.</p>
<p>Chọn một tình huống, bấm câu hỏi gợi ý rồi gửi. Bạn có thể sửa câu hỏi, tự viết cách xử lý hoặc dừng bất cứ lúc nào. Không chấm điểm phù hợp nghề từ câu trả lời.</p>
<?php foreach (['Giáo viên', 'Lập trình viên', 'Thiết kế đồ họa'] as $careerName): ?>
<details <?=($scenario && $scenario['career']===$careerName)?'open':''?>><summary><strong><?=e($careerName)?></strong> · 3 tình huống</summary><div class="grid-3">
<?php foreach (career_conversation_catalog() as $item): if ($item['career']!==$careerName) continue; ?>
<article class="card"><h3><?=e($item['title'])?></h3><p><?=e($item['scene'])?></p><a class="btn btn-outline" href="<?=url('index.php?page=ai-roadmap&scenario='.$item['id'].'#career-chat')?>">Khám phá tình huống →</a></article>
<?php endforeach; ?></div></details><?php endforeach; ?>
<p class="muted">Gợi ý cho giáo viên: cho học sinh chọn cách xử lý, giải thích lý do rồi so sánh hai hướng; không dùng hội thoại làm điểm đánh giá học sinh.</p>
</section>
<?php if ($scenario): ?>
<section class="card no-print" aria-labelledby="selected-scenario"><span class="eyebrow">TÌNH HUỐNG GIẢ ĐỊNH · <?=e($scenario['career'])?></span><h2 id="selected-scenario"><?=e($scenario['title'])?></h2><p><?=e($scenario['scene'])?></p>
<details><summary>Cơ sở và giới hạn của nội dung</summary><p><a href="<?=e($scenario['source']['url'])?>" target="_blank" rel="noopener noreferrer"><?=e($scenario['source']['title'])?></a> · Kiểm tra ngày <?=e($scenario['source']['checked_at'])?>.</p><p><?=e($scenario['source']['scope'])?></p><p>Phần tiếp diễn AI tạo cũng là giả định. Chưa có lời chứng thực hay phỏng vấn người đi làm trong bản thử này.</p></details>
<a href="<?=url('index.php?page=ai-roadmap#career-chat')?>">Quay về chat hướng nghiệp thông thường</a>
</section>
<?php endif; ?>
<section class="card no-print" aria-labelledby="website-guide"><h2 id="website-guide">Bạn muốn làm gì trên website?</h2><div class="grid-3">
<?php foreach (career_website_guides() as $guide): ?><article><h3><?=e($guide['title'])?></h3><p><?=e($guide['steps'])?></p><a href="<?=url($guide['path'])?>">Mở mục này →</a></article><?php endforeach; ?>
</div></section>
