import { Scene } from '@babylonjs/core/scene';
import { Vector3, Color3 } from '@babylonjs/core/Maths/math';
import { MeshBuilder } from '@babylonjs/core/Meshes/meshBuilder';
import { StandardMaterial } from '@babylonjs/core/Materials/standardMaterial';
import { Mesh } from '@babylonjs/core/Meshes/mesh';
import { ArcRotateCamera } from '@babylonjs/core/Cameras/arcRotateCamera';
import '@babylonjs/core/Collisions/collisionCoordinator';

/** Feet are at y=0; collider is centered on the visible body. */
export class CharacterController {
  private mesh: Mesh;
  private keys: Record<string, boolean> = {};
  private enabled = true;
  private limbs: Mesh[] = [];
  private stride = 0;
  constructor(private scene: Scene, private camera: ArcRotateCamera | null, initialPosition = new Vector3(0, 1, 0)) {
    this.mesh = MeshBuilder.CreateBox('playerTeacher', { width: .6, height: 1.8, depth: .4 }, scene);
    this.mesh.visibility = 0;
    this.mesh.position.copyFrom(initialPosition);
    this.mesh.ellipsoid = new Vector3(.33, .9, .33);
    this.mesh.ellipsoidOffset = Vector3.Zero();
    const material = (name: string, hex: string) => {
      const m = new StandardMaterial(name, scene); m.diffuseColor = Color3.FromHexString(hex); m.specularColor = Color3.Black(); return m;
    };
    const shirt = material('teacherShirt', '#eef3ee'), pants = material('teacherTrousers', '#243e55');
    const skin = material('teacherSkin', '#d7a47e'), hair = material('teacherHair', '#302c29');
    const part = (name: string, width: number, height: number, depth: number, x: number, y: number, z: number, mat: StandardMaterial) => {
      const p = MeshBuilder.CreateBox(name, { width, height, depth }, scene); p.parent = this.mesh; p.position.set(x,y,z); p.material=mat; return p;
    };
    part('teacherTorso', .55,.65,.3,0,.1,0,shirt);
    const head = MeshBuilder.CreateSphere('teacherHead', {diameter:.4, segments:10}, scene); head.parent=this.mesh; head.position.y=.66; head.material=skin;
    part('teacherHair',.37,.14,.35,0,.82,-.02,hair);
    part('teacherTie',.07,.4,.025,0,.16,.165,pants);
    for(const side of [-1,1]) {
      this.limbs.push(part('teacherLeg'+side,.2,.75,.22,side*.15,-.53,0,pants));
      this.limbs.push(part('teacherArm'+side,.16,.64,.2,side*.38,.02,0,shirt));
      part('teacherShoe'+side,.22,.13,.35,side*.15,-.85,.05,hair);
    }
    window.addEventListener('keydown', e => {
      if (!this.enabled || /INPUT|TEXTAREA|SELECT/.test((e.target as HTMLElement)?.tagName)) return;
      if(['ArrowUp','ArrowDown','ArrowLeft','ArrowRight',' '].includes(e.key)) e.preventDefault();
      this.keys[e.key.toLowerCase()] = true;
    });
    window.addEventListener('keyup', e => { this.keys[e.key.toLowerCase()] = false; });
    window.addEventListener('blur', () => { this.keys = {}; });
    scene.onBeforeRenderObservable.add(() => this.updateMovement(Math.min(scene.getEngine().getDeltaTime()/1000,.05)));
  }
  public setCamera(camera: ArcRotateCamera): void { this.camera = camera; }
  public setEnabled(enabled: boolean): void { this.enabled=enabled; this.keys={}; }
  public getMesh(): Mesh { return this.mesh; }
  public getPosition(): Vector3 { return this.mesh.position; }
  private updateMovement(delta: number): void {
    if(!this.enabled || !this.camera) return;
    const forward = Number(!!(this.keys.w || this.keys.arrowup))-Number(!!(this.keys.s || this.keys.arrowdown));
    const side = Number(!!(this.keys.d || this.keys.arrowright))-Number(!!(this.keys.a || this.keys.arrowleft));
    const direction = this.camera.target.subtract(this.camera.position); direction.y=0; direction.normalize();
    const right = new Vector3(direction.z,0,-direction.x);
    const move = direction.scale(forward).add(right.scale(side));
    const moving = move.lengthSquared()>0;
    if(moving) {
      move.normalize().scaleInPlace((this.keys.shift?7:4.5)*delta);
      this.mesh.rotation.y=Math.atan2(move.x,move.z); this.stride += delta*(this.keys.shift?12:8);
    }
    move.y=-9.81*delta; this.mesh.moveWithCollisions(move);
    this.limbs.forEach((limb,i)=>{limb.rotation.x=moving?Math.sin(this.stride+(i<2?0:Math.PI))*.35:0;});
    if(this.mesh.position.y < -5) this.mesh.position.set(-12,1.1,3);
  }
}
