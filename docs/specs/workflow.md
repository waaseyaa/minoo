# Workflow governance (anchor-issue + design-first)

<!-- Spec reviewed 2026-07-16 — Spec Kitty retirement: governance rewritten around GitHub anchor issues and the design-first flow, converging on waaseyaa's 2026-07-06 model. Historical mission artifacts remain read-only under kitty-specs/. -->

## Execution model (design-first)

**Planning and execution** for substantive work follow the **design-first flow**: brainstorm → design/spec in `docs/specs/` → written plan → TDD implementation → code review → verification. Multi-PR efforts are anchored by a **GitHub anchor issue** that records scope, work-package breakdown, and descope decisions; every PR in the effort references it. **`docs/specs/`** remains the contract layer agents read (directly or via the `minoo_*` MCP tools).

**GitHub** is the execution and visibility surface: issues, pull requests, Actions CI. Roadmap and prioritization live in anchor issues and human judgment; this repo does not maintain GitHub milestones as a planning surface.

> **Spec Kitty is retired** (2026-07, matching waaseyaa's 2026-07-06 retirement). Do not run `spec-kitty` commands or consult `.kittify/` state (the directory is removed). Historical mission artifacts are preserved read-only under `kitty-specs/`.

## The working rules

### 1. Substantive work begins with a design

Do not drive multi-step implementation from a blank prompt. Spec in `docs/specs/` first, then a written plan, then TDD implementation. Multi-PR efforts open a **GitHub anchor issue** recording intent, work-package breakdown, and decisions (descopes and deferrals land as issue comments). Small, single-file fixes may follow a direct user brief.

### 2. GitHub issues are lightweight

Not every change needs an issue — a single self-contained PR may stand alone if its body explains itself. When filed, issues are pure tracking with no enforced milestone or taxonomy. Anchor issues are the execution map for multi-PR efforts.

### 3. PRs must be traceable

Every PR in an anchored effort links what it delivers: `Closes #N` for a complete deliverable, `Part of #N` for one PR in the effort, with `#N` in the title (e.g. `feat(#919): …`). Each substantive PR also adds an entry under `[Unreleased]` in `CHANGELOG.md`.

### 4. Read context before generating work

At session start under an ongoing effort, read the anchor issue (including its comment trail — descopes and deferrals live there), `CLAUDE.md`, and the relevant `docs/specs/` contracts before generating work.

## Versioning model

Minoo and the Waaseyaa Framework version independently.

- **Framework versions** represent platform contract stability (ingestion envelope, schema registry, ACL substrate, operator diagnostics, CI gates).
- **Minoo versions** represent product feature maturity (entity domains, content authoring, knowledge features, UX stability).
- Minoo is a consumer of the framework. Minoo must always target a compatible framework version, but neither repo's version number constrains the other's.
- Minoo is pre-v1 in product terms; its `1.0.x` changelog line predates the 2026-06 relaunch. Framework release targets and milestones are documented on the framework side (`waaseyaa/docs/specs/workflow.md`) — this repo does not mirror them.

## Drift detection (`bin/check-milestones`)

The script exits `0` always — warning surface, not a CI gate (script name is historical; GitHub milestone checks were removed). It reports:

- No North Cloud classifier logic leaking into Minoo `src/`
- No Minoo-specific entity references in sibling `waaseyaa/packages/` (when that tree exists)
- Note on `indigenous-taxonomy` PHP package presence

These checks protect architectural boundaries.
