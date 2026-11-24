# Nhật Ký Quyết Định Kỹ Thuật (Architecture Decision Log - ADR)

---

## ADR 001: Xác định Đường Cơ Sở và Cô Lập Cơ Sở Dữ Liệu Thử Nghiệm
- **Bối cảnh**: `config.php` đang kết nối trực tiếp đến MySQL remote (`103.200.23.80`) chứa 241 người dùng thật. Các bài test cũ từng gây lỗi dữ liệu khi ghi trực tiếp vào CSDL này.
- **Quyết định**:
  1. Khi chạy test tự động, kích hoạt cờ môi trường `JOBSIMU_FORCE_ENV_DB=1` hoặc sử dụng SQLite in-memory (`sqlite::memory:`) để đảm bảo không tác động dữ liệu thật.
  2. Tuyệt đối không chạy script xóa `scripts/cleanup-grade-6-9-students.php` lên CSDL mặc định.
- **Trạng thái**: Đã áp dụng từ Phase 0.

---

## ADR 002: Thống Nhất Nguồn Dữ Liệu Kế Hoạch (Roadmap Single Source of Truth)
- **Bối cảnh**: Tồn tại 2 hệ thống kế hoạch song song: `user_roadmaps` (5 bản ghi, đang được mở rộng các trường theo tháng) và `career_action_plans` (0 bản ghi).
- **Quyết định**: Chọn `user_roadmaps` và `user_roadmap_milestones` làm nguồn lưu trữ duy nhất. Điều hướng hoặc chuyển đổi logic từ `plan.php` sang sử dụng cùng nguồn dữ liệu này để tránh phân mảnh.
- **Trạng thái**: Đã quyết định tại Phase 0, thực thi tại Phase 7.

---

## ADR 003: Chuẩn Hóa Nhãn Phân Loại SCCT và Diễn Giải Điểm Số Trung Tính
- **Bối cảnh**: Vùng sở thích thấp + tự tin cao đang bị dán nhãn "Vùng Kỹ năng Thụ động (Burnout Risk)", dễ gây hiểu nhầm tâm lý học đường cho học sinh THPT.
- **Quyết định**: Đổi nhãn thành "Kỹ năng Khả dụng nhưng Ít Ưu tiên" với giải thích trung tính: "Bạn tự tin vào năng lực này nhưng hiện tại ít hứng thú; có thể dùng làm kỹ năng bổ trợ thay vì định hướng nghề nghiệp chính". Đồng thời ghi rõ điểm số là chỉ số ưu tiên nội tại, không phải phần trăm thành công hay percentile dân số.
- **Trạng thái**: Đã quyết định tại Phase 0, thực thi tại Phase 1.
