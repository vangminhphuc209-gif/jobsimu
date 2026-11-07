/**
 * game/src/ui/DialogueUI.ts
 * Modal dialogue interface displaying 10-part event options and trade-offs.
 */

import { GameEvent, GameAction } from '../core/types';
import { EventBus } from '../core/EventBus';

export class DialogueUI {
  private container: HTMLElement;
  private eventBus: EventBus;
  private currentEvent: GameEvent | null = null;
  private onActionSelectedCallback: ((action: GameAction) => void) | null = null;

  constructor() {
    this.eventBus = EventBus.getInstance();
    this.container = document.createElement('div');
    this.container.id = 'dialogue-modal';
    this.applyStyles();
    document.body.appendChild(this.container);
    this.hide();
  }

  public showEvent(event: GameEvent, onSelect: (action: GameAction) => void): void {
    this.currentEvent = event;
    this.onActionSelectedCallback = onSelect;
    this.render();
    this.container.style.display = 'block';
  }

  public hide(): void {
    this.container.style.display = 'none';
    this.currentEvent = null;
    this.onActionSelectedCallback = null;
  }

  private render(): void {
    if (!this.currentEvent) return;

    const ev = this.currentEvent;
    let actionsHtml = '';

    ev.actions.forEach((action, idx) => {
      actionsHtml += `
        <div class="dialogue-choice" data-action-idx="${idx}">
          <div class="choice-header">
            <span class="choice-num">[${idx + 1}]</span>
            <span class="choice-label">${action.label}</span>
            <span class="choice-badge ${action.type}">${action.type.toUpperCase()}</span>
          </div>
          <div class="choice-desc">${action.description}</div>
          <div class="choice-cost">⏱️ Chi phí thời gian: ${action.timeCostMinutes} phút</div>
        </div>
      `;
    });

    this.container.innerHTML = `
      <div class="dialogue-box">
        <div class="dialogue-title">${ev.context.objective}</div>
        <div class="dialogue-clue">👁️ <b>Manh mối nhận biết:</b> ${ev.sensory_clue.visualClue}</div>
        <div class="dialogue-tradeoff">⚖️ <b>Đánh đổi:</b> ${ev.trade_off}</div>
        
        <div class="dialogue-choices-list">
          ${actionsHtml}
        </div>
      </div>
    `;

    // Attach click listeners to choices
    const choiceEls = this.container.querySelectorAll('.dialogue-choice');
    choiceEls.forEach(el => {
      el.addEventListener('click', () => {
        const idx = parseInt(el.getAttribute('data-action-idx') || '0', 10);
        const selectedAction = ev.actions[idx];
        if (selectedAction && this.onActionSelectedCallback) {
          this.hide();
          this.onActionSelectedCallback(selectedAction);
        }
      });
    });
  }

  private applyStyles(): void {
    this.container.style.position = 'fixed';
    this.container.style.bottom = '40px';
    this.container.style.left = '50%';
    this.container.style.transform = 'translateX(-50%)';
    this.container.style.width = '90%';
    this.container.style.maxWidth = '780px';
    this.container.style.backgroundColor = 'rgba(15, 23, 42, 0.95)';
    this.container.style.border = '1px solid rgba(96, 165, 250, 0.4)';
    this.container.style.borderRadius = '16px';
    this.container.style.padding = '24px';
    this.container.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.6)';
    this.container.style.zIndex = '1000';
    this.container.style.fontFamily = 'system-ui, sans-serif';
    this.container.style.color = '#f8fafc';

    const styleTag = document.createElement('style');
    styleTag.textContent = `
      .dialogue-title { font-size: 1.25rem; font-weight: 700; color: #60a5fa; margin-bottom: 8px; }
      .dialogue-clue { font-size: 0.95rem; color: #fbbf24; margin-bottom: 6px; }
      .dialogue-tradeoff { font-size: 0.9rem; color: #94a3b8; margin-bottom: 16px; }
      .dialogue-choices-list { display: flex; flex-direction: column; gap: 10px; }
      .dialogue-choice {
        background: rgba(30, 41, 59, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 12px 16px;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
      }
      .dialogue-choice:hover {
        background: rgba(59, 130, 246, 0.25);
        border-color: #3b82f6;
        transform: translateY(-2px);
      }
      .choice-header { display: flex; align-items: center; gap: 8px; font-weight: 600; }
      .choice-num { color: #60a5fa; }
      .choice-badge { font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; font-weight: 700; }
      .choice-badge.physical { background: #059669; }
      .choice-badge.dialogue { background: #2563eb; }
      .choice-badge.investigate { background: #d97706; }
      .choice-desc { font-size: 0.9rem; color: #cbd5e1; margin-top: 4px; }
      .choice-cost { font-size: 0.8rem; color: #94a3b8; margin-top: 4px; }
    `;
    document.head.appendChild(styleTag);
  }
}
