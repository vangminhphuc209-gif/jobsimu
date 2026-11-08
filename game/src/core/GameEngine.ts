import '@babylonjs/core/Lights/Shadows/shadowGeneratorSceneComponent';
import '@babylonjs/core/Collisions/collisionCoordinator';
/**
 * game/src/core/GameEngine.ts
 * Core 3D engine initializer using Babylon.js.
 * Manages rendering loop, graphic presets (30-60 FPS target), and window resize.
 */

import { Engine } from '@babylonjs/core/Engines/engine';
import { Scene } from '@babylonjs/core/scene';
import { Vector3, Color4, Color3 } from '@babylonjs/core/Maths/math';
import { HemisphericLight } from '@babylonjs/core/Lights/hemisphericLight';
import { DirectionalLight } from '@babylonjs/core/Lights/directionalLight';
import { ShadowGenerator } from '@babylonjs/core/Lights/Shadows/shadowGenerator';
import { GraphicsPreset } from './types';
import { EventBus } from './EventBus';

export class GameEngine {
  private canvas: HTMLCanvasElement;
  private engine: Engine;
  private scene: Scene;
  private shadowGenerator: ShadowGenerator | null = null;
  private currentPreset: GraphicsPreset = 'Medium';
  private eventBus: EventBus;

  constructor(canvasId: string) {
    const canvasEl = document.getElementById(canvasId) as HTMLCanvasElement;
    if (!canvasEl) {
      throw new Error(`Cannot find canvas element with ID "${canvasId}"`);
    }
    this.canvas = canvasEl;
    this.eventBus = EventBus.getInstance();

    // Initialize Babylon Engine with WebGL antialiasing
    this.engine = new Engine(this.canvas, true, {
      preserveDrawingBuffer: true,
      stencil: true,
      powerPreference: 'high-performance'
    });

    this.scene = new Scene(this.engine);
    this.scene.clearColor = new Color4(0.72, 0.85, 0.92, 1.0);
    this.scene.collisionsEnabled = true;

    this.setupLighting();
    this.setupResizeHandler();
  }

  public getScene(): Scene {
    return this.scene;
  }

  public getCanvas(): HTMLCanvasElement {
    return this.canvas;
  }

  public getShadowGenerator(): ShadowGenerator | null {
    return this.shadowGenerator;
  }

  private setupLighting(): void {
    // Ambient soft hemisphere light
    const hemiLight = new HemisphericLight('hemiLight', new Vector3(0, 1, 0), this.scene);
    hemiLight.intensity = 0.75;
    hemiLight.groundColor = new Color3(0.2, 0.22, 0.28);

    // Directional sunlight simulating morning daylight
    const dirLight = new DirectionalLight('sunLight', new Vector3(-0.5, -1, -0.5), this.scene);
    dirLight.position = new Vector3(20, 40, 20);
    dirLight.intensity = 1.0;

    // Shadows on Medium/High preset
    if (this.currentPreset !== 'Low') {
      try {
        this.shadowGenerator = new ShadowGenerator(1024, dirLight);
        this.shadowGenerator.useBlurExponentialShadowMap = true;
        this.shadowGenerator.blurKernel = 16;
      } catch (e) {
        console.warn('Shadow map creation skipped:', e);
      }
    }
  }

  public setGraphicsPreset(preset: GraphicsPreset): void {
    this.currentPreset = preset;
    console.log(`[GameEngine] Switching graphics preset to: ${preset}`);

    switch (preset) {
      case 'Low':
        this.engine.setHardwareScalingLevel(1.5);
        if (this.shadowGenerator) {
          this.scene.shadowsEnabled = false;
        }
        break;
      case 'Medium':
        this.scene.shadowsEnabled = true;
        this.engine.setHardwareScalingLevel(1.0);
        break;
      case 'High':
        this.scene.shadowsEnabled = true;
        this.engine.setHardwareScalingLevel(0.85); // High DPI crisp rendering
        break;
    }
    this.eventBus.emit('preset:changed', preset);
  }

  public startRenderLoop(): void {
    this.engine.runRenderLoop(() => {
      this.scene.render();
    });
  }

  private setupResizeHandler(): void {
    window.addEventListener('resize', () => {
      this.engine.resize();
    });
  }

  public getFPS(): number {
    return Math.round(this.engine.getFps());
  }
}
