# Danh Mục Kiểm Tra Phát Hành (Release Checklist)
*Bảng kiểm tra nghiệm thu toàn diện sản phẩm JobSimu trước khi xuất bản*

---

## 1. BẢO TOÀN DỮ LIỆU & CƠ SỞ DỮ LIỆU

| Tiêu chí | Trạng thái | Ghi chú bằng chứng |
| :--- | :---: | :--- |
| **Không mất bản ghi người dùng** | ĐẠT | 241 tài khoản người dùng nguyên vẹn, không có tài khoản thật nào bị xóa. |
| **Bảo toàn lịch sử đánh giá** | ĐẠT | 284 bài đánh giá Holland, đầy đủ điểm thô; bài cũ hiển thị bình thường không lỗi. |
| **Bảo toàn lịch sử mô phỏng** | ĐẠT | 181 lượt chơi mô phỏng và ghi chú phản tư được lưu giữ trọn vẹn. |
| **Dữ liệu ngành & tuyển sinh** | ĐẠT | 27 nghề, 358 ngành đào tạo, 6 trường trọng điểm, 46 bản ghi điểm chuẩn có nguồn. |
| **Không có migration tự chạy trong request** | ĐẠT | Đã đóng gói cache lock file (`.lock`), giảm thời gian bootstrap từ 4.14s xuống 0.16s (giảm 95.2% latency). |
| **Khóa an toàn 3D API cũ** | ĐẠT | `api/game/*` tiếp tục trả HTTP 503 fail-closed (`GAME_SYNC_UNAVAILABLE`). |

---

## 2. CHỨC NĂNG & HÀNH TRÌNH NGƯỜI DÙNG

| Phân hệ chức năng | Trạng thái | Chi tiết kiểm chứng |
| :--- | :---: | :--- |
| **Hành trình người dùng (User Journey)** | ĐẠT | 5 trạng thái (`anonymous`, `not_started`, `profile_ready`, `career_saved`, `simulated`, `planning`) chuyển tiếp xác định theo `journey_user_state` và `journey_resolve_next_step`. |
| **Dashboard "Hành trình của em"** | ĐẠT | Tái cấu trúc 5 khối: Bước tiếp theo ưu tiên ➔ Nghề quan tâm (top 3 + xem thêm) ➔ Công việc tháng gần nhất ➔ Hồ sơ tổng quan ➔ Tìm kiếm nhanh. |
| **Báo cáo kết quả đánh giá** | ĐẠT | 5 khối sư phạm: Thông tin tự khai ➔ Phân bố 6 nhóm RIASEC & Z-score nội tại ➔ Giới hạn diễn giải & Lưu ý phương pháp ➔ 8 nghề tương đồng kèm đối chiếu mô phỏng ➔ Ghi chú phản tư của học sinh. |
| **Khám phá nghề & Tuyển sinh có nguồn** | ĐẠT | Nghề nghiệp liên kết rõ với Ngành đào tạo; điểm chuẩn hiển thị đầy đủ Năm, Phương thức, Thang điểm, Huy hiệu xác minh và liên kết đề án gốc. |
| **Thống nhất kế hoạch theo tháng** | ĐẠT | Hợp nhất trên `user_roadmaps` và `user_roadmap_milestones`; route cũ `page=plan` tự động chuyển tiếp sạch sẽ về `index.php?page=ai-roadmap`. |
| **Mô phỏng 2D & 3D Unity** | ĐẠT | Kịch bản 3D THCS Bình Minh — Lớp 8A (Cô Mai, 4 nhóm, 12 học sinh) hoạt động xác định; trang chi tiết nghề Giáo viên liên kết trực tiếp bản 3D. |
| **Trợ lý AI La Bàn (Cô Mây)** | ĐẠT | Phản hồi gợi mở Socratic, chống prompt injection, tôn trọng rate limit, các nút hành động (action buttons) dẫn đúng trang. |

---

## 3. HIỆU NĂNG & AN TOÀN BẢO MẬT

| Mục kiểm tra | Kết quả đo lường | Tiêu chuẩn đạt |
| :--- | :--- | :---: |
| **Thời gian nạp Bootstrap** | **164.1 ms** (so với 4.139.0 ms ban đầu) | ĐẠT (< 500 ms) |
| **Kiểm soát phân quyền & IDOR** | Học sinh A truy vấn bài làm học sinh B nhận 404/rỗng; mọi lệnh update đều gắn điều kiện `AND user_id=?` | ĐẠT |
| **Chống giả mạo request (CSRF)** | Toàn bộ POST form và API (`result-feedback`, `ai-regenerate`, `may-chat-api`, `user-roadmap`) đều xác thực token qua `verify_csrf()` | ĐẠT |
| **Lọc dữ liệu hiển thị (XSS)** | Dùng hàm `e()` / `htmlspecialchars()` trên mọi chuỗi dữ liệu in ra trình duyệt | ĐẠT |
| **Tái sử dụng kết nối CSDL** | Đảm bảo 1 kết nối PDO duy nhất trong suốt vòng đời request (`db() === db()`) | ĐẠT |

---

## 4. GIAO DIỆN & TỐI ƯU DI ĐỘNG

| Độ phân giải | Kết quả kiểm tra giao diện |
| :--- | :--- |
| **Mobile nhỏ (360px – 390px)** | Giao diện hiển thị co giãn chuẩn; vùng an toàn Chatbot Safe Zone (`padding-bottom: 72px`) bảo đảm nút kích hoạt không che khuất CTA hay mốc kế hoạch. |
| **Tablet (768px)** | Bố cục dạng cột linh hoạt (flex/grid), bảng điểm chuẩn có thanh cuộn ngang `table-responsive`. |
| **Desktop (1280px+)** | Bố cục 12 cột chuẩn mực, sidebar cố định tiện theo dõi, khoảng cách thị giác hài hòa. |

---

## 5. TỔNG HỢP KIỂM THỬ TỰ ĐỘNG ĐÃ VƯỢT QUA

1. `tests/psychometrics-test.php` — **PASS 100%** (Chuẩn hóa Z-score, SCCT trung tính, Zero-variance guard).
2. `tests/journey-state-test.php` — **PASS 100%** (Đủ 5 trạng thái hành trình, next-step resolver).
3. `tests/preservation-regression-test.php` — **PASS 100%** (Bảo toàn dữ liệu cũ, câu hỏi, điểm chuẩn unsourced).
4. `tests/result-logic-test.php` — **PASS 100%** (Báo cáo kết quả 5 khối, phản tư học sinh, ma trận 4 góc).
5. `tests/career-admission-provenance-test.php` — **PASS 100%** (Quan hệ Nghề - Ngành, tuyển sinh có nguồn).
6. `tests/user-roadmap-test.php` — **PASS 100%** (Kế hoạch theo tháng, tạo/sửa/đánh dấu nhiệm vụ, AI gợi ý).
7. `tests/may-actions-test.php` — **PASS 100%** (Bộ action buttons an toàn của Trợ lý La Bàn).
8. `tests/may-knowledge-test.php` — **PASS 100%** (Truy xuất dữ liệu có nguồn, chống bịa đặt thông tin).
9. `tests/may-chat-test.php` — **PASS 100%** (Tích hợp chatbot, rate limiting, Socratic prompt).
10. `tests/security-performance-test.php` — **PASS 100%** (IDOR check, CSRF check, 164ms bootstrap latency).
11. `tests/e2e-user-journey-test.php` — **PASS 100%** (Trọn vẹn chuỗi 7 bước hành trình từ đăng ký đến lập kế hoạch).
