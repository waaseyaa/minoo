# Minoo SqlEntityQuery `accessCheck(false)` Bypass Audit

**Status:** Living document. Updated whenever a new `accessCheck(false)` call site lands in Minoo.
**Last full audit:** 2026-05-19 (mission `adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7`).

## Why this document exists

The Waaseyaa framework (alpha.181+, mission `sql-entity-query-access-checking-01KRYP15` / #1495) made `SqlEntityQuery::accessCheck(true)` the default and replaced the no-op stub with per-row filtering through `EntityAccessHandler::check($entity, 'view', $account)`. Every entity query that does not bind an account via `EntityQueryInterface::setAccount(?AccountInterface)` must explicitly opt out via `->accessCheck(false)`, and every such opt-out is a documented choice.

This file is Minoo's per-call-site audit. The framework maintains an analogous doc at `../waaseyaa/docs/security/sql-entity-query-access-check-bypass-audit.md`.

If you add a new `accessCheck(false)` call in Minoo, you MUST:

1. Add a row to the table below with a one-line justification and today's date in "Last reviewed".
2. Add an inline comment at the call site referencing this document (when the bypass is non-obvious from surrounding code).

Prefer `->setAccount($account)` over `->accessCheck(false)` whenever the calling code has access to a user account — typically via the `_account` request attribute (set by `Waaseyaa\User\Middleware\SessionMiddleware`) or via `AccountInterface` auto-injected as a constructor or method parameter (per CLAUDE.md gotcha "Controller DI").

## Three legal shapes

Per the framework's pattern, every `getQuery()` call site uses exactly one of:

1. **`->setAccount($account)`** — user-facing reads with an account in scope. PRIMARY pattern in Minoo controllers; ~90 sites use this.
2. **Conditional fallback** — `setAccount($account)` when present, `accessCheck(false)` otherwise. Rare in Minoo; Auth controllers use this for pre-session lookups.
3. **`->accessCheck(false)`** — system context. This document enumerates every such site.

## Current call sites

### Unconditional bypass — pure system context

These sites never have an end-user `AccountInterface` available, or run after the public-method-level access enforcement has already gated the caller.

| File | Line | Justification | Last reviewed |
|---|---|---|---|
| `src/Console/GenealogyDemoSeedHandler.php` | 29 | Demo-tree seeder; CLI-driven, no request account. Predates the alpha.181 contract. | 2026-05-19 |
| `src/Console/GenealogyDemoSeedHandler.php` | 42 | Same. | 2026-05-19 |
| `src/Console/GenealogyDemoSeedHandler.php` | 132 | Same. Ensures the demo tree is published for anonymous SSR. | 2026-05-19 |
| `src/Console/MessageDigestCommand.php` | 37 | Digest job runs from cron/queue; aggregates participants across all threads to build per-user summaries. Per-user filtering happens at digest-render time, not query time. | 2026-05-19 |
| `src/Console/MessageDigestCommand.php` | 79 | Same digest aggregation — unread-message count per thread. | 2026-05-19 |
| `src/Ingestion/IngestMaterializer.php` | 177 | NorthCloud ingest materializer dedupes existing entities across all communities. CLI-driven; no per-user view restriction makes sense. | 2026-05-19 |
| `src/Ingestion/IngestMaterializer.php` | 197 | Same materializer; ingest-log lookup. | 2026-05-19 |
| `src/Infrastructure/Fixture/FixtureResolver.php` | 30 | Test/seed fixture resolver; resolves entities by name/key for fixture wiring. No user context. | 2026-05-19 |
| `src/Infrastructure/Fixture/FixtureResolver.php` | 34 | Same. | 2026-05-19 |
| `src/Infrastructure/Fixture/FixtureResolver.php` | 58 | Same. | 2026-05-19 |
| `src/Infrastructure/Fixture/FixtureResolver.php` | 76 | Same. | 2026-05-19 |
| `src/Infrastructure/OpenGraph/CrisisOgImageService.php` | 295 | OG image generator runs from cron/queue rendering crisis-page OG cards. | 2026-05-19 |
| `src/Infrastructure/OpenGraph/PublicOgEntityLoader.php` | 23 | OG card lookup for public entities; called from cron and SSR for unauthenticated visitors. Public entities are by design viewable. | 2026-05-19 |
| `src/Infrastructure/OpenGraph/PublicOgEntityLoader.php` | 54 | Same. | 2026-05-19 |
| `src/Infrastructure/OpenGraph/PublicOgEntityLoader.php` | 80 | Same. | 2026-05-19 |
| `src/Domain/Feed/EntityLoaderService.php` | 25 | Public-content listing service; loads upcoming events for the home/feed pages. Public entities are intentionally visible to all. Service is called from controllers (WP02/WP03) that have already bound their direct queries; the service-layer bypass is the v1 pragmatic trade-off documented in the WP04 commit message. | 2026-05-19 |
| `src/Domain/Feed/EntityLoaderService.php` | 51 | Loads public groups for home/feed listings. | 2026-05-19 |
| `src/Domain/Feed/EntityLoaderService.php` | 63 | Loads public businesses for home/feed listings. | 2026-05-19 |
| `src/Domain/Feed/EntityLoaderService.php` | 75 | Loads public people for home/feed listings. | 2026-05-19 |
| `src/Domain/Feed/EntityLoaderService.php` | 98 | Loads active featured items (editorial). | 2026-05-19 |
| `src/Domain/Feed/EntityLoaderService.php` | 152 | Loads recent posts for feed; controller-layer bind in `Social/EngagementController` enforces post-level visibility on creation. | 2026-05-19 |
| `src/Domain/Feed/EntityLoaderService.php` | 164 | Same posts surface. | 2026-05-19 |
| `src/Domain/Feed/EngagementCounter.php` | 49 | Reaction count for a target — system-level aggregate; values are public per `EngagementAccessPolicy`. | 2026-05-19 |
| `src/Domain/Feed/EngagementCounter.php` | 56 | Comment count for a target — same. | 2026-05-19 |
| `src/Domain/Feed/Scoring/EngagementCalculator.php` | 55 | Feed-scoring engagement aggregation; computes per-target counts for ranking. System aggregate. | 2026-05-19 |
| `src/Domain/Feed/Scoring/AffinityCalculator.php` | 126 | Per-user follows lookup for affinity scoring. Cached behind user-keyed cache; no leak of cross-user data to caller. | 2026-05-19 |
| `src/Domain/Feed/Scoring/AffinityCalculator.php` | 158 | Per-user interaction counts. Same caching model. | 2026-05-19 |
| `src/Domain/Events/Service/EventFeedBuilder.php` | 229 | Public calendar grid build; events are public per `EventAccessPolicy`. | 2026-05-19 |
| `src/Domain/Events/Service/EventFeedBuilder.php` | 304 | Same builder; horizon window lookup. | 2026-05-19 |
| `src/Domain/Events/Service/EventFeedBuilder.php` | 322 | Same builder; featured events. | 2026-05-19 |
| `src/Domain/Events/Service/EventFeedBuilder.php` | 383 | Same builder; community lookup. | 2026-05-19 |
| `src/Domain/Geo/Service/LocationService.php` | 91 | Community lookup by UUID; geocoding service runs in background contexts (cron, system jobs). | 2026-05-19 |
| `src/Domain/Geo/Service/LocationService.php` | 183 | Loads all communities for proximity calculation — system aggregation. | 2026-05-19 |
| `src/Http/Controller/Home/HomeController.php` | 57 | Featured items loader (private helper). HomeController.index() has the request `$account` but the helper extracts featured/upcoming/recent surfaces that are public; bypass matches the EntityLoaderService pattern for public listings. | 2026-05-19 |
| `src/Http/Controller/Home/HomeController.php` | 93 | Upcoming events loader (private helper). Public listing. | 2026-05-19 |
| `src/Http/Controller/Home/HomeController.php` | 112 | Recent teachings loader (private helper). Public listing. | 2026-05-19 |
| `src/Http/Controller/Community/CommunityController.php` | 319 | `findNearbyCommunities` private helper. Community proximity is public per `CommunityAccessPolicy`. | 2026-05-19 |
| `src/Http/Controller/Feed/FeedController.php` | 125 | `buildTrending` private helper — trending aggregation across all posts; per-row policy applied at render. | 2026-05-19 |
| `src/Http/Controller/Feed/FeedController.php` | 204 | `buildUpcomingEvents` private helper — public events. | 2026-05-19 |
| `src/Http/Controller/Feed/FeedController.php` | 269 | `buildSuggestedCommunities` private helper — public communities. | 2026-05-19 |
| `src/Http/Controller/Social/MessagingController.php` | 698 | `isThreadOwner` helper called after `isParticipant` check; post-authorization participant lookup. | 2026-05-19 |
| `src/Http/Controller/Social/MessagingController.php` | 727 | `participantsForThread` helper called after `isParticipant` check. | 2026-05-19 |
| `src/Http/Controller/Social/MessagingController.php` | 738 | `participantsForUser` helper called after public-method auth check. | 2026-05-19 |
| `src/Http/Controller/Social/MessagingController.php` | 748 | `countUnreadMessages` helper called after `isParticipant` check. | 2026-05-19 |
| `src/Http/Controller/Social/MessagingController.php` | 772 | `latestMessageForThread` helper called after `isParticipant` check. | 2026-05-19 |
| `src/Http/Controller/Games/CrosswordController.php` | 588 | `generateFallbackDaily` — server-side fallback puzzle generator; runs without per-request account. | 2026-05-19 |
| `src/Http/Controller/Games/MatcherController.php` | 228 | `loadDictionaryEntries` — bootstrap content for the matcher game; dictionary entries are intentionally public. | 2026-05-19 |
| `src/Http/Controller/Games/ShkodaController.php` | 338 | `selectRandomWord` — random-word selection from the dictionary for game start; public content. | 2026-05-19 |
| `src/Http/Controller/Dashboard/CoordinatorDashboardController.php` | 167 | `loadVolunteerByUuid` — coordinator-only dashboard; admin role already enforced at routing. | 2026-05-19 |
| `src/Http/Controller/Ingestion/IngestionDashboardController.php` | 62 | `loadRecentLogs` — admin-only ingest log view; admin role enforced at routing. | 2026-05-19 |
| `src/Http/Controller/Ingestion/IngestionDashboardController.php` | 90 | `countLogs` — admin-only aggregate. | 2026-05-19 |
| `src/Http/Controller/Ingestion/IngestionDashboardController.php` | 101 | `loadLastSync` — admin-only last-sync timestamp. | 2026-05-19 |
| `src/Http/Controller/People/VolunteerController.php` | 151 | `phoneExists` private uniqueness check — phone numbers cannot be duplicated across volunteers regardless of caller's view scope. Mirrors the framework's `RelationshipValidator` pattern (integrity check spans access boundaries). | 2026-05-19 |

### Conditional fallback — set account when available, bypass otherwise

Currently empty in Minoo. The Auth controller's pre-session lookups use the conditional fallback pattern in `AuthController::submitLogin` — if a future audit identifies that specific bypass branch line, it belongs here.

## How to audit

To regenerate the bypass list:

```bash
grep -rnE 'accessCheck\(false\)' src/ 2>/dev/null
```

For each result, decide:

- **Keep (unconditional bypass)**: system context — runs without a user, or runs after authorization has already been enforced upstream. Add a row above.
- **Keep (conditional fallback)**: may or may not have an account. Add to the conditional-fallback table.
- **Switch**: user-facing — replace with `->setAccount($account)` (or thread the account through the call chain).

## Parity check

The number of rows in this document's bypass tables must equal:

```bash
grep -rcE 'accessCheck\(false\)' src/ 2>/dev/null | awk -F: '{sum += $2} END {print sum}'
```

As of 2026-05-19: **53 bypass call sites**, all documented above.

## Future automation

A CI grep gate that fails on new `accessCheck(false)` without an audit-doc row is a candidate follow-up. Not implemented in v1.

## Related

- Framework audit: `../waaseyaa/docs/security/sql-entity-query-access-check-bypass-audit.md`
- Framework mission: `../waaseyaa/kitty-specs/sql-entity-query-access-checking-01KRYP15/`
- Minoo mission: `kitty-specs/adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7/`
- Follow-up: a future hardening pass can thread `$account` through service signatures (Domain/Infrastructure) to add a second access-check layer beyond the controller-level bind. See spec §"Out of scope" notes.
