# Workflow governance

## Execution model (Spec Kitty)

Substantive product work and refactors are **planned, sequenced, and reviewed in Spec Kitty** — missions, work packages, step contracts, and the implement / review / `next` loop. Agents should use the repo’s Spec Kitty skills (`.claude/skills/spec-kitty-*`) as the default operating procedure.

Roadmap and prioritization live in **Spec Kitty** (and human judgment); this repo does not maintain GitHub milestones as a planning surface.

## Versioning model

Minoo and the Waaseyaa Framework version independently.

- **Framework versions** represent platform contract stability (ingestion envelope, schema registry, ACL substrate, operator diagnostics, CI gates).
- **Minoo versions** represent product feature maturity (entity domains, content authoring, knowledge features, UX stability).
- Minoo is a consumer of the framework. Minoo must always target a compatible framework version, but neither repo's version number constrains the other's.
- Both repos are pre-v1. Pre-v1 minor versions may increment indefinitely. v1.0 is cut only when contracts (framework) or product UX + content model (Minoo) are formally stable.

## Framework milestones (Waaseyaa)

High-level **framework** release targets (not Minoo GitHub milestones):

| Milestone | Description | Status |
|-----------|-------------|--------|
| v0.7 | SSR path templates stabilized; Admin SPA critical bugs resolved; app developer experience unblocked | Active |
| v0.8 | Default content type (core.note), boot enforcement, ACL baseline, CI versioning gates — platform contracts begin | Future |
| v0.9 | Ingestion envelope, schema registry, namespace rules, RBAC, telemetry, operator diagnostics, onboarding guardrails | Future |
| v0.10 | Feature flags, tenant migration plan — contract evolution and rollout safety finalized before v1.0 lock | Future |
| v1.0 | Platform contracts locked. Ingestion, schema registry, ACL, versioning, and CI — stable and semver-committed | Future |

## The five working rules

### 1. Spec Kitty tracks intent

Non-trivial work should be represented as a Spec Kitty mission or work package (or an explicit in-chat brief for small, single-file fixes).

### 2. Use the Spec Kitty control loop

Advance missions with runtime `next`, implement-review cycles, and mission review skills as configured. Merge discipline follows your team’s Spec Kitty procedures.

### 3. Codified context defines boundaries

`CLAUDE.md`, specialist skills, and MCP specs (`minoo_get_spec`, `waaseyaa_get_spec`) ground architecture and naming. See **Architectural Boundaries** in `CLAUDE.md`.

### 4. PRs describe outcomes

PR titles and bodies should summarize what merged and why. Optional links to Spec Kitty mission/WP or GitHub for traceability are fine when useful.

### 5. Read drift output when you run it

Session hooks may run `bin/check-milestones`. It prints **repository boundary** checks only (see below). Treat output as advisory.

## Drift detection (`bin/check-milestones`)

The script exits `0` always — warning surface, not a CI gate. It reports:

- No North Cloud classifier logic leaking into Minoo `src/`
- No Minoo-specific entity references in sibling `waaseyaa/packages/` (when that tree exists)
- Note on `indigenous-taxonomy` PHP package presence

These checks protect architectural boundaries.
