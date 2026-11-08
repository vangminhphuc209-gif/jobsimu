/**
 * game/src/world/SchoolWorld.ts
 * Blockout architecture for the 4 core P0 zones:
 * 1. Sân trường & Cổng (Yard)
 * 2. Phòng Giáo viên (StaffRoom)
 * 3. Hành lang (Corridor)
 * 4. Lớp 9A (Class9A)
 * Implements mesh instancing for desks/chairs and strict collision barriers.
 */

import { DynamicTexture } from '@babylonjs/core/Materials/Textures/dynamicTexture';
import { Scene } from '@babylonjs/core/scene';
import { Vector3, Color3 } from '@babylonjs/core/Maths/math';
import { MeshBuilder } from '@babylonjs/core/Meshes/meshBuilder';
import { StandardMaterial } from '@babylonjs/core/Materials/standardMaterial';
import { Mesh } from '@babylonjs/core/Meshes/mesh';
import { ShadowGenerator } from '@babylonjs/core/Lights/Shadows/shadowGenerator';

export class SchoolWorld {
  private scene: Scene;
  private shadowGen: ShadowGenerator | null;

  // Key interactable prop meshes
  private lessonPapers: Mesh[] = [];
  private boardBars: Mesh[] = [];
  private observedMaterial!: StandardMaterial;
  private groupMaterial!: StandardMaterial;
  private idleGroupMaterial!: StandardMaterial;
  public blackboardMesh!: Mesh;
  public groupMeshes: Mesh[] = [];
  public reflectionDeskMesh!: Mesh;
  public projectorControlMesh!: Mesh;
  public projectorMesh!: Mesh;
  public getZone(position: Vector3): string {
    if(position.x < -4 && position.z > -3) return 'Phòng giáo viên';
    if(position.x > 5 && position.z > 1) return 'Lớp 9A';
    return position.z < -5 ? 'Sân trường' : 'Hành lang';
  }
  public setProjectorWorking(working: boolean): void {
    const mat = this.projectorLightMesh.material as StandardMaterial;
    mat.emissiveColor = working ? new Color3(.1,1,.4) : new Color3(1,.15,.05);
  }
  private label(text: string, position: Vector3, width = 3): Mesh {
    const sign = MeshBuilder.CreatePlane('sign-'+text,{width,height:width/4,sideOrientation:Mesh.DOUBLESIDE},this.scene);
    sign.position.copyFrom(position);
    const texture = new DynamicTexture('label-'+text,{width:768,height:192},this.scene,false);
    texture.drawText(text,null,122,'bold 48px sans-serif','#ffffff','#245957',true);
    const mat = new StandardMaterial('labelMat-'+text,this.scene);mat.diffuseTexture=texture;mat.emissiveColor=Color3.White();mat.backFaceCulling=false;sign.material=mat;
    return sign;
  }
  public projectorLightMesh!: Mesh;
  public printerMesh!: Mesh;
  public exitTicketStackMesh!: Mesh;

  constructor(scene: Scene, shadowGen: ShadowGenerator | null = null) {
    this.scene = scene;
    this.shadowGen = shadowGen;

    this.buildYard();
    this.buildStaffRoom();
    this.buildCorridor();
    this.buildClass9A();
    this.buildBoundaryColliders();
    this.decorate();
    this.buildLessonVisuals();
  }

  /**
   * Zone 1: Sân trường & Cổng (Yard)
   * Position: X: -30 to 10, Z: -40 to -10, Y: 0
   */
  private buildYard(): void {
    // Yard Ground
    const ground = MeshBuilder.CreateGround('yardGround', { width: 69, height: 79 }, this.scene);
    ground.position = new Vector3(-5, 0, -10);
    ground.checkCollisions = true;

    const yardMat = new StandardMaterial('yardMat', this.scene);
    yardMat.diffuseColor = new Color3(0.55, 0.58, 0.52); // Cement schoolyard
    ground.material = yardMat;

    // Flagpole
    const flagpole = MeshBuilder.CreateCylinder('flagpole', { diameter: 0.25, height: 10 }, this.scene);
    flagpole.position = new Vector3(-10, 5, -25);
    flagpole.checkCollisions = true;

    // School Gate Barrier
    const gateWall = MeshBuilder.CreateBox('schoolGate', { width: 30, height: 4, depth: 0.8 }, this.scene);
    gateWall.position = new Vector3(-10, 2, -44);
    gateWall.checkCollisions = true;
    const gateMat = new StandardMaterial('gateMat', this.scene);
    gateMat.diffuseColor = new Color3(0.65, 0.2, 0.2); // Red iron gate
    gateWall.material = gateMat;
  }

  /**
   * Zone 2: Phòng Giáo viên / Hội đồng (StaffRoom)
   * Position: X: -20 to -5, Z: -5 to 15, Y: 0 (Tầng 1)
   */
  private buildStaffRoom(): void {
    const floor = MeshBuilder.CreateBox('staffFloor', { width: 16, height: 0.2, depth: 16 }, this.scene);
    floor.position = new Vector3(-12, -0.1, 5);
    floor.checkCollisions = true;
    const tileMat = new StandardMaterial('staffTileMat', this.scene);
    tileMat.diffuseColor = new Color3(0.85, 0.82, 0.75); // Polished floor tiles
    floor.material = tileMat;

    // Staff room walls
    this.createWall('staffWallBack', new Vector3(-12, 2.5, 13), 16, 5, 0.4);
    this.createWall('staffWallLeft', new Vector3(-20, 2.5, 5), 0.4, 5, 16);
    this.createWall('staffWallFront', new Vector3(-12, 2.5, -3), 16, 5, 0.4);
    // Door opening on right wall
    this.createWall('staffWallRightA', new Vector3(-4, 2.5, 1), 0.4, 5, 8);
    this.createWall('staffWallRightB', new Vector3(-4, 2.5, 10), 0.4, 5, 4);

    // Printer / Photocopy Machine (Interactable prop)
    this.printerMesh = MeshBuilder.CreateBox('printerMachine', { width: 1.2, height: 1.0, depth: 1.0 }, this.scene);
    this.printerMesh.position = new Vector3(-6, 0.6, 11);
    this.printerMesh.checkCollisions = true;
    const printerMat = new StandardMaterial('printerMat', this.scene);
    printerMat.diffuseColor = new Color3(0.9, 0.9, 0.92);
    this.printerMesh.material = printerMat;
    this.label('MÁY IN · PHIẾU DỰ PHÒNG',new Vector3(-6,2,11.6),3);

    // Teacher Desks
    const teacherDesk = MeshBuilder.CreateBox('mentorDesk', { width: 2.2, height: 0.8, depth: 1.2 }, this.scene);
    teacherDesk.position = new Vector3(-16, 0.5, 5);
    teacherDesk.checkCollisions = true;
    const woodMat = new StandardMaterial('woodMat', this.scene);
    woodMat.diffuseColor = new Color3(0.5, 0.32, 0.18);
    teacherDesk.material = woodMat;
    this.reflectionDeskMesh=teacherDesk;
    this.label('PHÒNG GIÁO VIÊN',new Vector3(-12,3.3,12.7),6);
  }

  /**
   * Zone 3: Hành lang & Cầu thang (Corridor)
   * Position: X: -4 to 4, Z: -5 to 25
   */
  private buildCorridor(): void {
    const corridorFloor = MeshBuilder.CreateBox('corridorFloor', { width: 10, height: 0.2, depth: 32 }, this.scene);
    corridorFloor.position = new Vector3(0, -0.1, 10);
    corridorFloor.checkCollisions = true;

    const corridorMat = new StandardMaterial('corridorMat', this.scene);
    corridorMat.diffuseColor = new Color3(0.78, 0.78, 0.78);
    corridorFloor.material = corridorMat;

    // Outer railing towards the school yard
    // The east side is the classroom entrance: never seal it with a railing.
    this.label('9A →',new Vector3(0,2.3,18),2);
  }

  /**
   * Zone 4: Lớp 9A (Class9A)
   * Position: X: 4 to 22, Z: 0 to 20, Y: 0 (Ground/1st Floor elevation for vertical slice simplicity)
   */
  private buildClass9A(): void {
    // Classroom Floor
    const classFloor = MeshBuilder.CreateBox('classFloor', { width: 16, height: 0.2, depth: 18 }, this.scene);
    classFloor.position = new Vector3(13, -0.1, 10);
    classFloor.checkCollisions = true;
    const classMat = new StandardMaterial('classFloorMat', this.scene);
    classMat.diffuseColor = new Color3(0.8, 0.75, 0.65);
    classFloor.material = classMat;

    // Classroom Walls
    this.createWall('classWallBack', new Vector3(13, 2.5, 19), 16, 5, 0.4);
    this.createWall('classWallRight', new Vector3(21, 2.5, 10), 0.4, 5, 18);
    this.createWall('classWallFront', new Vector3(13, 2.5, 1), 16, 5, 0.4);
    // Left wall with door to corridor
    this.createWall('classWallLeftA', new Vector3(5, 2.5, 4), 0.4, 5, 6);
    this.createWall('classWallLeftB', new Vector3(5, 2.5, 15), 0.4, 5, 6);

    // Chalkboard on Front Wall
    const blackboard = MeshBuilder.CreateBox('blackboard', { width: 6.0, height: 2.2, depth: 0.1 }, this.scene);
    blackboard.position = new Vector3(13, 2.2, 1.25);
    const boardMat = new StandardMaterial('boardMat', this.scene);
    boardMat.diffuseColor = new Color3(0.1, 0.25, 0.15); // Classic green board
    blackboard.material = boardMat;
    this.blackboardMesh = blackboard;
    this.label('BIẾN ĐỔI KHÍ HẬU',new Vector3(13,2.3,1.32),5).rotation.y=Math.PI;

    // Teacher Desk & Podium
    const teacherDesk = MeshBuilder.CreateBox('classTeacherDesk', { width: 2.0, height: 0.85, depth: 1.0 }, this.scene);
    teacherDesk.position = new Vector3(10, 0.5, 3.5);
    teacherDesk.checkCollisions = true;

    // Projector Prop hanging from ceiling
    this.projectorMesh = MeshBuilder.CreateBox('ceilingProjector', { width: 0.8, height: 0.4, depth: 0.6 }, this.scene);
    this.projectorMesh.position = new Vector3(13, 4.2, 7.5);
    this.projectorMesh.checkCollisions = true;
    const projMat = new StandardMaterial('projMat', this.scene);
    projMat.diffuseColor = new Color3(0.8, 0.8, 0.8);
    this.projectorMesh.material = projMat;
    this.projectorControlMesh=MeshBuilder.CreateBox('projectorControl',{width:.3,height:.1,depth:.45},this.scene);
    this.projectorControlMesh.position.set(10.6,1,3.5);this.projectorControlMesh.material=projMat;

    // Projector Warning Indicator Light (Yellow blinking / Red)
    this.projectorLightMesh = MeshBuilder.CreateSphere('projIndicator', { diameter: 0.15 }, this.scene);
    this.projectorLightMesh.position = new Vector3(13, 3.9, 7.2);
    const lightMat = new StandardMaterial('projLightMat', this.scene);
    lightMat.emissiveColor = new Color3(1.0, 0.8, 0.1); // Amber warning
    this.projectorLightMesh.material = lightMat;

    // Exit ticket stack on teacher desk
    this.exitTicketStackMesh = MeshBuilder.CreateBox('exitTicketStack', { width: 0.4, height: 0.1, depth: 0.3 }, this.scene);
    this.exitTicketStackMesh.position = new Vector3(10, 0.95, 3.5);
    const paperMat = new StandardMaterial('paperMat', this.scene);
    paperMat.diffuseColor = new Color3(0.98, 0.98, 0.95);
    this.exitTicketStackMesh.material = paperMat;

    // Mesh Instancing for 16 student double desks and 32 chairs
    this.buildInstancedDesksAndChairs();
  }

  private buildInstancedDesksAndChairs(): void {
    // Template Desk (Instanced for high FPS)
    const baseDesk = MeshBuilder.CreateBox('baseDeskTemplate', { width: 2.2, height: 0.75, depth: 0.9 }, this.scene);
    baseDesk.isVisible = false;
    baseDesk.checkCollisions = true;
    const deskMat = new StandardMaterial('deskMat', this.scene);
    deskMat.diffuseColor = new Color3(0.7, 0.5, 0.3);
    baseDesk.material = deskMat;

    const chair=MeshBuilder.CreateBox('chairTemplate',{width:.6,height:.12,depth:.6},this.scene);
    chair.isVisible=false;chair.material=deskMat;
    const back=MeshBuilder.CreateBox('chairBackTemplate',{width:.6,height:.65,depth:.12},this.scene);
    back.isVisible=false;back.material=deskMat;
    // 4 columns x 4 rows = 16 double desks for 32 students
    const startX = 8.5;
    const startZ = 7.0;
    const spacingX = 3.0;
    const spacingZ = 3.0;

    for (let col = 0; col < 4; col++) {
      for (let row = 0; row < 4; row++) {
        for(const side of [-.6,.6]) {
          const seat=chair.createInstance(`chair_${col}_${row}_${side}`);seat.position.set(startX+col*spacingX+side,.5,startZ+row*spacingZ+.85);
          const rest=back.createInstance(`chairBack_${col}_${row}_${side}`);rest.position.set(seat.position.x,.8,seat.position.z+.27);
        }
        const instance = baseDesk.createInstance(`studentDesk_${col}_${row}`);
        instance.position = new Vector3(startX + col * spacingX, 0.45, startZ + row * spacingZ);
        instance.checkCollisions = true;
        if(row===0) {
          const marker=MeshBuilder.CreateBox('groupWork'+col,{width:.6,height:.03,depth:.45},this.scene);
          marker.position.set(startX+col*spacingX,.84,startZ); marker.material=deskMat;
          this.groupMeshes.push(marker);
        }
      }
    }
  }

  private createWall(name: string, pos: Vector3, w: number, h: number, d: number): Mesh {
    const wall = MeshBuilder.CreateBox(name, { width: w, height: h, depth: d }, this.scene);
    wall.position = pos;
    wall.checkCollisions = true;
    const wallMat = new StandardMaterial(`${name}Mat`, this.scene);
    wallMat.diffuseColor = new Color3(0.9, 0.88, 0.82); // School pale cream
    wall.material = wallMat;
    return wall;
  }

  /** Visible consequences share the saved gameplay state; never keep a second progression model. */
  public setLessonVisuals(strategy: 'handouts' | 'groups' | 'board' | null, boardUsed: boolean, ticketsCollected: boolean, observedGroups: number[]): void {
    this.lessonPapers.forEach(paper => paper.setEnabled(strategy === 'handouts'));
    this.boardBars.forEach(bar => bar.setEnabled(boardUsed));
    this.exitTicketStackMesh.setEnabled(!ticketsCollected);
    this.groupMeshes.forEach((mesh,index) => {
      mesh.material = observedGroups.includes(index) ? this.observedMaterial : strategy === 'groups' ? this.groupMaterial : this.idleGroupMaterial;
    });
  }
  private buildLessonVisuals(): void {
    const mat = (name: string, color: string) => {
      const m=new StandardMaterial(name,this.scene);m.diffuseColor=Color3.FromHexString(color);m.specularColor=Color3.Black();return m;
    };
    const paperMaterial=mat('lessonPaper','#fff9e8');
    this.observedMaterial=mat('groupObserved','#6cbe9c');
    this.groupMaterial=mat('groupDiscussion','#e8b654');
    this.idleGroupMaterial=mat('groupIdle','#f6f0d9');
    const paper=MeshBuilder.CreateBox('lessonPaperTemplate',{width:.65,height:.025,depth:.45},this.scene);
    paper.material=paperMaterial;paper.isVisible=false;
    for(let col=0;col<4;col++) for(let row=0;row<4;row++) {
      // Clones permit independently toggling each desk's handout, while sharing geometry/material.
      const sheet=paper.clone(`lessonHandout_${col}_${row}`)!;
      sheet.isVisible=true;sheet.position.set(8.5+col*3,.85,7+row*3);
      sheet.setEnabled(false);this.lessonPapers.push(sheet);
    }
    const chalk=mat('chalkGraph','#f1e6ac');
    for(let i=0;i<5;i++) {
      const height=.25+i*.15;
      const bar=MeshBuilder.CreateBox('boardClimateGraph'+i,{width:.35,height,depth:.03},this.scene);
      bar.position.set(11.8+i*.6,1.45+height/2,1.4);bar.material=chalk;bar.setEnabled(false);this.boardBars.push(bar);
    }
    this.groupMeshes.forEach(mesh=>mesh.material=this.idleGroupMaterial);
  }
  private decorate(): void {
    const leafMat=new StandardMaterial('leafMat',this.scene); leafMat.diffuseColor=Color3.FromHexString('#4f8761');
    const trunkMat=new StandardMaterial('trunkMat',this.scene);trunkMat.diffuseColor=Color3.FromHexString('#926849');
    const trunk=MeshBuilder.CreateCylinder('treeTrunk',{height:3,diameter:.5,tessellation:7},this.scene);trunk.isVisible=false;trunk.material=trunkMat;
    const crown=MeshBuilder.CreateSphere('treeCrown',{diameter:5,segments:4},this.scene);crown.isVisible=false;crown.material=leafMat;
    [[-29,-30],[-29,-13],[8,-32],[8,-15]].forEach(([x,z],i)=>{
      const t=trunk.createInstance('treeTrunk'+i);t.position.set(x,1.5,z);t.checkCollisions=true;
      const c=crown.createInstance('treeCrown'+i);c.position.set(x,4.6,z);
    });
    const bench=MeshBuilder.CreateBox('benchTemplate',{width:3,height:.5,depth:1},this.scene);bench.isVisible=false;bench.material=trunkMat;
    [-24,2].forEach((x,i)=>{const b=bench.createInstance('yardBench'+i);b.position.set(x,.3,-16);b.checkCollisions=true;});
    this.label('THCS TUỆ ĐỨC',new Vector3(-10,3,-43.5),12).rotation.y=Math.PI;
    this.label('SÂN TRƯỜNG',new Vector3(-15,1.8,-7),4);
    const flag=MeshBuilder.CreateBox('schoolFlag',{width:2.4,height:1.5,depth:.03},this.scene);flag.position.set(-8.7,8.8,-25);
    const red=new StandardMaterial('flagRed',this.scene);red.diffuseColor=Color3.FromHexString('#bf3f3f');flag.material=red;
    for(const x of [8,13,18]) {
      const window=MeshBuilder.CreateBox('window',{width:.05,height:2,depth:3},this.scene);window.position.set(20.76,2.7,x);
      const glass=new StandardMaterial('glass'+x,this.scene);glass.diffuseColor=Color3.FromHexString('#a2d2d8');glass.emissiveColor=new Color3(.12,.2,.22);window.material=glass;
    }
    for(const mesh of this.scene.meshes) {
      mesh.receiveShadows=true;
      if(mesh.name.startsWith('tree') || mesh.name.startsWith('studentDesk')) this.shadowGen?.addShadowCaster(mesh);
    }
  }
  private buildBoundaryColliders(): void {
    // Invisible perimeter walls to prevent falling off map
    const bounds = [
      { name: 'bNorth', pos: new Vector3(0, 5, 30), w: 80, h: 10, d: 1 },
      { name: 'bSouth', pos: new Vector3(0, 5, -50), w: 80, h: 10, d: 1 },
      { name: 'bEast', pos: new Vector3(30, 5, 0), w: 1, h: 10, d: 90 },
      { name: 'bWest', pos: new Vector3(-40, 5, 0), w: 1, h: 10, d: 90 },
    ];

    bounds.forEach(b => {
      const invisWall = MeshBuilder.CreateBox(b.name, { width: b.w, height: b.h, depth: b.d }, this.scene);
      invisWall.position = b.pos;
      invisWall.isVisible = false;
      invisWall.checkCollisions = true;
    });
  }
}
