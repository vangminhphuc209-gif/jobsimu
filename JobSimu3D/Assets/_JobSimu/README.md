# JobSimu — First Playable (Unity 6)

**Bản chơi chính hiện hành:** mở `Scenes/SharedFrameworkDemo.unity` để chơi trọn tiết Khoa học tự nhiên **Lớp 8A (Trường THCS Bình Minh)** với 6 giai đoạn, 4 nhóm / 12 học sinh, Cô Mai, sổ lớp, phản tư và lưu cục bộ v2. Xem [hướng dẫn framework và Web](FRAMEWORK.md). Nội dung bên dưới mô tả cảnh làm quen cơ bản `SchoolFirstPlayable.unity`, vẫn được giữ để kiểm tra hồi quy.

Cảnh làm quen cơ bản của Trường THCS Bình Minh hư cấu, góc nhìn thứ nhất. Mở `Assets/_JobSimu/Scenes/SchoolFirstPlayable.unity`, nhấn **Play**, rồi nhấp vào Game view.

## Chơi thử (cảnh làm quen cơ bản)

- **WASD** di chuyển; **Shift trái** chạy; chuột xoay camera.
- **E** tương tác khi có lời nhắc giữa màn hình.
- **Esc** đóng hội thoại / thả chuột; nhấp chuột vào cảnh để tiếp tục.
- Gặp **cô Mai** bên trái sân → mở cửa **phòng giáo viên** bên phải → nhìn xuống và nhận **kế hoạch** trên bàn → vào **lớp 8A** bên trái, tương tác **bảng** → quay lại cô Mai.
- Lớp 8B mở để khám phá. Tầng 2 và cầu thang có rào chắn, chưa đưa vào vùng chơi.

Hội thoại tạm dừng di chuyển và camera. Cửa trượt dừng khi capsule người chơi nằm trên đường chuyển động. Mất focus hoặc Esc thả chuột; không tự khóa lại ngoài thao tác nhấp chuột.

## Các tệp chính

| Thư mục / tệp | Nội dung |
|---|---|
| `Scripts/Player/FirstPersonController.cs` | CharacterController, InputAction WASD/Shift/chuột/E/Esc, trọng lực, phục hồi khi rơi |
| `Scripts/Interaction/` | Raycast bị che bởi vật cản, cửa trượt có kiểm tra vùng chuyển động |
| `Scripts/NPC/MentorNPC.cs` | NPC cô Mai dạng placeholder, mở hội thoại |
| `Scripts/Gameplay/` | HUD, hội thoại, vòng làm quen có trạng thái và bằng chứng hành động |
| `Scripts/Core/PrototypeContext.cs` | Các tham chiếu scene, điểm spawn và tuyến kiểm tra |
| `Editor/SchoolPrototypeGenerator.cs` | Dựng scene, prefab, vật liệu URP và các root môi trường |
| `Editor/PrototypeValidator.cs` | Kiểm tra scene, tham chiếu, Input System, script thiếu, spawn và tuyến đi |
| `Editor/PrototypeCapture.cs` | Chụp ảnh camera bằng GPU trong Editor, không build |
| `Tests/EditMode/PrototypeTests.cs` | Test chức năng và kiểm tra âm tính |
| `Tests/PlayMode/PrototypePlayTests.cs` | Smoke test di chuyển và tương tác trong vòng lặp runtime |
| `Prefabs/`, `Materials/`, `Scenes/` | Assets đã được Unity tạo và lưu, bao gồm `.meta` |

## Tạo lại và kiểm định

**Tools > JobSimu > Generate School Prototype** tạo scene mới. Khi đã có scene cùng tên, Unity thêm hậu tố số; không ghi đè scene cũ. Prefab/material đã tồn tại được dùng lại để bảo toàn chỉnh sửa thủ công. Muốn thay đổi mẫu prefab, chỉnh asset đó hoặc tạo phiên bản mới.

**Tools > JobSimu > Validate Prototype** kiểm tra scene đang mở. Công cụ mô phỏng vị trí cửa mở để lấy mẫu tuyến đi bằng capsule, rồi khôi phục cửa. Việc kiểm tra không thay thế thử chơi thực tế và đo FPS.

Chạy test: **Window > General > Test Runner > EditMode**, chọn `JobSimu.EditModeTests`; tiếp tục ở tab **PlayMode** với `JobSimu.PlayModeTests`. Smoke test điều khiển CharacterController theo tuyến định trước và kiểm tra raycast tại các điểm nhiệm vụ. Nó không mô phỏng chuột/bàn phím vật lý hay pointer lock trong trình duyệt.

Ví dụ chạy từ thư mục dự án trên máy này:

```bash
/home/minhphuc/Unity/Hub/Editor/6000.6.8f1/Editor/Unity \
  -batchmode -nographics -projectPath "$PWD" \
  -runTests -testPlatform EditMode -assemblyNames JobSimu.EditModeTests \
  -testResults Logs/JobSimu-tests.xml -logFile Logs/JobSimu-tests.log
```

Không thêm `-quit` khi chạy Test Runner. Đóng Editor của cùng project trước khi chạy batch. Để tạo scene qua CLI, dùng `-executeMethod JobSimu.Editor.SchoolPrototypeGenerator.GenerateAndValidateBatch -quit` thay cho các tùy chọn test.

Để chạy smoke test runtime, đổi thành `-testPlatform PlayMode -assemblyNames JobSimu.PlayModeTests`, và dùng đường dẫn kết quả `Logs/JobSimu-play-tests.xml` cùng log `Logs/JobSimu-play-tests.log`.

## Chuẩn bị Unity Web

1. Module **WebGL Build Support** đã có trên máy. Mở **File > Build Profiles**, thêm/chọn profile **Web** và **Switch Platform**.
2. Trong danh sách scene của profile, chỉ bật `SchoolFirstPlayable.unity` cho bản prototype, đặt ở vị trí đầu. Danh sách build toàn cục hiện có không được tự động thay đổi.
3. **Active Input Handling: Input System Package (New)** đã được cấu hình. Code dùng Input System 1.20.1; không dùng `Input.GetAxis` hay input legacy.
4. Web đang mặc định dùng quality **Mobile**, renderer URP Forward, shadow map 1024, một cascade. Camera prototype tắt post-processing/HDR. Chưa đo FPS, chưa chốt ngân sách draw calls.
5. Khi thực sự build thử, chọn Development Build để đo bằng Profiler. Phục vụ qua HTTP(S); cấu hình MIME WebAssembly và Content-Encoding phù hợp với Gzip/Brotli, hoặc cân nhắc Decompression Fallback trên host không hỗ trợ header. Kiểm thử Chrome/Firefox trên phần cứng mục tiêu.
6. Pointer lock cần nhấp chuột và có thể bị browser thả khi Esc/mất focus. Kiểm thử riêng khi nhúng iframe và khi đổi tab. Prototype tắt `WebGLInput.stickyCursorLock` để trạng thái con trỏ bám theo browser.

Tham khảo chính thức: [Unity Web Build Profiles](https://docs.unity.com/en-us/engine/6000.0/manual/platform-specific/webgl/building-distribution/web-build-settings), [WebGLInput.stickyCursorLock](https://docs.unity.com/en-us/engine/6000.0/script-reference/unityengine/webglinput/stickycursorlock).

Code mới không có native plugin, thread/network API riêng, hay phụ thuộc Editor trong runtime assembly. Chưa build Web, chưa deploy, chưa tích hợp website PHP.

## Giới hạn phạm vi

Đây là blockout có vòng chơi làm quen, chưa phải art hoàn thiện hay mô phỏng cả ngày dạy học. NPC đứng yên; chưa có học sinh, lịch học, âm thanh, lưu tiến độ hoặc phản hồi lớp học. HUD dùng IMGUI cho prototype. Bản ghi nhận cuối vòng chỉ mô tả hành động quan sát được, không có điểm nghề nghiệp tổng hợp; năng lực chưa có bằng chứng được nêu rõ.

Kiểm tra thủ công còn cần: cảm giác WASD/sprint, khóa chuột/đóng hội thoại, đọc chữ tiếng Việt ở nhiều độ phân giải, mở/đóng cửa từ hai phía, cửa dừng khi người chơi đứng trong lối, và hiệu năng Web trên máy mục tiêu.

## Kết quả đã chạy — 09/11/2026

- Unity **6000.6.8f1** biên dịch runtime/editor/test assemblies thành công; log cuối không có C# warning/error của prototype.
- **10/10 EditMode tests PASS** (`Logs/JobSimu-tests.xml`): cấu trúc scene; spawn/tuyến bị chặn; thứ tự nhiệm vụ và chống ghi nhận trùng; giới hạn pitch; raycast bị tường che; cửa tránh capsule; chuyển động cửa ở bước thời gian 0,1 ms; hội thoại; bảo toàn scene/prefab khi tạo lại.
- **1/1 PlayMode smoke test PASS** (`Logs/JobSimu-play-tests.xml`): cửa mở bằng Update thực, CharacterController đi qua các tuyến, bốn lần tương tác bằng raycast hoàn thành vòng làm quen.
- **Validator PASS** (`Logs/JobSimu-validation.txt`): 486 renderers, 344 colliders. Đây là số đối tượng trong scene, không phải draw calls/FPS.
- Đã render và xem ba ảnh bằng GPU/OpenGL trong Unity: `Logs/JobSimu-courtyard.png`, `Logs/JobSimu-classroom.png`, `Logs/JobSimu-campus.png`. Ảnh camera không chứa HUD. Bảng chữ dùng shader URP kiểm tra chiều sâu, không xuyên tường.
- Đã tạo **15 tệp C#**, **4 prefab**, **17 material**, shader world text và scene riêng. `.gitignore` loại trừ cache, log và build output của Unity.
- `Assets/SchoolPrototype.unity` giữ nguyên SHA-256: `023937abf8ab56d7a05a20e98b9580edf55c47b284a6a3e47539d2a07e6b2f7a`.
- Chưa thực hiện Web build, thử chơi bằng chuột/bàn phím vật lý hoặc đo FPS trình duyệt. Các giới hạn này không được suy ra là đã đạt từ kiểm thử headless.
