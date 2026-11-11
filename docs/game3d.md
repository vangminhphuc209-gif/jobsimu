# JOBSIMU 3D — NGƯỜI THẦY SAU TIẾNG TRỐNG TRƯỜNG (TÀI LIỆU GDD GỐC)

> [!IMPORTANT]
> **Cập nhật định hướng triển khai (10/11/2026):** Tài liệu này là bản GDD gốc ban đầu (đề xuất Babylon.js, Lớp 9A, cả ngày làm việc). Trong phạm vi vertical slice hiện hành, các quyết định về engine, góc nhìn, bối cảnh lớp 8A và vòng lặp một tiết học đã được cập nhật và chốt thống nhất tại [`docs/TEACHER_8A_REDESIGN.md`](file:///home/minhphuc/jobsimu/docs/TEACHER_8A_REDESIGN.md) và [`AGENTS.md`](file:///home/minhphuc/jobsimu/AGENTS.md): **Unity 6 (`JobSimu3D/`)** góc nhìn thứ nhất là bản 3D chính, bối cảnh **Trường THCS Bình Minh — Lớp 8A (Khoa học tự nhiên, tiết 45 phút)**; `game/` (Babylon.js) chỉ giữ làm bản chuyển tiếp cũ.

> **Game Design Document (GDD) + Technical Design + Production Plan**  
> Dự án: [vangminhphuc209-gif/jobsimu](https://github.com/vangminhphuc209-gif/jobsimu)  
> Phiên bản tài liệu gốc: **1.0 — 07/11/2026**  
> Trạng thái: **Tài liệu đề xuất gốc (một phần công nghệ & bối cảnh đã được thay thế bởi `docs/TEACHER_8A_REDESIGN.md`)**  
> Phạm vi ưu tiên hiện hành: **Một tiết Khoa học tự nhiên Lớp 8A hoàn chỉnh (45 phút trong game) trên Unity 6 trước khi mở rộng sang cả ngày hay nghề khác**.

---

## Mục lục

1. [Tuyên ngôn sản phẩm và phạm vi](#1-tuyên-ngôn-sản-phẩm-và-phạm-vi)
2. [Đánh giá JOBSIMU hiện có và chiến lược nâng cấp](#2-đánh-giá-jobsimu-hiện-có-và-chiến-lược-nâng-cấp)
3. [Trải nghiệm người chơi và nguyên tắc thiết kế](#3-trải-nghiệm-người-chơi-và-nguyên-tắc-thiết-kế)
4. [Bối cảnh, bản đồ và phong cách nghệ thuật](#4-bối-cảnh-bản-đồ-và-phong-cách-nghệ-thuật)
5. [Nhân vật, NPC và đời sống học đường](#5-nhân-vật-npc-và-đời-sống-học-đường)
6. [Vòng lặp gameplay: tình huống mở ở **mọi** bước](#6-vòng-lặp-gameplay-tình-huống-mở-ở-mọi-bước)
7. [Lịch một ngày làm giáo viên](#7-lịch-một-ngày-làm-giáo-viên)
8. [Ba chuỗi cốt truyện liên kết sâu](#8-ba-chuỗi-cốt-truyện-liên-kết-sâu)
9. [Chiến dịch năm ngày và kết thúc mở](#9-chiến-dịch-năm-ngày-và-kết-thúc-mở)
10. [Hệ thống gameplay, luật chơi và phản hồi](#10-hệ-thống-gameplay-luật-chơi-và-phản-hồi)
11. [Thiết kế UX/UI, camera, điều khiển và âm thanh](#11-thiết-kế-uxui-camera-điều-khiển-và-âm-thanh)
12. [Kiến trúc kỹ thuật và tích hợp repository](#12-kiến-trúc-kỹ-thuật-và-tích-hợp-repository)
13. [Thiết kế dữ liệu, sự kiện và lưu game](#13-thiết-kế-dữ-liệu-sự-kiện-và-lưu-game)
14. [Kế hoạch assets 3D và quy trình sản xuất](#14-kế-hoạch-assets-3d-và-quy-trình-sản-xuất)
15. [AI, quyền riêng tư và chuẩn mực sư phạm](#15-ai-quyền-riêng-tư-và-chuẩn-mực-sư-phạm)
16. [Lộ trình, phân vai và ngân sách nguồn lực](#16-lộ-trình-phân-vai-và-ngân-sách-nguồn-lực)
17. [Tiêu chí nghiệm thu, kiểm thử và rủi ro](#17-tiêu-chí-nghiệm-thu-kiểm-thử-và-rủi-ro)
18. [Backlog triển khai và việc cần làm đầu tiên](#18-backlog-triển-khai-và-việc-cần-làm-đầu-tiên)
19. [Tài liệu tham chiếu](#19-tài-liệu-tham-chiếu)

---

## 1. Tuyên ngôn sản phẩm và phạm vi

### 1.1. Tầm nhìn

**JOBSIMU 3D là nền tảng để người chơi _sống thử một nghề_, thay vì đọc mô tả về nghề hoặc đoán đáp án đúng của một câu hỏi trắc nghiệm.** Phiên bản đầu tiên tập trung vào nghề giáo viên ở Việt Nam: lên lớp, chuẩn bị giáo án, theo dõi học sinh, xử lý phát sinh, trao đổi với phụ huynh và cân bằng công việc.

**Tên chiến dịch:** *Người thầy sau tiếng trống trường*.  
**Tagline:** *Một ngày bình thường. Hàng chục quyết định không bình thường.*

**Giả định sản phẩm:** game 3D trên trình duyệt, một người chơi, góc nhìn thứ ba, nội dung tiếng Việt; điều khiển bàn phím + chuột trước, khả năng mở rộng mobile sau. Phong cách *stylized 3D bán hiện thực*, lấy trường học Việt Nam làm cảm hứng nhưng sử dụng trường và nhân vật giả tưởng.

### 1.2. Năm trụ cột thiết kế

| Trụ cột | Cam kết cụ thể |
|---|---|
| **Sống trong thế giới 3D** | Nhân vật có thể đi, quan sát, mở cửa, lấy tài liệu, viết bảng, tương tác NPC và đồ vật có animation/âm thanh. |
| **Tình huống mở, không phải câu hỏi A/B/C** | Mỗi bước gameplay đều có thông tin chưa đầy đủ, nhiều cách giải quyết qua hành động, nói chuyện, chờ đợi, tìm bằng chứng hoặc nhờ trợ giúp. |
| **Hệ quả liên tục** | Việc làm ở đầu ngày thay đổi trạng thái lớp, quan hệ NPC, các nhiệm vụ và thông tin nhận được vào cuối ngày hoặc những ngày sau. |
| **Nghề nghiệp chân thực nhưng dễ tiếp cận** | Công việc có cả niềm vui, áp lực, khía cạnh hành chính, quan hệ con người, chuyên môn và việc bảo vệ học sinh. |
| **Hướng nghiệp có trách nhiệm** | Báo cáo dựa trên bằng chứng hành động và tự phản tư; không kết luận người chơi “phù hợp/không phù hợp” chỉ vì một điểm số. |

### 1.3. Đối tượng và thời lượng

- **Người chơi chính:** học sinh THCS cuối cấp, THPT và người muốn khám phá nghề giáo.
- **Phù hợp:** khoảng 13+ với nội dung xử lý xung đột học đường ở mức phù hợp lứa tuổi.
- **Vòng chơi ngắn:** một tiết học 15–25 phút thực.
- **Ngày chơi hoàn chỉnh:** khoảng **60–90 phút thực**, có checkpoint và tùy chọn chia thành các phiên ngắn.
- **Bản phát triển đầu tiên (vertical slice):** 1 ngày, 4 khu chơi, 8–10 NPC chủ chốt, 8–12 sự kiện có dàn dựng, ít nhất 3 chuỗi hậu quả.
- **Bản chiến dịch:** tuần làm việc 5 ngày, nội dung phân nhánh và sự kiện tái xuất.

### 1.4. Điều không làm trong bản đầu

Không xây bản đồ thành phố mở, không multiplayer, không chatbot vô hạn cho mọi học sinh, không làm mọi nghề cùng lúc, không mô phỏng bạo lực học đường một cách giật gân, không lồng tiếng toàn bộ thoại từ ngày đầu. **Độ sâu tương tác và chất lượng một ngày chơi quan trọng hơn độ rộng bản đồ.**

---

## 2. Đánh giá JOBSIMU hiện có và chiến lược nâng cấp

### 2.1. Những gì đã kiểm tra trên GitHub

Tại thời điểm lập tài liệu, các tệp công khai liên quan bao gồm:

| Thành phần đang có | Quan sát từ mã nguồn | Phương án xử lý |
|---|---|---|
| `README.md` | Website Hướng Nghiệp 360, PHP/MySQL, 19 hồ sơ nghề, 15 mô phỏng, RIASEC, báo cáo | **Giữ và tích hợp**, không viết lại toàn website |
| `app/simulations.php` | Dữ liệu mô phỏng nghề giáo `giao-vien`, chủ đề biến đổi khí hậu, các tình huống sư phạm | **Chuyển hóa nội dung** thành sự kiện trong thế giới 3D |
| `app/simulation-controller.php` | Mô phỏng hiện tại tạo lượt chơi, hiển thị tình huống và lựa chọn, báo cáo | **Giữ làm chế độ cổ điển**, bổ sung cửa vào chế độ 3D |
| `app/functions.php` | Có hàm chọn một tình huống mỗi giai đoạn, tính điểm và năm nhóm kỹ năng | **Tái sử dụng khái niệm kỹ năng**, không bê cơ chế cộng điểm sang game 3D |
| `database/schema.sql` | `users`, `careers`, `simulation_attempts`, `assessments`... | **Mở rộng bằng migrations**, không phá bảng cũ |
| `app/bootstrap.php` | Khởi tạo session, kết nối PDO và cấu hình | **Dùng lại xác thực PHP** cho Game API cùng origin |
| `.gitignore` | Đã loại `config.php`, `.env` khỏi tracking trong phiên bản quan sát | **Duy trì và rà soát lịch sử** trước khi phát hành |

**Nguồn mã:** https://github.com/vangminhphuc209-gif/jobsimu (nhánh `main`). Mọi đường dẫn file ở đây mô tả cấu trúc hiện quan sát, không bảo đảm mọi file sẽ giữ nguyên khi repository thay đổi.

### 2.2. Quyết định nâng cấp

1. Giữ website PHP/MySQL làm **cổng hướng nghiệp, hệ thống tài khoản, dữ liệu nghề và báo cáo**.
2. Thêm **ứng dụng 3D độc lập** trong `game/` viết bằng TypeScript và Babylon.js.
3. Tạo **API PHP** để bắt đầu lượt, lưu sự kiện, nạp bản lưu, hoàn thành ngày và đồng bộ báo cáo.
4. Nội dung cốt truyện chuyển thành **dữ liệu có cấu trúc** (JSON có schema) thay vì trộn trực tiếp vào mã điều khiển.
5. Để người chơi chọn **Mô phỏng nhanh (hiện tại)** hoặc **Trải nghiệm nghề 3D (mới)** trong trang giáo viên.

### 2.3. Công việc bảo mật ưu tiên

Bản README/.gitignore hiện nêu rõ không công khai `config.php`; phiên bản raw `config.php` hiện không truy cập được. **Tuy vậy, nếu khóa hay mật khẩu từng được commit vào lịch sử, xóa file ở commit sau không đủ.** Kiểm tra Git history, thay mật khẩu/thu hồi khóa đã lộ, sử dụng cấu hình nằm ngoài vùng public hoặc biến môi trường, tắt debug production, không đưa bí mật thật vào sample/documentation.

---

## 3. Trải nghiệm người chơi và nguyên tắc thiết kế

### 3.1. Lời hứa dành cho người chơi

“Bạn không chọn giữa một đáp án đúng và hai đáp án sai. Bạn khám phá một môi trường có người thật, thông tin chưa đầy đủ, thời gian hữu hạn. Bạn quyết định sẽ **quan sát gì, hỏi ai, làm gì trước, xử lý ra sao và học được điều gì**.”

### 3.2. Công thức một tình huống mở

Mọi sự kiện quan trọng phải có **đủ 10 thành phần**:

1. **Bối cảnh:** vị trí, giờ, các NPC liên quan, trạng thái lớp.
2. **Điều kiện kích hoạt:** đến nơi, nhìn thấy tín hiệu, NPC chủ động tìm tới, mốc thời gian hoặc hệ quả cũ.
3. **Dấu hiệu nhận biết:** hành vi, âm thanh, vật thể, lời nói; không chỉ popup.
4. **Thông tin chưa biết:** nguyên nhân không được lộ ngay từ đầu.
5. **Ít nhất 3 kiểu tiếp cận:** hành động vật lý, đối thoại, tìm thông tin/nhờ trợ giúp; có thể kết hợp.
6. **Đánh đổi hợp lý:** thời gian, nguồn lực, quan hệ, chất lượng học tập hoặc sự riêng tư.
7. **Phản ứng tức thì:** NPC/đồ vật/môi trường thay đổi dễ quan sát.
8. **Hệ quả chậm:** mở hoặc thay đổi một sự kiện ở giai đoạn sau.
9. **Phương án phục hồi:** sau lỗi có thể xin lỗi, điều chỉnh, tìm trợ giúp; không softlock.
10. **Bằng chứng đánh giá:** game ghi lại hành vi thực sự, không đoán nội tâm người chơi.

### 3.3. Định nghĩa “mở” nhưng làm được về kỹ thuật

- Người chơi **được di chuyển tự do trong phạm vi trường**, tương tác trong các khung giờ có ý nghĩa.
- Không bắt buộc mọi sự kiện dùng ba nút lựa chọn. Người chơi có thể **thực hiện chuỗi hành động**, chọn **thứ tự**, hoặc **không can thiệp** khi chưa đủ thông tin.
- Hội thoại có các ý định có kiểm soát: hỏi thăm, giải thích, yêu cầu thông tin, đề nghị hỗ trợ, xin thời gian, ghi chú, xin lỗi, chuyển tuyến. Chúng có thể được thể hiện bằng câu thoại tự nhiên.
- Có **hệ thống trạng thái và quy tắc** phía sau; không hứa rằng NPC hiểu vô hạn mọi câu gõ tự do.
- Với sự kiện an toàn học sinh, các lựa chọn sai quy trình cần được xử lý bằng phản hồi học tập và tuyến hỗ trợ phù hợp, không “thưởng” cho hành vi nguy hiểm.

### 3.4. Quy tắc chống bế tắc

Bất kể lựa chọn nào: câu chuyện vẫn tiến về cuối ngày; nhiệm vụ ưu tiên được gợi ý đúng lúc; khi bỏ lỡ một cơ hội, NPC có thể để lại lời nhắn hoặc có dịp trò chuyện sau. Những sự kiện bị bỏ qua trở thành **hệ quả được thừa nhận**, không phải màn hình *Game Over* vô lý.

---

## 4. Bối cảnh, bản đồ và phong cách nghệ thuật

### 4.1. Trường THCS Bình Minh

Trường giả tưởng tại một thành phố Việt Nam. Thiết kế nhấn mạnh các chi tiết quen thuộc: cổng sắt, khuôn viên có cây phượng, bảng thông báo, hành lang, lớp có quạt trần, bảng viết, cửa sổ, bàn ghế đôi, bình nước, thư viện, phòng giáo viên. Biển bảng/đồ vật không sử dụng thương hiệu thật khi chưa có quyền.

### 4.2. Phân vùng bản đồ

| Khu vực | Cảm xúc | Tương tác cần có | Mức ưu tiên |
|---|---|---|---|
| Lớp 9A | Năng động, có áp lực điều phối | Viết bảng, dùng máy chiếu, chia nhóm, quan sát học sinh | **P0** |
| Phòng giáo viên | Hối hả nhưng ấm áp | Nhận lịch, xem giáo án, nhờ mentor, chấm bài | **P0** |
| Hành lang | Chuyển tiếp, nhiều tình huống bất ngờ | Gặp học sinh, trò chuyện kín đáo, quan sát | **P0** |
| Sân trường/cổng | Không khí của một ngày học | Đi tới lớp, chào NPC, chứng kiến sự kiện | **P0** |
| Lớp học thứ hai | Khác biệt năng lực tiếp thu | Thử cách dạy thích ứng | P1 |
| Phòng y tế/tư vấn | An toàn, bảo mật | Chuyển tuyến xử lý phù hợp | P1 |
| Thư viện/phòng thiết bị | Khám phá, học tập | Lấy tài liệu, khắc phục thiếu học liệu | P1 |
| Ban giám hiệu | Trách nhiệm và quy trình | Báo cáo, họp, xử lý phản ánh | P1 |

Đầu tiên dùng **blockout** (hộp 3D đơn giản) để kiểm tra kích thước, lối đi và gameplay. Sau khi kiểm thử mới thay bằng asset hoàn chỉnh. Scene nên chia theo khu để tải dần; không yêu cầu tải mọi đồ vật/animation cùng lúc.

### 4.3. Hình ảnh và art direction

- **Phong cách:** stylized bán hiện thực, tỷ lệ cơ thể vừa phải, màu sắc dễ chịu, chất liệu nhất quán.
- **Bảng màu:** gỗ nâu sáng, tường kem, cây xanh, đồng phục trung tính, nắng vàng dịu.
- **Ánh sáng:** buổi sáng trong, trưa sáng gắt vừa phải, cuối chiều vàng ấm; hạn chế hiệu ứng quá nặng.
- **Máy quay:** góc nhìn thứ ba, tránh xuyên tường, tự điều chỉnh khoảng cách trong lớp hẹp.
- **Biểu cảm:** mắt, đầu, vai, tay và nhịp chuyển động phải giúp đọc được tình huống mà không dựa vào chữ.
- **Văn hóa:** ngữ cảnh giáo dục Việt Nam chân thực nhưng tránh đồng nhất mọi trường, mọi giáo viên.

---

## 5. Nhân vật, NPC và đời sống học đường

### 5.1. Nhân vật chính

**Giáo viên An** là tên mặc định (được đổi tên, diện mạo và cách xưng hô). Giáo viên trẻ mới nhận lớp 9A, dạy chủ đề Địa lý/biến đổi khí hậu. Người chơi không bị ép theo một “tính cách đạo đức” có sẵn; phong cách được hình thành qua hành động.

**Animation bắt buộc:** đứng nghỉ, đi/chạy nhẹ, rẽ, ngồi, nhìn mục tiêu, cầm tài liệu, viết bảng, chỉ tranh, giảng, lắng nghe, gật đầu, thể hiện ngạc nhiên/lo ngại phù hợp.

### 5.2. Dàn NPC có chức năng hệ thống

| NPC | Vai trò/tính cách | Mục tiêu cá nhân | Biến quan hệ / cốt truyện |
|---|---|---|---|
| **Cô Mai** | Mentor điềm tĩnh | Hỗ trợ giáo viên mới | `mentor_support`, mở gợi ý chuyên môn |
| **Cô Hạnh** | Phó hiệu trưởng nghiêm túc | Đảm bảo việc học và thủ tục | `admin_confidence`, ưu tiên công việc |
| **Thầy Quân** | Đồng nghiệp cởi mở | Cùng phát triển hoạt động lớp | `colleague_cooperation` |
| **Minh** | Hoạt bát, mất tập trung | Muốn được tôn trọng và hiểu | `minh_trust`, chuỗi quan sát dài hạn |
| **Linh** | Học giỏi, cầu toàn | Muốn đáp ứng kỳ vọng | `linh_pressure_known`, hỗ trợ riêng tư |
| **Nam** | Thích phản biện | Muốn ý kiến được lắng nghe | `nam_voice`, tinh thần tranh luận |
| **Hà** | Trầm tính | Muốn tham gia an toàn | `ha_participation`, hòa nhập |
| **Tuấn** | Lớp trưởng | Muốn lớp phối hợp tốt | `class_leadership` |
| **Chị Thu** | Phụ huynh quan tâm, đôi lúc lo lắng | Hiểu quá trình học của con | `parent_confidence`, trao đổi có bằng chứng |
| **Cô Lan** | Nhân viên y tế/hỗ trợ học đường | Chăm sóc an toàn học sinh | tuyến hỗ trợ cho tình huống phù hợp |

Ngoài nhóm trên có 12–18 NPC nền dùng mẫu model và biến thể tóc/trang phục, có lịch và hoạt ảnh. Không tạo NPC chỉ đứng bất động như vật trang trí.

### 5.3. Hành vi NPC: mô hình lai

- **Lịch sinh hoạt:** đi đến lớp, học, ra chơi, đi về; vị trí mục tiêu tùy giờ.
- **Finite State Machine/Behavior Tree:** `Idle → Walking → Seated → Listening → Reacting → Talking → Resume`.
- **Utility/Context Rules:** chọn hành động dựa trên mục tiêu, tình huống, áp lực và quan hệ. Ví dụ Minh giảm khả năng chủ động trả lời khi `minh_trust` thấp; không coi đây là chẩn đoán tâm lý.
- **Memory có giới hạn:** chỉ ghi nhớ sự kiện quan trọng, có ID, thời gian, người liên quan, độ quan trọng; không lưu toàn bộ hội thoại không cần thiết.
- **Cinematic:** hoạt ảnh chuyển trạng thái mượt; cử chỉ phát theo ý định hội thoại.

### 5.4. Nguyên tắc viết nhân vật

Mỗi NPC chủ chốt cần một trang **character bible**: mục tiêu, điều sợ, điều chưa biết, cách nói chuyện, mối quan hệ, hoàn cảnh chỉ tiết lộ khi có cơ sở, đường phát triển 5 ngày, giới hạn nội dung. Không gắn nhãn học sinh là “hư”, “yếu” hoặc dùng điểm số để mô tả giá trị của các em.

---

## 6. Vòng lặp gameplay: tình huống mở ở **mọi** bước

### 6.1. Vòng lặp tổng quát

```text
[1. QUAN SÁT, NHẬN TÍN HIỆU]
              ↓
[2. LẬP KẾ HOẠCH, CHUẨN BỊ]
              ↓
[3. DI CHUYỂN, TƯƠNG TÁC NPC/ĐỒ VẬT]
              ↓
[4. THỰC HIỆN NHIỆM VỤ NGHỀ NGHIỆP]
              ↓
[5. PHÁT SINH VẤN ĐỀ, ỨNG BIẾN]
              ↓
[6. THU PHẢN HỒI, ĐIỀU CHỈNH]
              ↓
[7. GHI NHẬN HỆ QUẢ, PHẢN TƯ, CHUYỂN NGÀY]
              ↺ Tình huống tiếp theo dựa trên thế giới đã thay đổi
```

**Yêu cầu cứng:** Không bước nào được triển khai chỉ như màn hình “Nhấn E để tiếp tục”. Mỗi bước có ít nhất một **tình huống phát sinh chủ động** và một **cơ hội mở qua khám phá**, nhiều cách xử lý, tác động đến trạng thái. Bảng dưới là **ma trận tình huống bắt buộc**.

| Bước | Hai tình huống mở mẫu | Điều người chơi thật sự quyết định | Hệ quả nối sang bước sau |
|---|---|---|---|
| **1. Quan sát** | Học sinh đến muộn với vẻ lo lắng; lịch dạy bất ngờ đổi | Có tìm hiểu trước không, hỏi ai, ưu tiên dấu hiệu nào | Mở thông tin về học sinh / nhận cảnh báo sai lịch |
| **2. Chuẩn bị** | Máy chiếu lỗi; tài liệu phù hợp lớp 9A chưa sẵn | Đổi phương pháp, tự sửa, mượn thiết bị, điều chỉnh kế hoạch | Ảnh hưởng công cụ và thời gian trong tiết |
| **3. Di chuyển/tương tác** | Đồng nghiệp nhờ giúp đúng giờ lên lớp; Minh đứng một mình ngoài hành lang | Dừng lại, hẹn sau, nhờ người khác, hỏi kín đáo | Quan hệ, thời gian, nhiệm vụ phụ |
| **4. Thực hành nghề** | Một nhóm học rất nhanh, nhóm khác bối rối; Nam phản biện | Phân hóa nhiệm vụ, thay cách giải thích, dành thời gian thảo luận | Mức độ hiểu, tham gia, tiến độ |
| **5. Ứng biến** | Hai học sinh tranh cãi; máy chiếu tắt giữa giờ | Bình ổn, tách vấn đề, dùng học liệu dự phòng, gọi hỗ trợ khi cần | Bầu không khí, niềm tin, tài nguyên còn lại |
| **6. Phản hồi/điều chỉnh** | Phiếu cuối giờ cho thấy hiểu sai; phụ huynh chất vấn một nhận xét | Kiểm tra bằng chứng, sửa cách dạy/nhận xét, đối thoại | Nhiệm vụ học bù, phụ huynh tin tưởng hay lo ngại |
| **7. Hệ quả/phản tư** | Chưa chấm hết bài; Minh gửi lời nhắn muộn | Ưu tiên sáng mai, nghỉ ngơi, phản tư, lên lịch hỗ trợ | Năng lượng ngày sau, mở sự kiện tiếp theo |

### 6.2. Bước 1 — QUAN SÁT & NHẬN TÍN HIỆU

**Mục tiêu trải nghiệm:** người chơi đọc được môi trường bằng mắt và tai, đặt giả thuyết thay vì được hệ thống đưa sẵn lời giải.

#### Sự kiện OBS-01: “Một buổi sáng có gì đó khác”

- **Kích hoạt:** 06:45, người chơi vào sân trường. Bình thường học sinh trò chuyện; hôm nay Minh đứng tách nhóm, nhìn điện thoại rồi cất vội.
- **Điều chưa rõ:** em đang mệt, chờ người thân, có xung đột hay chỉ cần không gian riêng?
- **Cách tiếp cận mở:** quan sát thêm vài giây; chào em một cách bình thường; hỏi chuyện kín đáo khi thích hợp; trao đổi với cô Mai về những điều đã biết; bỏ qua để kịp phòng giáo viên rồi tìm lúc khác.
- **Tương tác 3D:** quay camera nhìn nét mặt; đi gần nhưng không xâm phạm; chọn lời chào; NPC đáp lại bằng animation và giọng điệu khác nhau.
- **Hệ quả tức thì:** Minh tiếp tục hành vi tùy mức độ thoải mái; tạo `minh_observed=true` nếu người chơi thực sự chú ý.
- **Hệ quả muộn:** ở giờ ra chơi, có thể nhận ra đây là hiện tượng lặp lại và mở tuyến hỗ trợ đúng lúc. Không quan sát không bị trừ điểm tự động.
- **Tình huống phục hồi:** người chơi có thể trò chuyện vào giờ khác; sự kiện không biến mất vô lý.

#### Sự kiện OBS-02: “Bảng lịch dạy đã thay đổi”

- **Kích hoạt:** xem bảng thông báo hoặc nghe đồng nghiệp nhắc.
- **Điều chưa rõ:** đổi tiết chính thức hay chỉ là ghi chú tạm; lớp nào cần phòng máy?
- **Cách xử lý:** so sánh lịch trên sổ tay với bảng; xác nhận với nhân viên phụ trách; hỏi cô Mai; tự điều chỉnh rồi thông báo cho học sinh; vội lên phòng cũ.
- **Đánh đổi:** xác minh tốn ít phút nhưng ngăn chuẩn bị sai; hành động nhanh có thể đúng hoặc tạo sự nhầm lẫn.
- **Hệ quả:** `schedule_verified`, thay NPC có mặt tại lớp, thay thời điểm dạy; đồng nghiệp có thể giúp nếu đã được hỏi.

**Quy tắc bước 1:** thông tin không nằm trong một cửa sổ “chọn đáp án”. Bảng thông báo, biểu cảm NPC, đồ vật và hội thoại đều là nguồn bằng chứng. Người chơi tự quyết định **đã đủ hiểu để hành động chưa**.

### 6.3. Bước 2 — LẬP KẾ HOẠCH & CHUẨN BỊ

**Mục tiêu:** buộc người chơi biến thông tin thành kế hoạch có đánh đổi — không thể mang tất cả tài nguyên hoặc làm mọi thứ cùng lúc.

#### Sự kiện PREP-01: “Máy chiếu có thể không hoạt động”

- **Kích hoạt:** 07:00 tại phòng giáo viên; nhận thông báo hoặc kiểm tra trước thiết bị.
- **Nguồn lực:** bộ ảnh ngập sân trường, phiếu học tập, bảng viết, 1 máy chiếu dùng chung, đồng nghiệp có thể giúp.
- **Cách xử lý mở:** thử kiểm tra dây nguồn/đầu vào hợp lý; mượn máy khác nếu còn; chuẩn bị bài dùng bảng/ảnh in; chuyển sang hoạt động khảo sát nhỏ; điều chỉnh thứ tự tiết học; hỏi bộ phận thiết bị.
- **Tương tác:** cầm file/bản in, đặt vào cặp; sử dụng bảng kế hoạch kéo-thả hoạt động; kiểm tra trạng thái thiết bị ngay trong world.
- **Trade-off:** chuẩn bị phương án dự phòng mất thời gian nhưng giảm rủi ro; mượn thiết bị có thể làm ảnh hưởng lịch của người khác.
- **Hệ quả:** `backup_materials`, `projector_status`, `prep_minutes_spent`; quyết định được dùng trong `CLASS-02` và `CRISIS-02`.

#### Sự kiện PREP-02: “Một lớp, nhiều tốc độ học”

- **Kích hoạt:** mở sổ nhận xét bài trước của lớp 9A hoặc nghe cô Mai góp ý.
- **Cách xử lý:** chọn 2 mức phiếu bài tập; thêm câu hỏi mở cho nhóm nhanh; chia nhóm hỗn hợp; chuẩn bị minh họa trực quan; không điều chỉnh vì quá gấp.
- **Đánh đổi:** học liệu phân hóa tốt hơn nhưng giảm thời gian chuẩn bị nhiệm vụ khác.
- **Hệ quả:** `differentiation_ready`; nhóm học chậm có cơ hội tự làm, nhóm nhanh không phải ngồi chờ; thước đo là hoạt động trong lớp, không phải điểm thưởng vô điều kiện.

**Quy tắc bước 2:** mỗi phương án phải tạo **tài nguyên hoặc điều kiện chơi** khác nhau trong lớp. Không được chỉ thay câu thoại khen ngợi rồi vẫn dẫn đến cùng một scene.

### 6.4. Bước 3 — DI CHUYỂN & TƯƠNG TÁC

**Mục tiêu:** di chuyển có ý nghĩa; môi trường và NPC tạo xung đột ưu tiên thay vì bắt người chơi chạy tới marker vô tri.

#### Sự kiện MOVE-01: “Hai lời nhờ cùng lúc”

- **Kích hoạt:** 07:20 trên hành lang, thầy Quân cần hỗ trợ in tài liệu; Tuấn nói lớp đang cần giáo viên lên sớm.
- **Cách xử lý:** giúp ngay và báo lớp chờ; hẹn thầy Quân giờ ra chơi; chỉ chỗ máy in/hướng dẫn nhanh; nhờ cô Mai; ưu tiên vào lớp rồi gửi lời nhắn.
- **Hệ quả:** `colleague_cooperation`, `class_start_delay`, `promise_to_quan`; lời hứa phải được nhắc lại và có thể giữ hoặc thất hứa.
- **Thiết kế mở:** người chơi có thể **đi tiếp** mà không mở hội thoại. Không ép xuất hiện hộp thoại.

#### Sự kiện MOVE-02: “Một góc hành lang yên lặng”

- **Kích hoạt:** giờ ra chơi, Minh ngồi một mình; Hà đứng cách xa nhìn về phía lớp.
- **Cách xử lý:** nói chuyện kín đáo với Minh; dành thời gian quan sát; hỏi Hà xem có thông tin hữu ích không nhưng không khuyến khích đồn đoán; hỏi cô Mai cách tiếp cận; đi làm việc khác rồi quay lại.
- **Điều cần giữ:** không mặc định Minh đang gặp khủng hoảng. Game chỉ cung cấp thông tin khi nhân vật tin tưởng và hoàn cảnh phù hợp.
- **Hệ quả:** mở tuyến `STUDENT-MINH`; nếu xử lý thiếu tế nhị, cơ hội trò chuyện có thể chuyển sang ngày hôm sau.

**Quy tắc bước 3:** quản lý vị trí, khoảng cách, đường đi NPC, tầm nhìn, lịch hẹn; **không dùng teleport** để thay thế trải nghiệm tương tác trong bản chơi chính, trừ tính năng trợ năng/khôi phục lỗi.

### 6.5. Bước 4 — THỰC HIỆN NGHỀ: DẠY HỌC & CÔNG VIỆC

**Mục tiêu:** nghề giáo được thể hiện qua cơ chế độc đáo, không chỉ “đứng trước lớp rồi xem phim”.

#### Sự kiện CLASS-01: “Cả lớp không cùng tốc độ”

- **Kích hoạt:** 12 phút sau khi bắt đầu hoạt động vẽ biểu đồ khí hậu.
- **Dấu hiệu:** nhóm 1 giơ tay xong bài, nhóm 4 nhìn trục tung với vẻ bối rối; Nam muốn chuyển sang tranh luận.
- **Cách xử lý:** quan sát cụ thể nhóm 4; giao câu hỏi mở rộng nhóm 1; dùng bảng giải thích đơn vị; đề nghị nhóm 1 phản biện dữ liệu nhóm khác nhưng không làm hộ; đổi ví dụ; điều chỉnh lượng bài.
- **Thao tác 3D:** đi giữa bàn, xem phiếu, chỉ vào đồ thị trên bảng, đưa học liệu, nghe câu hỏi.
- **Hệ quả:** `student_understanding` cập nhật theo nhóm; `lesson_time` giảm theo hành động; nhóm không được quan tâm có thể mang hiểu nhầm sang `FEEDBACK-01`.

#### Sự kiện CLASS-02: “Câu hỏi làm chệch giáo án”

- **Kích hoạt:** Nam hỏi “Hôm nay lạnh thì sao nói Trái Đất nóng lên?”.
- **Cách xử lý:** lấy dữ liệu dài hạn đã chuẩn bị; cho lớp dự đoán rồi so sánh; tổ chức hỏi-đáp ngắn; giải thích ngắn và hẹn thảo luận sâu; thừa nhận chưa có đủ dữ liệu và kiểm tra sau.
- **Đánh đổi:** thảo luận có thể cải thiện hiểu biết nhưng chiếm thời gian; trả lời ngắn giúp giữ tiến độ nhưng cần xác nhận lại mức hiểu.
- **Hệ quả:** `nam_voice`, `climate_misconception_resolved`, `remaining_lesson_minutes`; có thể sinh câu hỏi cuối giờ.

#### Sự kiện CLASS-03: “Chấm bài có công bằng không?”

- **Kích hoạt:** 13:30 tại bàn giáo viên, gặp một bài có cách giải khác đáp án mẫu.
- **Cách xử lý:** đối chiếu tiêu chí; đọc lập luận học sinh; hỏi tổ chuyên môn khi cần; ghi nhận xét minh bạch; điều chỉnh hướng dẫn chung cho lớp.
- **Hệ quả:** `grading_rubric_consistency`, `feedback_quality`, bằng chứng trao đổi với phụ huynh ở `FEEDBACK-02`.

**Quy tắc bước 4:** có cơ chế chuyên môn tối thiểu: kế hoạch bài dạy, câu hỏi, nhiệm vụ nhóm, học liệu, thời gian, quan sát hiểu bài, kiểm tra tiêu chí chấm. Bỏ qua cơ chế này sẽ khiến game chỉ là visual novel trong mô hình 3D.

### 6.6. Bước 5 — SỰ CỐ & ỨNG BIẾN

**Mục tiêu:** khi kế hoạch không còn khớp thực tế, người chơi phải điều chỉnh dựa vào bối cảnh và các tài nguyên đã chuẩn bị.

#### Sự kiện CRISIS-01: “Tranh cãi giữa hai học sinh”

- **Kích hoạt:** hoạt động nhóm gần hết giờ, hai em cùng muốn thuyết trình, lời nói bắt đầu căng thẳng.
- **Cách xử lý:** tiếp cận và giảm kích động; lắng nghe cả hai; phân chia vai trò; cho nhóm lựa chọn cách đại diện; tạm hoãn trình bày để bình tĩnh; sử dụng quy tắc lớp đã thống nhất.
- **Hệ quả:** độ an toàn của lớp, quan hệ giữa hai học sinh, thời gian còn lại, độ tin tưởng vào sự công bằng.
- **Điều cấm:** không tạo lợi thế gameplay cho việc sỉ nhục công khai; lời nói thiếu chuẩn mực cần có phản ứng thực tế và cơ hội sửa chữa.

#### Sự kiện CRISIS-02: “Máy chiếu tắt giữa tiết”

- **Điều kiện:** chỉ xảy ra nếu đang dùng máy chiếu; mức khó phụ thuộc `backup_materials` từ bước chuẩn bị.
- **Cách xử lý:** dừng thiết bị an toàn; chuyển sang bảng/phiếu giấy; dùng hoạt động trao đổi; nhờ hỗ trợ thiết bị; tạm hoãn phần trực quan và đổi thứ tự bài.
- **Hệ quả:** nếu đã chuẩn bị dự phòng, hoạt động chuyển tiếp mượt; nếu chưa, vẫn có đường tiếp tục bằng bảng/đối thoại nhưng tiết học thay đổi.

#### Sự kiện CRISIS-03: “Một thông tin cần hỗ trợ nghiêm túc”

- **Kích hoạt:** một học sinh cho biết có dấu hiệu bị bắt nạt hoặc có nguy cơ mất an toàn.
- **Thiết kế:** cảnh được viết cẩn thận, không kịch tính hóa; người chơi cần lắng nghe, không hứa giữ bí mật vô điều kiện, ưu tiên an toàn và báo người có trách nhiệm theo quy trình nhà trường.
- **Hệ quả:** mở tuyến hỗ trợ chính thức; **không** cho phép người chơi tự điều tra công khai, ép học sinh đối chất hoặc công bố thông tin riêng tư để lấy điểm.

**Quy tắc bước 5:** biến cố phải có nguyên nhân và độ tin cậy rõ; không kích hoạt ngẫu nhiên đến mức gây bất công. Có thể cấu hình cường độ/tần suất sự kiện cho người chơi mới.

### 6.7. Bước 6 — THU PHẢN HỒI & ĐIỀU CHỈNH

**Mục tiêu:** người chơi phải nhận và xử lý bằng chứng trái với giả định ban đầu; không chỉ nhận một lời khen cuối nhiệm vụ.

#### Sự kiện FEEDBACK-01: “Bốn phút cuối tiết”

- **Kích hoạt:** còn 4 phút.
- **Nguồn dữ liệu:** bài nhóm, cử chỉ học sinh, phiếu cuối giờ (exit ticket), câu hỏi cuối bài.
- **Cách xử lý:** dùng phiếu một điều hiểu/một điều chưa rõ; hỏi ví dụ ứng dụng; chọn kiểm tra nhanh bằng bảng; dựa vào quan sát và cho lớp kết thúc; chọn đưa phần chưa hiểu sang tiết sau.
- **Hệ quả:** mở `misconception_followup` nếu nhiều em phân biệt sai thời tiết và khí hậu; giáo viên có dữ liệu để sửa tiết thứ hai.

#### Sự kiện FEEDBACK-02: “Phụ huynh muốn một lời giải thích”

- **Kích hoạt:** 15:00; phụ huynh phản ánh nhận xét hoặc điểm số.
- **Cách xử lý:** lắng nghe, xin phép kiểm tra hồ sơ/chứng cứ, trình bày tiêu chí, nhận sai nếu phát hiện lỗi, hẹn thời gian phản hồi cụ thể, đề xuất hỗ trợ chung.
- **Nguồn lực:** bài đã chấm, rubric, ghi chú từ tiết học, thông tin phụ huynh cung cấp.
- **Hệ quả:** `parent_confidence`, `grading_review_needed`, cuộc hẹn tiếp theo; không đánh đồng người đặt câu hỏi với người đối đầu.

**Quy tắc bước 6:** người chơi có thể đổi phương án, thừa nhận mình chưa biết hoặc sửa quyết định trước. **Khả năng sửa sai là một năng lực được ghi nhận**, không phải cách reset hậu quả miễn phí.

### 6.8. Bước 7 — HỆ QUẢ, PHẢN TƯ & CHUYỂN NGÀY

**Mục tiêu:** hệ quả cụ thể được giữ lại, trở thành đầu vào cho vòng lặp tiếp theo. Phản tư là hoạt động chơi có lựa chọn, không phải một popup “Bạn được 85 điểm”.

#### Sự kiện REFLECT-01: “Sổ công việc còn dang dở”

- **Kích hoạt:** 16:30 tại phòng giáo viên.
- **Vấn đề:** còn 8 bài chưa chấm, tài liệu mai chưa hoàn thiện, năng lượng đang thấp.
- **Cách xử lý:** lập danh sách ưu tiên và hẹn hoàn thành; chấm thêm một phần; nghỉ ngơi và chuẩn bị sáng mai; nhờ đồng nghiệp góp ý cách tổ chức chứ không nhờ chấm thay thiếu trách nhiệm.
- **Hệ quả:** `tomorrow_workload`, `energy_next_day`, `promises_due`; không coi nghỉ ngơi là hành vi xấu.

#### Sự kiện REFLECT-02: “Một lời nhắn sau giờ học”

- **Kích hoạt:** cuối ngày Minh để lại tin nhắn: “Em có thể nói chuyện với thầy/cô vào ngày mai không?”. Chỉ hiện nếu mạch truyện phù hợp.
- **Cách xử lý:** xác nhận sẽ sắp xếp giờ phù hợp, đề nghị kênh trao đổi an toàn, ghi hẹn trong sổ, không phản hồi ngay vì hết giờ nhưng xử lý vào sáng hôm sau.
- **Hệ quả:** `minh_appointment_day2`, `promise_minh`; chuyển câu chuyện từ bức tranh “học sinh mất tập trung” sang quan hệ tin tưởng phát triển chậm.

#### Sự kiện REFLECT-03: “Bạn đã thực sự học được gì?”

- **Tương tác:** người chơi chọn tối đa 2 tình huống đáng nhớ trong nhật ký và ghi một phản tư ngắn (tùy chọn). Báo cáo tự động đối chiếu hành động với mục tiêu đã đặt ra.
- **Hệ quả:** mở gợi ý chuẩn bị của ngày mai, lưu `reflection_tags` và tiến độ; không dùng văn bản phản tư để chẩn đoán người chơi.

**Quy tắc bước 7:** lưu trạng thái toàn bộ nhánh, lịch hẹn và nhiệm vụ chưa hoàn thành. Chỉ chuyển ngày sau khi xác định checkpoint nhất quán; có thể kết thúc ngày sớm theo tình huống hợp lệ và hệ thống ghi nhận công việc chưa xong.

### 6.9. Quy tắc kiểm tra vòng lặp mở

Trước khi nghiệm thu **bất kỳ** bước nào, QA phải trả lời “Có” cho 8 câu:

- Có thể phát hiện tình huống trong thế giới 3D mà không đọc sẵn câu trả lời?
- Có ít nhất ba **loại hành động** khác nhau, không chỉ ba câu thoại cùng tác dụng?
- Có thể hỏi thêm, quan sát thêm hoặc tìm thông tin phù hợp không?
- Có đánh đổi thời gian/nguồn lực/quan hệ/kiến thức hợp lý không?
- NPC và môi trường phản ứng ngay sau lựa chọn không?
- Một hệ quả có thể xuất hiện ở bước sau hoặc ngày sau không?
- Có lối chơi tiếp hoặc sửa sai nếu người chơi xử lý chưa tốt không?
- Có ghi nhận bằng chứng cụ thể cho phần phản hồi nghề nghiệp không?

---

## 7. Lịch một ngày làm giáo viên

Lịch dưới là **kịch bản mẫu**; trường thật có nhiều lịch dạy khác nhau. Đồng hồ game tăng tốc khi di chuyển/hoạt động thường và có thể chậm/tạm dừng ở đối thoại quan trọng, menu hoặc sự kiện nhạy cảm.

| Thời gian thế giới | Hoạt động chính | Sự kiện linh hoạt / nhiệm vụ phụ | Checkpoint |
|---|---|---|---|
| **06:45–07:00** | Đến cổng trường; quan sát sân | `OBS-01`; chào bảo vệ; nghe tin đổi tiết | CP-01 |
| **07:00–07:25** | Phòng giáo viên, nhận tài liệu, chuẩn bị | `OBS-02`, `PREP-01`, `PREP-02` | CP-02 |
| **07:25–07:30** | Đi lên lớp 9A | `MOVE-01`; trao đổi với lớp trưởng | — |
| **07:30–08:15** | Tiết 1: biến đổi khí hậu | `CLASS-01`, `CLASS-02`, `CRISIS-02` có điều kiện | CP-03 |
| **08:15–09:00** | Công việc chuyển tiết; ra chơi | `MOVE-02`, trò chuyện với Minh/Hà | — |
| **09:00–09:45** | Tiết 2: lớp khác hoặc hoạt động củng cố | Tùy kết quả tiết trước, thử phương án mới | CP-04 |
| **09:45–11:30** | Hoàn thiện kế hoạch, họp ngắn | Mentor nhận xét; tình huống điều phối công việc | — |
| **11:30–13:30** | Nghỉ trưa | Ăn trưa, nghỉ, trao đổi đồng nghiệp tùy chọn | CP-05 |
| **13:30–14:45** | Chấm bài, chuyên môn | `CLASS-03`; xử lý bài khác đáp án mẫu | — |
| **15:00–16:00** | Gặp phụ huynh/trao đổi về học sinh | `FEEDBACK-02`; tuyến hỗ trợ học sinh nếu phù hợp | CP-06 |
| **16:00–16:45** | Tổng kết, lên lịch ngày mai | `REFLECT-01`, `REFLECT-02`, `REFLECT-03` | CP-07 |

### 7.1. Kiểm soát nhịp độ

- Không tạo “thời gian chết” 30 phút chỉ để người chơi đi từ phòng này sang phòng khác.
- Có thể **fast-forward** các hoạt động lặp như ăn trưa, chấm tập bài giống nhau; các sự kiện quan trọng vẫn có tương tác.
- Sự kiện tự nhiên ưu tiên phát tại vị trí/khung giờ hợp lý. Nếu người chơi đến muộn, NPC có thể đã di chuyển hoặc để lại dấu hiệu.
- Có bảng lịch cho biết **việc cần làm** nhưng không tiết lộ mọi tình huống sắp xảy ra.
- Quy tắc ngắt ưu tiên: an toàn học sinh > nhiệm vụ bắt buộc theo giờ > sự kiện quan hệ > nhiệm vụ phụ. Không “phạt” người chơi vì giải quyết tình huống an toàn chính đáng.

### 7.2. Ví dụ một phiên chơi không theo tuyến cố định

Hai người chơi cùng thấy máy chiếu có vấn đề. Người A chuẩn bị bản in rồi dạy học bằng phiếu nhóm; Người B mượn được máy nhưng đến lớp muộn vài phút. Trong tiết, Nam phản biện; Người A dành thời gian cho dữ liệu trên bảng, Người B dùng biểu đồ máy chiếu nhưng lỡ nhịp hoạt động nhóm. Cuối tiết, hai người nhận **bằng chứng khác nhau**, dẫn tới **những việc chuẩn bị khác nhau** cho ngày hôm sau. Không có một đường chơi “duy nhất đúng”; việc đánh giá dựa trên chất lượng lựa chọn trong bối cảnh.

---

## 8. Ba chuỗi cốt truyện liên kết sâu

### 8.1. ARC A — “Minh có thật sự không tập trung?”

**Chủ đề:** nhìn vượt qua hành vi bề mặt; xây dựng tin cậy và ranh giới nghề nghiệp.

```text
06:45 Quan sát Minh đứng riêng (OBS-01)
        ├─ Có quan sát tế nhị → ghi nhận một dấu hiệu
        └─ Không quan sát → thông tin sẽ xuất hiện muộn hơn
07:40 Minh mất tập trung trong giờ
        ├─ Tiếp cận nhẹ nhàng → có thể quay lại bài
        ├─ Gọi tên trước lớp → Minh có thể phòng vệ
        └─ Chuyển tương tác riêng sang giờ phù hợp
08:30–09:00 Hành lang (MOVE-02)
        ├─ Có sự tin cậy → Minh có thể chia sẻ một phần
        └─ Thiếu sự tin cậy → chỉ có dấu hiệu, cần kiên nhẫn
15:30 Giáo viên xem lại thông tin/trao đổi phù hợp
16:30 Lời nhắn hoặc lịch hẹn ngày mai (REFLECT-02)
Ngày 2 → tiếp tục hỗ trợ, không có phép màu “giải quyết xong”.
```

**Các điểm khác biệt:** nếu người chơi đã sỉ nhục Minh, cần có lựa chọn nhận lỗi và thay đổi cách tiếp cận. Không suy luận rằng Minh có một “bệnh lý” từ hành vi mất tập trung. Khi nội dung vượt quá khả năng giải quyết của giáo viên, gameplay giới thiệu tuyến hỗ trợ phù hợp.

**Biến:** `minh_trust`, `minh_observed`, `public_callout`, `repair_attempted`, `minh_appointment_day2`.

### 8.2. ARC B — “Tiết học có phải là chạy hết giáo án?”

**Chủ đề:** chuyên môn, thời gian và sự tiếp thu của học sinh.

```text
07:00 Xem thông tin lớp và chuẩn bị học liệu (PREP-02)
07:30 Mở bài bằng ảnh sân trường / câu hỏi / hình thức khác
07:45 Nhóm học không đồng đều (CLASS-01)
07:52 Nam đặt câu hỏi phản biện (CLASS-02)
08:11 Còn 4 phút (FEEDBACK-01)
09:00 Tiết dạy tiếp theo dùng kết quả để điều chỉnh
16:30 Nhật ký: điều gì tạo ra hiểu biết thật?
Ngày 2 → nhiệm vụ củng cố kiến thức chưa vững.
```

**Hậu quả hữu hình:** nhóm học sinh ở các mức tiếp thu khác nhau cho ra phiếu cuối giờ khác nhau; nếu người chơi không có dữ liệu kiểm tra, báo cáo ghi rõ “chưa đủ bằng chứng”, không tự cho điểm tối đa. Có thể hoàn thành giáo án đúng giờ mà vẫn còn hiểu lầm; cũng có thể chậm một hoạt động nhưng khắc phục được hiểu lầm quan trọng.

**Biến:** `differentiation_ready`, `climate_misconception_resolved`, `class_understanding_by_group`, `class_participation`, `lesson_progress`.

### 8.3. ARC C — “Công bằng không chỉ là con số”

**Chủ đề:** chấm bài có tiêu chí, minh bạch với gia đình và khả năng nhận lỗi.

```text
13:30 Gặp bài làm khác đáp án mẫu (CLASS-03)
        ├─ Đối chiếu rubric, xem lập luận → có bằng chứng
        ├─ Kết luận quá nhanh → có thể bỏ sót cách làm hợp lý
        └─ Xin ý kiến tổ chuyên môn → tốn thời gian nhưng rõ hơn
15:00 Phụ huynh hỏi về nhận xét (FEEDBACK-02)
        ├─ Dữ liệu đủ → trao đổi có căn cứ
        ├─ Chưa đủ → hẹn kiểm tra lại, không đoán
        └─ Phát hiện sai → sửa minh bạch, ghi nhận
16:30 Theo dõi lời hứa, lịch phản hồi
Ngày 3 → kiểm tra/điều chỉnh cách đánh giá.
```

**Biến:** `grading_rubric_consistency`, `grading_review_needed`, `parent_confidence`, `parent_followup_due`, `admitted_error`.

### 8.4. Đồ thị các hệ quả chéo

- Chưa chuẩn bị học liệu → giờ dạy chậm → ít thời gian thu phản hồi → hôm sau cần củng cố nhiều hơn.
- Chạy theo tiến độ quá nhanh → học sinh bối rối → chấm bài lộ nhiều lỗi chung → phải đổi cách dạy.
- Lắng nghe Minh đúng lúc → tăng tin tưởng → Minh có thể chủ động đưa thông tin ở ngày sau.
- Hứa với thầy Quân nhưng quên → đồng nghiệp ít chủ động hỗ trợ; có thể sửa quan hệ bằng xin lỗi và giữ lời lần sau.
- Thiếu bằng chứng chấm điểm → phụ huynh cần được hẹn phản hồi thay vì được thuyết phục tức thì.
- Nghỉ ngơi hợp lý → giảm mệt mỏi mô phỏng, giúp giữ nhịp làm việc hôm sau; không biến game thành phần mềm khuyến khích làm quá giờ.

---

## 9. Chiến dịch năm ngày và kết thúc mở

| Ngày | Tên chương | Trục nghề nghiệp | Sự kiện mới / hậu quả được gọi lại |
|---|---|---|---|
| **Thứ Hai** | Người thầy mới | Làm quen trường, dạy học, quan sát lớp | Chuẩn bị, Minh, Nam, phụ huynh, phản tư |
| **Thứ Ba** | Điều chưa nói | Tìm hiểu học sinh, phối hợp trong trường | Hẹn với Minh, điều chỉnh bài dạy, họp tổ |
| **Thứ Tư** | Một bài kiểm tra | Đánh giá, công bằng, khác biệt người học | Bài làm sáng tạo, phản hồi của phụ huynh |
| **Thứ Năm** | Áp lực từ nhiều phía | Hồ sơ, thời gian, lịch đột xuất, giới hạn cá nhân | Lời hứa, công việc dồn, nhờ đồng nghiệp |
| **Thứ Sáu** | Tiết học của chúng ta | Hoạt động tổng hợp và nhìn lại tiến bộ | Nhánh học sinh và đồng nghiệp hội tụ |

**Quy tắc kết thúc:** không chấm một “kết thúc tốt” duy nhất. Báo cáo theo nhiều chiều: chất lượng trải nghiệm học tập, niềm tin, công bằng, tự điều chỉnh, quản lý công việc, khả năng phối hợp và giới hạn bản thân.

**Bốn kiểu tổng kết minh họa:** (1) Lớp cùng tiến bộ; (2) Chuyên môn vững nhưng cần kết nối thêm; (3) Tận tâm nhưng đang quá tải; (4) Biết nhận ra và sửa sai. Những kiểu này **không phải nhãn định danh cố định của người chơi**. Chỉ đưa nhận xét gắn với hành vi quan sát được, có ví dụ và gợi ý trải nghiệm lại.

**Replayability:** người chơi có thể bắt đầu lại với hồ sơ riêng, thử chiến lược khác; điều kiện sinh sự kiện thay đổi theo trạng thái, nhưng **không** random hóa các tình huống an toàn quan trọng tùy tiện.

---

## 10. Hệ thống gameplay, luật chơi và phản hồi

### 10.1. Danh sách hệ thống P0/P1

| Hệ thống | P0 cho vertical slice | P1 mở rộng |
|---|---|---|
| Character Controller | Đi, chạy nhẹ, camera thứ ba, va chạm, prompt tương tác | Tùy biến avatar nâng cao |
| Interactables | Cửa, bảng, giáo án, bàn giáo viên, phiếu bài | Thêm thiết bị thư viện/phòng y tế |
| School Clock | Các mốc hoạt động, tạm dừng đối thoại, checkpoint | Ngày/tuần và lịch thay đổi |
| NPC Scheduler | 8–10 NPC chính, lịch đơn giản, animation hợp ngữ cảnh | NPC nền đa dạng, hành vi phong phú |
| Dialogue System | Ý định hội thoại, điều kiện, cử chỉ, phụ đề | Voice đầy đủ, nhiều ngôn ngữ |
| Quest/Events | Trigger theo vùng, thời gian, sự kiện/biến | Bộ biên tập nội dung cho tác giả |
| Classroom Simulator | Bài giảng, hoạt động nhóm, quan sát, hiểu bài | Nhiều môn/nhiều lớp |
| Relationship/Memory | Quan hệ có giới hạn, sự kiện ghi nhớ | Mạng quan hệ phức tạp hơn |
| Save/Report | Lưu tự động, resume, nhật ký, báo cáo có chứng cứ | Phân tích nhiều lần chơi |
| Accessibility | Phụ đề, điều khiển rõ, âm lượng, đồ họa thấp | Tùy chỉnh nâng cao |

### 10.2. Mô hình trạng thái game

**Không dùng một điểm “giỏi giáo viên” duy nhất.** Tách thành:

- **Trạng thái thế giới:** thời gian, địa điểm, lịch, cửa/máy chiếu, tài nguyên.
- **Trạng thái hoạt động:** mục tiêu tiết học, tiến độ, thời gian còn lại, học liệu đã dùng.
- **Trạng thái theo nhóm:** hiểu bài ước tính và mức tham gia, có sai số; không hiển thị chắc chắn nếu chưa có bằng chứng.
- **Trạng thái quan hệ:** niềm tin/tính sẵn sàng nói chuyện, thay đổi theo nhiều sự kiện.
- **Trạng thái nghề nghiệp:** kế hoạch, nhiệm vụ chưa xong, lịch hẹn, năng lượng.
- **Bằng chứng năng lực:** sự kiện cụ thể chứng minh phân tích, sáng tạo, thấu cảm, ra quyết định, chính xác.

Ví dụ khởi tạo cho **bản thử** (không phải dữ liệu thật):

```json
{
  "day": 1,
  "clock": "07:00",
  "world": { "projectorWorking": false, "backupMaterials": false },
  "lesson": { "progress": 0, "minutesRemaining": 45 },
  "relationships": { "minh": 50, "mentorMai": 50, "parentThu": 50 },
  "evidence": [],
  "flags": { "scheduleVerified": false, "promisedToQuan": false }
}
```

**Chú ý:** con số nội bộ chỉ hỗ trợ điều kiện, không dùng nó để đo giá trị con người hoặc khẳng định tình trạng tâm lý. Với các sự kiện nhạy cảm, trạng thái và lời thoại phải được thẩm định độc lập.

### 10.3. Luật quản lý thời gian

- Hoạt động thông thường tiêu thụ thời gian theo bảng dữ liệu; hội thoại quan trọng có thể tạm dừng thời gian để người chơi đọc.
- Có giới hạn mềm, không dùng đồng hồ đếm ngược gây stress liên tục.
- Khi lịch xung đột, người chơi có thể **ủy quyền, xin hỗ trợ, hẹn lại hoặc chấp nhận việc chưa hoàn thành**.
- Không cho phép làm việc ngoài giờ vô hạn để farm điểm; sức khỏe và quản lý giới hạn công việc là bài học tích cực.

### 10.4. Cơ chế lớp học phải thực sự tương tác

1. **Bố trí kế hoạch:** mở bài, mục tiêu, học liệu, hoạt động nhóm, kết thúc.
2. **Đi lại và quan sát:** đi tới bàn cụ thể; quan sát bài/biểu cảm.
3. **Giảng dạy:** chọn ví dụ, minh họa, viết bảng/chiếu slide.
4. **Đặt câu hỏi:** hỏi toàn lớp, nhóm nhỏ, hoặc học sinh phù hợp mà không làm mất mặt.
5. **Tổ chức nhóm:** giao nhiệm vụ theo mức độ, thời lượng, vai trò.
6. **Xử lý phát sinh:** hệ thống đưa tín hiệu thông qua NPC; hành động gây hệ quả.
7. **Đánh giá cuối tiết:** thu sản phẩm học tập, tìm hiểu lỗ hổng, điều chỉnh tiết sau.

**Giới hạn MVP:** chưa cần nhận dạng tiếng nói, nhận dạng chữ viết trên bảng hay hiểu mọi câu người chơi nói. Các hành động có menu ngữ cảnh vừa đủ, diễn ra bằng animation 3D và quy tắc chuyên môn rõ ràng.

### 10.5. Phản hồi và báo cáo hướng nghiệp

Sau ngày chơi, báo cáo phải chứa:

- **Bạn đã làm gì:** 5–8 sự kiện kèm thời gian, hành động và hoàn cảnh.
- **Điều có hiệu quả:** dẫn bằng chứng như “Bạn đưa dữ liệu so sánh thời tiết/khí hậu sau khi Nam đặt câu hỏi”.
- **Điều cần cải thiện:** không đổ lỗi, có hành động thử lần sau.
- **Tác động chưa chắc chắn:** ghi “chưa đủ dữ liệu” nếu không quan sát/kiểm tra.
- **Năm nhóm năng lực tham khảo** từ JOBSIMU hiện tại: phân tích, sáng tạo, thấu cảm, ra quyết định, chính xác.
- **Tự phản tư:** người chơi đánh dấu tình huống khiến họ hứng thú hoặc áp lực.
- **Liên hệ nghề thực:** công việc nào thường gặp, công việc nào đặc thù, cần tìm hiểu thực tế thêm ở đâu.

Tránh các câu như “Bạn chắc chắn có tố chất làm giáo viên” hoặc “Bạn không phù hợp nghề này” chỉ vì một màn chơi.

---

## 11. Thiết kế UX/UI, camera, điều khiển và âm thanh

### 11.1. Điều khiển mặc định

| Phím | Hành động |
|---|---|
| `W A S D` | Di chuyển |
| Chuột | Xoay camera |
| `Shift` | Đi nhanh trong khu vực phù hợp |
| `E` | Tương tác đối tượng/NPC gần |
| `Tab` | Sổ nhiệm vụ, lịch và bản đồ |
| `J` | Nhật ký và ghi chú |
| `Esc` | Tạm dừng, tùy chỉnh, thoát |
| Click trái | Chọn UI/đối tượng có thể tương tác |

Có thể đổi phím. Tương tác hiển thị bằng dấu hiệu nhẹ khi đứng trong tầm; không spam popup che khuất scene. Camera tự tránh vật thể, có tùy chọn đảo trục/độ nhạy và chế độ giảm chuyển động.

### 11.2. Bố cục HUD

```text
┌────────────────────────────────────────────────────────────────┐
│  07:42  |  Thứ Hai  |  Tiết 1                  [LỊCH] [SỔ]  │
│                                                                │
│                    [KHÔNG GIAN 3D LỚP 9A]                      │
│                                                                │
│  Nhiệm vụ hiện tại: Quan sát các nhóm làm biểu đồ               │
│  [E] Xem phiếu của nhóm 4                                      │
│                                                                │
│  Lời thoại/tiếng động môi trường + phụ đề (nếu có)              │
└────────────────────────────────────────────────────────────────┘
```

HUD ưu tiên ngữ cảnh, không hiển thị liên tục năm thanh điểm khiến lớp học biến thành máy tính điểm. Chỉ báo khẩn cấp dùng riêng cho trường hợp thực sự cần chú ý. Có chế độ đồ họa thấp, phụ đề, font chữ dễ đọc và tương phản kiểm soát.

### 11.3. Âm thanh và cảm giác sống động

- **Ambience:** sân trường buổi sáng, trò chuyện xa, chim, quạt trần, hành lang, giấy lật, tiếng phấn/bút, trống hoặc chuông phù hợp.
- **Foley:** bước chân theo vật liệu sàn, cửa, lật giáo án, đặt sách.
- **NPC barks:** lời chào/nhắc ngắn theo thời gian và quan hệ; tránh lặp vô hạn.
- **Music:** tiết chế, ưu tiên không gian trường; nhạc làm nền ở mở đầu/kết thúc hoặc menu.
- **Voice:** P0 cho vài cảnh trọng tâm nếu có tài nguyên; luôn có phụ đề, không lệ thuộc vào audio để hiểu tình huống.

### 11.4. Camera và cinematic

Camera thường đặt sau vai với khoảng cách phù hợp; khi hội thoại, chuyển mượt sang góc hai nhân vật. Trong lớp có thể chuyển sang góc xem bảng/phiếu. Cắt cảnh ngắn, có nút bỏ qua đoạn đã xem và không bỏ qua thông tin an toàn thiết yếu.

---

## 12. Kiến trúc kỹ thuật và tích hợp repository

### 12.1. Stack đề xuất

- **Client:** Babylon.js + TypeScript, Vite.
- **3D art:** Blender; định dạng GLB/glTF, texture tối ưu.
- **Backend:** PHP hiện có, API trả JSON, xác thực phiên đăng nhập.
- **Database:** MySQL/MariaDB hiện có, migrations riêng cho game.
- **Deployment:** chạy build trên máy/CI, upload file tĩnh game và API PHP lên hosting hiện tại khi đủ tài nguyên; cân nhắc CDN/object storage nếu asset lớn.
- **Testing:** unit test cho event logic, integration test PHP API, E2E luồng game và kiểm thử trải nghiệm trên trình duyệt thật.

Babylon.js hỗ trợ engine scene, camera, picking, animation, collision và hệ render web. Vite là công cụ build; **cần bổ sung `tsc --noEmit` riêng** vì Vite không thay thế kiểm tra kiểu TypeScript.

### 12.2. Kiến trúc các lớp

```text
Website PHP (trắc nghiệm / thư viện nghề / tài khoản / báo cáo)
                │ cùng origin + session
                ▼
Game Entry Page ────────────► Babylon.js Client (TypeScript)
                                   │
                       ┌───────────┼────────────┐
                       ▼           ▼            ▼
                 World/Player   NPC/Dialogue  Quest/Event Engine
                       └───────────┼────────────┘
                                   ▼
                             Client State
                                   │ HTTPS JSON
                                   ▼
                          PHP Game API (auth, validate)
                                   │
                                   ▼
                             MySQL Save Data
```

**Phân vai chịu trách nhiệm:** client xử lý render, chuyển động, camera, animation và phản ứng tức thì; API xác thực người dùng, ràng buộc dữ liệu, tính/kiểm tra các kết quả quan trọng và lưu bền. Không chấp nhận điểm tùy ý do client gửi lên như bằng chứng thành tích.

### 12.3. Cấu trúc thư mục đích

```text
jobsimu/
├── app/                              # PHP website hiện tại
│   ├── bootstrap.php
│   ├── functions.php
│   ├── simulations.php
│   └── simulation-controller.php
├── assets/                           # CSS/JS giao diện website hiện tại
├── game/
│   ├── index.html
│   ├── src/
│   │   ├── main.ts
│   │   ├── core/                     # Game, SceneManager, EventBus, State
│   │   ├── player/                   # Controller, Camera, Interactor
│   │   ├── world/                    # SchoolScene, Clock, Interactables
│   │   ├── npc/                      # NPCController, Scheduler, Behavior
│   │   ├── narrative/                # StoryGraph, Conditions, Consequences
│   │   ├── systems/                  # Class, Quest, Dialogue, Save, Audio
│   │   ├── ui/                       # HUD, Menus, Subtitle, Report
│   │   └── api/                      # HTTP client typed
│   ├── public/assets/
│   │   ├── characters/
│   │   ├── environments/
│   │   ├── props/
│   │   ├── animations/
│   │   ├── textures/
│   │   └── audio/
│   ├── content/
│   │   ├── characters/
│   │   ├── days/
│   │   ├── events/
│   │   └── dialogues/
│   ├── tests/
│   ├── package.json
│   ├── tsconfig.json
│   └── vite.config.ts
├── api/game/
│   ├── start.php
│   ├── state.php
│   ├── events.php
│   ├── save.php
│   └── finish.php
├── database/
│   ├── schema.sql
│   └── migrations/
│       └── 2026_11_game3d_init.sql
├── docs/
│   ├── game3d.md                    # Bản sao tài liệu này khi đưa vào repo
│   ├── STORY_BIBLE.md
│   └── ASSET_MANIFEST.md
├── index.php
└── README.md
```

**Ghi chú build:** `game/` là dự án nguồn; build Vite tạo `game/dist/`. Chỉ triển khai file build với đường dẫn `base` phù hợp thư mục con của cPanel. Không tự động đặt cả `node_modules` hay các tệp source nhạy cảm vào `public_html` nếu không cần. API đặt tại URL cùng origin để sử dụng session/cookie hiện có đúng cách.

### 12.4. Tích hợp website và routing

1. Trong trang nghề `giao-vien`, thêm **“Trải nghiệm 3D”** bên cạnh **“Mô phỏng nhanh”**.
2. Chỉ cho phép tạo phiên có tài khoản đăng nhập; có thể bổ sung demo khách không lưu nếu cần.
3. Khi nhấn nút, backend tạo `game_run` và trả mã run; người chơi vào game.
4. Game nhận nội dung ngày 1, tải scene, NPC và snapshot nếu đã chơi.
5. Mỗi checkpoint gửi state/sự kiện lên API.
6. Khi xong, backend tính báo cáo và hiển thị ở dashboard nghề.
7. Các nghề khác tiếp tục dùng mô phỏng hiện có, không bị ảnh hưởng.

---

## 13. Thiết kế dữ liệu, sự kiện và lưu game

### 13.1. Bảng dữ liệu dự kiến

| Bảng | Vai trò và khóa quan trọng |
|---|---|
| `game_runs` | `id`, `user_id`, `career_id`, `content_version`, `day_index`, `status`, `started_at`, `updated_at` |
| `game_save_snapshots` | `run_id`, `revision`, `snapshot_json`, `saved_at` |
| `game_events` | `id`, `run_id`, `event_seq`, `event_id`, `action_id`, `payload_json`, `created_at` |
| `game_npc_states` | `run_id`, `npc_id`, `state_json`, `updated_at` |
| `game_quests` | `run_id`, `quest_id`, `status`, `progress_json` |
| `game_reports` | `run_id`, `report_version`, `summary_json`, `completed_at` |

**Chiến lược:** sự kiện là nhật ký bất biến ở mức hợp lý; snapshot là trạng thái tổng hợp để tải nhanh. Không lưu mỗi frame hay tọa độ NPC hàng chục lần/giây. Giới hạn payload, phiên bản schema, hỗ trợ phát lại tình huống khi cần debug.

### 13.2. Hợp đồng sự kiện mở

Một sự kiện có ID ổn định, điều kiện kích hoạt, dấu hiệu/đồ vật, nhiều **action** có thể đi cùng nhau, tác động tức thì và tác động trì hoãn. Ví dụ **minh họa**, chưa phải file có thể chạy trực tiếp:

```json
{
  "id": "PREP-01",
  "version": 1,
  "title": "Máy chiếu có thể không hoạt động",
  "window": { "day": 1, "from": "07:00", "to": "07:30" },
  "location": "staff_room",
  "conditions": [{ "key": "lesson.day1.started", "op": "eq", "value": false }],
  "clues": ["note_projector", "mentor_mai_dialogue", "equipment_desk"],
  "actions": [
    { "id": "check_projector", "type": "interact", "target": "projector", "costMinutes": 3 },
    { "id": "prepare_paper", "type": "craft_plan", "target": "handouts", "costMinutes": 6 },
    { "id": "ask_equipment_team", "type": "dialogue", "target": "staff_equipment", "costMinutes": 4 },
    { "id": "change_lesson_plan", "type": "edit_lesson", "target": "lesson_plan", "costMinutes": 4 }
  ],
  "consequences": [
    { "when": "action_completed:prepare_paper", "set": { "world.backupMaterials": true } },
    { "when": "event_window_closed", "emit": "PREP-01-RESOLVED" }
  ],
  "fallback": "continue_with_board"
}
```

**Quan trọng:** trong engine hoàn chỉnh, điều kiện cần schema an toàn và bộ *condition evaluator* đã định nghĩa; không dùng `eval()` để chạy chuỗi biểu thức từ JSON. Những action có thể kết hợp, và cần đánh dấu hoàn thành/chỉ chạy một lần phù hợp.

### 13.3. Các interface TypeScript đề xuất

```ts
type GameFlagValue = boolean | number | string;

type GameAction = {
  id: string;
  kind: "interact" | "dialogue" | "inspect" | "plan" | "wait" | "report";
  targetId?: string;
  costMinutes?: number;
};

type GameEvent = {
  id: string;
  version: number;
  locationId: string;
  triggerIds: string[];
  actionIds: string[];
  tags: string[];
  priority: "critical" | "main" | "side";
};

type GameSnapshot = {
  schemaVersion: number;
  runId: string;
  day: number;
  clockMinute: number;
  playerLocationId: string;
  flags: Record<string, GameFlagValue>;
  npcStates: Record<string, Record<string, GameFlagValue>>;
  questStates: Record<string, string>;
  contentVersion: string;
};
```

### 13.4. Luồng lưu và an toàn dữ liệu

- Mỗi `game_run` thuộc đúng `user_id` hiện đăng nhập; API xác minh quyền truy cập cho mọi lệnh.
- Mọi `POST` xác thực CSRF phù hợp session và `Content-Type: application/json` hoặc cơ chế được chuẩn hóa.
- Snapshot giới hạn kích thước và có **`revision`** tăng dần để tránh ghi đè phiên cũ; request trùng dùng **idempotency key** cho hành động ảnh hưởng tiến độ.
- `event_seq` tăng đơn điệu cho mỗi run; engine xử lý event phát lại theo phiên bản.
- Backend kiểm tra `action_id` hợp lệ với sự kiện, điều kiện và thứ tự; **không tin `score` từ trình duyệt**.
- Không lưu thông tin thật của học sinh/phụ huynh vào game; mọi nhân vật trong cốt truyện là giả tưởng.
- Có phương án khi mạng đứt: checkpoint cục bộ tạm thời có thông báo, retry an toàn, không tự tuyên bố đã đồng bộ khi chưa nhận xác nhận API.
- Mục tiêu tiếp tục phiên: tải từ snapshot mới nhất, nạp asset phù hợp, khôi phục vị trí và hành vi NPC theo mốc thời gian.

---

## 14. Kế hoạch assets 3D và quy trình sản xuất

### 14.1. Danh mục asset cho bản một ngày

| Nhóm | Mục tiêu tham khảo | Chất lượng yêu cầu |
|---|---:|---|
| Khu môi trường chơi | 4 khu P0 | Dễ đọc lối đi, scale thống nhất, collision hoàn chỉnh |
| Avatar người chơi | 1 bộ rig, vài biến thể | Đi/đứng/nhìn/giảng/cầm vật thể mượt |
| NPC chủ chốt | 8–10 nhân vật | Nhận diện bằng tóc/trang phục/biểu cảm |
| NPC nền | 12–18 biến thể | Lịch ngắn, không tất cả update mỗi frame |
| Prop lớp và trường | 40–60 loại | Có tên/pivot/collider, đồ tương tác có trạng thái |
| Animation | 15–25 hành vi nền | Chuyển động đủ tự nhiên; thêm cho phân cảnh chính |
| Texture/material | Bộ đồng nhất phong cách | PBR hợp lý, nén, không lạm dụng ảnh độ phân giải cao |
| Âm thanh | 20–30 hiệu ứng/ambience | Mix âm lượng, phụ đề cho thoại quan trọng |
| VFX/UI | Bộ nhỏ có hệ thống | Không che chắn tầm nhìn trong lớp |

**Đây là mục tiêu kế hoạch, không phải danh mục asset đã tồn tại.** Cần thử trên máy yếu trước khi quyết định ngân sách đa giác/texture chính xác.

### 14.2. Pipeline asset

```text
Moodboard → Concept/Reference → Blockout → Model 3D
→ UV/Material → Rig/Animation → GLB/glTF
→ Kiểm tra tỷ lệ / xương / texture → Tối ưu (LOD, texture, instancing)
→ Asset Registry (nguồn, license, phiên bản, đường dẫn)
→ Đưa vào scene Babylon.js → Kiểm tra FPS, lỗi hình ảnh
```

**Nguồn nghiên cứu:** Blender (tự tạo và chỉnh), Kenney (đồ vật/UI tùy gói), Poly Haven (vật liệu/HDRI), Mixamo (rig/animation theo điều khoản), Quaternius (kiểm tra license theo từng gói). Không tải bừa từ Internet vào game; mọi asset cần `author`, `source_url`, `license`, `modified`, `usage`, `credit_requirement`.

### 14.3. Thứ tự dựng scene hợp lý

1. Blockout lớp 9A: cửa, bàn, bảng, máy chiếu, khu giáo viên, lối đi.
2. Avatar và 2 NPC (Minh, Nam): rig + đi/đứng/ngồi/nhìn.
3. Hệ tương tác bàn/phiếu/bảng: phải chơi được mini-loop trước khi đẹp.
4. Hoàn thiện ánh sáng và vật liệu lớp.
5. Tạo phòng giáo viên + hành lang kết nối.
6. Thêm sân/cổng, NPC nền và âm thanh môi trường.
7. Hoàn thiện 8–10 nhân vật chủ chốt; VFX/cinematic cuối cùng.

### 14.4. Ngân sách kỹ thuật tham khảo

- Mục tiêu: **30 FPS ổn định** ở cấu hình thấp trên máy phổ thông đã xác định; 60 FPS tùy cấu hình.
- Có quality presets `Low`, `Medium`, `High`; tắt bớt bóng đổ, giảm texture, số NPC hoạt ảnh và mật độ hiệu ứng khi cần.
- Dùng mesh instancing/merging phù hợp cho bàn ghế lặp lại; LOD và culling cho đối tượng ngoài tầm nhìn.
- Texture nén, âm thanh nén, tải theo khu; theo dõi tổng dung lượng tải lần đầu bằng profiling.
- Không chốt polygon/MB cứng trước khi có benchmark và test trên thiết bị mục tiêu.

---

## 15. AI, quyền riêng tư và chuẩn mực sư phạm

### 15.1. Chiến lược AI

**Giai đoạn 1:** hội thoại và cốt truyện **được viết sẵn, kiểm thử trước**, NPC phản ứng theo state machine/điều kiện, không gửi lời thoại đến LLM. Mục tiêu là ổn định, kiểm soát chất lượng, tiết kiệm chi phí và dễ thẩm định.

**Giai đoạn sau (nếu thật sự cần):** thử AI trong phạm vi hạn chế như giải thích thêm về nghề hoặc câu hỏi ngoài giờ theo tập tri thức kiểm duyệt; không để AI tự quyết định kết quả an toàn học sinh, đánh giá sức khỏe hay kỷ luật. Phải có fallback, giám sát nội dung, giới hạn chi phí và tuân thủ quyền riêng tư.

### 15.2. Hành vi sư phạm nhạy cảm

- Không cổ vũ sỉ nhục học sinh, bạo lực hoặc tiết lộ đời tư để “quản lớp hiệu quả”.
- Không mặc định mọi học sinh mất tập trung có rối loạn hoặc hoàn cảnh gia đình tiêu cực.
- Phân biệt **khoảnh khắc làm giáo viên** với việc xử lý vấn đề thuộc thẩm quyền tư vấn, y tế, bảo vệ học sinh hoặc quản lý nhà trường.
- Các cảnh về bắt nạt, sức khỏe, nguy cơ an toàn phải có đường **lắng nghe – bảo vệ – báo người có trách nhiệm – ghi nhận phù hợp**, không mô phỏng hành vi tra hỏi tùy tiện.
- Cơ chế phụ huynh phải tôn trọng hai chiều, không khắc họa họ chỉ là chướng ngại.
- Nội dung cần được ít nhất một người có kinh nghiệm sư phạm xem xét, và cần thử với người chơi trong độ tuổi mục tiêu.

### 15.3. Dữ liệu và bảo mật

- Nhân vật là giả tưởng; không sử dụng tên, hình ảnh, giọng nói, hồ sơ của người thật khi chưa có quyền.
- Lưu ít dữ liệu nhất cần cho tiến độ/trải nghiệm; có tùy chọn xóa tiến độ.
- Nếu có nhập văn bản phản tư, quy định rõ lưu hay không lưu, ai có quyền xem và thời hạn lưu.
- Không gắn mô hình AI vào dữ liệu học sinh thật của website khi chưa có cơ sở và sự cho phép phù hợp.
- Theo dõi các chính sách pháp luật hiện hành về dữ liệu cá nhân, giáo dục và bản quyền trước phát hành; tài liệu này không thay thế tư vấn pháp lý.

---

## 16. Lộ trình, phân vai và ngân sách nguồn lực

### 16.1. Lộ trình cho nhóm nhỏ có kinh nghiệm (ước lượng)

| Giai đoạn | Tuần | Công việc chính | Điều kiện qua cổng |
|---|---:|---|---|
| **0. Nền tảng & an toàn** | 1–2 | Rà soát secret, chuẩn hóa Git, tạo GDD, source TypeScript/Babylon, CI | Scene mẫu build được; không có secrets trong mã nguồn |
| **1. Prototype 3D** | 3–5 | Lớp blockout, avatar, camera, collision, tương tác đồ vật và 1 NPC | Đi vào lớp, xem phiếu, trò chuyện với NPC, không lỗi chặn tiến trình |
| **2. Gameplay nghề** | 6–9 | 7 bước loop, lesson planner, 2 sự kiện mở/khối, dialogue, game state | Chơi được một tiết với 3+ chiến lược khác nhau và hậu quả |
| **3. Ngày chơi hoàn chỉnh** | 10–14 | 4 khu, 8–10 NPC, 8–12 sự kiện, 3 arc, lưu và báo cáo | Chơi trọn 60–90 phút, lưu/tiếp tục, hoàn thành 1 ngày |
| **4. Polish & thử nghiệm** | 15–20 | Art/âm thanh/cinematic, accessibility, tối ưu, test sư phạm | Đạt checklist nghiệm thu trên thiết bị mục tiêu |
| **5. Chiến dịch năm ngày** | Sau vertical slice | Mở rộng Day 2–5 dựa trên test, thêm nội dung/địa điểm | Không mở rộng nếu Day 1 chưa đủ chất lượng |

**Cảnh báo:** thời gian chỉ là giả định cho nhóm có người biết TypeScript/3D và dùng asset sẵn có. Nếu một người tự làm và học từ đầu, cần lập lại lịch theo năng lực và thời gian thực tế. Không nên hứa hoàn thành nhanh để đánh đổi chất lượng.

### 16.2. Vai trò tối thiểu

- **Gameplay/Technical Lead:** Babylon.js, TypeScript, kiến trúc hệ thống.
- **3D Generalist/Technical Artist:** Blender, vật liệu, rig/animation, hiệu năng.
- **Narrative/Game Designer:** sự kiện, biến, cốt truyện, dialogue, cân bằng gameplay.
- **PHP Backend Engineer:** API, migrations, xác thực, lưu game, báo cáo.
- **QA + Cố vấn sư phạm:** kiểm tra tình huống, ngôn ngữ, bug, trải nghiệm.

Một người có thể kiêm nhiều vai trò nhưng cần phân bổ thời gian QA/art nghiêm túc. Có thể hợp tác theo dự án thay vì tuyển đủ người ngay từ đầu.

### 16.3. Kiểm soát chi phí

Đầu tiên chi cho **asset có license rõ ràng, người kiểm thử nội dung, thời gian viết gameplay và tối ưu**. Chỉ đầu tư mocap, voice chuyên nghiệp hay bản đồ lớn khi vòng chơi đã đạt chỉ tiêu. Nếu dùng dịch vụ AI/hosting asset/CDN, phải có hạn mức và giám sát chi phí.

---

## 17. Tiêu chí nghiệm thu, kiểm thử và rủi ro

### 17.1. Definition of Done — Vertical Slice Day 1

- [ ] Game chạy trong trình duyệt desktop từ website JOBSIMU, không thay thế website cũ.
- [ ] Avatar 3D có điều khiển, camera thứ ba, va chạm, animation và âm thanh cơ bản.
- [ ] Có **4 khu P0** kết nối, tải ổn và có tối thiểu 8 NPC quan trọng.
- [ ] Có vòng lặp **7 bước**, **mỗi bước ít nhất 2 tình huống mở đã triển khai/kiểm thử** (có thể là biến thể có điều kiện, không nhất thiết luôn xảy ra trong một lần chơi).
- [ ] Ít nhất **3 chuỗi nhân-quả xuyên thời điểm** thực sự thay đổi nội dung, không chỉ câu thoại.
- [ ] Có một tiết học với chuẩn bị, quan sát nhóm, câu hỏi phản biện, ứng biến và đánh giá cuối giờ.
- [ ] Có nhiệm vụ ngoài lớp: đồng nghiệp, công việc chuyên môn và phụ huynh.
- [ ] Người chơi có thể bỏ lỡ/chậm/sửa sai mà không bị softlock.
- [ ] Lưu/tiếp tục đúng trạng thái, lời hứa, NPC và checkpoint qua API.
- [ ] Báo cáo cuối ngày có **bằng chứng cụ thể** và tự phản tư, không chẩn đoán năng lực.
- [ ] Phụ đề, âm lượng, điều chỉnh chất lượng đồ họa, cài đặt điều khiển cơ bản.
- [ ] Đạt mục tiêu hiệu năng trên thiết bị mục tiêu được đo và ghi nhận.
- [ ] Cố vấn sư phạm chấp thuận các tình huống an toàn/nhạy cảm.
- [ ] Bảo mật: không secret công khai, xác thực API, giới hạn payload, kiểm thử thao tác lưu giả mạo.

### 17.2. Bộ kịch bản QA bắt buộc

| Test | Điều kiện thử | Kết quả mong đợi |
|---|---|---|
| QA-01 | Không kiểm tra máy chiếu, vẫn bắt đầu tiết | Có fallback hợp lý; không ngắt game |
| QA-02 | Chuẩn bị bản in, máy chiếu tắt | Học liệu dự phòng dùng được; NPC phản hồi phù hợp |
| QA-03 | Người chơi đi ngang Minh mà không dừng | Sự kiện sau vẫn có nhánh, không phạt vô lý |
| QA-04 | Công khai nhắc Minh rồi xin lỗi riêng | Quan hệ phản ứng và có cơ hội phục hồi theo thời gian |
| QA-05 | Không kiểm tra hiểu bài, kết thúc đúng giờ | Báo cáo ghi thiếu bằng chứng, không tự chấm điểm cao |
| QA-06 | Chậm giờ vì hỗ trợ vấn đề an toàn | Không thưởng/phạt máy móc; xử lý ưu tiên hợp lý |
| QA-07 | Hứa với thầy Quân rồi không giữ | Ngày sau có lời nhắc/hệ quả quan hệ; có cách sửa |
| QA-08 | Thoát giữa tiết, tải lại | Đúng checkpoint, không nhân đôi event/điểm |
| QA-09 | Gửi `score` giả từ DevTools | Server bỏ qua điểm giả, ràng buộc hành động |
| QA-10 | Mạng mất trong lúc lưu | Thông báo chưa đồng bộ, retry không tạo event trùng |
| QA-11 | 30 phút không tương tác với marker | NPC vẫn hoạt động hợp lý, có gợi ý không gây phiền |
| QA-12 | Thiết bị đồ họa thấp | Preset thấp vẫn đọc được NPC/tình huống, không lỗi nặng |

### 17.3. Rủi ro và giảm thiểu

| Rủi ro | Tác động | Giảm thiểu |
|---|---|---|
| Làm môi trường đẹp nhưng không có nghề để chơi | Rất cao | Chỉ duyệt art sau khi có mini-loop dạy học |
| Quá nhiều nhánh khiến khó kiểm thử | Cao | Dùng flags chuẩn, event schema, state test và giới hạn 3 arc lớn đầu tiên |
| NPC hành vi giả/đứng yên | Cao | Scheduler + animation + tầm nhìn, test hành vi xuyên lịch |
| Ghép asset nhiều phong cách | Trung bình-cao | Art bible, bảng màu, chỉnh model/material và registry license |
| Game tải chậm trên cPanel | Cao | Tải từng khu, nén GLB/texture, cân nhắc CDN khi cần |
| Đánh giá sư phạm quá đơn giản | Cao | Lấy bằng chứng hành vi, cố vấn giáo dục, báo cáo đa chiều |
| Lộ khóa/cấu hình từ code cũ | Nghiêm trọng | Kiểm tra lịch sử Git, rotate secrets, API validation |
| Quy trình “game mở” thực chất vẫn là A/B/C | Rất cao | QA kiểm tra hành động đa dạng và hệ quả trạng thái, không duyệt bằng thoại đơn thuần |

---

## 18. Backlog triển khai và việc cần làm đầu tiên

### 18.1. Thứ tự ưu tiên P0 — không nên đảo

1. **[SEC-01]** Rà soát thông tin đăng nhập từng xuất hiện trong lịch sử Git; rotate nếu từng lộ; chuẩn hóa `.gitignore`, `.env.example`, `config.example.php` và phân quyền deploy.
2. **[DOC-01]** Đưa GDD này vào `docs/game3d.md`; tách `STORY_BIBLE.md` và `ASSET_MANIFEST.md` sau khi duyệt concept.
3. **[TECH-01]** Tạo `game/` với Vite + TypeScript + Babylon.js, scene trống, renderer, resize, pause.
4. **[3D-01]** Blockout lớp 9A, hành lang, cửa, bàn, bảng và vị trí 30–40 học sinh giả lập bằng placeholder.
5. **[PLAYER-01]** Nhân vật 3D, camera góc ba, đi bộ và tương tác bằng `E`.
6. **[NPC-01]** Cô Mai và Minh hoạt động theo lịch, hiển thị thoại/biểu cảm và một memory flag.
7. **[EVENT-01]** Hệ trigger + clue + nhiều action + consequence + delayed event, viết unit test.
8. **[PREP-01]** Máy chiếu lỗi và chuẩn bị dự phòng có hiệu lực trong tiết.
9. **[CLASS-01]** Mini-loop giảng: lấy học liệu → vào lớp → quan sát nhóm → điều chỉnh → exit ticket.
10. **[SAVE-01]** PHP API + migrations + start/save/resume, chống replay/ghi trùng.
11. **[ARC-01]** Hoàn thiện chuỗi Minh từ đầu ngày tới lịch hẹn ngày hai.
12. **[REPORT-01]** Báo cáo cuối ngày giải thích từ hành động thực tế.
13. **[POLISH-01]** Animation, sound, asset hoàn chỉnh, accessibility, FPS profiling.

### 18.2. Vertical slice nhỏ nhất có thể kiểm chứng ngay

**Cảnh khởi đầu:** 07:00, người chơi đứng trong phòng giáo viên. Cô Mai nói máy chiếu lớp 9A không ổn. Người chơi có thể kiểm tra, chuẩn bị bản in hoặc đổi cách dạy. Sau đó đi bộ qua hành lang, thấy Minh đứng riêng, lên lớp, bắt đầu tiết biến đổi khí hậu và nghe Nam đặt câu hỏi phản biện. Hệ thống chốt bằng phiếu cuối giờ. Ít nhất một quyết định ban đầu phải tạo **hệ quả có thể nhìn thấy ở cuối cảnh**.

**Một bài test đủ chất lượng:** chạy ba lần với ba cách chuẩn bị khác nhau. Nếu scene và phản hồi không thay đổi đáng kể, prototype chưa đạt yêu cầu, dù hình ảnh đẹp đến đâu.

### 18.3. Các đầu việc cần bàn giao sau prototype

| Tài liệu/sản phẩm | Người nhận | Tiêu chuẩn |
|---|---|---|
| `game3d.md` (GDD) | Cả nhóm | Scope, loop, world, NPC, arc, hệ quả, kỹ thuật rõ |
| Story bible từng NPC | Narrative + QA | Không mâu thuẫn hành vi và quyền riêng tư |
| Event catalog & schema | Gameplay dev | ID/version/trigger/action/effect/QA test |
| Level blockout | 3D + Dev | Hoàn thành đường đi và tương tác thật |
| Asset manifest | 3D + Producer | Có nguồn/license/phiên bản cho từng asset |
| API contract | Backend + Client | Xác thực, validate, lỗi, idempotency, save |
| QA report | QA + Cố vấn | Danh sách kiểm thử, lỗi và mức độ nghiệm thu |

### 18.4. Lệnh Git đưa tài liệu vào repo (chạy trên máy của bạn)

> Dùng khi bạn đã tải file `game3d.md` từ cuộc trò chuyện và **copy vào thư mục gốc của repository tại máy**. Nếu muốn đặt đúng cấu trúc đề xuất, chuyển nó vào `docs/`.

```bash
# Đầu tiên phải đứng trong thư mục chứa .git
cd /duong-dan-den/jobsimu

git status
mkdir -p docs
mv game3d.md docs/game3d.md

git add docs/game3d.md
git commit -m "docs: add JOBSIMU 3D teacher game design"
git push origin main
```

Nếu bạn đang ở nhánh riêng, thay `main` bằng nhánh đó và tạo pull request. Nếu `docs/game3d.md` đã có, xem diff trước khi ghi đè. **Không chạy `git init` trong `~`** nếu repository nằm ở thư mục dự án khác.

---

## 19. Tài liệu tham chiếu

### 19.1. Mã nguồn JOBSIMU đã đối chiếu

- Repository: https://github.com/vangminhphuc209-gif/jobsimu
- README: https://raw.githubusercontent.com/vangminhphuc209-gif/jobsimu/main/README.md
- Nội dung mô phỏng: https://raw.githubusercontent.com/vangminhphuc209-gif/jobsimu/main/app/simulations.php
- Controller: https://raw.githubusercontent.com/vangminhphuc209-gif/jobsimu/main/app/simulation-controller.php
- Hàm nghiệp vụ: https://raw.githubusercontent.com/vangminhphuc209-gif/jobsimu/main/app/functions.php
- Database: https://raw.githubusercontent.com/vangminhphuc209-gif/jobsimu/main/database/schema.sql
- Bootstrap: https://raw.githubusercontent.com/vangminhphuc209-gif/jobsimu/main/app/bootstrap.php
- Git ignore: https://raw.githubusercontent.com/vangminhphuc209-gif/jobsimu/main/.gitignore

### 19.2. Công nghệ & asset

- Babylon.js: https://doc.babylonjs.com/
- Đặc tả tính năng Babylon.js: https://www.babylonjs.com/specifications/
- Vite & TypeScript: https://vite.dev/guide/ và https://vite.dev/guide/features
- Blender: https://www.blender.org/
- glTF: https://www.khronos.org/gltf/
- Kenney Assets: https://kenney.nl/assets
- Poly Haven: https://polyhaven.com/
- Mixamo: https://www.mixamo.com/
- Quaternius: https://quaternius.com/

**Lưu ý:** luôn xem license của **từng asset** và điều khoản hiện hành trước khi đưa vào game; tài liệu chỉ liệt kê nơi có thể tìm tài nguyên, không khẳng định mọi tài nguyên đều được sử dụng miễn phí cho mọi trường hợp.

---

## Kết luận thiết kế

JOBSIMU 3D phải tạo được cảm giác: **“Tôi đã sống thử một ngày làm giáo viên và nhận ra nghề này đòi hỏi những quyết định như thế nào.”** Thành công không được đo bằng số phòng 3D, số NPC hay số câu thoại, mà bằng khả năng người chơi **quan sát — tìm hiểu — hành động — ứng biến — sửa sai — thấy hệ quả — tự phản tư** trong một thế giới có tính liên tục.

**Quyết định sản xuất cuối cùng:** ưu tiên **một ngày chơi có chiều sâu và tình huống mở tại cả 7 bước gameplay**; chỉ sau khi ngày đó đạt yêu cầu về trải nghiệm, an toàn nội dung và kỹ thuật mới phát triển 5 ngày và mở rộng các nghề khác.
