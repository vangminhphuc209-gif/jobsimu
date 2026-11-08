/**
 * game/src/npc/NPCController.ts
 * 4-layer Hybrid AI for JOBSIMU 3D Core NPCs:
 * Schedule + FSM + Utility Rules + Memory Flags.
 */

import { Scene } from '@babylonjs/core/scene';
import { Vector3, Color3 } from '@babylonjs/core/Maths/math';
import { MeshBuilder } from '@babylonjs/core/Meshes/meshBuilder';
import { StandardMaterial } from '@babylonjs/core/Materials/standardMaterial';
import { DynamicTexture } from '@babylonjs/core/Materials/Textures/dynamicTexture';
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
  showLabel?: boolean;
  seated?: boolean;
}

export class NPCController {
  private scene: Scene;
  private mesh: Mesh;
  private state: GameNPCState;
  private eventBus: EventBus;
  private config: NPCConfig;
  private limbs: Mesh[] = [];
  private animationTime = 0;
  private enabled = true;
  private stopListening: (() => void) | undefined;

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

    // The invisible capsule is the interaction/collision root; visual parts are authored locally.
    this.mesh = MeshBuilder.CreateCapsule(`npc_${config.id}`, { radius: 0.30, height: 1.75, subdivisions: 8 }, scene);
    this.mesh.position = config.initialPosition.clone();
    this.mesh.checkCollisions = true;
    this.mesh.isVisible = false;
    const material = (name: string, color: Color3) => {
      const mat = new StandardMaterial(`${config.id}_${name}`, scene);
      mat.diffuseColor = color; mat.specularColor = Color3.Black(); return mat;
    };
    const shirt = material('shirt', config.color);
    const skin = material('skin', new Color3(0.79, 0.56, 0.40));
    const hair = material('hair', new Color3(0.11, 0.08, 0.07));
    const trousers = material('trousers', new Color3(0.17, 0.23, 0.29));
    const part = (name: string, width: number, height: number, depth: number, x: number, y: number, z: number, mat: StandardMaterial) => {
      const mesh = MeshBuilder.CreateBox(`${config.id}_${name}`, {width, height, depth}, scene);
      mesh.parent = this.mesh; mesh.position.set(x, y, z); mesh.material = mat; mesh.isPickable = false; return mesh;
    };
    part('torso', .47, .60, .30, 0, .04, 0, shirt);
    const head = MeshBuilder.CreateSphere(`${config.id}_head`, {diameter: .38, segments: 12}, scene);
    head.parent = this.mesh; head.position.y = .57; head.material = skin; head.isPickable = false;
    part('hair', .39, .12, .33, 0, .72, -.02, hair);
    part('eye_left', .035, .035, .015, -.075, .60, .18, hair);
    part('eye_right', .035, .035, .015, .075, .60, .18, hair);
    part('collar', .12, .11, .02, 0, .29, .16, material('collar', new Color3(.95, .95, .89)));
    this.limbs.push(part('arm_left', .14, .53, .17, -.33, -.02, 0, shirt));
    this.limbs.push(part('arm_right', .14, .53, .17, .33, -.02, 0, shirt));
    part('hand_left', .13, .16, .15, -.33, -.35, 0, skin);
    part('hand_right', .13, .16, .15, .33, -.35, 0, skin);
    if (config.seated) {
      for (const side of [-1, 1]) {
        part(`thigh_${side}`, .17, .17, .40, side * .13, -.32, .12, trousers);
        part(`shin_${side}`, .17, .35, .18, side * .13, -.53, .28, trousers);
        part(`shoe_${side}`, .19, .12, .28, side * .13, -.74, .34, hair);
      }
      this.state.currentState = 'Seated';
    } else {
      part('leg_left', .17, .55, .20, -.13, -.53, 0, trousers);
      part('leg_right', .17, .55, .20, .13, -.53, 0, trousers);
      part('shoe_left', .19, .12, .28, -.13, -.84, .04, hair);
      part('shoe_right', .19, .12, .28, .13, -.84, .04, hair);
    }
    if (config.showLabel !== false) {
    const label = MeshBuilder.CreatePlane(`${config.id}_label`, {width: 1.8, height: .38}, scene);
    label.parent = this.mesh; label.position.y = 1.04; label.billboardMode = Mesh.BILLBOARDMODE_ALL; label.isPickable = false;
    const texture = new DynamicTexture(`${config.id}_name`, {width: 512, height: 112}, scene, false);
    texture.hasAlpha = true;
    texture.drawText(config.name, null, 76, 'bold 50px sans-serif', '#ffffff', '#183a32cc', true);
    const labelMat = material('label', Color3.White());
    labelMat.diffuseTexture = texture; labelMat.emissiveColor = Color3.White(); labelMat.disableLighting = true; labelMat.backFaceCulling = false;
    label.material = labelMat;
    }
    scene.onBeforeRenderObservable.add(() => {
      if (!this.enabled) return;
      this.animationTime += Math.min(scene.getEngine().getDeltaTime() / 1000, .05);
      const gesture = this.state.currentState === 'Talking' || this.state.currentState === 'Reacting';
      this.limbs[0].rotation.z = -.08 + Math.sin(this.animationTime * 1.8) * (gesture ? .22 : .025);
      this.limbs[1].rotation.z = .08 - Math.sin(this.animationTime * 1.8) * (gesture ? .22 : .025);
      head.rotation.y = Math.sin(this.animationTime * .6) * .08;
    });
    this.setupListeners();
  }

  public setEnabled(enabled: boolean): void { this.enabled = enabled; }

  public facePosition(position: Vector3): void {
    this.mesh.rotation.y = Math.atan2(position.x - this.mesh.position.x, position.z - this.mesh.position.z);
  }

  public dispose(): void { this.stopListening?.(); this.mesh.dispose(false, true); }

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
    this.stopListening = this.eventBus.on('time:updated', (time) => {
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
