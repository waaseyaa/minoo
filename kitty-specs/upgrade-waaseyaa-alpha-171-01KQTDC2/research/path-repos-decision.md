# Path-Repository Decision (WP01/T002)

**Date**: 2026-05-04
**Decision owner**: WP01 implementer
**Decision**: **Option B — no path-repository overrides for `waaseyaa/*` packages.**

## Context

`research/release-notes-triage.md` ("Path-repository decision (FR-010)") presented three
options for handling sibling-repo path overrides for `waaseyaa/entity`,
`waaseyaa/field`, and `waaseyaa/genealogy` during the alpha.171 upgrade:

- **Option A**: Update sibling tree to alpha.171 commit and tag locally.
- **Option B**: Remove path overrides for this upgrade; restore later for sibling-repo work.
- **Option C**: Leave hybrid; document the divergence.

Recommendation in the research note: **Option B**, so all 40 framework packages
resolve cleanly from Packagist at a unified alpha.171 baseline, with the option to
re-add path overrides as a separate scoped change after the upgrade ships.

## State at WP01 start

Inspection of `composer.json` at the start of WP01 found **no `repositories`
array** present. There were no path entries for `entity`, `field`, `genealogy`,
or any other sibling package. Minoo was already resolving every `waaseyaa/*`
constraint from Packagist via the prior alpha.142–143 mix.

This means Option B was **already in effect**. No removal step was required.

## Outcome

- `composer.json` `require` and `require-dev` blocks now constrain every
  `waaseyaa/*` dependency to `^0.1.0-alpha.171`.
- No `repositories` array was added or removed.
- All 40 framework packages resolve from Packagist exclusively for this upgrade.

## Re-adding path overrides later

If sibling-repo work on `entity`, `field`, or `genealogy` resumes after this
upgrade lands, add a `repositories` array to `composer.json` of the form:

```json
"repositories": [
    { "type": "path", "url": "../waaseyaa/packages/entity", "options": { "symlink": true } },
    { "type": "path", "url": "../waaseyaa/packages/field", "options": { "symlink": true } },
    { "type": "path", "url": "../waaseyaa/packages/genealogy", "options": { "symlink": true } }
]
```

…and switch the corresponding `require` constraints to `@dev` for the duration
of the sibling work. Revert before tagging or merging to a release branch so CI
resolves from Packagist again.

## Cross-references

- `kitty-specs/upgrade-waaseyaa-alpha-171-01KQTDC2/spec.md` (FR-010)
- `kitty-specs/upgrade-waaseyaa-alpha-171-01KQTDC2/research/release-notes-triage.md`
  ("Path-repository decision (FR-010)")
- Minoo `CLAUDE.md` "Vendor packages are versioned from Packagist" gotcha.
