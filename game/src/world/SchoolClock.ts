/**
 * game/src/world/SchoolClock.ts
 * Manages school timeline from 06:45 to 16:45 and period transitions.
 */

import { SchoolTime, CheckpointId } from '../core/types';
import { EventBus } from '../core/EventBus';

export class SchoolClock {
  private currentHour: number = 6;
  private currentMinute: number = 45;
  private isPaused: boolean = false;
  private timeScale: number = 1.0; // 1 real second = X game minutes (default real-time testing)
  private eventBus: EventBus;
  private timerId: number | null = null;

  constructor() {
    this.eventBus = EventBus.getInstance();
  }

  public setTime(hour: number, minute: number): void {
    this.currentHour = Math.min(Math.max(hour, 6), 17);
    this.currentMinute = Math.min(Math.max(minute, 0), 59);
    this.emitTimeUpdate();
  }

  public getTime(): SchoolTime {
    return {
      hour: this.currentHour,
      minute: this.currentMinute,
      timeString: this.formatTimeString(this.currentHour, this.currentMinute),
      periodName: this.getPeriodName(this.currentHour, this.currentMinute)
    };
  }

  public advanceMinutes(minutes: number): void {
    this.currentMinute += minutes;
    while (this.currentMinute >= 60) {
      this.currentMinute -= 60;
      this.currentHour += 1;
    }
    if (this.currentHour > 17) {
      this.currentHour = 17;
      this.currentMinute = 0;
    }
    this.emitTimeUpdate();
  }

  public pause(): void {
    this.isPaused = true;
  }

  public resume(): void {
    this.isPaused = false;
  }

  private formatTimeString(h: number, m: number): string {
    const hh = h < 10 ? `0${h}` : `${h}`;
    const mm = m < 10 ? `0${m}` : `${m}`;
    return `${hh}:${mm}`;
  }

  private getPeriodName(h: number, m: number): string {
    const totalMinutes = h * 60 + m;
    if (totalMinutes < 7 * 60) return 'Đến trường & chuẩn bị đầu ngày';
    if (totalMinutes < 7 * 60 + 15) return 'Phòng Hội đồng giáo viên';
    if (totalMinutes < 7 * 60 + 30) return 'Di chuyển lên lớp & Hành lang';
    if (totalMinutes < 8 * 60 + 15) return 'Tiết 1: Giảng bài Biến đổi khí hậu';
    if (totalMinutes < 8 * 60 + 30) return 'Giờ ra chơi & Tương tác học sinh';
    if (totalMinutes < 9 * 60 + 15) return 'Tiết 2 & Hoạt động chuyên môn';
    if (totalMinutes < 11 * 60 + 30) return 'Xử lý tình huống phát sinh';
    if (totalMinutes < 13 * 60 + 30) return 'Nghỉ trưa & Trao đổi đồng nghiệp';
    if (totalMinutes < 15 * 60 + 30) return 'Gặp phụ huynh & Tư vấn học sinh';
    return 'Cuối ngày: Tổng kết & Tự phản tư';
  }

  private emitTimeUpdate(): void {
    const time = this.getTime();
    this.eventBus.emit('time:updated', time);

    // Update DOM clock if element exists
    if (typeof document !== 'undefined') {
      const clockEl = document.getElementById('clock-display');
      const periodEl = document.getElementById('period-display');
      if (clockEl) clockEl.textContent = `⏰ ${time.timeString}`;
      if (periodEl) periodEl.textContent = `(${time.periodName})`;
    }
  }
}
