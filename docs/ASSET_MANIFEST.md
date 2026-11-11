# ASSET MANIFEST — JOBSIMU 3D (UNITY VERTICAL SLICE LỚP 8A)

> **Dự án:** JOBSIMU 3D — Một tiết Khoa học tự nhiên Lớp 8A  
> **Runtime chính:** Unity 6 (`JobSimu3D/`), URP Forward (Mobile/PC presets), góc nhìn thứ nhất  
> **Cập nhật:** 10/11/2026

---

## 1. TRẠNG THÁI ASSETS HIỆN TẠI (BLOCKOUT CÓ TƯƠNG TÁC)

Toàn bộ tài sản hình học và âm thanh trong nhánh hiện tại được dựng trực tiếp bằng mã C# Unity/URP và âm thanh tổng hợp trong repository, phục vụ kiểm thử gameplay và không gian:

- **Không gọi hình khối blockout là art hoàn chỉnh:** Các nhân vật học sinh và giáo viên là mô hình khối đơn giản có khớp tay/chân/đầu và cử động procedural (hoặc tư thế tĩnh khi bật *Giảm chuyển động*).
- **Chưa nhập asset thương mại bên ngoài:** Không có file `.glb`/`.fbx` ngoài repo cần mua giấy phép.
- **Font tiếng Việt:** `JobSimu3D/Assets/_JobSimu/Resources/JobSimuUI.ttf` (DejaVu Sans, kèm giấy phép `DejaVu-LICENSE.txt`, sao chép thành `ThirdPartyNotices.txt` khi build WebGL).

---

## 2. DANH MỤC KHÔNG GIAN & ĐẠO CỤ PHỤC VỤ GAMEPLAY

### 2.1. Không gian trường THCS Bình Minh (thu gọn đúng khu vực cần chơi)
- **Sân trường & Cổng trường:** Cổng trường mang biển `TRƯỜNG THCS BÌNH MINH`, cột cờ, lối đi, Cô Mai và khu vực **Vũng nước mẫu sân trường** (đối chiếu bề mặt gạch lát vs đất).
- **Phòng giáo viên:** Bàn đọc bài làm tiết trước (chứa phiếu mẫu đọc được), bàn kiểm tra khay mẫu & máy chiếu, máy in phiếu/ảnh, máy chiếu chuẩn bị slide, bộ thẻ & khay mẫu cho 4 nhóm.
- **Hành lang kết nối:** Hành lang nối Phòng giáo viên và Lớp 8A qua cửa trượt an toàn (`SlidingDoor`). Lớp 8B và cầu thang tầng 2 được khóa/rào để tập trung đúng không gian tiết học.
- **Lớp 8A:**
  - Bảng phấn cập nhật nội dung thật theo từng bước (câu hỏi mở đầu, sơ đồ dòng thấm, điều chỉnh cuối tiết).
  - Màn chiếu và đèn báo máy chiếu (chuyển trạng thái rõ khi máy chiếu hoạt động hoặc ngắt lỗi).
  - Bàn giáo viên kèm khay mẫu thử đất/gạch và góc thu phiếu cuối tiết.
  - **4 cụm bàn nhóm** (`Nhóm 1`–`Nhóm 4`), mỗi cụm có 3 ghế, 3 học sinh ngồi đúng ghế, phiếu bài làm và khay mẫu trên bàn đổi trạng thái quan sát được theo tiến trình tiết học.

### 2.2. Hệ thống âm thanh tổng hợp (`SimulationAudio`)
- Âm nền nhẹ trường học, nhạc nền tùy chỉnh âm lượng độc lập, cue tương tác, cue chuông vào/hết tiết học (tổng hợp bằng sóng âm nguyên bản trong mã nguồn, chưa phải bản thu môi trường thực tế).

---

## 3. HẠNG MỤC PLACEHOLDER CẦN THAY THẾ Ở GIAI ĐOẠN ART SAU NÀY

1. Thay khối nhân vật blockout bằng nhân vật rigged/textured có nguồn giấy phép rõ ràng (giữ tỷ lệ phù hợp bàn ghế THCS).
2. Thay bảng phấn/giấy/mẫu đất-gạch khối hộp bằng mesh chi tiết và texture đọc nét hơn khi được duyệt ngân sách art.
3. Thay âm thanh tổng hợp bằng bản thu trường học/trống trường có giấy phép hợp lệ.
4. Đo profiler thực tế (draw calls, bộ nhớ WebGL, FPS) trên thiết bị mục tiêu sau khi nâng cấp art.
