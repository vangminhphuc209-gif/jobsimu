<?php
declare(strict_types=1);
require_login();
require_once __DIR__ . '/four-hubs-ai.php';
require_once __DIR__ . '/user-roadmap.php';

$userId = (int)user()['id'];

// ==================== CONTROLLER: POST ACTIONS ====================
if (is_post() && $page === 'ai-roadmap') {
    $action = $_POST['action'] ?? '';
    if ($action !== '') {
        verify_csrf();
        $roadmap = user_roadmap_get_or_create($userId);
        $roadmapId = (int)$roadmap['id'];
        $currentMonthParam = trim((string)($_POST['month'] ?? ''));
        $monthRedirect = ($currentMonthParam !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $currentMonthParam)) ? $currentMonthParam : ($roadmap['start_month'] ?: date('Y-m'));
        $redirectUrl = 'index.php?page=ai-roadmap&month=' . urlencode($monthRedirect) . '#user-roadmap';

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        try {
            // 1. Setup / Reset Plan
            if ($action === 'create_or_reset_plan' || $action === 'update_plan_settings') {
                user_roadmap_update_details($roadmapId, $userId, $_POST);
                if (!empty($_POST['custom_monthly_goals']) && is_array($_POST['custom_monthly_goals'])) {
                    $updGoals = db()->prepare('UPDATE user_roadmaps SET monthly_goals=? WHERE id=? AND user_id=?');
                    $updGoals->execute([json_encode($_POST['custom_monthly_goals'], JSON_UNESCAPED_UNICODE), $roadmapId, $userId]);
                }
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok', 'message' => 'Đã lưu thiết lập kế hoạch thành công.']);
                    exit;
                }
                flash('success', 'Đã cập nhật kế hoạch hướng nghiệp của bạn.');
                redirect($redirectUrl);
            }

            // 2. Update Month Goal
            elseif ($action === 'update_month_goal') {
                $targetMonth = trim((string)($_POST['month_key'] ?? ''));
                $goalText = trim((string)($_POST['goal_text'] ?? ''));
                if ($targetMonth !== '') {
                    user_roadmap_update_month_goal($roadmapId, $userId, $targetMonth, $goalText);
                }
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok']);
                    exit;
                }
                flash('success', 'Đã cập nhật mục tiêu của tháng.');
                redirect($redirectUrl);
            }

            // 3. Add Task
            elseif ($action === 'add_task' || $action === 'add_milestone') {
                $newId = user_roadmap_add_task($roadmapId, $userId, $_POST);
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok', 'id' => $newId]);
                    exit;
                }
                flash('success', 'Đã thêm công việc mới vào kế hoạch.');
                redirect($redirectUrl);
            }

            // 4. Edit Task
            elseif ($action === 'edit_task' || $action === 'edit_milestone') {
                $tid = (int)($_POST['task_id'] ?? $_POST['milestone_id'] ?? 0);
                user_roadmap_update_task($tid, $userId, $_POST);
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok']);
                    exit;
                }
                flash('success', 'Đã cập nhật thông tin công việc.');
                redirect($redirectUrl);
            }

            // 5. Move Task Month
            elseif ($action === 'move_task_month') {
                $tid = (int)($_POST['task_id'] ?? 0);
                $newMonth = trim((string)($_POST['new_target_month'] ?? ''));
                $keepDueDate = !empty($_POST['keep_due_date']);
                $newDueDate = isset($_POST['new_due_date']) ? trim((string)$_POST['new_due_date']) : null;
                user_roadmap_move_task_month($tid, $userId, $newMonth ?: null, $keepDueDate, $newDueDate);
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok']);
                    exit;
                }
                flash('success', 'Đã chuyển công việc sang tháng mới.');
                redirect($redirectUrl);
            }

            // 6. Toggle / Set Task Status
            elseif ($action === 'toggle_task_status' || $action === 'toggle_milestone') {
                $tid = (int)($_POST['task_id'] ?? $_POST['milestone_id'] ?? 0);
                $targetStatus = !empty($_POST['status']) ? trim((string)$_POST['status']) : null;
                $res = user_roadmap_toggle_task_status($tid, $userId, $targetStatus);
                if ($isAjax) {
                    $monthData = user_roadmap_get_by_month($roadmapId, $userId, $roadmap, $currentMonthParam);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'status' => 'ok',
                        'data' => $res,
                        'month_progress' => $monthData['grouped'][$currentMonthParam]['percent'] ?? 0,
                        'overall_percent' => $monthData['overall_percent'],
                        'completed_all' => $monthData['completed_all'],
                        'total_all' => $monthData['total_all'],
                    ]);
                    exit;
                }
                flash('success', $res['is_completed'] ? 'Đã đánh dấu hoàn thành công việc!' : 'Đã mở lại công việc.');
                redirect($redirectUrl);
            }

            // 7. Record Actual Outcome
            elseif ($action === 'record_outcome') {
                $tid = (int)($_POST['task_id'] ?? 0);
                $outcomeText = trim((string)($_POST['actual_outcome'] ?? ''));
                $upd = db()->prepare('UPDATE user_roadmap_milestones SET actual_outcome=? WHERE id=? AND user_id=?');
                $upd->execute([$outcomeText ?: null, $tid, $userId]);
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok']);
                    exit;
                }
                flash('success', 'Đã ghi nhận kết quả thực tế.');
                redirect($redirectUrl);
            }

            // 8. Delete Task
            elseif ($action === 'delete_task' || $action === 'delete_milestone') {
                $tid = (int)($_POST['task_id'] ?? $_POST['milestone_id'] ?? 0);
                user_roadmap_delete_milestone($tid, $userId);
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok']);
                    exit;
                }
                flash('success', 'Đã xóa công việc khỏi kế hoạch.');
                redirect($redirectUrl);
            }

            // 9. Save Monthly Reflection
            elseif ($action === 'save_monthly_reflection') {
                $targetMonth = trim((string)($_POST['reflection_month'] ?? ''));
                $answers = [
                    'achieved' => $_POST['reflection_achieved'] ?? '',
                    'insight' => $_POST['reflection_insight'] ?? '',
                    'adjust' => $_POST['reflection_adjust'] ?? '',
                ];
                $decisions = is_array($_POST['task_decisions'] ?? null) ? $_POST['task_decisions'] : [];
                user_roadmap_save_monthly_reflection($roadmapId, $userId, $targetMonth, $answers, $decisions);
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok', 'message' => 'Đã lưu phản tư tháng thành công!']);
                    exit;
                }
                flash('success', 'Đã lưu nhìn lại tháng và điều chỉnh các việc tiếp theo.');
                redirect($redirectUrl);
            }

            // 10. AI / Smart Suggestions On-Demand
            elseif ($action === 'ai_suggest' || $action === 'ai_suggest_milestones') {
                $type = trim((string)($_POST['suggest_type'] ?? 'month_suggestions'));
                $taskTitle = trim((string)($_POST['task_title'] ?? ''));
                $targetMonth = trim((string)($_POST['target_month'] ?? ''));
                $aiData = user_roadmap_ai_generate_suggestions($userId, $roadmap, $targetMonth, $type, $taskTitle ?: null);
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok', 'data' => $aiData]);
                    exit;
                }
                $_SESSION['roadmap_ai_suggestions'] = $aiData;
                flash('success', 'Đã tạo gợi ý phù hợp cho bạn.');
                redirect($redirectUrl);
            }

            // 11. Schedule Unscheduled Tasks in Bulk
            elseif ($action === 'schedule_unscheduled') {
                $assignments = is_array($_POST['assignments'] ?? null) ? $_POST['assignments'] : [];
                $count = 0;
                foreach ($assignments as $taskId => $assignedMonth) {
                    $tid = (int)$taskId;
                    $mVal = trim((string)$assignedMonth);
                    if ($tid > 0 && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mVal)) {
                        user_roadmap_move_task_month($tid, $userId, $mVal, true);
                        $count++;
                    }
                }
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'ok', 'count' => $count]);
                    exit;
                }
                flash('success', "Đã xếp lịch cho $count công việc vào các tháng.");
                redirect($redirectUrl);
            }

            // Legacy fallback: apply assessment suggestions
            elseif ($action === 'apply_all_suggestions') {
                $insights = user_roadmap_derive_assessment_insights($userId);
                $curM = $roadmap['start_month'] ?: date('Y-m');
                $count = 0;
                foreach ($insights['recommended_milestones'] as $rm) {
                    $rm['target_month'] = $curM;
                    user_roadmap_add_task($roadmapId, $userId, $rm);
                    $count++;
                }
                flash('success', "Đã thêm $count gợi ý từ bài kiểm tra vào kế hoạch của bạn.");
                redirect($redirectUrl);
            }
        } catch (Throwable $e) {
            if ($isAjax) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit;
            }
            flash('error', $e->getMessage());
            redirect($redirectUrl);
        }
    }
}

// Career conversation scenario handling
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
        flash('warning', 'Phiên nhập đã thay đổi. Hãy kiểm tra hội thoại trước khi gửi lại.');
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
        flash('error', $error->getCode() === 429 ? $error->getMessage() : 'Chưa thể hoàn tất câu trả lời.');
    }
    redirect($chatPath);
}

// ==================== DATA LOADING ====================
$context = four_hub_ai_context($userId);
$used = four_hub_chat_count($userId);
$messages = career_conversation_history($userId, $scenario);
if (empty($_SESSION['four_hub_chat_nonce'])) $_SESSION['four_hub_chat_nonce'] = bin2hex(random_bytes(24));
$primaryCareer = $context['suggested_careers'][0] ?? null;

// Roadmap & Month Timeline Data
$roadmap = user_roadmap_get_or_create($userId);
$roadmapId = (int)$roadmap['id'];
$stages = user_roadmap_stages();

// Active month from URL query parameter
$requestedMonth = trim((string)($_GET['month'] ?? ''));
$monthData = user_roadmap_get_by_month($roadmapId, $userId, $roadmap, $requestedMonth ?: null);
$activeMonthKey = $monthData['active_month'];
$activeMonthGroup = $monthData['grouped'][$activeMonthKey] ?? ($monthData['grouped']['unscheduled'] ?? null);

// Next Actions (Vùng B)
$nextActionsData = user_roadmap_get_next_actions($monthData);
$nextTasks = $nextActionsData['tasks'];

// Reference details
$assessmentInsights = user_roadmap_derive_assessment_insights($userId);
$libraryMilestones = user_roadmap_library_milestones($roadmap['target_career_title'] ?? null);

// Filter by stage defaults to empty (showing all tasks of the month)
$filterStage = '';

$pageTitle = 'Kế hoạch hướng nghiệp của tôi · Lộ trình theo tháng';
require __DIR__ . '/header.php';
?>
<!-- Dedicated Roadmap CSS Component Bundle -->
<link rel="stylesheet" href="<?= url('assets/css/roadmap.css') ?>">

<section class="page-head no-print">
    <div class="container" style="max-width: var(--max-w-content);">
        <span class="eyebrow">Kế hoạch cá nhân · Theo thời gian thực</span>
        <h1 style="font-size: var(--text-3xl); font-weight: 800; margin: var(--space-2) 0 var(--space-3); color: var(--text);">Kế hoạch hướng nghiệp của tôi</h1>
        <p style="font-size: var(--text-base); color: var(--muted); max-width: 720px; margin: 0; line-height: var(--leading-relaxed);">
            Kế hoạch hành động cụ thể theo tháng giúp bạn biết rõ mục tiêu, việc cần làm tiếp theo, hạn hoàn thành và kết quả thực tế cần đạt.
        </p>
    </div>
</section>

<div class="roadmap-container">

    <!-- ==================== VÙNG A: PHẦN ĐẦU TRANG ==================== -->
    <section id="user-roadmap" class="roadmap-goal-card" aria-labelledby="roadmap-goal-heading">
        <div class="roadmap-goal-header">
            <div class="roadmap-goal-info">
                <div class="roadmap-goal-meta-badges">
                    <span class="eyebrow" style="color: var(--primary);">Mục tiêu hiện tại</span>
                    <span class="roadmap-goal-timeframe">📅 Khung thời gian: <?= e($roadmap['target_timeframe']) ?></span>
                    <?php if (!empty($roadmap['target_career_title'])): ?>
                        <span class="badge" style="background: var(--primary-light); color: var(--primary); font-weight: 700;">
                            🎯 Hướng tới: <?= e($roadmap['target_career_title']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($roadmap['weekly_hours'])): ?>
                        <span class="badge" style="background: var(--surface-alt); color: var(--text-muted); border: 1px solid var(--border);">
                            ⏱️ <?= (int)$roadmap['weekly_hours'] ?> giờ/tuần
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
                <button type="button" class="btn btn-primary" onclick="openAddTaskModal('<?= e($activeMonthKey) ?>')">
                    + Thêm công việc
                </button>
                <button type="button" class="btn btn-outline" onclick="openSetupModal()">
                    ⚙️ Chỉnh sửa kế hoạch
                </button>
                <button type="button" class="btn btn-outline" id="print-roadmap-btn" title="In kế hoạch ra giấy hoặc lưu PDF">
                    🖨️ In kế hoạch
                </button>
                <button type="button" class="btn btn-outline" onclick="openAiSuggestModal('<?= e($activeMonthKey) ?>')" style="color: var(--primary); border-color: #c7d2fe;">
                    🧭 Trợ lý La Bàn
                </button>
            </div>
        </div>

        <!-- Thanh tiến độ tổng quan -->
        <div class="roadmap-progress-bar-wrap">
            <div class="roadmap-progress-meta">
                <span style="font-weight: 700; color: var(--text);">
                    Tiến độ toàn kế hoạch:
                    <small style="font-weight: normal; color: var(--muted); margin-left: 4px;">(Bao gồm tất cả các tháng và việc chưa xếp lịch)</small>
                </span>
                <span id="overall-progress-text" style="font-weight: 800; color: var(--primary); font-size: 0.9375rem;">
                    <?= $monthData['overall_percent'] ?>% (Đã hoàn thành <?= $monthData['completed_all'] ?> / <?= $monthData['total_all'] ?> việc)
                </span>
            </div>
            <div class="roadmap-progress-track">
                <div id="overall-progress-fill" class="roadmap-progress-fill" style="width: <?= $monthData['overall_percent'] ?>%;"></div>
            </div>
        </div>

        <!-- Thông báo việc chưa xếp lịch nếu có -->
        <?php if ($monthData['unscheduled_count'] > 0 && $activeMonthKey !== 'unscheduled'): ?>
        <div class="unscheduled-notice-banner no-print">
            <div class="banner-text">
                📌 <strong>Bạn có <?= $monthData['unscheduled_count'] ?> công việc chưa được xếp vào tháng cụ thể.</strong> Xếp các việc này vào các tháng giúp bạn theo dõi thời gian và không bỏ sót mục tiêu.
            </div>
            <div style="display: flex; gap: var(--space-2);">
                <a href="<?= url('index.php?page=ai-roadmap&month=unscheduled#user-roadmap') ?>" class="btn btn-outline btn-sm" style="background:#ffffff; color:#92400e; border-color:#fde68a;">
                    Xem việc chưa xếp lịch →
                </a>
                <button type="button" class="btn btn-primary btn-sm" onclick="openScheduleUnscheduledModal()">
                    Xếp lịch nhanh
                </button>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <!-- ==================== VÙNG B: VIỆC CẦN LÀM TIẾP THEO ==================== -->
    <section class="roadmap-next-section no-print" aria-labelledby="next-actions-heading">
        <div class="roadmap-next-section-head">
            <h3 id="next-actions-heading" class="roadmap-next-section-title">
                <span>⚡ Việc cần làm tiếp theo</span>
                <span class="badge" style="background: var(--surface-alt); border: 1px solid var(--border); font-size: var(--text-xs); font-weight: normal; color: var(--muted);">
                    Ưu tiên theo hạn hoàn thành và tính sẵn sàng
                </span>
            </h3>
            <?php if ($nextActionsData['blocked_count'] > 0): ?>
                <span style="font-size: 0.75rem; color: #b45309; background: #fffbeb; padding: 2px 8px; border-radius: 4px; border: 1px solid #fde68a;">
                    ⚠️ Đang có <?= $nextActionsData['blocked_count'] ?> việc chờ hoàn thành việc tiên quyết trước
                </span>
            <?php endif; ?>
        </div>

        <?php if (!empty($nextTasks)): ?>
            <div class="roadmap-next-grid">
                <?php foreach ($nextTasks as $nt): ?>
                <article class="next-action-card <?= $nt['is_overdue'] ? 'is-overdue' : '' ?>" id="next-card-<?= $nt['id'] ?>">
                    <div>
                        <div class="next-action-top">
                            <span class="next-action-month-badge">
                                📅 <?= e($nt['month_label']) ?>
                            </span>
                            <?php if ($nt['is_overdue']): ?>
                                <span class="badge badge-overdue">⚡ Quá hạn</span>
                            <?php elseif (!empty($nt['due_date'])): ?>
                                <span class="badge" style="background: #eef2ff; color: #4338ca; font-weight: 700;">
                                    Hạn: <?= date('d/m/Y', strtotime($nt['due_date'])) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge" style="background: var(--surface-alt); color: var(--muted); border: 1px solid var(--border);">
                                    Chưa đặt hạn
                                </span>
                            <?php endif; ?>
                        </div>

                        <h4 class="next-action-title">
                            <?= e($nt['title']) ?>
                        </h4>

                        <div class="next-action-outcome">
                            <strong>🎯 Đầu ra:</strong> <?= e($nt['expected_outcome']) ?>
                        </div>
                    </div>

                    <div class="next-action-footer">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label class="task-checkbox-container" style="width: 32px; height: 32px;" title="Đánh dấu hoàn thành ngay">
                                <input type="checkbox" 
                                       class="task-checkbox task-toggle-check" 
                                       data-id="<?= $nt['id'] ?>"
                                       aria-label="Đánh dấu hoàn thành: <?= e($nt['title']) ?>">
                            </label>
                            <span style="font-size: 0.72rem; color: var(--muted);"><?= e($nt['estimated_duration'] ?: 'Nhiệm vụ trọng tâm') ?></span>
                        </div>

                        <div>
                            <?php if (!empty($nt['action_url'])): ?>
                                <a href="<?= url($nt['action_url']) ?>" class="btn btn-primary btn-sm">
                                    <?= e($nt['action_label']) ?>
                                </a>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline btn-sm" onclick="openEditTaskModal(<?= $nt['id'] ?>)">
                                    Xem chi tiết →
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php elseif ($nextActionsData['is_all_completed']): ?>
            <div style="background: #ecfdf5; border: 1.5px solid #a7f3d0; border-radius: var(--radius-md); padding: var(--space-5); text-align: center;">
                <span style="font-size: 2rem; display: block; margin-bottom: 4px;" aria-hidden="true">🎉</span>
                <h4 style="margin: 0 0 4px; font-size: 1.05rem; font-weight: 800; color: #065f46;">Chúc mừng bạn! Tất cả công việc trong kế hoạch đã hoàn thành</h4>
                <p style="margin: 0 0 var(--space-3); font-size: var(--text-xs); color: #047857;">Bạn có thể nhìn lại toàn bộ hành trình bên dưới hoặc tạo kế hoạch mới cho giai đoạn tiếp theo.</p>
                <button type="button" class="btn btn-primary btn-sm" onclick="openSetupModal()">
                    Thiết lập mục tiêu mới 🎯
                </button>
            </div>
        <?php else: ?>
            <div style="background: var(--surface-alt); border: 1.5px dashed var(--border); border-radius: var(--radius-md); padding: var(--space-4); text-align: center;">
                <p style="margin: 0; font-size: var(--text-xs); color: var(--muted);">
                    Chưa có công việc nào được xếp lịch. Hãy chọn một tháng bên dưới hoặc nhấn <strong>"+ Thêm công việc"</strong> để bắt đầu xếp lịch.
                </p>
            </div>
        <?php endif; ?>
    </section>

    <!-- ==================== VÙNG C & D: BỐ CỤC 2 CỘT ==================== -->
    <div class="roadmap-layout">

        <!-- CỘT TRÁI (DESKTOP): BỘ ĐIỀU HƯỚNG THEO THÁNG -->
        <aside class="month-sidebar no-print" aria-label="Danh sách tháng trong kế hoạch">
            <div class="month-sidebar-head">
                <h3 class="month-sidebar-title">Kế hoạch theo tháng</h3>
                <span class="badge" style="background: var(--surface-alt); font-size: 0.6875rem; color: var(--muted); border: 1px solid var(--border);">
                    <?= count($monthData['plan_months']) ?> tháng
                </span>
            </div>

            <ul class="month-nav-list" role="tablist">
                <?php foreach ($monthData['plan_months'] as $ym => $pm): 
                    $mGroup = $monthData['grouped'][$ym] ?? ['completed_count' => 0, 'total_count' => 0];
                    $isActive = ($ym === $activeMonthKey);
                ?>
                <li role="presentation">
                    <a href="<?= url('index.php?page=ai-roadmap&month=' . $ym . '#user-roadmap') ?>" 
                       class="month-nav-btn <?= $isActive ? 'is-active' : '' ?>"
                       role="tab"
                       aria-selected="<?= $isActive ? 'true' : 'false' ?>">
                        <div class="month-nav-left">
                            <span class="month-nav-label"><?= e($pm['label']) ?></span>
                            <?php if ($pm['is_current']): ?>
                                <span class="current-month-badge">Tháng này</span>
                            <?php endif; ?>
                        </div>
                        <span class="month-nav-badge">
                            <?= $mGroup['completed_count'] ?> / <?= $mGroup['total_count'] ?>
                        </span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>

            <div class="month-sidebar-divider"></div>

            <!-- Mục Chưa xếp lịch -->
            <?php 
                $unsch = $monthData['grouped']['unscheduled'];
                $isUnschActive = ($activeMonthKey === 'unscheduled');
            ?>
            <a href="<?= url('index.php?page=ai-roadmap&month=unscheduled#user-roadmap') ?>" 
               class="month-nav-btn <?= $isUnschActive ? 'is-active' : '' ?>"
               style="margin-top: 2px;">
                <div class="month-nav-left">
                    <span class="month-nav-label">📋 Chưa xếp lịch</span>
                </div>
                <span class="month-nav-badge" style="<?= $unsch['total_count'] > 0 ? 'background:#fef3c7; color:#92400e; border-color:#fde68a;' : '' ?>">
                    <?= $unsch['total_count'] ?>
                </span>
            </a>
        </aside>

        <!-- BỘ CHỌN THÁNG CHO MOBILE (HIỂN THỊ KHI MÀN HÌNH NHỎ) -->
        <nav class="mobile-month-nav no-print" aria-label="Điều hướng tháng trên di động">
            <div class="mobile-month-scroll">
                <?php foreach ($monthData['plan_months'] as $ym => $pm): 
                    $mGroup = $monthData['grouped'][$ym] ?? ['completed_count' => 0, 'total_count' => 0];
                    $isActive = ($ym === $activeMonthKey);
                ?>
                    <a href="<?= url('index.php?page=ai-roadmap&month=' . $ym . '#user-roadmap') ?>" 
                       class="mobile-month-pill <?= $isActive ? 'is-active' : '' ?>">
                        <span><?= e($pm['short_label']) ?></span>
                        <span class="badge" style="font-size: 0.65rem; padding: 2px 6px;">
                            <?= $mGroup['completed_count'] ?>/<?= $mGroup['total_count'] ?>
                        </span>
                    </a>
                <?php endforeach; ?>
                <a href="<?= url('index.php?page=ai-roadmap&month=unscheduled#user-roadmap') ?>" 
                   class="mobile-month-pill <?= $activeMonthKey === 'unscheduled' ? 'is-active' : '' ?>">
                    <span>Chưa xếp</span>
                    <span class="badge" style="font-size: 0.65rem; padding: 2px 6px;">
                        <?= $monthData['unscheduled_count'] ?>
                    </span>
                </a>
            </div>

            <?php if ($activeMonthKey !== $monthData['current_month'] && isset($monthData['grouped'][$monthData['current_month']])): ?>
            <div class="mobile-jump-current-bar">
                <span style="font-size: 0.72rem; color: var(--muted);">Đang xem: <?= e($activeMonthGroup['meta']['label']) ?></span>
                <a href="<?= url('index.php?page=ai-roadmap&month=' . $monthData['current_month'] . '#user-roadmap') ?>" class="btn btn-outline btn-sm" style="font-size: 0.72rem; padding: 3px 8px;">
                    📅 Về tháng hiện tại
                </a>
            </div>
            <?php endif; ?>
        </nav>

        <!-- CỘT PHẢI: NỘI DUNG THÁNG ĐANG CHỌN -->
        <main class="stage-main-panel" aria-labelledby="month-main-title">
            <!-- Header tháng đang chọn -->
            <header class="month-panel-header">
                <div class="month-panel-top">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span class="eyebrow" style="color: var(--primary);">Nội dung chi tiết</span>
                            <?php if (!empty($activeMonthGroup['meta']['is_current'])): ?>
                                <span class="current-month-badge">Tháng hiện tại</span>
                            <?php endif; ?>
                        </div>
                        <h2 id="month-main-title" class="month-panel-title">
                            <?= e($activeMonthGroup['meta']['label']) ?>
                        </h2>
                    </div>

                    <div>
                        <span class="badge" style="background: var(--surface-alt); border: 1.5px solid var(--border); font-size: var(--text-xs); padding: 6px 14px; font-weight: 700; color: var(--text);">
                            <?php if ($activeMonthGroup['total_count'] > 0): ?>
                                Đã hoàn thành <?= $activeMonthGroup['completed_count'] ?> / <?= $activeMonthGroup['total_count'] ?> việc (<?= $activeMonthGroup['percent'] ?>%)
                            <?php else: ?>
                                Chưa có công việc
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <!-- Mục tiêu tháng: một câu cụ thể -->
                <div class="month-goal-box">
                    <div style="flex: 1;">
                        <div style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #4338ca; margin-bottom: 2px;">
                            🎯 Mục tiêu trọng tâm của tháng:
                        </div>
                        <p class="month-goal-text" id="month-goal-display">
                            <?= e($activeMonthGroup['goal']) ?>
                        </p>
                    </div>
                    <?php if ($activeMonthKey !== 'unscheduled'): ?>
                    <button type="button" class="btn-edit-month-goal no-print" onclick="openEditMonthGoalModal('<?= e($activeMonthKey) ?>')">
                        ✏️ Sửa mục tiêu
                    </button>
                    <?php endif; ?>
                </div>
            </header>

            <!-- Thanh công cụ & Bộ lọc của tháng -->
            <div class="month-toolbar no-print">
                <div class="month-filters-group">
                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--muted); margin-right: 4px;">Lọc:</span>
                    <button type="button" class="filter-pill-btn is-active" data-filter="all">Tất cả</button>
                    <button type="button" class="filter-pill-btn" data-filter="todo">Chưa xong</button>
                    <button type="button" class="filter-pill-btn" data-filter="completed">Đã xong</button>
                    <button type="button" class="filter-pill-btn" data-filter="overdue">Quá hạn</button>

                    <!-- Stage filter dropdown -->
                    <select class="stage-filter-select" id="stage-filter-selector" aria-label="Lọc theo giai đoạn">
                        <option value="">Tất cả giai đoạn</option>
                        <?php foreach ($stages as $sk => $sm): ?>
                            <option value="<?= $sk ?>" <?= ($filterStage === $sk) ? 'selected' : '' ?>>
                                <?= e($sm['icon']) ?> <?= e($sm['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; gap: var(--space-2);">
                    <button type="button" class="btn btn-outline btn-sm" onclick="openTemplateModal('<?= e($activeMonthKey) ?>')">
                        💡 Thêm từ gợi ý
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openAddTaskModal('<?= e($activeMonthKey) ?>')">
                        + Thêm việc cho tháng này
                    </button>
                </div>
            </div>

            <!-- Danh sách công việc của tháng -->
            <div class="tasks-list" id="tasks-list-container">
                <?php if (empty($activeMonthGroup['items'])): ?>
                    <div style="background: var(--surface-alt); border: 1.5px dashed var(--border); border-radius: var(--radius-md); padding: var(--space-8); text-align: center;">
                        <span style="font-size: 2rem; display: block; margin-bottom: var(--space-2);" aria-hidden="true">📝</span>
                        <h4 style="margin: 0 0 var(--space-1); font-size: var(--text-base); font-weight: 700; color: var(--text);">Tháng này chưa có công việc nào</h4>
                        <p style="margin: 0 0 var(--space-4); font-size: var(--text-xs); color: var(--muted); max-width: 420px; margin-left: auto; margin-right: auto; line-height: var(--leading-relaxed);">
                            Bạn có thể thêm công việc mới, chọn từ các gợi ý chuẩn mực hoặc chuyển các công việc từ danh sách chưa xếp lịch sang tháng này.
                        </p>
                        <div style="display: flex; justify-content: center; gap: var(--space-2); flex-wrap: wrap;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="openAddTaskModal('<?= e($activeMonthKey) ?>')">
                                + Thêm việc cho tháng này
                            </button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="openTemplateModal('<?= e($activeMonthKey) ?>')">
                                💡 Chọn từ gợi ý mẫu
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($activeMonthGroup['items'] as $m): 
                        $isCompleted = (bool)$m['is_completed'];
                        $isOverdue = (bool)$m['is_overdue'];
                        $isBlocked = (bool)$m['is_blocked'];
                        $stageMeta = $stages[$m['stage']] ?? ['title' => $m['stage'], 'icon' => '📌'];
                    ?>
                    <article class="task-card <?= $isCompleted ? 'is-completed' : '' ?> <?= $isOverdue ? 'is-overdue' : '' ?>" 
                             id="task-card-<?= $m['id'] ?>"
                             data-status="<?= e($m['status']) ?>"
                             data-stage="<?= e($m['stage']) ?>"
                             data-overdue="<?= $isOverdue ? '1' : '0' ?>"
                             data-completed="<?= $isCompleted ? '1' : '0' ?>">
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

                                    <!-- Status Badge -->
                                    <?php if ($m['status'] === 'completed'): ?>
                                        <span class="task-status-badge status-completed">✓ Hoàn thành</span>
                                    <?php elseif ($m['status'] === 'in_progress'): ?>
                                        <span class="task-status-badge status-in-progress">⏳ Đang làm</span>
                                    <?php else: ?>
                                        <span class="task-status-badge status-todo">Chưa làm</span>
                                    <?php endif; ?>

                                    <!-- Overdue or Due Date -->
                                    <?php if ($isOverdue): ?>
                                        <span class="badge badge-overdue">⚡ Quá hạn (Hạn: <?= date('d/m/Y', strtotime($m['due_date'])) ?>)</span>
                                    <?php elseif (!empty($m['due_date'])): ?>
                                        <span class="badge" style="background: #f1f5f9; color: #334155;">Hạn: <?= date('d/m/Y', strtotime($m['due_date'])) ?></span>
                                    <?php else: ?>
                                        <span class="badge" style="background: var(--surface-alt); color: var(--muted); border: 1px solid var(--border);">Chưa đặt hạn</span>
                                    <?php endif; ?>

                                    <!-- Duration -->
                                    <?php if (!empty($m['estimated_duration'])): ?>
                                        <span class="badge" style="background: var(--surface-alt); color: var(--text-muted); border: 1px solid var(--border);">
                                            ⏱️ <?= e($m['estimated_duration']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <!-- Stage Tag -->
                                    <span class="task-stage-badge">
                                        <?= e($stageMeta['icon']) ?> <?= e($stageMeta['title']) ?>
                                    </span>

                                    <!-- Priority Badge -->
                                    <?php if ($m['priority'] === 'high'): ?>
                                        <span class="task-priority-high">⚡ Ưu tiên cao</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Blocked Prerequisite Notice -->
                                <?php if ($isBlocked): ?>
                                <div class="task-blocked-notice">
                                    <span>⚠️</span>
                                    <span><strong>Bị chặn:</strong> Cần hoàn thành công việc <em>"<?= e($m['blocked_by_title']) ?>"</em> trước khi bắt đầu việc này.</span>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($m['description'])): ?>
                                    <p class="task-desc"><?= e($m['description']) ?></p>
                                <?php endif; ?>

                                <!-- Expected Outcome Box -->
                                <div class="task-outcome-box">
                                    <span class="task-outcome-label">🎯 Kết quả cần đạt:</span>
                                    <span><?= e($m['expected_outcome']) ?></span>
                                </div>

                                <!-- Actual Outcome Box if recorded -->
                                <?php if (!empty($m['actual_outcome'])): ?>
                                <div class="task-actual-outcome-box">
                                    <strong>📝 Kết quả thực tế / Ghi chép của bạn:</strong>
                                    <div><?= e($m['actual_outcome']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Card Footer & Action Buttons -->
                        <div class="task-footer no-print">
                            <div>
                                <?php if (!empty($m['action_url'])): ?>
                                    <a href="<?= url($m['action_url']) ?>" class="task-action-btn">
                                        <?= e($m['action_label']) ?>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                <!-- Move Month Button -->
                                <button type="button" 
                                        class="task-menu-btn" 
                                        onclick="openMoveMonthModal(<?= $m['id'] ?>, '<?= e($m['title']) ?>', '<?= e($m['target_month'] ?? '') ?>', '<?= e($m['due_date'] ?? '') ?>')"
                                        title="Chuyển sang tháng khác">
                                    📅
                                </button>
                                <!-- Record Outcome Button -->
                                <button type="button" 
                                        class="task-menu-btn" 
                                        onclick="openRecordOutcomeModal(<?= $m['id'] ?>, '<?= e($m['title']) ?>', '<?= e($m['actual_outcome'] ?? '') ?>')"
                                        title="Ghi nhận kết quả thực tế / ghi chép">
                                    📝
                                </button>
                                <!-- Edit Button -->
                                <button type="button" 
                                        class="task-menu-btn" 
                                        onclick="openEditTaskModal(<?= $m['id'] ?>)"
                                        title="Chỉnh sửa chi tiết">
                                    ✏️
                                </button>
                                <!-- Delete Button -->
                                <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin: 0;" onsubmit="return confirm('Bạn có chắc muốn xóa công việc này khỏi kế hoạch?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_task">
                                    <input type="hidden" name="task_id" value="<?= $m['id'] ?>">
                                    <input type="hidden" name="month" value="<?= e($activeMonthKey) ?>">
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

            <!-- ==================== KHỐI NHÌN LẠI CUỐI THÁNG ==================== -->
            <?php if ($activeMonthKey !== 'unscheduled'): 
                $reflection = $activeMonthGroup['reflection'] ?? [];
                $incompleteMonthTasks = array_filter($activeMonthGroup['items'], fn($i) => !$i['is_completed']);
            ?>
            <section class="month-reflection-card no-print" aria-labelledby="reflection-title-<?= e($activeMonthKey) ?>">
                <div class="month-reflection-head">
                    <h3 id="reflection-title-<?= e($activeMonthKey) ?>" class="month-reflection-title">
                        <span>📝 Nhìn lại cuối tháng · <?= e($activeMonthGroup['meta']['label']) ?></span>
                    </h3>
                    <span style="font-size: var(--text-xs); color: var(--muted);">
                        Dành 2 phút đúc kết giúp điều chỉnh hướng đi thông minh
                    </span>
                </div>

                <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" id="form-month-reflection">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_monthly_reflection">
                    <input type="hidden" name="reflection_month" value="<?= e($activeMonthKey) ?>">
                    <input type="hidden" name="month" value="<?= e($activeMonthKey) ?>">

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: var(--space-3); margin-bottom: var(--space-4);">
                        <div>
                            <label class="reflection-question-label">1. Tôi đã làm được những gì?</label>
                            <textarea class="input" name="reflection_achieved" rows="2" placeholder="Những việc bạn tự hào đã hoàn thành hoặc các kết quả cụ thể..."><?= e($reflection['achieved'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="reflection-question-label">2. Tôi nhận ra điều gì về bản thân hoặc nghề?</label>
                            <textarea class="input" name="reflection_insight" rows="2" placeholder="Điểm phù hợp, điều bất ngờ hoặc những điều muốn tránh..."><?= e($reflection['insight'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="reflection-question-label">3. Tháng sau tôi muốn điều chỉnh điều gì?</label>
                            <textarea class="input" name="reflection_adjust" rows="2" placeholder="Tăng/giảm nhịp độ, đổi phương pháp học hoặc đổi nghề tìm hiểu..."><?= e($reflection['adjust'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Incomplete Tasks Review in Month -->
                    <?php if (!empty($incompleteMonthTasks)): ?>
                    <div class="incomplete-review-box">
                        <div style="font-size: var(--text-xs); font-weight: 700; color: #92400e; margin-bottom: var(--space-2);">
                            ⏳ Tháng này còn <?= count($incompleteMonthTasks) ?> việc chưa hoàn thành. Hãy chọn cách xử lý:
                        </div>
                        <?php foreach ($incompleteMonthTasks as $it): ?>
                        <div class="incomplete-task-row">
                            <span style="font-size: 0.8125rem; font-weight: 600; color: var(--text); flex: 1; min-width: 220px;">
                                <?= e($it['title']) ?>
                            </span>
                            <div style="display: flex; align-items: center; gap: var(--space-3); font-size: 0.75rem;">
                                <label style="display: inline-flex; align-items: center; gap: 4px; cursor: pointer;">
                                    <input type="radio" name="task_decisions[<?= $it['id'] ?>]" value="keep" checked>
                                    <span>Giữ nguyên</span>
                                </label>
                                <label style="display: inline-flex; align-items: center; gap: 4px; cursor: pointer;">
                                    <input type="radio" name="task_decisions[<?= $it['id'] ?>]" value="move_next">
                                    <span>Chuyển sang tháng sau</span>
                                </label>
                                <label style="display: inline-flex; align-items: center; gap: 4px; cursor: pointer; color: var(--danger);">
                                    <input type="radio" name="task_decisions[<?= $it['id'] ?>]" value="delete">
                                    <span>Xóa khỏi kế hoạch</span>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div style="display: flex; justify-content: flex-end; margin-top: var(--space-4);">
                        <button type="submit" class="btn btn-primary btn-sm">
                            Lưu nhìn lại tháng & Cập nhật kế hoạch
                        </button>
                    </div>
                </form>
            </section>
            <?php endif; ?>

        </main>
    </div>

    <!-- ==================== KHỐI COLLAPSIBLE: DỮ LIỆU BÀI ĐÁNH GIÁ THAM KHẢO ==================== -->
    <details id="assessment-insights" class="card no-print" style="margin-top: var(--space-8); border: 1.5px solid var(--border);">
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
            <span style="font-size: var(--text-xs); color: var(--primary); font-weight: 700;">Xem chi tiết ▼</span>
        </summary>

        <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--border);">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-4);">
                <div style="background: var(--surface-alt); padding: var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--border);">
                    <h4 style="font-size: var(--text-sm); font-weight: 700; color: var(--text); margin-top: 0;">✨ Điểm mạnh & Thiên hướng</h4>
                    <?php if (!empty($assessmentInsights['strengths'])): ?>
                        <ul style="padding-left: var(--space-4); margin: var(--space-2) 0; font-size: var(--text-xs); line-height: var(--leading-relaxed);">
                            <?php foreach ($assessmentInsights['strengths'] as $s): ?>
                                <li><?= e($s) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="font-size: var(--text-xs); color: var(--muted); margin: 0;">Chưa có kết quả trắc nghiệm. Hãy làm bài để nhận diện điểm mạnh.</p>
                    <?php endif; ?>
                </div>

                <div style="background: var(--surface-alt); padding: var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--border);">
                    <h4 style="font-size: var(--text-sm); font-weight: 700; color: var(--text); margin-top: 0;">⚠️ Vùng cần lưu ý & Ranh giới thực tế</h4>
                    <?php if (!empty($assessmentInsights['growth_areas'])): ?>
                        <ul style="padding-left: var(--space-4); margin: var(--space-2) 0; font-size: var(--text-xs); line-height: var(--leading-relaxed);">
                            <?php foreach ($assessmentInsights['growth_areas'] as $g): ?>
                                <li><?= e($g) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="font-size: var(--text-xs); color: var(--muted); margin: 0;">Hồ sơ cân bằng hoặc chưa hoàn thành khảo sát Cổng đánh đổi.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </details>

    <!-- ==================== GÓC NGƯỜI TRONG NGHỀ ==================== -->
    <?php require __DIR__ . '/career-conversations-view.php'; ?>

    <!-- ==================== KHỐI CHAT ĐỊNH HƯỚNG NGHỀ NGHIỆP ==================== -->
    <section id="career-chat" class="card no-print" aria-labelledby="chat-title" style="margin-top: var(--space-6); border: 1px solid var(--border);">
        <h2 id="chat-title" style="margin-top: 0; font-size: var(--text-xl); font-weight: 700; color: var(--text);">
            <?= e($scenario ? 'Cùng khám phá: ' . $scenario['title'] : 'Trao đổi với trợ lý định hướng') ?>
        </h2>
        <p style="font-size: var(--text-xs); color: var(--muted); margin: var(--space-1) 0 var(--space-2); line-height: var(--leading-relaxed);">
            Trợ lý định hướng hỗ trợ giải đáp thắc mắc về lộ trình môn học, trải nghiệm thực tế hoặc giải tỏa áp lực.
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
                            <?= $chat['role'] === 'user' ? 'Bạn' : 'Trợ lý AI · ' . e($chat['model']) ?>
                        </strong>
                        <small style="font-size: 0.7rem; color: var(--muted);"><?= e($chat['created_at']) ?></small>
                    </div>
                    <p style="white-space: pre-wrap; overflow-wrap: anywhere; margin: 0; font-size: var(--text-sm); line-height: var(--leading-relaxed);"><?= e($chat['message']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="flex flex-wrap gap-2" aria-label="Câu hỏi gợi ý" style="margin-bottom: var(--space-4);">
            <?php foreach (($scenario ? career_conversation_prompts($scenario) : ['Tôi nên tập trung môn học nào cho kế hoạch này?', 'Làm sao để hoàn thành mục tiêu tháng hiệu quả?', 'Gợi ý thử thách 15 phút thực tế cho tôi.']) as $chip): ?>
                <button type="button" class="btn btn-outline" data-chat-prompt="<?= e($chip) ?>" style="font-size: var(--text-xs); padding: 4px 10px;"><?= e($chip) ?></button>
            <?php endforeach; ?>
        </div>

        <form method="post" action="<?= url('index.php?page=ai-chat') ?>" class="mt-4" id="career-chat-form">
            <?= csrf_field() ?>
            <input type="hidden" name="chat_nonce" value="<?= e($_SESSION['four_hub_chat_nonce']) ?>">
            <input type="hidden" name="scenario" value="<?= e($scenario['id'] ?? '') ?>">
            <?php if ($scenario): ?>
                <p style="font-size: var(--text-xs); margin-bottom: var(--space-2);"><label><input type="checkbox" name="include_profile" value="1"> Kèm tóm tắt đánh giá của tôi.</label></p>
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

<!-- ==================== MODALS & DIALOGS ==================== -->

<!-- 1. Modal Thiết lập Kế hoạch (Setup Wizard) -->
<div class="roadmap-modal-backdrop" id="setup-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="setup-modal-title">
    <div class="roadmap-modal" style="max-width: 620px;">
        <div class="roadmap-modal-header">
            <div>
                <h3 class="roadmap-modal-title" id="setup-modal-title">⚙️ Thiết lập kế hoạch hướng nghiệp</h3>
                <p style="margin: 2px 0 0; font-size: var(--text-xs); color: var(--muted);">Thiết lập khung thời gian và mục tiêu phù hợp với nhịp độ của bạn.</p>
            </div>
            <button type="button" class="roadmap-modal-close" onclick="closeSetupModal()" aria-label="Đóng">&times;</button>
        </div>

        <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_or_reset_plan">
            <input type="hidden" name="month" value="<?= e($activeMonthKey) ?>">

            <!-- Câu 1: Bạn muốn đạt điều gì? -->
            <div class="form-group" style="margin-bottom: var(--space-3);">
                <label for="setup-goal-title" style="font-size: var(--text-xs); font-weight: 700;">1. Bạn muốn đạt điều gì? *</label>
                <input class="input" id="setup-goal-title" name="goal_title" value="<?= e($roadmap['goal_title']) ?>" required maxlength="255">
                <div style="display: flex; gap: 6px; margin-top: 4px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-outline btn-sm quick-goal-btn" data-val="Khám phá và nhận diện hướng đi phù hợp">
                        🧭 Khám phá hướng phù hợp
                    </button>
                    <button type="button" class="btn btn-outline btn-sm quick-goal-btn" data-val="Định hình lộ trình phát triển nghề <?= e($roadmap['target_career_title'] ?: 'Lập trình viên') ?>">
                        🎯 Định hình nghề cụ thể
                    </button>
                </div>
            </div>

            <!-- Câu 2 & 3: Bắt đầu tháng nào & Kéo dài bao nhiêu tháng -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-3);">
                <div>
                    <label for="setup-start-month" style="font-size: var(--text-xs); font-weight: 700;">2. Bắt đầu vào tháng/năm nào? *</label>
                    <input type="month" class="input" id="setup-start-month" name="start_month" value="<?= e($roadmap['start_month'] ?: date('Y-m')) ?>" required>
                    <small style="font-size: 0.72rem; color: var(--muted);">Mặc định là tháng hiện tại</small>
                </div>
                <div>
                    <label for="setup-duration-months" style="font-size: var(--text-xs); font-weight: 700;">3. Lập kế hoạch trong bao lâu? *</label>
                    <select class="input" id="setup-duration-months" name="duration_months">
                        <option value="3" <?= (int)$roadmap['duration_months'] === 3 ? 'selected' : '' ?>>3 tháng (Khám phá nhanh)</option>
                        <option value="6" <?= (int)$roadmap['duration_months'] === 6 || empty($roadmap['duration_months']) ? 'selected' : '' ?>>6 tháng (1 học kỳ - Phổ biến)</option>
                        <option value="12" <?= (int)$roadmap['duration_months'] === 12 ? 'selected' : '' ?>>12 tháng (1 năm học)</option>
                    </select>
                </div>
            </div>

            <!-- Câu 4 & 5: Giờ/tuần & Nghề quan tâm -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-3);">
                <div>
                    <label for="setup-weekly-hours" style="font-size: var(--text-xs); font-weight: 700;">4. Dành khoảng bao nhiêu giờ/tuần?</label>
                    <select class="input" id="setup-weekly-hours" name="weekly_hours">
                        <option value="2" <?= (int)($roadmap['weekly_hours'] ?? 0) === 2 ? 'selected' : '' ?>>1–2 giờ/tuần (Thong thả)</option>
                        <option value="3" <?= (int)($roadmap['weekly_hours'] ?? 0) === 3 || empty($roadmap['weekly_hours']) ? 'selected' : '' ?>>2–4 giờ/tuần (Tiêu chuẩn)</option>
                        <option value="6" <?= (int)($roadmap['weekly_hours'] ?? 0) === 6 ? 'selected' : '' ?>>5–8 giờ/tuần (Tập trung cao)</option>
                    </select>
                </div>
                <div>
                    <label for="setup-career-title" style="font-size: var(--text-xs); font-weight: 700;">5. Nghề/ngành quan tâm (Tùy chọn)</label>
                    <input class="input" id="setup-career-title" name="target_career_title" value="<?= e($roadmap['target_career_title']) ?>" placeholder="Ví dụ: Giáo viên, Lập trình viên...">
                </div>
            </div>

            <!-- Ghi chú động lực -->
            <div class="form-group" style="margin-bottom: var(--space-4);">
                <label for="setup-notes" style="font-size: var(--text-xs); font-weight: 700;">Ghi chú / Động lực cá nhân</label>
                <textarea class="input" id="setup-notes" name="notes" rows="2" placeholder="Điều bạn mong muốn thấy được sau khi hoàn thành kế hoạch này..."><?= e($roadmap['notes']) ?></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: var(--space-2); border-top: 1px solid var(--border); padding-top: var(--space-3);">
                <button type="button" class="btn btn-outline" onclick="closeSetupModal()">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu kế hoạch</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Modal Thêm Công Việc Mới -->
<div class="roadmap-modal-backdrop" id="add-task-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="add-task-modal-title">
    <div class="roadmap-modal">
        <div class="roadmap-modal-header">
            <h3 class="roadmap-modal-title" id="add-task-modal-title">➕ Thêm công việc mới</h3>
            <button type="button" class="roadmap-modal-close" onclick="closeAddTaskModal()" aria-label="Đóng">&times;</button>
        </div>

        <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" onsubmit="return validateTaskDates(this);">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_task">
            <input type="hidden" name="month" value="<?= e($activeMonthKey) ?>">

            <div class="form-group" style="margin-bottom: var(--space-3);">
                <label for="add-task-title" style="font-size: var(--text-xs); font-weight: 700;">Tên công việc (Bắt đầu bằng hành động) *</label>
                <input class="input" id="add-task-title" name="title" required maxlength="255" placeholder="Ví dụ: So sánh học phí và điều kiện đầu vào của 3 trường...">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-3);">
                <div>
                    <label for="add-task-month" style="font-size: var(--text-xs); font-weight: 700;">Tháng dự kiến *</label>
                    <select class="input" id="add-task-month" name="target_month">
                        <?php foreach ($monthData['plan_months'] as $ym => $pm): ?>
                            <option value="<?= $ym ?>" <?= ($ym === $activeMonthKey) ? 'selected' : '' ?>>
                                <?= e($pm['label']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="" <?= ($activeMonthKey === 'unscheduled') ? 'selected' : '' ?>>Chưa xếp lịch</option>
                    </select>
                </div>
                <div>
                    <label for="add-task-due-date" style="font-size: var(--text-xs); font-weight: 700;">Hạn hoàn thành (Tùy chọn)</label>
                    <input type="date" class="input" id="add-task-due-date" name="due_date">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-3);">
                <div>
                    <label for="add-task-stage" style="font-size: var(--text-xs); font-weight: 700;">Nhãn giai đoạn</label>
                    <select class="input" id="add-task-stage" name="stage">
                        <?php foreach ($stages as $sk => $sm): ?>
                            <option value="<?= $sk ?>" <?= ($filterStage === $sk) ? 'selected' : '' ?>>
                                <?= e($sm['icon']) ?> <?= e($sm['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="add-task-duration" style="font-size: var(--text-xs); font-weight: 700;">Thời lượng dự kiến</label>
                    <input class="input" id="add-task-duration" name="estimated_duration" placeholder="Ví dụ: 30 phút, 2 giờ...">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: var(--space-3);">
                <label for="add-task-outcome" style="font-size: var(--text-xs); font-weight: 700;">Kết quả cần đạt (Đầu ra cụ thể)</label>
                <input class="input" id="add-task-outcome" name="expected_outcome" placeholder="Ví dụ: Bảng đối chiếu 3 trường, Nhật ký phản tư 2 điểm thích...">
            </div>

            <div class="form-group" style="margin-bottom: var(--space-3);">
                <label for="add-task-desc" style="font-size: var(--text-xs); font-weight: 700;">Mô tả / Hướng dẫn thực hiện</label>
                <textarea class="input" id="add-task-desc" name="description" rows="2" placeholder="Ghi chú chi tiết các bước làm..."></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-4);">
                <div>
                    <label for="add-task-priority" style="font-size: var(--text-xs); font-weight: 700;">Mức ưu tiên</label>
                    <select class="input" id="add-task-priority" name="priority">
                        <option value="medium">Bình thường</option>
                        <option value="high">Ưu tiên cao ⚡</option>
                        <option value="low">Thấp</option>
                    </select>
                </div>
                <div>
                    <label for="add-task-prereq" style="font-size: var(--text-xs); font-weight: 700;">Công việc tiên quyết (Nếu có)</label>
                    <select class="input" id="add-task-prereq" name="prerequisite_id">
                        <option value="">Không có việc tiên quyết</option>
                        <?php foreach ($monthData['grouped'] as $mg): ?>
                            <?php foreach ($mg['items'] as $it): ?>
                                <option value="<?= $it['id'] ?>"><?= e(mb_substr($it['title'], 0, 40)) ?>...</option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: var(--space-2); border-top: 1px solid var(--border); padding-top: var(--space-3);">
                <button type="button" class="btn btn-outline" onclick="closeAddTaskModal()">Hủy</button>
                <button type="submit" class="btn btn-primary">Thêm vào kế hoạch</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Modal Chỉnh Sửa Công Việc -->
<div class="roadmap-modal-backdrop" id="edit-task-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="edit-task-modal-title">
    <div class="roadmap-modal">
        <div class="roadmap-modal-header">
            <h3 class="roadmap-modal-title" id="edit-task-modal-title">✏️ Chỉnh sửa công việc</h3>
            <button type="button" class="roadmap-modal-close" onclick="closeEditTaskModal()" aria-label="Đóng">&times;</button>
        </div>

        <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" onsubmit="return validateTaskDates(this);">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_task">
            <input type="hidden" name="task_id" id="edit-task-id" value="">
            <input type="hidden" name="month" value="<?= e($activeMonthKey) ?>">

            <div class="form-group" style="margin-bottom: var(--space-3);">
                <label for="edit-task-title" style="font-size: var(--text-xs); font-weight: 700;">Tên công việc *</label>
                <input class="input" id="edit-task-title" name="title" required maxlength="255">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-3);">
                <div>
                    <label for="edit-task-month" style="font-size: var(--text-xs); font-weight: 700;">Tháng dự kiến</label>
                    <select class="input" id="edit-task-month" name="target_month">
                        <?php foreach ($monthData['plan_months'] as $ym => $pm): ?>
                            <option value="<?= $ym ?>"><?= e($pm['label']) ?></option>
                        <?php endforeach; ?>
                        <option value="">Chưa xếp lịch</option>
                    </select>
                </div>
                <div>
                    <label for="edit-task-due-date" style="font-size: var(--text-xs); font-weight: 700;">Hạn hoàn thành</label>
                    <input type="date" class="input" id="edit-task-due-date" name="due_date">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-3);">
                <div>
                    <label for="edit-task-status" style="font-size: var(--text-xs); font-weight: 700;">Trạng thái</label>
                    <select class="input" id="edit-task-status" name="status">
                        <option value="todo">Chưa bắt đầu</option>
                        <option value="in_progress">Đang làm</option>
                        <option value="completed">Hoàn thành ✓</option>
                    </select>
                </div>
                <div>
                    <label for="edit-task-priority" style="font-size: var(--text-xs); font-weight: 700;">Mức ưu tiên</label>
                    <select class="input" id="edit-task-priority" name="priority">
                        <option value="medium">Bình thường</option>
                        <option value="high">Ưu tiên cao ⚡</option>
                        <option value="low">Thấp</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: var(--space-3);">
                <label for="edit-task-outcome" style="font-size: var(--text-xs); font-weight: 700;">Kết quả cần đạt</label>
                <input class="input" id="edit-task-outcome" name="expected_outcome">
            </div>

            <div class="form-group" style="margin-bottom: var(--space-3);">
                <label for="edit-task-actual-outcome" style="font-size: var(--text-xs); font-weight: 700;">Kết quả thực tế / Ghi chép</label>
                <textarea class="input" id="edit-task-actual-outcome" name="actual_outcome" rows="2" placeholder="Ghi lại kết quả sau khi hoàn thành..."></textarea>
            </div>

            <div class="form-group" style="margin-bottom: var(--space-4);">
                <label for="edit-task-desc" style="font-size: var(--text-xs); font-weight: 700;">Mô tả chi tiết</label>
                <textarea class="input" id="edit-task-desc" name="description" rows="2"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: var(--space-2); border-top: 1px solid var(--border); padding-top: var(--space-3);">
                <button type="button" class="btn btn-outline" onclick="closeEditTaskModal()">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. Modal Chuyển Tháng Cho Công Việc -->
<div class="roadmap-modal-backdrop" id="move-month-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="move-month-title">
    <div class="roadmap-modal" style="max-width: 480px;">
        <div class="roadmap-modal-header">
            <h3 class="roadmap-modal-title" id="move-month-title">📅 Chuyển công việc sang tháng khác</h3>
            <button type="button" class="roadmap-modal-close" onclick="closeMoveMonthModal()" aria-label="Đóng">&times;</button>
        </div>

        <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="move_task_month">
            <input type="hidden" name="task_id" id="move-task-id" value="">
            <input type="hidden" name="month" value="<?= e($activeMonthKey) ?>">

            <p style="font-size: 0.8125rem; color: var(--text); margin: 0 0 var(--space-3);">
                Công việc: <strong id="move-task-name"></strong>
            </p>

            <div class="form-group" style="margin-bottom: var(--space-3);">
                <label for="move-target-month" style="font-size: var(--text-xs); font-weight: 700;">Chọn tháng mới *</label>
                <select class="input" id="move-target-month" name="new_target_month">
                    <?php foreach ($monthData['plan_months'] as $ym => $pm): ?>
                        <option value="<?= $ym ?>"><?= e($pm['label']) ?></option>
                    <?php endforeach; ?>
                    <option value="">Chưa xếp lịch</option>
                </select>
            </div>

            <!-- Xử lý hạn hoàn thành -->
            <div id="move-due-date-handling" style="background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: var(--space-3); margin-bottom: var(--space-4);">
                <span style="font-size: 0.72rem; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">
                    Xử lý hạn hoàn thành hiện tại (<span id="move-current-due"></span>):
                </span>
                <label style="display: flex; align-items: center; gap: 6px; font-size: var(--text-xs); margin-bottom: 6px; cursor: pointer;">
                    <input type="radio" name="keep_due_date" value="1" checked>
                    <span>Giữ nguyên ngày hạn cũ</span>
                </label>
                <label style="display: flex; align-items: center; gap: 6px; font-size: var(--text-xs); margin-bottom: 6px; cursor: pointer;">
                    <input type="radio" name="keep_due_date" value="0">
                    <span>Bỏ hạn hoàn thành cũ</span>
                </label>
                <div style="margin-top: 6px;">
                    <label for="move-new-due-date" style="font-size: 0.72rem; color: var(--muted); display: block; margin-bottom: 2px;">Hoặc đặt hạn mới trong tháng được chọn:</label>
                    <input type="date" class="input" id="move-new-due-date" name="new_due_date" style="padding: 6px 10px; font-size: var(--text-xs);">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: var(--space-2);">
                <button type="button" class="btn btn-outline" onclick="closeMoveMonthModal()">Hủy</button>
                <button type="submit" class="btn btn-primary">Xác nhận chuyển</button>
            </div>
        </form>
    </div>
</div>

<!-- 5. Modal Ghi Nhận Kết Quả Thực Tế -->
<div class="roadmap-modal-backdrop" id="outcome-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="outcome-modal-title">
    <div class="roadmap-modal" style="max-width: 500px;">
        <div class="roadmap-modal-header">
            <h3 class="roadmap-modal-title" id="outcome-modal-title">📝 Ghi nhận kết quả thực tế</h3>
            <button type="button" class="roadmap-modal-close" onclick="closeRecordOutcomeModal()" aria-label="Đóng">&times;</button>
        </div>

        <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="record_outcome">
            <input type="hidden" name="task_id" id="outcome-task-id" value="">
            <input type="hidden" name="month" value="<?= e($activeMonthKey) ?>">

            <p style="font-size: 0.8125rem; color: var(--text); margin: 0 0 var(--space-3);">
                Công việc: <strong id="outcome-task-title"></strong>
            </p>

            <div class="form-group" style="margin-bottom: var(--space-4);">
                <label for="input-actual-outcome" style="font-size: var(--text-xs); font-weight: 700;">Kết quả thực tế / Bài học rút ra</label>
                <textarea class="input" id="input-actual-outcome" name="actual_outcome" rows="4" maxlength="2000" placeholder="Ví dụ: Đã ghi nhận 2 điểm phù hợp về giờ giấc và 1 điểm lo ngại về áp lực giao tiếp..."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: var(--space-2);">
                <button type="button" class="btn btn-outline" onclick="closeRecordOutcomeModal()">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu kết quả</button>
            </div>
        </form>
    </div>
</div>

<!-- 6. Modal Sửa Mục Tiêu Tháng -->
<div class="roadmap-modal-backdrop" id="month-goal-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="month-goal-modal-title">
    <div class="roadmap-modal" style="max-width: 500px;">
        <div class="roadmap-modal-header">
            <h3 class="roadmap-modal-title" id="month-goal-modal-title">🎯 Chỉnh sửa mục tiêu tháng</h3>
            <button type="button" class="roadmap-modal-close" onclick="closeEditMonthGoalModal()" aria-label="Đóng">&times;</button>
        </div>

        <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_month_goal">
            <input type="hidden" name="month_key" id="edit-goal-month-key" value="">
            <input type="hidden" name="month" value="<?= e($activeMonthKey) ?>">

            <div class="form-group" style="margin-bottom: var(--space-4);">
                <label for="input-month-goal-text" style="font-size: var(--text-xs); font-weight: 700;">Mục tiêu cụ thể của tháng *</label>
                <textarea class="input" id="input-month-goal-text" name="goal_text" rows="3" required maxlength="500"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: var(--space-2);">
                <button type="button" class="btn btn-outline" onclick="closeEditMonthGoalModal()">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu mục tiêu tháng</button>
            </div>
        </form>
    </div>
</div>

<!-- 7. Modal Trợ lý La Bàn (AI Suggestions) -->
<div class="roadmap-modal-backdrop" id="ai-suggest-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="ai-suggest-title">
    <div class="roadmap-modal" style="max-width: 620px;">
        <div class="roadmap-modal-header">
            <div>
                <h3 class="roadmap-modal-title" id="ai-suggest-title">🧭 Trợ lý La Bàn · Gợi ý cho <?= e($activeMonthGroup['meta']['label']) ?></h3>
                <p style="margin: 2px 0 0; font-size: var(--text-xs); color: var(--muted);">Dựa trên mục tiêu và quỹ thời gian của bạn.</p>
            </div>
            <button type="button" class="roadmap-modal-close" onclick="closeAiSuggestModal()" aria-label="Đóng">&times;</button>
        </div>

        <!-- Mode Tabs -->
        <div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-3); border-bottom: 1px solid var(--border); padding-bottom: var(--space-2);">
            <button type="button" class="btn btn-outline btn-sm ai-tab-btn is-active" data-type="month_suggestions">Gợi ý việc tháng này</button>
            <button type="button" class="btn btn-outline btn-sm ai-tab-btn" data-type="time_adjust">Điều chỉnh theo thời gian</button>
            <button type="button" class="btn btn-outline btn-sm ai-tab-btn" data-type="breakdown">Chia nhỏ công việc</button>
        </div>

        <div id="ai-suggest-body">
            <div id="ai-suggest-loading" style="display: none; padding: var(--space-6); text-align: center;">
                <div style="font-size: 1.5rem; margin-bottom: var(--space-2);" aria-hidden="true">⏳</div>
                <p style="font-size: var(--text-sm); font-weight: 600; color: var(--primary); margin: 0;">Trợ lý La Bàn đang phân tích hồ sơ và tổng hợp gợi ý...</p>
            </div>
            <div id="ai-suggest-result"></div>
        </div>

        <div style="margin-top: var(--space-4); display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: var(--space-3);">
            <button type="button" class="btn btn-outline btn-sm" id="btn-re-generate-ai">
                🔄 Tải lại gợi ý
            </button>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeAiSuggestModal()">
                Đóng
            </button>
        </div>
    </div>
</div>

<!-- 8. Modal Chọn Từ Thư Viện Gợi Ý Có Sẵn (Templates) -->
<div class="roadmap-modal-backdrop" id="template-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="template-modal-title">
    <div class="roadmap-modal" style="max-width: 650px;">
        <div class="roadmap-modal-header">
            <div>
                <h3 class="roadmap-modal-title" id="template-modal-title">💡 Thư viện gợi ý chuẩn mực</h3>
                <p style="margin: 2px 0 0; font-size: var(--text-xs); color: var(--muted);">Chọn việc phù hợp để thêm ngay vào <?= e($activeMonthGroup['meta']['label']) ?>.</p>
            </div>
            <button type="button" class="roadmap-modal-close" onclick="closeTemplateModal()" aria-label="Đóng">&times;</button>
        </div>

        <div style="max-height: 60vh; overflow-y: auto; padding-right: 4px;">
            <?php foreach ($stages as $sk => $sm): 
                $tpls = $libraryMilestones[$sk] ?? [];
            ?>
            <div style="margin-bottom: var(--space-4);">
                <h4 style="font-size: var(--text-xs); font-weight: 800; color: var(--primary); text-transform: uppercase; margin: 0 0 8px;">
                    <?= e($sm['icon']) ?> <?= e($sm['title']) ?>
                </h4>
                <?php foreach ($tpls as $tpl): ?>
                <div class="template-item-card">
                    <div style="flex: 1;">
                        <h5 class="template-item-title"><?= e($tpl['title']) ?></h5>
                        <p class="template-item-desc"><?= e($tpl['description']) ?></p>
                        <div style="font-size: 0.72rem; color: var(--primary); font-weight: 600;">
                            🎯 Kết quả: <?= e($tpl['expected_outcome']) ?>
                        </div>
                    </div>
                    <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin: 0; flex-shrink: 0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_task">
                        <input type="hidden" name="month" value="<?= e($activeMonthKey) ?>">
                        <input type="hidden" name="target_month" value="<?= e($activeMonthKey === 'unscheduled' ? '' : $activeMonthKey) ?>">
                        <input type="hidden" name="stage" value="<?= e($tpl['stage']) ?>">
                        <input type="hidden" name="title" value="<?= e($tpl['title']) ?>">
                        <input type="hidden" name="description" value="<?= e($tpl['description']) ?>">
                        <input type="hidden" name="expected_outcome" value="<?= e($tpl['expected_outcome']) ?>">
                        <input type="hidden" name="action_url" value="<?= e($tpl['action_url']) ?>">
                        <input type="hidden" name="estimated_duration" value="<?= e($tpl['estimated_duration'] ?? '30 phút') ?>">
                        <input type="hidden" name="source" value="suggested">
                        <button type="submit" class="btn btn-outline btn-sm" style="white-space: nowrap;">
                            + Thêm việc này
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top: var(--space-4); display: flex; justify-content: flex-end; border-top: 1px solid var(--border); padding-top: var(--space-3);">
            <button type="button" class="btn btn-outline btn-sm" onclick="closeTemplateModal()">Đóng</button>
        </div>
    </div>
</div>

<!-- 9. Modal Xếp Lịch Cho Các Việc Chưa Xếp Lịch -->
<div class="roadmap-modal-backdrop" id="schedule-unscheduled-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="schedule-unscheduled-title">
    <div class="roadmap-modal" style="max-width: 620px;">
        <div class="roadmap-modal-header">
            <div>
                <h3 class="roadmap-modal-title" id="schedule-unscheduled-title">📋 Xếp lịch các công việc tồn đọng</h3>
                <p style="margin: 2px 0 0; font-size: var(--text-xs); color: var(--muted);">Chọn tháng tương ứng cho từng công việc.</p>
            </div>
            <button type="button" class="roadmap-modal-close" onclick="closeScheduleUnscheduledModal()" aria-label="Đóng">&times;</button>
        </div>

        <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="schedule_unscheduled">
            <input type="hidden" name="month" value="<?= e($activeMonthKey) ?>">

            <div style="max-height: 55vh; overflow-y: auto; padding-right: 4px;">
                <?php foreach ($monthData['grouped']['unscheduled']['items'] as $uItem): ?>
                <div style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); padding: 8px 0; border-bottom: 1px dashed var(--border); flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 220px;">
                        <span style="font-size: 0.8125rem; font-weight: 700; color: var(--text); display: block;">
                            <?= e($uItem['title']) ?>
                        </span>
                        <span style="font-size: 0.72rem; color: var(--muted);"><?= e($stages[$uItem['stage']]['title'] ?? $uItem['stage']) ?></span>
                    </div>
                    <div>
                        <select class="input" name="assignments[<?= $uItem['id'] ?>]" style="padding: 4px 8px; font-size: 0.75rem; width: auto;">
                            <option value="">Giữ chưa xếp</option>
                            <?php foreach ($monthData['plan_months'] as $ym => $pm): ?>
                                <option value="<?= $ym ?>"><?= e($pm['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: var(--space-2); margin-top: var(--space-4); border-top: 1px solid var(--border); padding-top: var(--space-3);">
                <button type="button" class="btn btn-outline" onclick="closeScheduleUnscheduledModal()">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu xếp lịch</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== JAVASCRIPT LOGIC ==================== -->
<script>
// 1. In kế hoạch
document.getElementById('print-roadmap-btn')?.addEventListener('click', () => window.print());

// 2. Validate dates: start_date <= due_date
function validateTaskDates(form) {
    const start = form.querySelector('[name="start_date"]')?.value;
    const due = form.querySelector('[name="due_date"]')?.value;
    if (start && due && start > due) {
        alert('Ngày bắt đầu không được sau hạn hoàn thành.');
        return false;
    }
    return true;
}

// 3. Modal Handlers
function openSetupModal() {
    document.getElementById('setup-modal-backdrop')?.classList.add('is-open');
    document.getElementById('setup-goal-title')?.focus();
}
function closeSetupModal() {
    document.getElementById('setup-modal-backdrop')?.classList.remove('is-open');
}

function openAddTaskModal(monthKey) {
    const sel = document.getElementById('add-task-month');
    if (sel && monthKey && monthKey !== 'unscheduled') sel.value = monthKey;
    document.getElementById('add-task-modal-backdrop')?.classList.add('is-open');
    document.getElementById('add-task-title')?.focus();
}
function closeAddTaskModal() {
    document.getElementById('add-task-modal-backdrop')?.classList.remove('is-open');
}

function openTemplateModal(monthKey) {
    document.getElementById('template-modal-backdrop')?.classList.add('is-open');
}
function closeTemplateModal() {
    document.getElementById('template-modal-backdrop')?.classList.remove('is-open');
}

function openEditTaskModal(taskId) {
    const card = document.getElementById('task-card-' + taskId);
    if (!card) return;
    document.getElementById('edit-task-id').value = taskId;
    document.getElementById('edit-task-title').value = card.querySelector('.task-title')?.textContent.trim() || '';
    document.getElementById('edit-task-desc').value = card.querySelector('.task-desc')?.textContent.trim() || '';
    const outcome = card.querySelector('.task-outcome-box span:last-child')?.textContent.trim() || '';
    document.getElementById('edit-task-outcome').value = outcome;
    const actual = card.querySelector('.task-actual-outcome-box div')?.textContent.trim() || '';
    document.getElementById('edit-task-actual-outcome').value = actual;

    const status = card.dataset.status || 'todo';
    const statusSelect = document.getElementById('edit-task-status');
    if (statusSelect) statusSelect.value = status;

    document.getElementById('edit-task-modal-backdrop')?.classList.add('is-open');
    document.getElementById('edit-task-title')?.focus();
}
function closeEditTaskModal() {
    document.getElementById('edit-task-modal-backdrop')?.classList.remove('is-open');
}

function openMoveMonthModal(taskId, title, currentMonth, dueDate) {
    document.getElementById('move-task-id').value = taskId;
    document.getElementById('move-task-name').textContent = title;
    const mSelect = document.getElementById('move-target-month');
    if (mSelect) mSelect.value = currentMonth || '';
    document.getElementById('move-current-due').textContent = dueDate || 'Chưa đặt';
    document.getElementById('move-month-modal-backdrop')?.classList.add('is-open');
}
function closeMoveMonthModal() {
    document.getElementById('move-month-modal-backdrop')?.classList.remove('is-open');
}

function openRecordOutcomeModal(taskId, title, currentOutcome) {
    document.getElementById('outcome-task-id').value = taskId;
    document.getElementById('outcome-task-title').textContent = title;
    document.getElementById('input-actual-outcome').value = currentOutcome || '';
    document.getElementById('outcome-modal-backdrop')?.classList.add('is-open');
    document.getElementById('input-actual-outcome')?.focus();
}
function closeRecordOutcomeModal() {
    document.getElementById('outcome-modal-backdrop')?.classList.remove('is-open');
}

function openEditMonthGoalModal(monthKey) {
    document.getElementById('edit-goal-month-key').value = monthKey;
    const curGoal = document.getElementById('month-goal-display')?.textContent.trim() || '';
    document.getElementById('input-month-goal-text').value = curGoal;
    document.getElementById('month-goal-modal-backdrop')?.classList.add('is-open');
    document.getElementById('input-month-goal-text')?.focus();
}
function closeEditMonthGoalModal() {
    document.getElementById('month-goal-modal-backdrop')?.classList.remove('is-open');
}

function openScheduleUnscheduledModal() {
    document.getElementById('schedule-unscheduled-modal-backdrop')?.classList.add('is-open');
}
function closeScheduleUnscheduledModal() {
    document.getElementById('schedule-unscheduled-modal-backdrop')?.classList.remove('is-open');
}

let activeAiSuggestType = 'month_suggestions';
function openAiSuggestModal(monthKey) {
    document.getElementById('ai-suggest-modal-backdrop')?.classList.add('is-open');
    fetchAiSuggestions(activeAiSuggestType, monthKey);
}
function closeAiSuggestModal() {
    document.getElementById('ai-suggest-modal-backdrop')?.classList.remove('is-open');
}

// Close modals on Escape key or backdrop click
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

// Quick goal fill buttons in setup modal
document.querySelectorAll('.quick-goal-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = document.getElementById('setup-goal-title');
        if (input) {
            input.value = btn.dataset.val;
            input.focus();
        }
    });
});

// 4. Milestone Checkbox Optimistic Toggle with Rollback
document.querySelectorAll('.task-toggle-check').forEach(chk => {
    chk.addEventListener('change', async function() {
        const id = this.dataset.id;
        const card = document.getElementById('task-card-' + id);
        const nextCard = document.getElementById('next-card-' + id);
        const originallyChecked = !this.checked;
        const isNowCompleted = this.checked;

        // Optimistic UI updates
        if (card) {
            card.classList.toggle('is-completed', isNowCompleted);
            card.dataset.completed = isNowCompleted ? '1' : '0';
            card.dataset.status = isNowCompleted ? 'completed' : 'todo';
            const badge = card.querySelector('.task-status-badge');
            if (badge) {
                badge.className = 'task-status-badge ' + (isNowCompleted ? 'status-completed' : 'status-todo');
                badge.textContent = isNowCompleted ? '✓ Hoàn thành' : 'Chưa làm';
            }
        }
        if (nextCard) {
            nextCard.style.opacity = isNowCompleted ? '0.5' : '1';
        }

        const csrfToken = document.querySelector('input[name="csrf"]')?.value || '';
        const formData = new FormData();
        formData.append('action', 'toggle_task_status');
        formData.append('task_id', id);
        formData.append('month', '<?= e($activeMonthKey) ?>');
        formData.append('csrf', csrfToken);

        try {
            const res = await fetch('<?= url('index.php?page=ai-roadmap') ?>', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await res.json();
            if (result.status === 'ok') {
                const fill = document.getElementById('overall-progress-fill');
                const text = document.getElementById('overall-progress-text');
                if (fill && result.overall_percent !== undefined) fill.style.width = result.overall_percent + '%';
                if (text && result.completed_all !== undefined) {
                    text.textContent = `${result.overall_percent}% (Đã hoàn thành ${result.completed_all} / ${result.total_all} việc)`;
                }
            } else {
                this.checked = originallyChecked;
                if (card) card.classList.toggle('is-completed', originallyChecked);
                alert(result.message || 'Chưa thể cập nhật trạng thái. Vui lòng thử lại.');
            }
        } catch (e) {
            this.checked = originallyChecked;
            if (card) card.classList.toggle('is-completed', originallyChecked);
            alert('Lỗi kết nối khi cập nhật công việc.');
        }
    });
});

// 5. Filter Toolbar in Active Month
const filterPills = document.querySelectorAll('.filter-pill-btn');
const stageSelector = document.getElementById('stage-filter-selector');

function applyTaskFilters() {
    const activePill = document.querySelector('.filter-pill-btn.is-active')?.dataset.filter || 'all';
    const activeStage = stageSelector ? stageSelector.value : '';

    document.querySelectorAll('#tasks-list-container .task-card').forEach(card => {
        const cStatus = card.dataset.status;
        const cStage = card.dataset.stage;
        const cOverdue = card.dataset.overdue === '1';
        const cCompleted = card.dataset.completed === '1';

        let statusMatch = true;
        if (activePill === 'todo') statusMatch = !cCompleted;
        else if (activePill === 'completed') statusMatch = cCompleted;
        else if (activePill === 'overdue') statusMatch = cOverdue && !cCompleted;

        let stageMatch = true;
        if (activeStage && cStage !== activeStage) stageMatch = false;

        card.style.display = (statusMatch && stageMatch) ? 'block' : 'none';
    });
}

filterPills.forEach(pill => {
    pill.addEventListener('click', () => {
        filterPills.forEach(p => p.classList.remove('is-active'));
        pill.classList.add('is-active');
        applyTaskFilters();
    });
});
stageSelector?.addEventListener('change', applyTaskFilters);

// 6. AI Suggestions Fetcher
document.querySelectorAll('.ai-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.ai-tab-btn').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        activeAiSuggestType = btn.dataset.type;
        fetchAiSuggestions(activeAiSuggestType, '<?= e($activeMonthKey) ?>');
    });
});

async function fetchAiSuggestions(type = 'month_suggestions', monthKey = '<?= e($activeMonthKey) ?>') {
    const loading = document.getElementById('ai-suggest-loading');
    const resultBox = document.getElementById('ai-suggest-result');
    if (!resultBox) return;

    if (loading) loading.style.display = 'block';
    resultBox.innerHTML = '';

    try {
        const csrfToken = document.querySelector('input[name="csrf"]')?.value || '';
        const formData = new FormData();
        formData.append('action', 'ai_suggest');
        formData.append('suggest_type', type);
        formData.append('target_month', monthKey);
        formData.append('csrf', csrfToken);

        const res = await fetch('<?= url('index.php?page=ai-roadmap') ?>', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.status === 'ok' && data.data && data.data.suggestions) {
            let html = `<div style="margin-bottom:8px; font-size:0.75rem; color:var(--muted); font-style:italic;">${data.data.note || ''}</div>`;
            html += '<div style="display: flex; flex-direction: column; gap: 8px;">';
            data.data.suggestions.forEach(item => {
                html += `
                    <div class="template-item-card">
                        <div style="flex: 1;">
                            <h5 class="template-item-title">${item.title}</h5>
                            <p class="template-item-desc">${item.description}</p>
                            <div style="font-size:0.72rem; color:var(--primary); font-weight:600;">🎯 Đầu ra: ${item.expected_outcome || ''}</div>
                        </div>
                        <form method="post" action="<?= url('index.php?page=ai-roadmap') ?>" style="margin: 0; flex-shrink: 0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_task">
                            <input type="hidden" name="month" value="${monthKey}">
                            <input type="hidden" name="target_month" value="${monthKey === 'unscheduled' ? '' : monthKey}">
                            <input type="hidden" name="title" value="${item.title}">
                            <input type="hidden" name="description" value="${item.description}">
                            <input type="hidden" name="stage" value="${item.stage || 'skills'}">
                            <input type="hidden" name="expected_outcome" value="${item.expected_outcome || ''}">
                            <input type="hidden" name="action_url" value="${item.action_url || ''}">
                            <input type="hidden" name="estimated_duration" value="${item.estimated_duration || '30 phút'}">
                            <input type="hidden" name="source" value="ai">
                            <button type="submit" class="btn btn-primary btn-sm" style="white-space: nowrap;">
                                + Thêm việc này
                            </button>
                        </form>
                    </div>
                `;
            });
            html += '</div>';
            resultBox.innerHTML = html;
        } else {
            resultBox.innerHTML = '<p style="color: var(--danger); font-size: var(--text-xs); text-align: center; padding: 12px;">Chưa thể tải gợi ý lúc này. Hãy thử lại.</p>';
        }
    } catch (e) {
        resultBox.innerHTML = '<p style="color: var(--danger); font-size: var(--text-xs); text-align: center; padding: 12px;">Đã xảy ra lỗi kết nối.</p>';
    } finally {
        if (loading) loading.style.display = 'none';
    }
}
document.getElementById('btn-re-generate-ai')?.addEventListener('click', () => fetchAiSuggestions(activeAiSuggestType, '<?= e($activeMonthKey) ?>'));

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
