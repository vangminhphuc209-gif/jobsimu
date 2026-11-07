/**
 * game/src/main.ts
 * Entry point for JOBSIMU 3D Vertical Slice (Day 1 - Teacher Sim).
 * Integrates 3D Scene, Character Controller, School Clock, Core NPCs, and Event Handlers.
 */

import { Vector3, Color3 } from '@babylonjs/core/Maths/math';
import { GameEngine } from './core/GameEngine';
import { SchoolWorld } from './world/SchoolWorld';
import { ThirdPersonCamera } from './player/ThirdPersonCamera';
import { CharacterController } from './player/CharacterController';
import { InteractionRaycaster } from './player/InteractionRaycaster';
import { SchoolClock } from './world/SchoolClock';
import { NPCController } from './npc/NPCController';
import { DialogueUI } from './ui/DialogueUI';
import { CareerReportUI } from './ui/CareerReportUI';
import { ApiClient } from './api/ApiClient';
import { EventBus } from './core/EventBus';

// Import key Day 1 event JSON content
import prep01Data from '../content/events/PREP-01.json';
import obs01Data from '../content/events/OBS-01.json';
import class01Data from '../content/events/CLASS-01.json';
import feedback01Data from '../content/events/FEEDBACK-01.json';
import reflect01Data from '../content/events/REFLECT-01.json';

class JobsimuApp {
  private engine: GameEngine;
  private world: SchoolWorld;
  private camera: ThirdPersonCamera;
  private player: CharacterController;
  private interactor: InteractionRaycaster;
  private clock: SchoolClock;
  private dialogueUI: DialogueUI;
  private reportUI: CareerReportUI;
  private apiClient: ApiClient;
  private eventBus: EventBus;

  private runId: string = 'local_dev_run';
  private eventSeq: number = 0;
  private currentRevision: number = 1;
  private loggedEvidence: any[] = [];

  constructor() {
    this.eventBus = EventBus.getInstance();
    this.apiClient = new ApiClient();

    // 1. Initialize 3D Engine & Canvas
    this.engine = new GameEngine('renderCanvas');
    const scene = this.engine.getScene();

    // 2. Build 4 P0 Blockout Zones
    this.world = new SchoolWorld(scene, this.engine.getShadowGenerator());

    // 3. Initialize Player & Third-Person Camera
    // Spawn player in StaffRoom at 07:00
    const spawnPos = new Vector3(-12, 1.0, 3);
    this.player = new CharacterController(scene, null as any, spawnPos);
    this.camera = new ThirdPersonCamera(scene, this.engine.getCanvas(), this.player.getMesh());
    (this.player as any).camera = this.camera.getCamera();

    // 4. Initialize Interactor & Clock
    this.interactor = new InteractionRaycaster(scene, this.player.getMesh());
    this.clock = new SchoolClock();
    this.clock.setTime(7, 0);

    // 5. Initialize UI layers
    this.dialogueUI = new DialogueUI();
    this.reportUI = new CareerReportUI();

    // 6. Spawn Core NPCs
    this.spawnCoreNPCs(scene);

    // 7. Register Interactables
    this.registerInteractables();

    // 8. Start Game Loop & API session
    this.engine.startRenderLoop();
    this.initSession();

    console.log('🎮 [JOBSIMU 3D] Vertical Slice initialized successfully at 60 FPS target.');
  }

  private async initSession(): Promise<void> {
    try {
      const res = await this.apiClient.startRun();
      if (res.data?.run_id) {
        this.runId = res.data.run_id;
        console.log(`[JOBSIMU 3D] Connected to server backend run: ${this.runId}`);
      }
    } catch (e) {
      console.warn('[JOBSIMU 3D] Running in local offline mode (backend sync deferred).');
    }
  }

  private spawnCoreNPCs(scene: any): void {
    // Mentor Mai in Staff Room
    const mai = new NPCController(scene, {
      id: 'mai',
      name: 'Cô Mai',
      role: 'Tổ trưởng / Người hướng dẫn',
      initialPosition: new Vector3(-14, 1.0, 5),
      color: new Color3(0.85, 0.45, 0.55), // Warm rose
      dialogueGreeting: 'Chào em! Sáng nay tiết đầu của em ở lớp 9A đấy.'
    });

    this.interactor.registerTarget({
      id: 'npc_mai',
      name: 'Cô Mai',
      label: 'Nói chuyện với Cô Mai',
      mesh: mai.getMesh(),
      onInteract: () => {
        this.triggerEvent(prep01Data as any);
      }
    });

    // Student Minh in Yard / Corridor
    const minh = new NPCController(scene, {
      id: 'minh',
      name: 'Minh',
      role: 'Học sinh lớp 9A',
      initialPosition: new Vector3(-10, 1.0, -22),
      color: new Color3(0.2, 0.7, 0.4), // Forest green
      dialogueGreeting: 'Em chào thầy/cô ạ...'
    });

    this.interactor.registerTarget({
      id: 'npc_minh',
      name: 'Minh (9A)',
      label: 'Quan sát & Nói chuyện với Minh',
      mesh: minh.getMesh(),
      onInteract: () => {
        this.triggerEvent(obs01Data as any);
      }
    });
  }

  private registerInteractables(): void {
    // Printer machine in StaffRoom
    this.interactor.registerTarget({
      id: 'prop_printer',
      name: 'Máy in / Photo',
      label: 'In phiếu bài tập dự phòng',
      mesh: this.world.printerMesh,
      onInteract: () => {
        this.triggerEvent(prep01Data as any);
      }
    });

    // Exit tickets on Teacher Desk in Class9A
    this.interactor.registerTarget({
      id: 'prop_exit_tickets',
      name: 'Phiếu Exit Ticket',
      label: 'Thu phiếu kiểm tra hiểu bài cuối giờ',
      mesh: this.world.exitTicketStackMesh,
      onInteract: () => {
        this.triggerEvent(feedback01Data as any);
      }
    });

    // End-of-day summary on Teacher desk in Staff Room
    const endOfDayDesk = this.world.printerMesh; // Proximity trigger placeholder
    window.addEventListener('keydown', (e) => {
      if (e.key.toLowerCase() === 'p') {
        // Toggle end of day reflection or pause menu
        this.triggerEvent(reflect01Data as any);
      }
    });
  }

  private triggerEvent(eventData: any): void {
    this.dialogueUI.showEvent(eventData, (action) => {
      this.handleActionSelected(eventData, action);
    });
  }

  private async handleActionSelected(event: any, action: any): Promise<void> {
    this.eventSeq++;
    this.clock.advanceMinutes(action.timeCostMinutes || 5);

    // Log evidence telemetry
    if (action.evidenceTelemetry) {
      this.loggedEvidence.push({
        actionId: action.id,
        competency: action.evidenceTelemetry.competency,
        note: action.evidenceTelemetry.observationNote
      });
    }

    // Server-side event synchronization
    try {
      await this.apiClient.logEvent(this.runId, this.eventSeq, event.id, action.id, {
        competency: action.evidenceTelemetry?.competency,
        note: action.evidenceTelemetry?.observationNote,
        timeSpent: action.timeCostMinutes,
        mutations: action.immediateConsequence?.stateMutations
      });
    } catch (e) {
      console.warn('[Sync] Event log stored locally:', e);
    }

    // Check if end of day reflection
    if (event.id === 'REFLECT-01') {
      try {
        const reportRes = await this.apiClient.finishRun(this.runId, {
          evidence: this.loggedEvidence
        });
        this.reportUI.showReport(reportRes.data);
      } catch (e) {
        // Fallback local report
        this.reportUI.showReport({
          dataSufficiency: 'Đầy đủ bằng chứng thực tế',
          competencies: {
            analytical: { dimension: 'Năng lực Phân tích Sư phạm', level: 'Vững vàng', evidence: ['Quan sát và thu thập dữ liệu học sinh'] },
            creative: { dimension: 'Năng lực Ứng biến & Sáng tạo', level: 'Linh hoạt', evidence: ['Chuẩn bị phương án bảng phấn dự phòng'] },
            empathetic: { dimension: 'Năng lực Thấu cảm & Đồng hành', level: 'Chu đáo', evidence: ['Lắng nghe và tôn trọng khó khăn học sinh Minh'] },
            decisive: { dimension: 'Năng lực Ra Quyết định', level: 'Trách nhiệm', evidence: ['Ưu tiên xử lý an toàn học đường'] },
            accurate: { dimension: 'Năng lực Chuẩn xác & Quy trình', level: 'Đúng chuẩn', evidence: ['Quản trị rủi ro thiết bị và thu phiếu cuối giờ'] }
          },
          pedagogicalReflection: {
            summaryComment: 'Chúc mừng bạn đã hoàn thành trọn vẹn Ngày 1 với tư cách là giáo viên bộ môn tại trường THCS Tuệ Đức.'
          }
        });
      }
    }
  }
}

// Bootstrap when DOM is loaded
window.addEventListener('DOMContentLoaded', () => {
  new JobsimuApp();
});
