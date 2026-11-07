import { describe, it, expect } from 'vitest';
import fs from 'fs';
import path from 'path';

describe('10-Part Open Event Schema Tests', () => {
  const eventsDir = path.resolve(__dirname, '../content/events');
  const files = fs.readdirSync(eventsDir).filter(f => f.endsWith('.json'));

  it('should have loaded at least 5 core events', () => {
    expect(files.length).toBeGreaterThanOrEqual(5);
  });

  files.forEach(file => {
    it(`validates ${file} conforms to 10-part open event formula`, () => {
      const raw = fs.readFileSync(path.join(eventsDir, file), 'utf8');
      
      // Architectural Invariant 4: No eval strings in JSON
      expect(raw).not.toContain('eval(');
      expect(raw).not.toContain('new Function');

      const ev = JSON.parse(raw);

      // 1. Context & Objective
      expect(ev.id).toBeDefined();
      expect(ev.context).toBeDefined();
      expect(ev.context.objective).toBeDefined();
      expect(ev.context.location).toBeDefined();

      // 2. Trigger
      expect(ev.trigger).toBeDefined();
      expect(ev.trigger.checkpointId).toBeDefined();

      // 3. Sensory Clue
      expect(ev.sensory_clue).toBeDefined();
      expect(ev.sensory_clue.visualClue).toBeDefined();

      // 4. Hidden Info
      expect(ev.hidden_info).toBeDefined();

      // 5. Actions >= 3
      expect(Array.isArray(ev.actions)).toBe(true);
      expect(ev.actions.length).toBeGreaterThanOrEqual(3);

      ev.actions.forEach((a: any) => {
        expect(a.id).toBeDefined();
        expect(a.label).toBeDefined();
        expect(a.type).toBeDefined();
        expect(['physical', 'dialogue', 'investigate', 'creative']).toContain(a.type);
        expect(typeof a.timeCostMinutes).toBe('number');
      });

      // 6. Explicit Trade-off
      expect(ev.trade_off).toBeDefined();

      // 7. Immediate Feedback
      expect(ev.immediate_feedback).toBeDefined();

      // 8. Delayed Consequences
      expect(ev.delayed_consequences).toBeDefined();

      // 9. Anti-softlock
      expect(ev.anti_softlock).toBeDefined();
      expect(ev.anti_softlock.fallbackActionId).toBeDefined();

      // 10. Evidence Telemetry
      expect(ev.evidence_telemetry).toBeDefined();
      expect(Array.isArray(ev.evidence_telemetry.competenciesAffected)).toBe(true);
    });
  });
});
