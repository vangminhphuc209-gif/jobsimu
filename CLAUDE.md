# CLAUDE.md — JOBSIMU 3D AGENTIC WORKSPACE INSTRUCTIONS

> **Project:** JOBSIMU 3D — Người Thầy Sau Tiếng Trống Trường (Teacher Simulation Vertical Slice)  
> **Source of Truth:** `docs/game3d.md` | **Orchestrator Rules:** `AGENTS.md`  
> **Repository:** `vangminhphuc209-gif/jobsimu`

---

## 1. CORE ARCHITECTURAL INVARIANTS (NON-NEGOTIABLE)

1. **Protect Legacy PHP Website:** Never break `app/`, `index.php`, or existing tables in `database/schema.sql`. Keep classic "Mô phỏng nhanh" operational.
2. **Isolate 3D Client in `game/`:** Pure browser-based Babylon.js + TypeScript + Vite. Build artifact outputs strictly to `game/dist/`.
3. **Isolate Backend in `api/game/`:** Dedicated endpoints (`start.php`, `state.php`, `events.php`, `save.php`, `finish.php`). Migrations in `database/migrations/2026_11_game3d_init.sql`. Same origin for session auth & CSRF.
4. **No `eval()` in JSON:** All narrative/event condition evaluation must be strongly-typed AST / safe condition evaluator.
5. **No Single-Score Metric:** Reject client-sent `score` variables. Career evaluation is purely evidence-based across 5 competencies (Phân tích, Sáng tạo, Thấu cảm, Ra quyết định, Chuẩn xác).
6. **Strict Pedagogical & Psychological Safety:** No pupil shaming, no medical/pathological labels (e.g. for student Minh). Safe escalation for `CRISIS-03` (Listen → Protect → Non-confidential safety disclosure → Refer to school counselor/nurse Ms. Lan or School Board).

---

## 2. REGISTERED AGENT SKILLS

- `skill:babylon-scene-blockout`: Construct 4 P0 zones (Class9A, StaffRoom, Corridor, Yard), LOD, mesh instancing, colliders, 30–60 FPS.
- `skill:open-event-compiler`: Enforce 10-part open event formula, $\ge 3$ action types, immediate & delayed consequences, TypeScript schema validation.
- `skill:npc-hybrid-ai`: 4-layer architecture (Schedule 06:45–16:45 + FSM + Utility Rules + Memory Flags) for 10 core NPCs (Mai, Hanh, Quan, Minh, Linh, Nam, Ha, Tuan, Thu, Lan).
- `skill:php-save-sync`: Session authentication, CSRF, optimistic locking (`revision`), idempotency keys, event sequence ordering.

---

## 3. MCP WHITELIST & BOUNDARIES

- `mcp-github`: `vangminhphuc209-gif/jobsimu` (PRs, issues, commits, branches).
- `mcp-mysql`: Schema inspection & migration testing on test database.
- `mcp-playwright`: Headless WebGL tests, canvas screenshots, FPS profiling.
- `mcp-filesystem`: Restricted to repository root `/home/minhphuc/jobsimu`.

---

## 4. MULTI-AGENT SWARM & WORKTREE TOPOLOGY

Strict limit: 1 Lead Orchestrator + 5 Specialized Subagents running on dedicated Git worktrees:

- `@lead-orchestrator`: `main` (`./`) — Plan mode, DAG orchestration, gatekeeper.
- `@agent-backend`: `wt-backend` (`feat/backend-api`) — `api/game/**`, `database/migrations/**`.
- `@agent-engine-3d`: `wt-3d` (`feat/engine-3d`) — `game/src/core/**`, `game/src/player/**`, `game/src/world/**`, `game/public/assets/**`.
- `@agent-gameplay-narrative`: `wt-narrative` (`feat/gameplay-narrative`) — `game/src/narrative/**`, `game/src/systems/**`, `game/content/**`.
- `@agent-npc-ui`: `wt-npc-ui` (`feat/npc-ui`) — `game/src/npc/**`, `game/src/ui/**`.
- `@agent-qa-eval`: `wt-qa` (`feat/qa-eval`) — `game/tests/**`.

---

## 5. SLASH COMMANDS & TEST MATRIX

- `/init-slice`: Scaffold `game/` and `api/game/`.
- `/compile-event <ID>`: Generate and validate 10-part event JSON against TS interfaces (`GameAction`, `GameEvent`, `GameSnapshot`).
- `/verify-loop <STEP_1_TO_7>`: Verify compliance with the 8 QA open-loop criteria.
- `/run-qa <QA_01_TO_12|all>`: Run automated tests `QA-01` to `QA-12` and the 3-Run Divergence Evaluation.
- `/plan-p0`: Inspect progress of the 13 P0 backlog items (`SEC-01` to `POLISH-01`).
- `/init-worktrees`: Spawn the 5 Git worktrees for subagents.

---

## 6. HUMAN-IN-THE-LOOP APPROVAL GATES

Stop and request explicit confirmation before:
1. Executing real DB migrations or rewriting Git history.
2. Modifying legacy PHP routing in `app/simulation-controller.php`.
3. Finalizing sensitive pedagogical dialogue (`CRISIS-01`, `CRISIS-03`, `ARC-A`).
4. Merging any subagent worktree into `main`.
