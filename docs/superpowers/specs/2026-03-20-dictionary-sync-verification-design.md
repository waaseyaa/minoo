# Dictionary Sync Verification Design

## Goal

Verify issue `#330` against the live North Cloud dictionary API without changing Minoo behavior during the first pass. The output of this work is evidence: exact commands, observed NC payloads, sync results in an isolated local database, SSR rendering checks, and a documented acceptance-criteria status.

## Scope

This verification pass covers:

- Live North Cloud dictionary endpoints used by Minoo:
  - `GET /api/v1/dictionary/entries`
  - `GET /api/v1/dictionary/search`
- `bin/sync-dictionary --dry-run`
- A real `bin/sync-dictionary` run against a disposable SQLite database
- Post-sync inspection of stored `dictionary_entry` entities
- SSR rendering checks for:
  - `/language`
  - one synced dictionary entry detail page
- Documentation of results in-repo, followed by a concise issue update on `waaseyaa/minoo#330`

This pass does not include behavior changes unless verification is impossible without a trivial non-behavioral adjustment. If a product or code defect is found, the first pass stops at documented evidence.

## Existing Constraints

- `north-cloud#483` and `north-cloud#484` are now complete, so external blockers are cleared.
- Attribution display has already been implemented and merged in `waaseyaa/minoo#333`.
- The remaining open question in `#330` is whether the current sync path actually works end to end against live NC data.
- The normal local app database should not be treated as disposable test state.

## Verification Approach

### 1. Live API shape check

Probe the live NC endpoints directly from the workspace and capture:

- HTTP success/failure
- presence of `entries` and `total`
- sample entry fields relevant to Minoo mapping:
  - `lemma`
  - `definitions`
  - `word_class_normalized`
  - `inflections`
  - `source_url`
  - `attribution`
  - `consent_public_display`

This confirms whether the live payload still matches the assumptions in `NorthCloudClient` and `DictionaryEntryMapper`.

### 2. Dry-run sync check

Run `bin/sync-dictionary --dry-run` against live NC and record:

- whether pagination succeeds
- total fetched count
- whether create/update logging is coherent
- whether the command exits cleanly

This validates the operator-facing preview mode without writing data.

### 3. Isolated real sync check

Run `bin/sync-dictionary` against a disposable SQLite database via `WAASEYAA_DB=<temp-path>`.

The verification should prove:

- entries are created in local storage
- consent governance is preserved
  - `consent_public = 1` produces `status = 1`
  - if any non-public entries are returned, they remain unpublished
- attribution and source URL fields are stored
- inflected forms are stored in the format the app can render

Using an isolated DB keeps the evidence reproducible and avoids mutating the user’s normal local state.

### 4. SSR rendering check

With synced data available in the isolated DB, verify:

- `/language` renders dictionary cards
- cards include attribution
- a detail page renders:
  - word
  - definition
  - part of speech
  - inflected forms
  - attribution

If a full HTTP server is awkward for this pass, equivalent controller/template-level evidence is acceptable as long as it uses real synced entity data from the isolated DB.

## Acceptance Criteria Mapping

- `bin/sync-dictionary` successfully pulls entries from NC and stores locally
  - Proven by isolated real sync plus DB inspection
- `/language` page displays dictionary entries
  - Proven by SSR rendering evidence
- Entry detail pages show full data (definition, POS, inflections)
  - Proven by SSR rendering evidence for one synced entry
- Attribution displayed on every entry
  - Verified on listing and detail rendering for synced entries
- Consent governance respected (only public entries visible)
  - Verified through stored entity fields and published-query behavior
- `bin/sync-dictionary --dry-run` previews without writing
  - Proven by dry-run output and unchanged isolated DB state

## Outputs

Primary output:

- a verification/results document in the repo recording commands, outputs, sample data, and pass/fail status against each acceptance criterion

Secondary output:

- a short comment on `waaseyaa/minoo#330` summarizing what passed, what failed, and whether follow-up implementation work is required

## Failure Handling

If verification fails, the first pass documents:

- exact failing command
- exact observed output or error
- whether the failure is in:
  - NC response shape
  - `NorthCloudClient`
  - `DictionaryEntryMapper`
  - `bin/sync-dictionary`
  - entity storage
  - SSR rendering

No remediation work starts until that evidence is recorded and reviewed.
