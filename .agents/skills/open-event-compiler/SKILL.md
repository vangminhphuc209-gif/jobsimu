---
name: open-event-compiler
description: Validates and compiles open narrative events using the 10-part formula, requiring >=3 diverse action types and delayed consequences.
---

# skill:open-event-compiler

## Purpose
Validates that every single game event complies strictly with the **10-Part Open Event Formula** from `docs/game3d.md` (Section 3.2). Rejects linear A/B/C visual-novel multiple-choice patterns.

## The 10-Part Formula
1. **Context & Objective (`context`):** Specific time window, location, pedagogical challenge.
2. **Trigger Condition (`trigger`):** Preconditions based on time (`SchoolClock`), location trigger volumes, or prior memory flags.
3. **Sensory Clue (`sensory_clue`):** Visual (e.g. flickering projector lamp, student looking down) or audio cues in the 3D space.
4. **Hidden / Incomplete Information (`hidden_info`):** Facts the player doesn't know until they observe or investigate.
5. **$\ge 3$ Independent Action Types (`actions`):**
   - Action Type A: Physical action in 3D world (e.g., test device, print handouts, walk closer).
   - Action Type B: Direct dialogue / inquiry (e.g., talk privately, ask open question).
   - Action Type C: Investigation / Seeking assistance (e.g., review notebook, call IT teacher Mr. Quan, consult mentor Ms. Mai).
6. **Explicit Trade-off (`trade_off`):** Costs time (`minutes_spent`), energy, or interpersonal capital.
7. **Immediate Feedback (`immediate_feedback`):** Immediate 3D animation, student body language change, or dialogue response.
8. **Delayed Consequence (`delayed_consequences`):** Effects manifested at later checkpoints or during end-of-day review.
9. **Fail-forward / Anti-softlock (`anti_softlock`):** If player misses or fails the action, a backup path opens without halting progress.
10. **Evidence Telemetry (`evidence_telemetry`):** Logged behavioral data points used for the 5 competency dimensions in the final career report.

## Schema Validation
All events must conform to the TypeScript interface `GameEvent` defined in `game/src/core/types.ts`.
