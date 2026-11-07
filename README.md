# Hướng Nghiệp 360

Website định hướng nghề nghiệp hoàn chỉnh cho học sinh, tương thích PHP 7.4+, MySQL 5.7+/MariaDB và Tailwind CSS production build, thiết kế để upload trực tiếp lên cPanel.

## Chức năng

- Đăng ký, đăng nhập, hồ sơ học sinh và phân quyền quản trị.
- Trắc nghiệm RIASEC 30 câu, 5 mức độ, hiển thị và tự động lưu tiến độ trên thiết bị.
- Chấm 6 nhóm Holland, mã 3 chữ, ghép và xếp hạng nghề theo trọng số.
- Tư vấn cá nhân hóa tích hợp trực tiếp bằng OpenAI Responses API + Structured Outputs; có trạng thái tải, tạo lại tư vấn, nhật ký và nội dung dự phòng khi API gặp lỗi.
- Thư viện 19 nhóm nghề: 15 hồ sơ mẫu có mô phỏng và 4 hồ sơ bổ sung về quốc phòng, an ninh, điều dưỡng, công tác xã hội.
- Phòng mô phỏng tích hợp cho 15 nghề: 60 tình huống ra quyết định, phản hồi tức thì, điểm trải nghiệm và 5 năng lực nghề nghiệp.
- Lưu lịch sử chơi, điểm tốt nhất và báo cáo từng quyết định; vẫn hỗ trợ link tài nguyên ngoài cho từng nghề.
- Lưu nghề yêu thích, lịch sử các lần đánh giá và xem lại kết quả.
- Admin Panel: thống kê, CRUD nghề, sửa link ngoài, bật/tắt từng game, theo dõi lượt chơi/điểm, CRUD câu hỏi, khóa/mở học sinh, xem kết quả, cấu hình OpenAI và bật/tắt trắc nghiệm.
- Trang Health Check kiểm tra môi trường, database, 30 câu, 15 nghề, HTTPS và OpenAI.
- CSRF, prepared statement, `password_hash`, session cookie HttpOnly/SameSite, audit log và kiểm tra quyền.

## Cài trên cPanel

1. Nén toàn bộ nội dung dự án thành ZIP và upload vào `public_html` (hoặc thư mục con), sau đó Extract.
2. Trong cPanel > **MySQL Databases**, tạo database, database user và cấp **ALL PRIVILEGES** cho user đó.
3. Mở `https://ten-mien-cua-ban/setup.php`.
4. Nhập thông tin MySQL và tạo tài khoản quản trị. Bộ cài tạo bảng, 30 câu hỏi, 15 nghề mẫu; lần chạy đầu bổ sung 4 hồ sơ ngành mới.
5. Đăng nhập Admin Panel > **Cấu hình** để nhập OpenAI API key và model.
6. Mở **Admin > Kiểm tra hệ thống** và xử lý cảnh báo production.
7. Xóa hoặc đổi tên `setup.php` sau khi cài xong.

Nếu bộ cài không ghi được `config.php`, bạn có thể sao chép `config.example.php` thành `config.php` (hoặc sao chép `.env.example` thành `.env`) và điền thông tin kết nối CSDL thủ công.

> **Cảnh báo bảo mật quan trọng:** File `config.php` và `.env` đã được cấu hình trong `.gitignore` để không bao giờ đưa lên kho lưu trữ Git công khai. Luôn giữ `debug => false` trên môi trường production.

## Cấu hình OpenAI

Vào **Admin Panel > Cấu hình hệ thống**:

- API key: khóa riêng của dự án OpenAI.
- Model mặc định: `gpt-5-mini` (admin có thể đổi).

Hosting cần bật PHP cURL và cho phép kết nối HTTPS ra ngoài. API key không xuất hiện ở phía trình duyệt. Nếu API không khả dụng, học sinh vẫn nhận đầy đủ điểm, nghề phù hợp và tư vấn dự phòng.

## Cấu trúc

```text
app/             Bootstrap, hàm nghiệp vụ, layout
assets/          CSS/JavaScript
database/        Schema và dữ liệu mẫu
admin.php        Admin Panel
index.php        Website học sinh
setup.php        Bộ cài cPanel
health.php       Kiểm tra môi trường production
config.example.php Mẫu cấu hình an toàn (track trên Git)
.env.example     Mẫu biến môi trường (track trên Git)
config.php       File cấu hình thực tế (nằm trong .gitignore, không đưa lên Git)
```

## Kiểm tra nhanh trước khi đưa vào dùng

- Tạo một học sinh và làm đủ bài trắc nghiệm.
- Kiểm tra kết quả, nghề yêu thích và lịch sử.
- Chơi trọn một game, kiểm tra phản hồi, báo cáo năng lực và lịch sử tại Phòng mô phỏng.
- Trong Admin → Mô phỏng nghề, kiểm tra thống kê và bật/tắt một game; link ngoài vẫn sửa tại mục Nghề nghiệp.
- Thử API OpenAI và kiểm tra quota/billing của tài khoản API.
- Bật HTTPS trên domain; ưu tiên PHP 8.1 trở lên, đồng thời tương thích PHP 7.4 trên hosting cũ.

## Phát triển giao diện Tailwind

Website deploy bằng file CSS đã build sẵn `assets/css/app.css`, cPanel không cần Node.js. Khi chỉnh giao diện ở máy phát triển:

```bash
npm install
npm run build:css
```

Kết quả hướng nghiệp chỉ mang tính tham khảo, không phải chẩn đoán tâm lý hay quyết định thay học sinh.

## Luồng người dùng

Sau khi đăng ký, học sinh làm trắc nghiệm Holland (RIASEC), rồi chuyển sang khảo sát điều kiện học tập và cách làm việc. Khảo sát hỏi khoảng ngân sách học phí, lộ trình học ưu tiên, thời gian đào tạo, mức vận động mong muốn, một số câu tự đánh giá thể lực và mức sẵn sàng luyện năng khiếu/portfolio. Học sinh có thể bỏ qua phần này.

Kết quả ghép sở thích RIASEC với nhóm ngành và lộ trình tham khảo của 19 hồ sơ nghề. Điều kiện chỉ điều chỉnh thứ tự và tạo gợi ý chuẩn bị; hệ thống không loại nghề cứng dựa trên ngân sách hay thể lực tự khai. Số lần chống đẩy/kéo xà và quãng chạy chỉ dùng để tự nhìn lại, không được lưu cùng kết quả. Các bậc chi phí trong dữ liệu chỉ là ước lượng tương đối, không phải học phí cập nhật của trường. Hãy xác minh học phí, học bổng, sơ tuyển và tiêu chuẩn sức khỏe ở thông báo chính thức của từng trường/ngành.

Tên nhóm ngành tham khảo theo [danh mục ngành nghề Hướng Nghiệp Việt](https://huongnghiepviet.com/nganh-nghe). Danh mục này dùng để phân loại và khám phá ngành, không cung cấp căn cứ về chi phí hoặc điều kiện tuyển sinh.
