/**
 * game/src/core/EventBus.ts
 * Strongly-typed publish-subscribe event bus for JOBSIMU 3D.
 */

type EventHandler<T = any> = (payload: T) => void;

export class EventBus {
  private static instance: EventBus;
  private listeners: Map<string, Set<EventHandler>> = new Map();

  private constructor() {}

  public static getInstance(): EventBus {
    if (!EventBus.instance) {
      EventBus.instance = new EventBus();
    }
    return EventBus.instance;
  }

  public on<T = any>(eventName: string, handler: EventHandler<T>): () => void {
    if (!this.listeners.has(eventName)) {
      this.listeners.set(eventName, new Set());
    }
    this.listeners.get(eventName)!.add(handler);

    return () => this.off(eventName, handler);
  }

  public off<T = any>(eventName: string, handler: EventHandler<T>): void {
    const handlers = this.listeners.get(eventName);
    if (handlers) {
      handlers.delete(handler);
      if (handlers.size === 0) {
        this.listeners.delete(eventName);
      }
    }
  }

  public emit<T = any>(eventName: string, payload: T): void {
    const handlers = this.listeners.get(eventName);
    if (handlers) {
      handlers.forEach(handler => {
        try {
          handler(payload);
        } catch (err) {
          console.error(`[EventBus] Error in handler for event "${eventName}":`, err);
        }
      });
    }
  }

  public clear(): void {
    this.listeners.clear();
  }
}
