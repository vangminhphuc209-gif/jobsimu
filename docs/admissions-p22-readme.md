# JOBSIMU P2.2 — Bộ dữ liệu điểm chuẩn và học phí có nguồn

Ngày đối chiếu: 09/11/2026. Cung cấp danh mục ban đầu cho quá trình mở rộng; **không khẳng định đủ dữ liệu tất cả trường**.

## 1. Bảng điểm chuẩn (40 bản ghi, chưa tự công bố)
- File `docs/admissions-p22-official-cutoffs.csv` chứa **40 bản ghi** với URL nguồn, năm, phương thức, điểm và thang điểm.
- Đại học Bách khoa Hà Nội: 5 chương trình tin học (IT2, IT-E10, IT-E15, IT-E6, IT-E7), hai phương thức Điểm thi THPT/Đánh giá tư duy, giai đoạn 2023–2025.
- Trường Đại học Sư phạm Hà Nội: Sư phạm Toán học, CNTT 2023–2025; Tâm lý học (Tâm lý học trường học), Công tác xã hội và Quản trị dịch vụ du lịch và lữ hành năm 2025.
- Chương trình IT1 của ĐHBK Hà Nội đã được seed ở P1, tránh tạo một bản ghi trùng và giữ quyền chỉnh sửa của admin.
- **Nguồn chính thức:** https://soict.hust.edu.vn/diem-chuan-tham-khao.html ; https://smcs.hnue.edu.vn/tuyen-sinh-dai-hoc/diem-chuan-3-nam-gan-nhat-2026.html ; https://hnue.edu.vn/tin-tuc/10941/thong-bao-diem-chuan-xet-tuyen-dai-hoc-nam-2025.html
- Không tự gán cùng một điểm cho A00/A01/K01 khi nguồn chỉ công bố một mức chung. Năm 2025 của HNUE dùng phương thức điểm xét quy đổi chung và được phân biệt với THPT các năm trước.

**Cách công bố:** Tại `admin.php?section=admissions` tải file CSV lên, xem trước 40 dòng, nhấp từng URL nguồn và chỉ duyệt sau khi kiểm tra. Trong PR này, CSV **không tự động nhập** vào database production.

## 2. Học phí theo nhóm chương trình (8 khoảng, tự seed an toàn)
- Bổ sung bảng `institution_tuition_bands` riêng để lưu khoảng (minimum, maximum), theo `institution_id / academic_year / program_group / charge_basis`, link nguồn và ngày kiểm chứng.
- ĐHBK Hà Nội 2024–2025, đơn vị học kỳ: chuẩn 12–15 triệu; Elitech 16,5–21 triệu; song bằng/hợp tác quốc tế 22,5–29 triệu; riêng IT-E10 và EM-E14 32–33,5 triệu.
- ĐHBK Hà Nội 2025–2026, đơn vị học kỳ: chuẩn 14–17,5 triệu; Elitech 17,5–22,5 triệu; song bằng/hợp tác quốc tế 25,5–30 triệu; riêng IT-E10 và EM-E14 32–33,5 triệu.
- **Nguồn chính thức:** https://smse.hust.edu.vn/vi/news/tin-bai/diem-chuan-dai-hoc-bach-khoa-ha-noi-2024-118.html ; https://hust.edu.vn/vi/news/hoat-dong-chung/dai-hoc-bach-khoa-ha-noi-cong-bo-diem-chuan-xet-tuyen-dai-hoc-nam-2025-655575.html
- Không biến **khoảng theo học kỳ** thành học phí **chính xác của ngành** hoặc **tổng học phí năm**.
- Biểu đồ bảng thể hiện tăng trưởng **cận dưới/cận trên** giữa cùng nhóm và cùng đơn vị tính của hai năm liên tiếp; không áp một tỷ lệ cho mọi sinh viên.
- Không ghi học phí theo tín chỉ, số tín chỉ hoặc mức tăng bắt buộc của trường nếu chưa có nguồn cụ thể.

## 3. Kiểm thử và giới hạn
- `tests/admissions-p2-smoke.php` kiểm thử migration P0/P2/P2.2 lặp lại, seed idempotent, 8 khoảng học phí, phép tính tăng, import CSV 40 dòng, duyệt transaction và lưu từng URL nguồn.
- CI chạy PHP 8.1/8.3 với SQLite. Cần kiểm tra thêm MySQL/MariaDB thực tế và giao diện mobile.
- Chưa mở rộng tới 20 trường: chất lượng chứng cứ được ưu tiên hơn số lượng. Các trường khác vẫn hiển thị “Chưa có dữ liệu xác thực” cho học phí và điểm chuẩn.
- Dữ liệu năm 2023–2025 chỉ có giá trị **lịch sử**, không được coi là điểm dự đoán năm 2027.

## 4. Quy trình staging
1. Sao lưu DB staging, chạy nhánh P2.2; trang tra cứu một hồ sơ `BKA` sẽ hiện phần khoảng học phí 2024–2026.
2. Upload file CSV tại Admin, kiểm tra 40 hàng, mở nguồn công bố, rồi duyệt.
3. Mở `index.php?page=university-explorer` và `index.php?page=university-compare` để kiểm tra điểm chuẩn và so sánh.
4. Kiểm thử không trùng dữ liệu sau lần chạy migration tiếp theo.
