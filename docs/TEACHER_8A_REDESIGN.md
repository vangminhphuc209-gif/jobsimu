# Thiết kế lại Vertical Slice 3D: Một tiết Khoa học tự nhiên lớp 8A

> **Trạng thái:** Tài liệu thiết kế triển khai trên nhánh riêng (`feat/teacher-8a-redesign`)  
> **Runtime chính:** Unity 6 (`JobSimu3D/`), góc nhìn thứ nhất (FPP), scene `Assets/_JobSimu/Scenes/SharedFrameworkDemo.unity`  
> **Bản chuyển tiếp cũ:** `game/` (Babylon.js) và cổng PHP (`app/simulation-3d.php`) giữ nguyên, chưa xóa và chưa trỏ sang Unity; `api/game/*` tiếp tục trả `503 GAME_SYNC_UNAVAILABLE`

---

## 1. Phạm vi, công nghệ và bối cảnh nhất quán

- **Nền tảng 3D chính:** `JobSimu3D/` (Unity 6000.6.8f1, URP, New Input System, góc nhìn thứ nhất).
- **Trạng thái mô phỏng xác định (Deterministic):** Toàn bộ phản ứng học sinh, tiến trình tiết học, hệ quả chậm và báo cáo chạy bằng bộ chuyển trạng thái xác định trong C#, có thể replay từ lịch sử hành động; **không** dùng LLM để quyết định trạng thái học sinh, chấm năng lực hay điều khiển nhiệm vụ.
- **Bối cảnh thống nhất (đã loại bỏ hỗn hợp THCS/THPT, 9A/10A1):**
  - **Trường:** Trường THCS Bình Minh (trường hư cấu tại Việt Nam).
  - **Vai trò:** Giáo viên mới phụ trách một tiết **Khoa học tự nhiên** của **Lớp 8A** dưới sự hướng dẫn của **Cô Mai**.
  - **Chủ đề tiết học:** Điều tra vì sao một số vị trí trong sân trường đọng nước sau mưa, thông qua quan sát và so sánh mẫu (đất trồng tơi xốp, gạch/bê tông lát kín, lối đất nén chặt).
  - **Thời lượng trong thế giới game:** Khoảng chuẩn bị đầu giờ `07:10–07:30` (20 phút trước trống vào lớp) và tiết học **45 phút** (`07:30–08:15`). Nếu chuẩn bị quá `07:30`, thời gian vào tiết bị lẹm vào quỹ 45 phút.
  - **Mục tiêu thời gian chơi thực:** Khoảng **20–30 phút** (cần đo qua playtest thực tế).

### Những giả định chưa thẩm định (cần ghi rõ trong báo cáo và tài liệu)

1. Tình huống điều tra sân trường đọng nước cho Lớp 8A là kịch bản thiết kế cho mục đích trải nghiệm nghề; **chưa** tự tuyên bố phù hợp chương trình chính thức hay đã qua thẩm định của giáo viên chuyên môn KHTN.
2. Mô hình trạng thái học sinh (kiến thức, hiểu yêu cầu, mức sẵn sàng, cảm giác an toàn) là mô phỏng sư phạm đơn giản hóa, **không** phải công cụ đo lường tâm lý học chuẩn hóa.
3. Mục tiêu thời gian chơi 20–30 phút và hiệu năng FPS trên máy người dùng cuối chỉ là giả định thiết kế cho đến khi đo bằng playtest người thật và profiler trên phần cứng mục tiêu.
4. Các tài sản 3D nhân vật và đồ vật trong giai đoạn này là hình khối có chủ đích phục vụ gameplay/kiểm thử không gian (blockout có tương tác và nội dung đọc được), không phải art/rig hoàn thiện.

---

## 2. Kiến trúc vòng lặp 6 giai đoạn và điều kiện chuyển bước

Không còn cơ chế `chọn 1 hành động → tự chuyển bước → khóa phần còn lại`. Vòng lặp gồm 6 giai đoạn rõ ràng:

| Giai đoạn | Khu vực chính | Hành động được phép & lặp lại | Điều kiện chuyển giai đoạn |
|---|---|---|---|
| **A. Nhận nhiệm vụ** (`Briefing`) | Sân trường (Cô Mai + vũng nước mẫu sân trường) | Nghe mục tiêu; hỏi thêm tối đa 3 câu về thói quen học tập lớp 8A, cách chia nhóm, tình trạng thiết bị (chỉ thông tin cô Mai thực sự biết); quan sát vũng đọng nước mẫu ngoài sân. | Chủ động xác nhận nhận nhiệm vụ để vào chuẩn bị (`brief.accept`). |
| **B. Chuẩn bị** (`Preparation`) | Phòng giáo viên | Đọc bài làm tiết trước (`prep.read_prev_work`), kiểm tra bộ mẫu & tình trạng máy chiếu (`prep.inspect_gear`), soạn slide trình chiếu (`prep.projector`), in phiếu & ảnh mẫu (`prep.print`), soạn thẻ nhóm & khay mẫu (`prep.group_cards`). **Cho phép phối hợp nhiều phương án** nếu còn thời gian/tài nguyên. | Chủ động mang học liệu sang lớp 8A (`prep.enter_class`). |
| **C. Mở đầu tiết học** (`Opening`) | Bảng & bục giảng Lớp 8A | Nêu nhiệm vụ điều tra theo hướng rõ ràng, nhanh hoặc từng bước; **bắt buộc kiểm tra mức hiểu yêu cầu** (`open.check_understanding`) bằng hỏi cả lớp hoặc hỏi nhanh 1–2 bàn trước khi giao việc nhóm. | Đã nêu nhiệm vụ **và** đã thực hiện bước kiểm tra hiểu yêu cầu (`open.start_group_work`). |
| **D. Điều hành lớp** (`Facilitating`) | 4 cụm bàn Nhóm 1–4, bảng, khay mẫu | Thực hiện **nhiều hành động tự do** across 4 nhóm và bảng: quan sát bài làm nhóm, hỏi làm rõ, chia nhỏ nhiệm vụ / gợi ý, minh họa bảng hoặc nhỏ nước mẫu thử, giao mở rộng cho nhóm xong sớm, ghép kiểm tra chéo, **quay lại kiểm tra bằng ví dụ mới** sau khi hỗ trợ. | Không tự chuyển sau 1 hành động! Chỉ mở nút `Chuyển sang Kiểm tra việc học` sau khi đã thực hiện tối thiểu 2 tương tác lớp/nhóm có nghĩa (hoặc gần cuối tiết), do người chơi chủ động chọn. |
| **E. Kiểm tra việc học** (`CheckingLearning`) | Góc phiếu, bảng, các nhóm | Thu phiếu viết của 4 nhóm (`assess.collect_sheets`), gọi giải thích miệng (`assess.oral_sample`), hoặc phát phiếu kiểm tra ngắn ví dụ mới (`assess.quick_probe`); **có thể điều chỉnh ngắn trên bảng/với nhóm** (`assess.adjust_reteach`) nếu phát hiện lỗ hổng từ kết quả kiểm tra! | Đã thu ít nhất 1 nguồn bằng chứng kiểm tra việc học; người chơi chủ động kết thúc tiết (`assess.end_lesson`), hoặc khi chuông hết giờ `08:15` vang lên. |
| **F. Phản tư** (`Reflection`) | Phòng giáo viên / Cô Mai (hoặc UI phản tư khi hết giờ) | Xem dòng thời gian và các mục còn dang dở; **chọn bài làm/sự kiện cụ thể làm bằng chứng**; ghi điều thích/chưa thích, điều thấy khó, điều giữ lại/thay đổi ở tiết sau; nhận phản hồi từ Cô Mai **có dẫn lại sự kiện cụ thể đã xảy ra**. | Hoàn thành ghi nhận phản tư (`reflect.finish`). |

---

## 3. Kiến trúc trạng thái & quản lý thời gian

`ScenarioSession` giữ mô hình reducer xác định (cập nhật từ `ScenarioAction` + `ClockTick`), nâng cấp từ chuỗi bước tuyến tính sang trạng thái đa chiều:

- **Thời gian tách bạch, không tính hai lần:**
  - Thời gian tự trôi (`PassiveMinutes`): chỉ tích lũy trong `ScreenPage.Gameplay` khi `Started && !Complete && !BlocksGameplay && Application.isFocused && !ApplicationPaused` (1 phút trong game mỗi 25 giây thực di chuyển/quan sát).
  - Thời gian tiêu hao do hành động (`ActionMinutes`): mỗi hành động tiêu tốn đúng số phút định nghĩa (ví dụ đọc bài cũ 4 phút, hỗ trợ nhóm 5–6 phút) và **đặt lại bộ đếm tích lũy thụ động** (`clockAccumulator = 0`) để không cộng trùng khoảng thời gian vừa thao tác.
  - Đọc hội thoại, mở menu tạm dừng, nhật ký, cài đặt, bảng bài làm, hoặc mất focus trình duyệt **dừng đồng hồ hoàn toàn**.
  - **Hết giờ (`08:15`, phút `495`):** Chuyển ngay sang `Reflection` với cờ `period.timed_out`, ghi nhận danh sách công việc còn dang dở, không softlock và không tự đánh dấu hoàn thành tốt.
- **Chống spam bằng chứng & phân biệt hành động:**
  - Phân biệt hành động chỉ làm 1 lần (`OneTime`), hành động theo ngữ cảnh chỉ mở khi trạng thái thỏa điều kiện (`ContextGated`), và hành động xem lại thông tin (`InspectOnly`).
  - Xem lại bài làm khi trạng thái nhóm chưa đổi không cộng thêm bằng chứng năng lực; chỉ khi có dữ kiện mới hoặc thay đổi trạng thái mới ghi nhận bằng chứng.

---

## 4. Lớp 8A: 12 học sinh, 4 nhóm và 4 tình huống đan xen

Mỗi nhóm gồm 3 học sinh ngồi đúng ghế theo cụm bàn (1 học sinh trọng tâm + 2 học sinh nền):

1. **Nhóm 1 — Linh (trọng tâm), An, Bình** — *Tình huống 3 (Học sinh im lặng)*:
   - Linh hiểu quan sát và ghi bảng so sánh khá cẩn thận trên giấy, nhưng ngại phát biểu trước cả lớp.
   - Hỏi riêng / trao đổi theo cặp / đọc bài trên bàn cho thông tin khác hẳn việc gọi đứng lên ngay trước lớp. Nếu bị thúc ép công khai khi cảm giác an toàn chưa đủ, Linh càng thu mình; hệ thống **không** gán "không hiểu bài" chỉ vì Linh im lặng.
2. **Nhóm 2 — Nam (trọng tâm), Vy, Khánh** — *Tình huống 4 (Trả lời nghe đúng nhưng thiếu bằng chứng)*:
   - Nam phát biểu nhanh: "Sân bê tông không thấm nên đọng nước, đất thấm hết". Nghe hợp lý nhưng chưa có đối chiếu lượng nước bằng nhau và bỏ qua đất nén chặt.
   - Người chơi có thể: chấp nhận ngay (nhanh nhưng bài nhóm còn hời hợt), hỏi cách suy luận, yêu cầu đối chiếu 2 mẫu, hoặc thiết kế phép kiểm tra nhỏ giọt nước. Kết quả hiện ngay trên bài làm Nhóm 2 và khả năng bảo vệ lập luận ở pha E.
3. **Nhóm 3 — Hà (trọng tâm), Trang, Đức** — *Tình huống 2 (Xong sớm + diễn đạt từ hình vẽ)*:
   - Hà vẽ sơ đồ lát cắt hạt đất và mặt gạch rất rõ, khiến nhóm xong phần cơ bản sớm (~7 phút sau khi vào hoạt động nhóm), nhưng phần lời giải thích của Hà còn ngắn.
   - Người chơi có thể: giúp Hà chuyển hình vẽ thành lời giải thích, giao nhiệm vụ mở rộng (mẫu lối đất nén dưới gốc cây), hoặc phân công kiểm tra chéo Nhóm 4 theo câu hỏi gợi mở. Nếu bỏ mặc kéo dài (>8 phút lớp sau khi xong), Trang và Đức giảm tập trung (nhìn quanh, nói chuyện riêng), nhưng không bị phạt tức thì khi người chơi đang hỗ trợ nhóm khác.
4. **Nhóm 4 — Minh (trọng tâm), Hùng, Thảo** — *Tình huống 1 (Hiểu sai yêu cầu / thiếu nền)*:
   - Minh hiểu nhầm yêu cầu thành "tìm chỗ bẩn/nhiều rác hơn" hoặc cho rằng "nước tự biến mất trên sân nắng".
   - Đọc bài tiết trước (pha B) hoặc hỏi làm rõ ở bàn (pha D) giúp biết nguyên nhân trước khi chọn cách hỗ trợ. Chỉ đọc lại nguyên đề bài **không** sửa được lỗi trên bài làm; chia nhỏ nhiệm vụ hoặc minh họa bằng 2 mẫu thử mới giúp nhóm đổi hướng — và **bắt buộc phải quay lại kiểm tra bằng ví dụ mới** (đất tơi vs lối đất nén) để xác nhận chuyển biến thật.

**Hệ quả chậm rõ nguyên nhân & thời điểm:**
- Nếu chọn `prep.projector` (đèn báo đã chập chờn khi kiểm tra hoặc chưa kịp kiểm tra), sau 6 phút dạy trong lớp 8A máy chiếu ngắt hình (`event.projector_failed`), bảng chiếu tắt và đèn báo chuyển đỏ — buộc người chơi xoay sang bảng phấn, bản in dự phòng (nếu đã phối hợp chuẩn bị) hoặc mẫu vật thật.

---

## 5. Báo cáo cuối tiết & lưu cục bộ

- **Tên báo cáo:** `Hồ sơ trải nghiệm nghề giáo viên — phiên thử nghiệm`
- **Cấu trúc 6 mục (A–F):**
  - **A. Bạn đã làm gì?** (dòng thời gian quyết định theo giờ trong lớp)
  - **B. Có bằng chứng gì?** (tách rõ: thông tin đã quan sát, hành động đã làm & việc còn dang dở, phản ứng thực tế từng nhóm, kết quả kiểm tra lại)
  - **C. Những xu hướng được ghi nhận** (5 trục mô tả dựa trên hành vi, không chấm điểm đơn lẻ, không kết luận hợp/không hợp nghề)
  - **D. Cách giải thích khác** (giải thích thay thế khách quan cho những việc chưa kịp làm, không tự suy diễn "thiếu thấu cảm")
  - **E. Tự phản tư** (dữ liệu do người chơi nhập + sự kiện đã chọn làm bằng chứng)
  - **F. Cần trải nghiệm thêm** (nhiệm vụ chưa thử, bằng chứng chưa nhất quán, gợi ý lượt chơi sau)
- Mọi mục ghi rõ nhãn phân biệt: `[Dữ liệu quan sát]`, `[Tự báo cáo]`, `[Suy luận hệ thống — giới hạn trong phiên thử nghiệm]`.
- **Lưu cục bộ (`LocalSaveStore` v2):** Lưu chuỗi hành động, tích lũy đồng hồ, sự kiện đã phát, lựa chọn phản tư và hạt giống xác định; replay kiểm tra lại toàn bộ trạng thái khi tải; bản lưu lỗi được bảo toàn sang khóa dự phòng `.corrupt` và không bị autosave hay phiên trống tự ghi đè.
