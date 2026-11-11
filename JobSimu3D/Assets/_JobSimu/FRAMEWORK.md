# Shared framework + Teacher Class 8A Vertical Slice (Unity 6)

> **Tài liệu thiết kế đầy đủ:** [`docs/TEACHER_8A_REDESIGN.md`](../../../docs/TEACHER_8A_REDESIGN.md)  
> **Phạm vi:** Một tiết Khoa học tự nhiên Lớp 8A (`07:30–08:15`, chuẩn bị từ `07:10`) tại Trường THCS Bình Minh (hư cấu). Các nghề Lập trình viên, Bác sĩ, Công an chỉ giữ định danh `Profession`, chưa có nội dung chơi.

## Chạy và build đúng scene

1. Mở Unity project `JobSimu3D/` (Unity `6000.6.8f1`).
2. Mở **`Assets/_JobSimu/Scenes/SharedFrameworkDemo.unity`**, nhấn **Play** → **Chơi mới · Giáo viên THCS (Lớp 8A)**. Nhấp vào cảnh để điều khiển:
   - `WASD` di chuyển · `Shift` chạy · Chuột xoay góc nhìn · `E` tương tác · `Tab` mở Sổ lớp 4 nhóm · `J` mở Nhật ký · `P` mở lại Hồ sơ sau khi kết thúc · `Esc` tạm dừng / thả chuột.
3. Build Web Development qua menu **Tools > JobSimu > Build Teacher Web Demo** (hoặc batch CLI bên dưới), xuất vào `Builds/TeacherWeb`.

## Vòng lặp 6 giai đoạn (Class 8A)

1. **A. Nhận nhiệm vụ (`Briefing`):** Gặp **Cô Mai** ngoài sân, có thể hỏi thêm về thói quen học tập lớp 8A và tình trạng thiết bị, hoặc quan sát **Vũng nước mẫu sân trường** trước khi vào phòng giáo viên.
2. **B. Chuẩn bị (`Preparation`):** Trong Phòng giáo viên, tự do kết hợp nhiều việc nếu còn thời gian: đọc **bài làm tiết trước**, kiểm tra **khay mẫu & sổ thiết bị**, **in phiếu/ảnh**, **soạn trình chiếu** hoặc **soạn bộ thẻ & khay mẫu nhóm**, rồi đi qua điểm **Bảng chuẩn bị · Sang Lớp 8A**. Chuẩn bị quá `07:30` làm giảm quỹ 45 phút trên lớp.
3. **C. Mở đầu tiết học (`Opening`):** Tại **Bảng Lớp 8A**, nêu nhiệm vụ điều tra và **bắt buộc kiểm tra học sinh hiểu yêu cầu** (hỏi cả lớp hoặc trao đổi cặp) trước khi khởi động hoạt động 4 nhóm.
4. **D. Điều hành lớp (`Facilitating`):** Thực hiện nhiều hành động tự do trên 4 cụm bàn (12 học sinh: **Nhóm 1 — Linh, An, Bình**; **Nhóm 2 — Nam, Vy, Khánh**; **Nhóm 3 — Hà, Trang, Đức**; **Nhóm 4 — Minh, Hùng, Thảo**), đọc phiếu trực tiếp trên bàn, hỏi làm rõ, chia nhỏ nhiệm vụ/minh họa, dùng bảng phấn, giao mở rộng, ghép kiểm tra chéo và **quay lại kiểm tra bằng ví dụ mới**. Không tự chuyển sang bước kiểm tra sau 1 thao tác; yêu cầu tối thiểu 2 tương tác điều hành lớp có nghĩa (hoặc gần cuối giờ `08:05`).
5. **E. Kiểm tra việc học (`CheckingLearning`):** Thu phiếu viết cả 4 nhóm, phát câu hỏi ngắn ví dụ mới hoặc mời học sinh giải thích miệng (nêu rõ giới hạn khi chỉ nghe 1 em), sau đó có thể **điều chỉnh ngắn trên bảng** theo lỗ hổng vừa phát hiện.
6. **F. Phản tư (`Reflection`):** Gặp lại Cô Mai (hoặc khi chuông hết giờ `08:15` vang lên), chọn 1–3 sự kiện/bài làm làm bằng chứng, tự phản tư và nhận trao đổi của Cô Mai **dẫn chiếu đúng sự kiện đã xảy ra**, xem báo cáo **Hồ sơ trải nghiệm nghề giáo viên — phiên thử nghiệm** (6 mục A–F).

## Kiến trúc chính

| Thành phần | Vai trò |
|---|---|
| `Scripts/Quests/ScenarioDefinition.cs` | Schema cho 6 `LessonPhase`, hành động 1 lần / lặp có điều kiện, cờ nhiều điều kiện, `TimedEvent` có `anchorFlag` |
| `Scripts/Consequences/ClassroomModel.cs` | Trạng thái 4 nhóm, 12 học sinh (4 trọng tâm + 8 nền), vấn đề phát sinh, tài nguyên và phản tư |
| `Scripts/Consequences/ScenarioSession.cs` | Reducer xác định: điều hành 4 tình huống đan xen, chống spam bằng chứng, tách thời gian tự trôi và hành động, xử lý hết giờ `08:15`, báo cáo A–F |
| `Scripts/Core/SimulationRuntime.cs` | Đồng bộ vòng đời, dừng đồng hồ khi mở UI/tạm dừng/mất focus, reset tích lũy thụ động khi hành động tiêu hao phút |
| `Scripts/SaveSystem/LocalSaveStore.cs` | Lưu cục bộ v2 (`jobsimu.unity.v2.*`), replay xác định, bảo toàn bản lỗi sang `.corrupt`, không ghi đè bởi autosave/phiên trống |
| `Scripts/UI/FrameworkHUD.cs` | HUD đồng hồ/mục tiêu, đối thoại nhiều lượt, Sổ lớp 4 nhóm (`Tab`), Nhật ký (`J`), Phản tư chọn bằng chứng, Báo cáo A–F |
| `Scripts/Gameplay/TeacherLessonVisuals.cs` | Cập nhật bảng phấn, màn chiếu & đèn báo lỗi, phiếu in/khay mẫu trên 4 bàn nhóm |
| `Scripts/NPC/ScheduledNpc.cs` | Tư thế ngồi ghế đúng bàn, giơ tay, trao đổi, mất tập trung khi chờ lâu, giữ tư thế tĩnh khi bật *Giảm chuyển động* |
| `Scripts/Interaction/PlayerInteractor.cs` | Raycast bị tường che và chặn tương tác lên vật thể ẩn/tắt renderer |
| `Editor/FrameworkDemoGenerator.cs` | Tạo/tái tạo `SharedFrameworkDemo.unity` cùng `FirstLesson.asset` (`contentVersion = 2`) và 13 hồ sơ NPC |
| `Editor/FrameworkValidator.cs` | Kiểm tra tham chiếu, 13 NPC, toàn bộ trạm, visuals, tuyến capsule và chặn nhãn cũ (`10A1`/`THPT`/`9A`) |

## Kiểm thử và build CLI

*(Đóng Unity Editor của cùng thư mục dự án trước khi chạy batch CLI.)*

```bash
# 1. Tái tạo & kiểm định scene
/home/minhphuc/Unity/Hub/Editor/6000.6.8f1/Editor/Unity \
  -batchmode -nographics -projectPath /home/minhphuc/jobsimu/JobSimu3D \
  -executeMethod JobSimu.Editor.SchoolPrototypeGenerator.RegenerateFirstPlayableAndSharedDemoBatch \
  -logFile Logs/redesign-generate.log -quit

# 2. Chạy EditMode tests (23 bài: reducer, chống spam, hết giờ, replay save, bảo toàn bản lỗi, 3 lượt phân kỳ)
/home/minhphuc/Unity/Hub/Editor/6000.6.8f1/Editor/Unity \
  -batchmode -nographics -projectPath /home/minhphuc/jobsimu/JobSimu3D \
  -runTests -testPlatform EditMode -assemblyNames JobSimu.EditModeTests \
  -testResults Logs/redesign-edit-tests.xml -logFile Logs/redesign-edit-tests.log

# 3. Chạy PlayMode tests (5 bài: đi bộ thật CharacterController qua các trạm/4 nhóm, dừng đồng hồ, ẩn vật thể, hết giờ 08:15)
/home/minhphuc/Unity/Hub/Editor/6000.6.8f1/Editor/Unity \
  -batchmode -nographics -projectPath /home/minhphuc/jobsimu/JobSimu3D \
  -runTests -testPlatform PlayMode -assemblyNames JobSimu.PlayModeTests \
  -testResults Logs/redesign-play-tests.xml -logFile Logs/redesign-play-tests.log

# 4. Build WebGL cục bộ + chạy Playwright playtest (headless Chrome / SwiftShader)
/home/minhphuc/Unity/Hub/Editor/6000.6.8f1/Editor/Unity \
  -batchmode -nographics -projectPath /home/minhphuc/jobsimu/JobSimu3D \
  -executeMethod JobSimu.Editor.FrameworkWebBuild.Build \
  -logFile Logs/redesign-web-build.log -quit
python3 Tools/WebSmoke/serve.py --port 8765
# Terminal khác:
npm test --prefix Tools/WebSmoke
```

## Kết quả kiểm thử đã chạy (11/11/2026)

- **Validator:** `PASS: shared scene, Class 8A content, 13 NPCs, stations, visuals, spawn and routes.`
- **EditMode (`JobSimu.EditModeTests`):** **23/23 PASS** (`Logs/redesign-edit-tests.xml`).
- **PlayMode (`JobSimu.PlayModeTests`):** **5/5 PASS** (`Logs/redesign-play-tests.xml`).
- **WebGL Development Build (`Builds/TeacherWeb`):** **Succeeded**, `0` errors, `0` warnings (`Logs/framework-web-build.txt`).
- **Playwright Browser Playtest (`Tools/WebSmoke/smoke.mjs`):** **PASSED**, `0` console/page errors, kiểm chứng di chuyển WASD + xoay chuột pointer lock + `Tab`/`Esc` + đủ 6 giai đoạn + lưu IndexedDB v2 qua reload (`Tools/WebSmoke/results/report.json`).

## Danh sách placeholder & giới hạn còn lại

- **Hình khối 3D & nhân vật:** Toàn bộ học sinh, giáo viên và đạo cụ (máy in, máy chiếu, khay mẫu, vũng nước) là khối hình học procedural/blockout bằng vật liệu URP, chưa phải model rigged/textured hoàn thiện.
- **Âm thanh:** Âm nền, nhạc và tiếng chuông/tương tác được tổng hợp trong `SimulationAudio.cs`, chưa phải âm thanh thu thực tế.
- **Thẩm định chuyên môn & thời lượng chơi thực:** Kịch bản KHTN Lớp 8A là tình huống thiết kế trải nghiệm nghề; chưa qua thẩm định chương trình chính thức, chưa đo thời gian chơi thực 20–30 phút trên người dùng thật và chưa đo FPS mục tiêu trên máy chơi thật (Playwright chạy headless SwiftShader).
- **Đồng bộ server & cổng website:** Tiến độ lưu cục bộ trên trình duyệt (`IndexedDB` / `PlayerPrefs`), chưa đồng bộ tài khoản; website PHP (`app/simulation-3d.php`) vẫn giữ bản Babylon.js chuyển tiếp và `api/game/*` tiếp tục trả `503 GAME_SYNC_UNAVAILABLE`.
