# Research: Migrate community marker to explicit tenancy

## Decision 1 — Target declaration shape

**Decision**: `tenancy: ['scope' => 'community']` passed as a named argument
to the `EntityType` constructor.

**Rationale**: Verified against the framework source at
`/home/jones/dev/waaseyaa/packages/entity/src/EntityType.php`:

- Constructor signature includes `array{scope: string}|null $tenancy` with
  default `null` (= non-tenant).
- Runtime validation rejects:
  - `array` shapes missing the `scope` key
    (`InvalidArgumentException`: *"must contain a 'scope' key …"*)
  - `array` shapes with extra keys
    (`InvalidArgumentException`: *"accepts only the 'scope' key …"*)
  - any `scope` value other than `'community'`
    (`InvalidArgumentException`: *"scope … is not supported; only
    'community' is recognized today"*)
- The class exposes `EntityType::TENANCY_SCOPE_COMMUNITY = 'community'`
  for callers that prefer the constant; literal strings are equally valid.
- `EntityTypeManager.php:202` emits a deprecation message specifying this
  exact form.

**Alternatives considered**:
- A static `tenancyScope()` method on the entity class — rejected: the
  framework's contract is constructor-side metadata on `EntityType`, not
  the entity class. Mixing the two would re-introduce the marker problem
  one layer up.
- Using the `EntityType::TENANCY_SCOPE_COMMUNITY` constant in registrations
  — equivalent functionally; we'll use the literal `'community'` string
  for grep-ability, matching the framework's own deprecation message.

## Decision 2 — Single-pass marker removal

**Decision**: Each WP both adds the `tenancy:` declaration AND removes
`implements HasCommunityInterface` plus the `use` import in the same
change. No two-step "compatibility window."

**Rationale**: The framework's deprecation message (`EntityTypeManager.php`
lines 175–202) is informational only — runtime behavior is unchanged
whether the marker exists or not, as long as the explicit declaration is
present. Leaving the marker in place serves no purpose and only adds noise
to a future cleanup sweep.

**Alternatives considered**:
- Add `tenancy:` first, remove marker in a second mission — rejected per
  user direction (prefers single-pass; cleanup-deferred-indefinitely is
  the worse failure mode here).

## Decision 3 — Verification strategy

**Decision**: Existing PHPUnit suite + cold-boot log scan. No new
behavior-preservation tests authored.

**Rationale**: Minoo's existing 914-test suite already exercises tenancy
behavior end-to-end (community-scoped queries, access policies, seed data,
integration smoke). If those tests pass and the cold-boot log emits zero
deprecation notices for the 7 entity types, behavior is preserved.

**Alternatives considered**:
- Per-entity tenancy:-resolution tests — adds 7 mechanical tests that
  duplicate what the framework's own validation throws on misuse.
  Rejected as defensive over-engineering.

## Decision 4 — WP sequencing

**Decision**: Three WPs grouped by owning provider:
- WP01 — `EntityCommunityProvider` (Group, Leader, Contributor)
- WP02 — `EntityContentProvider` (OralHistory, Teaching, Event)
- WP03 — `EntityFoundationProvider` (Post) + final reconciliation

**Rationale**: Each provider owns a disjoint registration site, making the
WPs independently shippable. The cluster pattern mirrors the recently
completed `migrate-controllers-explicit-route-attributes-01KQYNX7`
mission, which the team already validated as a workflow.

**Alternatives considered**:
- Single PR / single WP — a 7-entity diff is reviewable but loses parallel
  workflow; rejected to keep per-WP review surface small.
- Per-entity WP (7 WPs) — overkill given each entity touches ~3 lines.
  Rejected to avoid bookkeeping bloat.

## Decision 5 — Provider-ownership verification

**Decision**: WP01 includes a verification pass that confirms each of the
7 entity classes is registered in the provider listed in `data-model.md`.
If a discrepancy surfaces (e.g., Post turns out to be registered in
`EntityFeedProvider`, not `EntityFoundationProvider`), the WP boundary
adjusts during planning rather than mid-implementation.

**Rationale**: The provider mapping in `CLAUDE.md` is documentation that
can drift from code. A 1-minute grep catches drift before WP boundaries
solidify.

**Alternatives considered**:
- Trust documentation — rejected, drift is the prior expectation given the
  audit already mismatched (4 entities → 7 actual).

## Status

All decisions are locked. No `[NEEDS CLARIFICATION]` markers remain. The
plan is ready for `/spec-kitty.tasks`.
