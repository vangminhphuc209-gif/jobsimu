# Kế Hoạch Triển Khai Nâng Cấp Từng Phase (Implementation Plan)

## MỤC TIÊU & DANH SÁCH CÁC PHASE

### Phase 0: Kiểm tra hiện trạng & Đường cơ sở (Hoàn thành)
- Baseline audit mã nguồn, môi trường, số liệu thực tế của CSDL.
- Lập tài liệu đường cơ sở và bản đồ bảo toàn dữ liệu.

### Phase 1: Chuẩn hóa logic kết quả & Ý nghĩa đánh giá
- Loại bỏ các nhãn tiêu cực (như "nguy cơ kiệt sức / Burnout Risk"). Thay bằng mô tả trung tính khoa học ("Kỹ năng thành thạo nhưng ít quan tâm", "Cần bổ sung trải nghiệm").
- Làm rõ bản chất của điểm số: Ipsative Z-score là độ ưu tiên nội tại, không phải phần trăm phù hợp nghề tuyệt đối hay percentile dân số.
- Phân biệt rành mạch giữa: Sở thích tự khai, Tự tin tự khai, Hành vi quan sát trong mô phỏng, và Điều kiện thực tế.
- Tạo `docs/upgrades/assessment-methodology.md`.

### Phase 2: Thống nhất kiến trúc thông tin & Trạng thái hành trình
- Thiết kế module xác định trạng thái hành trình dùng chung: `journey_state_resolve(user_id)`.
- Xác định "Bước tiếp theo ưu tiên" (Next Action Service) nhất quán giữa Dashboard, Header và Chatbot.
- Giữ nguyên các route cũ hoạt động ổn định.
- Tạo `docs/upgrades/user-journey.md`.

### Phase 3: Hệ thống giao diện & Frontend dùng chung
- Chuẩn hóa Design Tokens (màu sắc, khoảng cách, typography, button, badges, alerts).
- Tinh chỉnh responsive trên các kích thước màn hình 360px, 390px, 768px, 1280px.
- Đảm bảo trợ lý chatbot không che khuất nút CTA hoặc thao tác quan trọng.
- Tạo `docs/upgrades/ui-system.md`.

### Phase 4: Xây dựng "Hành trình của em" (Dashboard Trung Tâm)
- Tái cấu trúc Dashboard theo 5 khối thông tin ưu tiên: Bước tiếp theo, Nghề đang quan tâm, Những điều đã tìm hiểu, Công việc gần nhất trong tháng, Lịch sử.
- Xử lý mượt mà các trạng thái: Tài khoản mới, Chưa chọn nghề, Có nhiều nghề, Có mô phỏng chưa xong.

### Phase 5: Báo cáo kết quả & Hồ sơ khám phá nghề
- Tái cấu trúc Báo cáo kết quả (`index.php?page=result` và `assessment-report`):
  1. Em đã cung cấp thông tin gì?
  2. Kết quả hiện tại gợi ý điều gì?
  3. Kết quả này có giới hạn gì?
  4. Hướng khám phá tiếp theo.
- Cho phép học sinh phản hồi: "Kết quả này chưa giống em" kèm ghi chú tự nguyện.

### Phase 6: Khám phá nghề, ngành & Tuyển sinh có nguồn
- Thể hiện rõ mối quan hệ nhiều-nhiều giữa Nghề (`careers`) $\leftrightarrow$ Ngành đào tạo (`training_majors`) $\leftrightarrow$ Chương trình tuyển sinh (`programs`).
- Đảm bảo mọi số liệu điểm chuẩn, học phí đều hiển thị rõ: Năm tuyển sinh, Đơn vị đào tạo, Link nguồn tài liệu kiểm chứng.

### Phase 7: Thống nhất kế hoạch theo tháng (Monthly Execution Planner)
- Hợp nhất hệ thống kế hoạch: Chọn `user_roadmaps` và `user_roadmap_milestones` làm nguồn sự thật chính (đã có dữ liệu và đang được mở rộng các trường `target_month`, `monthly_goals`, `status`, `priority`).
- Hỗ trợ adapter tương thích cho `career_action_plans` nếu có yêu cầu truy cập từ route cũ `?page=plan`.
- Đảm bảo đầy đủ chức năng: Thêm, sửa, dời tháng, hoàn thành, mở lại, không sinh nhiệm vụ trùng khi reload.

### Phase 8: Hoàn thiện mô phỏng nghề giáo viên (Unity 3D / 2D)
- Ghi nhận hiện trạng: Unity 6 URP (`JobSimu3D/`) là bản game chính thức; Babylon.js WebGL (`game/`) là bản chuyển tiếp.
- Giữ cổng API game khóa fail-closed 503 cho tới khi có giao thức server-authoritative.
- Tạo tài liệu tích hợp game `docs/upgrades/game-integration.md`.

### Phase 9: Trợ lý AI theo ngữ cảnh (Trợ lý La Bàn)
- Thống nhất thương hiệu hiển thị: "Trợ lý La Bàn".
- Cung cấp ngữ cảnh RAG tối thiểu theo trang hiện tại; không gửi toàn bộ thông tin cá nhân vào mọi request.
- Chatbot chỉ đưa ra Action IDs có kiểm tra bảo mật từ máy chủ.

### Phase 10: Độ tin cậy, hiệu năng & Bảo mật
- Kiểm tra quyền sở hữu tài nguyên (Ownership check).
- Tối ưu hóa chuỗi migration trong `bootstrap.php` để không chạy lại DDL trên mọi web request.
- Thêm composite index tối ưu cho bảng `admission_records`.

### Phase 11: Kiểm thử tích hợp & Kế hoạch đánh giá học sinh
- Thiết lập bộ test suite tự động kiểm tra trọn vẹn hành trình người dùng.
- Chuẩn bị bộ công cụ đánh giá trải nghiệm người dùng với 6–8 học sinh THPT (Lớp 10–12).
- Tạo `docs/upgrades/usability-study.md` và `docs/upgrades/test-results.md`.

### Phase 12: Chuẩn bị phát hành & Bàn giao
- Rà soát diff, secret, migration.
- Tạo `docs/upgrades/release-checklist.md` và hướng dẫn vận hành chi tiết.
