# WP01 Review — Cycle 1: REJECTED

## Verdict: rejected

## Summary

WP01 correctly added `tenancy: ['scope' => 'community']` to the 4 EntityType registrations in `EntityContentProvider.php` (lines 120, 327, 363, 477) and removed the `HasCommunityInterface` `implements` clause from `Post`, `Leader`, `Contributor`, and `OralHistory`. However, the implementer **also removed the `use HasCommunityTrait;` line and the trait import** from each of the 4 entity classes — a deviation NOT in the WP plan.

This trait removal is **unsafe** and breaks the test suite.

## Evidence of regression

`HasCommunityTrait` (vendor/waaseyaa/entity/src/Community/HasCommunityTrait.php) is NOT a no-op marker. It provides two runtime methods:

- `public function getCommunityId(): ?string`
- `public function setCommunityId(string $communityId): void`

Four existing unit tests in this repo invoke those methods on the WP01-modified entities:

- `tests/App/Unit/Entity/PostHasCommunityTest.php` (3 tests)
- `tests/App/Unit/Entity/OralHistoryHasCommunityTest.php` (3 tests)
- `tests/App/Unit/Entity/LeaderHasCommunityTest.php` (3 tests)
- `tests/App/Unit/Entity/ContributorHasCommunityTest.php` (3 tests)

After running `composer dump-autoload && rm -f storage/framework/packages.php` to defeat stale autoload caches, `./vendor/bin/phpunit --filter PostHasCommunity` produces:

```
Error: Call to undefined method App\Entity\Post::getCommunityId()
  tests/App/Unit/Entity/PostHasCommunityTest.php:26
Error: Call to undefined method App\Entity\Post::setCommunityId()
  tests/App/Unit/Entity/PostHasCommunityTest.php:33
Failed asserting that an object is an instance of interface
  Waaseyaa\Entity\Community\HasCommunityInterface.
  tests/App/Unit/Entity/PostHasCommunityTest.php:19

Tests: 3, Assertions: 1, Errors: 2, Failures: 1.
```

The same failure mode applies to the Leader/Contributor/OralHistory tests by identical structure (12 broken tests total).

The implementer's earlier "15 tests passing" report was a false positive caused by a stale autoload classmap from a prior worktree run. A clean `composer dump-autoload` exposes the regression.

## FR check

- **FR-001 (provider tenancy literal):** PASS — exactly `tenancy: ['scope' => 'community']` at lines 120, 327, 363, 477. Strict-shape compliant.
- **FR-002 (marker removal):** PASS for the `implements HasCommunityInterface` and the interface `use` import removal.
- **FR-004 (PHPUnit clean):** **FAIL** — 4 entity tests now error with "undefined method getCommunityId/setCommunityId" and 4 instanceof assertions fail.
- **FR-005 (cold-boot clean):** Cannot verify in good faith while FR-004 is broken.

## Other findings

- Diff scope is otherwise clean: only the 5 owned files changed, single commit (`7111041`), atomic.
- The "pre-existing" `IngestionDashboardControllerTest` failure was not investigated because the entity-test regression above is sufficient to reject.

## Required changes

The migration plan calls for removing the marker interface only. The trait — which carries real behavior — must be either:

1. **Restored as-is.** Add back `use Waaseyaa\Entity\Community\HasCommunityTrait;` (import) and `use HasCommunityTrait;` (inside class body) on `Post`, `Leader`, `Contributor`, `OralHistory`. The trait carries no marker semantics; only the `implements HasCommunityInterface` was deprecated.
   OR
2. **Replaced with explicit accessor methods on the entity** (or moved to an app-level trait) so the existing test contract continues to compile. This is a larger change and likely out of scope for this WP — prefer option 1.

Re-run `composer dump-autoload && ./vendor/bin/phpunit --filter HasCommunity` and confirm 12/12 green before resubmitting.

## Acceptance gating

WP01 cannot proceed to approved while FR-004 is red. Please address and resubmit for cycle-2 review.
