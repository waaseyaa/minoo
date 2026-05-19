# Research: Adopt Waaseyaa alpha.182 Access-Checking Contract

**Mission**: `01KS0WZ7MX6P96NP0V95RBTPG2` (`mid8: 01KS0WZ7`)
**Date**: 2026-05-19
**Phase**: 0 (research)

This document records the three load-bearing decisions for this mission. Each is keyed to the spec's requirements and the upstream framework's authoritative artifacts.

---

## Decision 1: Adopt the framework's three-shape classification verbatim

**Decision**: Every `getQuery()` call site in Minoo is converted to exactly one of these three shapes — no Minoo-specific variations.

1. **`->setAccount($account)`** — user-facing reads where an account is in scope.
2. **Conditional fallback** — `->setAccount($account)` when the controller has an account, `->accessCheck(false)` otherwise (used for surfaces that may be invoked from a CLI driver path or with an unauthenticated visitor).
3. **`->accessCheck(false)`** — unconditional system-context bypass (seeders, save-time validators, integrity checks, system lookups).

**Rationale**:
- Mirrors the upstream pattern in `../waaseyaa/docs/security/sql-entity-query-access-check-bypass-audit.md` so reviewers and future maintainers see the same vocabulary in both repos.
- The framework has already audited 17 call sites in its own packages (see the same doc) and converged on these three shapes — extending the pattern is cheap; inventing a fourth shape (try/catch the exception, default to empty result, etc.) would diverge from the framework and obscure intent.
- Three shapes map cleanly to the three actual runtime contexts in Minoo: HTTP request with auth (shape 1), HTTP request that may or may not have auth (shape 2), and "no request at all" (shape 3).

**Alternatives considered**:
- *Try/catch `MissingQueryAccountException` and substitute an empty result.* Rejected: silences real bugs, defeats the fail-closed posture, hides "I forgot to bind an account" from operators.
- *Wrap `getQuery()` in a Minoo-specific helper that auto-binds the request's account.* Rejected: introduces an abstraction the framework doesn't have, makes review harder, gives future readers two patterns to learn.
- *Disable access checking globally via a kernel-level toggle.* Rejected: gives up the security improvement the upgrade exists to deliver; matches the framework's deprecated `accessCheck(false)` no-op posture from alpha.180 verbatim.

**References**:
- `../waaseyaa/CHANGELOG.md` § `[0.1.0-alpha.181]` → "Per-row access enforcement at the query layer".
- `../waaseyaa/docs/security/sql-entity-query-access-check-bypass-audit.md` (read in full during specify phase; 98 lines).

---

## Decision 2: Account threading via constructor DI (`AccountInterface $account`)

**Decision**: Controllers that need to bind an account take `AccountInterface` as a constructor parameter, store it on `$this->account`, and call `->setAccount($this->account)` on every query. Domain services accept the account as a method parameter on entry points and thread it down.

**Rationale**:
- Per CLAUDE.md gotcha "Controller DI": `SsrPageHandler::resolveControllerInstance()` already auto-injects `AccountInterface` from its hardcoded `$serviceMap`. Adding `AccountInterface $account` to a constructor signature is free — the framework wires it up without provider changes.
- For anonymous visitors, framework `SessionMiddleware` resolves an anonymous-shaped `AccountInterface` (the framework's anonymous user). `setAccount($anonymousAccount)` is always valid; there is no null-account branch to handle in controllers.
- Constructor DI keeps the bind call site identical across queries within a controller (`->setAccount($this->account)`) — easier to grep, easier to review.
- For domain services like `EntityLoaderService`, controllers pass the account as a method parameter (e.g. `loadPostsForAccount(AccountInterface $account, int $limit)`). The framework's `EntityLoaderService` does not need DI access to the request; the controller decides which account to load on behalf of.

**Alternatives considered**:
- *Fetch `_account` from `Request` per query* (`$request->attributes->get('_account')`). Rejected for controllers because constructor DI is cleaner; accepted for surfaces that already have `Request` in scope but no constructor `AccountInterface` (rare).
- *Static accessor (`AccountContext::current()`).* Rejected: introduces ambient state, defeats DI testability, not used by the framework.
- *Inject `AccountInterface` only where currently absent; otherwise leave existing controllers alone.* Adopted as default — controllers that already have `AccountInterface` injected for other reasons keep using their existing field.

**Anonymous account verification**: Confirm during WP01 smoke that an anonymous request to `/` resolves an `AccountInterface` instance into `_account` (rather than `null`). If the framework's `SessionMiddleware` returns null for anonymous, we adjust shape (1) to a small null-coalescing guard. Expected: no null based on framework's mission #1495 which specifically designed for anonymous bind.

**References**:
- Minoo CLAUDE.md → §"Gotchas" → "Controller DI" entry.
- Framework `packages/auth/src/Middleware/SessionMiddleware.php` (anonymous-account resolution).
- Framework `packages/api/src/JsonApiController.php` lines 50–80 (canonical bind pattern with `_account` request attribute).

---

## Decision 3: Mission branch sequencing — bump first, fix on top

**Decision**: WP01 bumps `composer.json` + `composer.lock` to alpha.182. WPs 02–05 land binding/bypass fixes on top of the bumped lockfile. WP06 closes out docs. `main` stays green throughout because every WP lives on the mission branch until the mission is approved.

**Rationale**:
- **C-001 forces this order**: `EntityQueryInterface::setAccount()` does not exist on alpha.180 — pre-staging `->setAccount(...)` calls under the alpha.180 lock would be a fatal class error at autoload time, blocking even the test bootstrapper. Bump must come first.
- The framework's own mission `sql-entity-query-access-checking-01KRYP15` followed the same pattern (5 WPs on one mission branch, flipped the default in the final WP). We mirror that shape.
- Spec Kitty's mission-branch model is designed for exactly this: the mission branch absorbs in-progress red; only the final squash-merge to `main` runs the full quality gate.

**Alternatives considered**:
- *Bump last (do all binding fixes first under alpha.180, bump in WP06).* Rejected as unbuildable per the rationale above — `setAccount()` literally doesn't exist as a method.
- *Bump in a separate "preparation PR" merged to main, then start the audit on a fresh mission branch.* Rejected: that preparation PR would itself be red (every getQuery() site would throw on first request), violating C-002. We'd have to merge a known-broken state to `main`. Worse than the proposed approach.
- *Parallelize WPs 02–05 across lanes.* Allowable but not required by planning. The 4 WPs touch disjoint files, so lanes are technically safe. Starting sequential reduces merge-conflict surface; the implement-review loop can convert to parallel lanes if the human operator wants to compress the timeline.

**Mitigation for the red period on the mission branch**:
- Each WP includes its own targeted PHPUnit selection (e.g. `--filter Controller\\Community`) so the WP can prove its slice is green even while the rest of the mission branch is not.
- The mission-level gate (`./vendor/bin/phpunit`, `composer phpstan`, `composer cs-fixer`, body-size smoke) runs only at mission-review time, not per-WP.

**References**:
- Spec Kitty mission-branch model: `../waaseyaa/kitty-specs/sql-entity-query-access-checking-01KRYP15/` (the framework's own application of this pattern).
- Spec Kitty git workflow skill: `superpowers:using-git-worktrees` + `spec-kitty-git-workflow`.

---

## Open questions resolved during planning

| Question | Resolution |
|---|---|
| Does `setAccount()` exist on alpha.180's `EntityQueryInterface`? | No — confirmed via changelog "added in alpha.181 (#1495)". Forces bump-first ordering (C-001). |
| Does framework's `SessionMiddleware` resolve an anonymous account or null? | Anonymous instance per framework mission #1495 design. Smoke verified in WP01. |
| Are there schema migrations bundled with alpha.181/.182? | No — pure behavior change. The `_data` JSON blob and migration tables are unchanged. |
| Does the bump affect MCP servers (`minoo-specs`, `bimaaji`)? | No — node-side artifacts in `mcp/` are untouched by alpha.181/.182. C-006 satisfied. |
| Does `composer phpstan-dead-code` need re-baselining after the bump? | Possibly. Will check in WP06. Out of scope is *adopting* the Phase 4 fail-on-new gate (C-005); maintaining the existing warn-only run is in scope. |

## Items deferred to follow-up issues (out of scope)

Per spec FR-013, one GitHub issue per surface, each filed in WP06:

1. **#1496 trackeR** — adopt AI agent executor (`bin/waaseyaa ai:run`, `/api/ai/agent/*`, persisted `AgentRun`/`AgentAuditLog`). Significant scope; not needed for current Minoo product surface.
2. **#1499 tracker** — enable 2FA endpoints (`POST /api/auth/2fa/{setup,enable,verify,disable}`) and wire to the Minoo SSR auth UI. Capability already in `User` entity via two `#[Field]` properties — wiring the SSR side and providing recovery-code download is the gap.
3. **#1500 tracker** — adopt the dead-code Phase 4 fail-on-new gate. Requires Minoo's existing `phpstan-dead-code-baseline.neon` to be re-attested and a new CI job added.
