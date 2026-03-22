# Social Feed Type Design Analysis

**Branch:** `social-feed-smoke-test`
**Date:** 2026-03-21
**Scope:** 4 entity types + 2 service classes

---

## Type 1: Reaction (`src/Entity/Reaction.php`)

### Invariants Identified
- Must reference a target entity (target_type + target_id)
- Must belong to a user (user_id)
- Emoji field serves as both display value and label key
- created_at defaults to 0 (not current time)
- **MISSING: reaction_type constraint.** The task description says reaction_type is constrained to [interested, going, miigwech, recommend], but no such field or validation exists anywhere in the entity or provider.

### Ratings
- **Encapsulation**: 4/10
  Bare value bag. No accessor methods, no validation. The `emoji` field is an unconstrained string -- any value is accepted. The stated reaction_type enum does not exist in the code.

- **Invariant Expression**: 2/10
  The field definition declares `emoji` as type `string` with no `allowed_values`, `settings`, or constraint metadata. There is zero structural difference between a valid reaction and `new Reaction(['emoji' => 'anything'])`. The target_type field accepts any string, not just known entity types.

- **Invariant Usefulness**: 5/10
  The target_type/target_id polymorphic reference is useful. But without constraining emoji/reaction_type values, the type cannot prevent garbage data.

- **Invariant Enforcement**: 1/10
  No constructor validation at all. The only logic is defaulting `created_at` to 0.

### Concerns
1. **The advertised reaction_type enum does not exist.** The field definition only has `emoji` (unconstrained string). Either add an `allowed_values` setting to the field definition, or add a dedicated `reaction_type` field with constructor validation.
2. No `user_id` or `target_type`/`target_id` required check at construction.
3. `created_at` defaults to 0 instead of `time()`. Zero is a valid-looking falsy timestamp (1970-01-01) that will silently produce wrong relative times.

### Recommended Improvements
- Add constructor validation: require `emoji`, `user_id`, `target_type`, `target_id`.
- Add `ALLOWED_EMOJIS` constant and validate in constructor.
- Default `created_at` to `time()` instead of 0, or make it required.

---

## Type 2: Comment (`src/Entity/Comment.php`)

### Invariants Identified
- Has a body (text_long), used as the label key
- Targets another entity (target_type + target_id)
- Has a publication status (default 1)
- Belongs to a user (user_id)
- created_at defaults to 0

### Ratings
- **Encapsulation**: 4/10
  Same bare value bag as Reaction. No typed accessors, no body length validation.

- **Invariant Expression**: 3/10
  The `text_long` type on body is good signal. Status default is reasonable. But body can be empty string, null, or missing.

- **Invariant Usefulness**: 6/10
  Status field enables moderation workflow. The target polymorphic reference is correct.

- **Invariant Enforcement**: 2/10
  Only sets defaults. A `Comment` with no body, no user_id, and no target is perfectly constructable.

### Concerns
1. Body can be empty or missing -- a Comment without text is nonsensical.
2. No max-length or sanitization signal in field definition.
3. Missing `updated_at` field unlike Post, creating inconsistency.

### Recommended Improvements
- Validate non-empty `body` in constructor (at minimum `trim($values['body'] ?? '') !== ''`).
- Add `description` metadata to field definitions (Event entity does this well).
- Consider adding `updated_at` for edit support consistency.

---

## Type 3: Post (`src/Entity/Post.php`)

### Invariants Identified
- User-generated content with body text
- Has status (default 1), created_at (default 0), updated_at (default 0)
- Belongs to a user (user_id)
- **MISSING: community_id.** The task description says Post has community_id, but no such field exists in the entity class or in the field definitions in `EngagementServiceProvider`.

### Ratings
- **Encapsulation**: 4/10
  Same pattern as siblings. No typed accessors.

- **Invariant Expression**: 3/10
  Has both timestamps (created/updated), which is better than Comment/Reaction. But the stated community_id relationship is absent.

- **Invariant Usefulness**: 4/10
  Without community_id, a Post floats unanchored -- it cannot be scoped to a community feed. This significantly reduces the type's domain usefulness.

- **Invariant Enforcement**: 2/10
  Only defaults. No required field validation.

### Concerns
1. **community_id is missing** from both the entity and the provider field definitions. This is a design gap if posts are meant to be community-scoped.
2. No body validation (same as Comment).
3. No target_type/target_id -- unlike Reaction and Comment, Posts are standalone. This is fine if intentional, but the lack of community_id leaves them orphaned.

### Recommended Improvements
- Add `community_id` field as `entity_reference` with `target_type => 'community'` (matching Event's pattern).
- Validate non-empty body.
- Validate user_id is present.

---

## Type 4: Follow (`src/Entity/Follow.php`)

### Invariants Identified
- Represents a user following a target entity
- Uses target_type + target_id polymorphic reference
- Label key is `target_type` (unusual -- most entities use a human-readable field)
- created_at defaults to 0
- No status field (follow is binary: exists or not)

### Ratings
- **Encapsulation**: 4/10
  Same bare value bag. No uniqueness enforcement possible at the entity level.

- **Invariant Expression**: 4/10
  The absence of a status field correctly models follows as binary relationships. Using target_type as label is pragmatic but semantically weak.

- **Invariant Usefulness**: 6/10
  The polymorphic target design allows following communities, users, or other entity types -- flexible and appropriate.

- **Invariant Enforcement**: 1/10
  No validation. `new Follow([])` creates a follow with no user and no target. Duplicate follows (same user + same target) cannot be prevented at the entity level.

### Concerns
1. No uniqueness constraint signal for user_id + target_type + target_id (must be enforced at storage/query level).
2. No required field validation at all.
3. Label key `target_type` means the entity's human label is something like "community" -- not useful for display.

### Recommended Improvements
- Validate required fields: `user_id`, `target_type`, `target_id`.
- Document the uniqueness constraint expectation (even if enforced at storage layer).
- Consider a computed label method if the framework supports it.

---

## Type 5: EngagementCounter (`src/Feed/EngagementCounter.php`)

### Invariants Identified
- Stateless service with readonly EntityTypeManager dependency
- getCounts accepts a typed list of targets, returns keyed array
- getCountsForTarget is a convenience wrapper

### Ratings
- **Encapsulation**: 8/10
  Good constructor injection with `private readonly`. Clean single-responsibility design. No mutable state.

- **Invariant Expression**: 6/10
  PHPDoc types are clear (`list<array{type: string, id: int}>` and return shape). However, the N+1 query pattern (one query per target per entity type) is a performance concern hidden behind the clean interface.

- **Invariant Usefulness**: 7/10
  Solves a real problem (batch engagement counts). The keyed return format is easy to consume in templates.

- **Invariant Enforcement**: 7/10
  Empty array guard is good. The `count()` call after `->count()->execute()` suggests the query may return IDs rather than a scalar -- verify this matches the storage API contract.

### Concerns
1. **N+1 query problem.** For 20 feed items, this executes 40 queries (20 reaction + 20 comment). Consider batching with IN clauses or a single aggregate query.
2. The `->count()->execute()` returns a value that then gets `count()` called on it. If `execute()` after `count()` returns a scalar, `count()` on an int will always be 1. If it returns an array of IDs, the `->count()` call is misleading. Verify the storage query API.
3. `final` class is correct for a service that should not need mocking in its current form, but if this becomes a DI boundary, it may need an interface.

### Recommended Improvements
- Extract an `EngagementCounterInterface` if this will be injected into controllers.
- Batch the target queries using `condition('target_id', $ids, 'IN')` grouped by target_type.
- Add return type verification against the actual storage query API.

---

## Type 6: RelativeTime (`src/Feed/RelativeTime.php`)

### Invariants Identified
- Pure static utility -- no state, no dependencies
- Handles: <60s, <1h, <24h, <48h, and older dates
- Accepts optional `$now` for testability
- Future timestamps fall through to date format

### Ratings
- **Encapsulation**: 9/10
  Pure function design. No side effects. Injectable `$now` parameter enables deterministic testing.

- **Invariant Expression**: 8/10
  Clear threshold progression. PHPDoc documents example outputs. The `int $timestamp` parameter type prevents string dates at compile time.

- **Invariant Usefulness**: 8/10
  Covers the common social feed time display pattern well. The "Yesterday" threshold at 48h is standard.

- **Invariant Enforcement**: 7/10
  Negative diff (future timestamps) handled gracefully. However, a timestamp of 0 (the default for many entity fields) would show "Jan 1" (1970) rather than a sentinel value.

### Concerns
1. Timestamp 0 (the entity default) produces "Jan 1" instead of something like "Unknown" -- this will surface if the created_at default-to-0 pattern persists.
2. No timezone awareness. `date('M j')` uses server timezone. Consider if community-local display matters.
3. Static class cannot be swapped via DI if formatting rules change per-locale.

### Recommended Improvements
- Guard against timestamp 0: return empty string or "Unknown".
- Consider making it a non-static service if localization (Anishinaabemowin) is planned for time display.

---

## Cross-Cutting Findings

### Pattern Conformance vs. Event Entity

The existing `Event` entity establishes a mature pattern:
- `description` metadata on fields
- `entity_reference` type with `settings.target_type` for relationships
- `copyright_status` and `consent_*` fields for data sovereignty
- `default_value` in field definitions

The 4 new engagement entities follow **none** of these refinements. They use bare `integer` for user_id instead of `entity_reference`, have no `description` metadata, and lack consent/sovereignty fields. This is the most significant design gap.

### Systemic Issues

| Issue | Affected Types | Severity |
|-------|---------------|----------|
| No constructor validation of required fields | All 4 entities | High |
| `created_at` defaults to 0 instead of `time()` | All 4 entities | Medium |
| `user_id` is `integer` not `entity_reference` | All 4 entities | Medium |
| Missing `community_id` on Post | Post | High |
| Missing reaction_type enum constraint | Reaction | High |
| No `description` metadata on fields | All 4 entities | Low |
| No data sovereignty fields (consent_public, consent_ai_training) | Comment, Post | Medium |
| N+1 query pattern | EngagementCounter | Medium |
| Timestamp 0 produces misleading display | RelativeTime + all entities | Low |

### Priority Recommendations

1. **Add constructor validation** to all 4 entities for required fields (user_id, target references, body text). This is the single highest-impact improvement.
2. **Add the reaction_type constraint** -- either as an `allowed_values` setting in the field definition or as a constant + constructor check.
3. **Add community_id to Post** as `entity_reference` matching Event's pattern.
4. **Change user_id to entity_reference** type with `settings.target_type => 'user'` across all 4 types to match Event's community_id pattern.
5. **Default created_at to time()** or make it required, and add a timestamp-0 guard in RelativeTime.
6. **Consider consent_public field** on Comment and Post, since these contain user-generated content that may appear publicly.
