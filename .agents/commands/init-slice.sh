#!/usr/bin/env bash
# .agents/commands/init-slice.sh
# Scaffolds game/ directory and api/game/ endpoints + database migrations

set -e

ROOT_DIR="/home/minhphuc/jobsimu"
cd "$ROOT_DIR"

echo "📦 [INIT-SLICE] Scaffolding JOBSIMU 3D directories..."

# 1. Create directory structure
mkdir -p api/game
mkdir -p database/migrations
mkdir -p game/public/assets/audio
mkdir -p game/public/assets/models
mkdir -p game/src/core
mkdir -p game/src/player
mkdir -p game/src/world
mkdir -p game/src/narrative
mkdir -p game/src/systems
mkdir -p game/src/npc
mkdir -p game/src/ui
mkdir -p game/src/api
mkdir -p game/content/characters
mkdir -p game/content/days
mkdir -p game/content/events
mkdir -p game/content/dialogues
mkdir -p game/tests

echo "✅ [INIT-SLICE] Folder structures created successfully."
