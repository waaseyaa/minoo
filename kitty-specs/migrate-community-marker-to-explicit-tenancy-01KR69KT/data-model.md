# Data Model: Migrate community marker to explicit tenancy

This is a metadata-shape migration; no runtime data structures change. The
"data model" here is the set of `EntityType` registrations whose tenancy
declaration is moving from marker-interface to constructor argument.

## Entity Registration Map (verified against code 2026-05-09)

| #  | Entity        | Class file                       | Marker (`implements`)         | Provider registration site                                | Bundle key      |
| -- | ------------- | -------------------------------- | ----------------------------- | --------------------------------------------------------- | --------------- |
| 1  | OralHistory   | `src/Entity/OralHistory.php`     | `HasCommunityInterface`       | `src/Provider/Entity/EntityContentProvider.php:116`        | `oral_history`  |
| 2  | Contributor   | `src/Entity/Contributor.php`     | `HasCommunityInterface`       | `src/Provider/Entity/EntityContentProvider.php:322`        | `contributor`   |
| 3  | Post          | `src/Entity/Post.php`            | `HasCommunityInterface`       | `src/Provider/Entity/EntityContentProvider.php:357`        | `post`          |
| 4  | Leader        | `src/Entity/Leader.php`          | `HasCommunityInterface`       | `src/Provider/Entity/EntityContentProvider.php:470`        | `leader`        |
| 5  | Event         | `src/Entity/Event.php`           | `HasCommunityInterface`       | `src/Provider/Entity/EntityFoundationProvider.php:195`     | `event`         |
| 6  | Teaching      | `src/Entity/Teaching.php`        | `HasCommunityInterface`       | `src/Provider/Entity/EntityFoundationProvider.php:420`     | `teaching`      |
| 7  | Group         | `src/Entity/Group.php`           | `HasCommunityInterface`       | **NOT REGISTERED** (orphan)                               | —               |

## Drift from CLAUDE.md

The "Entity provider ownership" table in `CLAUDE.md` does not match this
mapping. Drift filed as **#760**. CLAUDE.md update is OUT OF SCOPE for
this mission.

## Group orphan

`src/Entity/Group.php` carries the `HasCommunityInterface` marker but has
no corresponding `EntityType` registration in any provider. The
similarly-named `CulturalGroup::class` is registered at
`EntityFoundationProvider.php:324`, but plain `Group::class` is unused.

This mission removes the marker from `Group.php` to satisfy FR-006
(repo-wide grep cleanliness) but does not register Group as an entity.
The orphan-code cleanup question (delete vs register) is tracked in
**#760**.

## Per-WP Scope

### WP01 — `EntityContentProvider` cluster
Owned files:
- `src/Provider/Entity/EntityContentProvider.php` (4 `EntityType` calls migrated: lines 116, 322, 357, 470)
- `src/Entity/OralHistory.php`
- `src/Entity/Contributor.php`
- `src/Entity/Post.php`
- `src/Entity/Leader.php`

### WP02 — `EntityFoundationProvider` cluster
Owned files:
- `src/Provider/Entity/EntityFoundationProvider.php` (2 `EntityType` calls migrated: lines 195, 420)
- `src/Entity/Event.php`
- `src/Entity/Teaching.php`

### WP03 — Group orphan + final reconciliation
Owned files:
- `src/Entity/Group.php` (marker removal only — no provider edit, no `tenancy:` to add)
- `kitty-specs/migrate-community-marker-to-explicit-tenancy-01KR69KT/occurrence_map.yaml` (reconciliation block)

## Per-WP Verification Greps

### WP01
```bash
grep -nE "tenancy: \['scope' => 'community'\]" src/Provider/Entity/EntityContentProvider.php
# expected: 4 matches (one per oral_history, contributor, post, leader)

grep -n HasCommunityInterface src/Entity/OralHistory.php src/Entity/Contributor.php src/Entity/Post.php src/Entity/Leader.php
# expected: 0 matches
```

### WP02
```bash
grep -nE "tenancy: \['scope' => 'community'\]" src/Provider/Entity/EntityFoundationProvider.php
# expected: 2 matches (event, teaching)

grep -n HasCommunityInterface src/Entity/Event.php src/Entity/Teaching.php
# expected: 0 matches
```

### WP03
```bash
grep -n HasCommunityInterface src/Entity/Group.php
# expected: 0 matches

grep -rn HasCommunityInterface src/
# expected: 0 matches across the entire src/ tree

grep -rn HasCommunityInterface tests/
# expected: 0 matches
```

## Constraints (from spec)

| ID    | Mapping                                                                                                                                |
| ----- | -------------------------------------------------------------------------------------------------------------------------------------- |
| C-001 | No behavioral change — only registration shape changes. Access policies and queries are untouched.                                      |
| C-002 | Each registered entity's marker removal AND provider edit land in the same commit (atomicity).                                          |
| C-003 | Framework alpha.173+ guarantees the explicit `tenancy:` declaration; this mission targets alpha.173 (verified in `composer.lock`).      |

## Notes

- Registration sites verified by `grep -rn "id: '...'" src/Provider/` on
  2026-05-09. Re-run before each WP to catch any drift introduced by
  parallel work.
