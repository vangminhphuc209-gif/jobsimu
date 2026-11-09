<?php
// Group names follow the referenced taxonomy; cost tiers are rough planning hints, not school tuition data.
// v3.0.0 adds `sacrifices` (Realistic Job Preview trade-offs) and `big5_weights` (empirically grounded Five-Factor profiles).
return [
    'lap-trinh-vien' => [
        'group' => 'Máy tính và công nghệ thông tin', 'cost' => 2, 'years' => 4,
        'paths' => ['university', 'college', 'vocational'], 'physical' => 'low', 'portfolio' => false,
        'sacrifices' => ['debugging_detail_grind'],
        'big5_weights' => ['O' => 75, 'C' => 85, 'E' => 40, 'A' => 55, 'N' => 70],
    ],
    'ky-su-tri-tue-nhan-tao' => [
        'group' => 'Máy tính và công nghệ thông tin', 'cost' => 3, 'years' => 4,
        'paths' => ['university'], 'physical' => 'low', 'portfolio' => false,
        'sacrifices' => ['debugging_detail_grind', 'high_academic_duration'],
        'big5_weights' => ['O' => 90, 'C' => 85, 'E' => 40, 'A' => 50, 'N' => 70],
    ],
    'bac-si' => [
        'group' => 'Sức khỏe', 'cost' => 3, 'years' => 6,
        'paths' => ['university'], 'physical' => 'medium', 'portfolio' => false,
        'sacrifices' => ['high_academic_duration', 'emotional_labor_crisis'],
        'big5_weights' => ['O' => 65, 'C' => 95, 'E' => 55, 'A' => 85, 'N' => 85],
    ],
    'nha-thiet-ke-do-hoa' => [
        'group' => 'Nghệ thuật và mỹ thuật ứng dụng', 'cost' => 2, 'years' => 4,
        'paths' => ['university', 'college', 'vocational'], 'physical' => 'low', 'portfolio' => true,
        'sacrifices' => ['creative_rejection_deadline'],
        'big5_weights' => ['O' => 95, 'C' => 65, 'E' => 55, 'A' => 65, 'N' => 60],
    ],
    'giao-vien' => [
        'group' => 'Giáo dục', 'cost' => 2, 'years' => 4,
        'paths' => ['university'], 'physical' => 'low', 'portfolio' => false,
        'sacrifices' => ['emotional_labor_crisis'],
        'big5_weights' => ['O' => 75, 'C' => 80, 'E' => 75, 'A' => 90, 'N' => 80],
    ],
    'chuyen-vien-marketing' => [
        'group' => 'Kinh doanh và quản lý', 'cost' => 2, 'years' => 4,
        'paths' => ['university', 'college'], 'physical' => 'low', 'portfolio' => true,
        'sacrifices' => ['creative_rejection_deadline'],
        'big5_weights' => ['O' => 85, 'C' => 70, 'E' => 85, 'A' => 70, 'N' => 70],
    ],
    'kien-truc-su' => [
        'group' => 'Kiến trúc và xây dựng', 'cost' => 3, 'years' => 5,
        'paths' => ['university'], 'physical' => 'medium', 'portfolio' => true,
        'sacrifices' => ['creative_rejection_deadline', 'high_academic_duration'],
        'big5_weights' => ['O' => 90, 'C' => 80, 'E' => 55, 'A' => 60, 'N' => 70],
    ],
    'ky-su-co-khi' => [
        'group' => 'Kỹ thuật', 'cost' => 2, 'years' => 4,
        'paths' => ['university', 'college', 'vocational'], 'physical' => 'medium', 'portfolio' => false,
        'sacrifices' => ['debugging_detail_grind'],
        'big5_weights' => ['O' => 65, 'C' => 85, 'E' => 45, 'A' => 55, 'N' => 75],
    ],
    'chuyen-vien-tam-ly' => [
        'group' => 'Khoa học xã hội và hành vi', 'cost' => 2, 'years' => 4,
        'paths' => ['university'], 'physical' => 'low', 'portfolio' => false,
        'sacrifices' => ['emotional_labor_crisis', 'high_academic_duration'],
        'big5_weights' => ['O' => 80, 'C' => 75, 'E' => 60, 'A' => 95, 'N' => 85],
    ],
    'luat-su' => [
        'group' => 'Pháp luật', 'cost' => 2, 'years' => 4,
        'paths' => ['university'], 'physical' => 'low', 'portfolio' => false,
        'sacrifices' => ['high_academic_duration', 'debugging_detail_grind'],
        'big5_weights' => ['O' => 75, 'C' => 90, 'E' => 75, 'A' => 50, 'N' => 80],
    ],
    'nha-bao-bien-tap-vien' => [
        'group' => 'Báo chí và thông tin', 'cost' => 2, 'years' => 4,
        'paths' => ['university', 'college'], 'physical' => 'medium', 'portfolio' => true,
        'sacrifices' => ['creative_rejection_deadline'],
        'big5_weights' => ['O' => 85, 'C' => 75, 'E' => 80, 'A' => 65, 'N' => 70],
    ],
    'chuyen-vien-tai-chinh' => [
        'group' => 'Kinh doanh và quản lý', 'cost' => 2, 'years' => 4,
        'paths' => ['university', 'college'], 'physical' => 'low', 'portfolio' => false,
        'sacrifices' => ['debugging_detail_grind'],
        'big5_weights' => ['O' => 60, 'C' => 95, 'E' => 60, 'A' => 55, 'N' => 80],
    ],
    'quan-tri-nhan-su' => [
        'group' => 'Kinh doanh và quản lý', 'cost' => 2, 'years' => 4,
        'paths' => ['university', 'college'], 'physical' => 'low', 'portfolio' => false,
        'sacrifices' => ['emotional_labor_crisis'],
        'big5_weights' => ['O' => 65, 'C' => 80, 'E' => 80, 'A' => 85, 'N' => 75],
    ],
    'ky-su-moi-truong' => [
        'group' => 'Khoa học môi trường và kỹ thuật', 'cost' => 2, 'years' => 4,
        'paths' => ['university', 'college'], 'physical' => 'medium', 'portfolio' => false,
        'sacrifices' => ['debugging_detail_grind'],
        'big5_weights' => ['O' => 75, 'C' => 80, 'E' => 50, 'A' => 70, 'N' => 70],
    ],
    'dau-bep' => [
        'group' => 'Du lịch, khách sạn và dịch vụ', 'cost' => 1, 'years' => 2,
        'paths' => ['college', 'vocational'], 'physical' => 'high', 'portfolio' => false,
        'sacrifices' => ['creative_rejection_deadline'],
        'big5_weights' => ['O' => 75, 'C' => 85, 'E' => 60, 'A' => 65, 'N' => 75],
    ],
    'si-quan-quan-doi' => [
        'group' => 'An ninh, quốc phòng', 'cost' => null, 'years' => 4,
        'paths' => ['university'], 'physical' => 'high', 'portfolio' => false, 'selection' => true,
        'sacrifices' => ['strict_physical_discipline'],
        'big5_weights' => ['O' => 55, 'C' => 95, 'E' => 70, 'A' => 65, 'N' => 90],
    ],
    'cong-an-nhan-dan' => [
        'group' => 'An ninh, quốc phòng', 'cost' => null, 'years' => 4,
        'paths' => ['university'], 'physical' => 'high', 'portfolio' => false, 'selection' => true,
        'sacrifices' => ['strict_physical_discipline'],
        'big5_weights' => ['O' => 55, 'C' => 95, 'E' => 70, 'A' => 65, 'N' => 90],
    ],
    'dieu-duong' => [
        'group' => 'Sức khỏe', 'cost' => 2, 'years' => 4,
        'paths' => ['university', 'college'], 'physical' => 'medium', 'portfolio' => false, 'license' => true,
        'sacrifices' => ['emotional_labor_crisis'],
        'big5_weights' => ['O' => 60, 'C' => 85, 'E' => 60, 'A' => 90, 'N' => 80],
    ],
    'cong-tac-xa-hoi' => [
        'group' => 'Dịch vụ xã hội', 'cost' => 1, 'years' => 4,
        'paths' => ['university', 'college'], 'physical' => 'low', 'portfolio' => false,
        'sacrifices' => ['emotional_labor_crisis'],
        'big5_weights' => ['O' => 70, 'C' => 75, 'E' => 75, 'A' => 95, 'N' => 80],
    ],
];
