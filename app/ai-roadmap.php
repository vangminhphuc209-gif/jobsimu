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

$pageTitle = 'Kế hoạch và Bản đồ hành trình cá nhân';
require __DIR__ . '/header.php';
?>
<section class="page-head no-print">
    <div class="container" style="max-width: var(--max-w-content);">
        <span class="eyebrow">Hành trình của tôi · Kế hoạch cá nhân</span>
        <h1 style="font-size: var(--text-3xl); font-weight: 800; margin: var(--space-2) 0 var(--space-3); color: var(--text);">Kế hoạch và Bản đồ hành trình cá nhân</h1>
        <p style="font-size: var(--text-base); color: var(--muted); max-width: 680px; margin: 0; line-height: var(--leading-relaxed);">
            Học sinh tự làm chủ lộ trình: đặt mục tiêu riêng, theo dõi các mốc hành động và chủ động nhờ AI gợi ý khi cần.
        </p>
    </div>
</section>

<div class="container" style="max-width: var(--max-w-content); margin-top: var(--space-6); margin-bottom: var(--space-12);">

<!-- KHỐI 1 (ƯU TIÊN HÀNG ĐẦU): HÀNH TRÌNH TỰ CHỦ CỦA BẠN (USER-AUTHORED CAREER ROADMAP) -->
<section id="user-roadmap" class="card roadmap-print" aria-labelledby="roadmap-main-title" style="margin-bottom: var(--space-6); border: 1.5px solid var(--border); box-shadow: var(--shadow-sm);">
    <!-- Tiêu đề & Thông tin Hành trình -->
    <div class="flex flex-wrap items-center justify-between gap-3" style="border-bottom: 1px solid var(--border); padding-bottom: var(--space-4); margin-bottom: var(--space-5);">
        <div>
            <span class="eyebrow" style="color: var(--primary);">Kế hoạch hành động cá nhân</span>
            <h2 id="roadmap-main-title" style="margin: var(--space-1) 0 0; font-size: var(--text-2xl); font-weight: 700; color: var(--text);">
                <?= e(user()['name']) ?> · <?= e($roadmap['target_timeframe']) ?>
            </h2>
            <div style="margin-top: var(--space-2); display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap;">
                <span style="font-size: var(--text-base); color: var(--text);">
                    🎯 <strong>Mục tiêu:</strong> <span id="display-goal-title" style="font-weight: 600;"><?= e($roadmap['goal_title']) ?></span>
                </span>
                <?php if (!empty($roadmap['target_career_title'])): ?>
                    <span class="badge" style="background: var(--primary-light); color: var(--primary); font-weight: 600; padding: 3px 8px;">
                        Nghề hướng tới: <?= e($roadmap['target_career_title']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php if (!empty($roadmap['notes'])): ?>
                <p style="margin: var(--space-2) 0 0; font-size: var(--text-sm); color: var(--muted); font-style: italic;">
                    "<?= e($roadmap['notes']) ?>"
                </p>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap gap-2 no-print">
            <button type="button" class="btn btn-outline" id="toggle-goal-edit-btn" style="font-size: var(--text-sm);">✏️ Sửa mục tiêu</button>
            <button type="button" class="btn btn-outline" id="print-roadmap" style="font-size: var(--text-sm);">🖨️ Xuất PDF / In</button>
        </div>
    </div>

    <!-- Form Chỉnh sửa Mục tiêu (Ẩn/Hiện) -->
    <div id="goal-edit-container" class="no-print" style="display: none; background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-md); padding: var(--space-4); margin-bottom: var(--space-6);">
        <h3 style="margin-top: 0; font-size: var(--text-base); font-weight: 700; color: var(--text);">Chỉnh sửa mục tiêu & hướng đi của bạn</h3>
        <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_goal">
            <div class="grid-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: var(--space-4);">
                <div class="form-group">
                    <label for="input-goal-title" style="font-size: var(--text-sm); font-weight: 600;">Mục tiêu lớn của bạn *</label>
                    <input class="input" id="input-goal-title" name="goal_title" value="<?= e($roadmap['goal_title']) ?>" required maxlength="255" style="width: 100%;">
                </div>
                <div class="form-group">
                    <label for="input-career-title" style="font-size: var(--text-sm); font-weight: 600;">Nghề đích / Lĩnh vực quan tâm</label>
                    <input class="input" id="input-career-title" name="target_career_title" value="<?= e($roadmap['target_career_title']) ?>" placeholder="Ví dụ: Lập trình viên, Giáo viên, Bác sĩ..." maxlength="200" style="width: 100%;">
                </div>
            </div>
            <div class="grid-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: var(--space-4); margin-top: var(--space-3);">
                <div class="form-group">
                    <label for="input-timeframe" style="font-size: var(--text-sm); font-weight: 600;">Giai đoạn / Khung thời gian</label>
                    <input class="input" id="input-timeframe" name="target_timeframe" value="<?= e($roadmap['target_timeframe']) ?>" placeholder="Ví dụ: Lớp 10 - 12, Năm 2026-2027..." maxlength="100" style="width: 100%;">
                </div>
                <div class="form-group">
                    <label for="input-notes" style="font-size: var(--text-sm); font-weight: 600;">Ghi chú / Động lực cá nhân</label>
                    <input class="input" id="input-notes" name="notes" value="<?= e($roadmap['notes']) ?>" placeholder="Điều thôi thúc bạn muốn đạt được mục tiêu này..." maxlength="2000" style="width: 100%;">
                </div>
            </div>
            <div style="margin-top: var(--space-4); display: flex; gap: var(--space-2);">
                <button type="submit" class="btn btn-primary" style="font-size: var(--text-sm);">Lưu mục tiêu</button>
                <button type="button" class="btn btn-outline" id="cancel-goal-edit-btn" style="font-size: var(--text-sm);">Hủy</button>
            </div>
        </form>
    </div>

    <!-- Thanh tiến độ tổng quan -->
    <div style="margin-bottom: var(--space-6); background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: var(--space-4);">
        <div class="flex flex-wrap items-center justify-between gap-2" style="margin-bottom: var(--space-2);">
            <span style="font-weight: 700; color: var(--text); font-size: var(--text-base);">Tiến độ thực hiện hành trình:</span>
            <span id="progress-text" style="font-weight: 700; color: var(--primary); font-size: var(--text-base);">
                <?= $progress['percent'] ?>% (Hoàn thành <?= $progress['completed'] ?> / <?= $progress['total'] ?> cột mốc)
            </span>
        </div>
        <div style="width: 100%; height: 10px; background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-full); overflow: hidden;">
            <div id="progress-bar-fill" style="width: <?= $progress['percent'] ?>%; height: 100%; background: var(--primary); transition: width 0.3s ease;"></div>
        </div>
    </div>

    <!-- KHỐI TRỢ LÝ AI CỐ VẤN HÀNH TRÌNH (CHỈ KÍCH HOẠT KHI HỌC SINH CHỦ ĐỘNG YÊU CẦU) -->
    <div id="ai-copilot-section" class="no-print" style="margin-bottom: var(--space-6);">
        <div style="background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-md); padding: var(--space-4);">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div style="display: flex; align-items: center; gap: var(--space-3);">
                    <span style="font-size: 1.5rem;" aria-hidden="true">✨</span>
                    <div>
                        <h3 style="margin: 0; font-size: var(--text-base); font-weight: 700; color: var(--text);">Trợ lý AI Cố Vấn Lộ Trình</h3>
                        <p style="margin: var(--space-1) 0 0; font-size: var(--text-xs); color: var(--muted);">
                            AI chỉ hoạt động khi bạn bấm yêu cầu. Phân tích mục tiêu và gợi ý 3 mốc hành động thực tế tiếp theo.
                        </p>
                    </div>
                </div>
                <button type="button" class="btn btn-outline" id="toggle-ai-copilot-btn" style="border-color: var(--primary); color: var(--primary); font-weight: 600; font-size: var(--text-sm);">
                    ✨ Mở Trợ Lý AI Lộ Trình
                </button>
            </div>

            <!-- Panel AI On-Demand (Ẩn mặc định) -->
            <div id="ai-copilot-panel" style="display: none; margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px dashed var(--border);">
                <div class="grid-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4);">
                    <!-- Option 1: AI Gợi ý Cột mốc thông minh -->
                    <div style="background: var(--surface); padding: var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--border);">
                        <h4 style="margin: 0 0 var(--space-2); font-size: var(--text-sm); font-weight: 700; color: var(--text);">1. Sinh 3 cột mốc hành động theo mục tiêu</h4>
                        <p style="margin: 0 0 var(--space-3); font-size: var(--text-xs); color: var(--muted); line-height: var(--leading-relaxed);">
                            AI phân tích mục tiêu <strong>"<?= e($roadmap['target_career_title'] ?: $roadmap['goal_title']) ?>"</strong> và hồ sơ của bạn để đề xuất 3 bước hành động cụ thể.
                        </p>
                        <button type="button" class="btn btn-outline" id="btn-request-ai-milestones" style="font-size: var(--text-xs); border-color: var(--primary); color: var(--primary);">
                            ✨ Nhờ AI gợi ý 3 cột mốc mới
                        </button>
                        <div id="ai-milestones-loading" style="display: none; margin-top: var(--space-2); font-size: var(--text-xs); color: var(--primary);">
                            ⏳ Đang phân tích hồ sơ và tạo cột mốc phù hợp...
                        </div>
                        <div id="ai-milestones-result" style="margin-top: var(--space-3);"></div>
                    </div>

                    <!-- Option 2: Hỏi nhanh AI về lộ trình -->
                    <div style="background: var(--surface); padding: var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--border);">
                        <h4 style="margin: 0 0 var(--space-2); font-size: var(--text-sm); font-weight: 700; color: var(--text);">2. Hỏi đáp nhanh về lộ trình học tập</h4>
                        <p style="margin: 0 0 var(--space-3); font-size: var(--text-xs); color: var(--muted); line-height: var(--leading-relaxed);">
                            Bấm câu hỏi gợi ý để chuyển nhanh xuống khung trò chuyện cố vấn bên dưới.
                        </p>
                        <div style="display: flex; flex-direction: column; gap: var(--space-2);">
                            <div style="display: flex; gap: var(--space-1); flex-wrap: wrap;">
                                <button type="button" class="btn btn-outline quick-ai-ask" data-ask="Với mục tiêu <?= e($roadmap['target_career_title'] ?: 'nghề này') ?>, tôi nên tập trung vào môn học nào trước?" style="font-size: var(--text-xs); padding: 4px 8px;">
                                    Tập trung môn gì?
                                </button>
                                <button type="button" class="btn btn-outline quick-ai-ask" data-ask="Làm thế nào để biết lộ trình này có vừa sức với học sinh THCS/THPT không?" style="font-size: var(--text-xs); padding: 4px 8px;">
                                    Lộ trình có vừa sức?
                                </button>
                            </div>
                            <div style="margin-top: var(--space-2);">
                                <a href="#career-chat" class="btn btn-outline" style="font-size: var(--text-xs); width: 100%; text-align: center;">
                                    💬 Chuyển xuống khung trò chuyện AI ↓
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
        <div class="stage-card" style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: var(--space-4); margin-bottom: var(--space-4);">
            <div class="flex flex-wrap items-center justify-between gap-2" style="border-bottom: 1px solid var(--border); padding-bottom: var(--space-3); margin-bottom: var(--space-4);">
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <span style="font-size: 1.25rem;" aria-hidden="true"><?= $stageMeta['icon'] ?></span>
                    <div>
                        <h3 style="margin: 0; font-size: var(--text-base); font-weight: 700; color: var(--text);"><?= e($stageMeta['title']) ?></h3>
                        <p style="margin: var(--space-1) 0 0; font-size: var(--text-xs); color: var(--muted);"><?= e($stageMeta['summary']) ?></p>
                    </div>
                </div>
                <div>
                    <span class="badge" style="background: var(--surface-alt); color: var(--text); font-size: var(--text-xs); padding: 4px 8px; border: 1px solid var(--border);">
                        <?= $stageData['completed_count'] ?> / <?= $stageData['total_count'] ?> hoàn thành
                    </span>
                </div>
            </div>

            <!-- Danh sách Cột mốc Hiện tại của User (Checklist dạng thẻ rõ ràng) -->
            <div class="milestones-list" id="stage-milestones-<?= $stageKey ?>" style="margin-bottom: var(--space-4);">
                <?php if (empty($items)): ?>
                    <div class="empty-stage-note" style="padding: var(--space-3); font-size: var(--text-sm); color: var(--muted); font-style: italic; background: var(--surface-alt); border-radius: var(--radius-sm); border: 1px dashed var(--border);">
                        Chưa có cột mốc nào trong giai đoạn này. Hãy chọn từ gợi ý bên dưới hoặc tự thêm cột mốc riêng.
                    </div>
                <?php else: ?>
                    <?php foreach ($items as $m): ?>
                    <div class="milestone-item <?= $m['is_completed'] ? 'is-completed' : '' ?>" id="milestone-row-<?= $m['id'] ?>" style="display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-3); padding: var(--space-3); margin-bottom: var(--space-2); border-radius: var(--radius-sm); border: 1px solid <?= $m['is_completed'] ? 'var(--success)' : 'var(--border)' ?>; background: <?= $m['is_completed'] ? '#f0fdf4' : 'var(--surface)' ?>;">
                        <div style="display: flex; align-items: flex-start; gap: var(--space-3); flex: 1;">
                            <!-- Checkbox toggle -->
                            <input type="checkbox" class="milestone-toggle-check" data-id="<?= $m['id'] ?>" <?= $m['is_completed'] ? 'checked' : '' ?> style="width: 18px; height: 18px; margin-top: 3px; cursor: pointer; accent-color: var(--success);" aria-label="Đánh dấu hoàn thành cột mốc">
                            <div>
                                <div style="display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap;">
                                    <strong class="milestone-title-text" style="font-size: var(--text-sm); color: <?= $m['is_completed'] ? 'var(--success)' : 'var(--text)' ?>; <?= $m['is_completed'] ? 'text-decoration: line-through;' : '' ?>">
                                        <?= e($m['title']) ?>
                                    </strong>
                                    <!-- Source Badge phân biệt nguồn -->
                                    <?php if ($m['source'] === 'assessment'): ?>
                                        <span class="badge" style="background: #e0e7ff; color: #4338ca; font-size: 0.7rem; padding: 2px 6px;">Từ đánh giá</span>
                                    <?php elseif ($m['source'] === 'ai'): ?>
                                        <span class="badge" style="background: var(--primary-light); color: var(--primary); font-size: 0.7rem; padding: 2px 6px;">✨ AI gợi ý</span>
                                    <?php elseif ($m['source'] === 'custom'): ?>
                                        <span class="badge" style="background: #fef3c7; color: #b45309; font-size: 0.7rem; padding: 2px 6px;">Tự lập</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: var(--surface-alt); color: var(--muted); font-size: 0.7rem; padding: 2px 6px;">Gợi ý mẫu</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($m['description'])): ?>
                                    <p style="margin: var(--space-1) 0 0; font-size: var(--text-xs); color: var(--muted); line-height: var(--leading-relaxed);"><?= e($m['description']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="no-print" style="display: flex; align-items: center; gap: var(--space-2); flex-shrink: 0;">
                            <?php if (!empty($m['action_url'])): ?>
                                <a href="<?= url($m['action_url']) ?>" class="btn btn-outline" style="font-size: var(--text-xs); padding: 3px 8px; white-space: nowrap;">
                                    Mở →
                                </a>
                            <?php endif; ?>
                            <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin: 0;" onsubmit="return confirm('Bạn có chắc muốn xóa cột mốc này?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_milestone">
                                <input type="hidden" name="milestone_id" value="<?= $m['id'] ?>">
                                <button type="submit" class="btn btn-outline" style="font-size: var(--text-xs); padding: 3px 8px; color: var(--danger); border-color: var(--danger);" title="Xóa cột mốc">
                                    ✕
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Form Tự Thêm Cột Mốc Mới -->
            <details class="no-print" style="margin-top: var(--space-3); background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: var(--space-3);">
                <summary style="font-weight: 600; font-size: var(--text-xs); color: var(--primary); cursor: pointer;">
                    + Tự viết cột mốc riêng của bạn trong giai đoạn này
                </summary>
                <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin-top: var(--space-3);">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_milestone">
                    <input type="hidden" name="stage" value="<?= $stageKey ?>">
                    <input type="hidden" name="source" value="custom">
                    <div class="form-group" style="margin-bottom: var(--space-2);">
                        <label style="font-size: var(--text-xs); font-weight: 600;">Tên nhiệm vụ / Cột mốc *</label>
                        <input class="input" name="title" required maxlength="255" placeholder="Ví dụ: Ôn tập 5 đề thi môn Toán, phỏng vấn một anh chị đi trước..." style="width: 100%;">
                    </div>
                    <div class="form-group" style="margin-bottom: var(--space-3);">
                        <label style="font-size: var(--text-xs); font-weight: 600;">Ghi chú thực hiện:</label>
                        <textarea class="input" name="description" rows="2" maxlength="2000" placeholder="Chi tiết cách bạn sẽ hoàn thành nhiệm vụ này..." style="width: 100%;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="font-size: var(--text-xs); padding: 5px 12px;">
                        Lưu vào hành trình
                    </button>
                </form>
            </details>

            <!-- Thư Viện Cột Mốc Mẫu Từ Website -->
            <details class="no-print" style="margin-top: var(--space-2); background: var(--surface); border: 1px dashed var(--border); border-radius: var(--radius-sm); padding: var(--space-3);">
                <summary style="font-weight: 600; font-size: var(--text-xs); color: var(--muted); cursor: pointer;">
                    💡 Gợi ý mẫu từ thư viện website (<?= count($libItems) ?> mẫu)
                </summary>
                <div style="margin-top: var(--space-3); display: flex; flex-direction: column; gap: var(--space-2);">
                    <?php foreach ($libItems as $lib): ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); background: var(--surface-alt); padding: var(--space-2) var(--space-3); border-radius: var(--radius-sm); border: 1px solid var(--border);">
                        <div>
                            <strong style="font-size: var(--text-xs); color: var(--text);"><?= e($lib['title']) ?></strong>
                            <p style="margin: 2px 0 0; font-size: var(--text-xs); color: var(--muted);"><?= e($lib['description']) ?></p>
                        </div>
                        <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin: 0; flex-shrink: 0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_milestone">
                            <input type="hidden" name="stage" value="<?= $stageKey ?>">
                            <input type="hidden" name="title" value="<?= e($lib['title']) ?>">
                            <input type="hidden" name="description" value="<?= e($lib['description']) ?>">
                            <input type="hidden" name="action_url" value="<?= e($lib['action_url']) ?>">
                            <input type="hidden" name="source" value="suggested">
                            <button type="submit" class="btn btn-outline" style="font-size: var(--text-xs); padding: 3px 8px; white-space: nowrap;">
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

    <p style="margin-top: var(--space-4); font-size: var(--text-xs); color: var(--muted); line-height: var(--leading-relaxed);">
        Bản đồ hành trình là công cụ hướng dẫn cá nhân, có thể điều chỉnh bất kỳ lúc nào theo sự trưởng thành và điều kiện thực tế của bạn. Hãy chia sẻ với giáo viên hoặc gia đình để nhận thêm lời khuyên.
    </p>
</section>

<!-- KHỐI 2 (COLLAPSIBLE): PHÂN TÍCH & GỢI Ý TỪ KẾT QUẢ CÁC BÀI ĐÁNH GIÁ TRƯỚC -->
<details id="assessment-insights" class="card no-print" style="margin-bottom: var(--space-6); border: 1px solid var(--border);" <?= $assessmentInsights['tests_count'] > 0 ? '' : '' ?>>
    <summary style="cursor: pointer; padding: var(--space-2) 0; display: flex; align-items: center; justify-content: space-between; list-style: none;">
        <div style="display: flex; align-items: center; gap: var(--space-2);">
            <span style="font-size: 1.25rem;" aria-hidden="true">🧭</span>
            <div>
                <strong style="font-size: var(--text-base); color: var(--text);">Phân tích & Gợi ý từ các bài kiểm tra đã làm</strong>
                <span class="badge" style="background: var(--primary-light); color: var(--primary); font-size: var(--text-xs); margin-left: var(--space-2);">
                    <?= $assessmentInsights['tests_count'] ?> dữ liệu đánh giá
                </span>
            </div>
        </div>
        <span style="font-size: var(--text-xs); color: var(--primary); font-weight: 600;">Xem chi tiết & gợi ý ▼</span>
    </summary>

    <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--border);">
        <div class="grid-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-5);">
            <div style="background: var(--surface-alt); padding: var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--border);">
                <h4 style="font-size: var(--text-sm); font-weight: 700; color: var(--text); margin-top: 0;">✨ Điểm mạnh & Thiên hướng nhận diện</h4>
                <?php if (!empty($assessmentInsights['strengths'])): ?>
                    <ul style="padding-left: var(--space-4); margin: var(--space-2) 0; color: var(--text); line-height: var(--leading-relaxed); font-size: var(--text-xs);">
                        <?php foreach ($assessmentInsights['strengths'] as $s): ?>
                            <li><?= e($s) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="font-size: var(--text-xs); color: var(--muted); margin: 0;">Chưa có kết quả kiểm tra. Hãy làm bài đánh giá để phát hiện điểm mạnh tự nhiên.</p>
                <?php endif; ?>
            </div>

            <div style="background: var(--surface-alt); padding: var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--border);">
                <h4 style="font-size: var(--text-sm); font-weight: 700; color: var(--text); margin-top: 0;">⚠️ Vùng cần lưu ý & Ranh giới thực tế</h4>
                <?php if (!empty($assessmentInsights['growth_areas'])): ?>
                    <ul style="padding-left: var(--space-4); margin: var(--space-2) 0; color: var(--text); line-height: var(--leading-relaxed); font-size: var(--text-xs);">
                        <?php foreach ($assessmentInsights['growth_areas'] as $g): ?>
                            <li><?= e($g) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="font-size: var(--text-xs); color: var(--muted); margin: 0;">Hồ sơ cân bằng hoặc chưa hoàn thành khảo sát Cổng đánh đổi.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Gợi ý nghề mục tiêu từ bài đánh giá -->
        <?php if (!empty($assessmentInsights['suggested_careers'])): ?>
        <div style="background: var(--surface-alt); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--border); margin-bottom: var(--space-4);">
            <h4 style="margin: 0 0 var(--space-2); font-size: var(--text-xs); color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700;">Nghề tương thích từ kết quả đánh giá (Bấm để chọn làm mục tiêu):</h4>
            <div style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
                <?php foreach ($assessmentInsights['suggested_careers'] as $c): ?>
                    <button type="button" class="btn btn-outline apply-career-goal" data-career-title="<?= e($c['title']) ?>" style="background: var(--surface); font-size: var(--text-xs);">
                        🎯 <?= e($c['title']) ?> (Độ khớp: <?= (int)$c['match_percent'] ?>%)
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Các cột mốc đề xuất tương ứng với điểm mạnh / rào cản -->
        <div>
            <div class="flex flex-wrap items-center justify-between gap-2" style="margin-bottom: var(--space-3);">
                <h4 style="font-size: var(--text-sm); font-weight: 700; margin: 0; color: var(--text);">📋 Cột mốc hành động gợi ý từ bài kiểm tra:</h4>
                <?php if (!empty($assessmentInsights['recommended_milestones'])): ?>
                <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin: 0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="apply_all_suggestions">
                    <button type="submit" class="btn btn-primary" style="font-size: var(--text-xs); padding: 5px 12px;">
                        📥 Thêm tất cả gợi ý vào hành trình của tôi
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <?php if (!empty($assessmentInsights['recommended_milestones'])): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: var(--space-3);">
                    <?php foreach ($assessmentInsights['recommended_milestones'] as $rm): ?>
                        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: var(--space-3); display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <span class="badge" style="background: var(--surface-alt); border: 1px solid var(--border); font-size: 0.7rem; color: var(--muted); margin-bottom: 4px; display: inline-block;">
                                    <?= e($stages[$rm['stage']]['title'] ?? $rm['stage']) ?>
                                </span>
                                <h5 style="margin: 2px 0 4px; font-size: var(--text-sm); color: var(--text); font-weight: 700;"><?= e($rm['title']) ?></h5>
                                <p style="margin: 0; font-size: var(--text-xs); color: var(--muted); line-height: var(--leading-relaxed);"><?= e($rm['description']) ?></p>
                            </div>
                            <div style="margin-top: var(--space-3); display: flex; align-items: center; justify-content: space-between; gap: var(--space-2);">
                                <?php if (!empty($rm['action_url'])): ?>
                                    <a href="<?= url($rm['action_url']) ?>" class="btn btn-outline" style="font-size: var(--text-xs); padding: 3px 8px;">Thực hiện →</a>
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
                                    <button type="submit" class="btn btn-outline" style="font-size: var(--text-xs); padding: 3px 8px; color: var(--primary); border-color: var(--primary);">
                                        + Thêm vào hành trình
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="font-size: var(--text-xs); color: var(--muted);">Chưa có đề xuất cụ thể. Hãy hoàn thành các bài trắc nghiệm để nhận gợi ý chuẩn.</p>
            <?php endif; ?>
        </div>
    </div>
</details>

<!-- KHỐI 3 (COLLAPSIBLE): TỔNG HỢP HỒ SƠ ĐA CHIỀU (THAM KHẢO) -->
<details id="profile-summary" class="card no-print" style="margin-bottom: var(--space-6); border: 1px solid var(--border);">
    <summary style="cursor: pointer; padding: var(--space-2) 0; display: flex; align-items: center; justify-content: space-between; list-style: none;">
        <div style="display: flex; align-items: center; gap: var(--space-2);">
            <span style="font-size: 1.25rem;" aria-hidden="true">📊</span>
            <div>
                <strong style="font-size: var(--text-base); color: var(--text);">Hồ sơ đa chiều của bạn (RIASEC, MBTI, Big Five, Trải nghiệm)</strong>
                <span class="badge" style="background: var(--surface-alt); color: var(--muted); font-size: var(--text-xs); margin-left: var(--space-2);">
                    Tham khảo
                </span>
            </div>
        </div>
        <span style="font-size: var(--text-xs); color: var(--primary); font-weight: 600;">Xem hồ sơ ▼</span>
    </summary>

    <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--border);">
        <div class="grid-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-4);">
            <div style="background: var(--surface-alt); padding: var(--space-3); border-radius: var(--radius-sm); border: 1px solid var(--border);">
                <h4 style="margin: 0 0 var(--space-1); font-size: var(--text-sm); font-weight: 700;">Holland (RIASEC)</h4>
                <p style="font-size: var(--text-base); font-weight: 700; color: var(--primary); margin: 0 0 var(--space-2);"><?= e($context['holland']['code'] ?? 'Chưa có kết quả') ?></p>
                <a href="<?= url('index.php?page=assessment-report') ?>" style="font-size: var(--text-xs); color: var(--primary);">Xem báo cáo đánh giá →</a>
            </div>
            <div style="background: var(--surface-alt); padding: var(--space-3); border-radius: var(--radius-sm); border: 1px solid var(--border);">
                <h4 style="margin: 0 0 var(--space-1); font-size: var(--text-sm); font-weight: 700;">Tính cách & Thích ứng</h4>
                <p style="font-size: var(--text-xs); color: var(--text); margin: 0 0 4px;">MBTI: <strong><?= e($context['psychometrics']['mbti']['code'] ?? 'Chưa làm') ?></strong></p>
                <p style="font-size: var(--text-xs); color: var(--text); margin: 0 0 4px;">Big Five: <?= isset($context['psychometrics']['big5']) ? '<span style="color:var(--success);">Đã có hồ sơ</span>' : 'Chưa làm' ?></p>
                <p style="font-size: var(--text-xs); color: var(--text); margin: 0;">EQ: <?= isset($context['psychometrics']['eq']) ? '<span style="color:var(--success);">Đã có hồ sơ</span>' : 'Chưa làm' ?></p>
            </div>
            <div style="background: var(--surface-alt); padding: var(--space-3); border-radius: var(--radius-sm); border: 1px solid var(--border);">
                <h4 style="margin: 0 0 var(--space-1); font-size: var(--text-sm); font-weight: 700;">Bằng chứng trải nghiệm</h4>
                <p style="font-size: var(--text-xs); color: var(--text); margin: 0 0 var(--space-2);"><?= count($context['experiences']) ?> trải nghiệm gần nhất được tổng hợp.</p>
                <a href="<?= url('index.php?page=simulations') ?>" style="font-size: var(--text-xs); color: var(--primary);">Mở phòng trải nghiệm nghề →</a>
            </div>
        </div>
        <p style="margin-top: var(--space-3); font-size: var(--text-xs); color: var(--muted); line-height: var(--leading-relaxed);">
            Các kết quả chỉ hỗ trợ khám phá, không phải chẩn đoán hay thước đo giá trị của bạn. Trợ lý không tự ý suy đoán khi chưa có dữ liệu quan sát.
        </p>
    </div>
</details>

<!-- KHỐI 4: GÓC NGƯỜI TRONG NGHỀ & CHATBOT HƯỚNG NGHIỆP (BẢO TOÀN KIẾN TRÚC & TESTS) -->
<?php require __DIR__ . '/career-conversations-view.php'; ?>

<section id="career-chat" class="card no-print" aria-labelledby="chat-title" style="margin-top: var(--space-6); border: 1px solid var(--border);">
    <h2 id="chat-title" style="margin-top: 0; font-size: var(--text-xl); font-weight: 700; color: var(--text);">
        <?= e($scenario ? 'Cùng khám phá: ' . $scenario['title'] : 'Trao đổi với trợ lý hướng nghiệp') ?>
    </h2>
    <p style="font-size: var(--text-xs); color: var(--muted); margin: var(--space-1) 0 var(--space-2); line-height: var(--leading-relaxed);">
        Khi AI được cấu hình, câu hỏi và hội thoại trong mục đang chọn được gửi tới nhà cung cấp AI. Chat thông thường kèm tóm tắt đánh giá/trải nghiệm; chat tình huống chỉ kèm hồ sơ nếu bạn chọn bên dưới. Nếu không có kết nối AI, hệ thống hiển thị gợi ý dự phòng chuẩn mực.
    </p>
    <p style="font-size: var(--text-xs); color: var(--text); margin-bottom: var(--space-4);">
        <strong><?= max(0, 10 - $used) ?> / 10 lượt còn lại hôm nay</strong> · Đặt lại lúc 00:00 theo giờ Việt Nam.
    </p>

    <div class="chat-history" aria-label="Lịch sử hội thoại" style="margin-bottom: var(--space-4); display: flex; flex-direction: column; gap: var(--space-3);">
        <?php if (!$messages): ?>
            <p style="font-size: var(--text-sm); color: var(--muted); font-style: italic;">Bắt đầu bằng một câu hỏi về hồ sơ, lộ trình học tập hoặc bước tiếp theo của bạn.</p>
        <?php endif; ?>
        <?php foreach ($messages as $chat): ?>
            <article class="card chat-message" style="background: <?= $chat['role'] === 'user' ? 'var(--surface-alt)' : 'var(--surface)' ?>; border: 1px solid var(--border); padding: var(--space-3); border-radius: var(--radius-sm);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                    <strong style="font-size: var(--text-xs); color: <?= $chat['role'] === 'user' ? 'var(--text)' : 'var(--primary)' ?>;">
                        <?= $chat['role'] === 'user' ? 'Bạn' : ($chat['model'] === 'fallback' ? 'Gợi ý dự phòng' : ($chat['model'] === 'unavailable' ? 'Trạng thái AI' : 'Trợ lý AI · ' . e($chat['model']))) ?>
                    </strong>
                    <small style="font-size: 0.7rem; color: var(--muted);"><?= e($chat['created_at']) ?></small>
                </div>
                <p style="white-space: pre-wrap; overflow-wrap: anywhere; margin: 0; font-size: var(--text-sm); line-height: var(--leading-relaxed);"><?= e($chat['message']) ?></p>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="flex flex-wrap gap-2" aria-label="Câu hỏi gợi ý" style="margin-bottom: var(--space-4);">
        <?php foreach (($scenario ? career_conversation_prompts($scenario) : ['Tôi nên tập trung môn học nào cho lộ trình này?', 'MBTI và Big Five của tôi có ý nghĩa gì với nghề này?', 'Gợi ý thử thách 15 phút thực tế cho tôi.']) as $chip): ?>
            <button type="button" class="btn btn-outline" data-chat-prompt="<?= e($chip) ?>" style="font-size: var(--text-xs); padding: 4px 10px;"><?= e($chip) ?></button>
        <?php endforeach; ?>
    </div>

    <form method="post" action="<?= url('index.php?page=ai-chat') ?>" class="mt-4" id="career-chat-form">
        <?= csrf_field() ?>
        <input type="hidden" name="chat_nonce" value="<?= e($_SESSION['four_hub_chat_nonce']) ?>">
        <input type="hidden" name="scenario" value="<?= e($scenario['id'] ?? '') ?>">
        <?php if ($scenario): ?>
            <p style="font-size: var(--text-xs); margin-bottom: var(--space-2);"><label><input type="checkbox" name="include_profile" value="1"> Kèm tóm tắt đánh giá/trải nghiệm của tôi để liên hệ với tình huống (tùy chọn).</label></p>
        <?php endif; ?>
        <div class="form-group" style="margin-bottom: var(--space-3);">
            <label for="career-question" style="font-size: var(--text-xs); font-weight: 600; display: block; margin-bottom: var(--space-1);">Câu hỏi của bạn</label>
            <textarea class="input" id="career-question" name="message" rows="3" maxlength="2000" required <?= $used >= 10 ? 'disabled' : '' ?> placeholder="Hỏi về nghề nghiệp, môn học hoặc bước đi tiếp theo..." style="width: 100%;"></textarea>
        </div>
        <button class="btn btn-primary" type="submit" <?= $used >= 10 ? 'disabled' : '' ?> style="font-size: var(--text-sm);">Gửi câu hỏi</button>
        <p id="chat-send-status" role="status" style="font-size: var(--text-xs); color: var(--muted); margin-top: var(--space-2);"></p>
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
                        <div style="background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                            <div>
                                <strong style="font-size: var(--text-xs); color: var(--text);">${item.title}</strong>
                                <p style="margin: 2px 0 0; font-size: var(--text-xs); color: var(--muted);">${item.description}</p>
                            </div>
                            <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin: 0; flex-shrink: 0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add_milestone">
                                <input type="hidden" name="stage" value="${item.stage}">
                                <input type="hidden" name="title" value="${item.title}">
                                <input type="hidden" name="description" value="${item.description}">
                                <input type="hidden" name="action_url" value="${item.action_url || ''}">
                                <input type="hidden" name="source" value="ai">
                                <button type="submit" class="btn btn-primary" style="font-size: var(--text-xs); padding: 4px 8px; white-space: nowrap;">
                                    + Thêm
                                </button>
                            </form>
                        </div>
                    `;
                });
                html += '</div>';
                if (aiResult) aiResult.innerHTML = html;
            } else {
                if (aiResult) aiResult.innerHTML = '<p style="color: var(--danger); font-size: var(--text-xs);">Chưa thể sinh gợi ý từ AI lúc này. Vui lòng thử lại sau.</p>';
            }
        } catch (e) {
            if (aiResult) aiResult.innerHTML = '<p style="color: var(--danger); font-size: var(--text-xs);">Đã xảy ra lỗi kết nối. Hãy thử lại.</p>';
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
                        row.style.borderColor = 'var(--success)';
                        if (titleText) {
                            titleText.style.color = 'var(--success)';
                            titleText.style.textDecoration = 'line-through';
                        }
                    } else {
                        row.classList.remove('is-completed');
                        row.style.background = 'var(--surface)';
                        row.style.borderColor = 'var(--border)';
                        if (titleText) {
                            titleText.style.color = 'var(--text)';
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
