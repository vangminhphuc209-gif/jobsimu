# AGENTS.md — JOBSIMU 3D: NGƯỜI THẦY SAU TIẾNG TRỐNG TRƯỜNG

> **Single Source of Truth (SSOT):** `docs/game3d.md`  
> **Repository:** `vangminhphuc209-gif/jobsimu`  
> **Mục tiêu ưu tiên:** Hoàn thiện **Vertical Slice — Ngày 1 (Nghề Giáo viên THCS)** chạy trực tiếp trên trình duyệt (Babylon.js + TypeScript + Vite), tích hợp liền mạch với nền tảng PHP/MySQL hiện có.

---

## 1. NGUYÊN TẮC KIẾN TRÚC BẤT BIẾN ( ARCHITECTURAL INVARIANTS )

Mọi Agent (Orchestrator và Subagents) **BẮT BUỘC** tuân thủ các quy tắc bất biến sau trước khi đọc hoặc sửa bất kỳ dòng code nào:

1. **Bảo toàn hệ thống Legacy PHP/MySQL:**
   - **KHÔNG** viết lại hoặc phá vỡ cấu trúc hiện tại của website Hướng Nghiệp 360 (`index.php`, `app/bootstrap.php`, `app/functions.php`, `app/simulations.php`, `app/simulation-controller.php`).
   - **KHÔNG** sửa đổi trực tiếp các bảng cũ trong `database/schema.sql` (`users`, `careers`, `simulation_attempts`, `assessments`). Mọi bảng mới cho chế độ 3D phải đặt trong file migration riêng: `database/migrations/2026_11_game3d_init.sql`.
   - Giữ nguyên chế độ **"Mô phỏng nhanh"** hiện tại làm chế độ cổ điển; chỉ bổ sung cổng vào **"Trải nghiệm nghề 3D"** cho nghề `giao-vien`.

2. **Cô lập Module 3D Client & Game API:**
   - Toàn bộ mã nguồn 3D Client đặt trong `game/` (TypeScript + Babylon.js + Vite). Bản build tĩnh xuất ra `game/dist/`.
   - Toàn bộ API đồng bộ trạng thái đặt trong `api/game/` (`start.php`, `state.php`, `events.php`, `save.php`, `finish.php`), chạy cùng origin để dùng lại phiên đăng nhập PHP (`$_SESSION`) và kiểm tra CSRF.
   - **KHÔNG** dùng Unity, Unreal hay bất kỳ engine ngoài trình duyệt nào.
   - **KHÔNG** dùng hàm `eval()` hoặc `new Function()` để thực thi logic điều kiện từ file JSON. Bắt buộc viết bộ *Condition Evaluator* kiểm tra kiểu an toàn.

3. **Triết lý Gameplay Mở (Zero Visual-Novel / Zero A-B-C Quiz):**
   - Cấm thiết kế sự kiện chỉ gồm một hộp thoại có 3 nút chọn dẫn tới cùng một kết quả.
   - Mọi sự kiện quan trọng phải thỏa mãn **Công thức 10 thành phần** (Mục 3.2 trong `docs/game3d.md`): Bối cảnh, Trigger, Dấu hiệu nhận biết 3D, Thông tin ẩn, $\ge 3$ kiểu tiếp cận (Hành động vật lý / Đối thoại / Tìm bằng chứng hoặc nhờ trợ giúp), Đánh đổi hợp lý, Phản ứng tức thì, Hệ quả chậm, Phương án phục hồi chống softlock, và Bằng chứng đánh giá.
   - **Không dùng một điểm số "giỏi giáo viên" duy nhất.** Server tuyệt đối không tin bất kỳ biến `score` nào gửi lên từ trình duyệt (`QA-09`).

4. **Chuẩn mực Sư phạm & Quyền riêng tư:**
   - Không thưởng điểm hoặc tạo lợi thế gameplay cho hành vi sỉ nhục học sinh trước lớp, bạo lực, hoặc xâm phạm đời tư.
   - Không dán nhãn học sinh là "hư", "kém" hay tự suy diễn chẩn đoán tâm lý/bệnh lý (ví dụ: không gán bệnh lý cho hành vi mất tập trung của Minh).
   - Với tình huống nhạy cảm/an toàn học đường (`CRISIS-03`), bắt buộc dẫn theo quy trình: **Lắng nghe $\rightarrow$ Bảo vệ an toàn $\rightarrow$ Không hứa giữ bí mật vô điều kiện $\rightarrow$ Chuyển tuyến cho người có trách nhiệm (Cô Lan y tế/tư vấn hoặc Ban giám hiệu)**.

---

## 2. CẤU TRÚC THƯ MỤC ĐÍCH & QUYỀN SỞ HỮU FILE ( FILE OWNERSHIP )

```text
jobsimu/
├── AGENTS.md                         # File điều phối trung tâm này
├── app/                              # [PROTECTED] PHP website hiện tại (chỉ mở cổng vào 3D khi được duyệt)
├── api/game/                         # [@agent-backend] REST/JSON endpoints cho game 3D
│   ├── start.php
│   ├── state.php
│   ├── events.php
│   ├── save.php
│   └── finish.php
├── database/
│   ├── schema.sql                    # [READ-ONLY] Schema gốc
│   └── migrations/
│       └── 2026_11_game3d_init.sql   # [@agent-backend] 6 bảng dữ liệu game 3D
├── game/                             # Dự án Vite + TypeScript + Babylon.js
│   ├── src/
│   │   ├── core/                     # [@agent-3d-world] Game loop, SceneManager, EventBus, State
│   │   ├── player/                   # [@agent-3d-world] CharacterController, ThirdPersonCamera, Interactor
│   │   ├── world/                    # [@agent-3d-world] SchoolScene, SchoolClock, Interactables
│   │   ├── narrative/                # [@agent-gameplay] StoryGraph, ConditionEvaluator, ConsequenceEngine
│   │   ├── systems/                  # [@agent-gameplay] ClassroomSim, QuestSystem, DialogueSystem, SaveClient
│   │   ├── npc/                      # [@agent-npc-qa] NPCController, Scheduler, FSM, MemoryFlags
│   │   ├── ui/                       # [@agent-npc-qa] HUD, LessonPlannerUI, JournalUI, CareerReportUI
│   │   └── api/                      # [@agent-backend / @agent-gameplay] Typed HTTP Client
│   ├── content/                      # [@agent-gameplay] Dữ liệu JSON có schema kiểm soát
│   │   ├── characters/
│   │   ├── days/
│   │   ├── events/
│   │   └── dialogues/
│   ├── public/assets/                # [@agent-3d-world] GLB/glTF, textures, audio
│   └── tests/                        # [@agent-npc-qa] Vitest unit tests & Playwright E2E tests
└── docs/
    ├── game3d.md                     # GDD gốc
    ├── STORY_BIBLE.md                # Hồ sơ 10 NPC chủ chốt & quy tắc sư phạm
    └── ASSET_MANIFEST.md             # Quản lý nguồn, license, polycount budget
```

---

## 3. ĐIỀU PHỐI ĐA AGENT & GIT WORKTREES ( MULTI-AGENT SWARM )

Để ngăn chặn xung đột code (merge conflicts) và tràn ngữ cảnh, dự án giới hạn đúng **1 Orchestrator + 4 Subagents chuyên biệt**. Mỗi Subagent chạy trên một **Git Worktree riêng biệt**:

| Agent ID | Vai trò | Nhánh & Git Worktree | Phạm vi được phép sửa (Write Scope) |
|---|---|---|---|
| `@lead-orchestrator` | Kiến trúc sư trưởng, quản lý Plan Mode, duyệt PR giữa các worktree | `main` (`./`) | `AGENTS.md`, `docs/**`, `.agents/**` |
| `@agent-backend` | Bảo mật Git, PHP Game API, MySQL Migrations, chống cheat | `feat/backend-api` (`../jobsimu-wt-backend`) | `api/game/**`, `database/migrations/**`, `game/src/api/**` |
| `@agent-3d-world` | Babylon.js Engine, Blockout 4 khu P0, Camera, Player, Tối ưu FPS | `feat/3d-world` (`../jobsimu-wt-3d`) | `game/src/core/**`, `game/src/player/**`, `game/src/world/**`, `game/public/assets/**` |
| `@agent-gameplay` | Vòng lặp 7 bước, Classroom Simulator, 15+ Sự kiện mở, 3 Arcs cốt truyện | `feat/gameplay-loop` (`../jobsimu-wt-gameplay`) | `game/src/narrative/**`, `game/src/systems/**`, `game/content/**` |
| `@agent-npc-qa` | Hành vi lai 10 NPC, HUD/UI, Báo cáo Hướng nghiệp, Kiểm thử `QA-01..12` | `feat/npc-ui-qa` (`../jobsimu-wt-npc-qa`) | `game/src/npc/**`, `game/src/ui/**`, `game/tests/**` |

**Quy tắc Phối hợp Worktree:**
- Subagent nào cần thay đổi interface chung (ví dụ `game/src/core/types.ts`) phải gửi yêu cầu lên `@lead-orchestrator` để cập nhật ở nhánh chính trước khi rebase.
- Tuyệt đối không có 2 Subagents cùng sửa một file JSON hoặc một scene 3D tại cùng một thời điểm.

---

## 4. DANH MỤC AGENT SKILLS ( `.agents/skills/` )

1. **`skill:sec-audit-and-env`**
   - Rà soát toàn bộ Git history tìm mật khẩu/khóa bị lộ (`config.php`, `.env`), thiết lập `.env.example`, `config.example.php` và đảm bảo không đẩy `node_modules` lên `public_html`.
2. **`skill:babylon-p0-blockout`**
   - Dựng 4 phân vùng ưu tiên P0: **Lớp 9A, Phòng giáo viên, Hành lang, Sân trường/Cổng**.
   - Áp dụng mesh instancing cho bàn ghế đôi, quạt trần; gắn collider chuẩn xác; thiết lập 3 mức chất lượng đồ họa (`Low`, `Medium`, `High`) đảm bảo $\ge 30\text{ FPS}$ trên máy cấu hình thấp.
3. **`skill:open-event-compiler`**
   - Biên dịch và kiểm tra các sự kiện mở (`OBS-01..02`, `PREP-01..02`, `MOVE-01..02`, `CLASS-01..03`, `CRISIS-01..03`, `FEEDBACK-01..02`, `REFLECT-01..03`) tuân thủ `GameEvent` và `GameAction` schema.
   - Đảm bảo mỗi bước trong 7 bước gameplay có ít nhất 1 tình huống phát sinh chủ động và 1 cơ hội mở qua khám phá.
4. **`skill:npc-hybrid-controller`**
   - Vận hành 10 NPC chủ chốt (**Cô Mai, Cô Hạnh, Thầy Quân, Minh, Linh, Nam, Hà, Tuấn, Chị Thu, Cô Lan**) và 12–18 NPC nền.
   - Kết hợp Lịch trình theo giờ (`06:45` – `16:45`) + FSM (`Idle -> Walking -> Seated -> Listening -> Reacting -> Talking -> Resume`) + Utility Rules dựa trên biến quan hệ (`minh_trust`, `mentor_support`, `colleague_cooperation`, `parent_confidence`).
5. **`skill:evidence-report-generator`**
   - Tổng hợp nhật ký hành động thực tế cuối ngày thành báo cáo hướng nghiệp đa chiều (5 nhóm năng lực tham khảo: *Phân tích, Sáng tạo, Thấu cảm, Ra quyết định, Chính xác*).
   - Nếu người chơi bỏ qua bước kiểm tra hiểu bài cuối giờ (`FEEDBACK-01`), báo cáo phải xuất trạng thái `"Chưa đủ dữ liệu bằng chứng"`, không tự động chấm điểm cao.

---

## 5. SLASH COMMANDS & HOOKS TỰ ĐỘNG

### 5.1. Slash Commands (`.agents/commands/`)
- `/plan-p0`: Kích hoạt Plan Mode, kiểm tra tiến độ 13 đầu việc P0 (`[SEC-01]` $\rightarrow$ `[POLISH-01]`) và xuất kế hoạch thực thi tiếp theo.
- `/init-worktrees`: Tự động tạo 4 nhánh và 4 thư mục Git Worktree cho 4 Subagents.
- `/compile-event <EVENT_ID>`: Sinh dữ liệu JSON cho sự kiện `<EVENT_ID>`, kiểm tra đủ 10 thành phần tình huống mở và chạy trình kiểm tra kiểu TypeScript.
- `/check-loop <STEP_1_TO_7>`: Kiểm tra một bước trong vòng lặp 7 bước xem đã trả lời **"Có"** cho đủ **8 câu hỏi nghiệm thu vòng lặp mở** (Mục 6.9 GDD) hay chưa.
- `/run-qa <QA_ID|all>`: Thực thi bộ test tự động từ `QA-01` đến `QA-12` và bài test phân kỳ 3 nhánh chạy (3-Run Divergence Eval).

### 5.2. Automated Hooks (`.agents/hooks/`)
- **`pre-edit`**:
  - Chặn chỉnh sửa các file trong `app/` và `database/schema.sql` nếu chưa có cờ phê duyệt của người dùng (Human Approval).
- **`post-edit`**:
  - Khi sửa file `.ts` / `.tsx`: Chạy `npm --prefix game run typecheck` (`tsc --noEmit`).
  - Khi sửa file `.json` trong `game/content/`: Chạy script validate schema JSON và kiểm tra các tham chiếu `targetId`, `actionId`, `eventId`.
  - Khi sửa file `.php`: Chạy `php -l <filepath>` để bắt lỗi cú pháp ngay lập tức.
- **`pre-commit`**:
  - Quét chuỗi nhạy cảm (API keys, DB passwords).
  - Kiểm tra chắc chắn `game/node_modules/` nằm trong `.gitignore`.

---

## 6. VÒNG LẶP TỰ SỬA LỖI ( AGENTIC LOOPS ) & STRUCTURED SCHEMAS

Mọi tác vụ lập trình của Subagent phải tuân theo vòng lặp khép kín (tối đa **3 lần thử lại** trước khi báo cáo lên Orchestrator):

```text
[1. Đọc mục tương ứng trong docs/game3d.md]
        ↓
[2. Viết Code TypeScript / PHP hoặc JSON Content]
        ↓
[3. Chạy Hook Kiểm tra tĩnh: tsc --noEmit + php -l + JSON Schema Validator]
        ↓
[4. Chạy Unit Test (Vitest) & Headless WebGL Test (Playwright)]
        ↓
[5. Kiểm tra Phân kỳ (Divergence Check): 3 cách chơi khác nhau phải sinh ra State & Báo cáo khác nhau]
        ↺ (Nếu lỗi ở bước 3, 4, 5 -> Đọc stack trace -> Tự sửa -> Chạy lại)
```

### Hợp đồng TypeScript & Database Bắt buộc:
- **6 Bảng MySQL (`2026_11_game3d_init.sql`):**
  1. `game_runs` (`id`, `user_id`, `career_id`, `content_version`, `day_index`, `status`, `started_at`, `updated_at`)
  2. `game_save_snapshots` (`run_id`, `revision`, `snapshot_json`, `saved_at`)
  3. `game_events` (`id`, `run_id`, `event_seq`, `event_id`, `action_id`, `payload_json`, `created_at`)
  4. `game_npc_states` (`run_id`, `npc_id`, `state_json`, `updated_at`)
  5. `game_quests` (`run_id`, `quest_id`, `status`, `progress_json`)
  6. `game_reports` (`run_id`, `report_version`, `summary_json`, `completed_at`)
- **7 Checkpoints trong Ngày 1:** `CP-01` (06:45 Cổng/Sân), `CP-02` (07:00 Phòng GV), `CP-03` (07:30 Tiết 1 Lớp 9A), `CP-04` (09:00 Tiết 2), `CP-05` (11:30 Nghỉ trưa), `CP-06` (15:00 Phụ huynh/Hỗ trợ HS), `CP-07` (16:00 Tổng kết & Phản tư).

---

## 7. CỔNG PHÊ DUYỆT CON NGƯỜI ( HUMAN-IN-THE-LOOP GUARDRAILS )

Agent **BẮT BUỘC DỪNG LẠI VÀ XIN PHÉP NGƯỜI DÙNG** trước khi thực hiện 4 hành động sau:

1. **Can thiệp Bảo mật & Database:** Xóa/viết lại lịch sử Git (`[SEC-01]`) hoặc chạy lệnh thực thi migration SQL lên cơ sở dữ liệu thật.
2. **Chạm vào Code Legacy:** Sửa `app/simulation-controller.php` hoặc giao diện trang nghề `giao-vien` để gắn nút chuyển sang chế độ 3D.
3. **Duyệt Nội dung Sư phạm Nhạy cảm:** Trước khi chốt lời thoại và nhánh hậu quả của `CRISIS-01` (tranh cãi học sinh), `CRISIS-03` (bắt nạt/an toàn học đường) và `ARC-A` (học sinh Minh).
4. **Hợp nhất Nhánh (Merge Worktrees):** Trước khi merge bất kỳ nhánh Subagent nào vào `main`.

---

## 8. BACKLOG P0 & TIÊU CHÍ NGHIỆM THU ( DEFINITION OF DONE )

### 8.1. Thứ tự 13 bước triển khai P0 (Không được đảo thứ tự)
1. `[SEC-01]` Rà soát git history, rotate secrets nếu lộ, chuẩn hóa `.gitignore`, `.env.example`, `config.example.php`.
2. `[DOC-01]` Lưu `docs/game3d.md`, khởi tạo `docs/STORY_BIBLE.md` và `docs/ASSET_MANIFEST.md`.
3. `[TECH-01]` Khởi tạo `game/` với Vite + TypeScript + Babylon.js, scene cơ bản, renderer, resize, pause menu.
4. `[3D-01]` Blockout Lớp 9A, Phòng giáo viên, Hành lang, Sân trường và placeholder chỗ ngồi lớp học.
5. `[PLAYER-01]` Nhân vật Giáo viên An (3D), góc nhìn thứ ba chống xuyên tường, di chuyển `WASD + Shift`, tương tác `E`.
6. `[NPC-01]` Cô Mai và Minh hoạt động theo lịch, hiển thị thoại/cử chỉ và ghi nhận memory flag (`minh_observed`, `minh_trust`).
7. `[EVENT-01]` Engine xử lý Trigger + Clue + Multi-Action + Immediate/Delayed Consequence + Unit Tests.
8. `[PREP-01]` Sự kiện máy chiếu lỗi và chuẩn bị học liệu dự phòng tại phòng giáo viên có tác động thật tới tiết học.
9. `[CLASS-01]` Mini-loop giảng dạy Lớp 9A: lấy học liệu $\rightarrow$ vào lớp $\rightarrow$ đi giữa các bàn quan sát nhóm 1–4 $\rightarrow$ xử lý câu hỏi phản biện của Nam (`CLASS-02`) $\rightarrow$ thu phiếu cuối giờ (`FEEDBACK-01`).
10. `[SAVE-01]` Hoàn thiện PHP API (`api/game/*`) + MySQL migrations + cơ chế lưu/tải theo `revision` và `idempotency_key`.
11. `[ARC-01]` Kết nối trọn vẹn 3 chuỗi cốt truyện (Arc A: Minh, Arc B: Tiến độ vs Hiểu bài, Arc C: Chấm bài công bằng & Phụ huynh Chị Thu).
12. `[REPORT-01]` Màn hình Báo cáo Hướng nghiệp cuối ngày dẫn chứng từng hành động thực tế và tự phản tư (`REFLECT-03`).
13. `[POLISH-01]` Hoàn thiện hoạt ảnh, âm thanh môi trường học đường, phụ đề, chỉnh chất lượng đồ họa và đo kiểm FPS.

### 8.2. Ma trận Kiểm thử Tự động Bắt buộc (`QA-01` $\rightarrow$ `QA-12`)
Một tính năng chỉ được tính là **HOÀN THÀNH (DONE)** khi vượt qua toàn bộ 12 bài test:
- `QA-01`: Không kiểm tra máy chiếu, vào thẳng tiết học $\rightarrow$ Có fallback bảng phấn hợp lý, không treo game.
- `QA-02`: Chuẩn bị bản in ở `PREP-01`, máy chiếu tắt ở `CRISIS-02` $\rightarrow$ Học liệu dự phòng kích hoạt mượt mà.
- `QA-03`: Đi ngang Minh ở sân trường không dừng lại $\rightarrow$ Sự kiện sau vẫn có nhánh phát triển, không trừ điểm vô lý.
- `QA-04`: Nhắc nhở Minh công khai trước lớp rồi xin lỗi riêng $\rightarrow$ Quan hệ giảm rồi có cơ hội phục hồi có điều kiện.
- `QA-05`: Không thu phiếu/kiểm tra hiểu bài cuối tiết $\rightarrow$ Báo cáo ghi rõ *"Thiếu bằng chứng"*, không tự cho điểm cao.
- `QA-06`: Chậm giờ do xử lý tình huống an toàn học sinh (`CRISIS-03`) $\rightarrow$ Hệ thống ưu tiên an toàn, không phạt máy móc.
- `QA-07`: Hứa giúp thầy Quân (`MOVE-01`) nhưng quên $\rightarrow$ Ghi nhận hệ quả quan hệ và mở nhánh xin lỗi/bù đắp.
- `QA-08`: Thoát game giữa tiết, tải lại (`F5`) $\rightarrow$ Khôi phục đúng checkpoint, không nhân đôi sự kiện hay tiến độ.
- `QA-09`: Dùng DevTools gửi payload `score: 9999` hoặc `action_id` sai thứ tự lên API $\rightarrow$ Server từ chối thẳng.
- `QA-10`: Ngắt mạng khi đang lưu checkpoint $\rightarrow$ UI báo chưa đồng bộ, tự động retry an toàn với `idempotency_key` khi có mạng lại.
- `QA-11`: Đứng yên 30 phút trong thế giới game $\rightarrow$ NPC vẫn theo lịch sinh hoạt, gợi ý nhiệm vụ nhẹ nhàng không gây softlock.
- `QA-12`: Chạy ở preset đồ họa `Low` $\rightarrow$ Vẫn đọc rõ biểu cảm/tín hiệu tình huống, giữ FPS ổn định.