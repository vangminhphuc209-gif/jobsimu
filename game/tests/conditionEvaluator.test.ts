import { describe, it, expect } from 'vitest';
import { ConditionEvaluator, ConditionGroup, ConditionRule } from '../src/narrative/ConditionEvaluator';

describe('ConditionEvaluator', () => {
  it('should return true for undefined or null condition', () => {
    expect(ConditionEvaluator.evaluate(null, {})).toBe(true);
    expect(ConditionEvaluator.evaluate(undefined, {})).toBe(true);
  });

  it('should evaluate simple key-value equality shorthand', () => {
    const context = { minh_trust: 3, helped_quan: true, location: 'StaffRoom' };
    expect(ConditionEvaluator.evaluate({ minh_trust: 3 }, context)).toBe(true);
    expect(ConditionEvaluator.evaluate({ minh_trust: 1 }, context)).toBe(false);
    expect(ConditionEvaluator.evaluate({ helped_quan: true, location: 'StaffRoom' }, context)).toBe(true);
  });

  it('should evaluate comparison operators: >, >=, <, <=, ==, !=', () => {
    const context = { trust: 4, score: 10 };

    const ruleGt: ConditionRule = { field: 'trust', operator: '>', value: 2 };
    expect(ConditionEvaluator.evaluate(ruleGt, context)).toBe(true);

    const ruleLt: ConditionRule = { field: 'trust', operator: '<', value: 2 };
    expect(ConditionEvaluator.evaluate(ruleLt, context)).toBe(false);

    const ruleEq: ConditionRule = { field: 'score', operator: '==', value: 10 };
    expect(ConditionEvaluator.evaluate(ruleEq, context)).toBe(true);
  });

  it('should evaluate AND / OR condition groups correctly', () => {
    const context = { minh_trust: 2, prep_ready: true, energy: 50 };

    const andGroup: ConditionGroup = {
      logic: 'AND',
      rules: [
        { field: 'minh_trust', operator: '>=', value: 2 },
        { field: 'prep_ready', operator: '==', value: true }
      ]
    };
    expect(ConditionEvaluator.evaluate(andGroup, context)).toBe(true);

    const orGroup: ConditionGroup = {
      logic: 'OR',
      rules: [
        { field: 'energy', operator: '<', value: 20 },
        { field: 'prep_ready', operator: '==', value: true }
      ]
    };
    expect(ConditionEvaluator.evaluate(orGroup, context)).toBe(true);
  });
});
