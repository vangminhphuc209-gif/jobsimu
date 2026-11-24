# Quy Tắc Bảo Toàn Dữ Liệu & Ranh Giới An Toàn (Data Preservation Manifesto)

---

## 1. NGUYÊN TẮC BẤT KHẢ XÂM PHẠM

Hệ thống đang phục vụ dữ liệu thực tế gồm **241 người dùng** và **284 bài đánh giá**. Mọi thao tác mã nguồn và CSDL phải tuân thủ:

1. **Tuyệt đối không chạy câu lệnh phá hủy**: Không bao giờ chạy `DROP TABLE`, `TRUNCATE`, `DELETE FROM users`, `DELETE FROM assessments` trên CSDL chính.
2. **Không tự động chạy script xóa tài khoản**: Dù repository có tệp `scripts/cleanup-grade-6-9-students.php`, tuyệt đối KHÔNG tự ý thực thi trên cơ sở dữ liệu thật khi chưa có phê duyệt chính thức từ người dùng (Human Approval Gate).
3. **Bảo toàn điểm thô và dữ liệu lịch sử**:
   - Mọi bản ghi `assessments` cũ giữ nguyên câu trả lời JSON và điểm thô ban đầu.
   - Không được âm thầm tính lại hay ghi đè kết quả quá khứ làm thay đổi hồ sơ của học sinh.
   - Khi áp dụng công thức mới hoặc nhãn mới, hệ thống phải xử lý tương thích ngược: nếu thiếu dữ liệu mới, hiển thị rõ "kết quả phiên bản trước", cho phép học sinh bổ sung tự nguyện mà không bắt buộc làm lại từ đầu.
4. **Không làm đứt gãy liên kết ID**: Giữ nguyên toàn bộ ID của `careers`, `training_majors`, `institutions`, `programs`.
5. **Cách ly kiểm thử**: Kiểm thử tự động phải chạy trên cơ sở dữ liệu tạm thời (SQLite `:memory:` hoặc fixture riêng), không ghi dữ liệu rác vào CSDL mặc định.

---

## 2. BẢN ĐỒ ÁNH XẠ DỮ LIỆU & QUY TRÌNH HỢP NHẤT KẾ HOẠCH (PHASE 7 PREVIEW)

- Hiện tại bảng `user_roadmaps` đang có 5 bản ghi thực tế của học sinh.
- Bảng `career_action_plans` có 0 bản ghi.
- Quyết định: Giữ nguyên `user_roadmaps` và `user_roadmap_milestones` làm cơ sở lưu trữ kế hoạch tháng chính thức; module `plan.php` sẽ được tích hợp hoặc trỏ vào nguồn dữ liệu này để không sinh ra hệ thống thứ ba.
