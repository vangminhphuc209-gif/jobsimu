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
        $stageRedirect = in_array($_POST['stage'] ?? '', ['discovery', 'academic', 'skills', 'decision'], true) ? $_POST['stage'] : 'discovery';
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        try {
            if ($action === 'update_goal') {
                user_roadmap_update_details($roadmapId, $userId, $_POST);
                flash('success', 'Đã cập nhật mục tiêu và hướng đi của bạn.');
                redirect('index.php?page=ai-roadmap&stage=' . urlencode($stageRedirect) . '#user-roadmap');
            } elseif ($action === 'add_milestone') {
                $newId = user_roadmap_add_milestone($roadmapId, $userId, $_POST);
                if ($isAjax) {
                    $milestones = user_roadmap_get_milestones($roadmapId, $userId);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok', 'id' => $newId, 'progress' => user_roadmap_calculate_progress($milestones)]);
                    exit;
                }
                flash('success', 'Đã thêm công việc mới vào kế hoạch cá nhân.');
                redirect('index.php?page=ai-roadmap&stage=' . urlencode($stageRedirect) . '#user-roadmap');
            } elseif ($action === 'edit_milestone') {
                $mid = (int)($_POST['milestone_id'] ?? 0);
                user_roadmap_update_milestone($mid, $userId, $_POST);
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok']);
                    exit;
                }
                flash('success', 'Đã cập nhật công việc.');
                redirect('index.php?page=ai-roadmap&stage=' . urlencode($stageRedirect) . '#user-roadmap');
            } elseif ($action === 'toggle_milestone') {
                $mid = (int)($_POST['milestone_id'] ?? 0);
                $res = user_roadmap_toggle_milestone($mid, $userId);
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok', 'data' => $res]);
                    exit;
                }
                flash('success', $res['is_completed'] ? 'Đã hoàn thành công việc!' : 'Đã chuyển công việc về trạng thái đang làm.');
                redirect('index.php?page=ai-roadmap&stage=' . urlencode($stageRedirect) . '#user-roadmap');
            } elseif ($action === 'delete_milestone') {
                $mid = (int)($_POST['milestone_id'] ?? 0);
                user_roadmap_delete_milestone($mid, $userId);
                if ($isAjax) {
                    $milestones = user_roadmap_get_milestones($roadmapId, $userId);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok', 'progress' => user_roadmap_calculate_progress($milestones)]);
                    exit;
                }
                flash('success', 'Đã xóa công việc khỏi kế hoạch.');
                redirect('index.php?page=ai-roadmap&stage=' . urlencode($stageRedirect) . '#user-roadmap');
            } elseif ($action === 'apply_all_suggestions') {
                $insights = user_roadmap_derive_assessment_insights($userId);
                $count = 0;
                foreach ($insights['recommended_milestones'] as $rm) {
                    user_roadmap_add_milestone($roadmapId, $userId, $rm);
                    $count++;
                }
                flash('success', "Đã thêm $count gợi ý từ bài kiểm tra vào kế hoạch của bạn.");
                redirect('index.php?page=ai-roadmap&stage=' . urlencode($stageRedirect) . '#user-roadmap');
            } elseif ($action === 'ai_suggest_milestones') {
                $aiData = user_roadmap_ai_generate_suggestions($userId, $roadmap);
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok', 'data' => $aiData]);
                    exit;
                }
                $_SESSION['roadmap_ai_suggestions'] = $aiData;
                flash('success', 'AI đã phân tích và đề xuất các mốc hành động phù hợp.');
                redirect('index.php?page=ai-roadmap&stage=' . urlencode($stageRedirect) . '#user-roadmap');
            }
        } catch (Throwable $e) {
            if ($isAjax) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit;
            }
            flash('error', $e->getMessage());
            redirect('index.php?page=ai-roadmap&stage=' . urlencode($stageRedirect) . '#user-roadmap');
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
$stages = user_roadmap_stages();
$libraryMilestones = user_roadmap_library_milestones($roadmap['target_career_title'] ?? null);

// Determine active stage
$activeStage = in_array($_GET['stage'] ?? '', ['discovery', 'academic', 'skills', 'decision'], true) ? $_GET['stage'] : 'discovery';
$activeStageMeta = $stages[$activeStage];
$activeStageData = $groupedMilestones[$activeStage] ?? ['items' => [], 'completed_count' => 0, 'total_count' => 0];

// Compute genuine Next Action (Vùng B)
$nextAction = user_roadmap_get_next_action($roadmap, $activeStage);

$pageTitle = 'Lộ trình của tôi · Kế hoạch cá nhân';
require __DIR__ . '/header.php';
?>
<!-- Dedicated Roadmap CSS Component Bundle -->
<link rel="stylesheet" href="<?= url('assets/css/roadmap.css') ?>">

<section class="page-head no-print">
    <div class="container" style="max-width: var(--max-w-content);">
        <span class="eyebrow">Hành trình của tôi · Kế hoạch cá nhân</span>
        <h1 style="font-size: var(--text-3xl); font-weight: 800; margin: var(--space-2) 0 var(--space-3); color: var(--text);">Lộ trình của tôi</h1>
        <p style="font-size: var(--text-base); color: var(--muted); max-width: 680px; margin: 0; line-height: var(--leading-relaxed);">
            Kế hoạch hành động do bạn tự chủ thiết kế và điều chỉnh theo thời gian. Khám phá từng bước với mục tiêu rõ ràng và kết quả cụ thể.
        </p>
    </div>
</section>

<div class="roadmap-container">

    <!-- VÙNG A: MỤC TIÊU CỦA TÔI -->
    <section id="user-roadmap" class="roadmap-goal-card" aria-labelledby="roadmap-goal-heading">
        <div class="roadmap-goal-header">
            <div class="roadmap-goal-info">
                <div style="display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap;">
                    <span class="eyebrow" style="color: var(--primary);">Mục tiêu hiện tại</span>
                    <span class="roadmap-goal-timeframe">📅 Khung thời gian: <?= e($roadmap['target_timeframe']) ?></span>
                    <?php if (!empty($roadmap['target_career_title'])): ?>
                        <span class="badge" style="background: var(--primary-light); color: var(--primary); font-weight: 700; padding: 4px 10px; border-radius: var(--radius-full); font-size: var(--text-xs);">
                            Nghề hướng tới: <?= e($roadmap['target_career_title']) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <h2 id="roadmap-goal-heading" class="roadmap-goal-title">
                    🎯 <span id="display-goal-title"><?= e($roadmap['goal_title']) ?></span>
                </h2>

                <?php if (!empty($roadmap['notes'])): ?>
                    <p class="roadmap-goal-notes">
                        "<?= e($roadmap['notes']) ?>"
                    </p>
                <?php endif; ?>
            </div>

            <div class="roadmap-goal-actions no-print">
                <button type="button" class="btn btn-outline" id="open-goal-modal-btn" style="font-size: var(--text-sm); font-weight: 600;">
                    ✏️ Sửa mục tiêu
                </button>
                <button type="button" class="btn btn-outline" id="print-roadmap-btn" style="font-size: var(--text-sm); font-weight: 600;">
                    🖨️ In lộ trình
                </button>
            </div>
        </div>

        <!-- Thanh tiến độ tổng quan -->
        <div class="roadmap-progress-bar-wrap">
            <div class="roadmap-progress-meta">
                <span style="font-weight: 700; color: var(--text);">Tiến độ lộ trình:</span>
                <span id="progress-text" style="font-weight: 700; color: var(--primary);">
                    <?= $progress['percent'] ?>% (Đã hoàn thành <?= $progress['completed'] ?> / <?= $progress['total'] ?> cột mốc)
                </span>
            </div>
            <div class="roadmap-progress-track">
                <div id="progress-bar-fill" class="roadmap-progress-fill" style="width: <?= $progress['percent'] ?>%;"></div>
            </div>
        </div>

        <!-- Trạng thái gợi ý bắt đầu cho học sinh mới hoặc cần chọn hướng -->
        <div class="no-print" style="margin-top: var(--space-4); padding-top: var(--space-3); border-top: 1px dashed var(--border); display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); flex-wrap: wrap;">
            <span style="font-size: var(--text-xs); color: var(--muted);">Bắt đầu lập kế hoạch mới:</span>
            <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
                <button type="button" class="btn btn-outline quick-start-goal-btn" data-mode="career" style="font-size: var(--text-xs); padding: 4px 10px;">
                    🎯 Tôi đã có nghề muốn khám phá
                </button>
                <button type="button" class="btn btn-outline quick-start-goal-btn" data-mode="general" style="font-size: var(--text-xs); padding: 4px 10px;">
                    🧭 Tôi chưa biết nên bắt đầu từ đâu
                </button>
            </div>
        </div>
    </section>

    <!-- VÙNG B: VIỆC NÊN LÀM TIẾP THEO (HERO BANNER) -->
    <?php if ($nextAction): ?>
    <section class="roadmap-next-card no-print" aria-labelledby="next-step-title">
        <div class="roadmap-next-content">
            <span class="roadmap-next-badge">⚡ Bước tiếp theo nên làm · <?= e($nextAction['stage_title']) ?></span>
            <h3 id="next-step-title" class="roadmap-next-title"><?= e($nextAction['title']) ?></h3>
            <p class="roadmap-next-reason"><?= e($nextAction['rationale']) ?></p>
            <div class="roadmap-next-outcome">
                <span aria-hidden="true">🎯</span>
                <span><strong>Kết quả cần có:</strong> <?= e($nextAction['outcome']) ?></span>
            </div>
        </div>
        <div class="roadmap-next-action-area">
            <?php if (!empty($nextAction['action_url']) && strpos($nextAction['action_url'], '#') !== 0): ?>
                <a href="<?= url($nextAction['action_url']) ?>" class="roadmap-next-cta">
                    <?= e($nextAction['action_label']) ?>
                </a>
            <?php else: ?>
                <button type="button" class="roadmap-next-cta" id="next-action-trigger-btn">
                    <?= e($nextAction['action_label']) ?>
                </button>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- VÙNG C: BỐ CỤC 2 CỘT (DESKTOP 280PX SIDEBAR + CỘT PHẢI DUY NHẤT GIAI ĐOẠN ĐANG CHỌN) -->
    <div class="roadmap-layout">

        <!-- CỘT TRÁI: BỘ CHỌN 4 GIAI ĐOẠN -->
        <aside class="stage-sidebar" aria-label="Danh sách giai đoạn hành trình">
            <h3 class="stage-sidebar-title">4 Giai đoạn phát triển</h3>
            <ul class="stage-nav-list" role="tablist">
                <?php 
                $stageIndex = 1;
                foreach ($stages as $sk => $sm): 
                    $sd = $groupedMilestones[$sk] ?? ['completed_count' => 0, 'total_count' => 0];
                    $isActive = ($sk === $activeStage);
                ?>
                <li role="presentation">
                    <a href="<?= url('index.php?page=ai-roadmap&stage=' . $sk . '#user-roadmap') ?>" 
                       class="stage-nav-btn <?= $isActive ? 'is-active' : '' ?>" 
                       role="tab" 
                       aria-selected="<?= $isActive ? 'true' : 'false' ?>"
                       data-stage="<?= $sk ?>">
                        <div class="stage-nav-left">
                            <span class="stage-nav-number"><?= $stageIndex++ ?></span>
                            <div>
                                <span class="stage-nav-text"><?= e($sm['title']) ?></span>
                            </div>
                        </div>
                        <span class="stage-nav-badge">
                            <?= $sd['completed_count'] ?> / <?= $sd['total_count'] ?>
                        </span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <!-- CỘT PHẢI: NỘI DUNG GIAI ĐOẠN ĐANG CHỌN -->
        <main class="stage-main-panel" aria-labelledby="stage-main-title">
            <!-- Header giai đoạn -->
            <header class="stage-header">
                <div class="stage-header-top">
                    <div>
                        <span class="eyebrow" style="color: var(--primary);">Giai đoạn đang chọn</span>
                        <h2 id="stage-main-title" class="stage-header-title">
                            <?= e($activeStageMeta['icon']) ?> <?= e($activeStageMeta['title']) ?>
                        </h2>
                        <p class="stage-header-desc"><?= e($activeStageMeta['summary']) ?></p>
                    </div>
                    <div>
                        <span class="badge" style="background: var(--surface-alt); border: 1px solid var(--border); font-size: var(--text-xs); padding: 5px 12px; font-weight: 700;">
                            <?= $activeStageData['completed_count'] ?> / <?= $activeStageData['total_count'] ?> việc đã hoàn thành
                        </span>
                    </div>
                </div>

                <div class="stage-target-outcome-box">
                    <span aria-hidden="true">💡</span>
                    <span><strong>Mục tiêu giai đoạn:</strong> Hoàn thành các việc bên dưới để xây dựng nền tảng vững chắc và ghi chép kết quả thực tế.</span>
                </div>
            </header>

            <!-- Thanh công cụ & Nút Thêm công việc duy nhất -->
            <div class="stage-action-toolbar no-print">
                <span style="font-size: var(--text-sm); font-weight: 700; color: var(--text);">
                    Danh sách công việc (<?= count($activeStageData['items']) ?>)
                </span>

                <div class="add-task-dropdown-container">
                    <button type="button" class="btn-add-task" id="btn-add-task-toggle" aria-haspopup="true" aria-expanded="false">
                        <span>+ Thêm công việc</span>
                        <span style="font-size: 0.75rem;">▼</span>
                    </button>
                    <div class="add-task-menu" id="add-task-menu-dropdown" role="menu">
                        <button type="button" class="add-task-menu-item" id="btn-open-custom-task-modal" role="menuitem">
                            <span>✏️</span>
                            <span>Tự viết việc cần làm</span>
                        </button>
                        <button type="button" class="add-task-menu-item" id="btn-open-template-modal" role="menuitem">
                            <span>💡</span>
                            <span>Chọn từ gợi ý có sẵn</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Danh sách công việc của giai đoạn -->
            <div class="tasks-list" id="tasks-list-container">
                <?php if (empty($activeStageData['items'])): ?>
                    <div style="background: var(--surface-alt); border: 1.5px dashed var(--border); border-radius: var(--radius-md); padding: var(--space-8); text-align: center;">
                        <span style="font-size: 2rem; display: block; margin-bottom: var(--space-2);" aria-hidden="true">📝</span>
                        <h4 style="margin: 0 0 var(--space-1); font-size: var(--text-base); font-weight: 700; color: var(--text);">Giai đoạn này chưa có công việc nào</h4>
                        <p style="margin: 0 0 var(--space-4); font-size: var(--text-xs); color: var(--muted); max-width: 420px; margin-left: auto; margin-right: auto; line-height: var(--leading-relaxed);">
                            Bạn có thể chọn các gợi ý tiêu chuẩn từ thư viện hoặc tự viết mục tiêu riêng theo nhịp độ học tập của mình.
                        </p>
                        <div style="display: flex; justify-content: center; gap: var(--space-2); flex-wrap: wrap;">
                            <button type="button" class="btn btn-primary" onclick="openTemplateModal()" style="font-size: var(--text-xs);">
                                💡 Chọn từ gợi ý có sẵn
                            </button>
                            <button type="button" class="btn btn-outline" onclick="openCustomTaskModal()" style="font-size: var(--text-xs);">
                                ✏️ Tự viết việc cần làm
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($activeStageData['items'] as $m): 
                        $isCompleted = (bool)$m['is_completed'];
                        $actionMeta = user_roadmap_resolve_action($m['action_url'] ?? '');
                        $outcomeText = user_roadmap_resolve_outcome($m);
                    ?>
                    <article class="task-card <?= $isCompleted ? 'is-completed' : '' ?>" id="task-card-<?= $m['id'] ?>">
                        <div class="task-card-main">
                            <!-- Accessible Checkbox >= 44x44px target -->
                            <label class="task-checkbox-container" title="<?= $isCompleted ? 'Đánh dấu chưa hoàn thành' : 'Đánh dấu đã hoàn thành' ?>">
                                <input type="checkbox" 
                                       class="task-checkbox task-toggle-check" 
                                       data-id="<?= $m['id'] ?>" 
                                       <?= $isCompleted ? 'checked' : '' ?>
                                       aria-label="Hoàn thành: <?= e($m['title']) ?>">
                            </label>

                            <!-- Task Details -->
                            <div class="task-content">
                                <div class="task-title-row">
                                    <h4 class="task-title"><?= e($m['title']) ?></h4>
                                    <!-- Source Badge -->
                                    <?php if ($m['source'] === 'assessment'): ?>
                                        <span class="task-source-badge badge-source-assessment">Từ đánh giá</span>
                                    <?php elseif ($m['source'] === 'ai'): ?>
                                        <span class="task-source-badge badge-source-ai">✨ AI gợi ý</span>
                                    <?php elseif ($m['source'] === 'custom'): ?>
                                        <span class="task-source-badge badge-source-custom">Tự lập</span>
                                    <?php else: ?>
                                        <span class="task-source-badge badge-source-suggested">Gợi ý mẫu</span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($m['description'])): ?>
                                    <p class="task-desc"><?= e($m['description']) ?></p>
                                <?php endif; ?>

                                <!-- Expected Outcome Box -->
                                <div class="task-outcome-box">
                                    <span class="task-outcome-label">🎯 Kết quả:</span>
                                    <span><?= e($outcomeText) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer & Action Buttons -->
                        <div class="task-footer no-print">
                            <div>
                                <?php if (!empty($actionMeta['url'])): ?>
                                    <a href="<?= url($actionMeta['url']) ?>" class="task-action-btn">
                                        <?= e($actionMeta['label']) ?>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div style="display: flex; align-items: center; gap: 6px;">
                                <button type="button" 
                                        class="task-menu-btn edit-task-btn" 
                                        data-id="<?= $m['id'] ?>" 
                                        data-title="<?= e($m['title']) ?>" 
                                        data-desc="<?= e($m['description'] ?? '') ?>"
                                        title="Chỉnh sửa công việc">
                                    ✏️
                                </button>
                                <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin: 0;" onsubmit="return confirm('Bạn có chắc muốn xóa công việc này khỏi lộ trình?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_milestone">
                                    <input type="hidden" name="milestone_id" value="<?= $m['id'] ?>">
                                    <input type="hidden" name="stage" value="<?= $activeStage ?>">
                                    <button type="submit" class="task-menu-btn" style="color: var(--danger); border-color: #fecaca;" title="Xóa công việc">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- VÙNG D: TRỢ GIÚP VÀ PHẢN TƯ -->
            <div class="roadmap-reflection-bar no-print">
                <div class="reflection-bar-left">
                    <span style="font-size: 1.25rem;" aria-hidden="true">💡</span>
                    <span>Bạn muốn tìm thêm ý tưởng cho giai đoạn này hoặc ghi lại điều vừa học?</span>
                </div>
                <div class="reflection-bar-actions">
                    <button type="button" class="btn-reflection-action" id="open-ai-suggest-btn">
                        <span>✨</span>
                        <span>Nhờ Mây gợi ý</span>
                    </button>
                    <button type="button" class="btn-reflection-action" id="open-reflection-modal-btn">
                        <span>📝</span>
                        <span>Nhìn lại điều vừa học</span>
                    </button>
                </div>
            </div>
        </main>
    </div>

    <!-- KHỐI COLLAPSIBLE: DỮ LIỆU THAM KHẢO TỪ CÁC BÀI ĐÁNH GIÁ ĐÃ LÀM -->
    <details id="assessment-insights" class="card no-print" style="margin-top: var(--space-8); border: 1.5px solid var(--border); box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);">
        <summary style="cursor: pointer; padding: var(--space-2) 0; display: flex; align-items: center; justify-content: space-between; list-style: none;">
            <div style="display: flex; align-items: center; gap: var(--space-3);">
                <span style="font-size: 1.5rem;" aria-hidden="true">🧭</span>
                <div>
                    <strong style="font-size: var(--text-base); color: var(--text);">Dữ liệu tham khảo từ các bài kiểm tra đã làm</strong>
                    <span class="badge" style="background: var(--primary-light); color: var(--primary); font-size: var(--text-xs); margin-left: var(--space-2); font-weight: 700;">
                        <?= $assessmentInsights['tests_count'] ?> nguồn dữ liệu
                    </span>
                </div>
            </div>
            <span style="font-size: var(--text-xs); color: var(--primary); font-weight: 700;">Xem chi tiết & Gợi ý ▼</span>
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
                        <p style="font-size: var(--text-xs); color: var(--muted); margin: 0;">Chưa có kết quả kiểm tra. Hãy làm bài đánh giá để nhận diện phong cách học tập/làm việc.</p>
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

            <!-- Gợi ý cột mốc hành động từ bài đánh giá -->
            <?php if (!empty($assessmentInsights['recommended_milestones'])): ?>
            <div style="margin-top: var(--space-4);">
                <div class="flex flex-wrap items-center justify-between gap-2" style="margin-bottom: var(--space-3);">
                    <h4 style="font-size: var(--text-sm); font-weight: 700; margin: 0; color: var(--text);">📋 Gợi ý phù hợp từ kết quả bài làm:</h4>
                    <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin: 0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="apply_all_suggestions">
                        <input type="hidden" name="stage" value="<?= $activeStage ?>">
                        <button type="submit" class="btn btn-primary" style="font-size: var(--text-xs); padding: 5px 12px;">
                            📥 Thêm tất cả gợi ý vào lộ trình
                        </button>
                    </form>
                </div>

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
                                    <a href="<?= url($rm['action_url']) ?>" class="btn btn-outline" style="font-size: var(--text-xs); padding: 3px 8px;">Xem →</a>
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
                                        + Thêm
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </details>

    <!-- KHỐI COLLAPSIBLE: HỒ SƠ ĐA CHIỀU THAM KHẢO -->
    <details id="profile-summary" class="card no-print" style="margin-top: var(--space-4); border: 1px solid var(--border);">
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
            <span style="font-size: var(--text-xs); color: var(--primary); font-weight: 700;">Xem hồ sơ ▼</span>
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
                    <p style="font-size: var(--text-xs); color: var(--text); margin: 0 0 var(--space-2);"><?= count($context['experiences']) ?> trải nghiệm được tổng hợp.</p>
                    <a href="<?= url('index.php?page=simulations') ?>" style="font-size: var(--text-xs); color: var(--primary);">Mở phòng trải nghiệm nghề →</a>
                </div>
            </div>
            <p style="margin-top: var(--space-3); font-size: var(--text-xs); color: var(--muted); line-height: var(--leading-relaxed);">
                Các kết quả chỉ hỗ trợ khám phá, không phải thước đo cố định về con người bạn. Bạn hoàn toàn có thể thay đổi và phát triển thêm theo thời gian.
            </p>
        </div>
    </details>

    <!-- GÓC NGƯỜI TRONG NGHỀ (CAREER CONVERSATIONS) -->
    <?php require __DIR__ . '/career-conversations-view.php'; ?>

    <!-- KHỐI CHAT ĐỊNH HƯỚNG NGHỀ NGHIỆP -->
    <section id="career-chat" class="card no-print" aria-labelledby="chat-title" style="margin-top: var(--space-6); border: 1px solid var(--border);">
        <h2 id="chat-title" style="margin-top: 0; font-size: var(--text-xl); font-weight: 700; color: var(--text);">
            <?= e($scenario ? 'Cùng khám phá: ' . $scenario['title'] : 'Trao đổi với trợ lý định hướng') ?>
        </h2>
        <p style="font-size: var(--text-xs); color: var(--muted); margin: var(--space-1) 0 var(--space-2); line-height: var(--leading-relaxed);">
            Trợ lý định hướng hỗ trợ trả lời thắc mắc về lộ trình môn học, trải nghiệm thực tế hoặc giải tỏa áp lực. Khi không có kết nối AI ngoài, hệ thống dùng phản hồi sư phạm chuẩn mực.
        </p>
        <p style="font-size: var(--text-xs); color: var(--text); margin-bottom: var(--space-4);">
            <strong><?= max(0, 10 - $used) ?> / 10 lượt còn lại hôm nay</strong> · Đặt lại lúc 00:00 theo giờ Việt Nam.
        </p>

        <div class="chat-history" aria-label="Lịch sử hội thoại" style="margin-bottom: var(--space-4); display: flex; flex-direction: column; gap: var(--space-3);">
            <?php if (!$messages): ?>
                <p style="font-size: var(--text-sm); color: var(--muted); font-style: italic;">Bắt đầu bằng một câu hỏi về mục tiêu, tổ hợp môn hoặc việc nên làm tiếp theo.</p>
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
            <?php foreach (($scenario ? career_conversation_prompts($scenario) : ['Tôi nên tập trung môn học nào cho lộ trình này?', 'Holland và Big Five của tôi có ý nghĩa gì với nghề này?', 'Gợi ý thử thách 15 phút thực tế cho tôi.']) as $chip): ?>
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

<!-- ==================== MODALS & DRAWERS ==================== -->

<!-- 1. Modal Sửa Mục Tiêu (Vùng A) -->
<div class="roadmap-modal-backdrop" id="goal-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="goal-modal-title">
    <div class="roadmap-modal">
        <div class="roadmap-modal-header">
            <h3 class="roadmap-modal-title" id="goal-modal-title">✏️ Chỉnh sửa mục tiêu & hướng đi</h3>
            <button type="button" class="roadmap-modal-close" onclick="closeGoalModal()" aria-label="Đóng">&times;</button>
        </div>
        <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_goal">
            <input type="hidden" name="stage" value="<?= $activeStage ?>">

            <div class="form-group" style="margin-bottom: var(--space-3);">
                <label for="input-goal-title" style="font-size: var(--text-xs); font-weight: 700;">Mục tiêu của bạn *</label>
                <input class="input" id="input-goal-title" name="goal_title" value="<?= e($roadmap['goal_title']) ?>" required maxlength="255" style="width: 100%;">
                <small style="font-size: 0.72rem; color: var(--muted);">Ví dụ: Khám phá nghề Nhà báo / Biên tập viên, hoặc Xây dựng nền tảng hướng nghiệp tương lai.</small>
            </div>

            <div class="form-group" style="margin-bottom: var(--space-3);">
                <label for="input-career-title" style="font-size: var(--text-xs); font-weight: 700;">Nghề đích / Lĩnh vực quan tâm (Tùy chọn)</label>
                <input class="input" id="input-career-title" name="target_career_title" value="<?= e($roadmap['target_career_title']) ?>" placeholder="Ví dụ: Nhà báo, Lập trình viên, Bác sĩ..." maxlength="200" style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: var(--space-3);">
                <label for="input-timeframe" style="font-size: var(--text-xs); font-weight: 700;">Khung thời gian dự kiến</label>
                <input class="input" id="input-timeframe" name="target_timeframe" value="<?= e($roadmap['target_timeframe']) ?>" placeholder="Ví dụ: Lớp 10 - 12, Học kỳ này..." maxlength="100" style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: var(--space-4);">
                <label for="input-notes" style="font-size: var(--text-xs); font-weight: 700;">Động lực / Ghi chú cá nhân</label>
                <textarea class="input" id="input-notes" name="notes" rows="2" maxlength="2000" placeholder="Điều thôi thúc bạn muốn hoàn thành kế hoạch này..." style="width: 100%;"><?= e($roadmap['notes']) ?></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: var(--space-2);">
                <button type="button" class="btn btn-outline" onclick="closeGoalModal()" style="font-size: var(--text-xs);">Hủy</button>
                <button type="submit" class="btn btn-primary" style="font-size: var(--text-xs);">Lưu mục tiêu</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Modal Tự Thêm Công Việc (Custom Task) -->
<div class="roadmap-modal-backdrop" id="custom-task-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="custom-task-title">
    <div class="roadmap-modal">
        <div class="roadmap-modal-header">
            <h3 class="roadmap-modal-title" id="custom-task-title">✏️ Tự viết việc cần làm</h3>
            <button type="button" class="roadmap-modal-close" onclick="closeCustomTaskModal()" aria-label="Đóng">&times;</button>
        </div>
        <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_milestone">
            <input type="hidden" name="stage" value="<?= $activeStage ?>">
            <input type="hidden" name="source" value="custom">

            <p style="font-size: var(--text-xs); color: var(--muted); margin: 0 0 var(--space-3);">
                Thêm vào: <strong><?= e($activeStageMeta['title']) ?></strong>
            </p>

            <div class="form-group" style="margin-bottom: var(--space-3);">
                <label for="custom-task-name" style="font-size: var(--text-xs); font-weight: 700;">Tên công việc / Cột mốc *</label>
                <input class="input" id="custom-task-name" name="title" required maxlength="255" placeholder="Ví dụ: Đọc và so sánh 2 bài viết về nghề..." style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: var(--space-4);">
                <label for="custom-task-desc" style="font-size: var(--text-xs); font-weight: 700;">Chi tiết cách thực hiện hoặc kết quả dự kiến</label>
                <textarea class="input" id="custom-task-desc" name="description" rows="3" maxlength="2000" placeholder="Ví dụ: Ghi lại 2 điểm khác biệt và điều mình thích vào sổ tay..." style="width: 100%;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: var(--space-2);">
                <button type="button" class="btn btn-outline" onclick="closeCustomTaskModal()" style="font-size: var(--text-xs);">Hủy</button>
                <button type="submit" class="btn btn-primary" style="font-size: var(--text-xs);">Thêm vào danh sách</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Modal Chọn Từ Thư Viện Gợi Ý Có Sẵn (Templates) -->
<div class="roadmap-modal-backdrop" id="template-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="template-modal-title">
    <div class="roadmap-modal" style="max-width: 650px;">
        <div class="roadmap-modal-header">
            <div>
                <h3 class="roadmap-modal-title" id="template-modal-title">💡 Gợi ý mẫu cho <?= e($activeStageMeta['title']) ?></h3>
                <p style="margin: 2px 0 0; font-size: var(--text-xs); color: var(--muted);">Chọn những việc phù hợp với bạn để thêm vào kế hoạch.</p>
            </div>
            <button type="button" class="roadmap-modal-close" onclick="closeTemplateModal()" aria-label="Đóng">&times;</button>
        </div>

        <div style="max-height: 60vh; overflow-y: auto; padding-right: 4px;">
            <?php 
            $stageTemplates = $libraryMilestones[$activeStage] ?? [];
            if ($stageTemplates): 
                foreach ($stageTemplates as $tpl): 
            ?>
            <div class="template-item-card">
                <div style="flex: 1;">
                    <h4 class="template-item-title"><?= e($tpl['title']) ?></h4>
                    <p class="template-item-desc"><?= e($tpl['description']) ?></p>
                    <?php if (!empty($tpl['expected_outcome'])): ?>
                        <div style="font-size: 0.72rem; color: var(--primary); font-weight: 600;">
                            🎯 Kết quả: <?= e($tpl['expected_outcome']) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin: 0; flex-shrink: 0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_milestone">
                    <input type="hidden" name="stage" value="<?= $activeStage ?>">
                    <input type="hidden" name="title" value="<?= e($tpl['title']) ?>">
                    <input type="hidden" name="description" value="<?= e($tpl['description']) ?>">
                    <input type="hidden" name="action_url" value="<?= e($tpl['action_url']) ?>">
                    <input type="hidden" name="source" value="suggested">
                    <button type="submit" class="btn btn-outline" style="font-size: var(--text-xs); padding: 5px 12px; white-space: nowrap;">
                        + Chọn việc này
                    </button>
                </form>
            </div>
            <?php 
                endforeach; 
            else: 
            ?>
                <p style="font-size: var(--text-sm); color: var(--muted); text-align: center; padding: var(--space-4);">Chưa có mẫu cho giai đoạn này.</p>
            <?php endif; ?>
        </div>

        <div style="margin-top: var(--space-4); display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-outline" onclick="closeTemplateModal()" style="font-size: var(--text-xs);">Đóng</button>
        </div>
    </div>
</div>

<!-- 4. Modal Chỉnh Sửa Công Việc Hiện Có -->
<div class="roadmap-modal-backdrop" id="edit-task-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="edit-task-title">
    <div class="roadmap-modal">
        <div class="roadmap-modal-header">
            <h3 class="roadmap-modal-title" id="edit-task-title">✏️ Chỉnh sửa công việc</h3>
            <button type="button" class="roadmap-modal-close" onclick="closeEditTaskModal()" aria-label="Đóng">&times;</button>
        </div>
        <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_milestone">
            <input type="hidden" name="milestone_id" id="edit-task-id" value="">
            <input type="hidden" name="stage" value="<?= $activeStage ?>">

            <div class="form-group" style="margin-bottom: var(--space-3);">
                <label for="edit-task-name" style="font-size: var(--text-xs); font-weight: 700;">Tên công việc *</label>
                <input class="input" id="edit-task-name" name="title" required maxlength="255" style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: var(--space-4);">
                <label for="edit-task-desc" style="font-size: var(--text-xs); font-weight: 700;">Ghi chú / Cách làm</label>
                <textarea class="input" id="edit-task-desc" name="description" rows="3" maxlength="2000" style="width: 100%;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: var(--space-2);">
                <button type="button" class="btn btn-outline" onclick="closeEditTaskModal()" style="font-size: var(--text-xs);">Hủy</button>
                <button type="submit" class="btn btn-primary" style="font-size: var(--text-xs);">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>

<!-- 5. Modal AI Gợi Ý Cho Giai Đoạn Này (On-demand) -->
<div class="roadmap-modal-backdrop" id="ai-suggest-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="ai-suggest-title">
    <div class="roadmap-modal" style="max-width: 600px;">
        <div class="roadmap-modal-header">
            <div>
                <h3 class="roadmap-modal-title" id="ai-suggest-title">✨ Mây gợi ý cho <?= e($activeStageMeta['title']) ?></h3>
                <p style="margin: 2px 0 0; font-size: var(--text-xs); color: var(--muted);">Dựa trên mục tiêu "<?= e($roadmap['target_career_title'] ?: $roadmap['goal_title']) ?>" của bạn.</p>
            </div>
            <button type="button" class="roadmap-modal-close" onclick="closeAiSuggestModal()" aria-label="Đóng">&times;</button>
        </div>

        <div id="ai-suggest-body">
            <div id="ai-suggest-loading" style="display: none; padding: var(--space-6); text-align: center;">
                <div style="font-size: 1.5rem; margin-bottom: var(--space-2);" aria-hidden="true">⏳</div>
                <p style="font-size: var(--text-sm); font-weight: 600; color: var(--primary); margin: 0;">Mây đang phân tích hồ sơ và tạo gợi ý phù hợp...</p>
            </div>
            <div id="ai-suggest-result"></div>
        </div>

        <div style="margin-top: var(--space-4); display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: var(--space-3);">
            <button type="button" class="btn btn-outline" id="btn-re-generate-ai" style="font-size: var(--text-xs);">
                🔄 Sinh lại gợi ý
            </button>
            <button type="button" class="btn btn-outline" onclick="closeAiSuggestModal()" style="font-size: var(--text-xs);">
                Đóng
            </button>
        </div>
    </div>
</div>

<!-- 6. Modal Nhìn Lại Điều Vừa Học (Phản Tư) -->
<div class="roadmap-modal-backdrop" id="reflection-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="reflection-modal-title">
    <div class="roadmap-modal">
        <div class="roadmap-modal-header">
            <h3 class="roadmap-modal-title" id="reflection-modal-title">📝 Nhìn lại điều vừa học</h3>
            <button type="button" class="roadmap-modal-close" onclick="closeReflectionModal()" aria-label="Đóng">&times;</button>
        </div>
        <div style="margin-bottom: var(--space-4);">
            <p style="font-size: var(--text-xs); color: var(--muted); line-height: var(--leading-relaxed); margin: 0 0 var(--space-3);">
                Dành 2 phút ghi chép giúp bạn nhận ra điều gì thực sự hợp với mình và chủ động điều chỉnh kế hoạch:
            </p>
            <div style="background: var(--surface-alt); padding: var(--space-3); border-radius: var(--radius-sm); border: 1px solid var(--border); margin-bottom: var(--space-3); font-size: var(--text-xs); line-height: var(--leading-relaxed);">
                <strong>Câu hỏi gợi ý:</strong>
                <ul style="margin: 4px 0 0; padding-left: var(--space-4);">
                    <li>Điều gì khiến bạn bất ngờ nhất sau khi làm các công việc gần đây?</li>
                    <li>Có điểm nào bạn cảm thấy chưa thoải mái hoặc muốn tránh?</li>
                    <li>Bạn có muốn thay đổi hoặc bổ sung mục tiêu nào cho bước tiếp theo không?</li>
                </ul>
            </div>
            <textarea class="input" id="reflection-input" rows="4" maxlength="2000" placeholder="Viết vài dòng cảm nhận hoặc điều chỉnh bạn muốn thực hiện..." style="width: 100%;"></textarea>
            <p style="font-size: 0.72rem; color: var(--muted); margin-top: 4px;">Ghi chép lưu tạm trong phiên hoặc bạn có thể copy để trao đổi cùng thầy cô/gia đình.</p>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: var(--space-2);">
            <button type="button" class="btn btn-outline" onclick="closeReflectionModal()" style="font-size: var(--text-xs);">Đóng</button>
            <button type="button" class="btn btn-primary" id="btn-save-reflection" style="font-size: var(--text-xs);">Ghi nhận phản tư</button>
        </div>
    </div>
</div>

<!-- JAVASCRIPT LOGIC CHO TOÀN BỘ TƯƠNG TÁC LỘ TRÌNH -->
<script>
// 1. In lộ trình
document.getElementById('print-roadmap-btn')?.addEventListener('click', () => window.print());

// 2. Dropdown "+ Thêm công việc"
const addTaskToggle = document.getElementById('btn-add-task-toggle');
const addTaskMenu = document.getElementById('add-task-menu-dropdown');
if (addTaskToggle && addTaskMenu) {
    addTaskToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = addTaskMenu.classList.toggle('is-open');
        addTaskToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', () => {
        addTaskMenu.classList.remove('is-open');
        addTaskToggle.setAttribute('aria-expanded', 'false');
    });
}

// 3. Modals Open/Close Handlers
function openGoalModal() {
    document.getElementById('goal-modal-backdrop')?.classList.add('is-open');
    document.getElementById('input-goal-title')?.focus();
}
function closeGoalModal() {
    document.getElementById('goal-modal-backdrop')?.classList.remove('is-open');
}
document.getElementById('open-goal-modal-btn')?.addEventListener('click', openGoalModal);

function openCustomTaskModal() {
    document.getElementById('add-task-menu-dropdown')?.classList.remove('is-open');
    document.getElementById('custom-task-modal-backdrop')?.classList.add('is-open');
    document.getElementById('custom-task-name')?.focus();
}
function closeCustomTaskModal() {
    document.getElementById('custom-task-modal-backdrop')?.classList.remove('is-open');
}
document.getElementById('btn-open-custom-task-modal')?.addEventListener('click', openCustomTaskModal);

function openTemplateModal() {
    document.getElementById('add-task-menu-dropdown')?.classList.remove('is-open');
    document.getElementById('template-modal-backdrop')?.classList.add('is-open');
}
function closeTemplateModal() {
    document.getElementById('template-modal-backdrop')?.classList.remove('is-open');
}
document.getElementById('btn-open-template-modal')?.addEventListener('click', openTemplateModal);

function openEditTaskModal(id, title, desc) {
    const backdrop = document.getElementById('edit-task-modal-backdrop');
    if (!backdrop) return;
    document.getElementById('edit-task-id').value = id;
    document.getElementById('edit-task-name').value = title;
    document.getElementById('edit-task-desc').value = desc || '';
    backdrop.classList.add('is-open');
    document.getElementById('edit-task-name').focus();
}
function closeEditTaskModal() {
    document.getElementById('edit-task-modal-backdrop')?.classList.remove('is-open');
}

document.querySelectorAll('.edit-task-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        openEditTaskModal(btn.dataset.id, btn.dataset.title, btn.dataset.desc);
    });
});

function openAiSuggestModal() {
    document.getElementById('ai-suggest-modal-backdrop')?.classList.add('is-open');
    fetchAiSuggestions();
}
function closeAiSuggestModal() {
    document.getElementById('ai-suggest-modal-backdrop')?.classList.remove('is-open');
}
document.getElementById('open-ai-suggest-btn')?.addEventListener('click', openAiSuggestModal);

function openReflectionModal() {
    document.getElementById('reflection-modal-backdrop')?.classList.add('is-open');
    document.getElementById('reflection-input')?.focus();
}
function closeReflectionModal() {
    document.getElementById('reflection-modal-backdrop')?.classList.remove('is-open');
}
document.getElementById('open-reflection-modal-btn')?.addEventListener('click', openReflectionModal);

// Close on Escape or click backdrop
document.querySelectorAll('.roadmap-modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) backdrop.classList.remove('is-open');
    });
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.roadmap-modal-backdrop.is-open').forEach(b => b.classList.remove('is-open'));
    }
});

// Quick start goal buttons
document.querySelectorAll('.quick-start-goal-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const mode = btn.dataset.mode;
        openGoalModal();
        if (mode === 'career') {
            document.getElementById('input-career-title').focus();
        } else {
            document.getElementById('input-goal-title').value = 'Khám phá và nhận diện hướng đi phù hợp';
            document.getElementById('input-career-title').value = '';
            document.getElementById('input-goal-title').focus();
        }
    });
});

// Trigger next action if special modal
document.getElementById('next-action-trigger-btn')?.addEventListener('click', () => {
    openGoalModal();
});

// 4. Milestone Checkbox Optimistic Toggle with Rollback
document.querySelectorAll('.task-toggle-check').forEach(chk => {
    chk.addEventListener('change', async function() {
        const id = this.dataset.id;
        const card = document.getElementById('task-card-' + id);
        const originallyChecked = !this.checked; // previous state
        const isNowCompleted = this.checked;

        // 1. Optimistic UI Update
        if (card) {
            if (isNowCompleted) {
                card.classList.add('is-completed');
            } else {
                card.classList.remove('is-completed');
            }
        }

        const csrfToken = document.querySelector('input[name="csrf"]')?.value || '';
        const formData = new FormData();
        formData.append('action', 'toggle_milestone');
        formData.append('milestone_id', id);
        formData.append('stage', '<?= $activeStage ?>');
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
                // Update progress bar
                if (result.data.progress) {
                    const p = result.data.progress;
                    const pText = document.getElementById('progress-text');
                    const pFill = document.getElementById('progress-bar-fill');
                    if (pText) pText.textContent = `${p.percent}% (Đã hoàn thành ${p.completed} / ${p.total} cột mốc)`;
                    if (pFill) pFill.style.width = `${p.percent}%`;
                }
            } else {
                // Rollback on server error
                this.checked = originallyChecked;
                if (card) card.classList.toggle('is-completed', originallyChecked);
                alert(result.message || 'Chưa thể cập nhật trạng thái. Vui lòng thử lại.');
            }
        } catch (err) {
            // Rollback on network failure
            this.checked = originallyChecked;
            if (card) card.classList.toggle('is-completed', originallyChecked);
            alert('Lỗi kết nối mạng khi cập nhật cột mốc. Vui lòng thử lại.');
        }
    });
});

// 5. Fetch AI Suggestions on demand
async function fetchAiSuggestions() {
    const loading = document.getElementById('ai-suggest-loading');
    const resultBox = document.getElementById('ai-suggest-result');
    if (!resultBox) return;

    if (loading) loading.style.display = 'block';
    resultBox.innerHTML = '';

    try {
        const csrfToken = document.querySelector('input[name="csrf"]')?.value || '';
        const formData = new FormData();
        formData.append('action', 'ai_suggest_milestones');
        formData.append('stage', '<?= $activeStage ?>');
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
            let html = '<div style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">';
            data.data.suggestions.forEach(item => {
                html += `
                    <div class="template-item-card">
                        <div style="flex: 1;">
                            <h4 class="template-item-title">${item.title}</h4>
                            <p class="template-item-desc">${item.description}</p>
                            <span class="task-source-badge badge-source-ai" style="margin-top: 4px; display: inline-block;">Gợi ý phù hợp mục tiêu</span>
                        </div>
                        <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin: 0; flex-shrink: 0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_milestone">
                            <input type="hidden" name="stage" value="${item.stage || '<?= $activeStage ?>'}">
                            <input type="hidden" name="title" value="${item.title}">
                            <input type="hidden" name="description" value="${item.description}">
                            <input type="hidden" name="action_url" value="${item.action_url || ''}">
                            <input type="hidden" name="source" value="ai">
                            <button type="submit" class="btn btn-primary" style="font-size: var(--text-xs); padding: 5px 12px; white-space: nowrap;">
                                + Thêm
                            </button>
                        </form>
                    </div>
                `;
            });
            html += '</div>';
            resultBox.innerHTML = html;
        } else {
            resultBox.innerHTML = '<p style="color: var(--danger); font-size: var(--text-xs); text-align: center; padding: 12px;">Chưa thể sinh gợi ý lúc này. Hãy thử lại.</p>';
        }
    } catch (e) {
        resultBox.innerHTML = '<p style="color: var(--danger); font-size: var(--text-xs); text-align: center; padding: 12px;">Đã xảy ra lỗi kết nối. Hãy thử lại.</p>';
    } finally {
        if (loading) loading.style.display = 'none';
    }
}
document.getElementById('btn-re-generate-ai')?.addEventListener('click', fetchAiSuggestions);

// 6. Reflection save note (localStorage or feedback)
document.getElementById('btn-save-reflection')?.addEventListener('click', () => {
    const text = document.getElementById('reflection-input')?.value.trim();
    if (!text) {
        alert('Hãy viết vài dòng cảm nhận trước khi ghi nhận.');
        return;
    }
    try {
        localStorage.setItem('jobsimu_roadmap_reflection_<?= $userId ?>', JSON.stringify({
            text: text,
            stage: '<?= $activeStage ?>',
            saved_at: new Date().toISOString()
        }));
    } catch(e) {}
    alert('Đã ghi nhận phản tư của bạn. Bạn có thể xem lại bất kỳ lúc nào!');
    closeReflectionModal();
});

// 7. Chat prompts
document.querySelectorAll('[data-chat-prompt]').forEach(button => {
    button.addEventListener('click', () => {
        const field = document.getElementById('career-question');
        if (field) {
            field.value = button.dataset.chatPrompt;
            field.focus();
        }
    });
});

document.getElementById('career-chat-form')?.addEventListener('submit', function() {
    const btn = this.querySelector('button[type=submit]');
    if (btn) btn.disabled = true;
    const status = document.getElementById('chat-send-status');
    if (status) status.textContent = 'Đang tổng hợp hồ sơ và trả lời…';
});
</script>
<?php require __DIR__ . '/footer.php'; exit;
