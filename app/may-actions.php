<?php
declare(strict_types=1);

/**
 * Canonical Action Registry for Mây Chatbot.
 * All URLs are strictly application-controlled. The model can never invent routes.
 */
function may_action_registry(): array {
    return [
        'open_assessments' => [
            'id' => 'open_assessments',
            'name' => 'Làm bài đánh giá RIASEC',
            'path' => 'index.php?page=assessment-hub',
            'login_required' => false,
            'role' => 'all',
            'summary' => 'Mở Bộ đánh giá bản thân (Holland RIASEC, Big Five, CAAS, MBTI, EQ).',
            'icon' => '🧭',
            'keywords' => ['đánh giá', 'trắc nghiệm', 'riasec', 'holland', 'test', 'làm bài', 'khám phá bản thân']
        ],
        'view_my_report' => [
            'id' => 'view_my_report',
            'name' => 'Xem báo cáo của tôi',
            'path' => 'index.php?page=assessment-report',
            'login_required' => true,
            'role' => 'student',
            'summary' => 'Xem lại tổng hợp kết quả các bài trắc nghiệm và hồ sơ năng lực cá nhân.',
            'icon' => '📊',
            'keywords' => ['báo cáo', 'kết quả của tôi', 'xem điểm', 'hồ sơ của tôi', 'kết quả bài test']
        ],
        'explore_majors' => [
            'id' => 'explore_majors',
            'name' => 'Danh mục ngành đào tạo',
            'path' => 'index.php?page=majors',
            'login_required' => false,
            'role' => 'all',
            'summary' => 'Tra cứu các ngành đào tạo đại học, mã ngành, nhóm ngành và trường đào tạo.',
            'icon' => '🎓',
            'keywords' => ['ngành', 'ngành đào tạo', 'mã ngành', 'tìm ngành', 'tra ngành']
        ],
        'search_schools' => [
            'id' => 'search_schools',
            'name' => 'Tra cứu trường & Điểm chuẩn',
            'path' => 'index.php?page=university-explorer',
            'login_required' => false,
            'role' => 'all',
            'summary' => 'Tìm kiếm các trường đại học/cao đẳng, xem phương thức xét tuyển, điểm chuẩn và học phí.',
            'icon' => '🏫',
            'keywords' => ['tìm trường', 'tra cứu trường', 'điểm chuẩn', 'học phí', 'tuyển sinh', 'trường đại học']
        ],
        'compare_schools' => [
            'id' => 'compare_schools',
            'name' => 'So sánh trường',
            'path' => 'index.php?page=university-compare',
            'login_required' => false,
            'role' => 'all',
            'summary' => 'Đặt 2-3 trường cạnh nhau để so sánh điểm chuẩn, học phí và chương trình.',
            'icon' => '⚖️',
            'keywords' => ['so sánh trường', 'so sánh điểm chuẩn', 'đối chiếu trường']
        ],
        'explore_scenarios' => [
            'id' => 'explore_scenarios',
            'name' => 'Tình huống người trong nghề',
            'path' => 'index.php?page=ai-roadmap#career-conversations',
            'login_required' => false,
            'role' => 'all',
            'summary' => 'Khám phá 9 tình huống thực tế của giáo viên, lập trình viên và thiết kế đồ họa.',
            'icon' => '💬',
            'keywords' => ['tình huống', 'người trong nghề', 'giả định', 'xử lý tình huống', 'thử thách nghề']
        ],
        'explore_careers' => [
            'id' => 'explore_careers',
            'name' => 'Thư viện nghề nghiệp',
            'path' => 'index.php?page=careers',
            'login_required' => false,
            'role' => 'all',
            'summary' => 'Duyệt hơn 20 nghề nghiệp phổ biến với mô tả chi tiết, kỹ năng và lộ trình học tập.',
            'icon' => '💼',
            'keywords' => ['thư viện nghề', 'danh sách nghề', 'các nghề', 'tìm nghề', 'nghề nghiệp']
        ],
        'view_roadmap' => [
            'id' => 'view_roadmap',
            'name' => 'Bản đồ hành trình AI',
            'path' => 'index.php?page=ai-roadmap',
            'login_required' => false,
            'role' => 'all',
            'summary' => 'Xem lộ trình hướng nghiệp từng bước kết nối giữa sở thích, ngành học và mục tiêu nghề.',
            'icon' => '🗺️',
            'keywords' => ['hành trình', 'lộ trình', 'roadmap', 'kế hoạch học tập', 'bản đồ']
        ],
        'open_simulations' => [
            'id' => 'open_simulations',
            'name' => 'Phòng mô phỏng nghề',
            'path' => 'index.php?page=simulations',
            'login_required' => false,
            'role' => 'all',
            'summary' => 'Tham gia mô phỏng tương tác các nhiệm vụ công việc thực tế.',
            'icon' => '🎮',
            'keywords' => ['mô phỏng', 'chơi thử', 'game nghề', 'phòng mô phỏng']
        ],
    ];
}

/**
 * Validate and build an authorized action button descriptor.
 */
function may_build_action_button(string $actionId, ?array $user = null, array $params = []): ?array {
    $registry = may_action_registry();
    
    // Dynamic career action
    if ($actionId === 'view_career' && !empty($params['career_id'])) {
        $cid = (int)$params['career_id'];
        return [
            'id' => 'view_career_' . $cid,
            'label' => 'Xem chi tiết nghề này',
            'url' => 'index.php?page=career&id=' . $cid,
            'icon' => '💼',
            'login_required' => false
        ];
    }

    // Dynamic major action
    if ($actionId === 'view_major' && !empty($params['major_code'])) {
        $code = urlencode(trim((string)$params['major_code']));
        return [
            'id' => 'view_major_' . $code,
            'label' => 'Xem chi tiết ngành này',
            'url' => 'index.php?page=majors&search=' . $code,
            'icon' => '🎓',
            'login_required' => false
        ];
    }

    if (!isset($registry[$actionId])) {
        return null;
    }

    $entry = $registry[$actionId];

    // Check login requirement
    if ($entry['login_required'] && empty($user)) {
        return [
            'id' => 'login_to_' . $actionId,
            'label' => 'Đăng nhập để ' . mb_strtolower($entry['name']),
            'url' => 'index.php?page=login',
            'icon' => '🔐',
            'login_required' => true,
            'notice' => 'Chức năng này cần đăng nhập tài khoản học sinh.'
        ];
    }

    return [
        'id' => $entry['id'],
        'label' => $entry['name'],
        'url' => $entry['path'],
        'icon' => $entry['icon'],
        'login_required' => $entry['login_required']
    ];
}

/**
 * Detect matching actions from query text or explicit tags.
 */
function may_detect_actions(string $text, ?array $user = null, array $context = []): array {
    $actions = [];
    $lower = mb_strtolower($text);

    // 1. Explicit model tags: [action:action_id] or [action:view_career:12]
    if (preg_match_all('/\[action:([a-z_]+)(?::([a-z0-9_-]+))?\]/i', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $actId = $m[1];
            $param = $m[2] ?? '';
            $params = [];
            if ($actId === 'view_career') $params['career_id'] = $param;
            if ($actId === 'view_major') $params['major_code'] = $param;

            $btn = may_build_action_button($actId, $user, $params);
            if ($btn) $actions[$btn['id']] = $btn;
        }
    }

    // 2. Keyword matching from registry
    $registry = may_action_registry();
    foreach ($registry as $actId => $entry) {
        foreach ($entry['keywords'] as $kw) {
            if (mb_strpos($lower, $kw) !== false) {
                $btn = may_build_action_button($actId, $user);
                if ($btn && !isset($actions[$btn['id']])) {
                    $actions[$btn['id']] = $btn;
                }
                break;
            }
        }
        if (count($actions) >= 3) break;
    }

    // 3. Contextual action: if user is browsing a career/major and asking about it
    if (!empty($context['career_id'])) {
        $btn = may_build_action_button('view_career', $user, ['career_id' => $context['career_id']]);
        if ($btn && !isset($actions[$btn['id']])) $actions[$btn['id']] = $btn;
    }

    return array_values($actions);
}

/**
 * Strip raw [action:...] tags from message to leave clean, readable prose.
 */
function may_clean_action_tags(string $text): string {
    return trim(preg_replace('/\[action:[a-z0-9_:]+\]/i', '', $text));
}
