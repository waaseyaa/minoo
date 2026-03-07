# GitHub Workflow Governance

## Versioning Model

Minoo and the Waaseyaa Framework version independently.

- **Framework versions** represent platform contract stability (ingestion envelope, schema registry, ACL substrate, operator diagnostics, CI gates).
- **Minoo versions** represent product feature maturity (entity domains, content authoring, knowledge features, UX stability).
- Minoo is a consumer of the framework. Minoo must always target a compatible framework version, but neither repo's version number constrains the other's.
- Both repos are pre-v1. Pre-v1 minor versions may increment indefinitely. v1.0 is cut only when contracts (framework) or product UX + content model (Minoo) are formally stable.

## Minoo Milestones

| Milestone | Description | Status |
|-----------|-------------|--------|
| v0.2 | Foundation — 13 entity types, ingestion pipeline, search, SSR pages, codified context | Closed (2026-03-07) |
| v0.3 | Content authoring for all 5 entity domains via admin interface (depends on framework v0.7) | Active |
| v0.4 | Knowledge graph, cultural connections, richer language and teaching features | Future |
| v1.0 | Content model and UX stable — product ready for public launch | Future |

**Update this table whenever milestones are added, closed, or redescribed.**

## Framework Milestones

| Milestone | Description | Status |
|-----------|-------------|--------|
| v0.7 | SSR path templates stabilized; Admin SPA critical bugs resolved; app developer experience unblocked | Active |
| v0.8 | Default content type (core.note), boot enforcement, ACL baseline, CI versioning gates — platform contracts begin | Future |
| v0.9 | Ingestion envelope, schema registry, namespace rules, RBAC, telemetry, operator diagnostics, onboarding guardrails | Future |
| v0.10 | Feature flags, tenant migration plan — contract evolution and rollout safety finalized before v1.0 lock | Future |
| v1.0 | Platform contracts locked. Ingestion, schema registry, ACL, versioning, and CI — stable and semver-committed | Future |

## The 5 Workflow Rules

### 1. All work begins with an issue
No code is generated or written without an open GitHub issue. Claude must ask for the issue number before producing code. If no issue exists, Claude must propose creating one and assign it to the appropriate milestone before proceeding.

### 2. Every issue belongs to a milestone
Issues must be assigned to exactly one milestone. Unassigned issues represent incomplete triage. Claude must prompt milestone assignment if an issue lacks one. Use `bin/check-milestones` to surface unassigned issues at any time.

### 3. Milestones define the roadmap
Milestones are the authoritative plan for the repo. Codified context describes philosophy; milestones describe execution. Claude must align all suggestions with the active milestone structure. Do not invent new milestones without explicit discussion.

### 4. PRs must reference issues
Every PR title must include an issue number (e.g. `feat(#42): add search filters`). PRs without issue references should not be merged. Use the PR template checklist.

### 5. Claude reads milestones before generating work
At session start, `bin/check-milestones` runs automatically. Claude must read the report and flag any drift before beginning implementation work. Claude must also check which milestone is active and align output to it.

## Drift Detection

`bin/check-milestones` runs at every Claude session start via the SessionStart hook. It reports:
- Open issues with no milestone (incomplete triage)
- Open milestones with no open issues (possibly stale)

The script exits 0 always. Output is a warning surface for Claude and contributors, not a CI gate.
