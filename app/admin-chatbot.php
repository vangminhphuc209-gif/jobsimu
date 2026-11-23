<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/chat-providers.php';
require_once __DIR__ . '/may-telemetry.php';

require_admin();

$rawConfigs = chat_provider_get_raw_config();
$groqCfg = $rawConfigs['groq'];
$cfCfg = $rawConfigs['cloudflare'];

$groqHealth = chat_provider_get_health_status('groq');
$cfHealth = chat_provider_get_health_status('cloudflare');

// Handle POST actions (Health Check, Cooldown Reset, Save Keys)
if (is_post()) {
    verify_csrf();
    $intent = (string)($_POST['intent'] ?? '');

    if ($intent === 'save_keys') {
        $keys = [
            'groq_api_key' => trim((string)($_POST['groq_api_key'] ?? '')),
            'groq_model' => trim((string)($_POST['groq_model'] ?? 'openai/gpt-oss-120b')),
            'cloudflare_api_token' => trim((string)($_POST['cloudflare_api_token'] ?? '')),
            'cloudflare_account_id' => trim((string)($_POST['cloudflare_account_id'] ?? '')),
            'cloudflare_model' => trim((string)($_POST['cloudflare_model'] ?? '@cf/meta/llama-3.1-8b-instruct-fp8-fast')),
        ];

        // Cảnh báo nếu Cloudflare Account ID không đủ 32 ký tự
        if ($keys['cloudflare_account_id'] !== '' && strlen($keys['cloudflare_account_id']) !== 32) {
            flash('warning', '⚠️ Lưu ý: Cloudflare Account ID chuẩn là 32 ký tự hex (chuỗi bạn nhập có ' . strlen($keys['cloudflare_account_id']) . ' ký tự). Hãy kiểm tra lại nếu gặp lỗi 404 Not Found.');
        }

        $stmt = db()->prepare('INSERT INTO settings(setting_key, setting_value) VALUES(?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($keys as $k => $v) {
            if ($v !== '') {
                $stmt->execute([$k, $v]);
            }
        }
        if (function_exists('setting')) {
            setting('', '', true); // Refresh cache settings
        }
        flash('success', '✓ Đã cập nhật và lưu cấu hình API Key vào cơ sở dữ liệu hệ thống.');
        redirect('admin.php?section=chatbot');
    }

    if ($intent === 'health_check') {
        $provider = trim((string)($_POST['provider'] ?? ''));
        if (!in_array($provider, ['groq', 'cloudflare'], true)) {
            flash('error', 'Nhà cung cấp không hợp lệ.');
        } else {
            $result = chat_provider_health_check($provider);
            if ($result['ok']) {
                flash('success', "✓ Kiểm tra kết nối thành công: {$provider} ({$result['model']}) phản hồi trong {$result['latency_ms']}ms lúc {$result['timestamp']}.");
            } else {
                flash('error', "✗ Kiểm tra kết nối thất bại: {$provider} — {$result['message']}");
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

$title = 'Quản trị Trợ lý La Bàn';
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
    .provider-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; margin-top: 1rem; }
    .provider-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .badge-on { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; }
    .badge-off { background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; }
    .badge-untested { background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; }
    .badge-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; }
    .badge-cooldown { background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; }
    .source-tag { font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; font-weight: 500; }
    .source-env { background: #f1f5f9; color: #475569; }
    .source-db { background: #ecfdf5; color: #047857; font-weight: 600; }
    .source-config { background: #ede9fe; color: #6d28d9; }
    .meta-list { margin-top: 1rem; font-size: 0.875rem; }
    .meta-list dt { font-weight: 600; color: #64748b; margin-top: 0.65rem; }
    .meta-list dd { color: #1e293b; font-family: ui-monospace, monospace; font-size: 0.8125rem; word-break: break-all; margin-left: 0; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem; }
    .stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; }
    .stat-card span { display: block; font-size: 0.8125rem; color: #64748b; font-weight: 500; }
    .stat-card strong { display: block; font-size: 1.75rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem; }
    .status-breakdown { font-size: 0.75rem; color: #64748b; margin-top: 0.5rem; line-height: 1.4; }
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
        'chatbot'=>'🧭 Trợ lý La Bàn',
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
        <h1>🧭 Quản trị Trợ lý La Bàn</h1>
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
      <p class="muted">Hệ thống ưu tiên Groq (độ trễ thấp, suy luận nhanh), tự động chuyển đổi sang Cloudflare Workers AI khi Groq chạm hạn mức (429), quá thời gian chờ (timeout) hoặc lỗi máy chủ.</p>

      <div class="provider-grid">
        <!-- Groq Card -->
        <div class="provider-card">
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h3 style="margin:0;font-size:1.15rem;">⚡ Groq (Ưu tiên chính)</h3>
            <span class="<?=$groqHealth['badge_class']?>">
              <?php if($groqHealth['status']==='healthy'):?>● <?php elseif($groqHealth['status']==='error'):?>✗ <?php elseif($groqHealth['status']==='untested'):?>○ <?php else:?>● <?php endif;?>
              <?=e($groqHealth['label'])?>
            </span>
          </div>

          <dl class="meta-list">
            <dt>Mô hình (Model):</dt>
            <dd><?=e($groqCfg['model'])?></dd>

            <dt>Nguồn nạp cấu hình:</dt>
            <dd>
              <?php if($groqCfg['source']==='env'):?>
                <span class="source-tag source-env">Biến môi trường (.env / server)</span>
              <?php elseif($groqCfg['source']==='db'):?>
                <span class="source-tag source-db">Cơ sở dữ liệu (settings)</span>
              <?php elseif($groqCfg['source']==='config'):?>
                <span class="source-tag source-config">File config.php</span>
              <?php else:?>
                <span class="source-tag" style="background:#fee2e2;color:#991b1b;">Chưa cấu hình</span>
              <?php endif;?>
            </dd>

            <dt>Khóa API (Che bảo mật):</dt>
            <dd><?=e(chat_provider_mask_secret($groqCfg['key'], 4, 4))?></dd>

            <dt>Kiểm tra kết nối gần nhất (Health Check):</dt>
            <dd>
              <?php if($groqHealth['status']==='healthy'):?>
                <span style="color:#16a34a;font-weight:600;">✓ Phản hồi trong <?=$groqHealth['latency_ms']?>ms (HTTP 200)</span>
                <div class="muted" style="font-size:0.75rem;">Lúc: <?=e($groqHealth['tested_at'])?></div>
              <?php elseif($groqHealth['status']==='error'):?>
                <span style="color:#dc2626;font-weight:600;">✗ <?=e($groqHealth['message'])?></span>
                <div class="muted" style="font-size:0.75rem;">Lúc: <?=e($groqHealth['tested_at'])?></div>
              <?php elseif($groqHealth['status']==='untested'):?>
                <span style="color:#0284c7;">Chưa kiểm tra (Bấm nút bên dưới để test kết nối)</span>
              <?php else:?>
                <span style="color:#dc2626;">Chưa cấu hình GROQ_API_KEY</span>
              <?php endif;?>
            </dd>

            <dt>Trạng thái Circuit Breaker (Bộ ngắt mạch):</dt>
            <dd>
              <?php if($groqInCooldown):?>
                <span style="color:#d97706;font-weight:600;">⚠️ Tạm ngừng gửi yêu cầu đến <?=date('H:i:s', $cooldowns['groq']['until']??0)?> (Do lỗi HTTP <?=$cooldowns['groq']['last_status']??0?>)</span>
              <?php else:?>
                <span style="color:#16a34a;">✓ Bình thường (Sẵn sàng thử lại)</span>
                <div class="muted" style="font-size:0.72rem;font-family:sans-serif;margin-top:2px;">* Lưu ý: Trạng thái sẵn sàng chỉ thể hiện bộ ngắt mạch không bị khóa, không đại diện cho việc kết nối API thành công.</div>
              <?php endif;?>
            </dd>
          </dl>

          <div style="margin-top:1.25rem;border-top:1px solid #f1f5f9;padding-top:1rem;">
            <form method="post">
              <?=csrf_field()?>
              <input type="hidden" name="intent" value="health_check">
              <input type="hidden" name="provider" value="groq">
              <p class="muted" style="font-size:0.75rem;margin-bottom:0.5rem;">Gửi một yêu cầu thử nghiệm thực tế tới Groq API để kiểm tra kết nối và token.</p>
              <button class="btn btn-primary w-full" <?=$groqCfg['configured']?'':'disabled'?>>Kiểm tra kết nối Groq (Health Check)</button>
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
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h3 style="margin:0;font-size:1.15rem;">☁️ Cloudflare Workers AI (Dự phòng)</h3>
            <span class="<?=$cfHealth['badge_class']?>">
              <?php if($cfHealth['status']==='healthy'):?>● <?php elseif($cfHealth['status']==='error'):?>✗ <?php elseif($cfHealth['status']==='untested'):?>○ <?php else:?>● <?php endif;?>
              <?=e($cfHealth['label'])?>
            </span>
          </div>

          <dl class="meta-list">
            <dt>Mô hình (Model):</dt>
            <dd><?=e($cfCfg['model'])?></dd>

            <dt>Nguồn nạp cấu hình:</dt>
            <dd>
              <?php if($cfCfg['source']==='env'):?>
                <span class="source-tag source-env">Biến môi trường (.env / server)</span>
              <?php elseif($cfCfg['source']==='db'):?>
                <span class="source-tag source-db">Cơ sở dữ liệu (settings)</span>
              <?php elseif($cfCfg['source']==='config'):?>
                <span class="source-tag source-config">File config.php</span>
              <?php else:?>
                <span class="source-tag" style="background:#fee2e2;color:#991b1b;">Chưa cấu hình</span>
              <?php endif;?>
            </dd>

            <dt>Account ID (Che bảo mật):</dt>
            <dd>
              <?=e(chat_provider_mask_secret($cfCfg['account'], 4, 3))?>
              <?php if($cfCfg['account']!==''):?>
                <span class="muted" style="font-size:0.75rem;">(<?=$cfCfg['account_len']?> ký tự)</span>
                <?php if($cfCfg['account_len']!==32):?>
                  <div style="color:#d97706;font-size:0.75rem;margin-top:2px;">⚠️ Cảnh báo: Account ID Cloudflare chuẩn phải đủ 32 ký tự hex.</div>
                <?php endif;?>
              <?php endif;?>
            </dd>

            <dt>Token API (Che bảo mật):</dt>
            <dd><?=e(chat_provider_mask_secret($cfCfg['token'], 5, 4))?></dd>

            <dt>Kiểm tra kết nối gần nhất (Health Check):</dt>
            <dd>
              <?php if($cfHealth['status']==='healthy'):?>
                <span style="color:#16a34a;font-weight:600;">✓ Phản hồi trong <?=$cfHealth['latency_ms']?>ms (HTTP 200)</span>
                <div class="muted" style="font-size:0.75rem;">Lúc: <?=e($cfHealth['tested_at'])?></div>
              <?php elseif($cfHealth['status']==='error'):?>
                <span style="color:#dc2626;font-weight:600;">✗ <?=e($cfHealth['message'])?></span>
                <div class="muted" style="font-size:0.75rem;">Lúc: <?=e($cfHealth['tested_at'])?></div>
              <?php elseif($cfHealth['status']==='untested'):?>
                <span style="color:#0284c7;">Chưa kiểm tra (Bấm nút bên dưới để test kết nối)</span>
              <?php else:?>
                <span style="color:#dc2626;">Thiếu: <?=e(implode(', ', $cfCfg['missing']))?></span>
              <?php endif;?>
            </dd>

            <dt>Trạng thái Circuit Breaker (Bộ ngắt mạch):</dt>
            <dd>
              <?php if($cfInCooldown):?>
                <span style="color:#d97706;font-weight:600;">⚠️ Tạm ngừng gửi yêu cầu đến <?=date('H:i:s', $cooldowns['cloudflare']['until']??0)?> (Do lỗi HTTP <?=$cooldowns['cloudflare']['last_status']??0?>)</span>
              <?php else:?>
                <span style="color:#16a34a;">✓ Bình thường (Sẵn sàng thử lại)</span>
                <div class="muted" style="font-size:0.72rem;font-family:sans-serif;margin-top:2px;">* Lưu ý: Trạng thái sẵn sàng chỉ thể hiện bộ ngắt mạch không bị khóa, không đại diện cho việc kết nối API thành công.</div>
              <?php endif;?>
            </dd>
          </dl>

          <div style="margin-top:1.25rem;border-top:1px solid #f1f5f9;padding-top:1rem;">
            <form method="post">
              <?=csrf_field()?>
              <input type="hidden" name="intent" value="health_check">
              <input type="hidden" name="provider" value="cloudflare">
              <p class="muted" style="font-size:0.75rem;margin-bottom:0.5rem;">Gửi một yêu cầu thử nghiệm thực tế tới Cloudflare Workers AI để kiểm tra kết nối và token.</p>
              <button class="btn btn-primary w-full" <?=$cfCfg['configured']?'':'disabled'?>>Kiểm tra kết nối Cloudflare (Health Check)</button>
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

      <!-- Quick Key Config Form -->
      <div class="card" style="margin-top:1.5rem;padding:1.5rem;background:#fff;border-radius:12px;border:1px solid #e2e8f0;">
        <h3 style="margin-top:0;margin-bottom:0.5rem;font-size:1.1rem;display:flex;align-items:center;gap:8px;">
          <span>🔑</span> Cập nhật Khóa API & Cấu hình cho Trợ lý La Bàn
        </h3>
        <p class="muted" style="font-size:0.875rem;margin-bottom:1.25rem;">
          Bạn có thể nhập trực tiếp khóa API tại đây. Dữ liệu sẽ được lưu an toàn vào cơ sở dữ liệu hệ thống (bảng <code>settings</code>) và có hiệu lực ngay lập tức mà không cần tạo file <code>.env</code> trên hosting production.
        </p>

        <form method="post">
          <?=csrf_field()?>
          <input type="hidden" name="intent" value="save_keys">

          <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1.25rem;margin-bottom:1.25rem;">
            <!-- Groq Settings -->
            <div style="padding:1rem;background:#f8fafc;border-radius:8px;border:1px solid #f1f5f9;">
              <h4 style="margin:0 0 0.75rem;font-size:0.95rem;color:#1e293b;">⚡ Groq API (Ưu tiên chính)</h4>
              <div class="form-group" style="margin-bottom:0.75rem;">
                <label class="form-label" style="font-size:0.8125rem;">Groq API Key (Tên biến: <code>GROQ_API_KEY</code>, bắt đầu bằng <code>gsk_...</code>):</label>
                <input class="input input-sm w-full" type="password" name="groq_api_key" placeholder="<?= $groqCfg['configured'] ? '•••••••••••••••••••• (Đã cấu hình, để trống nếu không đổi)' : 'Dán gsk_... vào đây' ?>">
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" style="font-size:0.8125rem;">Groq Model (Tên biến: <code>GROQ_MODEL</code>):</label>
                <input class="input input-sm w-full" type="text" name="groq_model" value="<?= e($groqCfg['model']) ?>">
              </div>
            </div>

            <!-- Cloudflare Settings -->
            <div style="padding:1rem;background:#f8fafc;border-radius:8px;border:1px solid #f1f5f9;">
              <h4 style="margin:0 0 0.75rem;font-size:0.95rem;color:#1e293b;">☁️ Cloudflare Workers AI (Dự phòng)</h4>
              <div class="form-group" style="margin-bottom:0.75rem;">
                <label class="form-label" style="font-size:0.8125rem;">Cloudflare Account ID (Tên biến: <code>CLOUDFLARE_ACCOUNT_ID</code> — chính xác 32 ký tự hex):</label>
                <input class="input input-sm w-full" type="text" name="cloudflare_account_id" value="<?= e($cfCfg['account']) ?>" placeholder="Ví dụ: d84a91dfcde880b6b32ebb97c0ca5123 (32 ký tự)">
              </div>
              <div class="form-group" style="margin-bottom:0.75rem;">
                <label class="form-label" style="font-size:0.8125rem;">Cloudflare API Token (Tên biến: <code>CLOUDFLARE_API_TOKEN</code>):</label>
                <input class="input input-sm w-full" type="password" name="cloudflare_api_token" placeholder="<?= $cfCfg['configured'] ? '•••••••••••••••••••• (Đã cấu hình, để trống nếu không đổi)' : 'Dán API Token vào đây' ?>">
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" style="font-size:0.8125rem;">Cloudflare Model (Tên biến: <code>CLOUDFLARE_MODEL</code>):</label>
                <input class="input input-sm w-full" type="text" name="cloudflare_model" value="<?= e($cfCfg['model']) ?>">
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary" style="padding:8px 24px;">💾 Lưu cấu hình API Key vào CSDL</button>
        </form>
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
          <small class="muted">Độ trễ TB: <?=$stats['groq_latency_avg']?>ms</small>
        </div>
        <div class="stat-card">
          <span>Failover sang Cloudflare</span>
          <strong style="color:#f59e0b;"><?=number_format((int)$stats['cloudflare_count'])?></strong>
          <small class="muted">Độ trễ TB: <?=$stats['cloudflare_latency_avg']?>ms</small>
        </div>
        <div class="stat-card">
          <span>Phản hồi Fallback</span>
          <strong style="color:<?=($stats['fallback_count']>0?'#ef4444':'#10b981')?>;"><?=number_format((int)$stats['fallback_count'])?></strong>
          <div class="status-breakdown">
            <?php if($stats['fallback_count'] === 0):?>
              <span style="color:#10b981;">Chưa có phản hồi lỗi/fallback</span>
            <?php else:?>
              <span>Phân loại nguyên nhân:</span><br>
              • Chưa cấu hình: <b><?= (int)($stats['fallback_reasons']['unconfigured'] ?? 0) ?></b><br>
              • Quá hạn mức (429): <b><?= (int)($stats['fallback_reasons']['quota_exceeded'] ?? 0) ?></b><br>
              • Lỗi xác thực (401/403): <b><?= (int)($stats['fallback_reasons']['auth_error'] ?? 0) ?></b><br>
              • Timeout / Mạng: <b><?= (int)($stats['fallback_reasons']['timeout'] ?? 0) ?></b><br>
              • Khác: <b><?= (int)(($stats['fallback_reasons']['other'] ?? 0) + ($stats['fallback_reasons']['server_error'] ?? 0) + ($stats['fallback_reasons']['circuit_breaker_active'] ?? 0)) ?></b>
            <?php endif;?>
          </div>
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
                <th>Trạng thái chi tiết</th>
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
                      <?php 
                        $r = $log['fallback_reason'] ?? '';
                        if ($r === 'unconfigured') {
                            $rLabel = 'Chưa cấu hình API';
                        } elseif ($r === 'quota_exceeded') {
                            $rLabel = 'Quá hạn mức (429)';
                        } elseif ($r === 'auth_error') {
                            $rLabel = 'Lỗi xác thực (401/403)';
                        } elseif ($r === 'timeout') {
                            $rLabel = 'Timeout kết nối';
                        } elseif ($r === 'circuit_breaker_active') {
                            $rLabel = 'Ngắt mạch (Cooldown)';
                        } else {
                            $rLabel = 'Fallback (' . e($log['error'] ?: 'Lỗi') . ')';
                        }
                      ?>
                      <span class="status status-off"><?=e($rLabel)?></span>
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
