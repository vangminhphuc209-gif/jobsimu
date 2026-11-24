<?php
declare(strict_types=1);

$jobs = db()->query("SELECT * FROM careers WHERE status='published' ORDER BY featured DESC, title")->fetchAll();

$playable = [];
$developing = [];

foreach ($jobs as $job) {
    $game = simulation_for_career($job);
    if ($game) {
        $playable[] = ['career' => $job, 'game' => $game];
    } else {
        $developing[] = $job;
    }
}

$attempts = [];
$activity = [];
$todayAttemptsCount = 0;

if (user()) {
    $userId = (int)user()['id'];
    $s = db()->prepare('SELECT career_id, MAX(score) AS best_score, COUNT(*) AS attempt_count FROM simulation_attempts WHERE user_id=? GROUP BY career_id');
    $s->execute([$userId]);
    foreach ($s->fetchAll() as $x) {
        $attempts[$x['career_id']] = $x;
    }
    
    // Count today's attempts as pedagogical guardrail
    $sToday = db()->prepare('SELECT COUNT(*) FROM simulation_attempts WHERE user_id=? AND DATE(completed_at) = CURDATE()');
    $sToday->execute([$userId]);
    $todayAttemptsCount = (int)$sToday->fetchColumn();
    
    $activity = four_hub_activity($userId);
}

$pageTitle = 'Phòng trải nghiệm nghề';
require ROOT_PATH . '/app/header.php';
?>
<section class="page-head no-print">
    <div class="container" style="max-width: var(--max-w-content);">
        <nav class="breadcrumb mb-2" aria-label="Đường dẫn trang">
            <a href="<?=url('index.php?page=home')?>">Trang chủ</a>
            <span class="breadcrumb-sep">/</span>
            <span aria-current="page">Trải nghiệm nghề</span>
        </nav>
        <span class="eyebrow" style="color: var(--primary);">Giai đoạn 3 · Trải nghiệm & Phản tư</span>
        <h1 style="font-size: var(--text-3xl); font-weight: 800; margin: var(--space-2) 0 var(--space-3); color: var(--text);">Thử một nghề. Hiểu thêm về mình.</h1>
        <p style="font-size: var(--text-base); color: var(--muted); max-width: 680px; margin: 0; line-height: var(--leading-relaxed);">
            Quan sát, ra quyết định và tự nhìn nhận cảm xúc của bản thân sau từng tình huống thực tế. Không có điểm số quy chụp — mỗi lựa chọn là một cơ hội hiểu rõ hơn về mức độ phù hợp.
        </p>
    </div>
</section>

<div class="container" style="max-width: var(--max-w-content); margin-top: var(--space-6); margin-bottom: var(--space-12);">

    <!-- NHÓM 1: KHÔNG GIAN 3D — VERTICAL SLICE GIÁO VIÊN THCS BÌNH MINH -->
    <section class="card" style="border: 2px solid var(--primary-light); background: linear-gradient(135deg, var(--surface) 0%, var(--surface-alt) 100%); padding: var(--space-6); margin-bottom: var(--space-8); box-shadow: var(--shadow-sm);" aria-labelledby="heading-3d-sim">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div style="flex: 1; min-width: 280px;">
                <span class="badge" style="background: var(--primary); color: #fff; font-size: var(--text-xs); font-weight: 700; margin-bottom: var(--space-2); display: inline-block;">
                    🎮 KHÔNG GIAN 3D · BẢN THỰC NGHIỆM
                </span>
                <h2 id="heading-3d-sim" style="font-size: var(--text-2xl); font-weight: 800; margin: var(--space-2) 0; color: var(--text);">
                    Người thầy sau tiếng trống trường
                </h2>
                <p style="font-size: var(--text-sm); color: var(--text); line-height: var(--leading-relaxed); margin: 0 0 var(--space-3);">
                    Vào vai giáo viên mới nhận nhiệm vụ phụ trách tiết Khoa học tự nhiên Lớp 8A tại trường THCS Bình Minh. Dưới sự hướng dẫn của Cô Mai, bạn sẽ chuẩn bị bài dạy, điều phối 4 nhóm học sinh và kiểm tra mức độ hiểu bài.
                </p>

                <!-- Technical & Hardware Warnings -->
                <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: var(--space-3); margin-bottom: var(--space-4); font-size: var(--text-xs); color: var(--muted); line-height: var(--leading-relaxed);">
                    <div style="margin-bottom: 4px;">
                        💻 <strong>Yêu cầu thiết bị:</strong> Máy tính để bàn hoặc Laptop có bàn phím và chuột (WASD di chuyển, chuột xoay góc nhìn, E tương tác). Chưa hỗ trợ điện thoại cảm ứng.
                    </div>
                    <div style="margin-bottom: 4px;">
                        💾 <strong>Lưu trữ cục bộ:</strong> Kết quả lưu trực tiếp trên trình duyệt thiết bị này (Local Storage), chưa đồng bộ tài khoản.
                    </div>
                    <div>
                        ⚖️ <strong>Chuẩn mực sư phạm:</strong> Tình huống mô phỏng để cảm nhận thực tế nghề giáo, không dùng để gán nhãn hay kết luận khả năng học sinh.
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a class="btn btn-primary" href="<?=url('index.php?page=simulation-3d&career=giao-vien')?>" style="padding: 10px 20px; font-weight: 600;">
                        <?=is_file(ROOT_PATH.'/game/dist/index.html') ? 'Vào trường học 3D →' : 'Xem không gian 3D →'?>
                    </a>
                    <span style="font-size: var(--text-xs); color: var(--muted);">Khoảng 20–30 phút trải nghiệm</span>
                </div>
            </div>

            <div style="background: var(--surface); border: 1.5px dashed var(--border); border-radius: var(--radius-lg); padding: var(--space-5); text-align: center; min-width: 180px;" aria-hidden="true">
                <span style="font-size: 2.5rem; display: block; margin-bottom: var(--space-2);">🏫</span>
                <strong style="font-size: var(--text-sm); color: var(--text); display: block;">LỚP 8A THCS</strong>
                <span style="font-size: var(--text-xs); color: var(--muted); display: block; margin-top: 4px;">Tiết học 45 phút</span>
            </div>
        </div>
    </section>

    <!-- NHÓM 2: TÌNH HUỐNG TƯƠNG TÁC 2D -->
    <section style="margin-bottom: var(--space-8);" aria-labelledby="heading-2d-sim">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <div>
                <span class="eyebrow" style="color: var(--primary);">Tình huống tương tác 2D</span>
                <h2 id="heading-2d-sim" style="font-size: var(--text-xl); font-weight: 700; margin: var(--space-1) 0 0; color: var(--text);">
                    <?=count($playable)?> phòng trải nghiệm theo nghề
                </h2>
            </div>
            <div style="display: flex; align-items: center; gap: var(--space-2);">
                <span class="badge" style="background: var(--surface-alt); color: var(--muted); font-size: var(--text-xs); border: 1px solid var(--border);">
                    Khoảng 4–6 phút / lượt
                </span>
                <?php if (user()): ?>
                    <span class="badge" style="background: <?= $todayAttemptsCount >= 5 ? '#fef2f2' : 'var(--surface-alt)' ?>; color: <?= $todayAttemptsCount >= 5 ? 'var(--danger)' : 'var(--muted)' ?>; font-size: var(--text-xs); border: 1px solid var(--border);">
                        Hôm nay: <?= $todayAttemptsCount ?> / 5 lượt
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-bottom: var(--space-4);">
            <label for="hub-simulation-search" style="font-size: var(--text-xs); font-weight: 600; color: var(--muted); display: block; margin-bottom: 4px;">Tìm nhanh nghề trải nghiệm</label>
            <input id="hub-simulation-search" class="input" data-career-search placeholder="Nhập tên nghề hoặc vai trò thực hiện..." style="max-width: 420px; width: 100%;">
        </div>

        <div class="grid-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4);" id="simulations-grid">
            <?php foreach ($playable as $entry):
                $job = $entry['career'];
                $game = $entry['game'];
                $done = $attempts[$job['id']] ?? null;
            ?>
            <article data-career class="card" style="display: flex; flex-direction: column; justify-content: space-between; border: 1px solid var(--border); transition: border-color 0.2s, box-shadow 0.2s;">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-2);">
                        <span style="font-size: 1.75rem;" aria-hidden="true"><?= e($job['icon'] ?: '💼') ?></span>
                        <?php if ($done): ?>
                            <span class="badge" style="background: #f0fdf4; color: var(--success); border: 1px solid #bbf7d0; font-size: 0.7rem; font-weight: 600;">
                                ✓ Đã thử (<?= (int)$done['attempt_count'] ?> lượt)
                            </span>
                        <?php else: ?>
                            <span class="badge" style="background: var(--surface-alt); color: var(--muted); font-size: 0.7rem;">
                                Chưa thử
                            </span>
                        <?php endif; ?>
                    </div>
                    <h3 style="font-size: var(--text-base); font-weight: 700; margin: 0 0 var(--space-1); color: var(--text);">
                        <?= e($game['title']) ?>
                    </h3>
                    <p style="font-size: var(--text-xs); color: var(--primary); font-weight: 600; margin: 0 0 var(--space-2);">
                        Vai trò: <?= e($game['role']) ?>
                    </p>
                    <p style="font-size: var(--text-xs); color: var(--muted); line-height: var(--leading-relaxed); margin: 0 0 var(--space-3);">
                        <?= e($game['brief']) ?>
                    </p>
                </div>

                <div style="border-top: 1px solid var(--border); padding-top: var(--space-3); margin-top: var(--space-2); display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-size: var(--text-xs); color: var(--muted);"><?= e($job['title']) ?></span>
                    <a href="<?= url('index.php?page=simulation&id=' . (int)$job['id']) ?>" class="btn <?= $done ? 'btn-outline' : 'btn-primary' ?>" style="font-size: var(--text-xs); padding: 5px 12px;">
                        <?= $done ? 'Thử lại →' : 'Bắt đầu →' ?>
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- NHÓM 3: NGHỀ ĐANG PHÁT TRIỂN TÌNH HUỐNG -->
    <details class="card" style="margin-bottom: var(--space-8); border: 1px solid var(--border);">
        <summary style="cursor: pointer; padding: var(--space-2) 0; display: flex; align-items: center; justify-content: space-between; list-style: none;">
            <div>
                <span class="eyebrow" style="color: var(--muted);">Đang biên soạn kịch bản</span>
                <h3 style="font-size: var(--text-base); font-weight: 700; margin: 2px 0 0; color: var(--text);">
                    Các nghề tiếp theo đang được xây dựng (<?= count($developing) ?> nghề)
                </h3>
            </div>
            <span style="font-size: var(--text-xs); color: var(--primary); font-weight: 600;">Xem danh sách ▼</span>
        </summary>

        <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--border);">
            <p style="font-size: var(--text-xs); color: var(--muted); margin-bottom: var(--space-3);">
                Đội ngũ cố vấn đang tham vấn chuyên gia người trong nghề để xây dựng tình huống thực tế và câu hỏi phản tư có căn cứ khoa học. Trong lúc chờ đợi, bạn có thể xem hồ sơ nghề nghiệp chi tiết:
            </p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-2);">
                <?php foreach ($developing as $dJob): ?>
                    <div style="background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: var(--space-2) var(--space-3); display: flex; align-items: center; justify-content: space-between; gap: var(--space-2);">
                        <div style="display: flex; align-items: center; gap: var(--space-2); overflow: hidden;">
                            <span aria-hidden="true"><?= e($dJob['icon'] ?: '💼') ?></span>
                            <span style="font-size: var(--text-xs); font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= e($dJob['title']) ?></span>
                        </div>
                        <a href="<?= url('index.php?page=career&id=' . (int)$dJob['id']) ?>" style="font-size: var(--text-xs); color: var(--primary); white-space: nowrap;">Hồ sơ →</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </details>

    <!-- KHỐI 4: NHẬT KÝ TRẢI NGHIỆM -->
    <section id="activity" class="card" aria-labelledby="heading-activity" style="border: 1.5px solid var(--border);">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <div>
                <span class="eyebrow" style="color: var(--primary);">Hành trình phản tư</span>
                <h2 id="heading-activity" style="font-size: var(--text-xl); font-weight: 700; margin: var(--space-1) 0 0; color: var(--text);">
                    Nhật ký trải nghiệm của bạn
                </h2>
            </div>
            <p style="font-size: var(--text-xs); color: var(--muted); margin: 0;">
                Lịch sử đã lưu của tài khoản. Báo cáo 3D local được xem trong trò chơi.
            </p>
        </div>

        <?php if (!user()): ?>
            <div style="background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: var(--space-4); text-align: center;">
                <p style="font-size: var(--text-sm); color: var(--muted); margin: 0 0 var(--space-2);">Đăng nhập tài khoản để lưu lại cảm xúc và nhật ký sau mỗi lần thử việc.</p>
                <a href="<?=url('index.php?page=login')?>" class="btn btn-primary" style="font-size: var(--text-xs);">Đăng nhập ngay →</a>
            </div>
        <?php elseif (!$activity): ?>
            <div style="background: var(--surface-alt); border: 1px dashed var(--border); border-radius: var(--radius-sm); padding: var(--space-4); text-align: center;">
                <p style="font-size: var(--text-sm); color: var(--muted); margin: 0;">Chưa có trải nghiệm nào được lưu. Hãy chọn một nghề bên trên để bắt đầu thử việc!</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                <?php foreach ($activity as $item): ?>
                    <article style="background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: var(--space-4);">
                        <div class="flex flex-wrap items-center justify-between gap-2" style="margin-bottom: var(--space-2);">
                            <div style="display: flex; align-items: center; gap: var(--space-2);">
                                <h3 style="font-size: var(--text-sm); font-weight: 700; margin: 0; color: var(--text);"><?= e($item['title']) ?></h3>
                                <span class="badge" style="background: var(--surface); border: 1px solid var(--border); font-size: 0.7rem; color: var(--muted);"><?= e($item['mode']) ?></span>
                            </div>
                            <time style="font-size: var(--text-xs); color: var(--muted);"><?= e($item['completed_at']) ?></time>
                        </div>

                        <?php
                        $feelings = ['excited' => '🤩 Cảm giác: Hào hứng', 'challenged' => '🤔 Cảm giác: Thử thách', 'unsuited' => '😐 Cảm giác: Chưa thực sự phù hợp'];
                        if (isset($feelings[$item['reflection_feeling'] ?? ''])):
                        ?>
                            <p style="font-size: var(--text-xs); font-weight: 600; color: var(--primary); margin: 0 0 var(--space-1);">
                                <?= e($feelings[$item['reflection_feeling']]) ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($item['reflection_note'])): ?>
                            <p style="font-size: var(--text-xs); color: var(--text); line-height: var(--leading-relaxed); background: var(--surface); padding: var(--space-2) var(--space-3); border-radius: var(--radius-sm); border-left: 3px solid var(--primary); margin: var(--space-1) 0 var(--space-2);">
                                "<?= nl2br(e($item['reflection_note'])) ?>"
                            </p>
                        <?php endif; ?>

                        <?php if ($item['mode'] === '2D'): ?>
                            <div style="margin-top: var(--space-2); display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
                                    <?php foreach (simulation_skill_labels() as $k => $lbl):
                                        $v = isset($item['skills'][$k]) ? max(0, min(100, (int)$item['skills'][$k])) : null;
                                        if ($v !== null):
                                    ?>
                                        <span style="font-size: 0.7rem; color: var(--muted); background: var(--surface); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--border);">
                                            <?= e($lbl) ?>: <strong><?= $v ?>%</strong>
                                        </span>
                                    <?php endif; endforeach; ?>
                                </div>
                                <a href="<?= url('index.php?page=simulation-result&id=' . (int)$item['id']) ?>" style="font-size: var(--text-xs); color: var(--primary); font-weight: 600; white-space: nowrap;">
                                    Báo cáo chi tiết →
                                </a>
                            </div>
                        <?php else: ?>
                            <p style="font-size: var(--text-xs); color: var(--muted); margin-top: var(--space-2);">
                                Báo cáo 3D dựa trên bằng chứng hành động sư phạm, không quy đổi thành điểm số đơn lẻ.
                            </p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</div>

<script>
// Client-side career simulation search
const simSearchInput = document.getElementById('hub-simulation-search');
if (simSearchInput) {
    simSearchInput.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        const cards = document.querySelectorAll('#simulations-grid [data-career]');
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            card.style.display = text.includes(query) ? '' : 'none';
        });
    });
}
</script>
<?php require ROOT_PATH . '/app/footer.php'; exit;
