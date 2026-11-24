# Tài Liệu Phương Pháp Luận & Đánh Giá Hướng Nghiệp (Assessment Methodology)
*Cập nhật: Tháng 11/2026 — Antigravity Engineering Lead*

---

## 1. NGUYÊN TẮC ĐẠO ĐỨC & ĐO LƯỜNG SƯ PHẠM

Hệ thống Hướng Nghiệp 360 áp dụng các nguyên tắc đo lường hướng nghiệp trung thực:

1. **Không gán nhãn năng lực cố định**:
   - Các bài trắc nghiệm là công cụ **Tự báo cáo (Self-Reported Inventory)** phản ánh nhận thức và sở thích của học sinh tại một thời điểm, không phải trắc nghiệm đo chỉ số IQ hay bài thi năng lực tuyệt đối.
   - Tuyệt đối không đưa ra các kết luận khẳng định mang tính định kiến như: "Bạn hoàn toàn phù hợp nghề X", "Bạn không có khả năng làm nghề Y".
2. **Phân biệt rành mạch 4 nguồn dữ liệu**:
   - **Sở thích tự báo cáo (Reported Interests)**: Mức độ hứng thú với các hoạt động nghề nghiệp.
   - **Tự hiệu quả tự báo cáo (Reported Self-Efficacy)**: Niềm tin vào khả năng hoàn thành nhiệm vụ của bản thân.
   - **Hành vi quan sát trong mô phỏng (Observed Simulation Telemetry)**: Thời gian đọc tài liệu kiểm chứng, lựa chọn trong tình huống trade-off thực tế.
   - **Dữ liệu thực tế về ngành & tuyển sinh (Verified Admissions Data)**: Điểm chuẩn các năm, tổ hợp xét tuyển, chỉ tiêu, học phí.
3. **Không chuyển đổi trực tiếp phi lý**:
   - Không chuyển "tự tin" thành "đã có năng lực".
   - Không chuyển "đọc tài liệu lâu" thành "khả năng phân tích xuất sắc".
   - Không chuyển "điểm mô phỏng cao" thành "bảo đảm thành công trong nghề".

---

## 2. DANH MỤC CÔNG CỤ TỰ ĐÁNH GIÁ (ASSESSMENT INVENTORY)

| Công cụ | Phiên bản / Nguồn gốc | Số câu | Thang đo | Cơ chế chống thiên kiến & Đảo chiều | Tình trạng áp dụng cho THPT Việt Nam |
| :--- | :--- | :---: | :---: | :--- | :--- |
| **Holland RIASEC** | O*NET Interest Profiler Short Form (Rounds et al., 2010; O*NET Resource Center) | 30 | Likert 1–5 | • Chuẩn hóa nội tại cá nhân **Ipsative Within-Person Z-Score**<br>• Kiểm tra đồ thị phẳng (Flatline check: $\sigma < 0.20$) | Đã hiệu chỉnh ngôn ngữ phù hợp bối cảnh học sinh THPT Việt Nam (công việc học đường, câu lạc bộ, đời sống). |
| **Big Five Mini-IPIP** | Mini-IPIP (Donnellan, Oswald, Baird, & Lucas, 2006; IPIP Goldberg, 1999) | 20 | Likert 1–5 | • 10 câu đảo chiều ($X_{rev} = 6 - X_{raw}$): `b02, b04, b06, b08, b10, b12, b13, b15, b18, b20`<br>• Kiểm tra mâu thuẫn trực tiếp giữa câu thuận và câu nghịch | Thang đo nhân cách rút gọn dùng cho mục đích định hướng tự khám phá; không dùng để chẩn đoán tâm thần hay y khoa. |
| **CAAS-SF** | Career Adapt-Abilities Scale - Short Form (Savickas & Porfeli, 2012; Maggiori et al., 2017) | 12 | Likert 1–5 | • 4 phân nhóm (Concern, Control, Curiosity, Confidence)<br>• Mỗi nhóm có 1 câu nghịch (`c03, c06, c09, c12`) chống thiên kiến đồng thuận | Đánh giá 4 năng lực thích ứng nghề nghiệp cốt lõi (Quan tâm, Tự chủ, Tò mò, Tự tin). |
| **MBTI Rút Gọn** | Phỏng theo 4 cặp đối cực Myers-Briggs (Tự phản tư không chuẩn hóa) | 12 | Chọn A/B/Trung tính | • Trục không nghiêng rõ được gắn mã `X` (không tự bịa chắc chắn)<br>• Ghi rõ: Công cụ tự phản tư không chuẩn hóa | Chỉ dùng như bài đọc tham khảo phong cách làm việc; không xếp hạng nghề nghiệp. |
| **EQ Tình Huống** | 10 kịch bản phản xạ cảm xúc học đường | 10 | Tình huống | • 4 nhóm: Nhận biết, Thấu cảm, Điều hòa, Hợp tác | Đánh giá mức độ tự nhận thức cảm xúc trong bối cảnh học đường. |
| **Khảo sát Đánh đổi Nghề nghiệp** | Bảng kiểm 5 rào cản thực tế (Job Sacrifices) | 5 | Có / Không | • Đào tạo dài năm (6-8 năm), kỷ luật nghiêm ngặt, soi lỗi chi tiết, áp lực cảm xúc, làm lại nhiều lần | Giúp học sinh đối chiếu giữa đam mê bề nổi và sự sẵn sàng chấp nhận mặt khó của nghề. |

---

## 3. THUẬT TOÁN CHUẨN HÓA & PHÂN LOẠI (COMPUTATIONAL LOGIC)

### 3.1. Chuẩn Hóa Nội Tại Cá Nhân (Ipsative Within-Person Z-Score)

Để triệt tiêu thói quen đánh toàn bộ điểm cao (4 hoặc 5) do thiên kiến đồng thuận:
1. Tính trung bình cá nhân: $\mu_{user} = \frac{1}{N} \sum_{i=1}^N X_i$
2. Tính độ lệch chuẩn cá nhân: $\sigma_{user} = \sqrt{\frac{1}{N} \sum_{i=1}^N (X_i - \mu_{user})^2}$
3. Tính Z-Score cho từng nhóm $k \in \{R, I, A, S, E, C\}$:
   $$Z_k = \frac{\bar{X}_k - \mu_{user}}{\max(0.25, \sigma_{user})}$$
4. Quy đổi về thang chỉ số ưu tiên nội tại (0–100):
   $$\text{IpsativeScore}_k = \min(95, \max(5, \text{round}(50 + 15 \times Z_k)))$$
   *Lưu ý minh bạch*: Đây là chỉ số ưu tiên nội bộ so sánh giữa 6 nhóm sở thích của chính học sinh đó, **không phải là phần trăm thành công** hay **percentile so với dân số**.

### 3.2. Phân Loại 4 Vùng SCCT (Social Cognitive Career Theory)

Đối chiếu giữa **Hứng thú ($I$)** và **Tự hiệu quả ($E$)** theo ngưỡng chuẩn $58/100$:

| Vùng | Điều kiện | Tên chuẩn hóa (Trung tính) | Ý nghĩa sư phạm |
| :--- | :--- | :--- | :--- |
| **Core Mastery** | $I \ge 58 \land E \ge 58$ | **Vùng Phát huy** | Học sinh vừa yêu thích vừa tự tin vào năng lực thực hiện nhiệm vụ. |
| **Growth Aspiration** | $I \ge 58 \land E < 58$ | **Khát vọng Cần Bồi dưỡng** | Học sinh rất yêu thích nhưng chưa tự tin vào kỹ năng; cần các thử thách nhỏ để tích lũy dần niềm tin tự hiệu quả. |
| **Available Skill** | $I < 58 \land E \ge 58$ | **Kỹ năng Khả dụng (Ít Ưu tiên)** | Học sinh tự tin làm tốt nhưng hiện tại ít hứng thú; có thể dùng làm kỹ năng hỗ trợ thay vì định hướng nghề nghiệp chính. *(Đã loại bỏ nhãn tiêu cực "Burnout Risk")*. |
| **Low Priority** | $I < 58 \land E < 58$ | **Vùng Chưa Ưu tiên** | Hiện tại học sinh chưa thấy nhiều hứng thú hoặc chưa tự tin ở nhóm này; có thể quay lại tìm hiểu khi có nhu cầu. |

---

## 4. BẢO TOÀN DỮ LIỆU CŨ & TƯƠNG THÍCH LỊCH SỬ

1. Các bản ghi `assessments` cũ lưu điểm thô trong `answers` và `scores` tiếp tục hiển thị bình thường.
2. Với các bài làm cũ chưa có `efficacy_scores` (SCCT), hệ thống gán mặc định baseline bằng điểm sở thích, có nhãn ghi chú "Dữ liệu trước bản cập nhật v3.0, học sinh có thể bổ sung khảo sát điều kiện tự nguyện".
3. Không bắt buộc học sinh phải làm lại từ đầu nếu kết quả cũ vẫn còn giá trị tham khảo.
