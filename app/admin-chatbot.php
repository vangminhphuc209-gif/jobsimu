<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/chat-providers.php';
require_once __DIR__ . '/may-telemetry.php';

require_admin();

$groqKey = trim((string)(getenv('GROQ_API_KEY') ?: ''));
$groqModel = trim((string)(getenv('GROQ_MODEL') ?: 'openai/gpt-oss-120b'));
$cfToken = trim((string)(getenv('CLOUDFLARE_API_TOKEN') ?: ''));
$cfAccount = trim((string)(getenv('CLOUDFLARE_ACCOUNT_ID') ?: ''));
$cfModel = trim((string)(getenv('CLOUDFLARE_MODEL') ?: '@cf/meta/llama-3.1-8b-instruct-fp8-fast'));

$groqConfigured = ($groqKey !== '');
$cfConfigured = ($cfToken !== '' && preg_match('/^[a-f0-9]{32}$/i', $cfAccount));

// Handle POST actions (Health Check & Cooldown Reset)
if (is_post()) {
    verify_csrf();
    $intent = (string)($_POST['intent'] ?? '');

    if ($intent === 'health_check') {
        $provider = trim((string)($_POST['provider'] ?? ''));
        if (!in_array($provider, ['groq', 'cloudflare'], true)) {
            flash('error', 'Provider không hợp lệ.');
        } else {
            $result = chat_provider_health_check($provider);
            if ($result['ok']) {
                flash('success', "✓ Kiểm tra kết nối thành công: {$provider} ({$result['model']}) phản hồi trong {$result['latency_ms']}ms lúc {$result['timestamp']}.");
            } else {
                flash('error', "✗ Kiểm tra kết nối thất bại: {$provider} — {$result['message']} (HTTP {$result['http_status']}).");
            }
        }
        redirect('admin.php?section=chatbot');
    }

    if ($intent === 'reset_cooldown') {
        $provider = trim((string)($_POST['provider'] ?? ''));
        chat_provider_reset_cooldown($provider);
        flash('success', 'Đã đặt lại trạng thái Circuit Breaker Cooldown cho ' . ($provider ?: 'tất cả provider') . '.');
        redirect('admin.php?section=chatbot');
    }
}

// Fetch stats and logs
$stats = may_get_telemetry_stats();
$logs = may_get_telemetry_logs(50);
$cooldowns = chat_provider_cooldown_status();

$groqInCooldown = chat_provider_is_in_cooldown('groq');
$cfInCooldown = chat_provider_is_in_cooldown('cloudflare');

$title = 'Quản trị Chatbot Mây';
?><!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=e($title)?> · Admin 360</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?=url('assets/css/app.css')?>">
  <link rel="stylesheet" href="<?=url('assets/css/admin.css')?>">
  <style>
    .provider-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-top: 1rem; }
    .provider-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .badge-on { background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; }
    .badge-off { background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; }
    .badge-cooldown { background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; }
    .meta-list { margin-top: 1rem; font-size: 0.875rem; }
    .meta-list dt { font-weight: 600; color: #64748b; margin-top: 0.5rem; }
    .meta-list dd { color: #1e293b; font-family: ui-monospace, monospace; font-size: 0.8125rem; word-break: break-all; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-top: 1rem; }
    .stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; }
    .stat-card span { display: block; font-size: 0.8125rem; color: #64748b; font-weight: 500; }
    .stat-card strong { display: block; font-size: 1.75rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem; }
  </style>
</head>
<body>
<div class="admin-layout">
  <aside class="sidebar">
    <a class="brand" href="<?=url('admin.php')?>"><span>✦</span>Admin 360</a>
    <nav>
      <?php foreach([
        'admissions'=>'🏫 Tuyển sinh',
        'majors-review'=>'🎓 Ngành & Xác minh',
        'knowledge-articles'=>'📚 Bài viết',
        'knowledge-portals'=>'🌐 Cổng thông tin',
        'chatbot'=>'☁️ Chatbot Mây',
        'dashboard'=>'▦ Tổng quan',
        'careers'=>'💼 Nghề nghiệp',
        'simulations'=>'🎮 Mô phỏng nghề',
        'questions'=>'☑ Câu hỏi',
        'users'=>'👥 Học sinh',
        'results'=>'📈 Kết quả',
        'settings'=>'⚙ Cấu hình'
      ] as $k=>$v):?>
        <a class="<?=$k==='chatbot'?'active':''?>" href="<?=url('admin.php?section='.$k)?>"><?=$v?></a>
      <?php endforeach;?>
      <a href="<?=url('index.php')?>">← Xem website</a>
    </nav>
  </aside>

  <main class="admin-main">
    <header class="admin-top">
      <div>
        <span class="muted">Hệ thống quản trị</span>
        <h1>☁️ Quản trị Chatbot Mây</h1>
      </div>
      <div class="user-chip">
        <span class="avatar" style="background:<?=e(user()['avatar_color'])?>"><?=e(mb_substr(user()['name'],0,1))?></span>
        <b><?=e(user()['name'])?></b>
      </div>
    </header>

    <?php foreach(pull_flashes() as $f):?>
      <div class="alert alert-<?=e($f['type'])?>"><?=e($f['message'])?></div>
    <?php endforeach;?>

    <!-- Section 1: Provider Configurations & Health Check -->
    <section>
      <h2>1. Cấu hình & Trạng thái Nhà cung cấp AI (Dual Provider Failover)</h2>
      <p class="muted">Hệ thống sử dụng cơ chế chuyển đổi dự phòng tự động: ưu tiên Groq (độ trễ thấp, suy luận tốt), tự động chuyển sang Cloudflare Workers AI khi Groq đạt hạn mức (429), quá thời gian chờ (timeout) hoặc lỗi máy chủ.</p>

      <div class="provider-grid">
        <!-- Groq Card -->
        <div class="provider-card">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;font-size:1.15rem;">⚡ Groq (Nhà cung cấp chính)</h3>
            <?php if($groqInCooldown):?>
              <span class="badge-cooldown">Đang Cooldown</span>
            <?php elseif($groqConfigured):?>
              <span class="badge-on">Đã cấu hình</span>
            <?php else:?>
              <span class="badge-off">Chưa cấu hình</span>
            <?php endif;?>
          </div>

          <dl class="meta-list">
            <dt>Mô hình (Model):</dt>
            <dd><?=e($groqModel)?></dd>
            <dt>Khóa API (Che bảo mật):</dt>
            <dd><?=e(chat_provider_mask_secret($groqKey, 4, 4))?></dd>
            <dt>Trạng thái Circuit Breaker:</dt>
            <dd>
              <?php if($groqInCooldown):?>
                <span style="color:#d97706">⚠️ Tạm ngừng gửi yêu cầu đến <?=date('H:i:s', $cooldowns['groq']['until']??0)?></span>
              <?php else:?>
                <span style="color:#16a34a">✓ Sẵn sàng nhận yêu cầu</span>
              <?php endif;?>
            </dd>
          </dl>

          <div style="margin-top:1.25rem;border-top:1px solid #f1f5f9;padding-top:1rem;">
            <form method="post">
              <?=csrf_field()?>
              <input type="hidden" name="intent" value="health_check">
              <input type="hidden" name="provider" value="groq">
              <p class="muted" style="font-size:0.75rem;margin-bottom:0.5rem;">Thao tác này gửi 1 request thử nghiệm tới API bên thứ ba để kiểm tra kết nối.</p>
              <button class="btn btn-primary w-full" <?=$groqConfigured?'':'disabled'?>>Kiểm tra kết nối Groq (Health Check)</button>
            </form>
            <?php if($groqInCooldown):?>
              <form method="post" style="margin-top:0.5rem;">
                <?=csrf_field()?>
                <input type="hidden" name="intent" value="reset_cooldown">
                <input type="hidden" name="provider" value="groq">
                <button class="btn btn-outline w-full" style="font-size:0.8125rem;">Đặt lại Cooldown Groq ngay</button>
              </form>
            <?php endif;?>
          </div>
        </div>

        <!-- Cloudflare Card -->
        <div class="provider-card">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;font-size:1.15rem;">☁️ Cloudflare Workers AI (Dự phòng)</h3>
            <?php if($cfInCooldown):?>
              <span class="badge-cooldown">Đang Cooldown</span>
            <?php elseif($cfConfigured):?>
              <span class="badge-on">Đã cấu hình</span>
            <?php else:?>
              <span class="badge-off">Chưa cấu hình</span>
            <?php endif;?>
          </div>

          <dl class="meta-list">
            <dt>Mô hình (Model):</dt>
            <dd><?=e($cfModel)?></dd>
            <dt>Account ID (Che bảo mật):</dt>
            <dd><?=e(chat_provider_mask_secret($cfAccount, 4, 3))?></dd>
            <dt>Token API (Che bảo mật):</dt>
            <dd><?=e(chat_provider_mask_secret($cfToken, 5, 4))?></dd>
            <dt>Trạng thái Circuit Breaker:</dt>
            <dd>
              <?php if($cfInCooldown):?>
                <span style="color:#d97706">⚠️ Tạm ngừng gửi yêu cầu đến <?=date('H:i:s', $cooldowns['cloudflare']['until']??0)?></span>
              <?php else:?>
                <span style="color:#16a34a">✓ Sẵn sàng nhận yêu cầu</span>
              <?php endif;?>
            </dd>
          </dl>

          <div style="margin-top:1.25rem;border-top:1px solid #f1f5f9;padding-top:1rem;">
            <form method="post">
              <?=csrf_field()?>
              <input type="hidden" name="intent" value="health_check">
              <input type="hidden" name="provider" value="cloudflare">
              <p class="muted" style="font-size:0.75rem;margin-bottom:0.5rem;">Thao tác này gửi 1 request thử nghiệm tới API bên thứ ba để kiểm tra kết nối.</p>
              <button class="btn btn-primary w-full" <?=$cfConfigured?'':'disabled'?>>Kiểm tra kết nối Cloudflare (Health Check)</button>
            </form>
            <?php if($cfInCooldown):?>
              <form method="post" style="margin-top:0.5rem;">
                <?=csrf_field()?>
                <input type="hidden" name="intent" value="reset_cooldown">
                <input type="hidden" name="provider" value="cloudflare">
                <button class="btn btn-outline w-full" style="font-size:0.8125rem;">Đặt lại Cooldown Cloudflare ngay</button>
              </form>
            <?php endif;?>
          </div>
        </div>
      </div>
    </section>

    <!-- Section 2: Metrics Summary -->
    <section style="margin-top:2.5rem;">
      <h2>2. Chỉ số Hoạt động & Chuyển đổi Dự phòng (Telemetry Metrics)</h2>
      <div class="stats-grid">
        <div class="stat-card">
          <span>Tổng số lượt hỏi</span>
          <strong><?=number_format((int)$stats['total_requests'])?></strong>
        </div>
        <div class="stat-card">
          <span>Lượt hỏi hôm nay</span>
          <strong><?=number_format((int)$stats['today_requests'])?></strong>
        </div>
        <div class="stat-card">
          <span>Xử lý bởi Groq</span>
          <strong style="color:#4f46e5;"><?=number_format((int)$stats['groq_count'])?></strong>
          <small class="muted">TB: <?=$stats['groq_latency_avg']?>ms</small>
        </div>
        <div class="stat-card">
          <span>Failover sang Cloudflare</span>
          <strong style="color:#f59e0b;"><?=number_format((int)$stats['cloudflare_count'])?></strong>
          <small class="muted">TB: <?=$stats['cloudflare_latency_avg']?>ms</small>
        </div>
        <div class="stat-card">
          <span>Phản hồi Fallback</span>
          <strong style="color:<?=($stats['fallback_count']>0?'#ef4444':'#10b981')?>;"><?=number_format((int)$stats['fallback_count'])?></strong>
          <small class="muted">Khi cả 2 provider bận</small>
        </div>
      </div>
    </section>

    <!-- Section 3: Sanitized Request Logs -->
    <section style="margin-top:2.5rem;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div>
          <h2>3. Nhật ký Yêu cầu Đã được Làm sạch (Sanitized Logs)</h2>
          <p class="muted" style="margin-top:0.25rem;">Nhật ký chỉ lưu trữ metadata vận hành (thời gian, loại người dùng, độ trễ, mã lỗi); tuyệt đối không lưu câu hỏi, câu trả lời hoặc dữ liệu cá nhân của học sinh.</p>
        </div>
      </div>

      <div class="card table-wrap" style="margin-top:1rem;">
        <?php if(empty($logs)):?>
          <div style="text-align:center;padding:2rem;" class="muted">Chưa có nhật ký yêu cầu nào được ghi nhận.</div>
        <?php else:?>
          <table>
            <thead>
              <tr>
                <th>Thời điểm</th>
                <th>Người dùng</th>
                <th>Provider</th>
                <th>Model</th>
                <th>Độ trễ</th>
                <th>HTTP Status</th>
                <th>Trạng thái</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($logs as $log):?>
                <tr>
                  <td><?=e($log['timestamp'])?></td>
                  <td><b><?=e($log['user_type'])?></b></td>
                  <td>
                    <?php if($log['provider']==='groq'):?>
                      <span style="color:#4f46e5;font-weight:600;">⚡ Groq</span>
                    <?php elseif($log['provider']==='cloudflare'):?>
                      <span style="color:#f59e0b;font-weight:600;">☁️ Cloudflare</span>
                    <?php else:?>
                      <span class="muted"><?=e($log['provider'])?></span>
                    <?php endif;?>
                  </td>
                  <td><code style="font-size:0.75rem;"><?=e($log['model'])?></code></td>
                  <td><b><?=number_format((int)$log['latency_ms'])?>ms</b></td>
                  <td><span class="tag"><?=e($log['http_status'])?></span></td>
                  <td>
                    <?php if($log['status']==='completed'):?>
                      <span class="status status-on">Thành công</span>
                    <?php else:?>
                      <span class="status status-off"><?=e($log['status'])?></span>
                    <?php endif;?>
                  </td>
                </tr>
              <?php endforeach;?>
            </tbody>
          </table>
        <?php endif;?>
      </div>
    </section>
  </main>
</div>
<script src="<?=url('assets/js/app.js')?>"></script>
</body>
</html>
<?php exit;
