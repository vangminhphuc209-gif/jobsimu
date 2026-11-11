# AGENTS.md — JOBSIMU 3D: NGƯỜI THẦY SAU TIẾNG TRỐNG TRƯỜNG

> **Tài liệu thiết kế chính hiện hành:** [`docs/TEACHER_8A_REDESIGN.md`](file:///home/minhphuc/jobsimu/docs/TEACHER_8A_REDESIGN.md) (cập nhật định hướng từ GDD gốc [`docs/game3d.md`](file:///home/minhphuc/jobsimu/docs/game3d.md))  
> **Repository:** `vangminhphuc209-gif/jobsimu`  
> **Mục tiêu ưu tiên:** Hoàn thiện **Vertical Slice — Một tiết Khoa học tự nhiên Lớp 8A (Trường THCS Bình Minh)** bằng **Unity 6 (`JobSimu3D/`)** góc nhìn thứ nhất, xác định và kiểm thử được.

---

## 1. NGUYÊN TẮC KIẾN TRÚC BẤT BIẾN (ARCHITECTURAL INVARIANTS)

Mọi Agent (Orchestrator và Subagents) **BẮT BUỘC** tuân thủ các nguyên tắc bất biến sau:

1. **Bảo toàn ứng dụng PHP Legacy & Bản chuyển tiếp Babylon.js:**
   - **KHÔNG** viết lại hoặc phá vỡ cấu trúc hiện tại của website Hướng Nghiệp 360 (`index.php`, `app/bootstrap.php`, `app/functions.php`, `app/simulations.php`, `app/simulation-controller.php`, `app/simulation-3d.php`).
   - Giữ `game/` (Babylon.js) như bản cũ trong giai đoạn chuyển tiếp; **KHÔNG** xóa `game/` và **KHÔNG** tự ý trỏ cổng website sang Unity khi chưa có phê duyệt riêng.
   - Giữ nguyên khóa fail-closed của `api/game/*` (`503 GAME_SYNC_UNAVAILABLE`); **KHÔNG** mở lại API game cũ đang bị khóa và **KHÔNG** sửa đổi bảng cũ trong `database/schema.sql`.

2. **Unity `JobSimu3D/` là bản game 3D chính:**
   - Toàn bộ mã nguồn và scene 3D chính nằm trong `JobSimu3D/` (Unity 6 URP, New Input System, góc nhìn thứ nhất), scene vận hành chính là `JobSimu3D/Assets/_JobSimu/Scenes/SharedFrameworkDemo.unity`.
   - Chưa mở rộng sang nghề khác (Lập trình viên, Bác sĩ, Công an chỉ giữ định danh) và chưa xây cả ngày làm việc trước khi một tiết học 45 phút đạt nghiệm thu.

3. **Gameplay xác định & Không dùng LLM trong vòng chơi:**
   - Toàn bộ trạng thái học sinh, đánh giá năng lực, chuyển giai đoạn và điều khiển nhiệm vụ chạy xác định (deterministic) trong C# (`ScenarioSession`), có thể replay và kiểm thử tự động.
   - Tuyệt đối không dùng `eval()`, không dùng LLM quyết định trạng thái học sinh, chấm năng lực hay điều phối nhiệm vụ.

4. **Lưu cục bộ trung thực & chống giả mạo:**
   - Tiến độ và phản tư lưu cục bộ trên thiết bị (`LocalSaveStore`), ghi rõ chưa đồng bộ tài khoản.
   - Không dùng dữ liệu lưu do client tự khai làm bằng chứng năng lực được server xác thực; luôn bảo toàn bản lưu lỗi (`.corrupt`), không ghi đè bằng phiên trống hay autosave.

5. **Không dùng chỉ số đánh giá đơn lẻ (No Single-Score Metric):**
   - Tuyệt đối cấm kết luận kiểu "Bạn phù hợp nghề giáo viên X%" hoặc "Bạn không phù hợp nghề", và không hiển thị chỉ số bí mật (như "thấu cảm 80", "hiểu bài 60") trên đầu nhân vật.
   - Báo cáo cuối tiết mang tên **"Hồ sơ trải nghiệm nghề giáo viên — phiên thử nghiệm"**, tách rõ dữ liệu quan sát, tự báo cáo của người chơi và suy luận hệ thống giới hạn trong phiên đó, kèm giải thích thay thế khách quan.

6. **Chuẩn mực Sư phạm & An toàn Tâm lý Học đường:**
   - Không thưởng điểm cho hành vi sỉ nhục học sinh, không gán nhãn cố định về năng lực/tính cách hay tự chẩn đoán y khoa/tâm lý cho học sinh (hồ sơ ban đầu chỉ là trạng thái khởi đầu theo tình huống).
   - Không đánh đồng học sinh im lặng với "không hiểu bài", và không lấy câu trả lời đúng của một học sinh để kết luận cả lớp đã hiểu.

---

## 2. BỐI CẢNH & VÒNG LẶP CHUẨN (THCS BÌNH MINH — LỚP 8A)

- **Bối cảnh thống nhất:** Trường THCS Bình Minh hư cấu tại Việt Nam; người chơi là giáo viên mới phụ trách một tiết **Khoa học tự nhiên Lớp 8A** (45 phút: `07:30–08:15`, kèm khoảng chuẩn bị `07:10–07:30`) dưới sự hướng dẫn của **Cô Mai**.
- **Chủ đề tiết học:** Điều tra vì sao một số vị trí trong sân trường đọng nước sau mưa, thông qua quan sát và so sánh mẫu.
- **Tuyên bố trung thực:** Đây là tình huống thiết kế trải nghiệm nghề; chưa tự tuyên bố đã phù hợp chương trình chính thức hay được giáo viên chuyên môn thẩm định. Mục tiêu thời gian chơi thực 20–30 phút cần được đo bằng playtest.
- **Vòng lặp 6 giai đoạn (có điều kiện chuyển rõ ràng, không auto-chuyển sau 1 thao tác):**
  1. **A. Nhận nhiệm vụ (`Briefing`)** — Cô Mai nêu mục tiêu, người chơi có thể hỏi thêm thông tin mà cô thực sự có cơ sở biết.
  2. **B. Chuẩn bị (`Preparation`)** — Đọc bài cũ, kiểm tra học liệu/thiết bị, chuẩn bị trình chiếu/bản in/thẻ nhóm; được phép phối hợp nhiều phương án nếu còn thời gian và vật liệu.
  3. **C. Mở đầu tiết học (`Opening`)** — Nêu nhiệm vụ và kiểm tra học sinh hiểu yêu cầu trước khi làm việc nhóm.
  4. **D. Điều hành lớp (`Facilitating`)** — Thực hiện nhiều hành động trên 4 nhóm (12 học sinh: Linh, Nam, Hà, Minh + 8 học sinh nền), xem bài làm, hỏi làm rõ, gợi ý/chia nhỏ, minh họa, giao mở rộng, kiểm tra chéo, quay lại kiểm tra bằng ví dụ mới.
  5. **E. Kiểm tra việc học (`CheckingLearning`)** — Thu bài làm, giải thích miệng hoặc phiếu ngắn (nêu rõ giới hạn từng nguồn) và điều chỉnh cách dạy dựa trên kết quả kiểm tra.
  6. **F. Phản tư (`Reflection`)** — Xem lại diễn biến, chọn bài làm/sự kiện làm bằng chứng, ghi điều giữ lại/thay đổi/cần thử ở tiết sau và nhận phản hồi từ Cô Mai có dẫn lại sự kiện cụ thể.

---

## 3. CỔNG PHÊ DUYỆT CON NGƯỜI (HUMAN APPROVAL GATES)

Orchestrator và Agents **BẮT BUỘC DỪNG VÀ XIN PHÉP NGƯỜI DÙNG** trước khi:

1. Áp dụng database migration lên cơ sở dữ liệu thật hoặc can thiệp lịch sử Git.
2. Chỉnh sửa routing/cổng vào website PHP (`app/simulation-3d.php`, `app/simulation-controller.php`) để chuyển sang Unity hoặc mở lại `api/game/*`.
3. Chốt nội dung nhạy cảm liên quan an toàn tâm lý học đường hoặc thay đổi mục tiêu sản phẩm/chi phí tài nguyên ngoài repo.
4. Tự merge hoặc triển khai lên `main` / production (luôn làm việc trên nhánh riêng và mở PR để review).
