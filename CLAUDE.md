# CLAUDE.md — JOBSIMU 3D WORKSPACE INSTRUCTIONS

> **Project:** JOBSIMU 3D — Người Thầy Sau Tiếng Trống Trường (Teacher 8A Vertical Slice)  
> **Active Design Spec:** [`docs/TEACHER_8A_REDESIGN.md`](file:///home/minhphuc/jobsimu/docs/TEACHER_8A_REDESIGN.md) | **Rules:** [`AGENTS.md`](file:///home/minhphuc/jobsimu/AGENTS.md)  
> **Repository:** `vangminhphuc209-gif/jobsimu`

---

## 1. CORE ARCHITECTURAL INVARIANTS

1. **Unity `JobSimu3D/` is the Primary 3D Game:** First-person Unity 6 (`6000.6.8f1`) URP project, centered on `JobSimu3D/Assets/_JobSimu/Scenes/SharedFrameworkDemo.unity`. Keep `game/` (Babylon.js) intact as a legacy transition artifact; do not delete it and do not rewire PHP portal routing (`app/simulation-3d.php`) yet.
2. **Protect Legacy PHP & Keep `api/game/*` Fail-Closed:** Never break `app/`, `index.php`, or `database/schema.sql`. Keep `api/game/*` returning `503 GAME_SYNC_UNAVAILABLE` until a server-validated protocol is explicitly implemented and approved.
3. **Deterministic & Testable Gameplay (No LLM in Loop):** Student states, phase transitions, delayed events, and evidence reports run deterministically in C# (`ScenarioSession`) with full replay support in `LocalSaveStore`. No `eval()`, no LLM controlling students/scoring/quests.
4. **Consistent School & Lesson Context:** Fictional **Trường THCS Bình Minh**, new teacher leading one 45-minute **Khoa học tự nhiên** period for **Lớp 8A** under **Cô Mai**: investigating why some spots in the schoolyard puddle after rain via observation and sample comparison. Eliminate any legacy THPT/An Hòa/9A/10A1/climate-change mismatch.
5. **No Single-Score Career Verdict or Hidden Head-Floating Numbers:** Never show numeric empathy/understanding meters above NPCs. Never emit "suitable X%" or "unsuitable" verdicts. Report title: **`Hồ sơ trải nghiệm nghề giáo viên — phiên thử nghiệm`**, clearly separating observed facts, player self-report, and session-scoped inferences with alternative explanations.
6. **Pedagogical Fairness:** Initial student traits are situational starting states, not fixed labels or medical diagnoses. Silence (`Linh`) does not equal lack of understanding; one student speaking does not verify whole-class mastery; repeat/spam interactions never fabricate evidence.

---

## 2. HUMAN-IN-THE-LOOP APPROVAL GATES

Stop and request confirmation before:
1. Running real DB migrations or rewriting Git history.
2. Modifying PHP website routing (`app/simulation-3d.php`, `app/simulation-controller.php`) or re-enabling `api/game/*`.
3. Buying/importing third-party commercial assets or expanding to additional professions/full-day scope before the 8A lesson passes acceptance.
4. Merging into `main` or deploying to production (always work on a dedicated branch and open a PR).
