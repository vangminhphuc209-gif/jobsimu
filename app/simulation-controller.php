<?php
declare(strict_types=1);

if ($page === 'simulations') {
    $jobs = db()->query("SELECT * FROM careers WHERE status='published' ORDER BY featured DESC,title")->fetchAll();
    $attempts=[];$recent=[];
    if(user()){$s=db()->prepare('SELECT career_id,MAX(score) best_score,COUNT(*) attempt_count FROM simulation_attempts WHERE user_id=? GROUP BY career_id');$s->execute([user()['id']]);foreach($s->fetchAll() as $x)$attempts[$x['career_id']]=$x;$s=db()->prepare('SELECT sa.id,sa.score,sa.completed_at,c.title,c.icon FROM simulation_attempts sa JOIN careers c ON c.id=sa.career_id WHERE sa.user_id=? ORDER BY sa.completed_at DESC LIMIT 5');$s->execute([user()['id']]);$recent=$s->fetchAll();}
    $pageTitle='Phòng mô phỏng nghề';require ROOT_PATH.'/app/header.php';?>
    <section class="page-head"><div class="container"><span class="eyebrow">15 mô phỏng · 60 tình huống</span><h1>Phòng mô phỏng nghề nghiệp</h1><p>Ra quyết định như người trong nghề, nhận phản hồi tức thì và khám phá năng lực qua trải nghiệm.</p></div></section>
    <div class="container"><div class="toolbar"><input class="search" data-career-search placeholder="🔎 Tìm trò chơi nghề nghiệp..."><span class="muted">Mỗi mô phỏng khoảng 4–6 phút</span></div>
    <div class="grid-3"><?php foreach($jobs as $job):$game=simulation_for_career($job);if(!$game)continue;$done=$attempts[$job['id']]??null;?><a data-career class="card career-card" href="<?=url('index.php?page=simulation&id='.$job['id'])?>"><div class="flex items-start justify-between"><div class="career-icon"><?=e($job['icon'])?></div><?php if($done):?><span class="sim-done">✓ Tốt nhất <?=$done['best_score']?>%</span><?php else:?><span class="ai-badge">Mới</span><?php endif;?></div><h3><?=e($game['title'])?></h3><p><?=e($game['brief'])?></p><div class="meta"><span>🎭 <?=e($game['role'])?></span><b style="color:var(--primary)"><?=$done?'Chơi lại':'Bắt đầu'?> →</b></div></a><?php endforeach;?></div>
    <?php if($recent):?><div class="mt-10 flex items-center justify-between"><h2>Lịch sử trải nghiệm của bạn</h2><span class="muted"><?=array_sum(array_column($attempts,'attempt_count'))?> lượt đã chơi</span></div><div class="card table-wrap"><table><thead><tr><th>Nghề</th><th>Điểm</th><th>Thời gian</th><th></th></tr></thead><tbody><?php foreach($recent as $x):?><tr><td><b><?=e($x['icon'].' '.$x['title'])?></b></td><td><b class="text-brand-600"><?=$x['score']?>/100</b></td><td><?=date('d/m/Y H:i',strtotime($x['completed_at']))?></td><td><a class="btn py-2" href="<?=url('index.php?page=simulation-result&id='.$x['id'])?>">Xem báo cáo</a></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div>
    <?php require ROOT_PATH.'/app/footer.php';exit;
}

if ($page === 'career') {
    $id = (int)($_GET['id'] ?? 0);
    $s = db()->prepare("SELECT * FROM careers WHERE id=? AND status='published'"); $s->execute([$id]); $job = $s->fetch();
    if (!$job) { http_response_code(404); exit('Không tìm thấy nghề.'); }
    $fav=false; if(user()){$s = db()->prepare('SELECT 1 FROM favorites WHERE user_id=? AND career_id=?'); $s->execute([user()['id'],$id]); $fav=(bool)$s->fetchColumn();}
    $simulationUrl=safe_external_url($job['simulation_url']); $game=simulation_for_career($job); $pageTitle=$job['title'];
    require ROOT_PATH . '/app/header.php'; ?>
    <section class="page-head"><div class="container"><span class="eyebrow">Hồ sơ nghề nghiệp</span><h1><?=e($job['icon'].' '.$job['title'])?></h1><p><?=e($job['short_description'])?></p></div></section>
    <div class="container career-detail"><article class="card">
        <?php if($game):?><div class="career-game-banner"><div><span class="ai-badge">🎮 Chơi trực tiếp · 4 tình huống</span><h2><?=e($game['title'])?></h2><p><?=e($game['brief'])?></p><div class="sim-setting"><span>🎭 <?=e($game['role'])?></span><span>⏱ 4–6 phút</span></div></div><a class="btn btn-primary btn-lg" href="<?=url('index.php?page=simulation&id='.$job['id'])?>">Bắt đầu mô phỏng →</a></div><?php endif;?>
        <div class="detail-section"><h3>Tổng quan công việc</h3><p><?=nl2br(e($job['description']))?></p></div><div class="detail-section"><h3>🏢 Môi trường làm việc</h3><p><?=nl2br(e($job['work_environment']))?></p></div><div class="detail-section"><h3>🧩 Kỹ năng quan trọng</h3><p><?=nl2br(e($job['skills']))?></p></div><div class="detail-section"><h3>📚 Môn học nên chú ý</h3><p><?=nl2br(e($job['subjects']))?></p></div><div class="detail-section"><h3>🎓 Lộ trình học tập</h3><p><?=nl2br(e($job['education_path']))?></p></div>
    </article><aside><div class="card side-card"><div class="career-icon"><?=e($job['icon'])?></div><h3><?=e($job['title'])?></h3><p><b>Thu nhập tham khảo</b><br><?=e($job['salary_range'])?></p><p><b>Triển vọng</b><br><?=e($job['outlook'])?></p>
        <?php if($game):?><a class="btn btn-primary btn-lg w-full" href="<?=url('index.php?page=simulation&id='.$job['id'])?>">🎮 Chơi mô phỏng nghề</a><?php endif;?>
        <?php if($simulationUrl):?><a class="btn btn-outline w-full mt-3" href="<?=e($simulationUrl)?>" target="_blank" rel="noopener noreferrer">↗ <?=e($job['simulation_label'])?></a><?php endif;?>
        <?php if(user()):?><form method="post" action="<?=url('index.php?page=favorite')?>" style="margin-top:10px"><?=csrf_field()?><input type="hidden" name="career_id" value="<?=$job['id']?>"><button class="btn btn-outline w-full"><?=$fav?'♥ Đã lưu nghề này':'♡ Lưu vào yêu thích'?></button></form><?php endif;?><a class="btn w-full mt-3" href="<?=url('index.php?page=assessment')?>">Kiểm tra độ phù hợp</a>
    </div></aside></div>
    <?php require ROOT_PATH . '/app/footer.php'; exit;
}

require_login();

if ($page === 'simulation') {
    $id = (int)($_GET['id'] ?? 0);
    $s = db()->prepare("SELECT * FROM careers WHERE id=? AND status='published'");
    $s->execute([$id]);
    $career = $s->fetch();
    $rawGame = $career ? simulation_for_career($career) : null;
    if (!$career || !$rawGame) { http_response_code(404); exit('Mô phỏng không tồn tại hoặc đang tạm đóng.'); }

    // Dynamic Mission Selection: pick 1 mission per stage and shuffle choices
    $selectedMissions = select_simulation_missions($rawGame);
    $game = $rawGame;
    $game['missions'] = $selectedMissions;

    // Daily exploration cap check (pedagogical guardrail)
    $todayStmt = db()->prepare('SELECT COUNT(*) FROM simulation_attempts WHERE user_id=? AND career_id=? AND DATE(completed_at)=CURDATE()');
    $todayStmt->execute([user()['id'], $career['id']]);
    $todayCount = (int)$todayStmt->fetchColumn();

    $pageTitle = $game['title'];
    require ROOT_PATH . '/app/header.php';
    ?>
    <div class="sim-shell">
        <div class="sim-topbar">
            <a href="<?=url('index.php?page=career&id='.$career['id'])?>" class="btn btn-outline">← Thoát mô phỏng</a>
            <div class="sim-progress-wrap">
                <div class="flex justify-between text-sm font-bold"><span id="simProgressText">Nhiệm vụ 1 / <?=count($game['missions'])?></span><span id="simScoreText">0% phản xạ</span></div>
                <div class="progress-track mt-2"><div id="simProgress" class="progress-bar" style="width:<?=100/count($game['missions'])?>%"></div></div>
            </div>
        </div>

        <?php if ($todayCount >= 2): ?>
        <div class="container mt-4">
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 shadow-sm flex items-start gap-3">
                <span class="text-xl">🌿</span>
                <div>
                    <b>Lời nhắn từ người bạn hướng nghiệp:</b> Hôm nay bạn đã thử sức nghề này <b><?=$todayCount?> lượt</b> rồi. Trải nghiệm mô phỏng nhằm giúp bạn cảm nhận phản xạ thực tế, không nên áp lực 'cày điểm'. Sau lượt này, hãy dành thời gian đọc Lộ trình học tập hoặc thử sức với thẻ <b>Thử thách 15 phút ngoài đời thực</b> nhé!
                </div>
            </div>
        </div>
        <?php endif; ?>

        <section class="sim-stage">
            <div class="sim-brief">
                <div class="sim-role-icon"><?=e($career['icon'])?></div><span class="eyebrow">Mô phỏng nghề thực tế</span>
                <h1><?=e($game['title'])?></h1><p><?=e($game['brief'])?></p>
                <div class="sim-setting"><span>🎭 <?=e($game['role'])?></span><span>⏱ <?=e($game['setting'])?></span></div>
            </div>
            <div class="sim-console">
                <div id="simSceneBox" class="sim-scene">
                    <div class="flex items-center gap-2 mb-2">
                        <span id="missionBadge" class="sim-mission-badge"></span>
                        <span id="missionTypeBadge" class="task-badge-type"></span>
                    </div>
                    <h2 id="missionTitle"></h2>
                    <p id="missionSituation"></p>
                </div>
                <div id="choiceList" class="sim-choices"></div>
                <div id="simFeedback" class="sim-feedback" aria-live="polite">
                    <b id="feedbackTitle"></b>
                    <p id="feedbackText"></p>
                    <small id="feedbackTip"></small>
                </div>

                <!-- Self-Reflection Checkpoint -->
                <div id="simReflectionCheckpoint" class="sim-reflection-card" style="display:none">
                    <span class="eyebrow">Trạm Dừng Tự Phản Tư</span>
                    <h2 class="mt-2 text-2xl font-black">Lắng nghe cảm xúc của bạn</h2>
                    <p class="text-sm text-slate-500 mt-1">Trước khi nhận báo cáo Chỉ số Phản xạ Nghề nghiệp, hãy dành 15 giây lắng nghe cảm xúc thật của bạn sau khi trải nghiệm vai trò này.</p>
                    
                    <b class="block mt-4 text-sm text-slate-700">1. Bạn cảm thấy thế nào qua các tình huống vừa rồi?</b>
                    <div class="reflection-options">
                        <button type="button" class="reflection-btn selected" data-feeling="excited">
                            <span class="text-2xl">🤩</span>
                            <div>
                                <b>Rất hào hứng & tràn đầy năng lượng</b>
                                <div class="text-xs text-slate-500">Thấy bị cuốn hút, muốn tìm hiểu sâu hơn về chuyên môn này.</div>
                            </div>
                        </button>
                        <button type="button" class="reflection-btn" data-feeling="challenged">
                            <span class="text-2xl">🤔</span>
                            <div>
                                <b>Khá áp lực & thấy còn nhiều thử thách</b>
                                <div class="text-xs text-slate-500">Cảm giác công việc đòi hỏi nhiều kỹ năng mình cần rèn luyện thêm.</div>
                            </div>
                        </button>
                        <button type="button" class="reflection-btn" data-feeling="unsuited">
                            <span class="text-2xl">😐</span>
                            <div>
                                <b>Cảm thấy không hợp với phong cách của mình</b>
                                <div class="text-xs text-slate-500">Không tìm thấy niềm vui hoặc thấy phong cách làm việc không phù hợp.</div>
                            </div>
                        </button>
                    </div>

                    <b class="block mt-4 text-sm text-slate-700">2. Gắn nhãn cảm nhận nhanh hoặc ghi chú ngắn (tùy chọn):</b>
                    <div class="flex flex-wrap gap-2 mt-2" id="reflectionTagGroup">
                        <button type="button" class="tag cursor-pointer hover:bg-slate-200" onclick="addRefTag('#thich_giai_quyet_van_de')">#thich_giai_quyet_van_de</button>
                        <button type="button" class="tag cursor-pointer hover:bg-slate-200" onclick="addRefTag('#ngai_ap_luc_thoi_gian')">#ngai_ap_luc_thoi_gian</button>
                        <button type="button" class="tag cursor-pointer hover:bg-slate-200" onclick="addRefTag('#thich_giao_tiep')">#thich_giao_tiep</button>
                        <button type="button" class="tag cursor-pointer hover:bg-slate-200" onclick="addRefTag('#can_ren_luyen_them')">#can_ren_luyen_them</button>
                        <button type="button" class="tag cursor-pointer hover:bg-slate-200" onclick="addRefTag('#muon_tim_hieu_sau')">#muon_tim_hieu_sau</button>
                    </div>
                    <input type="text" id="reflectionInput" class="input mt-3" maxlength="300" placeholder="Ví dụ: Mình thích lúc giải quyết lỗi nhưng hơi căng thẳng khi bị giới hạn thời gian...">
                </div>

                <button id="simNext" class="btn btn-primary btn-lg w-full mt-4" type="button" disabled>Chọn một phương án để tiếp tục</button>
            </div>
        </section>

        <form id="simSubmit" method="post" action="<?=url('index.php?page=simulation-complete')?>"><?=csrf_field()?>
            <input type="hidden" name="career_id" value="<?=$career['id']?>">
            <input type="hidden" name="decisions" id="simDecisions">
            <input type="hidden" name="mission_keys" id="simMissionKeys">
            <input type="hidden" name="duration" id="simDuration">
            <input type="hidden" name="reflection_feeling" id="simFeeling" value="excited">
            <input type="hidden" name="reflection_note" id="simNote" value="">
        </form>
    </div>
    <script>
    const simGame = <?=json_encode($game, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
    const simStarted = Date.now();
    const simDecisions = [];
    const simMissionKeys = [];
    let simIndex = 0, simXp = 0, simLocked = false, inReflection = false, selectedFeeling = 'excited';

    const choiceList = document.getElementById('choiceList');
    const feedback = document.getElementById('simFeedback');
    const nextBtn = document.getElementById('simNext');
    const checkpointBox = document.getElementById('simReflectionCheckpoint');
    const sceneBox = document.getElementById('simSceneBox');

    const typeLabels = {
        'single_choice': '⚡ Tình huống khẩn cấp',
        'trade_off': '⚖️ Bài toán đánh đổi',
        'priority_order': '📋 Sắp xếp quy trình'
    };

    function renderMission() {
        const mission = simGame.missions[simIndex];
        simLocked = false;
        feedback.classList.remove('show');
        document.getElementById('missionBadge').textContent = `TÌNH HUỐNG ${String(simIndex+1).padStart(2,'0')}`;
        const typeBadge = document.getElementById('missionTypeBadge');
        typeBadge.textContent = typeLabels[mission.type] || '⚡ Nhiệm vụ';
        typeBadge.className = 'task-badge-type type-' + (mission.type || 'single_choice');
        document.getElementById('missionTitle').textContent = mission.title;
        document.getElementById('missionSituation').textContent = mission.situation;
        document.getElementById('simProgressText').textContent = `Nhiệm vụ ${simIndex+1} / ${simGame.missions.length}`;
        document.getElementById('simProgress').style.width = ((simIndex+1)/simGame.missions.length*100) + '%';
        
        choiceList.innerHTML = '';
        mission.choices.forEach((choice, i) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'sim-choice';
            btn.innerHTML = '<span></span><b></b>';
            btn.querySelector('span').textContent = String.fromCharCode(65 + i);
            btn.querySelector('b').textContent = choice.text;
            btn.onclick = () => choose(choice, btn);
            choiceList.appendChild(btn);
        });
        nextBtn.disabled = true;
        nextBtn.textContent = 'Chọn một phương án để tiếp tục';
    }

    function choose(choice, btn) {
        if (simLocked) return;
        simLocked = true;
        const mission = simGame.missions[simIndex];
        simDecisions.push(choice.original_index !== undefined ? choice.original_index : 0);
        simMissionKeys.push(mission.original_mission_index !== undefined ? mission.original_mission_index : simIndex);
        simXp += choice.points;
        
        choiceList.querySelectorAll('button').forEach(x => x.disabled = true);
        btn.classList.add(choice.points === 3 ? 'best' : 'selected');
        
        document.getElementById('feedbackTitle').textContent = choice.points === 3 ? '✓ Quyết định chuyên nghiệp' : (choice.points === 0 ? 'Cần cân nhắc thêm' : 'Một lựa chọn có đánh đổi');
        document.getElementById('feedbackText').textContent = choice.feedback;
        document.getElementById('feedbackTip').textContent = '💡 ' + mission.tip;
        feedback.classList.add('show');
        
        const pct = Math.round(simXp / ((simIndex + 1) * 3) * 100);
        document.getElementById('simScoreText').textContent = pct + '% phản xạ';
        
        nextBtn.disabled = false;
        nextBtn.textContent = simIndex === simGame.missions.length - 1 ? 'Chuyển sang Trạm Tự Phản Tư ✦' : 'Tình huống tiếp theo →';
    }

    function addRefTag(tag) {
        const input = document.getElementById('reflectionInput');
        if (!input.value.includes(tag)) {
            input.value = (input.value ? input.value + ' ' : '') + tag;
        }
    }

    document.querySelectorAll('.reflection-btn').forEach(btn => {
        btn.onclick = () => {
            document.querySelectorAll('.reflection-btn').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            selectedFeeling = btn.dataset.feeling;
        };
    });

    nextBtn.onclick = () => {
        if (!simLocked && !inReflection) return;
        if (simIndex < simGame.missions.length - 1) {
            simIndex++;
            renderMission();
            document.querySelector('.sim-console').scrollIntoView({behavior:'smooth', block:'start'});
        } else if (!inReflection) {
            inReflection = true;
            sceneBox.style.display = 'none';
            choiceList.style.display = 'none';
            feedback.style.display = 'none';
            checkpointBox.style.display = 'block';
            nextBtn.textContent = 'Hoàn tất & Xem Báo cáo Phản xạ Nghề nghiệp →';
            document.querySelector('.sim-console').scrollIntoView({behavior:'smooth', block:'start'});
        } else {
            nextBtn.disabled = true;
            nextBtn.textContent = 'Đang lập báo cáo...';
            document.getElementById('simDecisions').value = JSON.stringify(simDecisions);
            document.getElementById('simMissionKeys').value = JSON.stringify(simMissionKeys);
            document.getElementById('simDuration').value = Math.max(1, Math.round((Date.now() - simStarted) / 1000));
            document.getElementById('simFeeling').value = selectedFeeling;
            document.getElementById('simNote').value = document.getElementById('reflectionInput').value.trim();
            document.getElementById('simSubmit').submit();
        }
    };
    renderMission();
    </script>
    <?php require ROOT_PATH . '/app/footer.php'; exit;
}

$id = (int)($_GET['id'] ?? 0);
$s = db()->prepare('SELECT sa.*,c.title,c.slug,c.icon,c.short_description FROM simulation_attempts sa JOIN careers c ON c.id=sa.career_id WHERE sa.id=? AND sa.user_id=?');
$s->execute([$id, user()['id']]);
$attempt = $s->fetch();
if (!$attempt) { http_response_code(404); exit('Không tìm thấy kết quả mô phỏng.'); }
$game = simulation_catalog()[$attempt['slug']] ?? null;
if (!$game) { http_response_code(404); exit('Nội dung mô phỏng không còn tồn tại.'); }
$skills = json_decode($attempt['skill_scores'], true) ?: [];
$rawDecisions = json_decode($attempt['decisions'], true) ?: [];

// Reconstruct exact played missions
if (isset($rawDecisions['keys'], $rawDecisions['choices'])) {
    $recapMissions = [];
    foreach ($rawDecisions['keys'] as $idx => $k) {
        if (isset($game['missions'][$k])) {
            $m = $game['missions'][$k];
            $m['chosen_choice_index'] = $rawDecisions['choices'][$idx] ?? 0;
            $recapMissions[] = $m;
        }
    }
} else {
    $recapMissions = [];
    foreach (array_slice($game['missions'], 0, count($rawDecisions)) as $idx => $m) {
        $m['chosen_choice_index'] = $rawDecisions[$idx] ?? 0;
        $recapMissions[] = $m;
    }
}

$score = (int)$attempt['score'];
$rank = $score >= 90 ? 'Phản xạ Xuất sắc' : ($score >= 70 ? 'Phản xạ Sẵn sàng' : ($score >= 50 ? 'Phản xạ Tiềm năng' : 'Đang làm quen'));
$feelingMap = [
    'excited' => ['icon' => '🤩', 'label' => 'Rất hào hứng & Tràn đầy năng lượng', 'desc' => 'Bạn cảm thấy bị cuốn hút và tìm thấy sự hứng khởi tự nhiên với vai trò này.'],
    'challenged' => ['icon' => '🤔', 'label' => 'Khá áp lực & Còn nhiều thử thách', 'desc' => 'Bạn cảm nhận được sức nặng chuyên môn và thấy cần trang bị thêm kỹ năng.'],
    'unsuited' => ['icon' => '😐', 'label' => 'Chưa phù hợp với phong cách cá nhân', 'desc' => 'Bạn nhận diện rõ ràng phong cách làm việc này chưa phải là ưu tiên số một của bạn.']
];
$studentFeeling = $feelingMap[$attempt['reflection_feeling'] ?? 'excited'] ?? $feelingMap['excited'];
$challenge = $game['real_world_challenge'] ?? null;

$pageTitle = 'Chỉ số Phản xạ: ' . $attempt['title'];
require ROOT_PATH . '/app/header.php';
?>
<div class="container">
    <section class="sim-result-hero">
        <span class="eyebrow">Báo cáo Phản xạ Nghề nghiệp</span>
        <div class="sim-result-grid">
            <div>
                <div class="sim-role-icon"><?=e($attempt['icon'])?></div>
                <h1><?=e($attempt['title'])?></h1>
                <p>Bạn đã hoàn thành vai trò <b><?=e($game['role'])?></b> qua <?=count($recapMissions)?> quyết định chuyên môn.</p>
                <div class="flex flex-wrap gap-3">
                    <span class="sim-pill">🏅 <?=e($rank)?></span>
                    <span class="sim-pill">⏱ <?=max(1,(int)ceil($attempt['duration_seconds']/60))?> phút</span>
                    <span class="sim-pill"><?=$studentFeeling['icon'].' '.e($studentFeeling['label'])?></span>
                </div>
            </div>
            <div class="sim-total-score">
                <strong><?=$score?>%</strong>
                <small>Chỉ số Phản xạ Nghề nghiệp</small>
            </div>
        </div>
    </section>

    <!-- Student Reflection Card -->
    <div class="card mt-6" style="border-left: 4px solid var(--primary)">
        <span class="eyebrow">Trạm Tự Phản Tư Của Bạn</span>
        <div class="flex items-start gap-3 mt-2">
            <span class="text-3xl"><?=$studentFeeling['icon']?></span>
            <div>
                <h3 class="text-lg font-bold text-slate-800"><?=e($studentFeeling['label'])?></h3>
                <p class="text-slate-600 text-sm mt-1"><?=e($studentFeeling['desc'])?></p>
                <?php if (!empty($attempt['reflection_note'])): ?>
                <div class="mt-2 text-xs bg-slate-50 p-3 rounded-lg border border-slate-200 text-slate-700">
                    <b>Ghi chú cá nhân:</b> "<?=e($attempt['reflection_note'])?>"
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div>
            <div class="card">
                <span class="eyebrow">Hồ sơ năng lực</span>
                <h2 class="mt-3 text-2xl font-black">Những năng lực bạn đã bộc lộ</h2>
                <div class="sim-skill-list">
                    <?php foreach($skills as $skill=>$value):?>
                    <div>
                        <div class="flex justify-between">
                            <b><?=e(simulation_skill_labels()[$skill]??$skill)?></b>
                            <span><?=$value?>%</span>
                        </div>
                        <div class="progress-track"><div class="sim-skill-bar" style="width:<?=$value?>%"></div></div>
                    </div>
                    <?php endforeach;?>
                </div>
            </div>

            <!-- Real-World Action Card (Phase 3 Guardrail) -->
            <?php if ($challenge): ?>
            <div class="real-action-card">
                <div class="flex items-center justify-between">
                    <span class="eyebrow" style="color:#1d4ed8">Cầu Nối Đời Thực</span>
                    <span class="tag" style="background:#dbeafe;color:#1e40af">⏱ <?=e($challenge['duration'])?></span>
                </div>
                <h3 class="text-xl font-black text-slate-900 mt-2">📌 <?=e($challenge['title'])?></h3>
                <p class="text-slate-700 mt-2 leading-relaxed"><?=e($challenge['action'])?></p>
                <div class="mt-4 p-3 bg-white rounded-xl border border-blue-100 text-sm text-slate-600">
                    <b class="text-blue-900">💡 Câu hỏi phản tư sau thử thách:</b><br>
                    <?=e($challenge['reflection_question'])?>
                </div>
            </div>
            <?php endif; ?>

            <h2 class="mt-8">Nhật ký quyết định nghề nghiệp</h2>
            <?php foreach($recapMissions as $i=>$mission):
                $choice = $mission['choices'][$mission['chosen_choice_index']] ?? ($mission['choices'][0] ?? null);
                if(!$choice) continue;
            ?>
            <div class="card sim-recap">
                <span><?=($i+1)?></span>
                <div>
                    <div class="flex items-center gap-2">
                        <b><?=e($mission['title'])?></b>
                        <span class="task-badge-type type-<?=e($mission['type'] ?? 'single_choice')?>"><?=e($mission['type'] ?? '')?></span>
                    </div>
                    <p><?=e($choice['text'])?></p>
                    <small><?=e($choice['feedback'])?></small>
                </div>
            </div>
            <?php endforeach;?>
        </div>

        <aside>
            <div class="card side-card">
                <h3>Ý nghĩa Chỉ số Phản xạ</h3>
                <p>Chỉ số này không phải điểm thi đậu/rớt. Đó là thước đo mức độ nhạy bén và độ tự nhiên khi bạn hóa thân giải quyết bài toán của ngành nghề này.</p>
                
                <!-- Pedagogical Action Button Flip -->
                <a class="btn btn-primary btn-lg w-full mt-4" href="<?=url('index.php?page=career&id='.$attempt['career_id'])?>">
                    🎯 Xem Lộ trình Học tập & Nghề
                </a>
                <a class="btn btn-outline w-full mt-3" href="<?=url('index.php?page=simulation&id='.$attempt['career_id'])?>">
                    ↻ Trải nghiệm tình huống khác
                </a>
                <a class="btn w-full mt-3" href="<?=url('index.php?page=simulations')?>">
                    Khám phá phòng nghề khác
                </a>
            </div>
        </aside>
    </div>
</div>
<?php require ROOT_PATH . '/app/footer.php'; exit;
