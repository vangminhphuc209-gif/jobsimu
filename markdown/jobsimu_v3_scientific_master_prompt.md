# Master Prompt Nâng Cấp Khoa Học Hệ Thống Hướng Nghiệp 360 (JOBSIMU v3.0 — Psychometric & Evidence-Centered Architecture)

Tài liệu đặc tả kỹ thuật và prompt chỉ dẫn toàn diện này dùng để giao trực tiếp cho AI Coding Agent (Claude Code, Cursor, Windsurf, GitHub Copilot) hoặc đội ngũ kỹ sư nhằm nâng cấp mã nguồn Hướng Nghiệp 360 ([vangminhphuc209-gif/jobsimu](https://github.com/vangminhphuc209-gif/jobsimu)) từ phiên bản 2.2.0 (4-Hub Architecture) lên phiên bản 3.0.0 (Scientifically Validated Self-Insight & Stealth Assessment Architecture).

---

## 1. Vai trò, Ngữ cảnh & Lý do Khoa học của Bản Nâng cấp (Role & Scientific Rationale)

Bạn là **Principal Full-Stack Architect, Computational Psychometrician & Lead Game Engineer** phụ trách nâng cấp dự án Hướng Nghiệp 360 ([vangminhphuc209-gif/jobsimu](https://github.com/vangminhphuc209-gif/jobsimu)).

### 1.1. Thực trạng kỹ thuật hiện có (Baseline v2.2.0)
- **Backend & Database:** PHP 7.4+ (tương thích PHP 8.x), MySQL 5.7+/MariaDB (hỗ trợ SQLite cho kiểm thử tự động trong `tests/`), PDO Prepared Statements, chạy trực tiếp trên shared hosting cPanel.
- **Frontend & 3D Client:** Giao diện Tailwind CSS (`assets/css/app.css`), mô phỏng tình huống 2D (`app/simulations.php`, `app/simulation-controller.php`) và mô phỏng không gian 3D bằng TypeScript + Vite + Babylon.js 7.53 (`game/`).
- **AI Engine:** OpenAI Responses API (`https://api.openai.com/v1/responses`, model mặc định `gpt-5-mini`) với `json_schema` Structured Outputs trong `app/functions.php` và `app/four-hubs-ai.php`.

### 1.2. Các lỗ hổng khoa học cần khắc phục triệt để
Hệ thống v2.2.0 mới chỉ thu thập nhận thức bề nổi tự khai (surface self-report) mà chưa giúp học sinh khám phá năng lực thực chất và điểm mù bản thân:
1. **Nghịch lý "Tự biết mình" & Điểm mù nhận thức:** Theo mô hình Self-Other Knowledge Asymmetry (SOKA) của Simine Vazire ([Vazire, 2010](https://simine.com/docs/Vazire_&_Carlson_SPPC_2010.pdf); [Frontiers in Psychology, 2011](https://www.frontiersin.org/journals/psychology/articles/10.3389/fpsyg.2011.00312/full)), cá nhân chỉ tự nhận biết chính xác các nét nội tâm ít mang tính phán xét (Open/Hidden Areas), nhưng lại rơi vào Điểm mù (Blind Spots) đối với các đặc điểm đánh giá năng lực (Evaluative traits như tư duy phân tích, sáng tạo, khả năng chịu áp lực, trí tuệ cảm xúc thực chiến — [Neubauer et al., 2018](https://pmc.ncbi.nlm.nih.gov/articles/PMC6307038/)).
2. **Đứt gãy giữa Sở thích (Interests) và Tự hiệu quả / Kỳ vọng thực tế:** Theo Lý thuyết Nhận thức Xã hội về Nghề nghiệp (Social Cognitive Career Theory — SCCT) của Lent, Brown & Hackett ([Lent, Brown, & Hackett, 1994](https://www.researchgate.net/publication/230557657_Toward_a_Unifying_Social_Cognitive_Theory_of_Career_and_Academic_Interest_Choice_and_Performance); [Emerald SCCT Framework](https://www.emerald.com/books/edited-volume/19176/chapter/103473726/Social-Cognitive-Career-TheoryA-Theory-of-Self)), sở thích nghề nghiệp chỉ chuyển hóa thành lựa chọn bền vững khi đi kèm Niềm tin năng lực bản thân (Self-Efficacy) và Kỳ vọng kết quả / Chi phí cơ hội (Outcome Expectations).
3. **Thiên kiến đồng thuận (Acquiescence Bias) & Thang đo chưa chuẩn hóa:** Bài Holland 30 câu dùng thang Likert 1–5 thuần túy không có chuẩn hóa nội tại (ipsatization) khiến học sinh chọn toàn mức 4–5 ([EconStor Forced-Choice Study, 2024](https://www.econstor.eu/bitstream/10419/315318/1/10869_2024_Article_9931.pdf)), cộng thêm công thức ép biên `max(35, min(96, ...))` làm mọi nghề đều khớp cao giả tạo. Bộ MBTI 12 câu nhị phân có độ tin cậy kiểm tra lại (test-retest reliability) thấp ([Pittenger, 2005](https://www.researchgate.net/publication/27827048_Myers-Briggs_Type_Indicator_MBTI_Some_psychometric_limitations); [Teamazing APA Review, 2026](https://www.teamazing.com/blog/big-five-vs-mbti/)), còn bộ EQ 10 câu tự nhận xét trực diện rất dễ bị hiệu ứng tự tô hồng (Faking Good).
4. **Mô phỏng 2D lộ đáp án & Mô phỏng 3D bị cô lập:** Các phương án 0 điểm trong `app/simulations.php` quá ngớ ngẩn, chỉ đo nhận thức đạo đức sách vở thay vì đo phán đoán tình huống đánh đổi theo chuẩn Situational Judgment Test (SJT) ([Christian et al., 2010](https://www.researchgate.net/publication/229890585_Situational_judgment_tests_Constructs_assessed_and_a_meta-analysis_of_their_criterion-related_validities); [Lievens et al., 2008](https://eprints.whiterose.ac.uk/id/eprint/159850/8/medu.14201.pdf)).

---

## 2. Kiến trúc Khoa học Đích (Target Scientific Architecture v3.0.0)

| Trụ cột nâng cấp | Công cụ / Mô hình hiện tại (v2.2.0) | Mô hình Khoa học Thay thế (v3.0.0) | Nguồn dẫn chứng & Tiêu chuẩn kiểm định |
|---|---|---|---|
| **1. Sở thích & Tự hiệu quả Nghề nghiệp** | 30 câu RIASEC tự soạn, Likert 1–5 tuyến tính, ghép nghề Cosine Similarity khóa biên 35%–96%. | **Ma trận Kép SCCT-RIASEC (Interest × Self-Efficacy)** chuẩn hóa theo [O*NET Interest Profiler Short Form](https://www.onetcenter.org/dl_files/IPSF_Psychometric.pdf) + **Điểm chuẩn hóa nội tại (Ipsative Within-Person Z-Score)** + **Cổng Đánh đổi Thực tế (Realistic Job Sacrifice Gate)**. | [O*NET Resource Center](https://www.onetcenter.org/IP.html); [O*NET IP Manual](https://www.onetcenter.org/dl_files/IP_Manual.pdf); [SCCT — Lent, Brown, & Hackett (1994)](https://www.researchgate.net/publication/230557657_Toward_a_Unifying_Social_Cognitive_Theory_of_Career_and_Academic_Interest_Choice_and_Performance); [Forced-Choice & Bias Control (2024)](https://www.econstor.eu/bitstream/10419/315318/1/10869_2024_Article_9931.pdf) |
| **2. Nhân cách & Độ ổn định Cảm xúc** | 12 câu MBTI tự soạn (3 câu/trục), phân loại nhị phân E/I, S/N, T/F, J/P. | **Mini-IPIP Big Five (20 câu)** đo 5 chiều liên tục (*Openness, Conscientiousness, Extraversion, Agreeableness, Emotional Stability*) có câu hỏi đảo chiều (reverse-keyed items) và kiểm tra độ nhất quán nội tại. | [International Personality Item Pool (IPIP)](https://ipip.ori.org/); [Mini-IPIP Scale](https://ipip.ori.org/MiniIPIPTable.htm); [Mini-IPIP Scoring Key](https://ipip.ori.org/MiniIPIPKey.htm); [Big Five vs. MBTI APA Review](https://www.teamazing.com/blog/big-five-vs-mbti/) |
| **3. Năng lực Thích ứng & Trí tuệ Cảm xúc** | 10 câu EQ tự nhận xét thuận chiều (awareness, regulation, motivation, empathy). | **Career Adapt-Abilities Scale (CAAS — 12 câu)** đo 4 chiều (*Concern, Control, Curiosity, Confidence*) kết hợp TEIQue-SF có câu đảo chiều chống tự tô hồng. | [CAAS — Savickas & Porfeli (2012)](https://www.marksavickas.com/files/Assessments/CAAS/6.%20CAAS_International%20Psychometrics.pdf); [Frontiers CAAS Validation (2024)](https://www.frontiersin.org/journals/psychology/articles/10.3389/fpsyg.2024.1338401/pdf); [TEIQue Consortium](https://www.eiconsortium.org/measures/teique.html); [TEIQue IRT Validation (PMC)](https://pmc.ncbi.nlm.nih.gov/articles/PMC6344417/) |
| **4. Gương Phản chiếu Điểm mù 360°** | Không có (100% dựa vào học sinh tự khai). | **SOKA 360° Peer/Mentor Mirror**: Tạo link ẩn danh (6 câu hỏi hành vi quan sát được) gửi bạn học/giáo viên/phụ huynh để đối chiếu Tự đánh giá (Self) vs. Người khác thấy (Other). | [SOKA Model — Vazire (2010)](https://simine.com/docs/Vazire_&_Carlson_SPPC_2010.pdf); [Feedback as a Path to Self-Knowledge](https://www.frontiersin.org/journals/psychology/articles/10.3389/fpsyg.2011.00312/full); [SOKA in Intelligence & Creativity](https://pmc.ncbi.nlm.nih.gov/articles/PMC6307038/) |
| **5. Mô phỏng Tình huống 2D & 3D** | 2D chọn đáp án 3–1–0 lộ liễu; 3D lưu local, khóa đồng bộ server 503. | **Trade-off SJT 2D** (các lựa chọn đồng cấp hấp dẫn xã hội, gắn kèm **Ngăn Tài liệu Chứng cứ Ẩn / Evidence Drawer** để đo hành vi tra cứu) + **ECD Stealth Assessment 3D** đồng bộ Event Log có xác thực phía server. | [SJT Meta-Analysis — Christian et al. (2010)](https://www.researchgate.net/publication/229890585_Situational_judgment_tests_Constructs_assessed_and_a_meta-analysis_of_their_criterion-related_validities); [High-Stakes SJT — Lievens & Sackett](https://www.semanticscholar.org/paper/Situational-judgment-tests-in-high-stakes-settings%3A-Lievens-Sackett/22dd8c86f241acca3bd819febc46846ba89c6cd9); [ECD & Stealth Assessment — Kim, Almond, & Shute (2016)](https://myweb.fsu.edu/vshute/pdf/IJT.pdf); [Shute & Moore (2017)](https://myweb.fsu.edu/vshute/pdf/ShuteMoore.pdf) |
| **6. Động cơ Đối chiếu Chéo & AI** | Tổng hợp tuyến tính, AI khen ngợi chung chung theo kết quả tự khai. | **Self-Insight Discrepancy Engine** (tính độ lệch giữa Tự khai — Góc nhìn 360° — Hành vi Mô phỏng) + **Socratic AI Career Coach** chỉ ra điểm mù và giao thực nghiệm kiểm chứng giả thuyết. | [Blind Spots in Assessing Job Performance (2025)](https://www.tandfonline.com/doi/full/10.1080/08959285.2025.2515359); [ECD Simulation Assessment — Mislevy (2013)](https://myweb.fsu.edu/vshute/pdf/PL.pdf) |

---

## 3. Các Nguyên tắc Kiến trúc & Tâm lý học Bất biến (Non-Negotiable Invariants)

1. **Bảo toàn Tương thích Ngược & Dữ liệu Cũ:** Không xóa bất kỳ bảng hoặc cột nào của schema 2.2.0. Các bản ghi cũ trong `assessments`, `user_psychometrics` (mbti, eq) và `simulation_attempts` phải tiếp tục hiển thị bình thường. Nâng `$codeSchemaVersion` trong `app/functions.php` lên `'3.0.0'` và thực thi qua migration cộng thêm (additive migration).
2. **Kiểm soát Chất lượng Phản hồi (Response Quality & Validity Guards):**
   - Mọi bài đánh giá tâm lý học phải đo thời gian làm bài (`duration_seconds`) và chỉ số phương sai phản hồi (`response_variance` / `longstring_index`). Nếu học sinh bấm cùng một mức điểm cho $\ge 85\%$ số câu hoặc hoàn thành 20–30 câu trong dưới 25 giây, hệ thống không từ chối lưu nhưng gắn cờ `validity_flag = 'low_variance_or_rushed'` và yêu cầu làm lại hoặc cảnh báo rõ trên báo cáo.
   - Bắt buộc có câu hỏi đảo chiều (`reverse_keyed = true`) trong các thang đo nhân cách và cảm xúc/thích ứng để khử thiên kiến đồng thuận (Acquiescence Bias).
3. **Không Tin Dữ liệu Điểm Thô từ Client:** Trong cả mô phỏng 2D (`app/simulation-controller.php`) và 3D (`api/game/`), client chỉ gửi chuỗi sự kiện hành động (`decisions`, `telemetry_events`, `evidence_opened`, `dwell_ms`). Server chịu trách nhiệm xác thực tính hợp lệ của chuỗi sự kiện và tính toán điểm năng lực.
4. **Chuẩn mực Đạo đức Đo lường Tâm lý:** Không sử dụng ngôn từ chẩn đoán lâm sàng hay phán xét nhân cách. Mọi độ lệch giữa Tự khai và Hành vi mô phỏng phải được trình bày dưới dạng *"Góc nhìn khám phá & Điểm mù tiềm năng"* theo tinh thần đối thoại Socratic.
5. **Tương thích cPanel & SQLite Test Runner:** Mọi truy vấn và migration trong `app/four-hubs-data.php` phải hỗ trợ song song MySQL/MariaDB (production) và SQLite (bộ test tự động `tests/psychometrics-test.php`, `tests/four-hubs-data-test.php`, `tests/four-hubs-http.py`).

---

## 4. Đặc tả Mở rộng Cơ sở Dữ liệu (`database/migrations/2026_12_scientific_psychometrics_v3.sql`)

```sql
-- 1. Mở rộng bảng assessments để lưu Ma trận SCCT (Interest x Self-Efficacy), Z-scores và Cờ độ tin cậy
ALTER TABLE `assessments`
  ADD COLUMN `efficacy_scores` JSON NULL AFTER `scores`,
  ADD COLUMN `ipsative_z_scores` JSON NULL AFTER `efficacy_scores`,
  ADD COLUMN `validity_metrics` JSON NULL AFTER `constraint_answers`,
  ADD COLUMN `discrepancy_report` JSON NULL AFTER `validity_metrics`;

-- 2. Mở rộng bảng user_psychometrics để hỗ trợ chuẩn Big Five (Mini-IPIP), CAAS và TEIQue-SF
ALTER TABLE `user_psychometrics`
  MODIFY COLUMN `test_type` ENUM('mbti', 'eq', 'big5', 'caas', 'teique') NOT NULL,
  ADD COLUMN `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `summary_label`,
  ADD COLUMN `validity_flag` ENUM('valid', 'rushed', 'flatline', 'inconsistent') NOT NULL DEFAULT 'valid' AFTER `duration_seconds`;

-- 3. Mở rộng bảng simulation_attempts để lưu Telemetry Hành vi (ECD Process Data)
ALTER TABLE `simulation_attempts`
  ADD COLUMN `process_telemetry` JSON NULL AFTER `skill_scores`,
  ADD COLUMN `tradeoff_profile` JSON NULL AFTER `process_telemetry`;

-- 4. Bảng Token & Phản hồi Gương Phản Chiếu 360 Độ (SOKA Peer/Mentor Mirror)
CREATE TABLE IF NOT EXISTS `soka_mirror_requests` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `invite_token` VARCHAR(64) NOT NULL UNIQUE,
  `rater_relation` ENUM('peer', 'teacher', 'parent', 'mentor') NOT NULL DEFAULT 'peer',
  `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NULL,
  INDEX `idx_soka_user` (`user_id`),
  CONSTRAINT `fk_soka_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `soka_mirror_responses` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `request_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `rater_role` ENUM('peer', 'teacher', 'parent', 'mentor') NOT NULL DEFAULT 'peer',
  `ratings_json` JSON NOT NULL,
  `strength_note` VARCHAR(300) NULL,
  `blindspot_note` VARCHAR(300) NULL,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_soka_resp_user` (`user_id`, `submitted_at`),
  CONSTRAINT `fk_soka_resp_req` FOREIGN KEY (`request_id`) REFERENCES `soka_mirror_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_soka_resp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Kế hoạch Triển khai Chi tiết Từng Giai đoạn

### Giai đoạn 1: Nâng cấp Engine Đo lường Tâm lý học Chuẩn hóa (`app/psychometrics.php` & `app/functions.php`)
- **Ma trận Kép SCCT-RIASEC:** Thu thập đồng thời 2 chiều: Hứng thú (`interest` 1–5) và Tự tin Năng lực (`efficacy` 1–5).
- **Thuật toán Chuẩn hóa Nội tại (Ipsative Within-Person Z-Score):**
  $$Z_k = \frac{\bar{X}_k - \mu_{user}}{\max(0.25, \sigma_{user})}$$
  Quy đổi sang điểm chuẩn hóa: $50 + 15 \times Z_k$.
- **4 Góc phần tư SCCT:** Core Mastery, Growth Aspiration, Burnout Risk, Low Priority.
- **Thang đo Big Five Mini-IPIP (20 câu):** 5 nhân tố $\times$ 4 câu (2 câu thuận, 2 câu nghịch). Đảo chiều bằng công thức $6 - \text{value}$.
- **Thang đo CAAS (12 câu):** 4 chiều thích ứng nghề nghiệp (*concern, control, curiosity, confidence*).
- **Cổng Đánh đổi Thực tế (Realistic Job Sacrifice Gate):** 5 áp lực công việc đặc thù (`high_academic_duration`, `strict_physical_discipline`, `debugging_detail_grind`, `emotional_labor_crisis`, `creative_rejection_deadline`).

### Giai đoạn 2: Xây dựng "Gương Phản Chiếu 360°" theo Mô hình SOKA (`app/assessment-hub.php`)
- Link mời phản hồi 360° ẩn danh (`?page=soka-invite` & `?page=soka-feedback&token=...`).
- Đánh giá 6 hành vi quan sát được ngoài đời thực (thang 1–5): Phân tích, Sáng tạo, Thấu cảm, Tự chủ, Ra quyết định, Bình tĩnh dưới áp lực.
- Phân tích 4 vùng nhận thức SOKA (Johari Quadrant): Vùng Mở (Open Strength), Điểm Mù (Blind Spot), Viên Ngọc Ẩn (Hidden Gem), Vùng Chưa Khám Phá (Unknown Area).

### Giai đoạn 3: Nâng cấp Mô phỏng 2D sang Trade-off SJT & Evidence-Centered Design (`app/simulations.php` & `app/simulation-controller.php`)
- Ngăn Tài liệu Chứng cứ Ẩn (`evidence_drawer`).
- Đo lường Process Telemetry (`dwell_ms_per_mission`, `evidence_opened_count`, `option_switches`).
- Mở cổng đồng bộ server an toàn cho game 3D (`api/game/finish.php` replay xác thực trên PHP).

### Giai đoạn 4: Động cơ Đối chiếu Chéo & AI Phản biện Socratic (`app/four-hubs-ai.php`)
- `calculate_self_insight_discrepancy(int $userId)` đối chiếu 3 nguồn: Tự khai (Self) vs Góc nhìn 360° (Other) vs Hành vi Mô phỏng (Behavior).
- Socratic Prompt: Chỉ ra điểm tương đồng đã kiểm chứng, điểm mù/lệch pha kèm hành vi cụ thể, đặt câu hỏi phản tư và giao thực nghiệm 7 ngày.

### Giai đoạn 5: Danh mục Kiểm thử & Nghiệm thu Bắt buộc
- Chạy `php -l` và `npm test` + `npm run build`.
- Kiểm thử unit trong `tests/psychometrics-test.php`.
- Kiểm thử data & HTTP trong `tests/four-hubs-data-test.php` và `tests/four-hubs-http.py`.
- Cập nhật `health.php`.
