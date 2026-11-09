# Master Prompt Nâng Cấp Hệ Thống Hướng Nghiệp 360 (JOBSIMU 4-Hub Architecture)

Bạn có thể sao chép toàn bộ nội dung dưới đây để giao việc trực tiếp cho AI Coding Agent (Claude Code, Cursor, Windsurf, GitHub Copilot Workspace) hoặc sử dụng làm tài liệu đặc tả kỹ thuật để triển khai nâng cấp mã nguồn [vangminhphuc209-gif/jobsimu](https://www.google.com/search?q=https://github.com/vangminhphuc209-gif/jobsimu).

## 1. Vai trò & Ngữ cảnh Hệ thống (Role & Context)

Bạn là **Principal Full-Stack Architect & Lead Game Engineer** phụ trách nâng cấp dự án **Hướng Nghiệp 360** ([vangminhphuc209-gif/jobsimu](https://www.google.com/search?q=https://github.com/vangminhphuc209-gif/jobsimu)).

Hệ thống hiện tại là nền tảng định hướng nghề nghiệp cho học sinh Việt Nam, vận hành trên:
- **Backend & Web Portal:** PHP 7.4+ (tương thích PHP 8.x), MySQL 5.7+/MariaDB, PDO Prepared Statements, triển khai trực tiếp trên cPanel không cần Node.js runtime ở phía server.
- **Frontend Styling:** Tailwind CSS được biên dịch sẵn ra `assets/css/app.css` và `assets/js/app.js`.
- **AI Engine:** Tích hợp trực tiếp OpenAI Responses API (`https://api.openai.com/v1/responses`, model mặc định `gpt-5-mini`) với `json_schema` Structured Outputs trong `app/functions.php`.
- **Hệ thống Mô phỏng Kép (Dual Simulation Architecture):**
  - **Mô phỏng tình huống 2D (Classic Mode):** 15 ngành nghề với 60 tình huống ra quyết định trong `app/simulations.php` và `app/simulation-controller.php`, lưu kết quả tại bảng `simulation_attempts`.
  - **Mô phỏng không gian 3D (3D Vertical Slice):** Ứng dụng độc lập trong `game/` viết bằng **TypeScript + Vite + Babylon.js 7.53** (`@babylonjs/core`, `@babylonjs/gui`, `@babylonjs/loaders`), kết nối với API tại `api/game/` và hệ bảng CSDL trong `database/migrations/2026_11_game3d_init.sql`.

**Mục tiêu nâng cấp:** Tái cấu trúc toàn bộ luồng trải nghiệm từ Trang chủ thành **4 Trung tâm Chức năng (4 Hubs)** khép kín mà không làm gãy bất kỳ chức năng, dữ liệu hay nguyên tắc bảo mật hiện có.

## 2. Sơ đồ Kiến trúc Thông tin Đích (Target Information Architecture)

Hệ thống sau nâng cấp phải tổ chức điều hướng (`app/header.php`) và Trang chủ (`index.php?page=home`) xoay quanh 4 phân khu chính:

1. **Trung Tâm Kiến Thức & Tuyển Sinh (`index.php?page=knowledge-hub`)**
   - **Thư viện 19 nhóm nghề & Lộ trình học tập:** Kế thừa và nâng cấp từ `page=careers` và `page=career`, phân loại nhóm ngành tham chiếu theo [danh mục ngành nghề Hướng Nghiệp Việt](https://huongnghiepviet.com/nganh-nghe).
   - **Tin tức tuyển sinh & Xu hướng nghề nghiệp:** Hiển thị các bài viết phân tích xu hướng thị trường lao động, thông tin mùa tuyển sinh, gắn thẻ (tag) liên kết trực tiếp tới các hồ sơ nghề trong thư viện.
   - **Cổng liên kết chuyển hướng uy tín (External Redirect Directory):** Danh mục các cổng thông tin tuyển sinh, tra cứu điểm chuẩn và hướng nghiệp chính thống, đi qua bộ lọc an toàn `safe_external_url()` và có nhãn cảnh báo minh bạch trước khi mở tab mới.
2. **Bộ Công Cụ Đánh Giá Bản Thân (`index.php?page=assessment-hub`)**
   - **Bài test Holland (RIASEC — Sở thích nghề nghiệp):** Giữ nguyên ngân hàng 30 câu hỏi chuẩn trong bảng `questions` và khảo sát điều kiện học tập trong `app/constraints.php` làm trụ cột ghép nghề chính.
   - **Bài test MBTI Rút gọn (Xu hướng tính cách):** 12 câu hỏi trắc nghiệm phân loại nhanh 4 chiều (`E/I`, `S/N`, `T/F`, `J/P`) để nhận diện phong cách làm việc và giao tiếp.
   - **Bài test EQ & Chỉ số Đam mê / Năng lực:** 10 câu hỏi tình huống đo lường khả năng tự nhận thức, quản lý áp lực và mức độ gắn kết nội tại.
   - **Kết quả & Báo cáo đánh giá tổng quan (`index.php?page=assessment-report`):** Trang tổng hợp trực quan hiển thị đồng thời biểu đồ 6 nhóm Holland, mã MBTI, hồ sơ EQ và Ma trận Sở thích & Trải nghiệm (`career_matrix_status()`).
3. **Phòng Trải Nghiệm Mô Phỏng 3D & Tương Tác (`index.php?page=simulations`)**
   - **Thư viện Không gian Nghề nghiệp 3D (Babylon.js WebGL/WebXR-ready):** Cổng khởi chạy trực tiếp ứng dụng 3D tại `game/` (mở đầu với chiến dịch *Người thầy sau tiếng trống trường* — nghề Giáo viên `giao-vien`).
   - **Kịch bản Trải nghiệm Thực tế (Interactive Scenarios 2D):** Duy trì đầy đủ 15 phòng mô phỏng tình huống nhanh (4–6 phút/lượt) cùng Trạm Dừng Tự Phản Tư (`reflection_feeling`, `reflection_note`).
   - **Nhật ký Trải nghiệm Nghề (Unified Activity Log):** Hợp nhất lịch sử từ bảng `simulation_attempts` (2D) và `game_runs` / `game_reports` (3D) trên cùng một dòng thời gian, hiển thị 5 nhóm năng lực (`Phân tích`, `Sáng tạo`, `Thấu cảm`, `Ra quyết định`, `Chính xác`).
4. **Trợ Lý AI & Bản Đồ Hành Trình (`index.php?page=ai-roadmap`)**
   - **Trí tuệ nhân tạo tổng hợp đa nguồn (Multi-Source AI Synthesis):** Nâng cấp `generate_assessment_ai()` để kết hợp dữ liệu từ: Kết quả Holland (`assessments`) + Điều kiện học tập (`constraint_answers`) + Hồ sơ MBTI/EQ (`user_psychometrics`) + Lịch sử mô phỏng 2D (`simulation_attempts`) & 3D (`game_reports`).
   - **Chatbot Tư vấn Chuyên sâu:** Giao diện hội thoại theo ngữ cảnh hồ sơ cá nhân của học sinh, có giới hạn tần suất (rate-limit) và gợi ý câu hỏi thông minh (prompt chips).
   - **Bản Đồ Hành Trình Nghề Nghiệp Cá Nhân (Personal Career Roadmap — Dashboard & PDF):** Giao diện lộ trình từng bước (Cấp học hiện tại → Môn học trọng tâm → Kỹ năng cần rèn → Thử thách 15 phút đời thực) tích hợp chế độ xuất PDF chuẩn in ấn (`@media print`).

## 3. Các Nguyên tắc Kiến trúc Bất biến (Non-Negotiable Invariants)

Khi viết code, bạn **BẮT BUỘC** tuân thủ 7 quy tắc bất biến sau từ `CLAUDE.md` và `docs/game3d.md`:

1. **Bảo toàn hệ thống PHP/MySQL hiện hữu:** Tuyệt đối không xóa hoặc làm hỏng các cột/bảng đang có trong `database/schema.sql` (`users`, `questions`, `careers`, `assessments`, `assessment_matches`, `favorites`, `simulation_attempts`, `settings`, `audit_logs`, `ai_logs`). Mọi thay đổi CSDL phải thực hiện qua cơ chế tự động nâng cấp an toàn trong `run_migrations()` (`app/functions.php`) và file SQL trong `database/migrations/`.
2. **Giữ nguyên Babylon.js cho 3D Client:** Mặc dù bản mô tả ý tưởng có nhắc tới cụm từ `WebXR/Three.js`, mã nguồn thực tế trong `game/package.json` đã được xây dựng trên **Babylon.js 7.53 + TypeScript + Vite**. **KHÔNG** cài đặt hay chuyển sang Three.js; mọi mở rộng không gian 3D phải viết trên Babylon.js trong thư mục `game/src/` và build ra `game/dist/`.
3. **Không dùng `eval()` và không nhận điểm số thô từ Client trong Game 3D:** Mọi điều kiện rẽ nhánh sự kiện 3D phải chạy qua bộ kiểm tra AST an toàn (`conditionEvaluator.ts`). Backend `api/game/` không bao giờ tin tưởng biến `score` do client gửi lên mà chỉ ghi nhận bằng chứng hành động hợp lệ.
4. **Chuẩn mực Sư phạm & An toàn Tâm lý:** Kết quả MBTI, EQ, Holland và báo cáo mô phỏng chỉ mang tính khám phá và tham khảo định hướng, tuyệt đối không dán nhãn bệnh lý hay phán xét giá trị học sinh. Giữ nguyên quy tắc an toàn cho sự kiện `CRISIS-03` và `ARC-A`.
5. **Bảo mật Web & Cổng Chuyển hướng:** Mọi form POST phải gọi `verify_csrf()`. Mọi truy vấn SQL phải dùng PDO Prepared Statements. Mọi liên kết ngoài phải đi qua `safe_external_url()` kèm thuộc tính `target="_blank" rel="noopener noreferrer"`.
6. **Không phụ thuộc thư viện PDF phía Server:** Để đảm bảo tương thích 100% với môi trường shared hosting cPanel tiêu chuẩn, chức năng xuất PDF Bản đồ hành trình phải sử dụng bộ quy tắc CSS `@media print` chuyên biệt kết hợp `window.print()`, không cài đặt headless browser hay thư viện nhị phân ngoài trên server.
7. **Tương thích Ngược với Route Cũ:** Các đường dẫn cũ (`?page=careers`, `?page=assessment`, `?page=result`, `?page=dashboard`, `?page=simulations`) phải tiếp tục hoạt động bình thường hoặc tự động hiển thị giao diện nâng cấp tương ứng mà không gây lỗi `404`.

## 4. Thiết kế Mở rộng Cơ sở Dữ liệu (Database Migration Specification)

Tạo file migration mới `database/migrations/2026_11_four_hubs_upgrade.sql` và tích hợp vào hàm `run_migrations()` trong `app/functions.php` (nâng `$codeSchemaVersion` lên `'2.2.0'`):

```sql
-- 1. Bảng Tin tức tuyển sinh & Xu hướng nghề nghiệp (Hub 1)
CREATE TABLE IF NOT EXISTS `knowledge_articles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(220) NOT NULL UNIQUE,
    `category` ENUM('admissions', 'trend', 'guide') NOT NULL DEFAULT 'admissions',
    `summary` VARCHAR(400) NOT NULL,
    `content` TEXT NOT NULL,
    `source_name` VARCHAR(120) NULL,
    `source_url` VARCHAR(500) NULL,
    `related_career_slug` VARCHAR(180) NULL,
    `featured` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('published', 'draft') NOT NULL DEFAULT 'published',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_articles_category_status` (`category`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng Liên kết chuyển hướng tới Trang Hướng Nghiệp Uy Tín (Hub 1)
CREATE TABLE IF NOT EXISTS `external_portals` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(150) NOT NULL,
    `organization` VARCHAR(150) NOT NULL,
    `icon` VARCHAR(20) NOT NULL DEFAULT '🌐',
    `description` VARCHAR(300) NOT NULL,
    `target_url` VARCHAR(500) NOT NULL,
    `category` ENUM('official_admissions', 'career_directory', 'scholarship', 'skills') NOT NULL DEFAULT 'career_directory',
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng Kết quả Đánh giá Mở rộng MBTI & EQ (Hub 2 - Độc lập với bảng assessments để bảo toàn FK)
CREATE TABLE IF NOT EXISTS `user_psychometrics` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `test_type` ENUM('mbti', 'eq') NOT NULL,
    `answers_json` JSON NOT NULL,
    `scores_json` JSON NOT NULL,
    `result_code` VARCHAR(32) NOT NULL,
    `summary_label` VARCHAR(150) NOT NULL,
    `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_psych_user_type` (`user_id`, `test_type`, `completed_at`),
    CONSTRAINT `fk_psych_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bảng Hội thoại Chatbot Tư vấn Chuyên sâu (Hub 4)
CREATE TABLE IF NOT EXISTS `ai_chat_messages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `role` ENUM('user', 'assistant') NOT NULL,
    `message` TEXT NOT NULL,
    `context_snapshot` JSON NULL,
    `model` VARCHAR(100) NULL,
    `latency_ms` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_chat_user_time` (`user_id`, `created_at`),
    CONSTRAINT `fk_chat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 5. Kế hoạch Triển khai Từng Giai đoạn (Step-by-Step Implementation Plan)

Hãy thực hiện tuần tự 4 giai đoạn dưới đây. Sau mỗi giai đoạn, kiểm tra cú pháp PHP (`php -l`) và chạy bộ test TypeScript (`npm test` trong `game/` nếu có chỉnh sửa 3D).

### Giai đoạn 1: Tái cấu trúc Trang Chủ, Điều hướng & Hub 1 (Trung Tâm Kiến Thức & Tuyển Sinh)

| Thành phần | Chi tiết kỹ thuật cần lập trình |
|---|---|
| `app/header.php` | Cập nhật menu chính thành 4 mục đại diện cho 4 Hub: **1. Kiến thức & Tuyển sinh** (`?page=knowledge-hub`), **2. Đánh giá bản thân** (`?page=assessment-hub`), **3. Phòng mô phỏng 3D** (`?page=simulations`), **4. AI & Bản đồ hành trình** (`?page=ai-roadmap`). |
| `index.php` (`page=home`) | Thiết kế lại khu vực bên dưới Hero Banner thành **Sơ đồ 4 Trụ cột Hướng nghiệp 360** hiển thị trực quan trạng thái tiến độ của học sinh (nếu đã đăng nhập) trên cả 4 Hub. |
| `app/knowledge-hub.php` (File mới) | Xây dựng controller & giao diện cho `page=knowledge-hub` (và gộp chung với `page=careers`) gồm 3 phân vùng: (1) **Tin tức tuyển sinh & Xu hướng nghề nghiệp** đọc từ bảng `knowledge_articles` (có dữ liệu seed mẫu thực tế); (2) **Thư viện 19 ngành nghề** có bộ lọc theo 6 nhóm RIASEC và từ khóa; (3) **Danh bạ Cổng Hướng nghiệp Uy tín** đọc từ `external_portals` (bao gồm liên kết [Hướng Nghiệp Việt](https://huongnghiepviet.com/nganh-nghe)), kiểm tra qua `safe_external_url()`. |
| `admin.php` | Bổ sung tab quản trị nhanh cho `knowledge_articles` và `external_portals` (Thêm/Sửa/Ẩn/Hiện). |

### Giai đoạn 2: Xây dựng Hub 2 (Bộ Công Cụ Đánh Giá Bản Thân Đa Chiều)

| Thành phần | Chi tiết kỹ thuật cần lập trình |
|---|---|
| `app/psychometrics.php` (File mới) | Khai báo ngân hàng câu hỏi tĩnh có cấu trúc chuẩn và hàm chấm điểm cho 2 bài đánh giá mới:<br>- **MBTI Rút gọn (12 câu):** 3 câu cho mỗi cặp đối lập (`E-I`, `S-N`, `T-F`, `J-P`), thang đo 5 mức độ, trả về mã 4 chữ cái (ví dụ: `ENFP`, `ISTJ`) kèm mô tả phong cách học tập/làm việc.<br>- **EQ & Chỉ số Đam mê / Năng lực (10 câu):** Đo lường 4 trụ cột (`Tự nhận thức`, `Tự điều chỉnh`, `Động lực & Đam mê`, `Thấu cảm & Hợp tác`), trả về điểm phần trăm (0–100%) cho từng trụ cột. |
| `index.php` (`page=assessment-hub`) | Xây dựng bảng điều khiển Đánh giá bản thân gồm 4 thẻ: (1) **Bài test Holland (RIASEC)** + Khảo sát điều kiện (`?page=assessment`); (2) **Bài test MBTI** (`?page=test-mbti`); (3) **Bài test EQ & Năng lực** (`?page=test-eq`); (4) **Báo cáo Đánh giá Tổng quan** (`?page=assessment-report`). Hỗ trợ tự động lưu nháp `localStorage` cho cả bài MBTI và EQ giống cơ chế của bài Holland. |
| `index.php` (`page=assessment-report`) | Trang tổng hợp hợp nhất kết quả mới nhất của cả 3 bài test: Biểu đồ vòng/cột 6 nhóm Holland, Thẻ hồ sơ MBTI, Thanh chỉ số 4 trụ cột EQ và danh sách Top nghề phù hợp kèm trạng thái `career_matrix_status()`. |

### Giai đoạn 3: Nâng cấp Hub 3 (Phòng Trải Nghiệm Mô Phỏng 3D & Nhật ký Hợp nhất)

| Thành phần | Chi tiết kỹ thuật cần lập trình |
|---|---|
| `app/simulation-controller.php` | Nâng cấp trang `?page=simulations` thành **3D Career Experience Hub** với 3 khu vực rõ rệt:<br>1. **Không gian Nghề nghiệp 3D (Babylon.js Spotlight):** Banner nổi bật cho chiến dịch 3D *Người thầy sau tiếng trống trường* (nghề Giáo viên), cho phép mở trực tiếp trình chạy 3D (`game/dist/index.html` hoặc nhúng iframe/full-viewport tại `?page=simulation-3d&career=giao-vien`).<br>2. **Kịch bản Trải nghiệm Thực tế (15 nghề 2D):** Danh sách 15 phòng mô phỏng tình huống có tìm kiếm và huy hiệu điểm phản xạ cao nhất.<br>3. **Nhật ký Trải nghiệm Nghề (Activity Log):** Bảng lịch sử kết hợp cả lượt chơi 2D (`simulation_attempts`) và phiên trải nghiệm 3D (`game_runs` / `game_reports`), hiển thị cảm xúc phản tư (`🤩 Hào hứng`, `🤔 Thử thách`, `😐 Chưa hợp`) và biểu đồ 5 kỹ năng. |
| `api/game/` (`bootstrap.php`, `start.php`, `save.php`, `finish.php`) | Nếu triển khai đồng bộ Server cho bản 3D: Áp dụng kiểm tra `require_login()`, `verify_csrf()`, kiểm tra quyền sở hữu `user_id` trên `game_runs` và khóa phiên bản `revision` theo đúng tiêu chí an toàn trong `api/game/README.md`. Trong trường hợp chưa bật sync server, đảm bảo `LessonSession` lưu checkpoint local và cho phép gửi tóm tắt bằng chứng hoàn thành về trang báo cáo một cách an toàn. |

### Giai đoạn 4: Xây dựng Hub 4 (Trợ Lý AI Tổng hợp & Bản Đồ Hành Trình Cá Nhân)

| Thành phần | Chi tiết kỹ thuật cần lập trình |
|---|---|
| `app/functions.php` (`ai_advice` & `generate_assessment_ai`) | Nâng cấp hàm `generate_assessment_ai()` để truy vấn thêm kết quả MBTI & EQ mới nhất từ `user_psychometrics` và báo cáo 3D từ `game_reports` (nếu có). Đưa toàn bộ dữ liệu đa nguồn (`Holland + Điều kiện học tập + MBTI + EQ + Kịch bản 2D/3D + Ghi chú tự phản tư`) vào payload gửi tới OpenAI Responses API để tạo lời khuyên đối chiếu sâu sắc giữa *Tính cách — Sở thích — Năng lực thực chiến*. |
| `app/ai-roadmap.php` (File mới) | Xây dựng trang `?page=ai-roadmap` (và tích hợp với `?page=dashboard`) gồm 3 khối chức năng:<br>1. **Bảng Tổng hợp Dữ liệu Đa chiều:** Tóm tắt chân dung người học từ kết quả Test + Mô phỏng 3D/2D + Thói quen học tập.<br>2. **Chatbot Tư vấn Chuyên sâu (`?page=ai-chat` POST/AJAX):** Cho phép học sinh đặt câu hỏi trực tiếp cho AI Career Coach. Hệ thống tự động đính kèm ngữ cảnh hồ sơ cá nhân của học sinh vào `system` prompt, giới hạn tối đa 10 tin nhắn/ngày/học sinh để bảo vệ ngân sách API, và có cơ chế trả lời dự phòng (fallback) thông minh khi chưa cấu hình `openai_api_key`.<br>3. **Bản Đồ Hành Trình Nghề Nghiệp Cá Nhân (Personal Career Roadmap — Dashboard & PDF):** Hiển thị lộ trình 4 chặng (Khám phá → Môn học & Tổ hợp xét tuyển → Rèn luyện kỹ năng → Thử thách 15 phút đời thực) kèm nút **"🖨️ Xuất Bản Đồ Hành Trình (PDF)"** kích hoạt chế độ in chuẩn A4. |
| `assets/css/app.css` | Bổ sung bộ quy tắc `@media print` ẩn thanh điều hướng, nút bấm, khung chat khi in; căn chỉnh trang Bản đồ hành trình vừa vặn khổ giấy A4 với màu sắc và biểu đồ rõ nét. |

## 6. Tiêu chí Nghiệm thu & Kiểm thử (Verification Checklist)

Trước khi hoàn tất nâng cấp, hãy chạy kiểm tra và xác nhận toàn bộ các tiêu chí sau:

1. **Kiểm tra Cú pháp & Môi trường:** Chạy `php -l` trên toàn bộ các file `.php` trong thư mục gốc, `app/`, và `api/game/`; đảm bảo không có lỗi cú pháp và tương thích hoàn toàn với PHP 7.4+.
2. **Kiểm tra Tự động Khởi tạo CSDL:** Khi truy cập trang bất kỳ, `run_migrations()` tự động tạo các bảng mới (`knowledge_articles`, `external_portals`, `user_psychometrics`, `ai_chat_messages`) và chèn dữ liệu mẫu (seed) cho Hub 1 mà không làm mất dữ liệu cũ.
3. **Kiểm tra Luồng 4 Hub:**
   - Từ `index.php?page=home`, truy cập mượt mà vào cả 4 Hub.
   - Làm thử bài MBTI và EQ tại Hub 2, kiểm tra lưu nháp `localStorage` và trang Báo cáo tổng quan.
   - Mở Hub 3, kiểm tra danh sách 15 mô phỏng 2D, cổng vào phòng 3D Giáo viên và Nhật ký trải nghiệm nghề.
   - Mở Hub 4, thử nghiệm tổng hợp dữ liệu bằng AI (cả khi có và không có OpenAI API Key), gửi tin nhắn tới Chatbot và bấm nút Xuất PDF Bản đồ hành trình.
4. **Kiểm tra Quản trị & Health Check:** Cập nhật `health.php` để kiểm tra thêm sự tồn tại của các bảng mới trong bộ 4 Hub.

Hãy bắt đầu bằng việc rà soát cấu trúc hiện tại, tạo file migration CSDL và triển khai lần lượt từ **Giai đoạn 1** đến **Giai đoạn 4**.
