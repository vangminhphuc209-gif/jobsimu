#!/usr/bin/env node
/**
 * .agents/commands/compile-event.cjs
 * Validates and compiles game events using the 10-Part Open Event Formula.
 * Usage: node .agents/commands/compile-event.cjs [EVENT_ID|all]
 */

const fs = require('fs');
const path = require('path');

const EVENTS_DIR = path.join(__dirname, '../../game/content/events');
const eventId = process.argv[2] || 'all';

console.log(`🔍 [COMPILE-EVENT] Validating event(s): ${eventId}...`);

if (!fs.existsSync(EVENTS_DIR)) {
    console.error(`❌ Events directory does not exist yet: ${EVENTS_DIR}`);
    process.exit(1);
}

const REQUIRED_FIELDS = [
    'id',
    'context',
    'trigger',
    'sensory_clue',
    'hidden_info',
    'actions',
    'trade_off',
    'immediate_feedback',
    'delayed_consequences',
    'anti_softlock',
    'evidence_telemetry'
];

const files = fs.readdirSync(EVENTS_DIR).filter(f => f.endsWith('.json'));

if (files.length === 0) {
    console.log('ℹ️ No event JSON files found yet in game/content/events/');
    process.exit(0);
}

let errors = 0;
let validated = 0;

for (const file of files) {
    const filePath = path.join(EVENTS_DIR, file);
    try {
        const event = JSON.parse(fs.readFileSync(filePath, 'utf8'));
        if (eventId !== 'all' && event.id !== eventId && !file.includes(eventId)) {
            continue;
        }

        console.log(`\n📄 Checking ${file} (ID: ${event.id})...`);
        const missing = REQUIRED_FIELDS.filter(field => event[field] === undefined);

        if (missing.length > 0) {
            console.error(`❌ [FAIL] Missing 10-part formula fields in ${file}: ${missing.join(', ')}`);
            errors++;
            continue;
        }

        // Verify >= 3 diverse action types
        if (!Array.isArray(event.actions) || event.actions.length < 3) {
            console.error(`❌ [FAIL] Event ${event.id} must provide >= 3 independent actions! Found: ${event.actions ? event.actions.length : 0}`);
            errors++;
            continue;
        }

        // Verify action types
        const actionTypes = new Set(event.actions.map(a => a.type));
        if (actionTypes.size < 2) {
            console.warn(`⚠️ [WARN] Event ${event.id} has low action type diversity (${Array.from(actionTypes).join(', ')}).`);
        }

        // Verify no eval-like strings in trigger / conditions
        const serialized = JSON.stringify(event);
        if (serialized.includes('eval(') || serialized.includes('new Function')) {
            console.error(`❌ [CRITICAL] Event ${event.id} contains banned eval() strings!`);
            errors++;
            continue;
        }

        console.log(`✅ [PASS] Event ${event.id} satisfies the 10-part open event formula.`);
        validated++;
    } catch (e) {
        console.error(`❌ [ERROR] Failed to parse ${file}: ${e.message}`);
        errors++;
    }
}

console.log(`\n🏁 Summary: ${validated} event(s) validated, ${errors} error(s).`);
process.exit(errors > 0 ? 1 : 0);
