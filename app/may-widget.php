<?php
declare(strict_types=1);

$currentUser = user();
$csrf = csrf_token();
?>
<!-- Trợ lý La Bàn Chatbot Widget -->
<link rel="stylesheet" href="<?=url('assets/css/may-chatbot.css')?>">

<button
  id="mayChatTrigger"
  class="may-chat-trigger"
  type="button"
  title="Trợ lý La Bàn — Cùng bạn tìm hướng nghề nghiệp"
  aria-label="Mở Trợ lý La Bàn — Cùng bạn tìm hướng nghề nghiệp"
  aria-expanded="false"
  aria-controls="mayChatWindow"
>
  <span class="may-trigger-icon" aria-hidden="true">🧭</span>
  <span class="may-trigger-text">Trợ lý La Bàn</span>
</button>

<div id="mayChatWindow" class="may-chat-window" role="dialog" aria-label="Khung trò chuyện với Trợ lý La Bàn" aria-hidden="true">
  <!-- Header -->
  <div class="may-chat-header">
    <div class="may-header-info">
      <div class="may-header-avatar" aria-hidden="true">🧭</div>
      <div>
        <h2 class="may-header-title">Trợ lý La Bàn</h2>
        <span class="may-header-subtitle">Cùng bạn tìm hướng nghề nghiệp</span>
      </div>
    </div>
    <div class="may-header-actions">
      <button id="mayResetBtn" class="may-btn-icon" type="button" title="Cuộc trò chuyện mới" aria-label="Cuộc trò chuyện mới">↺</button>
      <button id="mayCloseBtn" class="may-btn-icon" type="button" title="Đóng khung chat (Esc)" aria-label="Đóng khung chat">✕</button>
    </div>
  </div>

  <!-- Privacy & Quota sub-bar -->
  <div class="may-privacy-bar">
    <?php if ($currentUser): ?>
      <label title="Chỉ gửi tóm tắt kết quả khám phá/đánh giá khi bạn hỏi; không gửi mật khẩu hay thông tin tài khoản không liên quan">
        <input type="checkbox" id="mayProfileOptin" checked>
        <span>Gợi ý theo hồ sơ của tôi</span>
      </label>
    <?php else: ?>
      <span style="font-size:0.75rem;color:#64748b;">Khách vãng lai (lưu tạm trên trình duyệt)</span>
    <?php endif; ?>
    <span id="mayQuotaPill" class="may-quota-pill"><?=$currentUser ? '30 câu/ngày' : '10 câu/ngày'?></span>
  </div>

  <!-- Messages List -->
  <div id="mayMessagesList" class="may-messages-area" role="log" aria-live="polite"></div>

  <!-- Suggestion Chips Container -->
  <div id="mayChipsContainer" class="may-chips-container" aria-label="Gợi ý câu hỏi mở đầu"></div>

  <!-- Input Area -->
  <div class="may-input-area">
    <textarea id="mayInputTextarea" class="may-textarea" placeholder="Hỏi Trợ lý La Bàn về bản thân, ngành học, tuyển sinh, kế hoạch..." rows="1" aria-label="Nhập câu hỏi cho Trợ lý La Bàn"></textarea>
    <button id="maySendBtn" class="may-send-btn" type="button" aria-label="Gửi tin nhắn tới Trợ lý La Bàn" disabled>➤</button>
  </div>
</div>

<script>
  window.JOBSIMU_CSRF = <?=json_encode($csrf)?>;
</script>
<script src="<?=url('assets/js/may-chatbot.js')?>" defer></script>
