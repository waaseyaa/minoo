#!/usr/bin/env bash
# Drift detector: maps recent file changes to affected specs and skills.
# Usage: ./tools/drift-detector.sh [number_of_commits]

set -euo pipefail

COMMITS="${1:-5}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

declare -A SPEC_MAP=(
  ["src/Entity/"]="docs/specs/entity-model.md"
  ["src/Provider/"]="docs/specs/entity-model.md"
  ["src/Access/"]="docs/specs/entity-model.md"
  ["src/Seed/"]="docs/specs/entity-model.md"
  ["tests/Minoo/"]="docs/specs/entity-model.md"
)

declare -A SKILL_MAP=(
  ["src/Entity/"]="skills/minoo/SKILL.md"
  ["src/Provider/"]="skills/minoo/SKILL.md"
  ["src/Access/"]="skills/minoo/SKILL.md"
  ["src/Seed/"]="skills/minoo/SKILL.md"
  ["tests/Minoo/"]="skills/minoo/SKILL.md"
)

echo "Checking last $COMMITS commits for drift..."
echo

changed_files=$(cd "$ROOT" && git diff --name-only "HEAD~${COMMITS}" HEAD 2>/dev/null || git diff --name-only HEAD 2>/dev/null)

if [ -z "$changed_files" ]; then
  echo "No changes found."
  exit 0
fi

declare -A affected_specs
declare -A affected_skills

while IFS= read -r file; do
  for pattern in "${!SPEC_MAP[@]}"; do
    if [[ "$file" == "$pattern"* ]]; then
      spec="${SPEC_MAP[$pattern]}"
      affected_specs["$spec"]+="  $file"$'\n'
    fi
  done
  for pattern in "${!SKILL_MAP[@]}"; do
    if [[ "$file" == "$pattern"* ]]; then
      skill="${SKILL_MAP[$pattern]}"
      affected_skills["$skill"]+="  $file"$'\n'
    fi
  done
done <<< "$changed_files"

if [ ${#affected_specs[@]} -eq 0 ] && [ ${#affected_skills[@]} -eq 0 ]; then
  echo "No specs or skills affected by recent changes."
  exit 0
fi

echo "Affected specs:"
for spec in "${!affected_specs[@]}"; do
  if [ -f "$ROOT/$spec" ]; then
    last_mod=$(cd "$ROOT" && git log -1 --format="%cr" -- "$spec" 2>/dev/null || echo "unknown")
    echo "  $spec (last updated: $last_mod)"
  else
    echo "  $spec (MISSING — needs creation)"
  fi
  echo "${affected_specs[$spec]}"
done

echo "Affected skills:"
for skill in "${!affected_skills[@]}"; do
  if [ -f "$ROOT/$skill" ]; then
    last_mod=$(cd "$ROOT" && git log -1 --format="%cr" -- "$skill" 2>/dev/null || echo "unknown")
    echo "  $skill (last updated: $last_mod)"
  else
    echo "  $skill (MISSING — needs creation)"
  fi
  echo "${affected_skills[$skill]}"
done
