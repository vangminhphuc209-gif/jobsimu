# AGENTS.md — JOBSIMU 3D: NGƯỜI THẦY SAU TIẾNG TRỐNG TRƯỜNG

> **Single Source of Truth (SSOT):** `docs/game3d.md`  
> **Repository:** `vangminhphuc209-gif/jobsimu`  
> **Mục tiêu ưu tiên:** Hoàn thiện **Vertical Slice — Ngày 1 (Nghề Giáo viên THCS)** chạy trực tiếp trên trình duyệt (Babylon.js + TypeScript + Vite), tích hợp liền mạch với nền tảng PHP/MySQL hiện có.

---

## 1. NGUYÊN TẮC KIẾN TRÚC BẤT BIẾN ( ARCHITECTURAL INVARIANTS )

Mọi Agent (Orchestrator và Subagents) **BẮT BUỘC** tuân thủ 6 nguyên tắc bất biến sau:

1. **Bảo toàn ứng dụng PHP Legacy:**
   - **KHÔNG** viết lại hoặc phá vỡ cấu trúc hiện tại của website Hướng Nghiệp 360 (`index.php`, `app/bootstrap.php`, `app/functions.php`, `app/simulations.php`, `app/simulation-controller.php`).
   - Giữ nguyên chế độ **"Mô phỏng nhanh"** làm chế độ mặc định; chỉ bổ sung cổng vào **"Trải nghiệm nghề 3D"** cho nghề `giao-vien` khi đã sẵn sàng.
   - **KHÔNG** sửa đổi trực tiếp các bảng cũ trong `database/schema.sql` (`users`, `careers`, `simulation_attempts`, `assessments`).

2. **Cô lập 3D Client trong `game/`:**
   - Toàn bộ mã nguồn 3D Client được đặt biệt lập trong `game/` (Babylon.js + TypeScript + Vite).
   - Bản build tĩnh xuất ra `game/dist/`.
   - **KHÔNG** dùng Unity, Unreal hay bất kỳ engine ngoài trình duyệt nào.

3. **Cô lập Game Backend & Database Migration:**
   - Toàn bộ API game đặt trong `api/game/` (`start.php`, `state.php`, `events.php`, `save.php`, `finish.php`).
   - Mọi bảng dữ liệu mới phục vụ 3D phải nằm trong file migration riêng: `database/migrations/2026_11_game3d_init.sql` (6 bảng: `game_runs`, `game_save_snapshots`, `game_events`, `game_npc_states`, `game_quests`, `game_reports`).
   - Chạy cùng origin với app PHP để tái sử dụng `$_SESSION` và kiểm tra CSRF token.

4. **Tuyệt đối không dùng `eval()` trong JSON Content (No eval in JSON):**
   - Tuyệt đối cấm sử dụng `eval()`, `new Function()`, hoặc giải mã chuỗi code động từ JSON.
   - Mọi biểu thức điều kiện (Condition Evaluation) phải chạy qua bộ parser kiểu an toàn (*ConditionEvaluator* / AST-based pattern matching).

5. **Không dùng chỉ số đánh giá đơn lẻ (No Single-Score Metric):**
   - Tuyệt đối cấm một điểm số "giỏi giáo viên" duy nhất.
   - Báo cáo hướng nghiệp cuối ngày phải dựa trên **bằng chứng hành vi đa chiều** (5 nhóm năng lực: Phân tích, Sáng tạo, Thấu cảm, Ra quyết định, Chuẩn xác) và nhật ký phản tư.
   - Server tuyệt đối **từ chối** mọi biến `score` do client gửi lên (`QA-09`).

6. **Chuẩn mực Sư phạm & An toàn Tâm lý Học đường Nghiêm ngặt:**
   - Không thưởng điểm hay khuyến khích hành vi sỉ nhục học sinh trước lớp, bạo lực hay xâm phạm đời tư.
   - Không dán nhãn học sinh là "hư", "kém" hoặc tự suy diễn chẩn đoán y tế/bệnh lý tâm lý (ví dụ: không gán bệnh lý cho sự mất tập trung của Minh).
   - Với tình huống nhạy cảm/an toàn học đường (`CRISIS-03`), bắt buộc tuân theo quy trình: **Lắng nghe → Bảo vệ an toàn → Không hứa bí mật vô điều kiện → Chuyển tuyến cho người có trách nhiệm (Cô Lan y tế/tư vấn tâm lý hoặc Ban giám hiệu)**.

---

## 2. ĐĂNG KÝ AGENT SKILLS ( REGISTERED AGENT SKILLS )

Hệ thống đăng ký 4 bộ kỹ năng chuyên biệt đặt tại `.agents/skills/`:

1. **`skill:babylon-scene-blockout`** (`.agents/skills/babylon-scene-blockout/SKILL.md`)
   - **Phạm vi P0:** Xây dựng blockout hình học, ánh sáng, va chạm (colliders) cho 4 phân vùng ưu tiên: **Lớp 9A (Class9A), Phòng giáo viên (StaffRoom), Hành lang (Corridor), Sân trường & Cổng trường (Yard)**.
   - **Kỹ thuật:** Áp dụng mesh instancing cho bàn ghế đôi, quạt trần, tủ sách; bố trí invisible wall ngăn rơi khỏi map; thiết lập 3 preset đồ họa (`Low`, `Medium`, `High`) đảm bảo đạt 30–60 FPS.

2. **`skill:open-event-compiler`** (`.agents/skills/open-event-compiler/SKILL.md`)
   - **Công thức 10 thành phần tình huống mở:**
     1. Bối cảnh & Mục tiêu (Context & Objective)
     2. Điều kiện kích hoạt (Trigger & Preconditions)
     3. Tín hiệu 3D nhận biết (Visual/Audio Sensory Clue)
     4. Thông tin chưa đầy đủ (Hidden/Incomplete Information)
     5. Tối thiểu $\ge 3$ hướng tiếp cận độc lập (Vật lý / Đối thoại / Bằng chứng hoặc nhờ trợ giúp)
     6. Đánh đổi rõ ràng (Trade-off: Thời gian, Sự tập trung, Quan hệ đồng nghiệp/học sinh)
     7. Phản hồi tức thì (Immediate Feedback / Animation / Dialogue)
     8. Hệ quả chậm theo thời gian (Delayed Consequence / Event Sequence)
     9. Cơ chế tự phục hồi chống softlock (Fail-forward / Recovery Mechanism)
     10. Bằng chứng đánh giá năng lực (Behavioral Evidence Telemetry)
   - **Kiểm định:** Chặn hoàn toàn mẫu A-B-C visual novel; bắt buộc validate qua TypeScript interface `GameEvent`, `GameAction`.

3. **`skill:npc-hybrid-ai`** (`.agents/skills/npc-hybrid-ai/SKILL.md`)
   - **Mô hình lai 4 lớp:** Lịch trình thời gian (`SchoolSchedule` 06:45–16:45) + Máy trạng thái hữu hạn (`FSM`: Idle → Walking → Seated → Listening → Reacting → Talking → Resume) + Quy tắc thỏa dụng (`Utility Rules`) + Cờ bộ nhớ (`MemoryFlags`).
   - **8–10 NPC Chủ chốt:** Cô Mai (mentor), Cô Hạnh (chủ nhiệm/BGH), Thầy Quân (tin học), Minh (học sinh then chốt), Linh (lớp trưởng), Nam (học sinh phản biện), Hà (học sinh hướng nội/vẽ), Tuấn (học sinh hiếu động), Chị Thu (phụ huynh Minh), Cô Lan (y tế/tư vấn).

4. **`skill:php-save-sync`** (`.agents/skills/php-save-sync/SKILL.md`)
   - **Cơ chế lưu & đồng bộ an toàn:** Xác thực session PHP (`$_SESSION['user_id']`), kiểm tra CSRF token, optimistic revision tracking (`revision`), chống ghi trùng lặp bằng `idempotency_key`, kiểm soát chuỗi sự kiện (`event_seq`).
   - **Chống gian lận:** Xác minh tính hợp lệ của chuỗi `action_id`, server-side state recalculation, từ chối client state tampering.

---

## 3. DANH SÁCH MCP WHITELIST ( READ/WRITE SCOPED )

Mọi agent chỉ được tương tác thông qua các công cụ MCP được cấp quyền rõ ràng:

| MCP Server | Quyền hạn (Scope) | Mục đích sử dụng |
|---|---|---|
| `mcp-github` | Repo: `vangminhphuc209-gif/jobsimu` | Tạo nhánh, quản lý PR, kiểm tra commit history, push thay đổi sau khi được duyệt. |
| `mcp-mysql` | Schema inspect & Migration test | Đọc cấu trúc bảng cũ, chạy thử và kiểm tra cú pháp file migration `2026_11_game3d_init.sql` trên DB dev/test. |
| `mcp-playwright` | WebGL E2E & FPS profiling | Chạy test headless browser, chụp ảnh màn hình WebGL canvas, đo FPS, kiểm thử tự động `QA-01..12`. |
| `mcp-filesystem` | Scoped to repo `/home/minhphuc/jobsimu` | Đọc/ghi mã nguồn, assets, test fixtures trong giới hạn workspace. Cấm truy cập ngoài repo. |

---

## 4. MULTI-AGENT SWARM ( STRICT 5 SUBAGENTS + GIT WORKTREES )

Nhằm triệt tiêu nguy cơ xung đột mã nguồn (merge conflicts) và quá tải ngữ cảnh, kiến trúc triển khai gồm **1 Lead Orchestrator + Đúng 5 Subagents chuyên biệt**, hoạt động trên **Git Worktrees độc lập**:

```text
/home/minhphuc/jobsimu/ (main - Lead Orchestrator)
├── wt-backend/          (feat/backend-api          -> @agent-backend)
├── wt-3d/               (feat/engine-3d            -> @agent-engine-3d)
├── wt-narrative/        (feat/gameplay-narrative   -> @agent-gameplay-narrative)
├── wt-npc-ui/           (feat/npc-ui               -> @agent-npc-ui)
└── wt-qa/               (feat/qa-eval              -> @agent-qa-eval)
```

### Phân công chi tiết:

1. **`@lead-orchestrator` (Nhánh: `main` | Cwd: `./`)**
   - Quản lý Plan Mode, điều phối DAG, phân việc cho subagents, duyệt PR và kiểm tra gatekeeper trước khi merge vào `main`.
   - Quyền ghi: `AGENTS.md`, `CLAUDE.md`, `docs/**`, `.agents/**`.

2. **`@agent-backend` (Worktree: `wt-backend` | Nhánh: `feat/backend-api`)**
   - **Phạm vi:** `api/game/**`, `database/migrations/**`, `game/src/api/**`.
   - **Nhiệm vụ:** 6 bảng MySQL (`game_runs`, `game_save_snapshots`, `game_events`, `game_npc_states`, `game_quests`, `game_reports`), API REST PHP (`start.php`, `state.php`, `events.php`, `save.php`, `finish.php`), CSRF, session validation, optimistic locking, server-side anti-tamper.

3. **`@agent-engine-3d` (Worktree: `wt-3d` | Nhánh: `feat/engine-3d`)**
   - **Phạm vi:** `game/src/core/**`, `game/src/player/**`, `game/src/world/**`, `game/public/assets/**`.
   - **Nhiệm vụ:** Khởi tạo Babylon.js engine, ArcRotate/Universal Third-Person Camera chống xuyên tường, Character Controller (WASD + Shift chạy, Space, Raycast E interactor), Blockout 4 phân vùng P0 (Lớp 9A, Phòng GV, Hành lang, Sân), vật liệu, ánh sáng, tối ưu FPS (LOD, mesh instancing, texture mipmaps).

4. **`@agent-gameplay-narrative` (Worktree: `wt-narrative` | Nhánh: `feat/gameplay-narrative`)**
   - **Phạm vi:** `game/src/narrative/**`, `game/src/systems/**`, `game/content/**`.
   - **Nhiệm vụ:** Vòng lặp 7 bước gameplay, đồng hồ học đường `SchoolClock` (06:45–16:45), hệ thống 7 Checkpoints (`CP-01` đến `CP-07`), cơ chế Classroom Simulator (chia nhóm quan sát, xử lý câu hỏi phản biện, thu phiếu exit ticket), nội dung JSON cho các sự kiện mở (`OBS-01..02`, `PREP-01..02`, `MOVE-01..02`, `CLASS-01..03`, `CRISIS-01..03`, `FEEDBACK-01..02`, `REFLECT-01..03`), và 3 Arcs (Arc A: Minh Trust, Arc B: Pacing vs Understanding, Arc C: Fair Grading Rubric).

5. **`@agent-npc-ui` (Worktree: `wt-npc-ui` | Nhánh: `feat/npc-ui`)**
   - **Phạm vi:** `game/src/npc/**`, `game/src/ui/**`.
   - **Nhiệm vụ:** Hybrid NPC Scheduler & FSM cho 10 NPC chủ chốt + NPC nền, Contextual HUD (tương tác `[E]`, mục tiêu hiện tại, đồng hồ trường học), Tab Planner (kế hoạch bài giảng), J Journal (nhật ký và cờ quan hệ), Dialogue UI (hộp thoại có cử chỉ/biểu cảm, hiển thị đánh đổi), End-of-Day Evidence Report UI.

6. **`@agent-qa-eval` (Worktree: `wt-qa` | Nhánh: `feat/qa-eval`)**
   - **Phạm vi:** `game/tests/**`.
   - **Nhiệm vụ:** Viết và thực thi bộ kiểm thử tự động Unit/Integration (Vitest) và Playwright E2E cho trọn bộ kịch bản từ `QA-01` đến `QA-12`, bao gồm cả bài test phân kỳ 3 kịch bản chơi (3-Run Divergence Eval).

---

## 5. AUTOMATION: SLASH COMMANDS & HOOKS

### 5.1. Slash Commands (`.agents/commands/`)
- `/init-slice`: Tự động scaffold khung thư mục `game/` (Vite + TS + Babylon.js) và `api/game/`.
- `/compile-event <ID>`: Sinh và kiểm tra JSON sự kiện `<ID>` theo đúng 10 thành phần, kiểm tra type TypeScript (`GameAction`, `GameEvent`, `GameSnapshot`).
- `/verify-loop <STEP_1_TO_7>`: Đánh giá một bước trong 7 bước theo 8 câu hỏi nghiệm thu vòng lặp mở (Mục 6.9 GDD).
- `/run-qa <QA_01_TO_12|all>`: Chạy ma trận test bắt buộc từ `QA-01` đến `QA-12` và đánh giá độ phân kỳ (Divergence Check).
- `/plan-p0`: Kiểm tra tiến độ 13 đầu việc backlog P0 và xuất trạng thái thực thi hiện tại.
- `/init-worktrees`: Tự động tạo và liên kết 5 Git worktrees cho 5 Subagents.

### 5.2. Automated Hooks (`.agents/hooks/`)
- **`pre-edit`**:
  - Chặn sửa đổi các tệp legacy trong `app/`, `index.php`, `database/schema.sql` nếu chưa có cờ phê duyệt rõ ràng từ người dùng.
- **`post-edit`**:
  - Tệp `.ts` / `.tsx`: Tự động chạy `tsc --noEmit` để bắt lỗi kiểu.
  - Tệp `.php`: Tự động chạy `php -l <filepath>` để bắt lỗi cú pháp.
  - Tệp `.json` trong `game/content/`: Chạy JSON Schema Validator kiểm tra cấu trúc 10 thành phần, ràng buộc enum và tính toàn vẹn khóa ngoại.
- **`pre-commit`**:
  - Quét chuỗi nhạy cảm (API keys, DB credentials, password hashes).
  - Chặn tuyệt đối `node_modules` hoặc build artifacts lọt vào git index hoặc `public_html`.

---

## 6. AGENTIC LOOPS & STRUCTURED OUTPUTS

### 6.1. Vòng lặp Tự sửa lỗi (Self-Healing Loop)
Mỗi Subagent khi thực hiện code hoặc JSON content phải tuân thủ quy trình lặp:

```text
[1. Đọc GDD docs/game3d.md & Schemas]
            ↓
[2. Viết Code TS / PHP hoặc JSON Content]
            ↓
[3. Chạy Hook Kiểm tra tĩnh: tsc --noEmit + php -l + JSON Schema]
            ↓
[4. Chạy Unit Test / Headless Playwright Test]
            ↓
[5. Đạt kết quả? ---(YES)--> Hoàn thành tác vụ
            |
          (NO)
            ↓
[6. Phân tích Error Trace -> Self-Heal (Tối đa 3 lần thử)]
            ↓
(Nếu vượt quá 3 lần -> Báo cáo escalate lên Lead Orchestrator)
```

### 6.2. Kiểm chuẩn Dữ liệu (Strict Structured Schemas)
Tất cả sự kiện (`OBS-01..02`, `PREP-01..02`, `MOVE-01..02`, `CLASS-01..03`, `CRISIS-01..03`, `FEEDBACK-01..02`, `REFLECT-01..03`) và 3 Tuyến truyện (Arc A, Arc B, Arc C) **BẮT BUỘC** định dạng chuẩn JSON và xác thực thành công với JSON Schema trước khi commit.

---

## 7. CỔNG PHÊ DUYỆT CON NGƯỜI ( HUMAN APPROVAL GATES )

Subagents và Orchestrator **BẮT BUỘC DỪNG VÀ XIN PHÉP NGƯỜI DÙNG** tại 4 cổng kiểm soát:

1. **Gate 1 - Database Migration:** Trước khi áp dụng migration `2026_11_game3d_init.sql` vào cơ sở dữ liệu thật hoặc can thiệp lịch sử Git.
2. **Gate 2 - Legacy PHP Integration:** Trước khi chỉnh sửa `app/simulation-controller.php` hoặc gắn liên kết chuyển hướng 3D vào giao diện website cũ.
3. **Gate 3 - Sensitive Pedagogical Content:** Trước khi hoàn thiện nội dung thoại và nhánh hậu quả của các tình huống nhạy cảm (`CRISIS-01`, `CRISIS-03`, `ARC-A`).
4. **Gate 4 - Worktree Merge to Main:** Trước khi gộp (merge/rebase) code từ bất kỳ worktree nào (`wt-backend`, `wt-3d`, `wt-narrative`, `wt-npc-ui`, `wt-qa`) vào nhánh `main`.

---

## 8. TRACING, AGENT EVALS & DIVERGENCE TEST

- **3-Run Divergence Evaluation:** Hệ thống QA tự động chạy 3 kịch bản chuẩn bị khác nhau tại `PREP-01` (ví dụ: Run 1 - Chuẩn bị bản in đầy đủ; Run 2 - Không in, tin tưởng máy chiếu; Run 3 - Chuẩn bị giáo án thảo luận nhóm không phụ thuộc máy chiếu).
- **Tiêu chí nghiệm thu:** Cả 3 runs **BẮT BUỘC** tạo ra các trạng thái lớp học (`CLASS-01`, `CRISIS-02`), hành vi NPC và báo cáo bằng chứng cuối ngày hoàn toàn khác biệt. Nếu kết quả trùng lặp hoặc mang tính chất giả vờ (Visual Novel A/B/C), bài test sẽ bị đánh trượt (FAIL).
- **Context Engineering & RAG:** Chỉ nạp các phần tài liệu thích hợp từ `docs/game3d.md`, `docs/STORY_BIBLE.md`, `docs/ASSET_MANIFEST.md` vào ngữ cảnh của từng subagent theo đúng phân quyền (Principle of Least Context).

---

## 9. THỨ TỰ THỰC THI P0 BACKLOG ( EXECUTION DAG )

```text
[SEC-01: Audit Git & Secrets] 
       ↓
[DOC-01: Establish Docs & Story Bible]
       ↓
[TECH-01: Scaffold game/ BabylonJS + TS + Vite]
       ↓
[3D-01: 4 P0 Blockouts Class9A, StaffRoom, Corridor, Yard]
       ↓
[PLAYER-01: 3rd Person Controller & E Interactor]
       ↓
[NPC-01: Core NPC Hybrid AI (Mai, Minh, Linh...)]
       ↓
[EVENT-01: Open Event Compiler & 10-Part Engine]
       ↓
[PREP-01: Projector Crisis & Lesson Prep]
       ↓
[CLASS-01: Classroom Sim Mini-Loop (Groups, Nam, Exit Ticket)]
       ↓
[SAVE-01: PHP API & MySQL Migrations]
       ↓
[ARC-01: 3 Integrated Story Arcs (Minh, Pacing, Rubric)]
       ↓
[REPORT-01: Evidence-Based Career Report UI]
       ↓
[POLISH-01: Audio, Accessibility, Graphics Presets, QA-01..12]
```
