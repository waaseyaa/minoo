# Mission Spec: Migrate community marker to explicit tenancy

**Mission ID**: `01KR69KT3D37BGRW76MSYTNR6R`
**Mission slug**: `migrate-community-marker-to-explicit-tenancy-01KR69KT`
**Mission type**: software-dev
**Created**: 2026-05-09
**Target branch**: main
**Change mode**: bulk_edit

## Summary

Minoo currently flags 7 entities as community-scoped using the
`Waaseyaa\Entity\Tenancy\HasCommunityInterface` marker interface. Framework
alpha.173 emits active deprecation warnings stating the marker contract will
be removed in the next minor release; the framework now expects tenancy to be
declared explicitly in the `EntityType` registration as
`tenancy: ['scope' => 'community']`.

This mission replaces the marker with the explicit declaration on all 7
affected entities, deletes the `HasCommunityInterface` marker (`use` import
and `implements` clause) from each entity class in the same change, and
verifies behavior is preserved through the existing PHPUnit suite plus a
cold-boot log scan. It is a blocker for upgrading Minoo past alpha.173.

## User Scenarios & Testing

The "user" of this mission is the Minoo platform itself plus the engineer who
runs the next framework upgrade — there is no end-user-visible change.

### Primary scenario

**Given** the codebase declares 7 community-scoped entities via
`HasCommunityInterface` (Group, OralHistory, Teaching, Event, Leader,
Contributor, Post),
**when** the migration ships,
**then** all 7 entities declare `tenancy: ['scope' => 'community']` in their
`EntityType` registration, and no `HasCommunityInterface` reference remains
in `src/Entity/`.

### Behavior preservation scenario

**Given** community-scoped access policies and queries that depend on tenancy
metadata,
**when** the migration ships,
**then** existing PHPUnit tests pass without behavioral modification, and
runtime tenancy resolution returns the same scope decisions as before.

### Cold-boot log scenario

**Given** a fresh PHP server boot exercising controllers that load these 7
entities (e.g., `/feed`, `/communities/{id}`, `/teachings/{id}`,
`/events/{id}`),
**when** the request completes,
**then** the log emits zero `tenancy.deprecation` (and equivalent) notices
attributable to these entity types.

### Edge cases

- An entity provider already declares an `EntityType` array — the migration
  must add the `tenancy:` key, not replace surrounding keys.
- An entity class has child classes (none currently, but Post-style
  extension might appear) — the marker removal must propagate without
  leaving a stale `implements` declaration in a subclass.
- A test fixture asserts on the marker via `instanceof HasCommunityInterface`
  — assertions must be updated to query the explicit tenancy declaration
  instead.

## Requirements

### Functional

| ID     | Requirement                                                                                                                                                              | Status |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------ |
| FR-001 | All registered entities currently using `HasCommunityInterface` declare `tenancy: ['scope' => 'community']` in their `EntityType` registration. (6 of 7 marker-tagged classes are registered: OralHistory, Contributor, Post, Leader in `EntityContentProvider`; Event, Teaching in `EntityFoundationProvider`. `src/Entity/Group.php` is unregistered orphan code — its marker is removed for FR-006 cleanliness but it has no `EntityType` to update.) | open   |
| FR-002 | `implements HasCommunityInterface` clause and the matching `use Waaseyaa\Entity\Tenancy\HasCommunityInterface;` import are removed from each of the 7 entity classes.    | open   |
| FR-003 | A repo-wide search for `HasCommunityInterface` under `src/Entity/` returns zero matches at mission completion.                                                            | open   |
| FR-004 | The existing PHPUnit suite passes end-to-end without behavioral test changes (only mechanical updates allowed where assertions reference the marker directly).            | open   |
| FR-005 | A cold-boot of the local dev server, exercising routes that load each of the 7 entity types, produces zero `tenancy.deprecation` log notices for those types.             | open   |
| FR-006 | A grep across the entire `src/` tree (not only `src/Entity/`) for `HasCommunityInterface` returns zero matches; any consumer code referencing the marker is also updated. | open   |

### Non-Functional

| ID      | Requirement                                                                                                                          | Status |
| ------- | ------------------------------------------------------------------------------------------------------------------------------------ | ------ |
| NFR-001 | PHPUnit suite runtime stays within ±5% of the pre-migration baseline (current baseline ≈ existing CI duration).                       | open   |
| NFR-002 | No new database migrations are introduced — this is a metadata-only declaration change with zero schema impact.                       | open   |

### Constraints

| ID    | Constraint                                                                                                                                                                                                  | Status |
| ----- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------ |
| C-001 | Zero behavioral change permitted: access policies, query scoping, and runtime tenancy decisions for these entities must be byte-identical to pre-migration.                                                  | open   |
| C-002 | Each entity's marker-removal AND provider `tenancy:` addition must land in the same atomic change (commit or PR) — no intermediate state where an entity is missing both signals.                            | open   |
| C-003 | Mission targets framework alpha.173+; explicit `tenancy:` registration depends on framework support that exists in that version.                                                                              | open   |

## Success Criteria

| ID    | Outcome                                                                                                                                                              |
| ----- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| SC-001 | A `grep -r HasCommunityInterface src/` from the project root returns 0 matches.                                                                                      |
| SC-002 | `./vendor/bin/phpunit` exits 0 with the same passing test count as pre-migration baseline (no test removal).                                                         |
| SC-003 | Cold-boot log scan across routes exercising the 7 entity types emits 0 `tenancy.deprecation` notices for those types.                                                |
| SC-004 | The next composer update of `waaseyaa/*` past alpha.173 does not regress on tenancy-related framework warnings or hard breaks tied to marker removal.                |

## Key Entities

The 7 entities in scope, with the providers that own their `EntityType`
registration (per `CLAUDE.md` "Entity provider ownership"):

| Entity        | File                       | Provider                                |
| ------------- | -------------------------- | --------------------------------------- |
| Group         | `src/Entity/Group.php`     | `EntityCommunityProvider`               |
| OralHistory   | `src/Entity/OralHistory.php` | `EntityContentProvider`                |
| Teaching      | `src/Entity/Teaching.php`  | `EntityContentProvider`                 |
| Event         | `src/Entity/Event.php`     | `EntityContentProvider`                 |
| Leader        | `src/Entity/Leader.php`    | `EntityCommunityProvider`               |
| Contributor   | `src/Entity/Contributor.php` | `EntityCommunityProvider`             |
| Post          | `src/Entity/Post.php`      | `EntityFoundationProvider`              |

Provider mapping is provisional and must be confirmed during planning by
inspection of the `entityType()` calls in each provider; the audit-grep that
seeded this list found marker usage in the 7 entity classes but did not
verify their registration site.

## Assumptions

1. The framework's `tenancy: ['scope' => 'community']` array key is consumed by
   the same `EntityType` constructor path that Minoo's existing entity
   registrations already flow through — no provider-level plumbing is
   required.
2. No external consumer (CLI script, API serializer, third-party package)
   reflects on `HasCommunityInterface` at runtime; the marker is
   read only by the framework's tenancy resolver.
3. Test fixtures and integration tests do not assert on the marker via
   `instanceof HasCommunityInterface`; if they do, those assertions can be
   replaced with explicit-declaration checks.
4. The framework continues to honor explicit `tenancy:` declarations in
   alpha.174+ (no parallel deprecation in flight).

Each assumption will be verified during planning. Any that proves false
graduates to a `[NEEDS CLARIFICATION]` and is escalated before tasks
materialize.

## Out of Scope

- ConsoleKernel `SqliteEmbeddingStorage` repair (upstream framework concern).
- Public kernel boot API (upstream framework concern).
- Migration of non-community tenancy patterns (none exist in Minoo today).
- Adding new tests beyond what behavior preservation requires.
- Refactoring entity class hierarchies, field definitions, or access policies
  while the marker is in flight.

## Bulk Edit Declaration

Per `spec-kitty-bulk-edit-classification`, this mission is a bulk edit
because the same identifier (`HasCommunityInterface`) is removed across
multiple files. `meta.json` carries `change_mode: "bulk_edit"`. Plan phase
must produce an `occurrence_map.yaml` enumerating every grep hit and
classifying each occurrence (remove vs preserve vs irrelevant).
