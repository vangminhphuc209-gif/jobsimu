---
name: npc-hybrid-ai
description: Implements 4-layer hybrid AI (Schedule, FSM, Utility Rules, Memory Flags) for the 10 core school NPCs.
---

# skill:npc-hybrid-ai

## Purpose
Operates dynamic, lifelike NPC behaviors throughout the school day without relying on unconstrained generative LLMs during runtime.

## 4-Layer Architecture
1. **School Schedule (`SchoolSchedule`):** Minute-by-minute routine (06:45 to 16:45) determining target locations and primary activities.
2. **Finite State Machine (`NPC_FSM`):**
   - States: `Idle`, `Walking`, `Seated`, `Listening`, `Reacting`, `Talking`, `Busy`, `Leaving`.
   - Transitions triggered by player proximity, classroom phase changes, or bell rings.
3. **Utility Rules (`UtilityEvaluator`):** Selects conversational responses and spontaneous reactions based on relationship metrics (`minh_trust`, `colleague_support`, `parent_confidence`).
4. **Memory Flags (`MemoryFlags`):** Tracks player actions and promises (e.g. `helped_mr_quan_printer`, `spoke_with_minh_quietly`, `promised_review_rubric`).

## Core NPCs (8–10 Key Figures)
- **Cô Mai:** Veteran Literature Teacher & Mentor.
- **Cô Hạnh:** Vice Principal / Head of 9th Grade.
- **Thầy Quân:** IT & Equipment Coordinator.
- **Minh:** Core 9A Student (Arc A - Trust & engagement).
- **Linh:** Class 9A Monitor (Organized, high expectations).
- **Nam:** Critical Thinker (Asks challenging real-world questions).
- **Hà:** Introverted Student (Skilled artist, quiet).
- **Tuấn:** Hyperactive Student (Playful, easily distracted).
- **Chị Thu:** Minh's mother (Parent meeting, Arc C).
- **Cô Lan:** School Nurse & Psychological Counselor (Crucial for `CRISIS-03`).
