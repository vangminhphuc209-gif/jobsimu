#!/usr/bin/env bash
# .agents/hooks/pre-commit.sh
# Scans staged files for sensitive secrets, credentials, or misplaced node_modules

set -e

echo "🔍 [PRE-COMMIT HOOK] Scanning staged files..."

STAGED_FILES=$(git diff --cached --name-only)

if [ -z "$STAGED_FILES" ]; then
    echo "✅ [PRE-COMMIT HOOK] No staged files to scan."
    exit 0
fi

# 1. Block node_modules or build artifacts
for file in $STAGED_FILES; do
    if [[ "$file" == *"node_modules/"* ]] || [[ "$file" == *"dist/"* ]]; then
        echo "❌ [PRE-COMMIT BLOCKED] Disallowed artifact '$file' is staged!"
        echo "   node_modules and dist directories must never be committed."
        exit 1
    fi
    if [[ "$file" == *".env"* ]] && [[ "$file" != *".env.example"* ]]; then
        echo "❌ [PRE-COMMIT BLOCKED] Environment file '$file' is staged!"
        exit 1
    fi
    if [[ "$file" == *"config.php"* ]] && [[ "$file" != *"config.example.php"* ]]; then
        echo "❌ [PRE-COMMIT BLOCKED] Production config file '$file' is staged!"
        exit 1
    fi
done

# 2. Check for leaked secret patterns in diff
SECRET_PATTERNS=("G7!mQ2" "BEGIN PRIVATE KEY" "AWS_SECRET_ACCESS_KEY" "ghp_[A-Za-z0-9_]{36}")

for pattern in "${SECRET_PATTERNS[@]}"; do
    if git diff --cached | grep -E "$pattern" > /dev/null; then
        echo "❌ [PRE-COMMIT BLOCKED] Detected sensitive pattern matching '$pattern' in staged changes!"
        exit 1
    fi
done

echo "✅ [PRE-COMMIT HOOK] All security scans passed."
exit 0
