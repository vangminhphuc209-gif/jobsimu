/**
 * game/src/npc/NPCController.ts
 * 4-layer Hybrid AI for JOBSIMU 3D Core NPCs:
 * Schedule + FSM + Utility Rules + Memory Flags.
 */

import { Scene } from '@babylonjs/core/scene';
import { Vector3, Color3 } from '@babylonjs/core/Maths/math';
import { MeshBuilder } from '@babylonjs/core/Meshes/meshBuilder';
import { StandardMaterial } from '@babylonjs/core/Materials/standardMaterial';
import { Mesh } from '@babylonjs/core/Meshes/mesh';
import { GameNPCState, NPCState } from '../core/types';
import { EventBus } from '../core/EventBus';

export interface NPCConfig {
  id: string;
  name: string;
  role: string;
  initialPosition: Vector3;
  color: Color3;
  dialogueGreeting: string;
}

export class NPCController {
  private scene: Scene;
  private mesh: Mesh;
  private state: GameNPCState;
  private eventBus: EventBus;
  private config: NPCConfig;

  constructor(scene: Scene, config: NPCConfig) {
    this.scene = scene;
    this.config = config;
    this.eventBus = EventBus.getInstance();

    this.state = {
      id: config.id,
      name: config.name,
      role: config.role,
      currentLocation: 'StaffRoom',
      currentState: 'Idle',
      relationshipMetrics: {
        trust: 0,
        cooperation: 0,
        confidence: 0
      },
      memoryFlags: {}
    };

    // Create stylized humanoid mesh for NPC
    this.mesh = MeshBuilder.CreateCapsule(`npc_${config.id}`, {
      radius: 0.42,
      height: 1.75,
      subdivisions: 8
    }, this.scene);

    this.mesh.position = config.initialPosition.clone();
    this.mesh.checkCollisions = true;

    const mat = new StandardMaterial(`npcMat_${config.id}`, this.scene);
    mat.diffuseColor = config.color;
    this.mesh.material = mat;

    this.setupListeners();
  }

  public getMesh(): Mesh {
    return this.mesh;
  }

  public getState(): GameNPCState {
    return this.state;
  }

  public setState(newState: NPCState): void {
    this.state.currentState = newState;
    this.eventBus.emit(`npc:${this.state.id}:stateChanged`, this.state);
  }

  public modifyTrust(amount: number): void {
    this.state.relationshipMetrics.trust += amount;
    this.eventBus.emit(`npc:${this.state.id}:trustUpdated`, this.state.relationshipMetrics.trust);
  }

  public setMemoryFlag(key: string, value: any): void {
    this.state.memoryFlags[key] = value;
  }

  public getMemoryFlag(key: string): any {
    return this.state.memoryFlags[key];
  }

  private setupListeners(): void {
    // Listen for school time updates to update schedule routines
    this.eventBus.on('time:updated', (time) => {
      this.updateSchedule(time.hour, time.minute);
    });
  }

  private updateSchedule(hour: number, minute: number): void {
    const totalMinutes = hour * 60 + minute;

    // Mentor Mai schedule:
    if (this.state.id === 'mai') {
      if (totalMinutes < 7 * 60 + 25) {
        this.state.currentLocation = 'StaffRoom';
        this.setState('Seated');
      } else if (totalMinutes < 8 * 60 + 30) {
        this.state.currentLocation = 'Class9A';
        this.setState('Listening');
      }
    }

    // Student Minh schedule:
    if (this.state.id === 'minh') {
      if (totalMinutes < 7 * 60 + 15) {
        this.state.currentLocation = 'Yard';
        this.setState('Idle');
      } else {
        this.state.currentLocation = 'Class9A';
        this.setState('Seated');
      }
    }
  }
}
