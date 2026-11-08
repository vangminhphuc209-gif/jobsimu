import { describe, it, expect } from 'vitest';
import { LessonSession, type LessonAction } from '../src/systems/LessonSession';
describe('playable lesson', () => {
  it('produces different strategies and reports for three preparations', () => {
    const runs = [null, 'print_handouts', 'plan_groups'].map(prep => {
      const run = new LessonSession();
      if (prep) run.act(prep as LessonAction);
      expect(run.act('start_class').ok).toBe(true);
      run.act('finish'); return run;
    });
    expect(new Set(runs.map(r => r.state.strategy)).size).toBe(3);
    expect(new Set(runs.map(r => JSON.stringify(r.report()))).size).toBe(3);
  });
  it('guards order and duplicate actions without awarding evidence', () => {
    const run = new LessonSession();
    expect(run.act('collect_tickets').ok).toBe(false);
    expect(run.act('finish').ok).toBe(false);
    run.act('print_handouts');
    const before = run.state;
    expect(run.act('print_handouts').ok).toBe(false);
    expect(run.state).toEqual(before);
  });
  it('records missing evidence honestly', () => {
    const run = new LessonSession(); run.act('start_class'); run.act('finish');
    expect(run.report().dataSufficiency).toContain('Chưa đủ');
    expect(run.report().competencies.empathetic.evidence).toEqual([]);
    expect(run.report().competencies.accurate.evidence).toEqual([]);
  });
  it('restores all state by replay and rejects invalid saves', () => {
    const run = new LessonSession();
    for (const action of ['plan_groups','start_class','observe_group_4','use_board','answer_nam','collect_tickets'] as LessonAction[]) run.act(action);
    expect(LessonSession.restore(run.serialize([-12,1,3]))?.state).toEqual(run.state);
    expect(LessonSession.restore('{bad')).toBeNull();
    expect(LessonSession.restore('{"version":1,"actions":["finish"]}')).toBeNull();
    expect(LessonSession.restore('{"version":1,"actions":["print_handouts","print_handouts"]}')).toBeNull();
    expect(LessonSession.restore(run.serialize([900,1,900]))?.position).toBeNull();
  });
  it('can complete without any preparation and cannot alter a finished lesson', () => {
    const run = new LessonSession();
    for(const action of ['start_class','use_board','observe_group_1','observe_group_2','observe_group_3','observe_group_4','answer_nam','collect_tickets','finish'] as LessonAction[]) expect(run.act(action).ok).toBe(true);
    expect(run.state.phase).toBe('reflection');
    expect(run.report().competencies.accurate.evidence).toHaveLength(1);
    expect(run.act('ask_mai').ok).toBe(false);
  });
});
