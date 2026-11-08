/** Deterministic, offline lesson simulation. Saves contain actions, never trusted scores. */
export const LESSON_ACTIONS = [
  'inspect_projector', 'ask_mai', 'print_handouts', 'plan_groups', 'start_class',
  'use_board', 'observe_group_1', 'observe_group_2', 'observe_group_3',
  'observe_group_4', 'answer_nam', 'collect_tickets', 'finish',
] as const;
export type LessonAction = typeof LESSON_ACTIONS[number];
export type Competency = 'analytical' | 'creative' | 'empathetic' | 'decisive' | 'accurate';
export type Strategy = 'handouts' | 'groups' | 'board';
export interface LessonEvidence { action: LessonAction; competency: Competency; note: string }
export interface LessonState {
  phase: 'preparation' | 'teaching' | 'reflection';
  strategy: Strategy | null;
  minutes: number;
  actions: LessonAction[];
  flags: { projectorChecked: boolean; mentorAsked: boolean; handouts: boolean;
    groupPlan: boolean; projectorFailed: boolean; namAnswered: boolean; ticketsCollected: boolean };
  observedGroups: number[];
  evidence: LessonEvidence[];
  outcome: string;
}
export interface LessonReport {
  dataSufficiency: string;
  competencies: Record<Competency, { dimension: string; level: string; evidence: string[] }>;
  pedagogicalReflection: { summaryComment: string };
}
export interface LessonActionResult { ok: boolean; message: string }

const LABELS: Record<Competency, string> = {
  analytical: 'Phân tích', creative: 'Ứng biến và sáng tạo', empathetic: 'Lắng nghe',
  decisive: 'Ra quyết định', accurate: 'Kiểm tra bằng chứng',
};
const COSTS: Record<LessonAction, number> = {
  inspect_projector: 2, ask_mai: 3, print_handouts: 6, plan_groups: 5,
  start_class: 2, use_board: 4, observe_group_1: 3, observe_group_2: 3,
  observe_group_3: 3, observe_group_4: 4, answer_nam: 5, collect_tickets: 4, finish: 1,
};

export class LessonSession {
  private data: LessonState = {
    phase: 'preparation', strategy: null, minutes: 420, actions: [], observedGroups: [], evidence: [],
    flags: { projectorChecked: false, mentorAsked: false, handouts: false, groupPlan: false,
      projectorFailed: false, namAnswered: false, ticketsCollected: false },
    outcome: 'Chuẩn bị tiết học về thời tiết và khí hậu tại lớp 9A.',
  };
  public position: [number, number, number] | null = null;

  /** Copies prevent callers from bypassing action guards by mutating state. */
  get state(): LessonState { return structuredClone(this.data); }

  act(action: LessonAction): LessonActionResult {
    const s = this.data;
    if (!LESSON_ACTIONS.includes(action)) return { ok: false, message: 'Hành động không hợp lệ.' };
    if (s.actions.includes(action)) return { ok: false, message: 'Bạn đã thực hiện việc này; nhật ký đã ghi lại kết quả.' };
    if (s.phase === 'reflection') return { ok: false, message: 'Tiết học đã kết thúc. Bắt đầu lượt mới để thử cách khác.' };
    const preparation = ['print_handouts', 'plan_groups'].includes(action);
    if (preparation && s.phase !== 'preparation') {
      return { ok: false, message: 'Tiết học đã bắt đầu. Bạn vẫn có thể ứng biến bằng bảng tại lớp.' };
    }
    const classroom = action.startsWith('observe_group_') || ['answer_nam', 'collect_tickets', 'finish'].includes(action);
    if (classroom && s.phase !== 'teaching') {
      return { ok: false, message: 'Đến bảng lớp và bắt đầu tiết học trước.' };
    }
    if ((action === 'print_handouts' && s.flags.groupPlan) || (action === 'plan_groups' && s.flags.handouts)) {
      return { ok: false, message: 'Học liệu đã chuẩn bị theo một phương án. Hãy thử phương án còn lại ở lượt mới.' };
    }
    if (action === 'use_board' && s.phase !== 'teaching') {
      return { ok: false, message: 'Bảng sẽ dùng để minh họa khi tiết học bắt đầu; hiện bạn có thể chuẩn bị phiếu hoặc kế hoạch nhóm.' };
    }

    let message = '';
    const evidence = (competency: Competency, note: string) => s.evidence.push({ action, competency, note });
    switch (action) {
      case 'inspect_projector':
        s.flags.projectorChecked = true;
        message = 'Đèn cảnh báo máy chiếu sáng: thiết bị chưa sẵn sàng. Không tháo thiết bị; chuẩn bị phiếu, thảo luận nhóm hoặc dùng bảng.';
        evidence('analytical', 'Đã kiểm tra dấu hiệu lỗi máy chiếu trước khi quyết định cách dạy.');
        break;
      case 'ask_mai':
        s.flags.mentorAsked = true;
        message = 'Cô Mai: “Nhóm 4 còn nhầm đơn vị trên đồ thị. Em có thể xem phiếu của nhóm rồi giải thích bằng ví dụ. Máy in ở bên cạnh.”';
        evidence('analytical', 'Đã hỏi đồng nghiệp về mức hiểu bài trước của lớp.');
        break;
      case 'print_handouts':
        s.flags.handouts = true;
        message = 'Đã lấy bộ phiếu có biểu đồ nhiệt độ nhiều năm. Mất 6 phút chuẩn bị; phiếu sẽ được phát trên bàn khi vào tiết.';
        evidence('creative', 'Chuẩn bị dữ liệu giấy để tiếp tục bài học khi thiết bị không hoạt động.');
        break;
      case 'plan_groups':
        s.flags.groupPlan = true;
        message = 'Đã chuẩn bị nhiệm vụ nhóm: so sánh một ngày lạnh với xu hướng nhiều năm. Mất 5 phút; trong lớp cần quan sát cả nhóm nhanh và nhóm cần hỗ trợ.';
        evidence('creative', 'Thiết kế hoạt động thảo luận không phụ thuộc máy chiếu.');
        break;
      case 'start_class':
        s.phase = 'teaching';
        s.minutes = Math.max(s.minutes, 450);
        s.flags.projectorFailed = true;
        s.strategy = s.flags.handouts ? 'handouts' : s.flags.groupPlan ? 'groups' : 'board';
        message = s.strategy === 'handouts'
          ? 'Máy chiếu không lên. Bạn phát bộ phiếu đã chuẩn bị; các nhóm bắt đầu đọc biểu đồ. Hãy đến gần từng nhóm để xem bài.'
          : s.strategy === 'groups'
            ? 'Máy chiếu không lên nhưng hoạt động nhóm vẫn bắt đầu. Nhóm 1 đã có dự đoán; nhóm 4 đang phân vân về đơn vị.'
            : 'Máy chiếu không lên, chưa có học liệu dự phòng. Bảng phấn luôn sẵn sàng: đến bảng để minh họa rồi quan sát các nhóm.';
        evidence('decisive', s.strategy === 'board'
          ? 'Bắt đầu tiết khi chưa có học liệu dự phòng; mở phương án bảng phấn để tiếp tục.'
          : `Triển khai phương án ${s.strategy === 'handouts' ? 'phiếu giấy' : 'thảo luận nhóm'} khi máy chiếu không hoạt động.`);
        break;
      case 'use_board':
        message = 'Bạn vẽ hai trục và ghi rõ °C, năm. Ví dụ trên bảng giúp nhóm 4 phân biệt nhiệt độ từng ngày với xu hướng dài hạn.';
        evidence('creative', 'Dùng bảng minh họa trục, đơn vị và dữ liệu dài hạn để khắc phục thiếu thiết bị.');
        break;
      case 'answer_nam':
        s.flags.namAnswered = true;
        message = 'Nam hỏi: “Hôm nay lạnh thì sao nói Trái Đất nóng lên?” Bạn cùng lớp đối chiếu một ngày với xu hướng nhiều năm. Nam đề xuất kiểm tra nhiều mốc thời gian hơn.';
        evidence('empathetic', 'Dành thời gian lắng nghe câu hỏi của Nam và cùng lớp đối chiếu dữ liệu.');
        break;
      case 'collect_tickets':
        s.flags.ticketsCollected = true;
        message = s.flags.namAnswered
          ? 'Phiếu cuối giờ: học sinh đã nêu được khác biệt giữa thời tiết và khí hậu. Cần kiểm tra tiếp cách đọc trục đồ thị của các nhóm chưa được quan sát.'
          : 'Phiếu cuối giờ cho thấy vẫn có câu trả lời dùng một ngày lạnh để kết luận về khí hậu. Hãy quay lại trao đổi với Nam trước khi tổng kết.';
        evidence('accurate', 'Đã thu phiếu cuối giờ để tìm hiểu nhầm thay vì suy đoán cả lớp hiểu bài.');
        break;
      case 'finish':
        s.phase = 'reflection';
        message = 'Đã kết thúc tiết học thử nghiệm. Báo cáo chỉ dùng các hành động đã ghi nhận trong lượt chơi này.';
        break;
      default: {
        const group = Number(action.slice(-1));
        s.observedGroups.push(group);
        message = group === 4
          ? 'Nhóm 4 chỉ vào trục tung: chưa rõ đơn vị. Bạn ghi nhận câu hỏi; có thể đến bảng minh họa để hỗ trợ.'
          : group === 1
            ? 'Nhóm 1 đã đọc xong dữ liệu. Bạn giao câu hỏi mở rộng: cần bao nhiêu năm quan sát để kiểm tra dự đoán?'
            : `Nhóm ${group} đang so sánh các mốc năm. Bạn xem lập luận của nhóm và gợi ý ghi rõ bằng chứng.`;
        evidence('analytical', `Đã trực tiếp xem bài và trao đổi với nhóm ${group}${group === 4 ? ', phát hiện nhầm lẫn về đơn vị' : ''}.`);
      }
    }
    s.minutes += COSTS[action];
    s.actions.push(action);
    s.outcome = message;
    return { ok: true, message };
  }

  objective(): string {
    const s = this.data;
    if (s.phase === 'reflection') return 'Xem báo cáo hoặc bắt đầu lượt mới để thử chiến lược khác.';
    if (s.phase === 'preparation') return 'Hỏi Cô Mai, chuẩn bị phiếu / kế hoạch nhóm, rồi đến bảng lớp 9A.';
    if (s.strategy === 'board' && !s.actions.includes('use_board')) return 'Máy chiếu hỏng: đến bảng phấn để minh họa bài học.';
    if (s.observedGroups.length < 4) return `Quan sát các nhóm trong lớp (${s.observedGroups.length}/4); Nam đang có câu hỏi.`;
    if (!s.flags.namAnswered) return 'Đến gần Nam và cùng lớp tìm hiểu câu hỏi về thời tiết, khí hậu.';
    if (!s.flags.ticketsCollected) return 'Đến bàn giáo viên để thu phiếu kiểm tra hiểu bài cuối giờ.';
    return 'Có thể hỗ trợ thêm tại bảng hoặc về sổ tổng kết ở phòng giáo viên.';
  }

  report(): LessonReport {
    const s = this.data;
    const competencies = {} as LessonReport['competencies'];
    for (const key of Object.keys(LABELS) as Competency[]) {
      const evidence = s.evidence.filter(e => e.competency === key).map(e => e.note);
      competencies[key] = { dimension: LABELS[key], level: evidence.length ? 'Có bằng chứng quan sát' : 'Chưa đủ dữ liệu', evidence };
    }
    const remaining: string[] = [];
    if (!s.flags.ticketsCollected) remaining.push('Chưa thu phiếu cuối giờ, chưa đủ dữ liệu bằng chứng về mức hiểu bài.');
    if (!s.flags.namAnswered) remaining.push('Câu hỏi phân biệt thời tiết và khí hậu của Nam chưa được trao đổi.');
    if (s.observedGroups.length < 4) remaining.push(`Chưa quan sát ${4 - s.observedGroups.length} nhóm.`);
    if (s.observedGroups.includes(4) && !s.actions.includes('use_board')) remaining.push('Đã phát hiện nhóm 4 cần hỗ trợ về đơn vị; cần minh họa lại.');
    return {
      dataSufficiency: s.flags.ticketsCollected ? 'Có phiếu cuối giờ; bằng chứng giới hạn trong tiết thử nghiệm' : 'Chưa đủ dữ liệu bằng chứng',
      competencies,
      pedagogicalReflection: { summaryComment: [
        s.phase === 'reflection' ? 'Đã hoàn thành một tiết thử nghiệm, chưa phải toàn bộ Ngày 1.' : 'Đây là báo cáo tạm thời của tiết đang chơi.',
        `Phương án: ${s.strategy === 'handouts' ? 'phiếu giấy' : s.strategy === 'groups' ? 'thảo luận nhóm' : s.strategy === 'board' ? 'bảng phấn dự phòng' : 'chưa bắt đầu tiết'}.`,
        ...remaining,
        remaining.length ? 'Dùng các việc còn dang dở này để chuẩn bị tiết tiếp theo.' : 'Tiết sau nên dùng dữ liệu mới để kiểm tra mức hiểu bài được duy trì.',
      ].join(' ') },
    };
  }

  serialize(position?: [number, number, number]): string {
    return JSON.stringify({ version: 1, actions: this.data.actions, position: position ?? this.position });
  }

  static restore(raw: string): LessonSession | null {
    if (raw.length > 16000) return null;
    try {
      const save: unknown = JSON.parse(raw);
      if (!save || typeof save !== 'object') return null;
      const value = save as Record<string, unknown>;
      if (value.version !== 1 || !Array.isArray(value.actions) || value.actions.length > LESSON_ACTIONS.length) return null;
      const result = new LessonSession();
      for (const action of value.actions) {
        if (typeof action !== 'string' || !LESSON_ACTIONS.includes(action as LessonAction) || !result.act(action as LessonAction).ok) return null;
      }
      const p = value.position;
      if (p !== undefined && p !== null) {
        if (!Array.isArray(p) || p.length !== 3 || p.some(n => typeof n !== 'number' || !Number.isFinite(n) || Math.abs(n) > 1000)) return null;
        if (p[0] >= -38 && p[0] <= 28 && p[1] >= .5 && p[1] <= 3 && p[2] >= -42 && p[2] <= 28) result.position = p as [number, number, number];
      }
      return result;
    } catch { return null; }
  }
}
