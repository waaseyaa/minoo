---
work_package_id: WP02
title: EntityFoundationProvider cluster — Event, Teaching
dependencies:
- WP01
requirement_refs:
- FR-001
- FR-002
- FR-004
- FR-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T010
- T011
- T012
- T013
- T014
- T015
- T016
agent: "claude:sonnet:implementer:implementer"
shell_pid: "19003"
history:
- timestamp: '2026-05-09T12:30:00Z'
  action: rescoped
  note: Rescoped from EntityContentProvider/3-entities to EntityFoundationProvider/2-entities after WP01 surfaced provider drift.
authoritative_surface: src/Provider/Entity/EntityFoundationProvider.php
execution_mode: code_change
mission_id: 01KR69KT3D37BGRW76MSYTNR6R
mission_slug: migrate-community-marker-to-explicit-tenancy-01KR69KT
owned_files:
- src/Provider/Entity/EntityFoundationProvider.php
- src/Entity/Event.php
- src/Entity/Teaching.php
tags: []
---

# WP02 — EntityFoundationProvider cluster (Event, Teaching)

## Objective

Migrate the 2 marker-tagged entities owned by `EntityFoundationProvider`
to explicit `tenancy: ['scope' => 'community']`. Same pattern as WP01,
applied to a different provider.

## Context

WP01 has merged into the lane and validated the pattern. This WP follows
the exact same shape — only the file paths and entity names change.

Read [spec.md](../spec.md), [plan.md](../plan.md), and
[data-model.md](../data-model.md). Before starting, inspect WP01's commit
in the lane worktree to confirm the formatting WP01 chose; replicate it
here.

## Branch strategy

- Planning base: `main`
- Merge target: `main`
- Per-WP commit in the lane worktree (same lane-a as WP01). **No PR push.**

## Worktree pre-flight

The worktree should already have `vendor/` from WP01. If a fresh claim
created a new worktree, run `composer install` per WP01's pre-flight
section.

## Subtasks

### T010 — Verify provider ownership

```bash
grep -rn "id: '\(event\|teaching\)'" src/Provider/
```

Expected (per [data-model.md](../data-model.md)):

```
src/Provider/Entity/EntityFoundationProvider.php:195:                        id: 'event',
src/Provider/Entity/EntityFoundationProvider.php:420:                        id: 'teaching',
```

**Validation**: Both hits in `EntityFoundationProvider.php`.

### T011 — Add `tenancy:` to both EntityType registrations

Open `src/Provider/Entity/EntityFoundationProvider.php` and add
`tenancy: ['scope' => 'community']` after `entityKeys:` in each of the 2
`new EntityType(...)` calls (lines 195, 420). Match the formatting WP01
chose (open WP01's commit to confirm).

**Validation**:

```bash
grep -cE "tenancy: \['scope' => 'community'\]" src/Provider/Entity/EntityFoundationProvider.php
# expected: 2
```

### T012 — [P] Remove marker from `src/Entity/Event.php`

Same shape as WP01's T003-T006: remove `use` import, remove
`implements HasCommunityInterface` from class declaration.

```bash
grep -n HasCommunityInterface src/Entity/Event.php
# expected: empty
```

### T013 — [P] Remove marker from `src/Entity/Teaching.php`

Same shape against `src/Entity/Teaching.php`.

### T014 — Bust manifest cache, run PHPUnit

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```

Mechanical replacement allowed for `instanceof HasCommunityInterface`
fixture assertions.

**Validation**: phpunit exits 0; test count unchanged.

### T015 — Cold-boot smoke + log scan

Time-box: 60 seconds. Skip with note if WSL2 dev server hangs.

```bash
php -S 0.0.0.0:8080 -t public public/index.php > /tmp/wp02-cold-boot.log 2>&1 &
PHPSERVER=$!
sleep 2
curl -sS -o /dev/null -w "GET /events → %{http_code}\n" http://localhost:8080/events
curl -sS -o /dev/null -w "GET /teachings → %{http_code}\n" http://localhost:8080/teachings
kill $PHPSERVER
grep -E "tenancy.deprecation|HasCommunityInterface" /tmp/wp02-cold-boot.log
```

**Validation**: HTTP 200/302/401 only; log scan 0 matches for Event/Teaching.

### T016 — Commit (no PR)

```bash
git add src/Provider/Entity/EntityFoundationProvider.php \
        src/Entity/Event.php \
        src/Entity/Teaching.php

git commit -m "$(cat <<'EOF'
feat(WP02): wp02 entity-foundation cluster -> explicit tenancy

Replace HasCommunityInterface marker with explicit
tenancy: ['scope' => 'community'] on the EntityType registrations
for Event and Teaching. Remove the marker `implements` clause and
`use` import from each entity class.

Behavior preserved: PHPUnit suite green.

Part of #749
EOF
)"
```

## After committing

```bash
spec-kitty agent tasks mark-status T010 T011 T012 T013 T014 T015 T016 \
  --status done \
  --mission migrate-community-marker-to-explicit-tenancy-01KR69KT
spec-kitty agent tasks move-task WP02 --to for_review \
  --mission migrate-community-marker-to-explicit-tenancy-01KR69KT \
  --note "Ready for review"
```

## Definition of Done

- [ ] All 7 subtasks completed and validated.
- [ ] `grep -cE "tenancy: \['scope' => 'community'\]" src/Provider/Entity/EntityFoundationProvider.php` returns 2.
- [ ] `grep -rn HasCommunityInterface src/Entity/Event.php src/Entity/Teaching.php` returns 0.
- [ ] PHPUnit green; cold-boot log clean (or skipped with note).

## Out of scope

- WP01 / WP03 files (bulk-edit gate enforces `owned_files`).
- Group's marker removal (WP03).
- CLAUDE.md drift (#760).

## Activity Log

- 2026-05-09T12:53:05Z – claude:sonnet:implementer:implementer – shell_pid=19003 – Started implementation via action command
