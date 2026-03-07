# GitHub Workflow Codification Design

**Date:** 2026-03-07
**Repos:** waaseyaa/framework, waaseyaa/minoo

## Goal

Prevent drift between development activity and GitHub milestone/issue tracking. Every piece of work must start with an issue, every issue must belong to a milestone, and both Claude and contributors must be reminded of this at the start of every session.

## Components

### 1. Drift-check script (`bin/check-milestones`)

A bash script in each repo that queries GitHub and reports:
- Open issues with no milestone assigned (incomplete triage)
- Milestones with zero open issues (possibly stale)
- Clean confirmation if everything is assigned

Exits 0 always — this is a warning surface for Claude, not a CI gate.
Each repo gets its own script with its slug hardcoded.

### 2. SessionStart hook

Both repos get a `SessionStart` hook in `.claude/settings.json` that runs
`bin/check-milestones` at the start of every Claude session. `"show_output": true`
ensures Claude sees the full script output even when it spans multiple lines.
Claude reads the report and flags problems before any work begins.

### 3. `docs/specs/workflow.md`

A governance spec in each repo containing:
- The versioning model (framework = platform contracts, minoo = product maturity)
- Current milestone list with descriptions
- The 5 workflow rules (full text)
- Instructions for updating the spec when milestones change

Added to each CLAUDE.md orchestration table so Claude loads it when working on
any new feature or GitHub-adjacent task.

### 4. CLAUDE.md additions

A "GitHub Workflow" section added to both CLAUDE.md files summarizing the 5 rules
and pointing to `docs/specs/workflow.md` for the full governance model.

### 5. PR template (`.github/pull_request_template.md`)

Both repos get a PR template with a three-item checklist:
- Issue reference (`Closes #N`)
- Milestone assigned to the issue
- PR title includes issue number

## The 5 Workflow Rules

1. **All work begins with an issue.** No code is generated or written without an open issue. Claude must ask for the issue number before producing code. If no issue exists, Claude must propose creating one.
2. **Every issue belongs to a milestone.** Issues must be assigned to exactly one milestone. Unassigned issues are incomplete triage. Claude must prompt assignment if missing.
3. **Milestones define the roadmap.** Milestones are the authoritative plan. Codified context describes philosophy; milestones describe execution. Claude must align all suggestions with the active milestone structure.
4. **PRs must reference issues.** Every PR title must include an issue number. PRs without issue references should not be merged.
5. **Claude must check milestones before generating work.** Claude must read the milestone list and issue list before producing code, and align output with the current milestone.
