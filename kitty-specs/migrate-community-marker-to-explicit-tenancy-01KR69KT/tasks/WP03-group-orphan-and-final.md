---
work_package_id: WP03
title: Group orphan + final reconciliation
dependencies:
- WP01
- WP02
requirement_refs:
- FR-002
- FR-003
- FR-004
- FR-005
- FR-006
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T017
- T018
- T019
- T020
- T021
- T022
- T023
agent: "claude:opus:reviewer:reviewer"
shell_pid: "21652"
history:
- timestamp: '2026-05-09T12:30:00Z'
  action: rescoped
  note: Rescoped from EntityFoundationProvider/Post to Group-orphan after WP01/WP02 absorbed all registered entities; Group has no provider registration to migrate, only the marker class needs cleaning + final mission reconciliation.
authoritative_surface: src/Entity/Group.php
execution_mode: code_change
mission_id: 01KR69KT3D37BGRW76MSYTNR6R
mission_slug: migrate-community-marker-to-explicit-tenancy-01KR69KT
owned_files:
- src/Entity/Group.php
- kitty-specs/migrate-community-marker-to-explicit-tenancy-01KR69KT/occurrence_map.yaml
tags: []
---

# WP03 — Group orphan + final reconciliation

## Objective

Remove the marker from the unregistered `src/Entity/Group.php` orphan
class and run mission-level reconciliation to confirm the bulk-edit
guarantee: zero `HasCommunityInterface` references remain in `src/`.

## Context

WP01 and WP02 have merged into the lane. All 6 registered marker-tagged
entities are migrated. The 7th class — `src/Entity/Group.php` — carries
the marker but has no `EntityType` registration in any provider (verified
in [data-model.md](../data-model.md)). It's effectively dead code from
the framework's perspective: the deprecation warning never fires for
Group because the framework only scans registered types.

But FR-006 says **all** `HasCommunityInterface` references in `src/`
must go to satisfy mission cleanup. WP03 removes Group's marker and
closes the mission.

## Branch strategy

- Planning base: `main`
- Merge target: `main`
- Per-WP commit in the lane worktree. **No PR push.** Cross-lane merge
  to `main` happens at mission close via `spec-kitty merge`.

## Worktree pre-flight

`vendor/` should be present from WP01/WP02. If not, `composer install`
per WP01's pre-flight section.

## Subtasks

### T017 — Confirm Group is still unregistered

**Purpose**: Defensive check. If Group has been registered between WP02
and WP03 (very unlikely but possible during a long mission), this WP's
boundary changes — it would now also need a provider edit.

```bash
grep -rn "id: 'group'" src/Provider/
# expected: empty (Group remains orphan)

grep -rn "App\\\\Entity\\\\Group::class\|Group::class" src/Provider/ | grep -v CulturalGroup
# expected: empty (only CulturalGroup is registered, plain Group is not)
```

If Group has been registered, halt and update WP03's `owned_files` to
include the new provider, plus add a `tenancy:` named arg to Group's
new `EntityType` call.

**Validation**: Both greps return empty.

### T018 — Remove marker from `src/Entity/Group.php`

```bash
grep -n HasCommunityInterface src/Entity/Group.php
# expect: at least 2 matches (use import + implements clause)
```

Remove:
1. `use Waaseyaa\Entity\Community\HasCommunityInterface;` import.
2. `implements HasCommunityInterface` from class declaration. Preserve
   any other interfaces.

```bash
grep -n HasCommunityInterface src/Entity/Group.php
# expected: empty
```

**Validation**: 0 grep matches in Group.php.

### T019 — Final repo-wide grep

**Purpose**: Mission's primary success criterion (SC-001).

```bash
grep -rn HasCommunityInterface src/
# expected: 0 matches across the entire src/ tree

grep -rn HasCommunityInterface tests/
# expected: 0 matches
```

If either returns matches, **do not** commit. Investigate:

- A forgotten file → migrate it; expand WP03's `owned_files`; document.
- A test fixture using `instanceof HasCommunityInterface` → mechanical
  replacement per FR-004; update `occurrence_map.yaml`.

**Validation**: Both greps return exactly 0 matches.

### T020 — Reconcile `occurrence_map.yaml`

**Purpose**: Close the bulk-edit guarantee.

Append a `reconciliation_completed` block to the YAML:

```yaml
reconciliation_completed:
  timestamp: "<UTC ISO timestamp at the moment of T019 completion>"
  by_wp: "WP03"
  final_grep_counts:
    src/: 0
    tests/: 0
  notes: |
    All 7 marker-tagged classes had their `HasCommunityInterface`
    import + `implements` clause removed. 6 of 7 (OralHistory,
    Contributor, Post, Leader, Event, Teaching) also had
    `tenancy: ['scope' => 'community']` added to their EntityType
    registration in the appropriate provider. The 7th class
    (`src/Entity/Group.php`) is unregistered orphan code; no provider
    edit was performed. Orphan disposition tracked in #760.
```

**Validation**: YAML still valid; new block present.

### T021 — Bust manifest cache, run PHPUnit

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```

**Validation**: exits 0; test count unchanged from baseline.

### T022 — Cold-boot smoke + log scan

Time-box: 60 seconds. Skip with note if WSL2 hangs.

```bash
php -S 0.0.0.0:8080 -t public public/index.php > /tmp/wp03-cold-boot.log 2>&1 &
PHPSERVER=$!
sleep 2
curl -sS -o /dev/null -w "GET / → %{http_code}\n" http://localhost:8080/
curl -sS -o /dev/null -w "GET /feed → %{http_code}\n" http://localhost:8080/feed
curl -sS -o /dev/null -w "GET /communities → %{http_code}\n" http://localhost:8080/communities
curl -sS -o /dev/null -w "GET /events → %{http_code}\n" http://localhost:8080/events
curl -sS -o /dev/null -w "GET /teachings → %{http_code}\n" http://localhost:8080/teachings
kill $PHPSERVER
grep -E "tenancy.deprecation|HasCommunityInterface" /tmp/wp03-cold-boot.log
# expected: 0 matches across the board
```

**Validation**: HTTP 200/302/401; log scan 0 matches.

### T023 — Commit (no PR)

```bash
git add src/Entity/Group.php \
        kitty-specs/migrate-community-marker-to-explicit-tenancy-01KR69KT/occurrence_map.yaml

git commit -m "$(cat <<'EOF'
feat(WP03): wp03 group orphan + final reconciliation

Final WP in the marker-to-tenancy migration. Remove the
HasCommunityInterface marker from src/Entity/Group.php (orphan
class, no EntityType registration). Append reconciliation_completed
block to occurrence_map.yaml documenting:

- grep -rn HasCommunityInterface src/   -> 0 matches
- grep -rn HasCommunityInterface tests/ -> 0 matches
- PHPUnit suite green; cold-boot log clean

Group disposition (delete vs register) tracked in #760.

Part of #749
EOF
)"
```

## After committing

```bash
spec-kitty agent tasks mark-status T017 T018 T019 T020 T021 T022 T023 \
  --status done \
  --mission migrate-community-marker-to-explicit-tenancy-01KR69KT
spec-kitty agent tasks move-task WP03 --to for_review \
  --mission migrate-community-marker-to-explicit-tenancy-01KR69KT \
  --note "Ready for review — final WP, mission reconciliation included"
```

## Definition of Done

- [ ] All 7 subtasks completed and validated.
- [ ] SC-001: `grep -rn HasCommunityInterface src/` → 0.
- [ ] SC-002: PHPUnit exits 0 with unchanged test count.
- [ ] SC-003: Cold-boot log scan → 0 deprecation notices.
- [ ] `occurrence_map.yaml` carries `reconciliation_completed` block.

## Out of scope

- WP01 / WP02 files (bulk-edit gate enforces `owned_files`).
- Group disposition (delete vs register) — tracked in #760.
- CLAUDE.md drift (#760).
- The framework's marker file at
  `vendor/waaseyaa/entity/src/Community/HasCommunityInterface.php`.

## Activity Log

- 2026-05-09T12:58:17Z – claude:sonnet:implementer:implementer – shell_pid=20564 – Started implementation via action command
- 2026-05-09T13:02:22Z – claude:sonnet:implementer:implementer – shell_pid=20564 – Ready for review — final WP, mission reconciliation included
- 2026-05-09T13:02:50Z – claude:opus:reviewer:reviewer – shell_pid=21652 – Started review via action command
- 2026-05-09T13:03:58Z – claude:opus:reviewer:reviewer – shell_pid=21652 – Mission complete: SC-001/002/003 verified; reconciliation block present; trait preserved; Group orphan tracked in #760
- 2026-05-09T13:06:25Z – claude:opus:reviewer:reviewer – shell_pid=21652 – Done override: Mission squash-merged to main as d02877e
