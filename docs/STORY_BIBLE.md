# STORY BIBLE — JOBSIMU 3D: MỘT TIẾT HỌC LỚP 8A

> **Dự án:** JOBSIMU 3D (Vertical Slice — Một tiết Khoa học tự nhiên Lớp 8A)  
> **Tài liệu kiến trúc hiện hành:** [`docs/TEACHER_8A_REDESIGN.md`](file:///home/minhphuc/jobsimu/docs/TEACHER_8A_REDESIGN.md)  
> **Phiên bản:** 2.0 — 10/11/2026

---

## 1. BỐI CẢNH & KHÔNG GIAN HỌC ĐƯỜNG

- **Tên trường:** Trường THCS Bình Minh (trường THCS hư cấu tại Việt Nam).
- **Nhân vật người chơi:** Giáo viên mới nhận lớp, phụ trách tiết **Khoa học tự nhiên — Lớp 8A** (45 phút: `07:30–08:15`, kèm giai đoạn chuẩn bị `07:10–07:30`).
- **Nội dung tiết học:** Điều tra vì sao một số vị trí trong sân trường đọng nước sau mưa, thông qua quan sát và so sánh mẫu (đất trồng tơi xốp, gạch/bê tông lát kín, lối đất nén chặt).
- **Quy mô không gian phục vụ gameplay:**
  1. **Sân trường & Cổng trường:** Cô Mai đón trao đổi đầu giờ, có vũng đọng nước mẫu ngoài sân để quan sát trước khi vào lớp.
  2. **Phòng giáo viên:** Bàn đọc bài tiết trước của lớp 8A, bàn kiểm tra bộ mẫu & thiết bị, máy in phiếu/ảnh, máy chiếu để soạn slide, bộ thẻ & khay mẫu theo nhóm.
  3. **Hành lang nối:** Nối phòng giáo viên và dãy phòng học (Lớp 8A mở cửa vào chơi, Lớp 8B khóa giữ bố cục).
  4. **Lớp 8A:** 4 cụm bàn (12 học sinh: 3 em/nhóm), bảng phấn viết được nội dung theo từng pha, màn chiếu & đèn báo máy chiếu, khay mẫu vật giáo viên và góc nộp phiếu cuối tiết.

---

## 2. NHÂN VẬT & TRẠNG THÁI KHỞI ĐẦU THEO TÌNH HUỐNG

*(Lưu ý: Mô tả dưới đây là trạng thái khởi đầu trong tiết học này, không phải nhãn cố định về năng lực, tính cách hay sức khỏe tâm lý của học sinh.)*

### 2.1. Giáo viên hướng dẫn
- **Cô Mai:** Giáo viên hướng dẫn chuyên môn. Chỉ biết những gì có cơ sở thực tế từ tuần trước (thói quen hay đoán nhanh trước khi đối chiếu mẫu của một số học sinh, cơ cấu 4 nhóm lớp 8A, tình trạng máy chiếu lớp 8A có dấu hiệu chập chờn). Trong pha phản tư, cô đối chiếu lại những sự kiện thực sự đã xảy ra trong tiết học.

### 2.2. 12 Học sinh Lớp 8A chia thành 4 nhóm

1. **Nhóm 1 — Linh (trọng tâm), An, Bình:**
   - *Linh:* Hiểu quan sát và ghi bảng so sánh khá cẩn thận trên giấy, nhưng còn dè dặt phát biểu trước cả lớp. Không được gán "không hiểu bài" chỉ vì giữ im lặng.
   - *An, Bình:* Quan sát cùng nhóm; phản ứng theo sự dẫn dắt và mức an toàn khi giáo viên trao đổi tại bàn.
2. **Nhóm 2 — Nam (trọng tâm), Vy, Khánh:**
   - *Nam:* Đưa ra giả thuyết nhanh ("bê tông không thấm nên đọng nước"), nhưng có xu hướng thiếu kiểm chứng lượng nước bằng nhau hoặc bỏ qua đất nén.
   - *Vy, Khánh:* Ghi chú theo Nam nếu chưa được gợi ý đối chiếu mẫu.
3. **Nhóm 3 — Hà (trọng tâm), Trang, Đức:**
   - *Hà:* Diễn đạt rất tốt qua hình vẽ cắt lớp hạt đất và bề mặt lát kín, nhưng cần gợi ý để chuyển hình vẽ thành lời giải thích rõ ràng. Nhóm hoàn thành nhiệm vụ cơ bản sớm.
   - *Trang, Đức:* Làm xong phần bảng cơ bản sớm cùng Hà; nếu bị bỏ mặc quá lâu sau khi hoàn thành sẽ giảm tập trung, nhưng không bị phạt ngay khi giáo viên đang hỗ trợ nhóm khác.
4. **Nhóm 4 — Minh (trọng tâm), Hùng, Thảo:**
   - *Minh:* Có thể làm sai ngay từ đầu vì hiểu nhầm yêu cầu ("chọn chỗ bẩn/lá rụng") hoặc thiếu kiến thức nền về sự thấm nước. Giáo viên cần đọc bài cũ hoặc hỏi làm rõ để tìm hiểu nguyên nhân trước khi chọn cách hỗ trợ, chia nhỏ nhiệm vụ/minh họa rồi quay lại kiểm tra bằng ví dụ mới.
   - *Hùng, Thảo:* Bị cuốn theo hướng hiểu nhầm ban đầu của nhóm cho đến khi có can thiệp phù hợp.

---

## 3. NGUYÊN TẮC SƯ PHẠM BẤT BIẾN

1. Không hiển thị các thanh điểm bí mật (như "thấu cảm 80", "hiểu bài 60") trên đầu nhân vật.
2. Người chơi phải suy luận trạng thái học sinh từ lời nói, bài làm trên bàn và hành vi quan sát được.
3. Không coi hoàn tất hoạt ảnh hay lời thông báo "học sinh đã hiểu" là bằng chứng hiểu bài; kết quả phải thể hiện qua bài làm sửa lại, lời giải thích khác đi và kết quả kiểm tra lại bằng ví dụ mới.
4. Báo cáo không kết luận "phù hợp/không phù hợp nghề" và không quy chụp nguyên nhân tâm lý khi dữ liệu còn thiếu.
