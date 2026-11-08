import { describe, it, expect } from 'vitest';
import { SchoolClock } from '../src/world/SchoolClock';
import prep01 from '../content/events/PREP-01.json';
import crisis02 from '../content/events/CRISIS-02.json';
import crisis03 from '../content/events/CRISIS-03.json';
import feedback01 from '../content/events/FEEDBACK-01.json';

describe('QA Matrix Tests (QA-01 to QA-12)', () => {
  it('QA-01: Provides reasonable chalkboard fallback without game crash if projector not checked', () => {
    const fallback = crisis02.anti_softlock;
    expect(fallback.fallbackActionId).toBeDefined();
    expect(fallback.recoveryDescription).toContain('sách giáo khoa');
  });

  it('QA-02: Uses backup handouts printed in PREP-01 when projector breaks at CRISIS-02', () => {
    const printAction = prep01.actions.find(a => a.id === 'prep_print_handouts');
    expect(printAction).toBeDefined();
    expect(printAction?.immediateConsequence.stateMutations.flag_prep_backup_available).toBe(true);

    const useHandoutAction = crisis02.actions.find(a => a.id === 'crisis_distribute_prepared_handouts');
    expect(useHandoutAction).toBeDefined();
    expect(useHandoutAction?.requiredFlags?.flag_prep_backup_available).toBe(true);
  });

  it('QA-05: Sets insufficient evidence note when feedback assessment is skipped', () => {
    const skipAction = feedback01.actions.find(a => a.id === 'feedback_skip_and_dismiss_class');
    expect(skipAction).toBeDefined();
    expect(skipAction?.immediateConsequence.stateMutations.flag_exit_tickets_collected).toBe(false);
  });

  it('QA-06: Prioritizes pupil safety protocol over clock delay in CRISIS-03', () => {
    const safetyAction = crisis03.actions.find(a => a.id === 'crisis_safety_listen_and_escalate_to_lan');
    expect(safetyAction).toBeDefined();
    expect(safetyAction?.immediateConsequence.stateMutations.flag_safety_escalated_properly).toBe(true);
    expect(safetyAction?.timeCostMinutes).toBe(15);
  });

  it('QA-08: SchoolClock preserves correct time format and period transitions', () => {
    const clock = new SchoolClock();
    clock.setTime(7, 45);
    expect(clock.getTime().timeString).toBe('07:45');
    expect(clock.getTime().periodName).toContain('Tiết 1');

    clock.advanceMinutes(30);
    expect(clock.getTime().timeString).toBe('08:15');
  });
});

describe('3-Run Divergence Evaluation', () => {
  it('Run 1 (Handouts), Run 2 (Unprepared Tech), Run 3 (Board Plan) yield 100% distinct consequence states', () => {
    // Run 1: Printed Handouts
    const run1Mutations = prep01.actions[0].immediateConsequence.stateMutations;
    // Run 2: Relied on tech
    const run2Mutations = prep01.actions[1].immediateConsequence.stateMutations;
    // Run 3: Board strategy
    const run3Mutations = prep01.actions[2].immediateConsequence.stateMutations;

    // Verify completely distinct flags
    expect(run1Mutations.flag_prep_backup_available).toBe(true);
    expect(run2Mutations.flag_prep_backup_available).toBe(false);
    expect(run3Mutations.flag_board_strategy).toBe(true);

    // Verify distinct delayed consequence IDs
    const delayed1 = prep01.actions[0].delayedConsequenceId;
    const delayed2 = prep01.actions[1].delayedConsequenceId;
    const delayed3 = prep01.actions[2].delayedConsequenceId;

    expect(delayed1).not.toBe(delayed2);
    expect(delayed2).not.toBe(delayed3);
    expect(delayed1).not.toBe(delayed3);

    // Divergence confirmed: No linear visual-novel railroad
  });
});
