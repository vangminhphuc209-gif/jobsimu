#!/usr/bin/env bash
# .agents/commands/init-worktrees.sh
# Spawns isolated Git Worktrees for each of the 5 subagents

set -e

ROOT_DIR="/home/minhphuc/jobsimu"
cd "$ROOT_DIR"

echo "🚀 [INIT-WORKTREES] Initializing Git Worktrees for 5 Subagents..."

# Array of Worktree Name : Branch Name
WORKTREES=(
    "wt-backend:feat/backend-api"
    "wt-3d:feat/engine-3d"
    "wt-narrative:feat/gameplay-narrative"
    "wt-npc-ui:feat/npc-ui"
    "wt-qa:feat/qa-eval"
)

for item in "${WORKTREES[@]}"; do
    IFS=":" read -r WT_NAME BRANCH_NAME <<< "$item"
    WT_PATH="$ROOT_DIR/$WT_NAME"

    # Check if branch exists
    if ! git show-ref --verify --quiet "refs/heads/$BRANCH_NAME"; then
        echo "🌿 Creating branch '$BRANCH_NAME'..."
        git branch "$BRANCH_NAME" main
    fi

    # Check if worktree directory exists
    if [ ! -d "$WT_PATH" ]; then
        echo "📂 Spawning worktree '$WT_NAME' on branch '$BRANCH_NAME'..."
        git worktree add "$WT_PATH" "$BRANCH_NAME"
    else
        echo "ℹ️ Worktree '$WT_NAME' already exists."
    fi
done

echo "✅ [INIT-WORKTREES] All 5 subagent worktrees are ready!"
git worktree list
