<?php
declare(strict_types=1);
require_login();
require_once __DIR__ . '/four-hubs-ai.php';
require_once __DIR__ . '/user-roadmap.php';

$userId = (int)user()['id'];

// Handle POST actions on user roadmap
if (is_post() && $page === 'ai-roadmap') {
    $action = $_POST['action'] ?? '';
    if ($action !== '') {
        verify_csrf();
        $roadmap = user_roadmap_get_or_create($userId);
        $roadmapId = (int)$roadmap['id'];
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        try {
            if ($action === 'update_goal') {
                user_roadmap_update_details($roadmapId, $userId, $_POST);
                flash('success', 'Đã cập nhật mục tiêu và hướng đi của bạn.');
                redirect('index.php?page=ai-roadmap#user-roadmap');
            } elseif ($action === 'add_milestone') {
                $newId = user_roadmap_add_milestone($roadmapId, $userId, $_POST);
                if ($isAjax) {
                    $milestones = user_roadmap_get_milestones($roadmapId, $userId);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok', 'id' => $newId, 'progress' => user_roadmap_calculate_progress($milestones)]);
                    exit;
                }
                flash('success', 'Đã thêm cột mốc mới vào hành trình cá nhân.');
                redirect('index.php?page=ai-roadmap#user-roadmap');
            } elseif ($action === 'toggle_milestone') {
                $mid = (int)($_POST['milestone_id'] ?? 0);
                $res = user_roadmap_toggle_milestone($mid, $userId);
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok', 'data' => $res]);
                    exit;
                }
                flash('success', $res['is_completed'] ? 'Đã hoàn thành cột mốc!' : 'Đã chuyển cột mốc về trạng thái đang làm.');
                redirect('index.php?page=ai-roadmap#user-roadmap');
            } elseif ($action === 'delete_milestone') {
                $mid = (int)($_POST['milestone_id'] ?? 0);
                user_roadmap_delete_milestone($mid, $userId);
                if ($isAjax) {
                    $milestones = user_roadmap_get_milestones($roadmapId, $userId);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok', 'progress' => user_roadmap_calculate_progress($milestones)]);
                    exit;
                }
                flash('success', 'Đã xóa cột mốc khỏi hành trình.');
                redirect('index.php?page=ai-roadmap#user-roadmap');
            } elseif ($action === 'apply_all_suggestions') {
                $insights = user_roadmap_derive_assessment_insights($userId);
                $count = 0;
                foreach ($insights['recommended_milestones'] as $rm) {
                    user_roadmap_add_milestone($roadmapId, $userId, $rm);
                    $count++;
                }
                flash('success', "Đã thêm $count cột mốc gợi ý từ bài kiểm tra vào hành trình của bạn.");
                redirect('index.php?page=ai-roadmap#user-roadmap');
            } elseif ($action === 'ai_suggest_milestones') {
                $aiData = user_roadmap_ai_generate_suggestions($userId, $roadmap);
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok', 'data' => $aiData]);
                    exit;
                }
                $_SESSION['roadmap_ai_suggestions'] = $aiData;
                flash('success', 'AI đã phân tích và đề xuất 3 cột mốc hành động phù hợp.');
                redirect('index.php?page=ai-roadmap#ai-copilot-section');
            }
        } catch (Throwable $e) {
            if ($isAjax) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit;
            }
            flash('error', $e->getMessage());
            redirect('index.php?page=ai-roadmap#user-roadmap');
        }
    }
}

try {
    $scenario = career_conversation_select(is_post() ? ($_POST['scenario'] ?? '') : ($_GET['scenario'] ?? ''));
} catch (InvalidArgumentException $error) {
    http_response_code(404); exit(e($error->getMessage()));
}
$chatPath = 'index.php?page=ai-roadmap'.($scenario ? '&scenario='.rawurlencode($scenario['id']) : '').'#career-chat';

if ($page === 'ai-chat') {
    if (!is_post()) redirect($chatPath);
    verify_csrf();
    $message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
    if ($message === '' || mb_strlen($message) > 2000) {
        flash('error', 'Hãy nhập câu hỏi từ 1 đến 2.000 ký tự.');
        redirect($chatPath);
    }
    $nonce = is_string($_POST['chat_nonce'] ?? null) ? $_POST['chat_nonce'] : '';
    if (!$nonce || !hash_equals($_SESSION['four_hub_chat_nonce'] ?? '', $nonce)) {
        flash('warning', 'Câu hỏi đã được gửi hoặc phiên nhập đã thay đổi. Hãy kiểm tra hội thoại trước khi gửi lại.');
        redirect($chatPath);
    }
    $context = $scenario && empty($_POST['include_profile'])
        ? ['holland'=>null, 'suggested_careers'=>[], 'experiences'=>[]]
        : four_hub_ai_context($userId);
    if ($scenario) $context['career_conversation'] = $scenario;
    $context['website_help'] = career_website_help($message);
    try {
        $messageId = four_hub_reserve_chat($userId, $message, $context);
        unset($_SESSION['four_hub_chat_nonce']);
        $answer = four_hub_chat_answer($userId, $messageId, $context, $message);
        $s = db()->prepare("INSERT INTO ai_chat_messages(user_id,role,message,model,latency_ms,created_at,context_snapshot) VALUES(?,'assistant',?,?,?,?,?)");
        $s->execute([$userId, $answer['message'], $answer['model'], $answer['latency_ms'], date('Y-m-d H:i:s'), json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)]);
    } catch (Throwable $error) {
        flash('error', $error->getCode() === 429 ? $error->getMessage() : 'Chưa thể hoàn tất câu trả lời. Kiểm tra lịch sử trước khi thử lại; lượt đã gửi vẫn được tính trong hạn mức.');
    }
    redirect($chatPath);
}

// Data loading
$context = four_hub_ai_context($userId);
$used = four_hub_chat_count($userId);
$messages = career_conversation_history($userId, $scenario);
if (empty($_SESSION['four_hub_chat_nonce'])) $_SESSION['four_hub_chat_nonce'] = bin2hex(random_bytes(24));
$primaryCareer = $context['suggested_careers'][0] ?? null;

// User custom roadmap & assessment insights
$roadmap = user_roadmap_get_or_create($userId);
$roadmapId = (int)$roadmap['id'];
$groupedMilestones = $roadmap['milestones'];
$progress = $roadmap['progress'];
$assessmentInsights = user_roadmap_derive_assessment_insights($userId);
$libraryMilestones = user_roadmap_library_milestones();
$stages = user_roadmap_stages();

$pageTitle = 'AI & Bản đồ hành trình cá nhân';
require __DIR__ . '/header.php';
?>
<section class="page-head no-print">
    <div class="container">
        <span class="eyebrow">Trung tâm 4 · Kết nối hiểu biết với hành động</span>
        <h1>AI & Bản đồ hành trình cá nhân</h1>
        <p>Học sinh tự làm chủ lộ trình: đặt mục tiêu riêng, nhận gợi ý từ các bài kiểm tra và chủ động nhờ AI cố vấn khi cần.</p>
    </div>
</section>

<div class="container">

<!-- KHỐI 1: TỔNG HỢP HỒ SƠ ĐA CHIỀU (GIỮ BẢO TOÀN KIẾN TRÚC) -->
<section class="card no-print" aria-labelledby="profile-summary">
    <h2 id="profile-summary">Hồ sơ đa chiều của bạn</h2>
    <div class="grid-3">
        <div>
            <h3>Holland (RIASEC)</h3>
            <p><strong><?=e($context['holland']['code'] ?? 'Chưa có kết quả')?></strong></p>
            <a href="<?=url('index.php?page=assessment-report')?>">Xem báo cáo đánh giá →</a>
        </div>
        <div>
            <h3>Tính cách & Thích ứng</h3>
            <p>MBTI: <?=e($context['psychometrics']['mbti']['code'] ?? 'Chưa thực hiện')?></p>
            <p>Big Five: <?=isset($context['psychometrics']['big5'])?'Đã có hồ sơ khoa học':'Chưa thực hiện'?></p>
            <p>EQ: <?=isset($context['psychometrics']['eq'])?'Đã có hồ sơ tự nhận xét':'Chưa thực hiện'?></p>
        </div>
        <div>
            <h3>Bằng chứng trải nghiệm</h3>
            <p><?=count($context['experiences'])?> trải nghiệm gần nhất được tổng hợp (tối đa 5).</p>
            <a href="<?=url('index.php?page=simulations')?>">Mở nhật ký trải nghiệm →</a>
        </div>
    </div>
    <p class="muted">Các kết quả chỉ hỗ trợ khám phá, không phải chẩn đoán hay thước đo giá trị của bạn. Trợ lý không tự ý suy đoán khi chưa có dữ liệu quan sát.</p>
</section>

<!-- KHỐI 2: SUY RA TỪ KẾT QUẢ CÁC BÀI ĐÁNH GIÁ TRƯỚC (YÊU CẦU CỐT LÕI) -->
<section id="assessment-insights" class="card no-print" style="border: 2px solid #e0e7ff; background: #fafbff;" aria-labelledby="insights-title">
    <div class="flex flex-wrap items-center justify-between gap-3" style="margin-bottom: 16px;">
        <div>
            <span class="eyebrow" style="color: #4f46e5;">Đặc tính cá nhân hóa từ bài đánh giá</span>
            <h2 id="insights-title" style="margin: 4px 0 0;">🧭 Phân tích & Gợi ý từ các bài kiểm tra của bạn</h2>
        </div>
        <div>
            <span class="badge" style="background: #eef2ff; color: #4338ca; font-weight: 600; padding: 6px 12px; border-radius: 9999px;">
                Đã hoàn thành <?= $assessmentInsights['tests_count'] ?> dữ liệu đánh giá
            </span>
        </div>
    </div>

    <div class="grid-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; margin-bottom: 20px;">
        <div style="background: #fff; padding: 18px; border-radius: 12px; border: 1px solid #e2e8f0;">
            <h3 style="font-size: 1.05rem; color: #1e293b; margin-top: 0;">✨ Điểm mạnh & Thiên hướng nhận diện</h3>
            <?php if (!empty($assessmentInsights['strengths'])): ?>
                <ul style="padding-left: 20px; margin: 8px 0; color: #334155; line-height: 1.6;">
                    <?php foreach ($assessmentInsights['strengths'] as $s): ?>
                        <li><?= e($s) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted">Chưa có kết quả kiểm tra. Hãy làm bài đánh giá để phát hiện điểm mạnh tự nhiên.</p>
            <?php endif; ?>
        </div>

        <div style="background: #fff; padding: 18px; border-radius: 12px; border: 1px solid #e2e8f0;">
            <h3 style="font-size: 1.05rem; color: #1e293b; margin-top: 0;">⚠️ Vùng cần lưu ý & Ranh giới thực tế</h3>
            <?php if (!empty($assessmentInsights['growth_areas'])): ?>
                <ul style="padding-left: 20px; margin: 8px 0; color: #334155; line-height: 1.6;">
                    <?php foreach ($assessmentInsights['growth_areas'] as $g): ?>
                        <li><?= e($g) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted">Hồ sơ cân bằng hoặc chưa hoàn thành khảo sát Cổng đánh đổi.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Gợi ý nghề mục tiêu từ bài đánh giá -->
    <?php if (!empty($assessmentInsights['suggested_careers'])): ?>
    <div style="background: #f1f5f9; padding: 16px; border-radius: 12px; margin-bottom: 20px;">
        <h4 style="margin: 0 0 10px; font-size: 0.95rem; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;">Nghề tương thích từ kết quả đánh giá (Bấm để chọn làm mục tiêu):</h4>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <?php foreach ($assessmentInsights['suggested_careers'] as $c): ?>
                <button type="button" class="btn btn-outline apply-career-goal" data-career-title="<?= e($c['title']) ?>" style="background: #fff; font-size: 0.9rem;">
                    🎯 <?= e($c['title']) ?> (Độ khớp: <?= (int)$c['match_percent'] ?>%)
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Các cột mốc đề xuất tương ứng với điểm mạnh / rào cản -->
    <div>
        <div class="flex flex-wrap items-center justify-between gap-2" style="margin-bottom: 12px;">
            <h3 style="font-size: 1.05rem; margin: 0; color: #1e293b;">📋 Cột mốc hành động gợi ý từ bài kiểm tra:</h3>
            <?php if (!empty($assessmentInsights['recommended_milestones'])): ?>
            <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin: 0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="apply_all_suggestions">
                <button type="submit" class="btn btn-primary" style="font-size: 0.85rem; padding: 6px 14px;">
                    📥 Thêm tất cả gợi ý vào hành trình của tôi
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php if (!empty($assessmentInsights['recommended_milestones'])): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px;">
                <?php foreach ($assessmentInsights['recommended_milestones'] as $rm): ?>
                    <div style="background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 14px; display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <span class="badge" style="background: #f8fafc; border: 1px solid #e2e8f0; font-size: 0.75rem; color: #64748b; margin-bottom: 6px; display: inline-block;">
                                <?= e($stages[$rm['stage']]['title'] ?? $rm['stage']) ?>
                            </span>
                            <h4 style="margin: 4px 0 8px; font-size: 0.95rem; color: #0f172a;"><?= e($rm['title']) ?></h4>
                            <p style="margin: 0; font-size: 0.85rem; color: #475569; line-height: 1.5;"><?= e($rm['description']) ?></p>
                        </div>
                        <div style="margin-top: 12px; display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                            <?php if (!empty($rm['action_url'])): ?>
                                <a href="<?= url($rm['action_url']) ?>" class="btn btn-outline" style="font-size: 0.8rem; padding: 4px 10px;">Thực hiện →</a>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin: 0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add_milestone">
                                <input type="hidden" name="stage" value="<?= e($rm['stage']) ?>">
                                <input type="hidden" name="title" value="<?= e($rm['title']) ?>">
                                <input type="hidden" name="description" value="<?= e($rm['description']) ?>">
                                <input type="hidden" name="action_url" value="<?= e($rm['action_url']) ?>">
                                <input type="hidden" name="source" value="assessment">
                                <button type="submit" class="btn btn-outline" style="font-size: 0.8rem; padding: 4px 10px; color: #4338ca; border-color: #c7d2fe;">
                                    + Thêm vào hành trình
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="muted">Chưa có đề xuất cụ thể. Hãy hoàn thành các bài trắc nghiệm để nhận gợi ý chuẩn.</p>
        <?php endif; ?>
    </div>
</section>

<!-- KHỐI 3: HÀNH TRÌNH TỰ CHỦ CỦA BẠN (USER-AUTHORED CAREER ROADMAP) -->
<section id="user-roadmap" class="card roadmap-print" aria-labelledby="roadmap-main-title" style="margin-top: 24px;">
    <!-- Tiêu đề & Thông tin Hành trình -->
    <div class="flex flex-wrap items-center justify-between gap-3" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 18px;">
        <div>
            <span class="eyebrow">Bản đồ hành trình tự chủ của bạn</span>
            <h2 id="roadmap-main-title" style="margin: 4px 0 0;"><?= e(user()['name']) ?> · <?= e($roadmap['target_timeframe']) ?></h2>
            <p style="margin: 6px 0 0; font-size: 1.05rem; color: #334155;">
                🎯 <strong>Mục tiêu:</strong> <span id="display-goal-title"><?= e($roadmap['goal_title']) ?></span>
                <?php if (!empty($roadmap['target_career_title'])): ?>
                    <span style="color: #64748b;">· Nghề hướng tới: <strong style="color: #4f46e5;"><?= e($roadmap['target_career_title']) ?></strong></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-2 no-print">
            <button type="button" class="btn btn-outline" id="toggle-goal-edit-btn">✏️ Sửa mục tiêu</button>
            <button type="button" class="btn btn-outline" id="print-roadmap">🖨️ Xuất PDF / In</button>
        </div>
    </div>

    <!-- Form Chỉnh sửa Mục tiêu (Ẩn/Hiện) -->
    <div id="goal-edit-container" class="no-print" style="display: none; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 18px; margin-bottom: 24px;">
        <h3 style="margin-top: 0; font-size: 1.05rem;">Chỉnh sửa mục tiêu & hướng đi của bạn</h3>
        <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_goal">
            <div class="grid-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">
                <div class="form-group">
                    <label for="input-goal-title"><strong>Mục tiêu lớn của bạn</strong></label>
                    <input class="input" id="input-goal-title" name="goal_title" value="<?= e($roadmap['goal_title']) ?>" required maxlength="255">
                </div>
                <div class="form-group">
                    <label for="input-career-title"><strong>Nghề đích / Lĩnh vực quan tâm</strong></label>
                    <input class="input" id="input-career-title" name="target_career_title" value="<?= e($roadmap['target_career_title']) ?>" placeholder="Ví dụ: Lập trình viên, Giáo viên, Dữ liệu..." maxlength="200">
                </div>
            </div>
            <div class="grid-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-top: 10px;">
                <div class="form-group">
                    <label for="input-timeframe"><strong>Giai đoạn / Khung thời gian</strong></label>
                    <input class="input" id="input-timeframe" name="target_timeframe" value="<?= e($roadmap['target_timeframe']) ?>" placeholder="Ví dụ: Lớp 10 - 12, Năm 2026-2027..." maxlength="100">
                </div>
                <div class="form-group">
                    <label for="input-notes"><strong>Ghi chú / Động lực cá nhân</strong></label>
                    <input class="input" id="input-notes" name="notes" value="<?= e($roadmap['notes']) ?>" placeholder="Ghi chú điều bạn muốn đạt được..." maxlength="2000">
                </div>
            </div>
            <div style="margin-top: 14px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Lưu mục tiêu</button>
                <button type="button" class="btn btn-outline" id="cancel-goal-edit-btn">Hủy</button>
            </div>
        </form>
    </div>

    <!-- Thanh tiến độ tổng quan -->
    <div style="margin-bottom: 24px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px;">
        <div class="flex flex-wrap items-center justify-between gap-2" style="margin-bottom: 8px;">
            <span style="font-weight: 700; color: #1e293b; font-size: 1.05rem;">Tiến độ thực hiện hành trình:</span>
            <span id="progress-text" style="font-weight: 700; color: #4f46e5; font-size: 1.1rem;">
                <?= $progress['percent'] ?>% (Hoàn thành <?= $progress['completed'] ?> / <?= $progress['total'] ?> cột mốc)
            </span>
        </div>
        <div style="width: 100%; height: 12px; background: #e2e8f0; border-radius: 9999px; overflow: hidden;">
            <div id="progress-bar-fill" style="width: <?= $progress['percent'] ?>%; height: 100%; background: linear-gradient(90deg, #4f46e5, #10b981); transition: width 0.3s ease;"></div>
        </div>
    </div>

    <!-- KHỐI AI CỐ VẤN HÀNH TRÌNH (CHỈ KÍCH HOẠT KHI USER CHỦ ĐỘNG BẤM NÚT AI) -->
    <div class="no-print" style="margin-bottom: 28px;">
        <div style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border: 1px solid #ddd6fe; border-radius: 14px; padding: 18px;">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 1.8rem;">✨</span>
                    <div>
                        <h3 style="margin: 0; font-size: 1.1rem; color: #4c1d95;">Trợ lý AI Cố Vấn Hành Trình</h3>
                        <p style="margin: 4px 0 0; font-size: 0.9rem; color: #6b21a8;">
                            AI chỉ hỗ trợ khi bạn chủ động yêu cầu. Phân tích mục tiêu và đồng hành giải đáp mọi thắc mắc.
                        </p>
                    </div>
                </div>
                <button type="button" class="btn" id="toggle-ai-copilot-btn" style="background: #7c3aed; color: #fff; font-weight: 600; padding: 10px 18px; border-radius: 10px; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);">
                    ✨ Mở Trợ Lý AI Lộ Trình
                </button>
            </div>

            <!-- Panel AI On-Demand (Ẩn mặc định, chỉ mở khi user bấm nút) -->
            <div id="ai-copilot-panel" style="display: none; margin-top: 18px; padding-top: 18px; border-top: 1px dashed #c4b5fd;">
                <div class="grid-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                    <!-- Option 1: AI Gợi ý Cột mốc thông minh -->
                    <div style="background: #fff; padding: 16px; border-radius: 10px; border: 1px solid #ddd6fe;">
                        <h4 style="margin: 0 0 8px; font-size: 1rem; color: #5b21b6;">1. Sinh cột mốc hành động thông minh</h4>
                        <p style="margin: 0 0 12px; font-size: 0.85rem; color: #64748b;">
                            AI sẽ đọc mục tiêu hiện tại (<strong><?= e($roadmap['target_career_title'] ?: $roadmap['goal_title']) ?></strong>) cùng hồ sơ đánh giá để sinh 3 cột mốc thực tế nhất.
                        </p>
                        <button type="button" class="btn btn-outline" id="btn-request-ai-milestones" style="border-color: #8b5cf6; color: #6d28d9; font-size: 0.9rem;">
                            ✨ Nhờ AI gợi ý 3 cột mốc mới
                        </button>
                        <div id="ai-milestones-loading" style="display: none; margin-top: 10px; font-size: 0.85rem; color: #6d28d9;">
                            ⏳ Đang phân tích hồ sơ và tạo cột mốc phù hợp...
                        </div>
                        <div id="ai-milestones-result" style="margin-top: 14px;"></div>
                    </div>

                    <!-- Option 2: Hỏi nhanh AI về lộ trình -->
                    <div style="background: #fff; padding: 16px; border-radius: 10px; border: 1px solid #ddd6fe;">
                        <h4 style="margin: 0 0 8px; font-size: 1rem; color: #5b21b6;">2. Hỏi đáp nhanh về lộ trình học tập</h4>
                        <p style="margin: 0 0 12px; font-size: 0.85rem; color: #64748b;">
                            Hỏi về môn học cần chú trọng, phân bổ thời gian hoặc cách vượt qua rào cản.
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <button type="button" class="btn btn-outline quick-ai-ask" data-ask="Với mục tiêu <?= e($roadmap['target_career_title'] ?: 'nghề này') ?>, tôi nên tập trung vào môn học nào trước?" style="font-size: 0.75rem; padding: 4px 8px;">
                                    Nên tập trung môn gì?
                                </button>
                                <button type="button" class="btn btn-outline quick-ai-ask" data-ask="Làm thế nào để biết lộ trình này có quá sức với tôi không?" style="font-size: 0.75rem; padding: 4px 8px;">
                                    Lộ trình có quá sức?
                                </button>
                            </div>
                            <div style="margin-top: 8px;">
                                <a href="#career-chat" class="btn btn-outline" style="font-size: 0.85rem; width: 100%; text-align: center;">
                                    💬 Chuyển xuống khung trò chuyện AI chi tiết ↓
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DANH SÁCH CỘT MỐC THEO 4 GIAI ĐOẠN (CHECKLIST VÀ THƯ VIỆN GỢI Ý) -->
    <div class="roadmap-stages-container">
        <?php foreach ($stages as $stageKey => $stageMeta):
            $stageData = $groupedMilestones[$stageKey] ?? ['items' => [], 'completed_count' => 0, 'total_count' => 0];
            $items = $stageData['items'];
            $libItems = $libraryMilestones[$stageKey] ?? [];
        ?>
        <div class="stage-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 22px;">
            <div class="flex flex-wrap items-center justify-between gap-2" style="border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 1.5rem;"><?= $stageMeta['icon'] ?></span>
                    <div>
                        <h3 style="margin: 0; font-size: 1.15rem; color: #0f172a;"><?= e($stageMeta['title']) ?></h3>
                        <p style="margin: 2px 0 0; font-size: 0.85rem; color: #64748b;"><?= e($stageMeta['summary']) ?></p>
                    </div>
                </div>
                <div>
                    <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.85rem; padding: 4px 10px; border-radius: 9999px;">
                        <?= $stageData['completed_count'] ?> / <?= $stageData['total_count'] ?> hoàn thành
                    </span>
                </div>
            </div>

            <!-- Danh sách Cột mốc Hiện tại của User -->
            <div class="milestones-list" id="stage-milestones-<?= $stageKey ?>" style="margin-bottom: 16px;">
                <?php if (empty($items)): ?>
                    <div class="muted empty-stage-note" style="padding: 12px 0; font-size: 0.9rem; font-style: italic;">
                        Chưa có cột mốc nào trong giai đoạn này. Hãy chọn từ gợi ý bên dưới hoặc tự thêm cột mốc riêng.
                    </div>
                <?php else: ?>
                    <?php foreach ($items as $m): ?>
                    <div class="milestone-item <?= $m['is_completed'] ? 'is-completed' : '' ?>" id="milestone-row-<?= $m['id'] ?>" style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 12px 14px; margin-bottom: 10px; border-radius: 8px; border: 1px solid <?= $m['is_completed'] ? '#bbf7d0' : '#e2e8f0' ?>; background: <?= $m['is_completed'] ? '#f0fdf4' : '#fafafa' ?>;">
                        <div style="display: flex; align-items: flex-start; gap: 12px; flex: 1;">
                            <!-- Checkbox toggle -->
                            <input type="checkbox" class="milestone-toggle-check" data-id="<?= $m['id'] ?>" <?= $m['is_completed'] ? 'checked' : '' ?> style="width: 20px; height: 20px; margin-top: 3px; cursor: pointer; accent-color: #10b981;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <strong class="milestone-title-text" style="font-size: 0.95rem; color: <?= $m['is_completed'] ? '#15803d' : '#1e293b' ?>; <?= $m['is_completed'] ? 'text-decoration: line-through;' : '' ?>">
                                        <?= e($m['title']) ?>
                                    </strong>
                                    <!-- Source Badge -->
                                    <?php if ($m['source'] === 'assessment'): ?>
                                        <span class="badge" style="background: #e0e7ff; color: #4338ca; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px;">Từ đánh giá</span>
                                    <?php elseif ($m['source'] === 'ai'): ?>
                                        <span class="badge" style="background: #f5f3ff; color: #7c3aed; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px;">✨ AI gợi ý</span>
                                    <?php elseif ($m['source'] === 'custom'): ?>
                                        <span class="badge" style="background: #fef3c7; color: #b45309; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px;">Tự lập</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #f1f5f9; color: #64748b; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px;">Gợi ý mẫu</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($m['description'])): ?>
                                    <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b; line-height: 1.5;"><?= e($m['description']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="no-print" style="display: flex; align-items: center; gap: 8px;">
                            <?php if (!empty($m['action_url'])): ?>
                                <a href="<?= url($m['action_url']) ?>" class="btn btn-outline" style="font-size: 0.75rem; padding: 3px 8px; white-space: nowrap;">
                                    Mở →
                                </a>
                            <?php endif; ?>
                            <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin: 0;" onsubmit="return confirm('Bạn có chắc muốn xóa cột mốc này?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_milestone">
                                <input type="hidden" name="milestone_id" value="<?= $m['id'] ?>">
                                <button type="submit" class="btn btn-outline" style="font-size: 0.75rem; padding: 3px 8px; color: #ef4444; border-color: #fca5a5;" title="Xóa cột mốc">
                                    ✕
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Form Tự Thêm Cột Mốc Mới (Người dùng tự đưa ra hành trình) -->
            <details class="no-print" style="margin-top: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px;">
                <summary style="font-weight: 600; font-size: 0.9rem; color: #2563eb; cursor: pointer;">
                    + Tự viết cột mốc riêng của bạn trong giai đoạn này
                </summary>
                <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin-top: 12px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_milestone">
                    <input type="hidden" name="stage" value="<?= $stageKey ?>">
                    <input type="hidden" name="source" value="custom">
                    <div class="form-group" style="margin-bottom: 10px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Tên nhiệm vụ / Cột mốc:</label>
                        <input class="input" name="title" required maxlength="255" placeholder="Ví dụ: Ôn tập 5 đề thi môn Toán, phỏng vấn một người đi trước...">
                    </div>
                    <div class="form-group" style="margin-bottom: 10px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Mô tả / Hướng dẫn tự đặt:</label>
                        <textarea class="input" name="description" rows="2" maxlength="2000" placeholder="Chi tiết cách bạn sẽ thực hiện nhiệm vụ này..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="font-size: 0.85rem; padding: 6px 14px;">
                        Lưu vào hành trình
                    </button>
                </form>
            </details>

            <!-- Thư Viện Cột Mốc Mẫu Từ Website (Website Suggests Milestones) -->
            <details class="no-print" style="margin-top: 10px; background: #fff; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 10px 14px;">
                <summary style="font-weight: 600; font-size: 0.85rem; color: #475569; cursor: pointer;">
                    💡 Xem gợi ý mẫu từ website cho giai đoạn này (<?= count($libItems) ?> gợi ý)
                </summary>
                <div style="margin-top: 12px; display: flex; flex-direction: column; gap: 8px;">
                    <?php foreach ($libItems as $lib): ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; background: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px solid #f1f5f9;">
                        <div>
                            <strong style="font-size: 0.85rem; color: #334155;"><?= e($lib['title']) ?></strong>
                            <p style="margin: 2px 0 0; font-size: 0.8rem; color: #64748b;"><?= e($lib['description']) ?></p>
                        </div>
                        <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin: 0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_milestone">
                            <input type="hidden" name="stage" value="<?= $stageKey ?>">
                            <input type="hidden" name="title" value="<?= e($lib['title']) ?>">
                            <input type="hidden" name="description" value="<?= e($lib['description']) ?>">
                            <input type="hidden" name="action_url" value="<?= e($lib['action_url']) ?>">
                            <input type="hidden" name="source" value="suggested">
                            <button type="submit" class="btn btn-outline" style="font-size: 0.75rem; padding: 4px 8px; white-space: nowrap;">
                                + Chọn
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>
        <?php endforeach; ?>
    </div>

    <p class="muted" style="margin-top: 20px;">
        Bản đồ hành trình là công cụ hướng dẫn cá nhân, có thể điều chỉnh bất kỳ lúc nào theo sự trưởng thành và điều kiện thực tế của bạn. Hãy chia sẻ với giáo viên hoặc gia đình để nhận thêm lời khuyên.
    </p>
</section>

<!-- KHỐI 4: GÓC NGƯỜI TRONG NGHỀ & CHATBOT HƯỚNG NGHIỆP (BẢO TOÀN KIẾN TRÚC & TESTS) -->
<?php require __DIR__ . '/career-conversations-view.php'; ?>

<section id="career-chat" class="card no-print" aria-labelledby="chat-title">
    <h2 id="chat-title"><?= e($scenario ? 'Cùng khám phá: ' . $scenario['title'] : 'Trao đổi với trợ lý hướng nghiệp') ?></h2>
    <p class="muted">
        Khi AI được cấu hình, câu hỏi và hội thoại gần đây trong mục đang chọn được gửi tới nhà cung cấp AI đã cấu hình. Chat thông thường kèm tóm tắt đánh giá/trải nghiệm; chat tình huống chỉ kèm hồ sơ nếu bạn chọn bên dưới. Không có AI thì hệ thống hiển thị gợi ý dự phòng.
    </p>
    <p>
        <strong><?= max(0, 10 - $used) ?> / 10 lượt còn lại hôm nay</strong> · Đặt lại lúc 00:00 theo giờ Việt Nam. Lượt dùng dự phòng vẫn tính vào hạn mức.
    </p>

    <div class="chat-history" aria-label="Lịch sử hội thoại">
        <?php if (!$messages): ?>
            <p class="muted">Bắt đầu bằng một câu hỏi về hồ sơ, lộ trình học tập hoặc bước tiếp theo của bạn.</p>
        <?php endif; ?>
        <?php foreach ($messages as $chat): ?>
            <article class="card chat-message">
                <strong><?= $chat['role'] === 'user' ? 'Bạn' : ($chat['model'] === 'fallback' ? 'Gợi ý dự phòng' : ($chat['model'] === 'unavailable' ? 'Trạng thái AI' : 'Trợ lý AI · ' . e($chat['model']))) ?></strong>
                <small class="muted"> · <?= e($chat['created_at']) ?></small>
                <p style="white-space:pre-wrap;overflow-wrap:anywhere"><?= e($chat['message']) ?></p>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="flex flex-wrap gap-3" aria-label="Câu hỏi gợi ý">
        <?php foreach (($scenario ? career_conversation_prompts($scenario) : ['Tôi nên tập trung môn học nào cho lộ trình này?', 'MBTI và Big Five của tôi có ý nghĩa gì với nghề này?', 'Gợi ý thử thách 15 phút thực tế cho tôi.']) as $chip): ?>
            <button type="button" class="btn btn-outline" data-chat-prompt="<?= e($chip) ?>"><?= e($chip) ?></button>
        <?php endforeach; ?>
    </div>

    <form method="post" action="<?= url('index.php?page=ai-chat') ?>" class="mt-4" id="career-chat-form">
        <?= csrf_field() ?>
        <input type="hidden" name="chat_nonce" value="<?= e($_SESSION['four_hub_chat_nonce']) ?>">
        <input type="hidden" name="scenario" value="<?= e($scenario['id'] ?? '') ?>">
        <?php if ($scenario): ?>
            <p><label><input type="checkbox" name="include_profile" value="1"> Kèm tóm tắt đánh giá/trải nghiệm của tôi để liên hệ với tình huống (tùy chọn).</label></p>
        <?php endif; ?>
        <div class="form-group">
            <label for="career-question">Câu hỏi của bạn</label>
            <textarea class="input" id="career-question" name="message" rows="4" maxlength="2000" required <?= $used >= 10 ? 'disabled' : '' ?>></textarea>
        </div>
        <button class="btn btn-primary" type="submit" <?= $used >= 10 ? 'disabled' : '' ?>>Gửi câu hỏi</button>
        <p id="chat-send-status" role="status"></p>
    </form>
</section>

</div>

<script>
// Print roadmap
document.getElementById('print-roadmap').addEventListener('click', () => window.print());

// Chat prompt helper
document.querySelectorAll('[data-chat-prompt]').forEach(button => {
    button.addEventListener('click', () => {
        const field = document.getElementById('career-question');
        field.value = button.dataset.chatPrompt;
        field.focus();
    });
});

// Chat submit loading
document.getElementById('career-chat-form').addEventListener('submit', function() {
    this.querySelector('button[type=submit]').disabled = true;
    document.getElementById('chat-send-status').textContent = 'Đang tổng hợp hồ sơ và trả lời…';
});

// Toggle Goal Editing
const toggleGoalBtn = document.getElementById('toggle-goal-edit-btn');
const goalContainer = document.getElementById('goal-edit-container');
const cancelGoalBtn = document.getElementById('cancel-goal-edit-btn');
if (toggleGoalBtn && goalContainer) {
    toggleGoalBtn.addEventListener('click', () => {
        goalContainer.style.display = goalContainer.style.display === 'none' ? 'block' : 'none';
        if (goalContainer.style.display === 'block') {
            document.getElementById('input-goal-title').focus();
        }
    });
    if (cancelGoalBtn) {
        cancelGoalBtn.addEventListener('click', () => {
            goalContainer.style.display = 'none';
        });
    }
}

// Quick apply career goal from assessment
document.querySelectorAll('.apply-career-goal').forEach(btn => {
    btn.addEventListener('click', () => {
        const title = btn.dataset.careerTitle;
        if (goalContainer) {
            goalContainer.style.display = 'block';
            document.getElementById('input-career-title').value = title;
            document.getElementById('input-goal-title').value = 'Định hình lộ trình phát triển nghề ' + title;
            goalContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
});

// Toggle AI Copilot Panel on-demand
const toggleAiBtn = document.getElementById('toggle-ai-copilot-btn');
const aiPanel = document.getElementById('ai-copilot-panel');
if (toggleAiBtn && aiPanel) {
    toggleAiBtn.addEventListener('click', () => {
        aiPanel.style.display = aiPanel.style.display === 'none' ? 'block' : 'none';
        toggleAiBtn.textContent = aiPanel.style.display === 'none' ? '✨ Mở Trợ Lý AI Lộ Trình' : '▲ Thu gọn Trợ Lý AI';
    });
}

// Quick ask from AI Copilot to Chat form
document.querySelectorAll('.quick-ai-ask').forEach(btn => {
    btn.addEventListener('click', () => {
        const ask = btn.dataset.ask;
        const field = document.getElementById('career-question');
        field.value = ask;
        const chatSec = document.getElementById('career-chat');
        if (chatSec) chatSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
        field.focus();
    });
});

// Request AI Milestones Generator
const btnAiMilestones = document.getElementById('btn-request-ai-milestones');
const aiLoading = document.getElementById('ai-milestones-loading');
const aiResult = document.getElementById('ai-milestones-result');
if (btnAiMilestones) {
    btnAiMilestones.addEventListener('click', async () => {
        btnAiMilestones.disabled = true;
        if (aiLoading) aiLoading.style.display = 'block';
        if (aiResult) aiResult.innerHTML = '';

        try {
            const csrfToken = document.querySelector('input[name="csrf"]')?.value || '';
            const formData = new FormData();
            formData.append('action', 'ai_suggest_milestones');
            formData.append('csrf', csrfToken);

            const res = await fetch('<?= url('index.php?page=ai-roadmap') ?>', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await res.json();
            if (data.status === 'ok' && data.data && data.data.suggestions) {
                let html = '<div style="margin-top: 10px; display: flex; flex-direction: column; gap: 8px;">';
                data.data.suggestions.forEach(item => {
                    html += `
                        <div style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 8px; padding: 10px; display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                            <div>
                                <strong style="font-size: 0.9rem; color: #581c87;">${item.title}</strong>
                                <p style="margin: 2px 0 0; font-size: 0.8rem; color: #6b21a8;">${item.description}</p>
                            </div>
                            <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin: 0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add_milestone">
                                <input type="hidden" name="stage" value="${item.stage}">
                                <input type="hidden" name="title" value="${item.title}">
                                <input type="hidden" name="description" value="${item.description}">
                                <input type="hidden" name="action_url" value="${item.action_url || ''}">
                                <input type="hidden" name="source" value="ai">
                                <button type="submit" class="btn btn-primary" style="font-size: 0.75rem; padding: 4px 8px; background: #7c3aed; white-space: nowrap;">
                                    + Thêm
                                </button>
                            </form>
                        </div>
                    `;
                });
                html += '</div>';
                if (aiResult) aiResult.innerHTML = html;
            } else {
                if (aiResult) aiResult.innerHTML = '<p class="muted" style="color: #dc2626;">Chưa thể sinh gợi ý từ AI lúc này. Vui lòng thử lại sau.</p>';
            }
        } catch (e) {
            if (aiResult) aiResult.innerHTML = '<p class="muted" style="color: #dc2626;">Đã xảy ra lỗi kết nối. Hãy thử lại.</p>';
        } finally {
            btnAiMilestones.disabled = false;
            if (aiLoading) aiLoading.style.display = 'none';
        }
    });
}

// Milestone Checkbox Toggle via AJAX
document.querySelectorAll('.milestone-toggle-check').forEach(chk => {
    chk.addEventListener('change', async function() {
        const id = this.dataset.id;
        const row = document.getElementById('milestone-row-' + id);
        const titleText = row?.querySelector('.milestone-title-text');
        const csrfToken = document.querySelector('input[name="csrf"]')?.value || '';

        const formData = new FormData();
        formData.append('action', 'toggle_milestone');
        formData.append('milestone_id', id);
        formData.append('csrf', csrfToken);

        try {
            const res = await fetch('<?= url('index.php?page=ai-roadmap') ?>', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const result = await res.json();
            if (result.status === 'ok') {
                const isCompleted = result.data.is_completed;
                if (row) {
                    if (isCompleted) {
                        row.classList.add('is-completed');
                        row.style.background = '#f0fdf4';
                        row.style.borderColor = '#bbf7d0';
                        if (titleText) {
                            titleText.style.color = '#15803d';
                            titleText.style.textDecoration = 'line-through';
                        }
                    } else {
                        row.classList.remove('is-completed');
                        row.style.background = '#fafafa';
                        row.style.borderColor = '#e2e8f0';
                        if (titleText) {
                            titleText.style.color = '#1e293b';
                            titleText.style.textDecoration = 'none';
                        }
                    }
                }
                // Update progress bar
                if (result.data.progress) {
                    const p = result.data.progress;
                    const pText = document.getElementById('progress-text');
                    const pFill = document.getElementById('progress-bar-fill');
                    if (pText) pText.textContent = `${p.percent}% (Hoàn thành ${p.completed} / ${p.total} cột mốc)`;
                    if (pFill) pFill.style.width = `${p.percent}%`;
                }
            } else {
                this.checked = !this.checked;
            }
        } catch (err) {
            this.checked = !this.checked;
        }
    });
});
</script>
<?php require __DIR__ . '/footer.php'; exit;
