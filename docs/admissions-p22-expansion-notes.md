# JOBSIMU — P2.2 mở rộng dữ liệu 2025–2026

Ngày đối chiếu: 09/11/2026. Đây là dữ liệu tham khảo lịch sử theo nguồn, không phải dự đoán tuyển sinh 2027.

## Bộ CSV mới: 40 bản ghi

`docs/admissions-p22-expansion-2025-2026.csv` gồm:

- **10** điểm chuẩn tuyển sinh 2026, phương thức 100, Trường Đại học Y Hà Nội: Y khoa, Y khoa phân hiệu Thanh Hóa, Y học dự phòng, Y học cổ truyền, Răng–Hàm–Mặt, Điều dưỡng, Điều dưỡng Thanh Hóa, Hộ sinh, Dinh dưỡng, Kỹ thuật phục hình răng. Nguồn: https://hmu.edu.vn/vn/tID7444_thong-bao-diem-trung-tuyen-dot-1-tuyen-sinh-dai-hoc-he-chinh-qui-cua-truong-dai-hoc-y-ha-noi-nam-2026.html
- **20** điểm chuẩn quy đổi 2026, Đại học Kinh tế Quốc dân, trong đó có Kế toán, Kiểm toán, Marketing, CNTT, Khoa học máy tính, An toàn thông tin, Logistics, Quản trị kinh doanh và các chương trình riêng biệt. Nguồn: https://fit.neu.edu.vn/post/diem-chuan-dai-hoc-chinh-quy-2026-toan-bo-nganh
- **8** điểm chuẩn 2025 của Học viện Cảnh sát nhân dân phân chia giới tính nam/nữ và Vùng 1/2/3/8. **2** điểm chuẩn 2026 phân chia nam/nữ, *không* tự suy ra mức điểm từng vùng khi nguồn 2026 chỉ nêu nam/nữ. Nguồn: https://hvcsnd.edu.vn/thong-bao-diem-trung-tuyen-phuong-thuc-2-va-3-xet-tuyen-dai-hoc-chinh-quy-hoc-vien-canh-sat-nhan-dan-nam-2025-13298 và https://hvcsnd.edu.vn/thong-bao-diem-trung-tuyen-phuong-thuc-2-va-3-xet-tuyen-dai-hoc-chinh-quy-hoc-vien-canh-sat-nhan-dan-nam-2026-14159

Mã trường: YHB (ĐH Y Hà Nội), KHA (ĐH Kinh tế Quốc dân), CSH (Học viện CSND); mã 7860100 = Nghiệp vụ Cảnh sát. Mã ngành (major_code) khác mã xét tuyển chương trình (program_code); riêng phân hiệu Thanh Hóa và chương trình EP được giữ trong program_code/name để tránh gộp sai.

**Dữ liệu CSV được đặt trong repository để quản trị viên tải lên, xem trước, đối chiếu và duyệt thủ công.** Không tự công bố bảng điểm chuẩn mới lên production.

## Quân đội: không nhập số liệu ước lượng

- Thêm hồ sơ Học viện Kỹ thuật Quân sự (mã KQH), theo mã trường từ văn bản Bộ Quốc phòng.
- Bảng `institution_admission_notices` mới lưu lịch sử năm, nguồn, đơn vị phụ trách, hệ tuyển sinh (military / civilian / police / general) và nhóm thông báo.
- Hệ quân sự 2026: cần sơ tuyển theo quy định Bộ Quốc phòng. Mốc lịch sử 10/2–15/4/2026. Nguồn: https://mod.gov.vn/wcm/connect/mod/sa-mod-site/sa-ttsk/sa-tt-qpan/them-phuong-thuc-xet-tuyen-moi-cho-ky-tuyen-sinh-quan-su-nam-2026
- Hệ dân sự của Học viện Kỹ thuật Quân sự năm 2026 có phương thức riêng, không tự động bị gắn sơ tuyển quân sự. Nguồn: https://www.qdnd.vn/giao-duc-khoa-hoc/nha-truong-quan-doi/tuyen-sinh-dan-su-nam-2026-cua-hoc-vien-ky-thuat-quan-su-co-gi-can-chu-y-1036679
- Học viện Cảnh sát nhân dân: lưu ghi chú yêu cầu sơ tuyển và điều kiện đặc thù 2026, nhưng không dùng kết quả tự khai để kết luận đủ điều kiện. Nguồn: https://www.hvcsnd.edu.vn/thong-tin-cap-nhat-bo-sung-ve-tuyen-sinh-trinh-do-dai-hoc-tai-hoc-vien-csnd-nam-hoc-2026-2027-13930

## Tích hợp
- `app/bootstrap.php`: tạo bảng notice nếu thiếu, seed idempotent.
- `app/university-explorer.php`: hiển thị thông báo và các thời hạn lịch sử trên trang trường.
- `admin.php?section=admissions`: thêm link tải bộ CSV mới.
- Smoke test: kiểm tra nhập toàn bộ CSV 40 dòng, duyệt transaction, 10 nhóm điểm CSH không bị gộp, bản ghi nguồn và migration lặp lại.

## Cần thực hiện trước khi merge
1. Chạy GitHub Actions PHP 8.1/8.3; kiểm tra thêm MySQL/MariaDB staging.
2. Kiểm tra lại từng nguồn, nhất là cách diễn giải nhóm điểm quy đổi và hệ Công an.
3. Kiểm tra lặp lại migration trên DB có dữ liệu thật, backup trước khi thử.
4. Duyệt CSV ở Admin trên staging rồi xác nhận bảng điểm / nguồn / phân hệ trước khi công bố.
5. **Chưa có học phí 2026–2027 có thể so sánh cho các trường mới trong bộ này:** phải để trống, không tự ước lượng.
