# Baseline System Audit & Technical Foundation (Phase 0)
*Thời điểm kiểm tra: 19/11/2026 — Antigravity Engineering Lead*

---

## 1. MÔI TRƯỜNG THỰC TẾ & CẤU HÌNH HỆ THỐNG

| Thành phần | Phiên bản / Cấu hình thực tế | Ghi chú & Đánh giá an toàn |
| :--- | :--- | :--- |
| **Hệ điều hành** | Linux (Ubuntu x86_64) | Môi trường phát triển cục bộ. |
| **PHP Runtime** | PHP 8.5.4 (cli) with Zend OPcache v8.5.4 | Codebase tương thích tốt; tránh dùng các hàm deprecate trong PHP 8.2+. |
| **Cơ sở dữ liệu mặc định** | MariaDB qua MySQL PDO (`103.200.23.80:3306`, DB: `devxcom_jobsimu`) | **CẢNH BÁO QUAN TRỌNG**: `config.php` trỏ tới cơ sở dữ liệu từ xa có chứa 241 người dùng và 284 bài đánh giá thật. Tuyệt đối không chạy test phá hủy lên kết nối này! |
| **Cơ chế tách môi trường DB** | `JOBSIMU_FORCE_ENV_DB=1` nạp `config.example.php` đọc biến môi trường | Đã có sẵn hook trong `app/bootstrap.php`. Có thể dùng biến môi trường để trỏ test vào SQLite in-memory hoặc test database riêng. |
| **Git & Nhánh làm việc** | Nhánh `feat/career-roadmap-redesign` | Có thay đổi chưa commit tại `app/ai-roadmap.php`, `app/user-roadmap.php`, `assets/css/roadmap.css`. Tuyệt đối không dùng `git reset --hard` hay ghi đè. |
| **Web Server / Routing** | Apache (`.htaccess`) hoặc PHP Built-in Server | Route trung tâm qua `index.php?page=...`. |

---

## 2. KIỂM KÊ DỮ LIỆU THỰC TẾ (GROUND TRUTH AUDIT)

*Lưu ý: Báo cáo dưới đây dựa trên truy vấn trực tiếp vào CSDL đang kết nối, loại bỏ hoàn toàn các giả định chưa xác minh.*

| Bảng dữ liệu | Số bản ghi thực tế | Hiện trạng & Nguồn dữ liệu |
| :--- | :---: | :--- |
| `careers` | 27 | 27 hồ sơ nghề nghiệp cơ bản kèm mô tả, kỹ năng, tổ hợp môn, đường học. |
| `training_majors` | 358 | 358 mã ngành đào tạo cấp IV chuẩn Bộ GD&ĐT (từ nguồn Hướng Nghiệp Việt). |
| `major_fields` | 23 | 23 khối ngành lớn cấp II (Kỹ thuật, Y tế, Sư phạm,...). |
| `major_subgroups` | 74 | 74 nhóm ngành cấp III. |
| `institutions` | 6 | 6 cơ sở giáo dục đại học (ĐHQGHN, Bách Khoa, Sư phạm HN, Kinh tế Quốc dân, Y Hà Nội, v.v.). *(allupdate.md trước đó ghi 235 là chưa chính xác với dữ liệu seed hiện tại).* |
| `programs` | 11 | 11 chương trình đào tạo cụ thể gắn với các cơ sở đào tạo trên. |
| `admission_records` | 46 | 46 bản ghi điểm chuẩn có `admission_year`, `source_url`, `source_title`, `cutoff_score`. |
| `institution_admission_notices` | 4 | 4 thông báo/đề án tuyển sinh chính thức có link nguồn. |
| `questions` | 30 | 30 câu hỏi trắc nghiệm sở thích Holland (O*NET). |
| `users` | 241 | 241 tài khoản người dùng (gồm học sinh thật, tài khoản test và admin). |
| `assessments` | 284 | 284 bài làm trắc nghiệm Holland của người dùng kèm mã kết quả và điểm thành phần. |
| `simulation_attempts` | 181 | 181 lượt trải nghiệm mô phỏng tình huống 2D có lưu điểm kỹ năng và phản tư. |
| `user_roadmaps` | 5 | 5 bản đồ hành trình cá nhân theo bảng `user_roadmaps`. |
| `career_action_plans` | 0 | 0 bản ghi ở bảng `career_action_plans` của module `plan.php`. |
| `provinces` | 3 | 3 tỉnh thí điểm: Lào Cai, Hà Giang, Hưng Yên. |
| `schools` | 6 | 6 trường THPT trọng điểm thuộc 3 tỉnh trên. |

---

## 3. BẢN ĐỒ CHỨC NĂNG & ĐIỀU HƯỚNG HIỆN HÀNH

```
index.php (Front Controller)
├── ?page=home                    -> app/home-hubs.php (Trang chủ 4 Trụ Cột)
├── ?page=knowledge-hub, careers  -> app/knowledge-hub.php (Hub 1: Tri thức nghề)
├── ?page=majors, major-detail    -> app/majors-explorer.php (Hub 1: 358 ngành cấp IV)
├── ?page=university-explorer     -> app/university-explorer.php (Hub 1: Khám phá trường)
├── ?page=university-compare      -> app/university-compare.php (Hub 1: So sánh trường ĐH)
├── ?page=lookup, lookup-api      -> app/lookup.php (Hành trình mới: Tra cứu hợp nhất)
├── ?page=assessment-hub          -> app/assessment-hub.php (Hub 2: Đánh giá bản thân)
├── ?page=assessment              -> index.php:41 (Holland 30 câu)
├── ?page=result                  -> index.php:43 (Kết quả Holland + Gợi ý nghề)
├── ?page=constraints             -> app/constraints.php (Khảo sát rào cản thực tế)
├── ?page=test-big5, test-caas... -> app/assessment-hub.php (Bài đánh giá bổ sung)
├── ?page=soka-mirror             -> app/soka-mirror.php (Gương 360° phản chiếu)
├── ?page=discover, discover-api  -> app/discover.php (Hành trình mới: Khám phá 5 câu)
├── ?page=compare, compare-api    -> app/compare.php (Hành trình mới: So sánh ưu tiên)
├── ?page=simulations, career...  -> app/simulation-controller.php (Hub 3: Mô phỏng 2D)
├── ?page=simulation-3d           -> app/simulation-3d.php (Hub 3: Iframe Babylon.js)
├── ?page=ai-roadmap, ai-chat     -> app/ai-roadmap.php + app/user-roadmap.php (Hub 4: Kế hoạch 1)
├── ?page=plan, plan-api          -> app/plan.php (Hành trình mới: Kế hoạch 2)
├── ?page=dashboard, profile      -> index.php:45 + app/auth-views.php (Không gian cá nhân)
├── ?page=login, register         -> app/auth-views.php (Đăng ký/Đăng nhập)
├── ?page=may-chat-api            -> app/may-chat-api.php (API Trợ lý La Bàn)
└── ?page=schools-api             -> app/schools.php (API dropdown trường 0ms)
```

---

## 4. KẾT QUẢ ĐIỀU TRA CÁC ĐIỂM NGHI NGỜ (AUDIT FINDINGS)

1. **Hai hệ thống lập kế hoạch song song**:
   - Hệ thống 1: `app/ai-roadmap.php` và `app/user-roadmap.php` (bảng `user_roadmaps`, `user_roadmap_milestones`). Có 5 bản ghi. Đang được chỉnh sửa để hỗ trợ chia theo tháng (Monthly Planner).
   - Hệ thống 2: `app/plan.php` (bảng `career_action_plans`, `career_action_plan_items`). Có 0 bản ghi.
   - *Kết luận*: Hai hệ thống hoàn toàn tách biệt, không dùng chung bảng. Cần hợp nhất thành một nguồn sự thật duy nhất (Single Source of Truth) trong Phase 7, bảo toàn 5 bản ghi hiện có của `user_roadmaps`.

2. **Công thức chấm điểm và ngưỡng phân loại**:
   - `app/functions.php`: Hàm `score_assessment_scientific` tính Ipsative Z-score.
   - Ngưỡng phân loại SCCT (dòng 409-430): Điểm $\ge 58$ cho Interest và Self-Efficacy. Vùng sở thích thấp + tự tin cao đang bị dán nhãn `'Vùng Kỹ năng Thụ động (Burnout Risk)'`.
   - *Kết luận*: Cần chuẩn hóa nhãn này thành mô tả trung tính khoa học, không gán nhãn y khoa/tâm lý tiêu cực "nguy cơ kiệt sức" cho học sinh THPT.

3. **Mô phỏng 3D và API 503**:
   - `game/`: Bản Babylon.js WebGL (4 khu vực trường học). Chạy bằng checkpoint trình duyệt.
   - `JobSimu3D/`: Dự án Unity 6 URP (Lớp 8A THCS Bình Minh). Chạy bằng C# FSM xác định, lưu `LocalSaveStore`.
   - `api/game/*`: Trả về HTTP 503 `GAME_SYNC_UNAVAILABLE` do cố ý khóa fail-closed để chặn gian lận điểm từ client.
   - *Kết luận*: Khóa 503 là đúng thiết kế an toàn. Không tự tiện gỡ bỏ khóa này khi chưa có server-authoritative validation.

4. **Migration tự động trong Web Request**:
   - `app/bootstrap.php` gọi 9 hàm migration liên tiếp trên mỗi request.
   - *Kết luận*: Cần tối ưu hóa có cache kiểm tra phiên bản (Schema Version Check) để không chạy lặp truy vấn DDL/SHOW TABLES trên môi trường production.

5. **Dữ liệu tuyển sinh**:
   - 46 bản ghi `admission_records` đều có `admission_year`, `source_url`, `source_title`, `cutoff_score`.
   - Cần tiếp tục duy trì nguyên tắc: thông tin tuyển sinh phải luôn gắn liền với năm tuyển sinh và nguồn thẩm định rõ ràng.

---

## 5. BẢN ĐỒ DỮ LIỆU CẦN BẢO TOÀN TUYỆT ĐỐI

1. `users` (241 tài khoản) — Tuyệt đối không xóa tài khoản.
2. `assessments` (284 bản ghi) — Bảo toàn lịch sử làm bài Holland và điểm thô.
3. `user_psychometrics` — Bảo toàn bài làm Big Five, CAAS, MBTI, EQ.
4. `simulation_attempts` (181 bản ghi) — Bảo toàn lịch sử và cảm nhận phản tư 2D.
5. `user_roadmaps` (5 bản ghi) & `user_roadmap_milestones` — Bảo toàn tiến độ của học sinh.
6. `careers` (27 nghề) & `training_majors` (358 ngành) — Bảo toàn danh mục dữ liệu chuẩn.
7. `admission_records` & `institutions` — Bảo toàn dữ liệu điểm chuẩn đã xác minh.
