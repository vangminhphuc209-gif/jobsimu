/**
 * game/src/player/CharacterController.ts
 * Character movement controller supporting WASD + Shift running with collision detection.
 */

import { Scene } from '@babylonjs/core/scene';
import { Vector3 } from '@babylonjs/core/Maths/math';
import { MeshBuilder } from '@babylonjs/core/Meshes/meshBuilder';
import { StandardMaterial } from '@babylonjs/core/Materials/standardMaterial';
import { Color3 } from '@babylonjs/core/Maths/math.color';
import { Mesh } from '@babylonjs/core/Meshes/mesh';
import { ArcRotateCamera } from '@babylonjs/core/Cameras/arcRotateCamera';

export class CharacterController {
  private mesh: Mesh;
  private scene: Scene;
  private camera: ArcRotateCamera;
  private keys: Record<string, boolean> = {};

  private walkSpeed: number = 4.5;
  private runSpeed: number = 7.5;
  private gravity: number = -9.81;

  constructor(scene: Scene, camera: ArcRotateCamera, initialPosition = new Vector3(0, 1.0, 0)) {
    this.scene = scene;
    this.camera = camera;

    // Stylized teacher placeholder avatar
    this.mesh = MeshBuilder.CreateCapsule('playerTeacher', {
      radius: 0.45,
      height: 1.8,
      subdivisions: 8
    }, this.scene);

    this.mesh.position = initialPosition.clone();
    this.mesh.checkCollisions = true;
    this.mesh.ellipsoid = new Vector3(0.45, 0.9, 0.45);
    this.mesh.ellipsoidOffset = new Vector3(0, 0.9, 0);

    const mat = new StandardMaterial('playerMat', this.scene);
    mat.diffuseColor = new Color3(0.2, 0.45, 0.85); // Professional navy blue
    mat.specularColor = new Color3(0.1, 0.1, 0.1);
    this.mesh.material = mat;

    this.setupInputListeners();
    this.setupGameLoop();
  }

  public getMesh(): Mesh {
    return this.mesh;
  }

  public getPosition(): Vector3 {
    return this.mesh.position;
  }

  private setupInputListeners(): void {
    window.addEventListener('keydown', (e) => {
      this.keys[e.key.toLowerCase()] = true;
    });

    window.addEventListener('keyup', (e) => {
      this.keys[e.key.toLowerCase()] = false;
    });
  }

  private setupGameLoop(): void {
    this.scene.onBeforeRenderObservable.add(() => {
      const delta = this.scene.getEngine().getDeltaTime() / 1000.0;
      this.updateMovement(delta);
    });
  }

  private updateMovement(delta: number): void {
    let forward = 0;
    let side = 0;

    if (this.keys['w'] || this.keys['arrowup']) forward += 1;
    if (this.keys['s'] || this.keys['arrowdown']) forward -= 1;
    if (this.keys['d'] || this.keys['arrowright']) side += 1;
    if (this.keys['a'] || this.keys['arrowleft']) side -= 1;

    if (forward === 0 && side === 0) {
      // Apply passive gravity
      this.mesh.moveWithCollisions(new Vector3(0, this.gravity * delta, 0));
      return;
    }

    const isRunning = this.keys['shift'];
    const speed = isRunning ? this.runSpeed : this.walkSpeed;

    // Camera relative angles
    const cameraAlpha = this.camera.alpha;
    const moveAngle = Math.atan2(side, forward) - cameraAlpha - Math.PI / 2;

    const moveX = Math.sin(moveAngle) * speed * delta;
    const moveZ = Math.cos(moveAngle) * speed * delta;
    const moveY = this.gravity * delta;

    const moveVector = new Vector3(moveX, moveY, moveZ);
    this.mesh.moveWithCollisions(moveVector);

    // Rotate player mesh toward movement direction
    this.mesh.rotation.y = moveAngle;
  }
}
