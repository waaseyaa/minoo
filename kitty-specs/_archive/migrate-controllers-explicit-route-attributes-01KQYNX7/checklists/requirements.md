# Specification Quality Checklist: Migrate Controllers to Explicit Route Attributes

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-05-06
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs) — *PHP/Waaseyaa references are unavoidable here because the migration target is a specific PHP attribute API (`#[MapRoute]`/`#[MapQuery]`); they describe the contract, not the implementation*
- [x] Focused on user value and business needs — *cleanup of deprecation noise + future framework upgrade unblocking*
- [x] Written for non-technical stakeholders — *primary audience is framework maintainers (technical), but acceptance scenarios and success criteria are stated in observable terms*
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Requirement types are separated (Functional / Non-Functional / Constraints)
- [x] IDs are unique across FR-###, NFR-###, and C-### entries
- [x] All requirement rows include a non-empty Status value
- [x] Non-functional requirements include measurable thresholds (914 tests, 2568 assertions, < 2s extractor, byte-equivalent body)
- [x] Success criteria are measurable (count = 0, status code parity, content-length > 0)
- [x] Success criteria are technology-agnostic — *exception: SC-002/SC-003 reference PHPUnit and the dispatcher.deprecation channel because those are the verification surfaces native to this codebase. Stating them generically would lose verifiability.*
- [x] All acceptance scenarios are defined (5 scenarios)
- [x] Edge cases are identified (variadic params, partial annotation, single-array methods, controllers without array params, traits)
- [x] Scope is clearly bounded (37 controllers, src/Controller/ only, no framework changes)
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows (add attributes → green tests → clean log → close issue)
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification beyond what is intrinsic to the migration target

## Notes

- This is a mechanical refactor mission. Spec complexity is intentionally low; the bulk of the work lives in `tasks.md` (per-WP file lists) which `/spec-kitty.plan` and `/spec-kitty.tasks` will produce.
- Bulk-edit classification (DIRECTIVE_035) was considered and rejected: the change adds new identifiers (attribute decorators) rather than renaming existing strings; every occurrence is internal PHP method-parameter syntax with uniform treatment — the 8-category occurrence map does not apply.
- Verification strategy is two-pronged: (a) syntactic — `scripts/check-implicit-array-params.php` token scan; (b) runtime — cold-boot log free of `dispatcher.deprecation` notices. Both must agree.
- All checklist items pass on first iteration. Spec is ready for `/spec-kitty.plan`.
