# Kiểm tra gói v1

Thực hiện ngày 22/11/2026, trước khi tạo PR. Phạm vi thay đổi chỉ nằm trong `data/official_sources_v1/`.

- Validator offline đạt: 27 tệp dữ liệu, hash khớp manifest, không trùng mã nghề/chương trình/khóa task/khóa interest, các tham chiếu SOC/element/scale tồn tại, giá trị interest trong thang nguồn.
- 1.016 nghề; 18.838 nhiệm vụ; 8.307 dòng interest; 3.006 mục content model; 33 thang đo.
- HUST: đúng thứ tự 68 dòng nguồn; mã chương trình không trùng; tổng chỉ tiêu dự kiến 9.880; trang PDF và dòng nguồn lưu theo bản ghi.
- 7 kiểm thử validator đạt: gói hợp lệ, file hỏng, chương trình trùng, thêm điểm chuẩn không có nguồn, gán mã chương trình thành mã ngành, nguồn không tồn tại, xuất bản trước kiểm duyệt.
- Tái tạo từ cùng cache nguồn thành công; script kiểm tra hash ghim trước khi đọc nguồn.
- Chỉ có dữ liệu, tài liệu, công cụ tạo/kiểm tra gói; không chạy PHP, migration hay importer vào database. Chưa thực hiện kiểm thử tích hợp ứng dụng vì importer cho gói này chưa được triển khai.

Lệnh tái kiểm tra:

```bash
python3 data/official_sources_v1/tools/validate.py
python3 data/official_sources_v1/tools/test_validate.py
```

Giới hạn: chưa bổ sung điểm chuẩn/học phí; chưa bao phủ các trường khác; chưa ánh xạ ngành quốc gia; chưa chuẩn hóa hệ số môn và điều kiện tuyển sinh HUST; chưa dịch/kiểm duyệt bối cảnh Việt Nam cho O*NET. Không coi kiểm tra cấu trúc đạt là kiểm duyệt nội dung toàn bộ dữ liệu.
