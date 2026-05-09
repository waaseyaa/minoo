# Tasks: Migrate community marker to explicit tenancy

**Mission ID**: `01KR69KT3D37BGRW76MSYTNR6R`
**Mission slug**: `migrate-community-marker-to-explicit-tenancy-01KR69KT`
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Branch contract**: current `main` → planning base `main` → merge target `main`
**Change mode**: `bulk_edit` (see [occurrence_map.yaml](occurrence_map.yaml))

## Pre-flight (mission orchestrator)

Before WP01 starts, the mission orchestrator must:

1. **Create or identify the umbrella GitHub issue** for this mission. Suggested title:
   *"Migrate community marker (`HasCommunityInterface`) to explicit `tenancy:` declaration."*
   Assign to the **V1 Release** milestone (per CLAUDE.md "GitHub Workflow"
   rule #1: every issue belongs to a milestone). Capture the issue number;
   the WP commits and PRs reference it.
2. Confirm the framework version satisfies the alpha.173+ floor (per C-003).

## Subtask Index

| Task ID | Description                                                                                                | WP    | Parallel |
| ------- | ---------------------------------------------------------------------------------------------------------- | ----- | -------- |
| T001    | WP01 verification: confirm Group/Leader/Contributor are registered in `EntityCommunityProvider`             | WP01  |          |
| T002    | Add `tenancy: ['scope' => 'community']` to Group/Leader/Contributor `EntityType` registrations              | WP01  |          |
| T003    | Remove marker from `src/Entity/Group.php`                                                                  | WP01  | [P]      |
| T004    | Remove marker from `src/Entity/Leader.php`                                                                 | WP01  | [P]      |
| T005    | Remove marker from `src/Entity/Contributor.php`                                                            | WP01  | [P]      |
| T006    | WP01 verification: bust manifest cache, run PHPUnit, confirm green                                          | WP01  |          |
| T007    | WP01 verification: cold-boot smoke + log scan for community/leader/contributor surfaces                     | WP01  |          |
| T008    | WP01 commit, push, open PR with `Part of #<umbrella>`                                                       | WP01  |          |
| T009    | WP02 verification: confirm OralHistory/Teaching/Event are registered in `EntityContentProvider`             | WP02  |          |
| T010    | Add `tenancy:` to OralHistory/Teaching/Event `EntityType` registrations                                     | WP02  |          |
| T011    | Remove marker from `src/Entity/OralHistory.php`                                                            | WP02  | [P]      |
| T012    | Remove marker from `src/Entity/Teaching.php`                                                               | WP02  | [P]      |
| T013    | Remove marker from `src/Entity/Event.php`                                                                  | WP02  | [P]      |
| T014    | WP02 verification: bust manifest cache, run PHPUnit, confirm green                                          | WP02  |          |
| T015    | WP02 verification: cold-boot smoke + log scan for teachings/events/oral-history surfaces                    | WP02  |          |
| T016    | WP02 commit, push, open PR with `Part of #<umbrella>`                                                       | WP02  |          |
| T017    | WP03 verification: confirm Post's owning provider (provisional `EntityFoundationProvider`)                  | WP03  |          |
| T018    | Add `tenancy:` to Post `EntityType` registration                                                            | WP03  |          |
| T019    | Remove marker from `src/Entity/Post.php`                                                                   | WP03  |          |
| T020    | Final repo-wide grep `grep -rn HasCommunityInterface src/ tests/` → expected 0                              | WP03  |          |
| T021    | Reconcile `occurrence_map.yaml` — confirm every `remove` entry is gone, no surprise hits                    | WP03  |          |
| T022    | WP03 verification: bust manifest cache, run PHPUnit, confirm green                                          | WP03  |          |
| T023    | WP03 verification: cold-boot smoke + log scan for `/`, `/feed`, post-engagement surfaces                    | WP03  |          |
| T024    | WP03 commit, push, open PR with `Closes #<umbrella>`                                                        | WP03  |          |

The `[P]` markers in the Parallel column indicate that the entity-class
edits within a single WP have no shared file (each entity is its own
class), so they could be done in any order. The provider edit (T002,
T010, T018) and verification steps (T006-T008, T014-T016, T022-T024)
must be sequential within their WP.

## Work Packages

### WP01 — EntityCommunityProvider cluster (Group, Leader, Contributor)

**Goal**: Migrate the 3 community-bundle entities owned by
`EntityCommunityProvider` to explicit `tenancy: ['scope' => 'community']`.

**Priority**: P1 (first WP — establishes pattern other WPs follow).
**Independent test**: PHPUnit suite green; cold-boot log clean for
community/leader/contributor surfaces.
**Estimated prompt size**: ~350 lines.
**Prompt file**: [tasks/WP01-entitycommunity-cluster.md](tasks/WP01-entitycommunity-cluster.md)

**Included subtasks**:

- [ ] T001 Verify Group/Leader/Contributor registrations are in `EntityCommunityProvider` (WP01)
- [ ] T002 Add `tenancy: ['scope' => 'community']` to all 3 `EntityType` registrations in `EntityCommunityProvider.php` (WP01)
- [ ] T003 [P] Remove `implements HasCommunityInterface` + `use` from `src/Entity/Group.php` (WP01)
- [ ] T004 [P] Remove `implements HasCommunityInterface` + `use` from `src/Entity/Leader.php` (WP01)
- [ ] T005 [P] Remove `implements HasCommunityInterface` + `use` from `src/Entity/Contributor.php` (WP01)
- [ ] T006 Delete `storage/framework/packages.php`, run `./vendor/bin/phpunit`, confirm green (WP01)
- [ ] T007 Cold-boot smoke `/communities`, `/communities/<slug>`, etc. + log scan (WP01)
- [ ] T008 Commit, push, open PR with `Part of #<umbrella>` (WP01)

**Implementation sketch**: verify ownership → edit provider → edit 3 entity classes (parallel-safe) → bust cache → tests → smoke → ship.

**Parallel opportunities**: T003/T004/T005 touch separate files; safe to do in any order or simultaneously.

**Dependencies**: none (this is the first WP).

**Risks**: Provider ownership drift (mitigated by T001). Stale manifest cache (mitigated by T006 cache delete).

### WP02 — EntityContentProvider cluster (OralHistory, Teaching, Event)

**Goal**: Migrate the 3 content-bundle entities owned by
`EntityContentProvider` to explicit `tenancy: ['scope' => 'community']`.

**Priority**: P2 (parallel to WP01 in principle, but conventionally
sequenced after WP01 lands so any pattern adjustments propagate).
**Independent test**: PHPUnit green; cold-boot log clean for
teachings/events/oral-history surfaces.
**Estimated prompt size**: ~350 lines.
**Prompt file**: [tasks/WP02-entitycontent-cluster.md](tasks/WP02-entitycontent-cluster.md)

**Included subtasks**:

- [ ] T009 Verify OralHistory/Teaching/Event registrations are in `EntityContentProvider` (WP02)
- [ ] T010 Add `tenancy: ['scope' => 'community']` to all 3 `EntityType` registrations in `EntityContentProvider.php` (WP02)
- [ ] T011 [P] Remove marker from `src/Entity/OralHistory.php` (WP02)
- [ ] T012 [P] Remove marker from `src/Entity/Teaching.php` (WP02)
- [ ] T013 [P] Remove marker from `src/Entity/Event.php` (WP02)
- [ ] T014 Delete manifest cache, run PHPUnit, confirm green (WP02)
- [ ] T015 Cold-boot smoke `/teachings`, `/events`, `/oral-histories` + log scan (WP02)
- [ ] T016 Commit, push, open PR with `Part of #<umbrella>` (WP02)

**Implementation sketch**: same shape as WP01 but on EntityContentProvider.

**Parallel opportunities**: T011/T012/T013 in any order. Conceptually parallelizable with WP01, but sequenced after WP01 in practice for review-load reasons.

**Dependencies**: WP01 (sequencing — for review and pattern lock-in, not technical).

**Risks**: same as WP01.

### WP03 — EntityFoundationProvider + final reconciliation (Post)

**Goal**: Migrate Post (the last marker-tagged entity) and reconcile the
mission's bulk-edit guarantees.

**Priority**: P3 (final WP — closes the mission).
**Independent test**: PHPUnit green; cold-boot log clean for `/feed`
post-engagement surfaces; `grep -rn HasCommunityInterface src/ tests/`
returns 0.
**Estimated prompt size**: ~400 lines.
**Prompt file**: [tasks/WP03-entityfoundation-and-final.md](tasks/WP03-entityfoundation-and-final.md)

**Included subtasks**:

- [ ] T017 Verify Post's owning provider — provisional `EntityFoundationProvider`, but `Post` could be in `EntityFeedProvider` (WP03)
- [ ] T018 Add `tenancy: ['scope' => 'community']` to Post `EntityType` registration in confirmed owner (WP03)
- [ ] T019 Remove marker from `src/Entity/Post.php` (WP03)
- [ ] T020 Final grep: `grep -rn HasCommunityInterface src/ tests/` → expected 0 matches (WP03)
- [ ] T021 Reconcile `occurrence_map.yaml` — every `remove` entry verified gone; close mission's bulk-edit guarantee (WP03)
- [ ] T022 Delete manifest cache, run PHPUnit, confirm green (WP03)
- [ ] T023 Cold-boot smoke `/`, `/feed`, post-engagement surfaces + log scan (WP03)
- [ ] T024 Commit, push, open PR with `Closes #<umbrella>` (WP03)

**Implementation sketch**: identify Post's actual provider → migrate it → run repo-wide reconciliation → ship final PR closing the mission.

**Parallel opportunities**: none within this WP (it's the closer).

**Dependencies**: WP01, WP02 (both must merge to `main` first so the final grep is meaningful and PR diff is small).

**Risks**: If Post is registered in a non-foundation provider, the WP boundary needs adjustment. T017's verification grep catches this in <1 min.

## Parallelization Highlights

- Inter-WP: WP01 and WP02 are technically independent (disjoint files). They run sequentially in convention to keep review surface focused.
- Intra-WP: Entity-class marker removal subtasks (T003-T005, T011-T013) are file-disjoint and can be batched into one edit pass.

## MVP scope

WP01 alone establishes the migration pattern and unblocks 3 of 7 entities.
However, the mission is only meaningful when the entire marker is gone
(`grep` returns 0). MVP is therefore the **complete mission**, not a
single WP. There is no partial-ship value here.

## Estimated total

- 24 subtasks across 3 WPs (avg 8 subtasks/WP, all within ideal range)
- Avg prompt size ~370 lines (within 200-500 ideal range)
- Total work: ~1-2 hours per WP for a focused implementer
