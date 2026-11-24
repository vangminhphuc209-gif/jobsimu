# Tài Liệu Tích Hợp Mô Phỏng Nghề Nghiệp (2D & 3D Unity)
*Tài liệu kỹ thuật và sư phạm — Chuẩn hóa trải nghiệm thực tế trên JobSimu*

---

## 1. TỔNG QUAN KIẾN TRÚC MÔ PHỎNG

Hệ thống mô phỏng của JobSimu được thiết kế nhằm giúp học sinh THPT trả lời câu hỏi cốt lõi:  
> **"Khi thực sự thử làm công việc này, em nhận thấy điều gì ở bản thân và thực tế nghề nghiệp?"**

Mô phỏng không phải là bài trắc nghiệm đội lốt game, cũng không phải là game đối kháng thưởng điểm theo phản xạ ngón tay. Toàn bộ kịch bản được xây dựng dựa trên các tình huống sư phạm và nghề nghiệp thực tế.

Hệ thống bao gồm 2 phân hệ hoạt động song song, bổ trợ cho nhau:

| Phân hệ | Công nghệ | Phạm vi áp dụng | Đặc điểm trải nghiệm |
| :--- | :--- | :--- | :--- |
| **Phòng thử 2D** | PHP / HTML5 Canvas / TailWind | 15 ngành nghề cốt lõi (IT, AI, Y khoa, Giáo viên, Marketing, Kiến trúc, Cơ khí, v.v.) | Mô phỏng tình huống ra quyết định nhanh (4–6 phút), 3 giai đoạn thử thách, đối chiếu cảm nhận và ghi chú phản tư cá nhân. |
| **Phòng thử 3D (Vertical Slice)** | Unity 6 URP, First-Person, C# Deterministic (`JobSimu3D/`) | Nghề Giáo viên — Lớp 8A, Trường THCS Bình Minh | Trải nghiệm nhập vai góc nhìn thứ nhất (20–30 phút playtest), tương tác trực tiếp với 12 học sinh và giáo viên hướng dẫn (Cô Mai). |
| **Bản chuyển tiếp Babylon.js** | TypeScript, Babylon.js (`game/`) | Bản prototype chuyển tiếp | Được lưu trữ nguyên vẹn tại `game/`, phục vụ tương thích ngược; API đồng bộ máy chủ `api/game/*` giữ khóa fail-closed (503). |

---

## 2. CHUẨN MỰC MÔ PHỎNG NGHỀ GIÁO VIÊN (THCS BÌNH MINH — LỚP 8A)

### 2.1. Bối cảnh & Nhân vật
- **Địa điểm:** Trường THCS Bình Minh (hư cấu, bối cảnh trường công lập Việt Nam).
- **Thời gian:** 45 phút tiết học chính khóa (`07:30–08:15`) kèm khoảng thời gian chuẩn bị trước giờ dạy (`07:10–07:30`).
- **Chủ đề bài học:** Tiết học môn Khoa học tự nhiên Lớp 8 — Điều tra nguyên nhân các vị trí đọng nước trong sân trường sau cơn mưa thông qua quan sát và đối chiếu mẫu đất.
- **Giáo viên hướng dẫn:** **Cô Mai** — người giao nhiệm vụ, giải đáp băn khoăn và thực hiện phản hồi sư phạm (Debrief) cuối buổi.
- **12 học sinh Lớp 8A (chia thành 4 nhóm):**
  - **Nhóm 1:** Linh (nhân vật chính: học sinh trầm lặng, quan sát tỉ mỉ, phiếu học tập rất chính xác nhưng ngại phát biểu trước đám đông) cùng các bạn.
  - **Nhóm 2:** Nam (nhân vật chính: học sinh năng động, thao tác nhanh, cần câu hỏi gợi mở để không kết luận vội vàng) cùng các bạn.
  - **Nhóm 3:** Hà (nhân vật chính: có khả năng tổ chức nhóm tốt) cùng các bạn.
  - **Nhóm 4:** Minh (nhân vật chính: dễ phân tâm, cần giáo viên chia nhỏ yêu cầu - scaffolding) cùng các bạn.
  - **8 học sinh nền:** An, Bình, Đức, Hùng, Khánh, Thảo, Trang, Vy.

### 2.2. Vòng lặp 6 giai đoạn (The 6-Phase Pedagogical Loop)
Quy trình điều hành tiết học diễn ra theo 6 giai đoạn xác định, có điều kiện chuyển rõ ràng, **tuyệt đối không tự động chuyển giai đoạn sau một thao tác đơn lẻ**:

```
[A. Nhận nhiệm vụ] ➔ [B. Chuẩn bị] ➔ [C. Mở đầu] ➔ [D. Điều hành lớp] ➔ [E. Kiểm tra việc học] ➔ [F. Phản tư]
```

1. **A. Nhận nhiệm vụ (`Briefing`):** Cô Mai phổ biến mục tiêu bài học; người chơi có quyền hỏi thêm các câu hỏi liên quan đến đặc thù lớp học.
2. **B. Chuẩn bị (`Preparation`):** Kiểm tra thiết bị trình chiếu, chuẩn bị bản in, phiếu quan sát hoặc thẻ phân công nhóm. Cho phép phối hợp nhiều phương án chuẩn bị nếu đủ thời gian.
3. **C. Mở đầu tiết học (`Opening`):** Nêu nhiệm vụ thực tế và kiểm tra học sinh đã nắm rõ yêu cầu trước khi phân nhóm làm việc.
4. **D. Điều hành lớp (`Facilitating`):** Di chuyển giữa 4 nhóm, quan sát, lắng nghe, đặt câu hỏi làm rõ, minh họa bằng sơ đồ bảng, chia nhỏ hướng dẫn mà không làm hộ học sinh.
5. **E. Kiểm tra việc học (`CheckingLearning`):** Thu phiếu quan sát, nghe đại diện nhóm giải thích miệng, kiểm tra chéo giữa các nhóm và linh hoạt điều chỉnh cách giảng.
6. **F. Phản tư (`Reflection`):** Đối chiếu diễn biến thực tế với mục tiêu ban đầu, ghi nhận bài học sư phạm (giữ lại điều gì, thay đổi điều gì) và nhận nhận xét từ Cô Mai có viện dẫn sự kiện cụ thể.

---

## 3. CÁC NGUYÊN TẮC BẤT BIẾN ĐÃ ĐƯỢC KIỂM CHỨNG

### 3.1. Gameplay xác định & Không sử dụng LLM trong vòng lặp thời gian thực
- Toàn bộ hành vi của 12 học sinh, mức độ hiểu bài, trạng thái tham gia và điểm chuyển giai đoạn được tính toán bằng máy trạng thái xác định (Deterministic State Machine & Reducer) trong C# (`JobSimu.Quests`, `JobSimu.Consequences`).
- Cùng một chuỗi hành động của người chơi sẽ luôn tạo ra cùng một diễn biến và kết quả, bảo đảm tính công bằng, khả năng replay và kiểm thử tự động (Unit Test / PlayMode Test).

### 3.2. Không dùng chỉ số đánh giá đơn lẻ (No Single-Score Metric)
- Trò chơi tuyệt đối **không** hiển thị các kết luận mang tính áp đặt như: *"Bạn phù hợp nghề giáo viên 82%"* hay *"Bạn không nên làm giáo viên"*.
- Kết thúc mô phỏng, người chơi nhận được: **"Hồ sơ trải nghiệm nghề giáo viên — phiên thử nghiệm"**.
- Báo cáo phân tách rạch ròi giữa:
  1. **Dữ liệu quan sát hành vi:** Các thao tác người chơi đã thực hiện (số lần hỗ trợ nhóm, cách xử lý khi học sinh gặp khó, cách sử dụng bảng).
  2. **Dữ liệu tự phản tư:** Nhận định chủ quan của người chơi về cảm xúc và bài học rút ra.
  3. **Phản hồi sư phạm:** Góp ý mang tính xây dựng từ Cô Mai.

### 3.3. An toàn tâm lý học đường & Chuẩn mực sư phạm
- Học sinh im lặng (như em Linh) có phiếu quan sát chính xác **không bao giờ bị hệ thống đánh giá là "không hiểu bài"**.
- Trò chơi không chấp nhận việc lấy một câu trả lời đúng của một học sinh giỏi để kết luận "cả lớp đã hiểu bài".
- Không thưởng điểm cho hành vi trách phạt, hạ thấp học sinh trước tập thể; khuyến khích các biện pháp hỗ trợ riêng tư và chia nhỏ vấn đề.

### 3.4. Lưu cục bộ trung thực & Khóa Fail-Closed máy chủ
- Tiến độ chơi của học sinh được lưu trữ cục bộ trên thiết bị thông qua `LocalSaveStore`.
- Trong trường hợp dữ liệu lưu bị hỏng, hệ thống tự động đổi tên thành `.corrupt` để bảo toàn dấu vết, không ghi đè bằng phiên trống.
- Khóa fail-closed tại `api/game/bootstrap.php` tiếp tục duy trì mã lỗi `HTTP 503 GAME_SYNC_UNAVAILABLE` cho đến khi có giao thức xác thực phía máy chủ hoàn chỉnh, ngăn chặn việc giả mạo kết quả qua API mở.

---

## 4. MA TRẬN ĐỐI CHIẾU THỰC TẾ TRÊN WEBSITE

Khi học sinh hoàn thành bất kỳ mô phỏng nào (2D hoặc 3D), kết quả được đối chiếu trực tiếp với điểm sở thích RIASEC tự khai thông qua hàm `career_matrix_status`:

```php
career_matrix_status(int $matchPercent, ?array $latestAttempt): array
```

- **🌟 Mục tiêu Vàng (Sweet Spot):** Sở thích lý thuyết O*NET cao (>= 75%) VÀ Phản xạ trải nghiệm mô phỏng tích cực (Điểm tình huống >= 70, cảm xúc "Hào hứng").
- **⚠️ Cần Kiểm chứng Thực tế (Reality Check):** Sở thích lý thuyết cao nhưng học sinh chưa từng thử mô phỏng, hoặc đã thử nhưng cảm thấy áp lực/băn khoăn.
- **💎 Tiềm năng Ẩn giấu (Hidden Gem):** Điểm lý thuyết chưa nổi bật nhưng khi trực tiếp giải quyết tình huống nghề nghiệp, học sinh lại xử lý rất tốt và cảm thấy yêu thích.
- **Khám phá thêm (Neutral):** Các nhóm ngành mở rộng để học sinh tiếp tục tìm hiểu thêm.
