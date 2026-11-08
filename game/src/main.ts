import { Vector3, Color3 } from '@babylonjs/core/Maths/math';
import { GameEngine } from './core/GameEngine';
import { SchoolWorld } from './world/SchoolWorld';
import { ThirdPersonCamera } from './player/ThirdPersonCamera';
import { CharacterController } from './player/CharacterController';
import { InteractionRaycaster } from './player/InteractionRaycaster';
import { NPCController } from './npc/NPCController';
import { GameHUD } from './ui/GameHUD';
import { LessonSession, type LessonAction } from './systems/LessonSession';

const SAVE_KEY = 'jobsimu.lesson.v1';

function start() {
  let session = new LessonSession();
  let saveStatus = 'Chỉ lưu trên trình duyệt này';
  try {
    const raw = localStorage.getItem(SAVE_KEY);
    if (raw) {
      const restored = LessonSession.restore(raw);
      if (restored) session = restored;
      else saveStatus = 'Bản lưu không hợp lệ; bắt đầu phiên mới';
    }
  } catch { saveStatus = 'Trình duyệt không cho lưu; tiến độ chỉ ở phiên này'; }

  const engine = new GameEngine('renderCanvas');
  const scene = engine.getScene();
  let player: CharacterController;
  let interactor: InteractionRaycaster;
  const npcs: NPCController[] = [];
  const hud = new GameHUD({
    onPauseChange: paused => { player?.setEnabled(!paused); interactor?.setEnabled(!paused); npcs.forEach(npc => npc.setEnabled(!paused)); },
    onQualityChange: preset => engine.setGraphicsPreset(preset),
    onRestart: () => {
      session = new LessonSession();
      player.getMesh().position.copyFrom(new Vector3(-12, 1, 3));
      save(); update();
    },
    onJournal: () => { if (session.state.phase === 'reflection') showReport(); else hud.showJournal(session.state.evidence.map(item => item.note)); },
  });
  const world = new SchoolWorld(scene, engine.getShadowGenerator());
  const spawn = session.position ? Vector3.FromArray(session.position) : new Vector3(-12, 1, 3);
  player = new CharacterController(scene, null as any, spawn);
  const camera = new ThirdPersonCamera(scene, engine.getCanvas(), player.getMesh());
  player.setCamera(camera.getCamera());
  interactor = new InteractionRaycaster(scene, player.getMesh());

  function save() {
    try {
      const pos = player.getMesh().position;
      localStorage.setItem(SAVE_KEY, session.serialize([pos.x, pos.y, pos.z]));
      saveStatus = 'Đã lưu trên máy · Chưa đồng bộ tài khoản';
    } catch { saveStatus = 'Không lưu được · Hãy giữ trang đang mở'; }
  }
  function update() {
    const state = session.state;
    const minutes = state.minutes;
    hud.update({objective: session.objective(), phase: state.phase === 'preparation' ? 'Chuẩn bị tiết học' : state.phase === 'teaching' ? 'Tiết học · Lớp 9A' : 'Phản tư sau tiết học', zone: world.getZone(player.getMesh().position), time: `${Math.floor(minutes / 60).toString().padStart(2,'0')}:${(minutes % 60).toString().padStart(2,'0')}`, saveStatus, progress: state.phase === 'reflection' ? 100 : Math.min(90,state.actions.length * 7)});
    world.setProjectorWorking(false);
    world.setLessonVisuals(state.strategy, state.actions.includes('use_board'), state.flags.ticketsCollected, state.observedGroups.map(n => n - 1));
  }
  function act(action: LessonAction) {
    if (hud.isModalOpen()) return;
    const result = session.act(action);
    hud.notify(result.message);
    if (result.ok) save();
    update();
    if (action === 'finish' && result.ok) {
      showReport();
    }
  }
  function showReport() {
    const report = session.report();
    hud.showReport({title:'Nhìn lại một tiết học', summary:report.pedagogicalReflection.summaryComment, evidence:session.state.evidence.map(item => item.note), missing:session.state.flags.ticketsCollected ? [] : [report.dataSufficiency]});
  }
  const register = (id: string, label: string, mesh: import('@babylonjs/core/Meshes/abstractMesh').AbstractMesh, action: LessonAction | (() => LessonAction)) => interactor.registerTarget({id,name:label,label,mesh,interactionDistance:2.7,onInteract:()=>act(typeof action === 'function' ? action() : action)});
  const mai = new NPCController(scene, {id:'mai',name:'Cô Mai',role:'Người hướng dẫn',initialPosition:new Vector3(-14,1,5),color:new Color3(.83,.38,.32),dialogueGreeting:'Máy chiếu lớp 9A đang chập chờn. Em có thể chuẩn bị bản in hoặc tổ chức hoạt động nhóm.'});
  npcs.push(mai);
  register('mai','Trao đổi với Cô Mai',mai.getMesh(),'ask_mai');
  register('printer','In học liệu dự phòng',world.printerMesh,'print_handouts');
  register('planner','Soạn hoạt động nhóm',world.reflectionDeskMesh,'plan_groups');
  register('projector','Kiểm tra máy chiếu',world.projectorControlMesh,'inspect_projector');
  register('board','Bảng lớp: bắt đầu / sử dụng bảng phấn',world.blackboardMesh,()=> session.state.phase === 'preparation' ? 'start_class' : 'use_board');
  world.groupMeshes.forEach((mesh,index)=>register(`group${index+1}`,`Quan sát sản phẩm nhóm ${index+1}`,mesh,`observe_group_${index+1}` as LessonAction));
  const nam = new NPCController(scene,{id:'nam',name:'Nam',role:'Học sinh lớp 9A',initialPosition:new Vector3(17.5,1,10),color:new Color3(.27,.48,.69),dialogueGreeting:'Thưa thầy cô, vì sao hôm nay trời lạnh mà Trái Đất vẫn nóng lên?'});
  npcs.push(nam);
  for (let col = 0; col < 4; col++) for (let row = 1; row < 4; row++) {
    const pupil = new NPCController(scene, {id:`pupil_${col}_${row}`,name:'Học sinh',role:'Lớp 9A',initialPosition:new Vector3(8.5 + col*3, .95, 7 + row*3 + .85),color:new Color3(.77,.85,.86),dialogueGreeting:'',showLabel:false,seated:true});
    pupil.getMesh().rotation.y = Math.PI; npcs.push(pupil);
  }
  register('nam','Cùng Nam tìm bằng chứng khí hậu',nam.getMesh(),'answer_nam');
  register('tickets','Thu phiếu cuối giờ',world.exitTicketStackMesh,'collect_tickets');
  // Reflection remains a physical destination in the staff room, replacing the planning action after class.
  const refreshTargets = () => {
    if(session.state.phase !== 'preparation') register('planner','Kết thúc tiết học và xem bằng chứng',world.reflectionDeskMesh,'finish');
    else register('planner','Soạn hoạt động nhóm',world.reflectionDeskMesh,'plan_groups');
  };
  let elapsed = 0;
  scene.onBeforeRenderObservable.add(()=>{
    elapsed += scene.getEngine().getDeltaTime();
    if(elapsed > 1000) { update(); refreshTargets(); elapsed = 0; }
  });
  window.addEventListener('pagehide',save);
  document.addEventListener('visibilitychange',()=>{if(document.hidden)save();});
  window.setInterval(save,5000);
  update(); refreshTargets();
  engine.startRenderLoop();
  document.getElementById('boot-status')!.hidden = true;
  hud.showWelcome(session.state.actions.length > 0);
}

try { start(); } catch (error) {
  console.error('[JOBSIMU] Startup failed',error);
  const status = document.getElementById('boot-status')!;
  status.replaceChildren();
  const title = document.createElement('h1'); title.textContent='Chưa mở được cảnh 3D';
  const message=document.createElement('p'); message.textContent='Trình duyệt cần hỗ trợ WebGL. Hãy bật tăng tốc đồ họa, thử Chrome/Edge trên máy tính rồi tải lại. Tiến độ đã lưu vẫn được giữ.';
  const button=document.createElement('button');button.textContent='Thử tải lại';button.onclick=()=>location.reload();
  status.append(title,message,button);
}
