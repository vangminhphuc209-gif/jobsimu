# JOBSIMU v3.0: Tài Liệu Hướng Dẫn Triển Khai Kỹ Thuật Chi Tiết
## Psychometric & Evidence-Centered Architecture (Baseline v2.2.0 -> v3.0.0)

Tài liệu này đóng vai trò là kim chỉ nam kỹ thuật chuẩn hóa, tổng hợp toàn bộ các ngân hàng câu hỏi, công thức toán học, cấu trúc JSON và mã nguồn tham chiếu để triển khai nâng cấp hệ thống Hướng Nghiệp 360 lên phiên bản 3.0.0.

---

## 1. Ngân Hàng Câu Hỏi & Khóa Chấm Tâm Lý Học Chuẩn Hóa

### 1.1. Thang đo Big Five Mini-IPIP (20 câu)
*Nguồn gốc: Donnellan et al. (2006) / Goldberg (1999) — International Personality Item Pool (IPIP)*  
*Thang đo: 1 (Rất không giống tôi) đến 5 (Rất giống tôi)*  
*Công thức đảo chiều: Khi `pole === '-'`, điểm chuyển đổi $= 6 - \text{value}$*

```php
function big5_questions(): array {
    return [
        // Extraversion (E) - Năng lượng xã hội & Hướng ngoại
        'b01' => ['axis' => 'E', 'pole' => '+', 'text' => 'Tôi thường là người khuấy động không khí trong các buổi gặp gỡ bạn bè.'],
        'b02' => ['axis' => 'E', 'pole' => '-', 'text' => 'Tôi thường ít nói và giữ khoảng lặng khi ở giữa đám đông.'],
        'b03' => ['axis' => 'E', 'pole' => '+', 'text' => 'Tôi cảm thấy thoải mái và dễ dàng bắt chuyện với người mới.'],
        'b04' => ['axis' => 'E', 'pole' => '-', 'text' => 'Tôi thường chọn vị trí quan sát ở phía sau thay vì đứng ở trung tâm.'],

        // Agreeableness (A) - Hòa đồng & Thấu cảm hợp tác
        'b05' => ['axis' => 'A', 'pole' => '+', 'text' => 'Tôi dễ đồng cảm và thấu hiểu cảm xúc của người khác khi họ gặp chuyện.'],
        'b06' => ['axis' => 'A', 'pole' => '-', 'text' => 'Tôi ít khi để tâm tới những khó khăn hay vấn đề của người không thân thiết.'],
        'b07' => ['axis' => 'A', 'pole' => '+', 'text' => 'Tôi chân thành quan tâm và sẵn lòng hỗ trợ bạn bè khi có thể.'],
        'b08' => ['axis' => 'A', 'pole' => '-', 'text' => 'Tôi ít khi xúc động trước những câu chuyện khó khăn của người khác.'],

        // Conscientiousness (C) - Tận tâm, Kỷ luật & Tổ chức
        'b09' => ['axis' => 'C', 'pole' => '+', 'text' => 'Tôi luôn chuẩn bị công việc kỹ lưỡng và hoàn thành nhiệm vụ đúng hạn.'],
        'b10' => ['axis' => 'C', 'pole' => '-', 'text' => 'Tôi hay để đồ đạc bừa bộn và làm việc theo cảm hứng nhất thời.'],
        'b11' => ['axis' => 'C', 'pole' => '+', 'text' => 'Tôi thích làm việc theo kế hoạch rõ ràng và các bước tuần tự.'],
        'b12' => ['axis' => 'C', 'pole' => '-', 'text' => 'Tôi hay trì hoãn hoặc quên cất đồ đạc về đúng vị trí quy định.'],

        // Emotional Stability (N/ES) - Ổn định cảm xúc & Khả năng chịu áp lực
        'b13' => ['axis' => 'N', 'pole' => '-', 'text' => 'Tâm trạng của tôi khá thất thường và dễ cảm thấy lo lắng không yên.'],
        'b14' => ['axis' => 'N', 'pole' => '+', 'text' => 'Nhìn chung, tôi luôn giữ được sự bình tĩnh và thư thái trong cuộc sống.'],
        'b15' => ['axis' => 'N', 'pole' => '-', 'text' => 'Tôi dễ cảm thấy buồn bực hoặc suy sụp khi mọi việc không như ý.'],
        'b16' => ['axis' => 'N', 'pole' => '+', 'text' => 'Tôi hiếm khi mất bình tĩnh hay bối rối trước những tình huống bất ngờ.'],

        // Openness / Intellect (O) - Cởi mở nhận thức & Trí tưởng tượng sáng tạo
        'b17' => ['axis' => 'O', 'pole' => '+', 'text' => 'Tôi có trí tưởng tượng phong phú và thường nghĩ ra những ý tưởng mới mẻ.'],
        'b18' => ['axis' => 'O', 'pole' => '-', 'text' => 'Tôi không mấy hứng thú với những ý tưởng trừu tượng hay triết lý xa vời.'],
        'b19' => ['axis' => 'O', 'pole' => '+', 'text' => 'Tôi nhanh chóng nắm bắt và kết nối được các khái niệm mới khi học tập.'],
        'b20' => ['axis' => 'O', 'pole' => '-', 'text' => 'Tôi thấy khó hình dung những điều mà mình chưa từng tận mắt chứng kiến.']
    ];
}
```

### 1.2. Thang đo Năng Lực Thích Ứng Nghề Nghiệp CAAS (12 câu)
*Nguồn gốc: Savickas & Porfeli (2012) — Career Adapt-Abilities Scale (CAAS)*  
*Thang đo: 1 (Rất yếu / Chưa bao giờ) đến 5 (Rất mạnh / Thường xuyên)*

```php
function caas_questions(): array {
    return [
        // Concern (Quan tâm & Chuẩn bị tương lai)
        'c01' => ['axis' => 'concern', 'text' => 'Nghĩ về tương lai nghề nghiệp của mình sẽ ra sao.'],
        'c02' => ['axis' => 'concern', 'text' => 'Nhận thức được những lựa chọn học tập hôm nay sẽ định hình ngày mai.'],
        'c03' => ['axis' => 'concern', 'text' => 'Chuẩn bị trước cho các kỳ thi và các mốc chuyển cấp sắp tới.'],

        // Control (Tự chủ & Chịu trách nhiệm quyết định)
        'c04' => ['axis' => 'control', 'text' => 'Tự đưa ra quyết định dựa trên chính kiến và mục tiêu của bản thân.'],
        'c05' => ['axis' => 'control', 'text' => 'Chịu trách nhiệm về hành động và kết quả học tập của chính mình.'],
        'c06' => ['axis' => 'control', 'text' => 'Bảo vệ niềm tin và những giá trị mà mình cho là quan trọng.'],

        // Curiosity (Tò mò khám phá & Tìm kiếm cơ hội)
        'c07' => ['axis' => 'curiosity', 'text' => 'Khám phá và tìm hiểu về các cơ hội nghề nghiệp xung quanh mình.'],
        'c08' => ['axis' => 'curiosity', 'text' => 'Tìm kiếm thông tin từ nhiều nguồn về ngành nghề mình quan tâm trước khi chọn.'],
        'c09' => ['axis' => 'curiosity', 'text' => 'Tò mò quan sát cách những người làm nghề giải quyết công việc hàng ngày.'],

        // Confidence (Tự tin vượt khó & Giải quyết rào cản)
        'c10' => ['axis' => 'confidence', 'text' => 'Nỗ lực làm tốt nhất phần việc của mình kể cả khi gặp bài tập phức tạp.'],
        'c11' => ['axis' => 'confidence', 'text' => 'Kiên trì rèn luyện và khắc phục những kỹ năng mà mình còn hạn chế.'],
        'c12' => ['axis' => 'confidence', 'text' => 'Tin tưởng rằng mình có thể vượt qua các trở ngại trên con đường đã chọn.']
    ];
}
```

### 1.3. Bảng Kiểm Tra Sự Đánh Đổi Thực Tế (Realistic Job Sacrifice Checklist)
*Thay thế các câu hỏi thể lực tự khai hình thức cũ trong `app/constraints.php`*

```php
function realistic_job_sacrifices(): array {
    return [
        'high_academic_duration' => [
            'label' => 'Đào tạo Chuyên sâu Dài năm (6–8 năm)',
            'desc' => 'Chấp nhận học tập liên tục, thi cử gắt gao và áp lực chứng chỉ hành nghề dài hạn (như Bác sĩ, Dược sĩ, Luật sư).',
            'careers' => ['bac-si-da-khoa', 'duoc-si', 'luat-su']
        ],
        'strict_physical_discipline' => [
            'label' => 'Kỷ luật Điều lệnh & Thể lực Khắt khe',
            'desc' => 'Sẵn sàng tuân thủ giờ giấc nghiêm ngặt, rèn luyện thể lực thường xuyên và chấp nhận điều động xa nhà (như Sĩ quan, Cảnh sát, Cứu hỏa).',
            'careers' => ['si-quan-quan-doi', 'canh-sat-hinh-su']
        ],
        'debugging_detail_grind' => [
            'label' => 'Kiên trì Soi lỗi Dữ liệu & Chi tiết Kỹ thuật',
            'desc' => 'Chấp nhận ngồi hàng giờ đồng hồ trước máy tính để dò tìm một lỗi nhỏ trong mã nguồn, dòng tiền hoặc bảng vẽ kỹ thuật.',
            'careers' => ['lap-trinh-vien', 'ky-su-ai', 'chuyen-vien-tai-chinh', 'ke-toan-kiem-toan']
        ],
        'emotional_labor_crisis' => [
            'label' => 'Tiếp xúc Áp lực & Cảm xúc Tiêu cực',
            'desc' => 'Có sức chịu đựng tâm lý cao khi phải thường xuyên lắng nghe nỗi đau, khủng hoảng hoặc cơn giận của khách hàng/học sinh/bệnh nhân.',
            'careers' => ['chuyen-vien-tam-ly', 'dieu-duong-vien', 'giao-vien']
        ],
        'creative_rejection_deadline' => [
            'label' => 'Làm lại Nhiều lần & Áp lực Hạn chót Khách hàng',
            'desc' => 'Sẵn sàng đập đi làm lại ý tưởng thiết kế/nội dung nhiều lần theo phản hồi khắt khe của khách hàng dưới sức ép thời gian.',
            'careers' => ['chuyen-vien-marketing', 'thiet-ke-do-hoa', 'kien-truc-su', 'nha-bao']
        ]
    ];
}
```

---

## 2. Thuật Toán Chuẩn Hóa & Đối Chiếu Nhận Thức (Computational Psychometrics)

### 2.1. Chuẩn Hóa Nội Tại Cá Nhân (Ipsative Within-Person Z-Score)
*Mục đích: Loại bỏ triệt để thiên kiến đồng thuận (Acquiescence Bias — thói quen đánh toàn điểm 4–5).*

Cho tập hợp các câu trả lời $\{X_1, X_2, \dots, X_N\}$ của học sinh ($N = 30$ hoặc $60$):

1. **Tính giá trị trung bình cá nhân ($\mu_{user}$)**:
   $$\mu_{user} = \frac{1}{N} \sum_{i=1}^{N} X_i$$

2. **Tính độ lệch chuẩn cá nhân ($\sigma_{user}$)**:
   $$\sigma_{user} = \sqrt{\frac{1}{N} \sum_{i=1}^{N} (X_i - \mu_{user})^2}$$

3. **Tính điểm Z cho từng nhóm RIASEC $k \in \{R, I, A, S, E, C\}$**:
   $$Z_k = \frac{\bar{X}_k - \mu_{user}}{\max(0.25, \sigma_{user})}$$

4. **Quy đổi sang thang điểm chuẩn hóa nội tại (Ipsative Score)**:
   $$\text{IpsativeScore}_k = \min(95, \max(5, \text{round}(50 + 15 \times Z_k)))$$

5. **Phát hiện phản hồi phẳng (Flatline Check)**:
   Nếu $\sigma_{user} < 0.20$ (học sinh chọn cùng một mức điểm cho gần như mọi câu):
   $$\text{validity\_metrics}['\text{flatline}'] = \text{true}$$

### 2.2. Phân Loại 4 Góc Phần Tư SCCT (Social Cognitive Career Theory)
Với mỗi nhóm RIASEC $k$, dựa trên điểm Hứng thú ($I_k$) và Tự hiệu quả ($E_k$):

$$\text{SCCT\_Quadrant}(I_k, E_k) = \begin{cases}
\text{Core Mastery (Vùng Phát huy)} & \text{nếu } I_k \ge 65\% \land E_k \ge 60\% \\
\text{Growth Aspiration (Khát vọng Bồi dưỡng)} & \text{nếu } I_k \ge 65\% \land E_k < 60\% \\
\text{Burnout Risk (Kỹ năng Thụ động)} & \text{nếu } I_k < 65\% \land E_k \ge 60\% \\
\text{Low Priority (Vùng Không Ưu tiên)} & \text{nếu } I_k < 65\% \land E_k < 60\%
\end{cases}$$

### 2.3. Bóc Tách 4 Vùng Nhận Thức SOKA (Johari/SOKA Discrepancy Matrix)
Đối chiếu giữa điểm **Tự đánh giá ($S$)** và điểm **Thực chứng ($O$: từ Gương 360° hoặc Mô phỏng)**:

$$\text{SOKA\_Region}(S, O) = \begin{cases}
\text{Open Strength (Thế mạnh Rõ nét)} & \text{nếu } S \ge 70\% \land O \ge 70\% \\
\text{Blind Spot (Điểm mù Quá tự tin)} & \text{nếu } S \ge 75\% \land O < 55\% \\
\text{Hidden Gem (Tiềm năng Ẩn giấu)} & \text{nếu } S < 55\% \land O \ge 75\% \\
\text{Unknown Area (Vùng Cần Khám phá)} & \text{còn lại}
\end{cases}$$

---

## 3. Kiến Trúc Trade-Off SJT 2D & Ngăn Chứng Cứ Ẩn (Evidence Drawer)

### 3.1. Cấu Trúc Dữ Liệu Mission Kèm Evidence Drawer
Trong `app/simulations.php`, mỗi nhiệm vụ mở rộng thêm cấu trúc `evidence_drawer`:

```php
[
    'id' => 'm01',
    'title' => 'Chẩn đoán Sự cố Máy chủ Trước Giờ Khởi động',
    'context' => 'Chỉ còn 15 phút trước khi cổng tuyển sinh mở, hệ thống bất ngờ báo nghẽn mạng...',
    'evidence_drawer' => [
        'label' => '🔍 Mở xem Trích xuất Log Hệ thống & Thông số Tải CPU',
        'content' => "LOG_ALERT: [14:45:02] CPU load: 98% trên Node-3.\nIP_BURST: 203.162.14.8 gửi 12,000 req/min.\nDB_LOCK: 0 deadlock detected. Traffic tập trung đột biến vào endpoint /api/v1/search.",
        'min_read_seconds' => 3
    ],
    'choices' => [
        [
            'text' => 'Ngắt kết nối ngay Node-3 để bảo vệ cơ sở dữ liệu chính, phát loa thông báo dời giờ mở cổng 30 phút.',
            'dimension' => 'decision',
            'tradeoff' => 'Ưu tiên kiểm soát an toàn hệ thống, chấp nhận ảnh hưởng trải nghiệm người dùng.',
            'skills' => ['decision' => 85, 'precision' => 70, 'analysis' => 50, 'empathy' => 40, 'creativity' => 40]
        ],
        [
            'text' => 'Kích hoạt bộ nhớ đệm tạm thời cho endpoint /api/v1/search và giới hạn tần suất theo dải IP nghi vấn.',
            'dimension' => 'analysis',
            'tradeoff' => 'Đòi hỏi sự am hiểu kỹ thuật và bình tĩnh phân tích log dữ liệu, giải quyết trúng gốc rễ.',
            'skills' => ['analysis' => 95, 'precision' => 90, 'decision' => 80, 'creativity' => 75, 'empathy' => 60]
        ],
        [
            'text' => 'Điều hướng người dùng sang trang chờ thân thiện kèm đồng hồ đếm ngược và thông điệp trấn an thí sinh.',
            'dimension' => 'empathy',
            'tradeoff' => 'Xoa dịu tâm lý căng thẳng của thí sinh và phụ huynh, nhưng sự cố quá tải nền tảng vẫn cần xử lý tiếp.',
            'skills' => ['empathy' => 90, 'creativity' => 80, 'decision' => 60, 'analysis' => 50, 'precision' => 50]
        ]
    ]
]
```

### 3.2. Thu Thập Process Telemetry Phía Client
Giao diện gửi kèm dữ liệu quá trình qua POST `simulation-complete`:

```json
{
  "dwell_ms_per_mission": {
    "m01": 8420,
    "m02": 14200,
    "m03": 6150,
    "m04": 9300
  },
  "evidence_opened_count": 3,
  "evidence_details": {
    "m01": {"opened": true, "view_duration_ms": 4800},
    "m02": {"opened": true, "view_duration_ms": 3200},
    "m03": {"opened": false, "view_duration_ms": 0},
    "m04": {"opened": true, "view_duration_ms": 5100}
  },
  "option_switches": 2
}
```

---

## 4. Hệ Thống Token Gương Phản Chiếu 360° (SOKA Mirror)

### 4.1. Quy trình Sinh Token & Phản Hồi Ẩn Danh
1. **Học sinh tạo yêu cầu**: Học sinh click "Mời bạn bè / Giáo viên phản chiếu góc nhìn" tại Hub 2. Hệ thống sinh `invite_token = bin2hex(random_bytes(32))` và lưu vào `soka_mirror_requests` (hạn dùng 14 ngày).
2. **Người nhận phản hồi**: Mở đường dẫn `?page=soka-feedback&token={invite_token}`.
   - Không yêu cầu đăng nhập.
   - Hiển thị tên học sinh (ví dụ: *"Bạn đang chia sẻ góc nhìn phản chiếu cho bạn: Nguyễn Văn An"*).
   - 6 câu trắc nghiệm hành vi (thang 1–5: *Hiếm khi thể hiện* đến *Luôn luôn thể hiện*).
   - 2 câu hỏi định tính ngắn gọn:
     - *"Một điểm mạnh hoặc hành vi đáng trân trọng mà bạn thấy ở bạn ấy:"* (tối đa 300 ký tự).
     - *"Một tình huống bạn ấy có thể làm tốt hơn nếu chú ý quan sát hoặc điều chỉnh:"* (tối đa 300 ký tự).
3. **Bảo mật & Giới hạn**:
   - Mỗi IP chỉ được gửi tối đa 5 phản hồi/ngày.
   - Token sau khi nhận đủ 3 phản hồi hoặc quá hạn sẽ chuyển trạng thái `status = 'closed'`.

---

## 5. Mẫu Prompt Socratic Cho AI Career Coach (v3.0)

Cập nhật `app/four-hubs-ai.php` với `system` prompt đối thoại Socratic:

```text
Bạn là Socratic Career Coach & Nhà Đo lường Tâm lý học Định hướng của JOBSIMU.
Nhiệm vụ của bạn: Giúp học sinh nhận diện sự thật khách quan về bản thân thông qua đối chiếu đa chiều giữa TỰ KHAI (Self-Report), GÓC NHÌN 360° (SOKA Mirror) và HÀNH VI MÔ PHỎNG THỰC TẾ (Stealth Telemetry).

NGUYÊN TẮC BẤT BIẾN:
1. KHÔNG khen ngợi sáo rỗng hoặc vuốt ve cảm xúc.
2. KHÔNG phán xét hay dán nhãn bệnh lý/năng lực tuyệt đối.
3. BẮT BUỘC có cấu trúc 3 phần rõ rệt:
   - Phần 1 (Khẳng định Thực chứng): Nêu 1 điểm mạnh đã được ĐỒNG THUẬN giữa lời tự khai và hành động thực tế.
   - Phần 2 (Phản biện Điểm mù / Lệch pha): Chỉ ra đúng 1 điểm lệch pha sâu sắc nhất giữa điều học sinh tự nghĩ và cách các em thực sự hành động trong bài test/mô phỏng (kèm dẫn chứng số liệu dwell_ms, việc bỏ qua bằng chứng hay đánh giá từ người khác).
   - Phần 3 (Thực nghiệm Socratic 7 ngày): Đặt 1 câu hỏi khơi mở tư duy sâu sắc và giao đúng 1 thực nghiệm nhỏ (Micro-Experiment) kéo dài 15–30 phút trong đời thực để học sinh tự kiểm chứng giả thuyết về bản thân.
```

---

## 6. Lộ Trình Triển Khai Mã Nguồn & Danh Mục File Cần Xử Lý

```text
[BƯỚC 1: Cơ sở Dữ liệu & Migrations]
  ├── database/migrations/2026_12_scientific_psychometrics_v3.sql
  ├── app/four-hubs-data.php (run_four_hub_migrations, four_hub_sqlite_schema)
  └── app/functions.php ($codeSchemaVersion = '3.0.0')

[BƯỚC 2: Psychometrics Engine & SCCT Dual Matrix]
  ├── app/psychometrics.php (big5_questions, caas_questions, score_psychometric_v3, psychometric_validate_quality)
  ├── app/functions.php (score_assessment ipsative Z-score, SCCT Quadrants)
  └── app/constraints.php (realistic_job_sacrifices checklist)

[BƯỚC 3: SOKA 360 Mirror Controller & UI]
  ├── app/assessment-hub.php (Trang tạo invite token & giao diện feedback SOKA)
  ├── app/four-hubs-data.php (four_hub_soka_summary, calculate_self_insight_discrepancy)
  └── index.php (route ?page=soka-invite, ?page=soka-feedback)

[BƯỚC 4: SJT 2D Evidence Drawer & 3D Server Sync Replay]
  ├── app/simulations.php (Bổ sung evidence_drawer cho 15 nghề)
  ├── app/simulation-controller.php (Thu thập process_telemetry, dwell_ms)
  └── api/game/finish.php (Xác thực replay action chain của LessonSession)

[BƯỚC 5: Socratic AI & Báo Cáo Tổng Hợp Điểm Mù]
  ├── app/four-hubs-ai.php (Cập nhật Socratic Prompt & Discrepancy Findings Payload)
  └── app/assessment-hub.php (?page=assessment-report tích hợp biểu đồ SOKA & Big Five)

[BƯỚC 6: Bộ Kiểm Thử Tự Động & Nghiệm Thu]
  ├── tests/psychometrics-test.php (Test Big Five, CAAS, Ipsative Z-score, Reverse items)
  ├── tests/four-hubs-data-test.php (Test migration v3, SOKA requests/responses, SQLite runner)
  ├── tests/four-hubs-http.py (Test luồng invite 360, nộp feedback ẩn danh, CSRF)
  └── health.php (Kiểm tra schema 3.0.0 và các bảng mới)
```
