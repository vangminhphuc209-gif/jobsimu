#!/usr/bin/env bash
# .agents/hooks/post-edit.sh
# Validates edited files:
# - .php: php -l syntax check
# - .ts / .tsx: tsc --noEmit check (if game/ exists and npm packages installed)
# - .json in game/content/: schema syntax check

EDITED_FILE="$1"

if [ -z "$EDITED_FILE" ]; then
    exit 0
fi

if [[ "$EDITED_FILE" == *.php ]]; then
    echo "🔍 [POST-EDIT HOOK] Validating PHP syntax for '$EDITED_FILE'..."
    if ! php -l "$EDITED_FILE"; then
        echo "❌ [POST-EDIT HOOK ERROR] PHP syntax check failed for '$EDITED_FILE'!"
        exit 1
    fi
    echo "✅ [POST-EDIT HOOK] PHP syntax valid."
fi

if [[ "$EDITED_FILE" == *.ts ]] || [[ "$EDITED_FILE" == *.tsx ]]; then
    echo "🔍 [POST-EDIT HOOK] Checking TypeScript file '$EDITED_FILE'..."
    if [ -f "/home/minhphuc/jobsimu/game/package.json" ] && [ -d "/home/minhphuc/jobsimu/game/node_modules" ]; then
        cd /home/minhphuc/jobsimu/game && npm run typecheck
    fi
fi

if [[ "$EDITED_FILE" == *"/game/content/"*.json ]]; then
    echo "🔍 [POST-EDIT HOOK] Validating JSON event file '$EDITED_FILE'..."
    node -e "
        const fs = require('fs');
        try {
            const data = JSON.parse(fs.readFileSync(process.argv[1], 'utf8'));
            if (data.id && data.context && (!Array.isArray(data.actions) || data.actions.length < 3)) {
                console.warn('⚠️ [POST-EDIT WARNING] Event ' + data.id + ' has fewer than 3 actions! Violates 10-part formula.');
            }
            console.log('✅ [POST-EDIT HOOK] JSON valid for: ' + (data.id || 'content'));
        } catch(e) {
            console.error('❌ [POST-EDIT HOOK ERROR] Invalid JSON in ' + process.argv[1] + ':', e.message);
            process.exit(1);
        }
    " "$EDITED_FILE"
fi

exit 0
