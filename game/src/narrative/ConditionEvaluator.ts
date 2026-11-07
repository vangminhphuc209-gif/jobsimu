/**
 * game/src/narrative/ConditionEvaluator.ts
 * Strongly-typed, AST-based condition evaluator.
 * STRICT ARCHITECTURAL INVARIANT #4: Absolutely no eval() or new Function().
 */

export type ComparisonOperator = '==' | '!=' | '>' | '>=' | '<' | '<=' | 'contains' | 'in';

export interface ConditionRule {
  field: string;
  operator: ComparisonOperator;
  value: any;
}

export interface ConditionGroup {
  logic: 'AND' | 'OR';
  rules: (ConditionRule | ConditionGroup)[];
}

export class ConditionEvaluator {
  /**
   * Safely evaluates whether a state context satisfies a condition rule or group.
   */
  public static evaluate(
    condition: ConditionRule | ConditionGroup | Record<string, any> | undefined | null,
    context: Record<string, any>
  ): boolean {
    if (!condition) {
      return true; // No conditions mean unconditionally available
    }

    // Direct key-value match shorthand (e.g. { minh_trust: 2, helped_quan: true })
    if (!('logic' in condition) && !('operator' in condition)) {
      for (const [key, expectedValue] of Object.entries(condition)) {
        const actualValue = this.resolveField(key, context);
        if (actualValue !== expectedValue) {
          return false;
        }
      }
      return true;
    }

    // ConditionGroup (AND / OR)
    if ('logic' in condition) {
      const group = condition as ConditionGroup;
      if (group.logic === 'AND') {
        return group.rules.every(r => this.evaluate(r, context));
      } else if (group.logic === 'OR') {
        return group.rules.some(r => this.evaluate(r, context));
      }
      return false;
    }

    // ConditionRule
    const rule = condition as ConditionRule;
    const actual = this.resolveField(rule.field, context);
    return this.compare(actual, rule.operator, rule.value);
  }

  private static resolveField(path: string, obj: Record<string, any>): any {
    if (!path) return undefined;
    const parts = path.split('.');
    let current = obj;
    for (const part of parts) {
      if (current === null || current === undefined) {
        return undefined;
      }
      current = current[part];
    }
    return current;
  }

  private static compare(actual: any, op: ComparisonOperator, expected: any): boolean {
    switch (op) {
      case '==':
        return actual === expected;
      case '!=':
        return actual !== expected;
      case '>':
        return typeof actual === 'number' && typeof expected === 'number' && actual > expected;
      case '>=':
        return typeof actual === 'number' && typeof expected === 'number' && actual >= expected;
      case '<':
        return typeof actual === 'number' && typeof expected === 'number' && actual < expected;
      case '<=':
        return typeof actual === 'number' && typeof expected === 'number' && actual <= expected;
      case 'contains':
        if (typeof actual === 'string' && typeof expected === 'string') {
          return actual.includes(expected);
        }
        if (Array.isArray(actual)) {
          return actual.includes(expected);
        }
        return false;
      case 'in':
        return Array.isArray(expected) && expected.includes(actual);
      default:
        console.warn(`[ConditionEvaluator] Unknown comparison operator: ${op}`);
        return false;
    }
  }
}
