# HTTP layer: controllers and routing

Minoo registers routes in `src/Provider/Routing/*RouteProvider.php`. Each route’s `->controller('…')` string is a **fully qualified class name** plus `::method`.

## Grouping rule

**One domain segment under `src/Http/Controller/{Domain}/` per route bundle** — pick the provider (or section of a provider) that owns the traffic, and keep the controller namespace aligned:

| Directory | Namespace | Primary route providers / surface |
|-----------|-----------|-----------------------------------|
| `Auth/` | `App\Http\Controller\Auth` | `AuthApiRouteProvider` |
| `Account/` | `App\Http\Controller\Account` | `PublicAccountRouteProvider` |
| `Home/` | `App\Http\Controller\Home` | `PublicHomeFeedRouteProvider` (entry `/`, `/home`) |
| `Feed/` | `App\Http\Controller\Feed` | `PublicHomeFeedRouteProvider` (`/feed`, feed API) |
| `Events/` | `App\Http\Controller\Events` | `PublicContentRouteProvider` (events) |
| `Groups/` | `App\Http\Controller\Groups` | `PublicContentRouteProvider` (groups + businesses) |
| `Teachings/` | `App\Http\Controller\Teachings` | `PublicContentRouteProvider` |
| `Language/` | `App\Http\Controller\Language` | `PublicContentRouteProvider` |
| `OpenGraph/` | `App\Http\Controller\OpenGraph` | `PublicContentRouteProvider` (OG image actions) |
| `Community/` | `App\Http\Controller\Community` | `PublicCommunityRouteProvider` (communities, location API, contributors) |
| `OralHistory/` | `App\Http\Controller\OralHistory` | `PublicCommunityRouteProvider` |
| `People/` | `App\Http\Controller\People` | `PublicCommunityRouteProvider` (people directory, volunteer signup) |
| `ElderSupport/` | `App\Http\Controller\ElderSupport` | `PublicCommunityRouteProvider` + coordinator/volunteer workflows |
| `Games/` | `App\Http\Controller\Games` | `GamesApiRouteProvider` + game-related shortcuts in `NewsletterApiRouteProvider` |
| `Social/` | `App\Http\Controller\Social` | `SocialApiRouteProvider` (engagement, messaging, blocks) |
| `Chat/` | `App\Http\Controller\Chat` | `SocialApiRouteProvider` (AI chat send) |
| `Newsletter/` | `App\Http\Controller\Newsletter` | `NewsletterApiRouteProvider`, `AdminRouteProvider` (admin newsletter API) |
| `Ingestion/` | `App\Http\Controller\Ingestion` | `AdminRouteProvider` (`/staff/ingestion`, staff APIs) |
| `Dashboard/` | `App\Http\Controller\Dashboard` | `AdminRouteProvider` (volunteer/coordinator dashboards, role management) |
| `Site/` | `App\Http\Controller\Site` | `NewsletterApiRouteProvider` (static marketing/info pages), misc |

**Adding a controller:** choose the directory that matches the route provider (or the dominant domain for that URL). Register the class in `composer` PSR-4 under `App\` → `src/` (already true); no extra autoload entry is needed.

## Project root paths

Controllers live under `src/Http/Controller/{Domain}/` (one extra directory compared to a flat `Controller/` tree). Any code that resolves the repository root from `__DIR__` must account for that depth: use **`dirname(__DIR__, 4)`** from a file in `…/Controller/{Domain}/` (Domain → Controller → Http → `src` → project root). Older snippets that used `dirname(__DIR__, 3)` applied when classes sat directly in `src/Http/Controller/`.

## Shared code

- `src/Http/Controller/Concerns/` — cross-cutting controller traits (`JsonResponseTrait`, etc.). Any domain controller may `use` these; **do not** move domain-specific traits here unless they are reused across domains.
- `src/Http/Controller/Games/GameControllerTrait.php` — game-only shared helpers; lives under `Games/` with the game controllers.

## Tests

Unit tests mirror the same domain folders under `tests/App/Unit/Http/Controller/{Domain}/` with namespaces `App\Tests\Unit\Http\Controller\{Domain}`.
