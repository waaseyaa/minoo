# Phase 5 Plan - HasCommunityInterface Reconciliation Via Domain Adapter

Refs minoo#741.

Framework dependency status: Phase 3 landed on `waaseyaa/framework:main` on 2026-04-20, so Phase 5 is not gated on any further framework work. This phase is Minoo-side only and executes the framework ADR at `waaseyaa/framework:docs/superpowers/specs/2026-04-19-groups-reconciliation-adr.md`.

## Why This Lives Here

`docs/superpowers/plans/` is already Minoo's active home for arc-level implementation plans, and `src/App/Domain/...` is already an established namespace (`App\Domain\Events`, `App\Domain\Geo`, `App\Domain\Newsletter`). The plan therefore lands at `docs/superpowers/plans/2026-04-20-phase-5-hascommunity-reconciliation.md`, and the execution work should introduce the adapter under `App\Domain\...` rather than inventing a new top-level convention.

## Read-First Findings

### Framework ADR + canonical Group

- The 2026-04-19 framework ADR chooses a Minoo-side domain adapter instead of lifting `HasCommunityInterface` into framework.
- Canonical `Waaseyaa\Groups\Group` exposes only `getName()`, `setName()`, and `getGroupTypeId()` beyond `ContentEntityBase`; it has no community-specific API.
- Canonical `Waaseyaa\Groups\GroupType` is already the framework truth shape (`id` / `label`), so Phase 5 must not add any framework-side compatibility layer.

### Current Minoo HasCommunity surface

Grep on current `main` shows:

- 7 entity implementations of `HasCommunityInterface`:
  - `src/Entity/Contributor.php`
  - `src/Entity/Event.php`
  - `src/Entity/Group.php`
  - `src/Entity/Leader.php`
  - `src/Entity/OralHistory.php`
  - `src/Entity/Post.php`
  - `src/Entity/Teaching.php`
- 7 trait uses of `HasCommunityTrait` in the same files.
- 7 `HasCommunityInterface::class` test assertions, one per `*HasCommunityTest.php`.
- 27 direct trait-method call lines (`getCommunityId()` / `setCommunityId()`) across those 7 test files.

Group-specific consumers are much smaller than the repo-wide interface footprint:

- Single-group callsites: 9
  - `tests/App/Unit/Entity/GroupHasCommunityTest.php`: 5 lines
  - `src/Controller/BusinessController.php`: 2 lines (`$business->get('community_id')`)
  - `src/Controller/GroupController.php`: 2 lines (`$group->get('community_id')`)
- Collection callsites: 1 helper line
  - `src/Support/CommunityLookup.php:20`
  - This helper is used by both `BusinessController::list()` and `GroupController::list()`.
- Field-presence checks on `Group` directly: 2
  - `src/Controller/BusinessController.php:110`
  - `src/Controller/GroupController.php:81`

There are many other `community_id` reads in Minoo, but they are for non-Group entities (`event`, `teaching`, `newsletter_*`, `post`, `user`, etc.). Phase 5 should not broaden into a repo-wide community-field rewrite. The production migration is Group-specific; the repo-wide cleanup at the end of the phase is limited to removing `HasCommunityInterface` / `HasCommunityTrait` imports from Minoo entities and rewriting/deleting their dedicated mechanic tests.

### Shadow-class status on Minoo main

- No current `src/`, `config/`, or `bin/` registration path imports `App\Entity\Group` or `App\Entity\GroupType`.
- Current `main` boots successfully with installed dependencies, so Minoo does not trip the Phase 3 shadow-collision guard at boot.
- Shadow imports remain in tests only:
  - `App\Entity\Group` is still imported by 10 test files.
  - `App\Entity\GroupType` is still imported by 1 test file.

No separate Phase 5 pre-task is required for latent duplicate registration cleanup. The guard check is already satisfied on current `main`.

## Adapter Shape

Phase 5 should introduce `App\Domain\GroupCommunity` as a narrow wrapper around canonical `Waaseyaa\Groups\Group`.

Minimum shape:

```php
namespace App\Domain;

use App\Entity\Community;
use Waaseyaa\Groups\Group;

final class GroupCommunity
{
    public function __construct(private readonly Group $group) {}

    public function hasCommunity(): bool;

    public function getCommunityId(): ?string;

    public function community(/* resolver dependency if needed */): ?Community;
}
```

Notes:

- Construction is per-group: `new GroupCommunity($group)`.
- Delegation target is the wrapped canonical `Waaseyaa\Groups\Group`.
- The adapter owns the only allowed field-name lookup for Group community membership.
- The adapter should centralize null-handling, ID normalization, and entity loading so controllers/tests stop duplicating those rules.
- If community entity resolution still needs storage lookup, pass that dependency at call time or through a small collaborator owned by the adapter implementation; do not make callers construct the adapter with anything other than the wrapped Group.

### Exhaustive method mapping from current interface + trait surface

Current surface to preserve intentionally:

- `getCommunityId(): ?string`
  - Adapter keeps this name so existing `HasCommunityTrait` readers have a direct migration target.
- `setCommunityId(string $communityId): void`
  - Do not carry this into the adapter.
  - Reason: Phase 5 is about read/reconciliation for canonical `Waaseyaa\Groups\Group`, not introducing a mutable compatibility shell around storage writes.
  - Write sites should continue to set the underlying group field directly where entity creation/update is the concern.
- New predicate/retrieval helpers required by the ADR:
  - `hasCommunity(): bool`
  - `community(): ?Community`

That means the final execution PR should preserve exactly one trait-era read method (`getCommunityId()`) and add exactly two domain methods (`hasCommunity()`, `community()`). No hidden convenience methods should appear.

### Important implementation note

The framework ADR text talks about a `community` bundle field, but Minoo `main` currently registers Group's business bundle field as `community_id` in `AppServiceProvider::groupBusinessBundleFields()`. Phase 5 should therefore treat the adapter as the only translation boundary and explicitly document which underlying field name it reads on current Minoo. This is the main place where execution could otherwise drift.

## Callsite Migration Strategy

Every `HasCommunityInterface` / trait-era Group callsite should land in one of these buckets.

### A. Single group sites -> `new GroupCommunity($group)`

Count from grep: 9 lines across 3 files.

Targets:

- `tests/App/Unit/Entity/GroupHasCommunityTest.php`
  - Replace interface/trait mechanics assertions with adapter behavior assertions.
  - Expected rewrite: instantiate canonical `Waaseyaa\Groups\Group`, wrap it, assert `getCommunityId()`, `hasCommunity()`, and `community()` behavior.
- `src/Controller/BusinessController.php`
  - Replace the linked-community block with the adapter.
  - The direct predicate `if ($business !== null && $business->get('community_id'))` becomes adapter-driven.
- `src/Controller/GroupController.php`
  - Replace the related-content lookup gate with the adapter's `getCommunityId()` / `hasCommunity()`.

### B. Collection sites -> bulk adapter pass

Count from grep: 1 helper line, used by 2 controller list pages.

Targets:

- `src/Support/CommunityLookup.php`
  - This is the single collection helper reading `community_id` across entity lists.
  - For Group/business collections, it should bulk-wrap each group and read via the adapter instead of touching the field directly.
- `BusinessController::list()` and `GroupController::list()`
  - No controller-specific logic should remain once `CommunityLookup` owns the collection migration.

### C. Field-presence checks on Group directly -> go through the adapter

Count from grep: 2 direct predicate sites.

Targets:

- `src/Controller/BusinessController.php:110`
- `src/Controller/GroupController.php:81`

These should stop doing "truthy field exists" checks on the Group object. The adapter becomes the only place that knows how to determine whether a Group is community-linked.

## Interface + Trait Retirement

Retirement plan:

1. Migrate all Group-specific production/test callsites to `App\Domain\GroupCommunity`.
2. After those callsites are green, land a dedicated cleanup PR that removes `HasCommunityInterface` / `HasCommunityTrait` imports from all 7 Minoo entities:
   - `Contributor`
   - `Event`
   - `Group`
   - `Leader`
   - `OralHistory`
   - `Post`
   - `Teaching`
3. In that same cleanup PR:
   - delete `src/Entity/Group.php`
   - remove or rewrite the 7 dedicated `*HasCommunityTest.php` files
   - keep framework package code untouched

Important scope clarification:

- On current `main`, `HasCommunityInterface` and `HasCommunityTrait` live in `waaseyaa/entity`, not in Minoo.
- Because Phase 5 is Minoo-side only, "retirement" here means "remove all Minoo references/imports and the shadow Group dependency", not "delete framework package code".

Deletion sequencing choice:

- Do the actual interface/trait retirement in its own final cleanup PR after the production Group adapter migrations.
- That final cleanup PR should also delete the shadow Group class, because once the adapter-backed callsites are complete the remaining risk is test/mechanics cleanup, not runtime behavior.

## Test Coverage Strategy

Sample grep counts for tests coupled to interface/trait mechanics:

- 7 `assertInstanceOf(HasCommunityInterface::class, ...)` lines
- 27 `getCommunityId()` / `setCommunityId()` call lines
- 7 dedicated `*HasCommunityTest.php` files overall
- Group-specific shadow-class imports still appear in 10 test files

Phase 5 execution should:

- Rewrite `GroupHasCommunityTest` into adapter behavior coverage.
- Delete or repurpose the other dedicated `*HasCommunityTest.php` files once their entities drop the interface/trait.
- Update Group-using unit/integration tests to instantiate canonical `Waaseyaa\Groups\Group` once the shadow class is removed.
- Keep Group bundle-routing coverage (`GroupBundleRoutingTest`) focused on storage semantics, not interface mechanics.

Kernel-touching test rule:

- Phase 5 execution must use the Phase 1 `publicBoot()` harness pattern rather than fresh reflection-based boot access.

Current-state caveat:

- Current Minoo tests still boot kernels with `ReflectionMethod(AbstractKernel::class, 'boot')` in 12 places.
- `HttpKernel` and `ConsoleKernel` are `final`, so the execution PR will need a dedicated helper/harness that exposes `publicBoot()` without expanding reflection usage further.

## Task Sequence

Each task should be independently reviewable and shippable.

1. Add `App\Domain\GroupCommunity` and its focused unit tests.
   - No shadow-class deletion yet.
   - This PR proves the adapter API and its field/entity resolution rules.

2. Migrate single-group production callsites.
   - `BusinessController::show()`
   - `GroupController::show()`
   - Any direct Group predicate/read logic should now go through the adapter.

3. Migrate collection helpers.
   - `CommunityLookup`
   - List-page callers become adapter-backed indirectly.

4. Rewrite Group-specific tests from interface/trait mechanics to adapter/canonical-Group behavior.
   - Replace `App\Entity\Group` test construction with canonical `Waaseyaa\Groups\Group` where possible.
   - Normalize kernel-touching tests onto the Phase 1 boot harness.

5. Land a final cleanup PR that retires `HasCommunityInterface` / `HasCommunityTrait` from Minoo.
   - Remove the interface/trait from all 7 Minoo entities.
   - Rewrite or delete the 7 dedicated `*HasCommunityTest.php` files.
   - Delete the shadow `App\Entity\Group` class.
   - Verify the Phase 3 guard still stays quiet at boot.

## Known Pitfalls

- `GroupCommunity` is not a universal "this entity is a community-aware thing" marker. It is a Group-only access boundary.
- The adapter catches Group community lookup/read semantics. It does not replace generic entity storage, write flows, or non-Group `community_id` consumers elsewhere in Minoo.
- The adapter must not become a second shadow entity type. Keep it as a small domain wrapper, not a subclass/decorator with broad entity API passthrough.
- The framework ADR and current Minoo code disagree on the literal field name (`community` in the ADR text, `community_id` on current Minoo business bundle fields). The adapter is exactly where that mismatch must be contained.
- Current kernel tests rely on reflection. Phase 5 execution should not cargo-cult that pattern further.

## Out Of Scope

- No framework-side code changes.
- No attempt to move `HasCommunityInterface` into framework packages.
- No repo-wide rewrite of every non-Group `community_id` read in Minoo.
- No re-planning of Phase 6 (`GroupType` entity-key reconciliation) or Phase 7 (shadow-class deletion / minoo#741 closeout) beyond naming the dependency.

## Forward Dependency

Phase 6 (`GroupType` entity-key reconciliation) and Phase 7 (shadow-class deletion, minoo#741) both depend on Phase 5 landing first. This plan does not re-plan those phases; it only establishes the Group-side adapter reconciliation they require.
