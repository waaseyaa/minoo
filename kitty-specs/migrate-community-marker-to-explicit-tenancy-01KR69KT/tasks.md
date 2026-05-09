# Tasks: Migrate community marker to explicit tenancy

**Mission ID**: `01KR69KT3D37BGRW76MSYTNR6R`
**Mission slug**: `migrate-community-marker-to-explicit-tenancy-01KR69KT`
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Branch contract**: current `main` → planning base `main` → merge target `main`
**Change mode**: `bulk_edit` (see [occurrence_map.yaml](occurrence_map.yaml))
**Umbrella issue**: #749
**Follow-up issue (CLAUDE.md drift)**: #760

## Pre-flight (mission orchestrator)

Mission already linked to umbrella issue **#749**. Framework version
**alpha.173** confirmed in `composer.lock`. Provider ownership re-verified
2026-05-09 — see [data-model.md](data-model.md). CLAUDE.md drift filed as
#760 and is OUT OF SCOPE for this mission.

## Subtask Index

| Task ID | Description                                                                                                                          | WP    | Parallel |
| ------- | ------------------------------------------------------------------------------------------------------------------------------------ | ----- | -------- |
| T001    | WP01 verification: confirm OralHistory, Contributor, Post, Leader registrations live in EntityContentProvider.php                    | WP01  |          | [D] | [D] |
| T002    | Add `tenancy: ['scope' => 'community']` to all 4 EntityType registrations in EntityContentProvider.php                                | WP01  |          | [D] |
| T003    | Remove marker from `src/Entity/OralHistory.php`                                                                                      | WP01  | [D] |
| T004    | Remove marker from `src/Entity/Contributor.php`                                                                                      | WP01  | [D] |
| T005    | Remove marker from `src/Entity/Post.php`                                                                                             | WP01  | [D] |
| T006    | Remove marker from `src/Entity/Leader.php`                                                                                           | WP01  | [D] |
| T007    | WP01 verification: bust manifest cache, run PHPUnit, confirm green                                                                    | WP01  |          | [D] |
| T008    | WP01 verification: cold-boot smoke + log scan                                                                                         | WP01  |          | [D] |
| T009    | WP01 commit (no PR — see plan.md)                                                                                                     | WP01  |          | [D] |
| T010    | WP02 verification: confirm Event, Teaching registrations live in EntityFoundationProvider.php                                         | WP02  |          | [D] |
| T011    | Add `tenancy:` to both EntityType registrations in EntityFoundationProvider.php                                                       | WP02  |          | [D] |
| T012    | Remove marker from `src/Entity/Event.php`                                                                                            | WP02  | [D] |
| T013    | Remove marker from `src/Entity/Teaching.php`                                                                                         | WP02  | [D] |
| T014    | WP02 verification: bust manifest cache, run PHPUnit, confirm green                                                                    | WP02  |          | [D] |
| T015    | WP02 verification: cold-boot smoke + log scan                                                                                         | WP02  |          | [D] |
| T016    | WP02 commit (no PR)                                                                                                                  | WP02  |          | [D] |
| T017    | WP03 verification: confirm Group remains unregistered (no EntityType migration to perform for Group)                                  | WP03  |          |
| T018    | Remove marker from `src/Entity/Group.php`                                                                                            | WP03  |          |
| T019    | Final repo-wide grep `grep -rn HasCommunityInterface src/ tests/` → expected 0                                                        | WP03  |          |
| T020    | Reconcile `occurrence_map.yaml` — append `reconciliation_completed` block                                                            | WP03  |          |
| T021    | WP03 verification: bust manifest cache, run PHPUnit, confirm green                                                                    | WP03  |          |
| T022    | WP03 verification: cold-boot smoke + log scan                                                                                         | WP03  |          |
| T023    | WP03 commit (no PR)                                                                                                                  | WP03  |          |

## Work Packages

### WP01 — EntityContentProvider cluster (OralHistory, Contributor, Post, Leader)

**Goal**: Migrate the 4 marker-tagged entities owned by `EntityContentProvider`
to explicit `tenancy: ['scope' => 'community']`.

**Priority**: P1 (largest WP — establishes the pattern).
**Independent test**: PHPUnit suite green; cold-boot log clean.
**Estimated prompt size**: ~400 lines.
**Prompt file**: [tasks/WP01-entitycontent-cluster.md](tasks/WP01-entitycontent-cluster.md)

**Included subtasks**:

- [x] T001 Confirm 4 registrations live at EntityContentProvider.php lines 116/322/357/470 (WP01)
- [x] T002 Add `tenancy: ['scope' => 'community']` to all 4 EntityType registrations (WP01)
- [x] T003 [P] Remove `implements HasCommunityInterface` + `use` from `src/Entity/OralHistory.php` (WP01)
- [x] T004 [P] Remove marker from `src/Entity/Contributor.php` (WP01)
- [x] T005 [P] Remove marker from `src/Entity/Post.php` (WP01)
- [x] T006 [P] Remove marker from `src/Entity/Leader.php` (WP01)
- [x] T007 Delete `storage/framework/packages.php`, run `./vendor/bin/phpunit`, confirm green (WP01)
- [x] T008 Cold-boot smoke + log scan (WP01)
- [x] T009 Commit in worktree (no PR) (WP01)

**Implementation sketch**: verify ownership → edit provider (4 named-arg additions) → edit 4 entity classes → bust cache → tests → smoke → commit.

**Dependencies**: none.

**Risks**: Test fixture asserting via marker (mitigated by T007). Cold-boot smoke flakiness on WSL2 (mitigated by time-box guidance in WP prompt).

### WP02 — EntityFoundationProvider cluster (Event, Teaching)

**Goal**: Migrate the 2 marker-tagged entities owned by `EntityFoundationProvider`
to explicit `tenancy: ['scope' => 'community']`.

**Priority**: P2.
**Independent test**: PHPUnit green; cold-boot log clean.
**Estimated prompt size**: ~300 lines.
**Prompt file**: [tasks/WP02-entityfoundation-cluster.md](tasks/WP02-entityfoundation-cluster.md)

**Included subtasks**:

- [x] T010 Confirm Event and Teaching live at EntityFoundationProvider.php lines 195/420 (WP02)
- [x] T011 Add `tenancy:` to both EntityType registrations (WP02)
- [x] T012 [P] Remove marker from `src/Entity/Event.php` (WP02)
- [x] T013 [P] Remove marker from `src/Entity/Teaching.php` (WP02)
- [x] T014 Delete manifest cache, run PHPUnit, confirm green (WP02)
- [x] T015 Cold-boot smoke + log scan (WP02)
- [x] T016 Commit in worktree (no PR) (WP02)

**Dependencies**: WP01 (sequencing — for review-load reasons; technically the providers don't share files).

**Risks**: same as WP01.

### WP03 — Group orphan + final reconciliation

**Goal**: Remove the marker from the unregistered `Group` orphan and run
mission-level reconciliation to confirm the bulk-edit promise.

**Priority**: P3 (closes the mission).
**Independent test**: PHPUnit green; cold-boot log clean; `grep -rn HasCommunityInterface src/ tests/` returns 0.
**Estimated prompt size**: ~300 lines.
**Prompt file**: [tasks/WP03-group-orphan-and-final.md](tasks/WP03-group-orphan-and-final.md)

**Included subtasks**:

- [ ] T017 Confirm Group is still unregistered (no provider edit needed) (WP03)
- [ ] T018 Remove marker from `src/Entity/Group.php` (use import + implements clause) (WP03)
- [ ] T019 Final grep: `grep -rn HasCommunityInterface src/ tests/` → expected 0 (WP03)
- [ ] T020 Append `reconciliation_completed` block to `occurrence_map.yaml` (WP03)
- [ ] T021 Delete manifest cache, run PHPUnit, confirm green (WP03)
- [ ] T022 Cold-boot smoke + log scan (WP03)
- [ ] T023 Commit in worktree (no PR) (WP03)

**Dependencies**: WP01, WP02 (final grep is meaningful only after both have merged into the lane).

**Risks**: Group's class declaration may have idiosyncratic `implements` chain; T018 includes inspection guidance.

## Parallelization Highlights

All 3 WPs share `lane-a` (single sequential lane). Intra-WP, the entity-class
marker removals are file-disjoint and could be batched.

## MVP scope

The mission is only meaningful when complete (`grep` returns 0). MVP = full
mission. No partial-ship value.

## Estimated total

- 23 subtasks across 3 WPs (WP01: 9, WP02: 7, WP03: 7 — all within 3-7 ideal/10 max)
- Avg prompt size ~330 lines (within 200-500 ideal)
