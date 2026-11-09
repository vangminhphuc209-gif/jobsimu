# University Explorer — P0/P1

Trang public: `index.php?page=university-explorer`.

## Đã có
- 24 nhóm lĩnh vực nghề từ từ điển Wake Power (chỉ tên nhóm, không sao chép bài viết).
- 8 hồ sơ nghề mới (không có mô phỏng giả); giữ nguyên nghề và trò chơi cũ.
- 14 mã ngành giới thiệu; 5 hồ sơ trường khởi tạo.
- Quan hệ nghề → ngành → chương trình → trường; tìm trường theo tên/mã/ngành, tỉnh thành, nhóm nghề.
- Bảng chương trình, điểm chuẩn theo năm + phương thức, học phí, điều kiện và mốc tuyển sinh, với thông báo khi chưa có dữ liệu.
- Ví dụ đã đối chiếu nguồn chính thức: HUST IT1 năm 2023–2025 theo hai phương thức THPT và Đánh giá tư duy; **không tách điểm theo tổ hợp vì nguồn không tách**.
- Dữ liệu được bổ sung idempotent khi khởi tạo và không ghi đè mục đã có.
- Tương thích schema MySQL và SQLite theo mã chuyển đổi; chưa chạy integration test trên hosting.

## Không có ở giai đoạn này
- Tự động cào toàn bộ website trường; quản trị nhập/dòng duyệt nhập liệu; tài liệu xác minh chi tiết; toàn bộ điểm chuẩn/học phí 2026 của mọi trường.
- Game 3D mới, học phí tính toán thật và tự động đánh giá điều kiện sức khỏe.
- Xác nhận hoạt động production trên cPanel.

## Để thử
1. Checkout nhánh `feature/career-university-explorer` trên môi trường staging với DB sao lưu.
2. Bảo đảm DB user có quyền CREATE/ALTER; `app/bootstrap.php` gọi `run_admissions_migrations()` sau schema cũ.
3. Truy cập `index.php?page=university-explorer`, chọn Đại học Bách khoa Hà Nội → Khoa học máy tính (IT1).
4. Xác nhận các điểm 2023/2024/2025 và link nguồn.
5. Kiểm tra `index.php?page=knowledge-hub`, một trang nghề, rồi đăng nhập và hoàn thành thử đánh giá/mô phỏng.
6. Refresh vài lần và xác minh không có bản ghi nghề/trường/điểm chuẩn trùng lặp.

## Quan trọng về dữ liệu
- Không điền học phí/tín chỉ khi chưa có đề án/chương trình chính thức.
- Nhập `admission_records` theo đúng năm/phương thức/thang điểm; cùng một điểm chuẩn chung của chương trình không nhân bản thành từng tổ hợp.
- Khi nhập số liệu qua SQL, bắt buộc điền `source_url` (đề án hoặc thông báo chính thức).
- Dữ liệu chỉ mang tính tham khảo, học sinh cần đối chiếu với thông báo hiện hành.

Nguồn nhóm nghề: https://hocvienhuongnghiep.com/tu-dien-huong-nghiep/
Nguồn IT1: https://soict.hust.edu.vn/diem-chuan-tham-khao.html
