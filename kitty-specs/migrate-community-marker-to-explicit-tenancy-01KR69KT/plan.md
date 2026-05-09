# Implementation Plan: Migrate community marker to explicit tenancy

**Mission ID**: `01KR69KT3D37BGRW76MSYTNR6R`
**Branch contract**: current `main` → planning base `main` → merge target `main`
**Change mode**: bulk_edit
**Spec**: [spec.md](spec.md)

## Technical Context

| Field                  | Value                                                                                                              |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------ |
| Language               | PHP 8.4 (`declare(strict_types=1)`)                                                                                |
| Framework              | Waaseyaa alpha.173                                                                                                 |
| Test runner            | PHPUnit 10.5                                                                                                       |
| Build/install tooling  | Composer (path/symlink to `../waaseyaa/packages/*` for sibling work; tagged versions in normal mode)                |
| Persistence            | SQLite (`storage/waaseyaa.sqlite`); in-memory for integration tests                                                 |
| Charter                | Not initialized (`.kittify/charter/charter.md` absent); proceed without charter context                              |

## Charter Check

Skipped — no charter present. No constraints from charter to validate.

## Engineering Alignment

The framework's tenancy contract (verified against
`/home/jones/dev/waaseyaa/packages/entity/src/EntityType.php`):

- `EntityType` constructor accepts a named parameter
  `array{scope: string}|null $tenancy`. Default `null` means non-tenant.
- Validation is strict: only the `scope` key is accepted, and only the
  literal value `'community'` is supported today.
  `\InvalidArgumentException` is thrown for any other shape.
- The runtime migration message in `EntityTypeManager.php:202` instructs
  callers to *"declare `tenancy: ['scope' => 'community']` on the
  EntityType registration."*

Minoo's registration style (verified via
`src/Provider/Entity/EntityCommunityProvider.php`) is uniformly
`$this->entityType(new EntityType( ... ))`. Adding the `tenancy:` named
argument to each call is mechanical.

The 7 entities and their owning providers (per `CLAUDE.md` "Entity provider
ownership" — to be re-verified per-WP):

| Entity        | Owning provider                        |
| ------------- | -------------------------------------- |
| Group         | `EntityCommunityProvider`              |
| Leader        | `EntityCommunityProvider`              |
| Contributor   | `EntityCommunityProvider`              |
| OralHistory   | `EntityContentProvider`                |
| Teaching      | `EntityContentProvider`                |
| Event         | `EntityContentProvider`                |
| Post          | `EntityFoundationProvider`             |

## Sequencing Strategy

Three work packages, grouped by provider. Each WP is independently
shippable because each provider owns a disjoint set of entities and
registration sites. This mirrors the controller-dispatcher mission
structure (cluster-by-cluster PRs to `main`).

| WP    | Cluster                                        | Entities                                | Files touched                                              |
| ----- | ---------------------------------------------- | --------------------------------------- | ---------------------------------------------------------- |
| WP01  | `EntityCommunityProvider`                      | Group, Leader, Contributor              | 1 provider + 3 entity classes                               |
| WP02  | `EntityContentProvider`                        | OralHistory, Teaching, Event            | 1 provider + 3 entity classes                               |
| WP03  | `EntityFoundationProvider` + final sweep       | Post                                    | 1 provider + 1 entity class + repo-wide grep reconciliation |

Each WP follows this internal pattern (atomicity per C-002):

1. Add `tenancy: ['scope' => 'community']` named arg to each affected
   `new EntityType(...)` call in the provider.
2. Remove `implements HasCommunityInterface` from the entity class.
3. Remove the `use Waaseyaa\Entity\Community\HasCommunityInterface;` import
   from the entity class.
4. `rm -f storage/framework/packages.php` to bust manifest cache.
5. Run `./vendor/bin/phpunit` and confirm green.
6. Cold-boot smoke test for routes that hit the affected entity types.
7. Commit, push, open PR with `Part of #<umbrella-issue>` (`Closes` for WP03).

WP03 additionally:
- Verifies `grep -rn HasCommunityInterface src/` returns 0 matches
  (FR-003 / FR-006).
- Reconciles `occurrence_map.yaml`: every classified occurrence is
  removed or explicitly preserved.

## Risk Register

| Risk                                                                                              | Likelihood | Mitigation                                                                                                                                                          |
| ------------------------------------------------------------------------------------------------- | ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| An entity's registration is in a provider not listed above (Newsletter, Feed, etc.)                | Medium     | WP01 starts with a verification pass: grep `entityType(new EntityType` for each of the 7 entity class names and confirm provider ownership before editing.            |
| A test fixture asserts via `instanceof HasCommunityInterface`                                      | Low        | WP01 grep extends to `tests/`; if any matches surface, the WP includes mechanical assertion updates per FR-004.                                                       |
| Stale `storage/framework/packages.php` cache breaks discovery after providers change               | Low        | Each WP deletes the cache file before running phpunit (per CLAUDE.md "Stale manifest cache" gotcha).                                                                  |
| Framework throws on first boot if registration was malformed                                       | Low        | Fail-fast — phpunit will catch this; cold-boot scan will catch any silent path. Atomicity per C-002 ensures we never ship an entity with neither marker nor `tenancy:`. |
| Subclasses (none today, but possible) inherit `implements` clause                                  | Very low   | WP01 grep recurses; subclasses surface as additional grep hits.                                                                                                       |

## Constitution Check

Per CLAUDE.md:

- Behavior preservation maps to C-001 (zero behavioral change).
- Atomic per-entity changes map to C-002 (provider edit + class edit
  together).
- Strict-types and `final class` already in place — no new files needed.
- No new migrations (NFR-002).

No constitution violations.

## Phase 0 Output

`research.md` consolidates the framework probe results. No
`[NEEDS CLARIFICATION]` markers remain.

## Phase 1 Outputs

- `data-model.md` — table of 7 entities with current marker, target
  declaration, owning provider, and verification grep.
- `contracts/README.md` — explains why this mission introduces no new
  external contracts (the `EntityType` constructor signature is the
  contract, and it lives in `vendor/waaseyaa/`).
- `quickstart.md` — verification commands the implementer runs at the end
  of each WP.
- `occurrence_map.yaml` — bulk-edit classification per
  `spec-kitty-bulk-edit-classification`.

## Branch contract (final restatement)

- Current branch at plan start: `main`
- Planning/base branch: `main`
- Final merge target: `main`
- `branch_matches_target`: true

All mission PRs land on `main`, mirroring the recently completed controller
dispatcher migration. No mission branch is created (work ships through
direct per-WP PRs).

## Stop point

Planning is complete. Next: `/spec-kitty.tasks` to materialize the work
package files.
