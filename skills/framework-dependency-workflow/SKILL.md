---
name: framework-dependency-workflow
description: Plan and verify Minoo work that depends on Waaseyaa, classify Framework blockers, and qualify upstream fixes in the application.
---

# Minoo Framework dependency workflow

Repository authorities: `docs/specs/social-capabilities.md`, `docs/roadmap.md` and `docs/specs/workflow.md`.

## Framework dependency workflow

Use this skill when planning or implementing application behavior that consumes
Waaseyaa, diagnosing a possible upstream blocker, or upgrading a Framework lock.
Read the application's current social spec, roadmap and workflow first.

1. Record the application revision, exact locked `waaseyaa/*` versions, installed
   package identity and affected entrypoint. Compare the supported upstream
   contract and current source. A stale app or absent opt-in package is not by
   itself a Framework defect; monorepo main is not an installed release.
2. Classify the gap: application bug/policy, stale dependency/adoption, confirmed
   Framework contract defect, or missing planned capability. Prove an upstream
   gap with a minimal synthetic failing scenario through the supported operation;
   include expected/actual behavior and relevant negative controls. Keep security
   reproductions and private data out of public issues; use private reporting.
3. Search open and closed upstream issues plus affected specs before filing.
   Keep one upstream owner per defect/capability. The application issue records
   its blocked acceptance scenario, upstream link, exact unblock condition and
   next independent work. Upstream descriptions stay generic and consumer-neutral.
4. Stop only the affected slice. Continue independent design, application work
   and fixtures within the authorized task. Do not copy the Framework engine,
   bypass access/storage rules, edit vendor code or conceal a defect behind a
   fallback. A temporary workaround needs a demonstrated need, explicit scope,
   owner and removal condition; it cannot weaken safety or data integrity.
5. Resolve a blocker only after an eligible published fix is adopted in the
   exact application lock and the formerly failing journey passes through the
   real app. Verify production no-dev composition when relevant. A closed
   upstream issue or passing monorepo test alone does not qualify this consumer.
6. Update the owning spec when behavior changes, roadmap when sequence changes,
   and issue with exact evidence. Retire superseded instructions and duplicate
   checklists. Keep planned features separate from confirmed defects.

This skill does not authorize upstream edits, issue publication, package upgrades,
release or deployment beyond the user's task. Specs define behavior, roadmaps
sequence delivery, and issues record execution; do not duplicate all three.
