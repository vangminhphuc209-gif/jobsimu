<?php
declare(strict_types=1);

/**
 * SOKA 360° Mirror Workflow (v3.0.0).
 * Implements Multi-Rater Behavioral Observation & Johari Window Discrepancy Engine.
 * Supports:
 * - Student invite creation with cryptographic tokens (valid for 14 days).
 * - Multi-role rater feedback (peer, teacher, parent, mentor) on 6 behavioral dimensions (scale 1-5).
 * - Qualitative strength & blindspot observations (max 300 chars).
 * - CSRF verification, input sanitization, and audit logging.
 */

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));

// ==========================================
// SCENARIO A: Rater access via invite_token
// ==========================================
if ($token !== '') {
    if (!preg_match('/^[a-f0-9]{32,64}$/iD', $token)) {
        http_response_code(400);
        $pageTitle = 'Liên kết không hợp lệ';
        require __DIR__.'/header.php';
        echo '<div class="container py-12"><div class="card text-center"><h1 class="text-2xl font-bold text-red-600">Mã liên kết không hợp lệ</h1><p class="muted mt-2">Vui lòng kiểm tra lại liên kết mời đánh giá mà bạn đã nhận được.</p><a class="btn btn-primary mt-4" href="'.url('index.php').'">Về trang chủ</a></div></div>';
        require __DIR__.'/footer.php';
        exit;
    }

    $stmt = db()->prepare('SELECT r.*, u.name as student_name, u.grade as student_grade, u.school as student_school FROM soka_mirror_requests r JOIN users u ON u.id=r.user_id WHERE r.invite_token=?');
    $stmt->execute([$token]);
    $req = $stmt->fetch();

    if (!$req) {
        http_response_code(404);
        $pageTitle = 'Không tìm thấy lời mời';
        require __DIR__.'/header.php';
        echo '<div class="container py-12"><div class="card text-center"><h1 class="text-2xl font-bold">Không tìm thấy yêu cầu đánh giá</h1><p class="muted mt-2">Liên kết này có thể không tồn tại hoặc đã bị xóa.</p><a class="btn btn-primary mt-4" href="'.url('index.php').'">Về trang chủ</a></div></div>';
        require __DIR__.'/footer.php';
        exit;
    }

    $isExpired = !empty($req['expires_at']) && strtotime((string)$req['expires_at']) < time();
    $isClosed = ($req['status'] ?? 'open') !== 'open';

    if ($isClosed || $isExpired) {
        $pageTitle = 'Liên kết đã đóng';
        require __DIR__.'/header.php';
        echo '<div class="container py-12"><div class="card text-center"><h1 class="text-2xl font-bold">Liên kết đánh giá đã đóng hoặc hết hạn</h1><p class="muted mt-2">Lời mời đánh giá 360° này đã hoàn tất hoặc đã hết hạn hiệu lực (14 ngày). Cảm ơn sự quan tâm của bạn!</p><a class="btn btn-primary mt-4" href="'.url('index.php').'">Về trang chủ</a></div></div>';
        require __DIR__.'/footer.php';
        exit;
    }

    $relationLabels = [
        'peer' => 'Bạn bè cùng lớp / nhóm',
        'teacher' => 'Thầy cô giáo',
        'parent' => 'Phụ huynh / Gia đình',
        'mentor' => 'Người hướng dẫn / Anh chị đi trước',
    ];
    $relationText = $relationLabels[$req['rater_relation']] ?? 'Người quan sát';

    $submittedSuccess = false;
    $errorMessage = '';

    if (is_post()) {
        verify_csrf();
        $ratings = [];
        $dims = ['C', 'E', 'A', 'N', 'O', 'analysis'];
        $hasErrors = false;

        foreach ($dims as $dim) {
            $val = $_POST['rating'][$dim] ?? null;
            if (!is_numeric($val) || (int)$val < 1 || (int)$val > 5) {
                $hasErrors = true;
                break;
            }
            $ratings[$dim] = (int)$val;
        }

        if ($hasErrors) {
            $errorMessage = 'Vui lòng đánh giá đủ 6 câu hỏi quan sát hành vi (thang điểm 1 đến 5).';
        } else {
            $strengthNote = mb_substr(trim((string)($_POST['strength_note'] ?? '')), 0, 300);
            $blindspotNote = mb_substr(trim((string)($_POST['blindspot_note'] ?? '')), 0, 300);

            db()->beginTransaction();
            try {
                $ins = db()->prepare('INSERT INTO soka_mirror_responses(request_id, user_id, rater_role, ratings_json, strength_note, blindspot_note) VALUES(?,?,?,?,?,?)');
                $ins->execute([
                    $req['id'],
                    $req['user_id'],
                    $req['rater_relation'],
                    json_encode($ratings),
                    $strengthNote !== '' ? $strengthNote : null,
                    $blindspotNote !== '' ? $blindspotNote : null,
                ]);

                $close = db()->prepare("UPDATE soka_mirror_requests SET status='closed' WHERE id=?");
                $close->execute([$req['id']]);

                db()->commit();
                $submittedSuccess = true;
                audit('Gửi phản hồi đánh giá SOKA 360', 'soka_mirror_response', (int)db()->lastInsertId());
            } catch (Throwable $e) {
                if (db()->inTransaction()) db()->rollBack();
                $errorMessage = 'Có lỗi xảy ra khi lưu phản hồi. Vui lòng thử lại.';
            }
        }
    }

    $pageTitle = 'SOKA 360° Mirror · Quan sát hành vi cho ' . e($req['student_name']);
    require __DIR__.'/header.php';
    ?>
    <div class="container py-8 max-w-3xl">
        <?php if ($submittedSuccess): ?>
            <div class="card text-center py-10">
                <span class="ai-badge mb-3">✦ Ghi nhận thành công</span>
                <h1 class="text-3xl font-black text-brand-600 mt-2">Cảm ơn bạn đã gửi đánh giá!</h1>
                <p class="text-slate-600 mt-3 max-w-xl mx-auto">
                    Góc nhìn quan sát thực tế của bạn về bạn <b><?=e($req['student_name'])?></b> là dữ liệu quý giá giúp bạn ấy đối chiếu tự nhận thức (Johari Window), phát hiện tiềm năng ẩn và nhận diện điểm mù nghề nghiệp.
                </p>
                <div class="mt-6">
                    <a class="btn btn-primary" href="<?=url('index.php')?>">Khám phá JobSimu →</a>
                </div>
            </div>
        <?php else: ?>
            <div class="card mb-6">
                <span class="eyebrow">Khảo sát Quan sát Hành vi 360° · SOKA Mirror</span>
                <h1 class="text-2xl font-black mt-2">Đánh giá khách quan cho <?=e($req['student_name'])?></h1>
                <p class="text-sm text-slate-500 mt-1">
                    <?=e($req['student_grade'] ? $req['student_grade'].' · ' : '')?><?=e($req['student_school'] ?: '')?>
                    — Bạn đang trả lời với tư cách: <b><?=e($relationText)?></b>.
                </p>
                <div class="alert alert-warning mt-4 text-xs">
                    <b>Lưu ý khách quan:</b> Khảo sát này không chấm điểm học tập hay xếp loại đạo đức. Mục đích duy nhất là cung cấp góc nhìn hành vi thực tế giúp học sinh hiểu mình sâu sắc hơn. Câu trả lời của bạn được tổng hợp bảo mật.
                </div>
            </div>

            <?php if ($errorMessage): ?>
                <div class="alert alert-error mb-6"><?=e($errorMessage)?></div>
            <?php endif; ?>

            <form method="post" class="space-y-6">
                <?=csrf_field()?>
                <input type="hidden" name="token" value="<?=e($token)?>">

                <?php
                $questions = [
                    'C' => [
                        'title' => '1. Tính cẩn trọng & Trách nhiệm (Conscientiousness)',
                        'desc' => 'Bạn quan sát thấy bạn ấy làm việc tỉ mỉ, có trách nhiệm và giữ đúng hẹn trong công việc/học tập ở mức nào?',
                        'low' => '1 · Hay quên / Làm qua loa',
                        'high' => '5 · Rất cẩn trọng & đúng hẹn',
                    ],
                    'E' => [
                        'title' => '2. Giao tiếp & Tương tác nhóm (Extraversion)',
                        'desc' => 'Bạn thấy bạn ấy chủ động giao tiếp, trình bày ý kiến và kết nối với mọi người trong tập thể ở mức nào?',
                        'low' => '1 · Khép kín / Hiếm khi lên tiếng',
                        'high' => '5 · Rất chủ động & truyền cảm hứng',
                    ],
                    'A' => [
                        'title' => '3. Lắng nghe & Hỗ trợ bạn bè (Agreeableness)',
                        'desc' => 'Bạn thấy bạn ấy biết lắng nghe, thấu cảm và sẵn lòng hỗ trợ đồng đội khi gặp khó khăn ở mức nào?',
                        'low' => '1 · Ít để tâm đến người khác',
                        'high' => '5 · Rất ấm áp & tận tình giúp đỡ',
                    ],
                    'N' => [
                        'title' => '4. Bình tĩnh trước áp lực (Emotional Stability)',
                        'desc' => 'Khi đối mặt tình huống bất ngờ, căng thẳng hoặc áp lực thi cử/hạn chót, bạn ấy giữ được sự bình tĩnh ở mức nào?',
                        'low' => '1 · Rất dễ lo âu / Mất bình tĩnh',
                        'high' => '5 · Rất điềm tĩnh & vững vàng',
                    ],
                    'O' => [
                        'title' => '5. Góc nhìn sáng tạo & Đổi mới (Openness)',
                        'desc' => 'Bạn thấy bạn ấy cởi mở với cái mới, thích tò mò tìm tòi và thường đưa ra các ý tưởng sáng tạo ở mức nào?',
                        'low' => '1 · Thích lối mòn quen thuộc',
                        'high' => '5 · Rất giàu ý tưởng độc đáo',
                    ],
                    'analysis' => [
                        'title' => '6. Tư duy phân tích có căn cứ (Analytical Thinking)',
                        'desc' => 'Khi giải quyết vấn đề hoặc tranh luận, bạn ấy có thói quen phân tích logic, dựa trên dữ liệu/căn cứ ở mức nào?',
                        'low' => '1 · Cảm tính / Ít kiểm chứng',
                        'high' => '5 · Rất logic & có căn cứ chặt chẽ',
                    ],
                ];
                ?>

                <?php foreach ($questions as $qKey => $qData): ?>
                    <section class="card">
                        <h2 class="text-base font-bold"><?=e($qData['title'])?></h2>
                        <p class="text-sm text-slate-600 mt-1"><?=e($qData['desc'])?></p>
                        <div class="mt-4 flex flex-wrap gap-2 items-center justify-between">
                            <span class="text-xs text-slate-400 font-medium"><?=e($qData['low'])?></span>
                            <div class="flex gap-3">
                                <?php for ($val = 1; $val <= 5; $val++): ?>
                                    <label class="flex flex-col items-center gap-1 cursor-pointer">
                                        <input type="radio" name="rating[<?=e($qKey)?>]" value="<?=$val?>" required class="w-5 h-5 text-brand-600" <?=isset($_POST['rating'][$qKey]) && (int)$_POST['rating'][$qKey] === $val ? 'checked' : ($val === 3 ? 'checked' : '')?>>
                                        <span class="text-xs font-bold text-slate-600"><?=$val?></span>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <span class="text-xs text-slate-400 font-medium"><?=e($qData['high'])?></span>
                        </div>
                    </section>
                <?php endforeach; ?>

                <section class="card">
                    <h2 class="text-base font-bold">7. Ghi nhận phẩm chất & Góp ý chân thành (Không bắt buộc)</h2>
                    <p class="text-sm text-slate-500 mt-1">Lời nhận xét cụ thể sẽ giúp bạn ấy nhận diện những tiềm năng mà bản thân chưa từng để ý.</p>

                    <div class="mt-4 form-group">
                        <label class="font-semibold text-sm" for="strength_note">Điểm mạnh hoặc phẩm chất nổi bật nhất bạn quan sát thấy ở bạn ấy:</label>
                        <textarea class="input" id="strength_note" name="strength_note" rows="2" maxlength="300" placeholder="Ví dụ: Bạn ấy rất kiên trì khi giải bài khó, luôn chủ động hỗ trợ nhóm khi gặp sự cố..."><?=e($_POST['strength_note'] ?? '')?></textarea>
                        <small class="text-slate-400">Tối đa 300 ký tự</small>
                    </div>

                    <div class="mt-4 form-group">
                        <label class="font-semibold text-sm" for="blindspot_note">Gợi ý điểm cải thiện hoặc điều bạn ấy có thể chưa nhận ra ở bản thân:</label>
                        <textarea class="input" id="blindspot_note" name="blindspot_note" rows="2" maxlength="300" placeholder="Ví dụ: Đôi khi bạn ấy hơi ngại nói ra ý kiến riêng của mình dù ý tưởng rất hay..."><?=e($_POST['blindspot_note'] ?? '')?></textarea>
                        <small class="text-slate-400">Tối đa 300 ký tự</small>
                    </div>
                </section>

                <div class="text-center pt-2">
                    <button class="btn btn-primary btn-lg w-full md:w-auto px-8" type="submit">Gửi phản hồi đánh giá 360° →</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <?php
    require __DIR__.'/footer.php';
    exit;
}

// ==========================================
// SCENARIO B: Student management dashboard
// ==========================================
require_login();
$studentId = (int)user()['id'];

// Handle create invite
if (is_post() && ($_POST['action'] ?? '') === 'create_invite') {
    verify_csrf();
    $relation = (string)($_POST['rater_relation'] ?? 'peer');
    if (!in_array($relation, ['peer', 'teacher', 'parent', 'mentor'], true)) {
        $relation = 'peer';
    }

    $inviteToken = bin2hex(random_bytes(16));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+14 days'));

    $ins = db()->prepare('INSERT INTO soka_mirror_requests(user_id, invite_token, rater_relation, status, expires_at) VALUES(?,?,?,\'open\',?)');
    $ins->execute([$studentId, $inviteToken, $relation, $expiresAt]);

    audit('Tạo liên kết mời đánh giá SOKA 360', 'soka_mirror_request', (int)db()->lastInsertId());
    flash('success', 'Đã tạo liên kết mời thành công! Hãy sao chép liên kết bên dưới và gửi cho người bạn muốn nhận đánh giá.');
    redirect('index.php?page=soka-mirror');
}

// Handle close invite
if (is_post() && ($_POST['action'] ?? '') === 'close_invite') {
    verify_csrf();
    $reqId = (int)($_POST['request_id'] ?? 0);
    $upd = db()->prepare("UPDATE soka_mirror_requests SET status='closed' WHERE id=? AND user_id=?");
    $upd->execute([$reqId, $studentId]);
    flash('success', 'Đã đóng liên kết mời.');
    redirect('index.php?page=soka-mirror');
}

// Fetch all requests
$reqStmt = db()->prepare('SELECT * FROM soka_mirror_requests WHERE user_id=? ORDER BY created_at DESC');
$reqStmt->execute([$studentId]);
$requests = $reqStmt->fetchAll() ?: [];

// Fetch all responses
$respStmt = db()->prepare('SELECT resp.*, req.invite_token FROM soka_mirror_responses resp JOIN soka_mirror_requests req ON req.id=resp.request_id WHERE resp.user_id=? ORDER BY resp.submitted_at DESC');
$respStmt->execute([$studentId]);
$responses = $respStmt->fetchAll() ?: [];

$relationLabels = [
    'peer' => 'Bạn bè cùng lớp / nhóm',
    'teacher' => 'Thầy cô giáo',
    'parent' => 'Phụ huynh / Gia đình',
    'mentor' => 'Người hướng dẫn / Mentor',
];

// Compute response statistics
$responseCount = count($responses);
$dimAverages = [];
if ($responseCount > 0) {
    $sums = ['C' => 0.0, 'E' => 0.0, 'A' => 0.0, 'N' => 0.0, 'O' => 0.0, 'analysis' => 0.0];
    $counts = ['C' => 0, 'E' => 0, 'A' => 0, 'N' => 0, 'O' => 0, 'analysis' => 0];
    foreach ($responses as $r) {
        $vals = json_decode($r['ratings_json'] ?? '{}', true) ?: [];
        foreach ($sums as $k => $v) {
            if (isset($vals[$k]) && is_numeric($vals[$k])) {
                $sums[$k] += ((float)$vals[$k] - 1.0) / 4.0 * 100.0;
                $counts[$k]++;
            }
        }
    }
    foreach ($sums as $k => $sum) {
        $dimAverages[$k] = $counts[$k] > 0 ? (int)round($sum / $counts[$k]) : null;
    }
}

$pageTitle = 'SOKA 360° Mirror · Góc nhìn Người quan sát';
require __DIR__.'/header.php';
?>
<section class="page-head">
    <div class="container">
        <span class="eyebrow">Hub 2 · Cửa sổ Johari & Đối chiếu Nhận thức Đa nguồn</span>
        <h1>SOKA 360° Mirror · Góc nhìn Người quan sát</h1>
        <p>Mời bạn bè, thầy cô hoặc phụ huynh gửi đánh giá quan sát hành vi thực tế để nhận diện điểm mù (Blind Spot) và tiềm năng ẩn (Hidden Strength).</p>
    </div>
</section>

<div class="container py-6">
    <div class="dashboard-grid">
        <div>
            <!-- Overview & Summary Card -->
            <div class="card mb-6">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <span class="tag">Mô hình SOKA · Vazire & Mehl</span>
                        <h2 class="text-2xl font-black mt-2">Dữ liệu quan sát hành vi thực tế</h2>
                    </div>
                    <span class="ai-badge"><?=$responseCount?> phản hồi đã nhận</span>
                </div>

                <p class="text-sm text-slate-600 mt-3">
                    Theo nghiên cứu của TS. Simine Vazire (Mô hình SOKA — Self-Other Knowledge Asymmetry), bản thân mỗi người thường hiểu rõ nhất những trải nghiệm nội tâm (như mức độ lo âu), nhưng <b>người khác lại quan sát chính xác hơn</b> các hành vi bộc lộ ra ngoài (như tính kỷ luật, sự chủ động và phong cách giao tiếp).
                </p>

                <?php if ($responseCount > 0): ?>
                    <h3 class="font-bold text-base mt-6 mb-3">Trung bình điểm quan sát từ người khác (Quy chuẩn 0–100):</h3>
                    <div class="score-grid">
                        <?php
                        $dimTitles = [
                            'C' => 'Tỉ mỉ & Đúng hẹn',
                            'E' => 'Giao tiếp nhóm',
                            'A' => 'Lắng nghe & Hỗ trợ',
                            'N' => 'Bình tĩnh áp lực',
                            'O' => 'Sáng tạo đổi mới',
                            'analysis' => 'Tư duy phân tích',
                        ];
                        foreach ($dimTitles as $dKey => $dTitle):
                            $avgVal = $dimAverages[$dKey] ?? 0;
                        ?>
                            <div class="score-item">
                                <div class="score-ring" style="--v:<?=$avgVal?>;--c:var(--primary)">
                                    <span><?=$avgVal?>%</span>
                                </div>
                                <b><?=e($dTitle)?></b>
                                <small class="muted"><?=$dKey?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-5">
                        <a class="btn btn-primary" href="<?=url('index.php?page=assessment-report')?>">Mở Báo cáo Đối chiếu Điểm mù (Johari Report) →</a>
                    </div>
                <?php else: ?>
                    <div class="card empty mt-4">
                        Chưa có người nào gửi đánh giá cho bạn. Hãy tạo liên kết mời bên cạnh và gửi cho bạn bè hoặc thầy cô!
                    </div>
                <?php endif; ?>
            </div>

            <!-- Qualitative notes received -->
            <?php if ($responseCount > 0): ?>
                <div class="card mb-6">
                    <h2 class="text-xl font-bold mb-4">Nhận xét định tính từ người quan sát</h2>
                    <div class="space-y-4">
                        <?php foreach ($responses as $rIdx => $resp): ?>
                            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50">
                                <div class="flex items-center justify-between text-xs text-slate-500 mb-2">
                                    <span><b><?=e($relationLabels[$resp['rater_role']] ?? 'Người quan sát')?></b></span>
                                    <span><?=date('d/m/Y H:i', strtotime($resp['submitted_at']))?></span>
                                </div>
                                <?php if (!empty($resp['strength_note'])): ?>
                                    <p class="text-sm text-emerald-800 bg-emerald-50 p-2.5 rounded-lg border border-emerald-200 mb-2">
                                        <b>✦ Điểm mạnh quan sát thấy:</b> <?=e($resp['strength_note'])?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($resp['blindspot_note'])): ?>
                                    <p class="text-sm text-blue-800 bg-blue-50 p-2.5 rounded-lg border border-blue-200">
                                        <b>◇ Gợi ý cải thiện:</b> <?=e($resp['blindspot_note'])?>
                                    </p>
                                <?php endif; ?>
                                <?php if (empty($resp['strength_note']) && empty($resp['blindspot_note'])): ?>
                                    <p class="text-xs text-slate-400 italic">Người đánh giá chỉ gửi điểm số thang đo, không để lại lời nhận xét.</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- List of created invites -->
            <div class="card">
                <h2 class="text-xl font-bold mb-4">Lịch sử liên kết mời đánh giá</h2>
                <?php if (empty($requests)): ?>
                    <p class="text-sm text-slate-500">Chưa có liên kết mời nào được tạo.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($requests as $req):
                            $link = url('index.php?page=soka-mirror&token=' . $req['invite_token']);
                            $isExp = !empty($req['expires_at']) && strtotime($req['expires_at']) < time();
                            $isOpen = $req['status'] === 'open' && !$isExp;
                        ?>
                            <div class="p-3.5 rounded-xl border border-slate-200 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <b class="text-sm"><?=e($relationLabels[$req['rater_relation']] ?? $req['rater_relation'])?></b>
                                        <span class="tag <?=$isOpen ? 'text-emerald-700 bg-emerald-100' : 'text-slate-500 bg-slate-100'?>">
                                            <?=$isOpen ? 'Đang mở' : ($isExp ? 'Hết hạn' : 'Đã phản hồi')?>
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-1">Tạo: <?=date('d/m/Y', strtotime($req['created_at']))?> · Hết hạn: <?=date('d/m/Y', strtotime($req['expires_at']))?></p>
                                    <input type="text" readonly class="input text-xs mt-1.5 py-1" style="max-width:380px" value="<?=e($link)?>">
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if ($isOpen): ?>
                                        <button type="button" class="btn btn-outline text-xs py-1.5 px-3" onclick="navigator.clipboard.writeText('<?=e($link)?>').then(()=>alert('Đã sao chép liên kết vào bộ nhớ tạm!'))">📋 Sao chép</button>
                                        <form method="post" onsubmit="return confirm('Bạn chắc chắn muốn đóng liên kết này?');">
                                            <?=csrf_field()?>
                                            <input type="hidden" name="action" value="close_invite">
                                            <input type="hidden" name="request_id" value="<?=$req['id']?>">
                                            <button type="submit" class="btn text-xs py-1.5 px-2.5 text-red-600 hover:bg-red-50">Đóng</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400">Đã đóng</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar: Create Invite -->
        <aside>
            <div class="card">
                <span class="eyebrow">Tạo liên kết mới</span>
                <h3 class="text-lg font-black mt-1">Mời người quan sát</h3>
                <p class="text-xs text-slate-500 mt-1">Mỗi liên kết có mã ngẫu nhiên bảo mật, có hiệu lực trong 14 ngày và chỉ được điền một lần.</p>

                <form method="post" class="mt-4 space-y-4">
                    <?=csrf_field()?>
                    <input type="hidden" name="action" value="create_invite">

                    <div class="form-group">
                        <label class="text-sm font-bold" for="rater_relation">Đối tượng bạn muốn mời:</label>
                        <select class="input" id="rater_relation" name="rater_relation" required>
                            <option value="peer">Bạn bè cùng lớp / nhóm làm việc</option>
                            <option value="teacher">Thầy cô giáo chủ nhiệm / bộ môn</option>
                            <option value="parent">Bố mẹ / Người thân trong gia đình</option>
                            <option value="mentor">Anh chị đi trước / Người hướng dẫn</option>
                        </select>
                    </div>

                    <p class="text-xs text-slate-400">
                        Sau khi bấm tạo, bạn sẽ nhận được một đường link chia sẻ. Bạn có thể gửi link qua tin nhắn Zalo, Messenger hoặc email.
                    </p>

                    <button class="btn btn-primary w-full" type="submit">Tạo liên kết mời 360° ✦</button>
                </form>

                <hr style="border:0;border-top:1px solid var(--line);margin:20px 0">

                <div class="text-xs text-slate-500 space-y-2">
                    <p><b>Vì sao cần góc nhìn 360°?</b></p>
                    <p>Nghiên cứu tâm lý học cho thấy khi tự đánh giá, học sinh dễ bị ảnh hưởng bởi nỗi sợ thất bại hoặc kỳ vọng của người khác. Góc nhìn của người quan sát bên ngoài giúp kiểm chứng lại niềm tin năng lực.</p>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php
require __DIR__.'/footer.php';
