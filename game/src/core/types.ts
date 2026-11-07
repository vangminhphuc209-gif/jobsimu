/**
 * game/src/core/types.ts
 * Core data contracts for JOBSIMU 3D Vertical Slice.
 * Single Source of Truth for TypeScript state, events, and telemetry.
 */

export type GraphicsPreset = 'Low' | 'Medium' | 'High';

export type CheckpointId = 
  | 'CP-01' // 06:45 Sân trường & Cổng
  | 'CP-02' // 07:00 Phòng Giáo viên & Chuẩn bị
  | 'CP-03' // 07:30 Di chuyển & Lớp 9A Tiết 1
  | 'CP-04' // 09:00 Tiết 2 & Giờ ra chơi
  | 'CP-05' // 11:30 Nghỉ trưa & Hội ý đồng nghiệp
  | 'CP-06' // 15:00 Phụ huynh Chị Thu & Hỗ trợ học sinh
  | 'CP-07'; // 16:00 Tổng kết & Phản tư cuối ngày

export interface SchoolTime {
  hour: number;
  minute: number;
  timeString: string; // e.g. "07:15"
  periodName: string;
}

export type ActionType = 
  | 'physical'     // Physical action in 3D world (examine projector, take printout)
  | 'dialogue'     // Conversational inquiry or choice
  | 'investigate'  // Gather evidence or ask assistance
  | 'wait';        // Deliberate observation or pause

export interface GameAction {
  id: string;
  type: ActionType;
  label: string;
  description: string;
  timeCostMinutes: number;
  requiredFlags?: Record<string, any>;
  immediateConsequence: {
    dialogueText?: string;
    animationName?: string;
    soundEffect?: string;
    stateMutations: Record<string, any>;
  };
  delayedConsequenceId?: string;
  evidenceTelemetry: {
    competency: 'analytical' | 'creative' | 'empathetic' | 'decisive' | 'accurate';
    dimensionLabel: string;
    observationNote: string;
  };
}

export interface GameEvent {
  id: string;
  context: {
    location: 'Yard' | 'StaffRoom' | 'Corridor' | 'Class9A';
    objective: string;
    timeWindow: { start: string; end: string };
  };
  trigger: {
    checkpointId: CheckpointId;
    preconditions?: Record<string, any>;
    triggerVolumeId?: string;
  };
  sensory_clue: {
    visualClue: string;
    audioClue?: string;
  };
  hidden_info: string;
  actions: GameAction[];
  trade_off: string;
  immediate_feedback: string;
  delayed_consequences: string;
  anti_softlock: {
    fallbackActionId: string;
    recoveryDescription: string;
  };
  evidence_telemetry: {
    competenciesAffected: string[];
    rubricMapping: string;
  };
}

export type NPCState = 'Idle' | 'Walking' | 'Seated' | 'Listening' | 'Reacting' | 'Talking' | 'Busy';

export interface GameNPCState {
  id: string;
  name: string;
  role: string;
  currentLocation: string;
  currentState: NPCState;
  relationshipMetrics: {
    trust: number;       // e.g. minh_trust (-5 to +5)
    cooperation: number; // colleague cooperation
    confidence: number;  // parent confidence
  };
  memoryFlags: Record<string, boolean | string | number>;
}

export interface QuestState {
  currentCheckpoint: CheckpointId;
  activeObjective: string;
  completedObjectives: string[];
  flags: Record<string, any>;
}

export interface EvidenceRecord {
  timestamp: string;
  actionId: string;
  competency: 'analytical' | 'creative' | 'empathetic' | 'decisive' | 'accurate';
  behavioralEvidence: string;
  context: string;
}

export interface CareerCompetencyReport {
  runId: string;
  completedAt: string;
  evidenceCount: number;
  competencies: {
    analytical: { scoreLabel: string; evidence: string[] };
    creative: { scoreLabel: string; evidence: string[] };
    empathetic: { scoreLabel: string; evidence: string[] };
    decisive: { scoreLabel: string; evidence: string[] };
    accurate: { scoreLabel: string; evidence: string[] };
  };
  pedagogicalReflection: {
    classroomManagement: string;
    studentSupport: string;
    nextDayFocus: string;
  };
}

export interface GameSnapshot {
  runId: string;
  revision: number;
  timestamp: string;
  clock: SchoolTime;
  activeCheckpoint: CheckpointId;
  player: {
    position: [number, number, number];
    rotationY: number;
    inventory: string[];
  };
  quests: QuestState;
  npcStates: Record<string, GameNPCState>;
  evidenceLog: EvidenceRecord[];
}
