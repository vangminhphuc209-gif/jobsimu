# Master Prompt Nâng Cấp Khoa Học Hệ Thống Hướng Nghiệp 360 (JOBSIMU v3.0 — Psychometric & Evidence-Centered Architecture)

Tài liệu đặc tả kỹ thuật và prompt chỉ dẫn toàn diện này dùng để giao trực tiếp cho AI Coding Agent (Claude Code, Cursor, Windsurf, GitHub Copilot) hoặc đội ngũ kỹ sư nhằm nâng cấp mã nguồn **Hướng Nghiệp 360 (`jobsimu`)** từ phiên bản `2.2.0` (4-Hub Architecture) lên phiên bản **`3.0.0` (Scientifically Validated Self-Insight & Stealth Assessment Architecture)**.

## 1. Vai trò, Ngữ cảnh & Lý do Khoa học của Bản Nâng cấp (Role & Scientific Rationale)

Bạn là **Principal Full-Stack Architect, Computational Psychometrician & Lead Game Engineer** phụ trách nâng cấp dự án **Hướng Nghiệp 360 (`jobsimu`)**.

### 1.1. Thực trạng kỹ thuật hiện có (Baseline v2.2.0)

* **Backend & Database:** PHP 7.4+ (tương thích PHP 8.x), MySQL 5.7+/MariaDB (hỗ trợ SQLite cho kiểm thử tự động trong `tests/`), PDO Prepared Statements, chạy trực tiếp trên shared hosting cPanel.

* **Frontend & 3D Client:** Giao diện Tailwind CSS (`assets/css/app.css`), mô phỏng tình huống 2D (`app/simulations.php`, `app/simulation-controller.php`) và mô phỏng không gian 3D bằng **TypeScript + Vite + Babylon.js 7.53** (`game/`).

* **AI Engine:** OpenAI Responses API (`https://api.openai.com/v1/responses`, model mặc định `gpt-5-mini`) với `json_schema` Structured Outputs trong `app/functions.php` và `app/four-hubs-ai.php`.

### 1.2. Các lỗ hổng khoa học cần khắc phục triệt để

Hệ thống v2.2.0 mới chỉ thu thập **nhận thức bề nổi tự khai (surface self-report)** mà chưa giúp học sinh khám phá **năng lực thực chất và điểm mù bản thân**:

1. **Nghịch lý "Tự biết mình" & Điểm mù nhận thức:** Theo mô hình **Self-Other Knowledge Asymmetry (SOKA)** của Simine Vazire ([Vazire, 2010](https://simine.com/docs/Vazire_&_Carlson_SPPC_2010.pdf); [Frontiers in Psychology, 2011](https://www.frontiersin.org/journals/psychology/articles/10.3389/fpsyg.2011.00312/full)), cá nhân chỉ tự nhận biết chính xác các nét nội tâm ít mang tính phán xét (*Open/Hidden Areas*), nhưng lại rơi vào **Điểm mù (*Blind Spots*)** đối với các đặc điểm đánh giá năng lực (*Evaluative traits* như tư duy phân tích, sáng tạo, khả năng chịu áp lực, trí tuệ cảm xúc thực chiến — [Neubauer et al., 2018](https://pmc.ncbi.nlm.nih.gov/articles/PMC6307038/)).

2. **Đứt gãy giữa Sở thích (Interests) và Tự hiệu quả / Kỳ vọng thực tế:** Theo **Lý thuyết Nhận thức Xã hội về Nghề nghiệp (Social Cognitive Career Theory — SCCT)** của Lent, Brown & Hackett ([Lent, Brown, & Hackett, 1994](https://www.researchgate.net/publication/230557657_Toward_a_Unifying_Social_Cognitive_Theory_of_Career_and_Academic_Interest_Choice_and_Performance); [Emerald SCCT Framework](https://www.emerald.com/books/edited-volume/19176/chapter/103473726/Social-Cognitive-Career-TheoryA-Theory-of-Self)), sở thích nghề nghiệp chỉ chuyển hóa thành lựa chọn bền vững khi đi kèm **Niềm tin năng lực bản thân (*Self-Efficacy*)** và **Kỳ vọng kết quả / Chi phí cơ hội (*Outcome Expectations*)**.

3. **Thiên kiến đồng thuận (*Acquiescence Bias*) & Thang đo chưa chuẩn hóa:** Bài Holland 30 câu dùng thang Likert 1–5 thuần túy không có chuẩn hóa nội tại (*ipsatization*) khiến học sinh chọn toàn mức 4–5 ([EconStor Forced-Choice Study, 2024](https://www.econstor.eu/bitstream/10419/315318/1/10869_2024_Article_9931.pdf)), cộng thêm công thức ép biên `max(35, min(96, ...))` làm mọi nghề đều khớp cao giả tạo. Bộ MBTI 12 câu nhị phân có độ tin cậy kiểm tra lại (*test-retest reliability*) thấp ([Pittenger, 2005](https://www.researchgate.net/publication/27827048_Myers-Briggs_Type_Indicator_MBTI_Some_psychometric_limitations); [Teamazing APA Review, 2026](https://www.teamazing.com/blog/big-five-vs-mbti/)), còn bộ EQ 10 câu tự nhận xét trực diện rất dễ bị hiệu ứng tự tô hồng (*Faking Good*).

4. **Mô phỏng 2D lộ đáp án & Mô phỏng 3D bị cô lập:** Các phương án 0 điểm trong `app/simulations.php` quá ngớ ngẩn, chỉ đo nhận thức đạo đức sách vở thay vì đo phán đoán tình huống đánh đổi theo chuẩn **Situational Judgment Test (SJT)** ([Christian et al., 2010](https://www.researchgate.net/publication/229890585_Situational_judgment_tests_Constructs_assessed_and_a_meta-analysis_of_their_criterion-related_validities); [Lievens et al., 2008](https://eprints.whiterose.ac.uk/id/eprint/159850/8/medu.14201.pdf)).

## 2. Kiến trúc Khoa học Đích (Target Scientific Architecture v3.0.0)

| Trụ cột nâng cấp | Công cụ / Mô hình hiện tại (v2.2.0) | Mô hình Khoa học Thay thế (v3.0.0) | Nguồn dẫn chứng & Tiêu chuẩn kiểm định | 
| ----- | ----- | ----- | ----- | 
| **1. Sở thích & Tự hiệu quả Nghề nghiệp** | 30 câu RIASEC tự soạn, Likert 1–5 tuyến tính, ghép nghề Cosine Similarity khóa biên `35%–96%`. | **Ma trận Kép SCCT-RIASEC (Interest × Self-Efficacy)** chuẩn hóa theo [O\*NET Interest Profiler Short Form](https://www.onetcenter.org/dl_files/IPSF_Psychometric.pdf) + **Điểm chuẩn hóa nội tại (Ipsative Within-Person Z-Score)** + **Cổng Đánh đổi Thực tế (Realistic Job Sacrifice Gate)**. | [O\*NET Resource Center](https://www.onetcenter.org/IP.html); [O\*NET IP Manual](https://www.onetcenter.org/dl_files/IP_Manual.pdf); [SCCT — Lent, Brown, & Hackett (1994)](https://www.researchgate.net/publication/230557657_Toward_a_Unifying_Social_Cognitive_Theory_of_Career_and_Academic_Interest_Choice_and_Performance); [Forced-Choice & Bias Control (2024)](https://www.econstor.eu/bitstream/10419/315318/1/10869_2024_Article_9931.pdf) | 
| **2. Nhân cách & Độ ổn định Cảm xúc** | 12 câu MBTI tự soạn (3 câu/trục), phân loại nhị phân `E/I, S/N, T/F, J/P`. | **Mini-IPIP Big Five (20 câu)** đo 5 chiều liên tục (*Openness, Conscientiousness, Extraversion, Agreeableness, Emotional Stability*) có câu hỏi đảo chiều (*reverse-keyed items*) và kiểm tra độ nhất quán nội tại. | [International Personality Item Pool (IPIP)](https://ipip.ori.org/); [Mini-IPIP Scale](https://ipip.ori.org/MiniIPIPTable.htm); [Mini-IPIP Scoring Key](https://ipip.ori.org/MiniIPIPKey.htm); [Big Five vs. MBTI APA Review](https://www.teamazing.com/blog/big-five-vs-mbti/) | 
| **3. Năng lực Thích ứng & Trí tuệ Cảm xúc** | 10 câu EQ tự nhận xét thuận chiều (`awareness, regulation, motivation, empathy`). | **Career Adapt-Abilities Scale (CAAS — 12/24 câu)** đo 4 chiều (*Concern, Control, Curiosity, Confidence*) kết hợp **TEIQue-SF / CSF** có câu đảo chiều chống tự tô hồng. | [CAAS — Savickas & Porfeli (2012)](https://www.marksavickas.com/files/Assessments/CAAS/6.%20CAAS_International%20Psychometrics.pdf); [Frontiers CAAS Validation (2024)](https://www.frontiersin.org/journals/psychology/articles/10.3389/fpsyg.2024.1338401/pdf); [TEIQue Consortium](https://www.eiconsortium.org/measures/teique.html); [TEIQue IRT Validation (PMC)](https://pmc.ncbi.nlm.nih.gov/articles/PMC6344417/) | 
| **4. Gương Phản chiếu Điểm mù 360°** | Không có (100% dựa vào học sinh tự khai). | **SOKA 360° Peer/Mentor Mirror**: Tạo link ẩn danh (6 câu hỏi hành vi quan sát được) gửi bạn học/giáo viên/phụ huynh để đối chiếu **Tự đánh giá (Self)** vs. **Người khác thấy (Other)**. | [SOKA Model — Vazire (2010)](https://simine.com/docs/Vazire_&_Carlson_SPPC_2010.pdf); [Feedback as a Path to Self-Knowledge](https://www.frontiersin.org/journals/psychology/articles/10.3389/fpsyg.2011.00312/full); [SOKA in Intelligence & Creativity](https://pmc.ncbi.nlm.nih.gov/articles/PMC6307038/) | 
| **5. Mô phỏng Tình huống 2D & 3D** | 2D chọn đáp án 3–1–0 lộ liễu; 3D lưu local, khóa đồng bộ server `503`. | **Trade-off SJT 2D** (các lựa chọn đồng cấp hấp dẫn xã hội, gắn kèm **Ngăn Tài liệu Chứng cứ Ẩn / Evidence Drawer** để đo hành vi tra cứu) + **ECD Stealth Assessment 3D** đồng bộ Event Log có xác thực phía server. | [SJT Meta-Analysis — Christian et al. (2010)](https://www.researchgate.net/publication/229890585_Situational_judgment_tests_Constructs_assessed_and_a_meta-analysis_of_their_criterion-related_validities); [High-Stakes SJT — Lievens & Sackett](https://www.semanticscholar.org/paper/Situational-judgment-tests-in-high-stakes-settings%3A-Lievens-Sackett/22dd8c86f241acca3bd819febc46846ba89c6cd9); [ECD & Stealth Assessment — Kim, Almond, & Shute (2016)](https://myweb.fsu.edu/vshute/pdf/IJT.pdf); [Shute & Moore (2017)](https://myweb.fsu.edu/vshute/pdf/ShuteMoore.pdf) | 
| **6. Động cơ Đối chiếu Chéo & AI** | Tổng hợp tuyến tính, AI khen ngợi chung chung theo kết quả tự khai. | **Self-Insight Discrepancy Engine** (tính độ lệch giữa *Tự khai — Góc nhìn 360° — Hành vi Mô phỏng*) + **Socratic AI Career Coach** chỉ ra điểm mù và giao thực nghiệm kiểm chứng giả thuyết. | [Blind Spots in Assessing Job Performance (2025)](https://www.tandfonline.com/doi/full/10.1080/08959285.2025.2515359); [ECD Simulation Assessment — Mislevy (2013)](https://myweb.fsu.edu/vshute/pdf/PL.pdf) | 

## 3. Các Nguyên tắc Kiến trúc & Tâm lý học Bất biến (Non-Negotiable Invariants)

1. **Bảo toàn Tương thích Ngược & Dữ liệu Cũ:** Không xóa bất kỳ bảng hoặc cột nào của schema `2.2.0`. Các bản ghi cũ trong `assessments`, `user_psychometrics` (`mbti`, `eq`) và `simulation_attempts` phải tiếp tục hiển thị bình thường. Nâng `$codeSchemaVersion` trong `app/functions.php` lên `'3.0.0'` và thực thi qua migration cộng thêm (`additive migration`).

2. **Kiểm soát Chất lượng Phản hồi (Response Quality & Validity Guards):**

   * Mọi bài đánh giá tâm lý học phải đo **thời gian làm bài (`duration_seconds`)** và **chỉ số phương sai phản hồi (`response_variance` / `longstring_index`)**. Nếu học sinh bấm cùng một mức điểm cho $\ge 85\%$ số câu hoặc hoàn thành 20–30 câu trong dưới 25 giây, hệ thống không từ chối lưu nhưng gắn cờ `validity_flag = 'low_variance_or_rushed'` và yêu cầu làm lại hoặc cảnh báo rõ trên báo cáo.

   * Bắt buộc có **câu hỏi đảo chiều (`reverse_keyed = true`)** trong các thang đo nhân cách và cảm xúc/thích ứng để khử thiên kiến đồng thuận (*Acquiescence Bias*).

3. **Không Tin Dữ liệu Điểm Thô từ Client:** Trong cả mô phỏng 2D (`app/simulation-controller.php`) và 3D (`api/game/`), client chỉ gửi chuỗi sự kiện hành động (`decisions`, `telemetry_events`, `evidence_opened`, `dwell_ms`). Server chịu trách nhiệm xác thực tính hợp lệ của chuỗi sự kiện và tính toán điểm năng lực.

4. **Chuẩn mực Đạo đức Đo lường Tâm lý:** Không sử dụng ngôn từ chẩn đoán lâm sàng hay phán xét nhân cách. Mọi độ lệch giữa *Tự khai* và *Hành vi mô phỏng* phải được trình bày dưới dạng **"Góc nhìn khám phá & Điểm mù tiềm năng"** theo tinh thần đối thoại Socratic.

5. **Tương thích cPanel & SQLite Test Runner:** Mọi truy vấn và migration trong `app/four-hubs-data.php` phải hỗ trợ song song MySQL/MariaDB (production) và SQLite (bộ test tự động `tests/psychometrics-test.php`, `tests/four-hubs-data-test.php`, `tests/four-hubs-http.py`).

## 4. Đặc tả Mở rộng Cơ sở Dữ liệu (`database/migrations/2026_12_scientific_psychometrics_v3.sql`)

Tạo file migration `database/migrations/2026_12_scientific_psychometrics_v3.sql` và cập nhật `run_migrations()` cùng `four_hub_sqlite_schema()`:

```
-- 1. Mở rộng bảng assessments để lưu Ma trận SCCT (Interest x Self-Efficacy), Z-scores và Cờ độ tin cậy
ALTER TABLE `assessments`
    ADD COLUMN `efficacy_scores` JSON NULL AFTER `scores`,
    ADD COLUMN `ipsative_z_scores` JSON NULL AFTER `efficacy_scores`,
    ADD COLUMN `validity_metrics` JSON NULL AFTER `constraint_answers`,
    ADD COLUMN `discrepancy_report` JSON NULL AFTER `validity_metrics`;

-- 2. Mở rộng bảng user_psychometrics để hỗ trợ chuẩn Big Five (Mini-IPIP), CAAS và TEIQue-SF
-- Lưu ý: Trên MySQL, mở rộng ENUM test_type; trên SQLite, cập nhật CHECK constraint hoặc hỗ trợ các mã chuẩn mới
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
    `rater_Relation` ENUM('peer', 'teacher', 'parent', 'mentor') NOT NULL DEFAULT 'peer',
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

## 5. Kế hoạch Triển khai Chi tiết Từng Giai đoạn (Step-by-Step Implementation Plan)

### Giai đoạn 1: Nâng cấp Engine Đo lường Tâm lý học Chuẩn hóa (`app/psychometrics.php` & `app/functions.php`)

1. **Thiết kế Ma trận Kép SCCT-RIASEC (`app/functions.php` & `index.php?page=assessment`):**

   * Giữ 30 câu hoạt động cốt lõi trong bảng `questions` (hoặc mở rộng lên 60 câu theo chuẩn [O\*NET Interest Profiler Short Form](https://www.onetcenter.org/dl_files/IPSF_Psychometric.pdf)), nhưng ở mỗi câu hỏi hoặc mỗi cụm chủ đề RIASEC, thu thập đồng thời 2 chiều theo [Lý thuyết SCCT](https://www.emerald.com/books/edited-volume/19176/chapter/103473726/Social-Cognitive-Career-TheoryA-Theory-of-Self):

     * **Mức độ Hứng thú (`interest` 1–5):** *"Bạn thích thực hiện hoạt động này đến mức nào?"*

     * **Mức độ Tự tin Năng lực (`efficacy` 1–5):** *"Bạn tự tin mình có thể làm tốt hoặc học tốt kỹ năng này đến mức nào?"*

   * **Thuật toán Chuẩn hóa Nội tại (Ipsative Within-Person Z-Score) trong `score_assessment()`:**

     * Bên cạnh điểm phần trăm tuyệt đối (`raw_scores`), tính trung bình cá nhân $\mu_{user}$ và độ lệch chuẩn $\sigma_{user}$ trên toàn bộ các câu trả lời của học sinh:
       

       $$
       Z_{k} = \frac{\bar{X}_{k} - \mu_{user}}{\max(0.25, \sigma_{user})}
       $$

     * Quy đổi $Z_{k}$ sang thang điểm chuẩn hóa nội tại `ipsative_scores` ($50 + 15 \times Z_{k}$, giới hạn $5..95$). Nếu học sinh chọn toàn 5 điểm cho mọi câu ($\sigma_{user} < 0.20$), gắn cờ `validity_metrics['flatline'] = true` và sử dụng thứ hạng ưu tiên tương đối thay vì cho tất cả các ngành đều khớp 96%.

   * **Phân loại 4 Góc phần tư SCCT cho từng nhóm RIASEC:**

     * *Hứng thú Cao + Tự hiệu quả Cao:* **Vùng Phát huy (Core Mastery)**.

     * *Hứng thú Cao + Tự hiệu quả Thấp:* **Vùng Khát vọng Cần Bồi dưỡng (Growth Aspiration — thích nhưng tự ti)**.

     * *Hứng thú Thấp + Tự hiệu quả Cao:* **Vùng Kỹ năng Thụ động (Burnout Risk — làm được nhưng dễ chán)**.

     * *Hứng thú Thấp + Tự hiệu quả Thấp:* **Vùng Không Ưu tiên (Low Priority)**.

2. **Tích hợp Thang đo Khoa học Mở `big5` (Mini-IPIP 20 câu) và `caas` (Career Adapt-Abilities 12 câu) vào `app/psychometrics.php`:**

   * **Bộ `big5` (20 câu chuẩn [Mini-IPIP](https://ipip.ori.org/MiniIPIPTable.htm) — Donnellan et al., 2006 / Goldberg, 1999):**

     * Đo 5 nhân tố, mỗi nhân tố 4 câu (2 câu thuận `pole => '+'`, 2 câu đảo chiều `pole => '-'` theo đúng [Mini-IPIP Scoring Key](https://ipip.ori.org/MiniIPIPKey.htm)):

       * `O` (*Openness / Intellect & Imagination* — Cởi mở nhận thức & Sáng tạo)

       * `C` (*Conscientiousness* — Tận tâm, Kỷ luật & Tổ chức)

       * `E` (*Extraversion* — Hướng ngoại & Năng lượng xã hội)

       * `A` (*Agreeableness* — Hòa đồng & Thấu cảm hợp tác)

       * `N` (*Emotional Stability / Low Neuroticism* — Ổn định cảm xúc & Chịu áp lực)

     * Khi chấm câu đảo chiều (`pole === '-'`), đảo giá trị thang 1–5 bằng công thức `6 - $value`.

   * **Bộ `caas` (12 câu rút gọn từ [Career Adapt-Abilities Scale — Savickas & Porfeli, 2012](https://www.marksavickas.com/files/Assessments/CAAS/6.%20CAAS_International%20Psychometrics.pdf); [Frontiers CAAS, 2024](https://www.frontiersin.org/journals/psychology/articles/10.3389/fpsyg.2024.1338401/pdf)):**

     * Đo 4 nguồn lực tâm lý xã hội của học sinh (3 câu/trục, có cài 1 câu kiểm tra độ chú ý/đảo chiều):

       * `concern` (*Quan tâm & Chuẩn bị tương lai*)

       * `control` (*Tự chủ & Chịu trách nhiệm quyết định*)

       * `curiosity` (*Tò mò khám phá & Thử nghiệm thực tế*)

       * `confidence` (*Tự tin vượt qua rào cản*)

   * **Kiểm định Độ tin cậy Phản hồi (`psychometric_validate_quality()`):**

     * Tính độ lệch chuẩn giữa các câu trong toàn bài và độ lệch giữa cặp câu thuận – câu nghịch trên cùng một trục. Nếu học sinh vừa chọn *"Rất đúng (5)"* ở câu thuận vừa chọn *"Rất đúng (5)"* ở câu nghịch trên >= 3 trục, gán `validity_flag = 'inconsistent'`.

     * Giữ nguyên hỗ trợ hiển thị cho các bản ghi `mbti` và `eq` cũ trong CSDL để không làm gãy dữ liệu lịch sử hoặc các bài test cũ.

3. **Tái cấu trúc Khảo sát Điều kiện & "Cổng Đánh đổi Thực tế" (`app/constraints.php` & `app/career-requirements.php`):**

   * Xóa bỏ 3 câu hỏi hình thức không lưu dữ liệu (`pushups`, `pullups`, `run`) trong `app/constraints.php`.

   * Thay thế bằng mục **"Kiểm tra Sự Đánh đổi Thực tế (Realistic Job Sacrifice Checklist)"**: Yêu cầu học sinh chọn tối đa 2 áp lực đặc thù mà các em **không thể chấp nhận** hoặc **sẵn sàng rèn luyện để vượt qua**:

     * `high_academic_duration` (Học chuyên sâu 6–8 năm, thi cử liên tục như Bác sĩ, Luật sư)

     * `strict_physical_discipline` (Kỷ luật điều lệnh, sơ tuyển thể lực/lý lịch nghiêm ngặt như Quân đội, Công an)

     * `debugging_detail_grind` (Ngồi hàng giờ tìm một lỗi nhỏ trong dữ liệu/mã nguồn/sổ sách như Lập trình viên, Kỹ sư AI, Tài chính)

     * `emotional_labor_crisis` (Tiếp xúc thường xuyên với cảm xúc tiêu cực, khủng hoảng hoặc bệnh nhân như Tâm lý, Điều dưỡng, Công tác xã hội)

     * `creative_rejection_deadline` (Làm lại nhiều lần theo phản hồi khách hàng dưới áp lực hạn chót như Thiết kế, Marketing, Kiến trúc, Báo chí)

   * Trong `career_matches()`, kết hợp:

     * $50\%$ Điểm tương đồng cấu trúc sở thích chuẩn hóa (`Ipsative Cosine Similarity` + `Holland Hexagon Distance`).

     * $25\%$ Điểm tương thích Tự hiệu quả (`Self-Efficacy Fit`) và Nhân cách `Big Five` (ví dụ: ngành Tài chính/Kỹ thuật yêu cầu trọng số `Conscientiousness` cao; ngành Tâm lý/Y khoa yêu cầu `Agreeableness` & `Emotional Stability` cao).

     * $25\%$ Điểm tương thích Điều kiện & Sự đánh đổi thực tế (`Realistic Sacrifice & Constraint Fit`). Bỏ khóa biên giả tạo `max(35, ...)` để phản ánh trung thực mức độ phù hợp từ `15%` đến `95%`.

### Giai đoạn 2: Xây dựng "Gương Phản Chiếu 360°" theo Mô hình SOKA (`app/assessment-hub.php`)

Dựa trên mô hình [Self-Other Knowledge Asymmetry (SOKA — Vazire, 2010)](https://simine.com/docs/Vazire_&_Carlson_SPPC_2010.pdf) và nghiên cứu về điểm mù năng lực ở học sinh ([Neubauer et al., 2018](https://pmc.ncbi.nlm.nih.gov/articles/PMC6307038/)):

1. **Tạo Link Mời Phản hồi 360° (`index.php?page=soka-invite` & `index.php?page=soka-feedback&token=...`):**

   * Trong `app/assessment-hub.php` và `index.php?page=assessment-report`, học sinh có thể bấm nút **"Tạo Link Gương Phản Chiếu 360°"** (lưu vào bảng `soka_mirror_requests` với `invite_token` ngẫu nhiên 32 byte).

   * Người được mời (bạn cùng nhóm học tập, giáo viên, hoặc phụ huynh) mở link `?page=soka-feedback&token=...` (không cần đăng nhập tài khoản học sinh, bảo vệ bằng CSRF & rate-limit theo IP) và đánh giá học sinh trên **6 hành vi quan sát được ngoài đời thực** (thang 1–5):

     1. **Phân tích & Giải quyết vấn đề (`analytical`):** Khi gặp bài khó hoặc sự cố, bạn ấy tìm nguyên nhân gốc rễ và kiểm tra bằng chứng thay vì đoán mò.

     2. **Sáng tạo & Linh hoạt (`creativity`):** Bạn ấy thường đưa ra ý tưởng mới hoặc cách tiếp cận khác biệt khi làm dự án.

     3. **Thấu cảm & Lắng nghe (`empathy`):** Khi làm việc nhóm, bạn ấy nhận ra cảm xúc của người khác và lắng nghe trước khi phản bác.

     4. **Tự chủ & Đáng tin cậy (`precision` / `conscientiousness`):** Bạn ấy hoàn thành phần việc đúng hạn, tỉ mỉ và giữ lời hứa.

     5. **Ra quyết định & Dẫn dắt (`decision`):** Khi nhóm lúng túng, bạn ấy dám đưa ra lựa chọn rõ ràng và chịu trách nhiệm.

     6. **Bình tĩnh dưới áp lực (`emotional_stability`):** Khi gần sát giờ nộp bài hoặc có tranh luận căng thẳng, bạn ấy vẫn giữ được sự bình tĩnh.

2. **Thuật toán Bóc tách 4 Vùng Nhận thức SOKA (Johari/SOKA Quadrant):**

   * Khi có ít nhất 1 phản hồi từ người khác hoặc 1 lượt mô phỏng thực tế, trang `assessment-report` tự động tính toán và hiển thị 4 vùng:

     * **Vùng Mở (*Open Strength*):** Điểm Tự đánh giá $\ge 70\%$ VÀ Điểm Người khác/Mô phỏng $\ge 70\%$.

     * **Điểm Mù (*Blind Spot — Quá tự tin*):** Điểm Tự đánh giá $\ge 75\%$ NHƯNG Điểm Người khác hoặc Hành vi Mô phỏng $< 55\%$.

     * **Viên Ngọc Ẩn (*Hidden Gem — Tự ti dưới mức năng lực thực*):** Điểm Tự đánh giá $< 55\%$ NHƯNG Điểm Người khác hoặc Hành vi Mô phỏng $\ge 75\%$.

     * **Vùng Cần Khám phá Thêm (*Unknown Area*):** Cả hai nguồn đều thấp hoặc chưa có dữ liệu kiểm chứng.

### Giai đoạn 3: Nâng cấp Mô phỏng 2D sang Trade-off SJT & Evidence-Centered Design (`app/simulations.php` & `app/simulation-controller.php`)

Áp dụng nguyên lý **Situational Judgment Tests (SJT)** ([Christian et al., 2010](https://www.researchgate.net/publication/229890585_Situational_judgment_tests_Constructs_assessed_and_a_meta-analysis_of_their_criterion-related_validities); [Lievens & Sackett](https://www.semanticscholar.org/paper/Situational-judgment-tests-in-high-stakes-settings%3A-Lievens-Sackett/22dd8c86f241acca3bd819febc46846ba89c6cd9)) và **Evidence-Centered Design (ECD / Stealth Assessment)** ([Kim, Almond, & Shute, 2016](https://myweb.fsu.edu/vshute/pdf/IJT.pdf)):

1. **Loại bỏ Đáp án 0 điểm Lộ liễu & Bổ sung "Ngăn Tài liệu Chứng cứ Ẩn" (`evidence_drawer`):**

   * Trong `app/simulations.php` và `app/simulation-controller.php`, mỗi nhiệm vụ (`mission`) được bổ sung một khối dữ liệu hiện trường thu gọn (ví dụ: *"🔍 Mở xem Log Hệ thống / Hồ sơ Bệnh án / Điều khoản Luật / Bảng Dự toán Chi tiết"*).

   * Trước khi bấm chọn phương án A/B/C, nếu học sinh **chủ động bấm mở và đọc ngăn dữ liệu chứng cứ ít nhất 3 giây**, giao diện ghi nhận `evidence_opened = true` vào `process_telemetry`.

   * Thiết kế lại các phương án lựa chọn để không còn các câu trả lời vô đạo đức/cẩu thả dễ đoán. Thay vào đó, mỗi lựa chọn đều có tính hợp lý nhất định nhưng phản ánh mức độ ưu tiên giữa 5 năng lực (`analysis`, `creativity`, `empathy`, `decision`, `precision`).

2. **Đo lường Dữ liệu Quá trình (Process Telemetry) trong `app/simulation-controller.php`:**

   * Thu thập ở phía client và gửi về server trong form `simulation-complete`:

     * `dwell_ms_per_mission`: Thời gian suy nghĩ ở từng nhiệm vụ.

     * `evidence_opened_count`: Số lần học sinh chủ động tra cứu dữ liệu hiện trường trước khi quyết định.

     * `option_switches`: Số lần đổi lựa chọn.

   * Trong `calculate_simulation_result()` (`app/functions.php`), tính toán **Chỉ số Hành vi Thực chứng (`Behavioral Integrity & Inquiry Score`)**:

     * Nếu học sinh chọn đáp án phân tích đúng nhưng `dwell_ms < 2500` (bấm vội dưới 2.5 giây) và không mở xem dữ liệu hiện trường (`evidence_opened_count === 0`), hệ thống ghi nhận đây là *"Phản xạ đoán mò / Chưa kiểm chứng dữ liệu"* và điều chỉnh giảm điểm năng lực `analysis` / `precision` kèm nhận xét phản hồi cụ thể.

3. **Mở Cổng Đồng bộ Server An toàn cho Mô phỏng 3D (`api/game/` & `game/src/systems/LessonSession.ts`):**

   * Thay vì trả về `503 GAME_SYNC_UNAVAILABLE` cố định, nâng cấp `api/game/finish.php` (hoặc endpoint nộp bằng chứng phiên chơi) với đầy đủ các chốt bảo mật đã nêu trong `api/game/README.md`:

     * Bắt buộc `require_login()` và `verify_csrf()`.

     * Client chỉ gửi mảng `actions: LessonAction[]` và `reflection` đã thực hiện trong `LessonSession`.

     * Server tái thực thi (replay) máy trạng thái `LessonSession` trên PHP (hoặc xác thực chuỗi hành động theo đồ thị điều kiện tiên quyết hợp lệ của 13 `LESSON_ACTIONS`) để tự sinh ra `competencies` và lưu vào bảng `game_runs` / `game_reports`. Tuyệt đối không nhận điểm số hay chuỗi bằng chứng tự viết từ phía trình duyệt.

### Giai đoạn 4: Xây dựng Động cơ Đối chiếu Chéo & AI Phản biện Socratic (`app/functions.php` & `app/four-hubs-ai.php`)

1. **Hàm Tính toán Độ lệch Tự nhận thức (`calculate_self_insight_discrepancy(int $userId)`):**

   * Viết hàm tổng hợp trong `app/four-hubs-data.php` để so sánh 3 nguồn dữ liệu chuẩn hóa (thang 0–100):

     1. **Tự khai (`self`):** Điểm `Self-Efficacy`, `Big Five` (`Conscientiousness`, `Openness`, `Agreeableness`, `Emotional Stability`), và `CAAS`.

     2. **Hành vi Thực nghiệm (`behavior`):** Điểm 5 kỹ năng từ `simulation_attempts` + `process_telemetry` (tỷ lệ mở dữ liệu chứng cứ, thời gian suy nghĩ, cảm xúc tại Trạm Tự Phản Tư) + bằng chứng 3D.

     3. **Góc nhìn 360° (`other`):** Điểm trung bình từ bảng `soka_mirror_responses` (nếu có).

   * Sinh ra danh sách **Các phát hiện đối chiếu (`discrepancy_findings`)**:

     * Ví dụ 1 (*Overestimation / Điểm mù Cẩn trọng*): Điểm tự khai `Conscientiousness` / `Conventional (C)` $\ge 80\%$ nhưng trong mô phỏng lại bỏ qua dữ liệu chứng cứ và chọn nhanh dưới 3 giây.

     * Ví dụ 2 (*Underestimation / Tiềm năng Ẩn*): Điểm tự khai `Self-Efficacy` ở nhóm `Investigative (I)` $< 55\%$ nhưng điểm giải quyết tình huống phân tích trong mô phỏng đạt $\ge 85\%$ và cảm thấy `excited`.

     * Ví dụ 3 (*Affective Mismatch / Xung đột Cảm xúc*): Điểm ghép nghề lý thuyết $\ge 80\%$ và điểm mô phỏng $\ge 80\%$, nhưng cảm nhận sau trải nghiệm lại chọn `unsuited` hoặc gắn thẻ `#ngai_ap_luc_thoi_gian`.

2. **Nâng cấp Prompt Hệ thống của OpenAI sang Phương pháp Phản biện Socratic (`app/functions.php` & `app/four-hubs-ai.php`):**

   * Cập nhật `generate_assessment_ai()` và `four_hub_chat_answer()` để truyền `discrepancy_findings`, `ipsative_z_scores`, `big5`, `caas` và `soka_mirror` vào ngữ cảnh của AI.

   * Yêu cầu AI không khen ngợi chung chung mà bắt buộc:

     1. Chỉ ra **1 Điểm tương đồng đã được kiểm chứng** (giữa lời tự khai và hành vi trong mô phỏng/góc nhìn 360°).

     2. Chỉ ra **1 Điểm lệch pha hoặc Điểm mù đáng suy ngẫm** (kèm dẫn chứng hành vi cụ thể học sinh đã làm trong hệ thống).

     3. Đặt **1 Câu hỏi phản tư Socratic** và giao **1 Thực nghiệm kiểm chứng giả thuyết (Hypothesis-Testing Micro-Experiment)** trong 7 ngày tới.

## 6. Danh mục Kiểm thử & Nghiệm thu Bắt buộc (Verification & Test Suite)

Trước khi hoàn tất, hãy cập nhật và chạy toàn bộ bộ kiểm thử tự động để đảm bảo không có lỗi hồi quy:

1. **Kiểm tra Cú pháp PHP & TypeScript:**

   * Chạy `php -l` trên toàn bộ các file `.php` trong thư mục gốc, `app/`, `api/game/` và `tests/`.

   * Chạy `cd game && npm test && npm run build` để đảm bảo 100% test Vitest của 3D engine vượt qua.

2. **Nâng cấp `tests/psychometrics-test.php`:**

   * Kiểm thử chấm điểm câu hỏi đảo chiều (`reverse-keyed`) của bộ `big5` ([Mini-IPIP](https://ipip.ori.org/MiniIPIPKey.htm)) và `caas` ([CAAS](https://www.marksavickas.com/files/Assessments/CAAS/6.%20CAAS_International%20Psychometrics.pdf)).

   * Kiểm thử phát hiện phản hồi phẳng (`flatline` khi chọn toàn điểm 5) và phản hồi mâu thuẫn (`inconsistent` khi chọn 5 cho cả câu thuận và câu nghịch).

   * Kiểm thử chuẩn hóa điểm nội tại `ipsative_z_scores` trong `score_assessment()`.

3. **Nâng cấp `tests/four-hubs-data-test.php` & `tests/four-hubs-http.py`:**

   * Kiểm thử migration idempotent lên phiên bản `3.0.0` trên cả SQLite và MariaDB/MySQL.

   * Kiểm thử luồng tạo token Gương Phản Chiếu 360° (`soka_mirror_requests`), gửi phản hồi ẩn danh (`soka_mirror_responses`) và tính toán 4 vùng SOKA (*Open Strength, Blind Spot, Hidden Gem, Unknown*).

   * Kiểm thử gửi `process_telemetry` trong mô phỏng 2D và xác thực replay hành động 3D trên server.

4. **Kiểm tra `health.php`:**

   * Cập nhật `health.php` để kiểm tra sự tồn tại của các bảng và cột mới trong schema `3.0.0`.