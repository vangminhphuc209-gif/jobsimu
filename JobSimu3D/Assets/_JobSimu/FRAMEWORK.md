# Shared framework + teacher lesson demo

Ưu tiên hiện tại: một nền tảng dùng chung được kiểm thử bằng một tiết học. Các nghề lập trình viên, bác sĩ và công an chỉ có định danh trong `Profession`; chưa có môi trường/nội dung chơi được cho ba nghề đó.

## Chạy demo

Mở `Assets/_JobSimu/Scenes/SharedFrameworkDemo.unity`, Play → **Chơi mới**. Nhấp vào cảnh để điều khiển. WASD di chuyển, Shift chạy, E tương tác, Esc tạm dừng/thả chuột, J mở nhật ký, P xem lại kết quả khi hoàn thành.

1. Gặp cô Mai bên trái sân, nhận tiết học.
2. Mở cửa phòng giáo viên bên phải. Chọn một học liệu bằng cách đến gần máy in, máy chiếu trên bàn, hoặc bộ thẻ cuối phòng.
3. Vào lớp 10A1 bên trái, tương tác bảng để bắt đầu.
4. Hỗ trợ Linh ở lối giữa bàn đầu, trao đổi với Nam ở hàng sau hoặc vẽ minh họa trên bảng.
5. Thu phiếu ở góc trái bảng hoặc mời Linh tổng kết.
6. Quay lại cô Mai. Đọc bằng chứng theo năm nhóm năng lực, ghi phản tư và lưu.

Ba phương án chuẩn bị thay đổi vật dụng trong lớp, mức tham gia, hiểu nhiệm vụ và áp lực thời gian. Thiết bị hỏng không chặn việc hoàn thành: bảng và trao đổi trực tiếp vẫn dùng được. Lựa chọn thu phiếu khác với chỉ nghe một học sinh trả lời; báo cáo không đánh đồng hai nguồn bằng chứng.

## Kiến trúc và dữ liệu

| Thành phần | Vai trò |
|---|---|
| `Scripts/Core/InteractionUI.cs` | Ranh giới UI/player dùng chung; prototype cũ vẫn hoạt động |
| `Scripts/Quests/ScenarioDefinition.cs` | ScriptableObject: nghề, bước, hành động, điều kiện cờ, hệ quả, trigger thời gian, bằng chứng |
| `Scripts/Consequences/ScenarioSession.cs` | Bộ chuyển trạng thái xác định; kiểm tra điều kiện, chống hành động trùng/sai thứ tự; không eval |
| `Scripts/SaveSystem/LocalSaveStore.cs` | Định dạng v1, ID ổn định, replay chuỗi hành động, bản dự phòng, thông báo lỗi |
| `Scripts/Dialogue/DialogueStation.cs` | Cùng một tương tác cho NPC và vật thể, hội thoại theo dữ liệu và cờ |
| `Scripts/NPC/` | Profile, lịch có waypoint, FSM, cử chỉ procedural, bộ nhớ tối đa tám bằng chứng mỗi NPC |
| `Scripts/UI/` | Main menu, pause, hội thoại, nhật ký, cài đặt, kết quả/phản tư |
| `Scripts/Core/SimulationAudio.cs` | Âm nền/nhạc/cue tổng hợp nguyên bản, âm lượng độc lập |
| `Scripts/Gameplay/TeacherLessonVisuals.cs` | Hiển thị vật liệu, trạng thái thiết bị và bảng theo kịch bản giáo viên |
| `Simulators/Teacher/FirstLesson.asset` | Nội dung tiếng Việt có thể chỉnh trong Inspector; 12 hành động, 7 bước, một sự kiện chậm |
| `Simulators/Teacher/NPC-*.asset` | Ba hồ sơ cô Mai, Linh, Nam |

`SimulationRuntime` chỉ ghép các dịch vụ ở scene và điều phối vòng đời. Nội dung/hệ quả không nằm rải trong NPC MonoBehaviour. Điều kiện hiện có là bước đang hoạt động + cờ bắt buộc/cờ cấm; không hỗ trợ biểu thức tùy ý. Bộ engine chưa phải hệ thống câu chuyện nhiều ngày hoàn chỉnh.

Khi có nhiều kịch bản, mỗi scenario cần ID/contentVersion riêng. Bản lưu khác phiên bản bị từ chối rõ ràng; chưa có migration nội dung. Các chỉ số nội bộ giới hạn trong [-10,10] để điều khiển phản ứng, không có điểm tổng hợp kết luận phù hợp nghề.

## Editor automation

- **Tools > JobSimu > Generate Shared Framework Demo**: tạo scene riêng từ prototype hiện có. Nếu đã có demo, mở và kiểm tra scene đó; không nhân đôi đối tượng hay ghi đè asset đã chỉnh tay.
- **Tools > JobSimu > Validate Current Scene**: kiểm tra scene cũ hoặc framework; bao gồm tham chiếu, ID, action/station, NPC/waypoint, spawn, tuyến capsule và missing script.
- **Tools > JobSimu > Build Teacher Web Demo**: build Development vào `Builds/TeacherWeb`; chọn đúng scene bằng BuildPlayerOptions, không đổi danh sách scene build của website.
- Các menu prototype cũ vẫn giữ nguyên. Muốn thử thay đổi bố cục lớn, sao chép scene trước khi chỉnh. Generator không tự xóa/chỉnh asset hiện có.

Không cần tạo GameObject thủ công để chạy demo. Các nhân vật là hình khối có cử động procedural, chưa phải model/rig/animation nghệ thuật đã hoàn thiện.

## Kiểm thử và Web cục bộ

Chạy Unity Test Runner với `JobSimu.EditModeTests` và `JobSimu.PlayModeTests`. Bộ mới kiểm tra phân kỳ ba run, các cách hoàn thành, thứ tự hành động, sự kiện chậm chỉ phát một lần, replay save, dữ liệu hỏng, tương tác raycast tại các điểm nhiệm vụ và lưu/khôi phục. Bộ cũ kiểm tra FPP, cửa, tuyến va chạm và bảo toàn scene vẫn được chạy.

Sau khi build Web:

```bash
python3 Tools/WebSmoke/serve.py --port 8765
# Terminal khác:
npm ci --prefix Tools/WebSmoke
npm test --prefix Tools/WebSmoke
```

Server chỉ bind `127.0.0.1`, phục vụ thư mục build, gửi MIME WebAssembly và không cache. Đây là URL HTTP cục bộ, không phải triển khai production. Playwright dùng Google Chrome cài trên Linux. `BrowserDiagnostics.Dump` là probe chỉ đọc trong Development Build để kiểm tra vị trí, tiến trình và số liệu frame; không có API dịch chuyển hay thực thi hành động từ JavaScript.

Template `Assets/WebGLTemplates/JobSimu/index.html` có trạng thái tải, lỗi khởi động và `autoSyncPersistentDataPath` để đồng bộ bộ nhớ filesystem Web với IndexedDB. Build QA không nén để chạy với server đơn giản; tối ưu Brotli/Gzip và cache/header production là bước riêng.

## Lưu và tích hợp PHP

Đã kiểm tra `../README.md`, `../docs/game3d.md`, `../app/simulation-3d.php`, `../app/simulation-hub.php`, `../api/game/README.md`, bootstrap/start/state/save API. Website hiện dùng iframe đến `game/dist/index.html`. API game trả **503 GAME_SYNC_UNAVAILABLE** ngay trong bootstrap vì implementation cũ thiếu owner validation và kiểm tra state/chuỗi hành động. Không bỏ gate này hoặc gửi snapshot Unity vào API cũ.

Demo lưu bằng PlayerPrefs/IndexedDB trên thiết bị. Mỗi lần tải, engine kiểm tra schema/contentVersion, độ dài, ID/thứ tự hành động rồi tính lại hệ quả; không đọc các giá trị năng lực do bản lưu tự khai. Tuy vậy người dùng vẫn kiểm soát bộ nhớ cục bộ: bản lưu **không** phải bằng chứng xác thực trên server. Không có secret, tài khoản hay token nhúng vào client.

Đồng bộ tài khoản sau này cần server-owned action definitions, session + owner checks trên mọi endpoint, CSRF cùng origin, event_seq/revision/idempotency trong transaction, từ chối score và snapshot tự khai, test quyền/concurrency trên DB thử nghiệm. Chưa triển khai adapter đồng bộ giả hoặc chế độ AI trả lời tự do. Hội thoại hiện chạy xác định và offline.

Không sửa website PHP, database hoặc hosting; bản Vite hiện có tiếp tục hoạt động. Sau khi có bản Web được chấp nhận, việc đổi cổng vào website và triển khai hosting là bước duyệt riêng.

## Giới hạn đã biết

- Một tiết học, không mô phỏng đầy đủ lịch cả ngày hoặc nhiều ngày; NPC chỉ có lịch/waypoint cục bộ.
- Chưa có nội dung y khoa/pháp lý, không có mô phỏng bí mật cá nhân hay tình huống nhạy cảm.
- Học sinh và môi trường vẫn là blockout. Âm thanh là cue tổng hợp, không phải ambience thu thực tế. Chưa có model ngoài hay asset phải mua giấy phép.
- IMGUI dùng được trong desktop Web, nhưng chưa phải giao diện screen-reader hoàn chỉnh. Chưa hỗ trợ điều khiển cảm ứng/gamepad.
- Mục tiêu 60/30 FPS chưa phải cam kết: cần đo build tối ưu trên hai thiết bị mục tiêu được xác định. Số liệu headless/software rendering không đại diện máy chơi thật.
- Dữ liệu trình duyệt có thể bị xóa hoặc bị chặn; UI báo lưu cục bộ, không hứa đồng bộ tài khoản.

Mốc tiếp theo sau khi demo được duyệt: tối ưu profiler trên thiết bị mục tiêu, thay blockout bằng asset được duyệt, rồi thiết kế lại protocol PHP an toàn. Ba nghề khác là P2, chưa mở rộng trước các cổng này.
