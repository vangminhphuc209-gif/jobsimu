# JOBSIMU — Quy trình nhập dữ liệu tuyển sinh P2

## Hướng dẫn thao tác
1. Trên bản thử nghiệm có sao lưu CSDL, đăng nhập tài khoản quản trị và mở `admin.php?section=admissions`.
2. Chuẩn bị CSV UTF-8 (tối đa 2 MB, 500 dòng) theo mẫu `docs/admissions-p2-template.csv`.
3. Mỗi dòng phải có nguồn thông tin chính thức **cụ thể cho năm, ngành, chương trình**. Truy cập nguồn, kiểm chứng nội dung trước khi tải lên; trang hệ thống không tự đọc hay xác minh website nguồn.
4. Tải lên -> xem trước mọi dòng -> duyệt hoặc từ chối. Chỉ sau khi duyệt thành công trong một transaction, dữ liệu mới hiển thị công khai.
5. Mở `index.php?page=university-explorer` hoặc `index.php?page=university-compare` để kiểm tra dữ liệu.

## Cột CSV (bắt buộc có tiêu đề, cho phép giá trị trống tùy loại)
`type,institution_code,institution_name,province,website,major_code,major_name,program_name,program_code,year,method,combination,candidate_group,score,score_scale,academic_year,charge_basis,amount_vnd,source_url,source_title`

- `type`: `cutoff` hoặc `tuition`.
- `institution_code`: mã trường, định danh ổn định (vd BKA). Nếu đã có trường, tên và tỉnh không bị cập nhật tự động.
- `major_code`: mã ngành; nếu không có trong DB, ngành được tạo mới. Tên ngành cũ không bị ghi đè.
- `program_name`: chính xác tên chương trình; không gộp chuẩn/tài năng/quốc tế.
- `year`: năm tuyển sinh 2010 đến năm kế tiếp; dùng khi `type=cutoff`.
- `method`: tên phương thức xét tuyển theo văn bản (vd Điểm thi tốt nghiệp THPT). Bắt buộc cho cutoff.
- `combination`: tổ hợp **chỉ khi điểm được công bố riêng theo tổ hợp**. Nếu trường công bố một điểm chung cho các tổ hợp, để trống.
- `candidate_group`: nhóm thí sinh/khu vực/giới/đối tượng khi điểm được công bố riêng.
- `score`: số điểm với tối đa 3 chữ số thập phân; chỉ cho cutoff.
- `score_scale`: thang điểm tương ứng, vd `30 điểm`, `100 điểm`. Phải ghi rõ quy tắc quy đổi nếu có trong nguồn.
- `academic_year`: dạng `2025-2026`; dùng cho tuition.
- `charge_basis`: `credit`, `semester`, `academic_year`, `whole_course`, `exempt`.
- `amount_vnd`: số nguyên VND, không dấu phân cách. Chỉ điền số 0 khi **có nguồn** xác nhận miễn học phí.
- `source_url`, `source_title`: link và tên thông báo gốc, **không dùng trang tổng hợp chỉ nói chung chung**.
- `website`: URL chính thức của trường (nếu có).

## Quy tắc an toàn dữ liệu
- Không tự suy ra điểm chuẩn 2026 từ 2025; không điền học phí khi chưa có văn bản.
- Chỉ cập nhật dữ liệu lịch sử bằng bản đã đối chiếu nguồn và duyệt thủ công.
- Việc duyệt lô nhập ghi cả thông tin người duyệt, thời điểm và `admission_source_documents`.
- Từ chối lô không làm thay đổi dữ liệu public; lỗi giữa chừng sẽ rollback.
- Công cụ so sánh nhóm điểm theo năm, phương thức, thang điểm, tổ hợp, nhóm thí sinh; không trực tiếp so học phí theo tín chỉ với học phí năm.
- Chỉ tính tỷ lệ tăng học phí khi có hai năm học liên tiếp, cùng cơ sở tính, số năm trước > 0.
- Nên chuẩn hóa mã trường và ngành từ nguồn cơ quan quản lý trước khi nhập lô lớn.
- **Đối chiếu định kỳ**: điểm chuẩn, học phí và điều kiện phải kiểm tra lại với đề án của năm dự tuyển.

## Kiểm thử
GitHub Actions `.github/workflows/admissions.yml` chạy `php -l` và SQLite integration smoke cho P0–P2 trên PHP 8.1/8.3.
Ngoài CI, cần test staging trên MySQL 5.7/MariaDB và xác minh giao diện, CSRF, upload, quyền admin.

## Giới hạn P2 lần này
Đã có công cụ nhập/duyệt và trang so sánh. **Chưa tự động thu thập dữ liệu tất cả trường.**
Điều kiện sức khỏe/sơ tuyển và lịch tuyển sinh đã có bảng trong P1 nhưng chưa có workflow import CSV riêng.
Chưa có kiểm chứng độc lập nội dung URL, trích xuất PDF hay đánh giá độ tin cậy tài liệu bằng AI.

Không triển khai thẳng lên production trước khi chạy CI và rà soát migration.
