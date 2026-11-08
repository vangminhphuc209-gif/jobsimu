import type { GraphicsPreset } from '../core/types';
import './hud.css';

export interface HUDCallbacks {
  onPauseChange: (paused: boolean) => void;
  onQualityChange: (preset: GraphicsPreset) => void;
  onRestart: () => void;
  onJournal?: () => void;
}
export interface HUDState {
  objective: string;
  phase: string;
  zone: string;
  time: string;
  saveStatus: string;
  progress?: number;
}
export interface LessonReportView {
  title: string;
  summary: string;
  evidence: string[];
  missing: string[];
}

/** DOM UI deliberately uses textContent for all game/save-derived values. */
export class GameHUD {
  private root: HTMLElement;
  private modal: HTMLDivElement;
  private toast: HTMLElement;
  private toastTimer = 0;
  private modalOpen = false;
  private focusBeforeModal: HTMLElement | null = null;
  constructor(private callbacks: HUDCallbacks) {
    document.getElementById('ui-overlay')?.remove();
    this.root = document.createElement('div');
    this.root.id = 'ui-overlay';
    this.root.innerHTML = `<header class="sim-top"><div class="sim-brand"><span class="sim-logo">J</span><div><b>JOBSIMU</b><small>MỘT NGÀY LÀM GIÁO VIÊN</small></div></div><div class="sim-location"><span id="sim-zone"></span><b id="clock-display">07:00</b></div><button id="sim-pause" aria-label="Mở menu tạm dừng">Ⅱ <span>Tạm dừng</span></button></header><aside class="sim-objective"><span class="sim-eyebrow" id="sim-phase"></span><h2 id="quest-objective"></h2><div class="sim-progress"><div id="sim-progress-fill"></div></div><p id="sim-save"></p></aside><div id="interaction-prompt"><kbd>E</kbd><span id="interaction-label"></span></div><footer class="sim-footer"><span><kbd>W A S D</kbd> Di chuyển <kbd>Shift</kbd> Chạy <kbd>Chuột</kbd> Xoay góc nhìn</span><button id="sim-journal"><kbd>J</kbd> Nhật ký</button><span class="sim-slice">BẢN CHƠI THỬ · MỘT TIẾT HỌC</span></footer>`;
    document.body.appendChild(this.root);
    this.modal = document.createElement('div');
    this.modal.className = 'sim-modal';
    this.modal.hidden = true;
    this.modal.setAttribute('role', 'dialog');
    this.modal.setAttribute('aria-modal', 'true');
    document.body.appendChild(this.modal);
    this.toast = document.createElement('div');
    this.toast.className = 'sim-toast';
    this.toast.setAttribute('role', 'status');
    document.body.appendChild(this.toast);
    this.root.querySelector('#sim-pause')!.addEventListener('click', () => this.showPause());
    this.root.querySelector('#sim-journal')!.addEventListener('click', () => this.callbacks.onJournal?.());
    window.addEventListener('keydown', (event) => {
      if (event.repeat || /INPUT|TEXTAREA|SELECT/.test((event.target as HTMLElement)?.tagName)) return;
      if (event.key === 'Escape' || event.key.toLowerCase() === 'p') {
        event.preventDefault();
        if (this.modalOpen) this.closeModal(); else this.showPause();
      } else if (event.key.toLowerCase() === 'j' && !this.modalOpen) {
        event.preventDefault(); this.callbacks.onJournal?.();
      }
      if (event.key === 'Tab' && this.modalOpen) {
        const nodes = Array.from(this.modal.querySelectorAll<HTMLElement>('button, select, [tabindex="0"]'));
        const first = nodes[0], last = nodes[nodes.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
      }
    });
  }
  public isModalOpen(): boolean { return this.modalOpen; }
  public update(state: HUDState): void {
    for (const [id, value] of Object.entries({ 'sim-zone': state.zone, 'clock-display': state.time, 'sim-phase': state.phase, 'quest-objective': state.objective, 'sim-save': state.saveStatus })) {
      this.root.querySelector(`#${id}`)!.textContent = value;
    }
    (this.root.querySelector('#sim-progress-fill') as HTMLElement).style.width = `${Math.max(0, Math.min(100, state.progress ?? 0))}%`;
  }
  public notify(message: string): void {
    this.toast.textContent = message;
    this.toast.classList.add('visible');
    window.clearTimeout(this.toastTimer);
    this.toastTimer = window.setTimeout(() => this.toast.classList.remove('visible'), 6500);
  }
  private openModal(title: string, subtitle: string): HTMLElement {
    this.focusBeforeModal = document.activeElement as HTMLElement;
    this.modal.replaceChildren();
    const panel = document.createElement('section');
    panel.className = 'sim-panel';
    const eyebrow = document.createElement('span'); eyebrow.className = 'sim-eyebrow'; eyebrow.textContent = 'JOBSIMU / TRẢI NGHIỆM NGHỀ 3D';
    const heading = document.createElement('h1'); heading.textContent = title; heading.id = 'sim-modal-title';
    const description = document.createElement('p'); description.className = 'sim-lead'; description.textContent = subtitle;
    panel.append(eyebrow, heading, description); this.modal.appendChild(panel);
    this.modal.setAttribute('aria-labelledby', heading.id);
    this.modal.hidden = false; this.modalOpen = true; this.callbacks.onPauseChange(true);
    return panel;
  }
  private button(panel: HTMLElement, text: string, action: () => void, secondary = false): HTMLButtonElement {
    const button = document.createElement('button'); button.className = secondary ? 'sim-secondary' : 'sim-primary'; button.textContent = text;
    button.addEventListener('click', action); panel.appendChild(button); return button;
  }
  public closeModal(): void {
    this.modal.hidden = true; this.modalOpen = false; this.callbacks.onPauseChange(false);
    this.focusBeforeModal?.focus();
  }
  public showWelcome(hasSave = false): void {
    const panel = this.openModal('Tiết học đầu tiên của bạn.', '07:00 · Trường THCS Tuệ Đức. Máy chiếu lớp 9A đang chập chờn. Bạn sẽ chuẩn bị và tổ chức tiết học như thế nào?');
    const steps = document.createElement('div'); steps.className = 'sim-welcome-steps';
    for (const [title, detail] of [['01 / CHUẨN BỊ', 'Gặp cô Mai, kiểm tra thiết bị hoặc lấy bản in.'], ['02 / ĐỨNG LỚP', 'Dạy bằng bảng, bản in hoặc tổ chức thảo luận.'], ['03 / NHÌN LẠI', 'Quan sát nhóm, thu phiếu và xem bằng chứng.']]) {
      const card = document.createElement('div'); const strong = document.createElement('strong'); strong.textContent = title;
      const text = document.createElement('p'); text.textContent = detail; card.append(strong, text); steps.appendChild(card);
    }
    panel.appendChild(steps);
    const note = document.createElement('p'); note.className = 'sim-note'; note.textContent = 'WASD để đi · Kéo chuột để xoay camera · E khi đứng gần đồ vật · J mở nhật ký · Esc tạm dừng. Tiến độ được lưu trên trình duyệt này, chưa đồng bộ tài khoản.'; panel.appendChild(note);
    this.button(panel, hasSave ? 'Tiếp tục tiết học →' : 'Bước vào trường →', () => this.closeModal()).focus();
    if (hasSave) this.button(panel, 'Bắt đầu tiết học mới…', () => this.confirmRestart(), true);
  }
  public showPause(): void {
    const panel = this.openModal('Nghỉ một chút.', 'Thế giới đang tạm dừng. Bạn có thể tiếp tục bất cứ lúc nào.');
    const label = document.createElement('label'); label.textContent = 'Chất lượng đồ họa ';
    const select = document.createElement('select');
    for (const [value, text] of [['Low', 'Thấp — máy yếu'], ['Medium', 'Vừa'], ['High', 'Cao']]) { const option = document.createElement('option'); option.value = value; option.textContent = text; select.appendChild(option); }
    select.value = this.quality; select.addEventListener('change', () => { this.quality = select.value as GraphicsPreset; this.callbacks.onQualityChange(this.quality); });
    label.appendChild(select); panel.appendChild(label);
    this.button(panel, 'Tiếp tục chơi', () => this.closeModal()).focus();
    this.button(panel, 'Bắt đầu lại tiết học…', () => this.confirmRestart(), true);
  }
  private quality: GraphicsPreset = 'Medium';
  private confirmRestart(): void {
    const panel = this.openModal('Bắt đầu lại tiết học?', 'Tiến độ tiết học đang lưu trên trình duyệt này sẽ được thay thế.');
    this.button(panel, 'Giữ tiến độ hiện tại', () => this.showPause()).focus();
    this.button(panel, 'Bắt đầu lại', () => { this.closeModal(); this.callbacks.onRestart(); }, true);
  }
  public showJournal(entries: string[]): void {
    const panel = this.openModal('Nhật ký tiết học', 'Những việc bạn thực sự đã làm sẽ là bằng chứng cho phần nhìn lại.');
    this.list(panel, entries.length ? entries : ['Chưa ghi nhận hành động. Hãy khám phá phòng giáo viên và chuẩn bị tiết học.']);
    this.button(panel, 'Trở lại trường', () => this.closeModal()).focus();
  }
  private list(panel: HTMLElement, entries: string[]): void {
    const list = document.createElement('ul'); list.className = 'sim-evidence';
    entries.forEach(text => { const item = document.createElement('li'); item.textContent = text; list.appendChild(item); }); panel.appendChild(list);
  }
  public showReport(report: LessonReportView): void {
    const panel = this.openModal(report.title, report.summary);
    this.list(panel, report.evidence.length ? report.evidence : ['Chưa đủ dữ liệu bằng chứng.']);
    if (report.missing.length) { const heading = document.createElement('h3'); heading.textContent = 'Điều cần tìm hiểu thêm'; panel.appendChild(heading); this.list(panel, report.missing); }
    const note = document.createElement('p'); note.className = 'sim-note'; note.textContent = 'Đây là phản hồi của một tiết chơi thử, không phải kết luận về năng lực hay mức độ phù hợp với nghề.'; panel.appendChild(note);
    this.button(panel, 'Trở lại trường', () => this.closeModal()).focus();
    this.button(panel, 'Thử một cách dạy khác…', () => this.confirmRestart(), true);
  }
}
