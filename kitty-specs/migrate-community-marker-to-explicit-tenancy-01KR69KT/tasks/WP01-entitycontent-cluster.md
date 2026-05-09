---
work_package_id: WP01
title: EntityContentProvider cluster — OralHistory, Contributor, Post, Leader
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-004
- FR-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T001
- T002
- T003
- T004
- T005
- T006
- T007
- T008
- T009
agent: "claude:opus:reviewer:reviewer"
shell_pid: "18447"
history:
- timestamp: '2026-05-09T12:30:00Z'
  action: rescoped
  note: 'Rescoped from EntityCommunityProvider/3-entities to EntityContentProvider/4-entities after T001 verification surfaced provider-ownership drift; CLAUDE.md drift filed as #760.'
authoritative_surface: src/Provider/Entity/EntityContentProvider.php
execution_mode: code_change
mission_id: 01KR69KT3D37BGRW76MSYTNR6R
mission_slug: migrate-community-marker-to-explicit-tenancy-01KR69KT
owned_files:
- src/Provider/Entity/EntityContentProvider.php
- src/Entity/OralHistory.php
- src/Entity/Contributor.php
- src/Entity/Post.php
- src/Entity/Leader.php
tags: []
---

# WP01 — EntityContentProvider cluster (OralHistory, Contributor, Post, Leader)

## Objective

Migrate the 4 marker-tagged entities owned by `EntityContentProvider` from
the deprecated `Waaseyaa\Entity\Community\HasCommunityInterface` marker to
the framework's explicit `tenancy: ['scope' => 'community']` declaration on
the `EntityType` constructor.

## Context

This is the first of 3 WPs. WP01 is the largest (4 entities, 5 files); WP02
and WP03 follow the same pattern with smaller scopes. Read [spec.md](../spec.md),
[plan.md](../plan.md), and [data-model.md](../data-model.md) before starting.

Key facts:

- Framework's `EntityType` constructor accepts named param
  `array{scope: string}|null $tenancy`. See
  `vendor/waaseyaa/entity/src/EntityType.php` lines 52–113. Strict
  validation: only `scope` key, only `'community'` value.
- Single-pass migration: each entity's marker removal AND `tenancy:` addition
  land in the same commit (constraint C-002).
- Bulk-edit gate is active. The diff check expects only `code_symbols` and
  `import_paths` categories to change; tests/fixtures category is also
  permitted if a test asserts via the marker.

## Branch strategy

- Planning base: `main`
- Merge target: `main`
- Per-WP commit lives in the lane worktree at
  `.worktrees/migrate-community-marker-to-explicit-tenancy-01KR69KT-lane-a/`.
  **No PR push from this WP.** Cross-lane integration to `main` happens at
  mission close via `spec-kitty merge`.

## Worktree pre-flight (BEFORE any code edit)

Worktrees do NOT inherit the main repo's `vendor/`. Before phpunit will run:

```bash
cd /home/jones/dev/minoo/.worktrees/migrate-community-marker-to-explicit-tenancy-01KR69KT-lane-a
composer install --no-interaction --prefer-dist
```

If composer install fails because of path repositories pointing at
`../waaseyaa/packages/*` not resolving from the worktree, fall back to:
```bash
ln -sfn /home/jones/dev/minoo/vendor vendor
```

## Subtasks

### T001 — Verify provider ownership

**Purpose**: Confirm the 4 entities are registered exactly where data-model.md
says. This step is non-negotiable; it's how WP01 caught the original drift.

**Steps**:

```bash
grep -rn "id: '\(oral_history\|contributor\|post\|leader\)'" src/Provider/
```

Expected output (per [data-model.md](../data-model.md)):

```
src/Provider/Entity/EntityContentProvider.php:116:                        id: 'oral_history',
src/Provider/Entity/EntityContentProvider.php:322:                        id: 'contributor',
src/Provider/Entity/EntityContentProvider.php:357:                        id: 'post',
src/Provider/Entity/EntityContentProvider.php:470:                        id: 'leader',
```

If any registration is missing or in another provider, halt and update
this WP's `owned_files` + `data-model.md` before proceeding.

**Validation**: All 4 hits in `EntityContentProvider.php`.

### T002 — Add `tenancy:` to all 4 EntityType registrations

**Purpose**: Add the explicit tenancy declaration to each of the 4
`new EntityType(...)` constructions.

**Steps**:

1. Open `src/Provider/Entity/EntityContentProvider.php`.
2. For each of the 4 calls (lines 116, 322, 357, 470), add the named arg
   `tenancy: ['scope' => 'community']`. Place after `entityKeys:`:

   ```php
   $this->entityType(new EntityType(
       id: 'oral_history',
       label: '...',
       entityKeys: [...],
       tenancy: ['scope' => 'community'],
       // ...remaining args...
   ));
   ```

3. **Do not** modify any other named arg or reformat surrounding code.
   The diff for T002 should be exactly 4 one-line additions inside 4
   `new EntityType(` calls.

**Validation**:

```bash
grep -cE "tenancy: \['scope' => 'community'\]" src/Provider/Entity/EntityContentProvider.php
# expected: 4
```

### T003 — [P] Remove marker from `src/Entity/OralHistory.php`

**Purpose**: Atomic with T002.

**Steps**:

1. Remove `use Waaseyaa\Entity\Community\HasCommunityInterface;` import.
2. Remove `implements HasCommunityInterface` from class declaration.
   Preserve any other interfaces:
   ```diff
   -final class OralHistory extends ContentEntityBase implements HasCommunityInterface
   +final class OralHistory extends ContentEntityBase
   ```
3. ```bash
   grep -n HasCommunityInterface src/Entity/OralHistory.php
   # expected: empty
   ```

**Validation**: 0 grep matches.

### T004 — [P] Remove marker from `src/Entity/Contributor.php`

Same shape as T003 against `src/Entity/Contributor.php`.

### T005 — [P] Remove marker from `src/Entity/Post.php`

Same shape against `src/Entity/Post.php`.

### T006 — [P] Remove marker from `src/Entity/Leader.php`

Same shape against `src/Entity/Leader.php`.

### T007 — Bust manifest cache, run PHPUnit

**Purpose**: Catch malformed registrations. The framework throws on bad
shape; the manifest cache can mask provider changes.

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```

If a test fails on `instanceof HasCommunityInterface`, mechanical assertion
replacement is allowed per FR-004. Update `occurrence_map.yaml`'s
`tests_fixtures` category notes if you touch any test file.

**Validation**: `phpunit` exits 0; test count unchanged.

### T008 — Cold-boot smoke + log scan

**Purpose**: Catch silent-path regressions phpunit doesn't exercise.

**Time-box**: 60 seconds total. If the local dev server hangs or curl
loops on WSL2, skip with explicit note.

```bash
php -S 0.0.0.0:8080 -t public public/index.php > /tmp/wp01-cold-boot.log 2>&1 &
PHPSERVER=$!
sleep 2
curl -sS -o /dev/null -w "GET / → %{http_code}\n" http://localhost:8080/
curl -sS -o /dev/null -w "GET /feed → %{http_code}\n" http://localhost:8080/feed
curl -sS -o /dev/null -w "GET /communities → %{http_code}\n" http://localhost:8080/communities
curl -sS -o /dev/null -w "GET /teachings → %{http_code}\n" http://localhost:8080/teachings  # exercises Post + content models
kill $PHPSERVER

grep -E "tenancy.deprecation|HasCommunityInterface" /tmp/wp01-cold-boot.log
# expected: 0 matches for OralHistory/Contributor/Post/Leader
```

**Validation**: All HTTP statuses 200/302/401 (no 500). Log scan returns 0.

### T009 — Commit (no PR)

**Purpose**: Lock in WP01's changes inside the lane worktree.

```bash
git add src/Provider/Entity/EntityContentProvider.php \
        src/Entity/OralHistory.php \
        src/Entity/Contributor.php \
        src/Entity/Post.php \
        src/Entity/Leader.php

git commit -m "$(cat <<'EOF'
feat(WP01): wp01 entity-content cluster -> explicit tenancy

Replace HasCommunityInterface marker with explicit
tenancy: ['scope' => 'community'] on the EntityType registrations
for OralHistory, Contributor, Post, and Leader. Remove the marker
`implements` clause and `use` import from each entity class.

Behavior preserved: PHPUnit suite green.

Part of #749
EOF
)"
```

**Do NOT push. Do NOT open a PR.** Cross-lane integration runs at mission close.

## After committing

```bash
spec-kitty agent tasks mark-status T001 T002 T003 T004 T005 T006 T007 T008 T009 \
  --status done \
  --mission migrate-community-marker-to-explicit-tenancy-01KR69KT
spec-kitty agent tasks move-task WP01 --to for_review \
  --mission migrate-community-marker-to-explicit-tenancy-01KR69KT \
  --note "Ready for review"
```

## Definition of Done

- [ ] All 9 subtasks completed and validated.
- [ ] `grep -nE "tenancy: \['scope' => 'community'\]" src/Provider/Entity/EntityContentProvider.php` shows 4 matches.
- [ ] `grep -rn HasCommunityInterface src/Entity/OralHistory.php src/Entity/Contributor.php src/Entity/Post.php src/Entity/Leader.php` returns 0.
- [ ] PHPUnit exits 0; test count unchanged.
- [ ] Cold-boot log scan returns 0 matches (or skipped with explicit note).

## Out of scope

- WP02 / WP03 files. The bulk-edit gate enforces `owned_files` boundaries.
- Group's marker removal (WP03's job).
- CLAUDE.md drift fix (#760).
- The framework's marker file at `vendor/waaseyaa/entity/src/Community/HasCommunityInterface.php` (framework owns its deletion).

## Activity Log

- 2026-05-09T12:35:17Z – claude:sonnet:implementer:implementer – shell_pid=14003 – Started implementation via action command
- 2026-05-09T12:40:37Z – claude:sonnet:implementer:implementer – shell_pid=14003 – Ready for review
- 2026-05-09T12:41:47Z – claude:opus:reviewer:reviewer – shell_pid=15594 – Started review via action command
- 2026-05-09T12:47:44Z – claude:opus:reviewer:reviewer – shell_pid=15594 – Review cycle 1: REJECTED — trait removal regression. getCommunityId/setCommunityId undefined after composer dump-autoload. See review-cycle-1.md. Restore HasCommunityTrait import + use in Post/Leader/Contributor/OralHistory; only the implements HasCommunityInterface clause is in scope for removal.
- 2026-05-09T12:48:25Z – claude:sonnet:implementer:implementer – shell_pid=17478 – Started implementation via action command
- 2026-05-09T12:50:59Z – claude:sonnet:implementer:implementer – shell_pid=17478 – Cycle 2: restored HasCommunityTrait; only the interface marker is removed. 4 implements_has_community_interface test methods also removed (they tested the removed interface). 23 HasCommunity tests green, 1087 total tests passing.
- 2026-05-09T12:51:29Z – claude:opus:reviewer:reviewer – shell_pid=18447 – Started review via action command
- 2026-05-09T12:52:41Z – claude:opus:reviewer:reviewer – shell_pid=18447 – Cycle 2 review passed: trait restored, 1087 tests green, mechanical test fixture updates within FR-004 allowance
