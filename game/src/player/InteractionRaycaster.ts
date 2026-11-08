/**
 * game/src/player/InteractionRaycaster.ts
 * Proximity and raycast interactor for [E] trigger activations.
 */

import { Ray } from '@babylonjs/core/Culling/ray';
import { Scene } from '@babylonjs/core/scene';
import { Vector3 } from '@babylonjs/core/Maths/math';
import { AbstractMesh } from '@babylonjs/core/Meshes/abstractMesh';
import { EventBus } from '../core/EventBus';

export interface InteractableTarget {
  id: string;
  name: string;
  label: string;
  mesh: AbstractMesh;
  interactionDistance?: number;
  onInteract: () => void;
}

export class InteractionRaycaster {
  private enabled = true;
  public setEnabled(enabled: boolean): void { this.enabled = enabled; if (!enabled) this.clearActiveTarget(); }
  private scene: Scene;
  private playerMesh: AbstractMesh;
  private targets: Map<string, InteractableTarget> = new Map();
  private currentActiveTarget: InteractableTarget | null = null;
  private eventBus: EventBus;

  private promptElement: HTMLElement | null = null;
  private promptLabel: HTMLElement | null = null;

  constructor(scene: Scene, playerMesh: AbstractMesh) {
    this.scene = scene;
    this.playerMesh = playerMesh;
    this.eventBus = EventBus.getInstance();

    this.promptElement = document.getElementById('interaction-prompt');
    this.promptLabel = document.getElementById('interaction-label');

    this.setupListeners();
  }

  public registerTarget(target: InteractableTarget): void {
    this.targets.set(target.id, target);
  }

  public unregisterTarget(id: string): void {
    this.targets.delete(id);
    if (this.currentActiveTarget?.id === id) {
      this.clearActiveTarget();
    }
  }

  private setupListeners(): void {
    // Check proximity on each frame
    this.scene.onBeforeRenderObservable.add(() => {
      this.checkProximity();
    });

    // Handle 'E' key press
    window.addEventListener('keydown', (e) => {
      if (this.enabled && !e.repeat && !/INPUT|TEXTAREA|SELECT/.test((e.target as HTMLElement)?.tagName) && e.key.toLowerCase() === 'e' && this.currentActiveTarget) {
        this.currentActiveTarget.onInteract();
      }
    });
  }

  private checkProximity(): void {
    if (!this.enabled) return;
    const playerPos = this.playerMesh.position;
    let closestTarget: InteractableTarget | null = null;
    let closestDist = Infinity;

    for (const target of this.targets.values()) {
      const maxDist = target.interactionDistance || 2.5;
      const dist = Vector3.Distance(playerPos, target.mesh.position);
      if (dist > maxDist || dist >= closestDist) continue;
      const origin = playerPos.add(new Vector3(0, .35, 0));
      const direction = target.mesh.getAbsolutePosition().subtract(origin);
      const obstruction = this.scene.pickWithRay(new Ray(origin, direction.normalize(), dist),
        mesh => mesh.checkCollisions && mesh !== target.mesh && mesh !== this.playerMesh && /Wall|Railing/.test(mesh.name));
      if (dist <= maxDist && dist < closestDist && !obstruction?.hit) {
        closestDist = dist;
        closestTarget = target;
      }
    }

    if (closestTarget) {
      this.setActiveTarget(closestTarget);
    } else {
      this.clearActiveTarget();
    }
  }

  private setActiveTarget(target: InteractableTarget): void {
    this.currentActiveTarget = target;
    if (this.promptElement && this.promptLabel) {
      this.promptLabel.textContent = target.label;
      this.promptElement.style.display = 'flex';
    }
    this.eventBus.emit('interact:available', target);
  }

  private clearActiveTarget(): void {
    if (this.currentActiveTarget) {
      this.currentActiveTarget = null;
      if (this.promptElement) {
        this.promptElement.style.display = 'none';
      }
      this.eventBus.emit('interact:unavailable', null);
    }
  }
}
