/**
 * game/src/ui/CareerReportUI.ts
 * Evidence-based Career Competency Report screen.
 * Strictly adheres to Invariant #5 (No single score metric).
 */

import { CareerCompetencyReport } from '../core/types';

export class CareerReportUI {
  private container: HTMLElement;

  constructor() {
    this.container = document.createElement('div');
    this.container.id = 'career-report-overlay';
    this.applyStyles();
    document.body.appendChild(this.container);
    this.container.style.display = 'none';
  }

  public showReport(report: any): void {
    const comps = report.competencies || {};
    let compCards = '';

    for (const [key, comp] of Object.entries(comps) as any) {
      let evidenceList = '';
      if (Array.isArray(comp.evidence) && comp.evidence.length > 0) {
        evidenceList = comp.evidence.map((e: string) => `<li>${e}</li>`).join('');
      } else {
        evidenceList = '<li><i>Chưa có bằng chứng hành vi cụ thể được ghi nhận trong phiên này.</i></li>';
      }

      compCards += `
        <div class="competency-card">
          <div class="comp-header">
            <span class="comp-title">${comp.dimension || key}</span>
            <span class="comp-level">${comp.level || 'Đang phát triển'}</span>
          </div>
          <ul class="comp-evidence">
            ${evidenceList}
          </ul>
        </div>
      `;
    }

    this.container.innerHTML = `
      <div class="report-box">
        <div class="report-header">
          <h2>BÁO CÁO TRẢI NGHIỆM NGHỀ GIÁO VIÊN THCS</h2>
          <div class="report-subtitle">Đánh giá đa chiều dựa trên bằng chứng hành vi thực tế (Ngày 1)</div>
          <div class="report-status"><b>Độ đầy đủ dữ liệu:</b> ${report.dataSufficiency || 'Đầy đủ'}</div>
        </div>

        <div class="competencies-grid">
          ${compCards}
        </div>

        <div class="reflection-summary">
          <h3>Ghi chú Sư phạm & Tự Phản tư</h3>
          <p>${report.pedagogicalReflection?.summaryComment || 'Bạn đã hoàn thành phiên mô phỏng nghề giáo.'}</p>
        </div>

        <div class="report-actions">
          <button class="btn-primary" id="btn-close-report">Tiếp tục / Hoàn tất</button>
        </div>
      </div>
    `;

    document.getElementById('btn-close-report')?.addEventListener('click', () => {
      this.container.style.display = 'none';
    });

    this.container.style.display = 'flex';
  }

  private applyStyles(): void {
    this.container.style.position = 'fixed';
    this.container.style.top = '0';
    this.container.style.left = '0';
    this.container.style.width = '100vw';
    this.container.style.height = '100vh';
    this.container.style.backgroundColor = 'rgba(11, 15, 25, 0.95)';
    this.container.style.backdropFilter = 'blur(10px)';
    this.container.style.zIndex = '2000';
    this.container.style.display = 'flex';
    this.container.style.justifyContent = 'center';
    this.container.style.alignItems = 'center';
    this.container.style.padding = '20px';
    this.container.style.boxSizing = 'border-box';
    this.container.style.overflowY = 'auto';

    const style = document.createElement('style');
    style.textContent = `
      .report-box {
        max-width: 900px;
        width: 100%;
        background: #1e293b;
        border: 1px solid #3b82f6;
        border-radius: 20px;
        padding: 32px;
        color: #f8fafc;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
      }
      .report-header h2 { margin: 0 0 8px 0; color: #60a5fa; font-size: 1.75rem; text-align: center; }
      .report-subtitle { text-align: center; color: #94a3b8; font-size: 0.95rem; margin-bottom: 8px; }
      .report-status { text-align: center; color: #38bdf8; font-size: 0.9rem; margin-bottom: 24px; }
      .competencies-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
      @media (max-width: 768px) { .competencies-grid { grid-template-columns: 1fr; } }
      .competency-card { background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 16px; }
      .comp-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
      .comp-title { font-weight: 700; color: #93c5fd; font-size: 0.95rem; }
      .comp-level { font-size: 0.8rem; background: #1e3a8a; color: #bfdbfe; padding: 2px 8px; border-radius: 9999px; }
      .comp-evidence { margin: 0; padding-left: 20px; font-size: 0.85rem; color: #cbd5e1; }
      .comp-evidence li { margin-bottom: 4px; }
      .reflection-summary { background: #0f172a; border-radius: 12px; padding: 16px; margin-bottom: 24px; }
      .reflection-summary h3 { margin: 0 0 8px 0; font-size: 1.1rem; color: #a78bfa; }
      .reflection-summary p { margin: 0; font-size: 0.9rem; line-height: 1.5; color: #cbd5e1; }
      .report-actions { display: flex; justify-content: center; }
      .btn-primary { background: #3b82f6; color: white; border: none; padding: 12px 32px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: background 0.2s; }
      .btn-primary:hover { background: #2563eb; }
    `;
    document.head.appendChild(style);
  }
}
