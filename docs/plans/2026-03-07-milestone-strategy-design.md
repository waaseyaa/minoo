# Milestone Strategy Design: Waaseyaa Framework + Minoo

**Date:** 2026-03-07
**Repos:** waaseyaa/framework, waaseyaa/minoo

## Context

Framework and Minoo are in a parent/child relationship — the framework is the platform, Minoo is the flagship consumer app. They version independently, but Minoo's milestones are constrained by the framework's release sequence.

The `v1.x` labels seen in older framework issues (e.g. "v1.6: Multi-Source Ingestion") were sprint identifiers, not semantic versions. No official v1.0 was ever tagged. The framework is pre-v1.

## Versioning Model

**Framework and Minoo version independently.**
- Framework versions represent platform contract stability.
- Minoo versions represent product feature maturity.
- Minoo must always target a compatible framework version, but neither repo's version number constrains the other's.

The framework is currently at `v0.7.0` (starting point, crediting significant shipped work: ingestion, SSR, search, admin SPA).

The framework remains pre-v1 until platform defaults, versioning rules, schema registry behavior, and CI enforcement are complete. Pre-v1 minor versions may increment indefinitely (v0.7 → v0.8 → v0.9 → v0.10 → …). There is no upper bound. v1.0 will be cut only when platform contracts are formally locked.

## Milestone Due Dates

**Framework milestones: no due dates.**
The framework is architectural, contract-defining, and research-driven. Artificial deadlines create misleading slip signals. Platforms need stability, not calendar pressure.

**Minoo milestones: use due dates.**
Minoo is user-facing and feature-driven. Due dates support release planning and communication.

## Framework Milestones (waaseyaa/framework)

| Milestone | Description | Issues |
|-----------|-------------|--------|
| v0.7 | SSR path templates stabilized; Admin SPA critical bugs resolved; app developer experience unblocked | #181–187, #189–191 |
| v0.8 | Default content type (core.note), boot enforcement, ACL baseline, CI versioning gates — platform contracts begin | #195, #197–202 |
| v0.9 | Ingestion envelope, schema registry, namespace rules, RBAC, telemetry, operator diagnostics, onboarding guardrails | #203–209 |
| v0.10 | Feature flags, tenant migration plan — contract evolution and rollout safety finalized before v1.0 lock | #210–211 |
| v1.0 | Platform contracts locked. Ingestion, schema registry, ACL, versioning, and CI — stable and semver-committed | TBD |

**Narrative arc:**
- v0.7 — make the platform usable
- v0.8 — define the platform contract
- v0.9 — expand the platform contract
- v0.10 — stabilize the platform contract
- v1.0 — lock the platform contract

## Minoo Milestones (waaseyaa/minoo)

| Milestone | Description | Due Date |
|-----------|-------------|----------|
| v0.2 | Foundation shipped — 13 entity types, ingestion pipeline, search, SSR pages, codified context | 2026-03-07 (retroactive) |
| v0.3 | Content authoring for all 5 entity domains via admin interface *(depends on framework v0.7)* | TBD |
| v0.4 | Knowledge graph, cultural connections, richer language and teaching features | TBD |
| v1.0 | Content model and UX stable — product ready for public launch | TBD |

## Dependency Note

Minoo v0.3 (content authoring) is blocked on framework v0.7. The admin SPA richtext crash (#181) prevents content entry for 6 of 13 entity types.
