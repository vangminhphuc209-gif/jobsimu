/**
 * game/src/player/ThirdPersonCamera.ts
 * Third-person orbital follow camera with collision prevention against walls.
 */

import { ArcRotateCamera } from '@babylonjs/core/Cameras/arcRotateCamera';
import { Scene } from '@babylonjs/core/scene';
import { Vector3 } from '@babylonjs/core/Maths/math';
import { TransformNode } from '@babylonjs/core/Meshes/transformNode';

export class ThirdPersonCamera {
  private camera: ArcRotateCamera;

  constructor(scene: Scene, canvas: HTMLCanvasElement, targetNode: TransformNode) {
    this.camera = new ArcRotateCamera(
      'thirdPersonCamera',
      -Math.PI / 2,
      Math.PI / 3,
      8.0,
      new Vector3(0, 1.6, 0),
      scene
    );

    this.camera.lowerRadiusLimit = 2.0;
    this.camera.upperRadiusLimit = 15.0;
    this.camera.lowerBetaLimit = 0.2;
    this.camera.upperBetaLimit = Math.PI / 2 - 0.05; // Prevent camera from flipping under floor
    this.camera.checkCollisions = true;
    this.camera.collisionRadius = new Vector3(0.5, 0.5, 0.5);

    this.camera.lockedTarget = targetNode;
    this.camera.attachControl(canvas, true);
  }

  public getCamera(): ArcRotateCamera {
    return this.camera;
  }
}
