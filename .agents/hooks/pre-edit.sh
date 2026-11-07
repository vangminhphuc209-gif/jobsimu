#!/usr/bin/env bash
# .agents/hooks/pre-edit.sh
# Blocks unauthorized edits to legacy PHP files (app/*, index.php, database/schema.sql)

TARGET_FILE="$1"

if [ -z "$TARGET_FILE" ]; then
    echo "Usage: pre-edit.sh <target-file-path>"
    exit 0
fi

PROTECTED_PATTERNS=("app/" "index.php" "database/schema.sql")

for pattern in "${PROTECTED_PATTERNS[@]}"; do
    if [[ "$TARGET_FILE" == *"$pattern"* ]]; then
        if [ "$ALLOW_LEGACY_EDIT" != "1" ]; then
            echo "❌ [PRE-EDIT HOOK BLOCKED] Target file '$TARGET_FILE' is protected under Architectural Invariant #1."
            echo "   Modifications to legacy PHP files require explicit Human Approval Gate #2."
            echo "   To override when authorized, set ALLOW_LEGACY_EDIT=1."
            exit 1
        else
            echo "⚠️ [PRE-EDIT HOOK OVERRIDE] Legacy edit authorized by ALLOW_LEGACY_EDIT=1."
        fi
    fi
done

exit 0
