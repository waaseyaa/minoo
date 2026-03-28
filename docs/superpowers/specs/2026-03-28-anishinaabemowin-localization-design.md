# Anishinaabemowin Localization — Phased Implementation Design

**Date:** 2026-03-28
**Milestone:** #21 (Anishinaabemowin Localization)
**Approach:** Strict sequential — one phase at a time, full checkpoint before advancing
**Target dialect:** Nishnaabemwin (Eastern Ojibwe), North Shore of Lake Huron

## Context

An external analysis of Minoo identified practical recommendations for Indigenous language technology. This design turns those into a phased implementation plan across 8 new GitHub issues (#599-#606) added to milestone #21, combined with 3 existing open issues (#277, #331, #533).

The waaseyaa framework already provides `packages/i18n/` with `Translator`, `LanguageManager`, `LanguageContext`, and `FallbackChain`. Phase 1 wires this existing infrastructure into Minoo rather than building from scratch.

Both repos are alpha. Framework changes use a path repository override in Minoo's `composer.json` for fast iteration, pinned to a tagged release before merging.

---

## Phase 1: Foundation

**Issues:** #600 (i18n infra, Must), #277 (string sweep, Must), #533 (part_of_speech bug), #601 (Speaker entity, Should)

### Step 1 — Framework i18n wiring (#600)

- Add path repository override in Minoo `composer.json` pointing to `../waaseyaa/packages/*`
- In the framework: ensure `Translator`, `LanguageManager`, `LanguageContext` are registered as services in a framework-level `I18nServiceProvider`
- In Minoo: create `translations/messages.en.yaml` and `translations/messages.oj.yaml`
- Wire `LanguageContext` to detect locale from URL prefix (`/oj/teachings` = Ojibwe, `/teachings` = English)
- Add language toggle UI in `base.html.twig`
- Replace hardcoded `'oj'` in `DictionaryEntry` constructor with configurable language code

**Key files:**
- `src/Entity/DictionaryEntry.php` — hardcoded language code
- `templates/base.html.twig` — language toggle UI
- Framework `packages/i18n/` — service registration

### Step 2 — String sweep (#277)

- Extract all hardcoded English strings from templates into `messages.en.yaml`
- Add Ojibwe translations where known (from OPD data, Nishnaabemwin dictionary)
- Strings without translations fall back to English via `FallbackChain`

### Step 3 — Part of speech fix (#533)

- Fix dictionary entries missing `part_of_speech` in the sync pipeline
- Ensure `DictionaryEntryMapper` maps the field correctly from NC API response

**Key file:** `src/Ingestion/EntityMapper/DictionaryEntryMapper.php`

### Step 4 — Speaker entity (#601)

- Create `src/Entity/Speaker.php` with consent fields (`consent_public_display`, `consent_ai_training`)
- Register in `LanguageServiceProvider`
- Wire `SpeakerMapper` to persist Speaker entities during sync
- Unit test in `tests/Minoo/Unit/Entity/SpeakerTest.php`

**Key files:**
- `src/Ingestion/EntityMapper/SpeakerMapper.php`
- `src/Ingestion/ValueObject/SpeakerFields.php`

### Checkpoint 1

| Check | Tool | Local | Prod |
|-------|------|-------|------|
| All tests pass | PHPUnit | `./vendor/bin/phpunit` | -- |
| Kernel boots with i18n services | Integration test | New smoke test | -- |
| `/teachings` renders English | Playwright | localhost:8081 | minoo.ca |
| `/oj/teachings` renders Ojibwe (or fallback) | Playwright | localhost:8081 | minoo.ca |
| Language toggle visible and functional | Playwright | localhost:8081 | minoo.ca |
| Dictionary entries have part_of_speech | PHPUnit | Unit test | -- |
| Speaker entity CRUD works | PHPUnit | Unit test | -- |

**Gate:** All checks green before starting Phase 2.

---

## Phase 2: Pipeline

**Issues:** #331 (OPD e2e, Should), #603 (dictionary search, Should)

### Step 1 — Sync verification (#331)

- Run `bin/sync-dictionary` against live NC API (`api.northcloud.one`)
- Verify entries land in Minoo SQLite with correct fields (word, definition, part_of_speech, inflected_forms, attribution)
- Fix any encoding/format edge cases (JSON-wrapped definitions, special characters)
- Verify `/language` page renders synced entries with pagination and attribution

**Key files:**
- `bin/sync-dictionary`
- `src/Ingestion/EntityMapper/DictionaryEntryMapper.php`
- `templates/language.html.twig`

### Step 2 — Dictionary search (#603)

- Add search endpoint to `LanguageController`
- Wire to `NorthCloudClient::searchDictionary()` (method already exists)
- Add search bar UI component on `language.html.twig`
- Handle double-vowel orthography normalization (`aa`, `ii`, `oo`) — at minimum, case-insensitive matching
- Search results render using existing `dictionary-entry-card.html.twig`

**Boundary:** Search hits the NC API, not local SQLite. NC is the search authority. Local SQLite is for display/cache of synced entries.

**Key files:**
- `src/Controller/LanguageController.php`
- `templates/language.html.twig`
- `src/Support/NorthCloudClient.php` — `searchDictionary()` method

### Checkpoint 2

| Check | Tool | Local | Prod |
|-------|------|-------|------|
| All tests pass (including Phase 1) | PHPUnit | `./vendor/bin/phpunit` | -- |
| `/language` shows synced dictionary entries | Playwright | localhost:8081 | minoo.ca |
| Dictionary entry detail page renders correctly | Playwright | localhost:8081 | minoo.ca |
| Attribution visible on entries | Playwright | localhost:8081 | minoo.ca |
| Search bar present on language page | Playwright | localhost:8081 | minoo.ca |
| Search returns results for "makwa" (bear) | Playwright | localhost:8081 | minoo.ca |
| Search handles no-results gracefully | Playwright | localhost:8081 | minoo.ca |
| i18n still works (Phase 1 regression) | Playwright | localhost:8081 | minoo.ca |

**Gate:** All checks green before starting Phase 3.

---

## Phase 3: Research & Enrichment

**Issues:** #602 (tool evaluation, Could), #604 (dialect regions, Could)

### Step 1 — Tool evaluation (#602)

Research deliverable, not code. Output: `docs/research/2026-XX-XX-language-tool-evaluation.md`

- Test NLLB-200 `ojb_Latn` with sample sentences from OPD (HuggingFace transformers, local)
- Check Apertium `apertium-oji` — install, run against Nishnaabemwin samples, evaluate morphological analysis
- Test FLEx import of OPD JSONL (22k entries)
- Document: self-hosting feasibility, licensing, quality for Eastern Ojibwe
- Connect with AmericasNLP community

### Step 2 — Dialect regions (#604)

- Seed data: fixture for Ojibwe dialect regions (Nishnaabemwin/Eastern, Southwestern, Northwestern, Odawa, Saulteaux) with BCP-47 subtags
- Add `DialectRegionController` with list route
- Create `dialect-regions.html.twig` extending `base.html.twig`
- Framework auto-serves at `/dialect-regions`
- Map component: if `boundary_geojson` data available, render with Leaflet or inline SVG. Otherwise text-only list.
- Link dictionary entries to dialect region where data exists in OPD

**Boundary:** DialectRegion is a config entity in Minoo. Seed data in Minoo's seeders. Map rendering is pure frontend. No framework changes.

**Key file:** `src/Entity/DialectRegion.php`

### Checkpoint 3

| Check | Tool | Local | Prod |
|-------|------|-------|------|
| All tests pass (including Phase 1+2) | PHPUnit | `./vendor/bin/phpunit` | -- |
| Research doc committed and reviewed | Manual | Git log | -- |
| `/dialect-regions` renders list | Playwright | localhost:8081 | minoo.ca |
| Dialect region detail page shows description | Playwright | localhost:8081 | minoo.ca |
| Dictionary entries show dialect tag where known | Playwright | localhost:8081 | minoo.ca |
| Phases 1+2 still work (regression) | Playwright | localhost:8081 | minoo.ca |

**Gate:** All checks green before starting Phase 4.

---

## Phase 4: Advanced

**Issues:** #605 (audio playback, Could), #606 (correction flow, Could)

### Step 1 — Audio playback (#605)

- Storage: local first (`public/audio/dictionary/`), object storage later if needed
- Add `audio_url` field to `DictionaryEntry` if not present (check mapper)
- Build `components/audio-player.html.twig` — HTML5 `<audio>` with play button, styled in `@layer components`
- Include in `dictionary-entry-card.html.twig` when `audio_url` is non-empty
- Consent gate: only render audio where entry's speaker has `consent_public_display = true`
- Sync audio URLs from NC during `bin/sync-dictionary` — store URL reference, not the file

**Key files:**
- `templates/components/dictionary-entry-card.html.twig`
- `src/Entity/DictionaryEntry.php`

### Step 2 — Suggest correction flow (#606)

- Create `TranslationCorrection` entity: `target_type`, `target_id`, `field_name`, `original_value`, `suggested_value`, `submitted_by`, `status` (pending/approved/rejected), `reviewed_by`
- Register in new `TranslationCorrectionServiceProvider`
- "Suggest correction" link on translated strings (authenticated users only)
- Simple form: shows original, text input for suggestion, submit
- Coordinator review queue at `/admin/corrections` (reuse coordinator dashboard pattern)
- On approval: update YAML catalog entry or entity field value

**Boundary:** `TranslationCorrection` is a Minoo entity. Framework's `Translator` just reads catalogs — no awareness of corrections needed.

### Checkpoint 4 (Final)

| Check | Tool | Local | Prod |
|-------|------|-------|------|
| All tests pass (full suite) | PHPUnit | `./vendor/bin/phpunit` | -- |
| Audio player renders on entries with audio | Playwright | localhost:8081 | minoo.ca |
| Audio player hidden on entries without audio | Playwright | localhost:8081 | minoo.ca |
| Audio plays | Manual | localhost:8081 | minoo.ca |
| "Suggest correction" visible when logged in | Playwright | localhost:8081 | minoo.ca |
| "Suggest correction" hidden when logged out | Playwright | localhost:8081 | minoo.ca |
| Correction submission creates pending record | PHPUnit | Integration test | -- |
| Coordinator sees pending corrections | Playwright | localhost:8081 | minoo.ca |
| All phases 1-3 still work (full regression) | Playwright | localhost:8081 | minoo.ca |

---

## Issue-to-Phase Map

| Issue | Title | Phase | Priority |
|-------|-------|-------|----------|
| #600 | i18n infra + BCP-47 | 1 | Must |
| #277 | String sweep | 1 | Must |
| #533 | part_of_speech bug | 1 | Bug |
| #601 | Speaker entity | 1 | Should |
| #331 | OPD pipeline e2e | 2 | Should |
| #603 | Dictionary search | 2 | Should |
| #602 | FLEx/Apertium/NLLB eval | 3 | Could |
| #604 | Dialect regions | 3 | Could |
| #605 | Audio playback | 4 | Could |
| #606 | Correction flow | 4 | Could |
| #599 | Data agreement | Backlog | Could |

## Architectural Boundaries

- **Minoo owns:** entity types, translations content, templates, CSS, controllers, seed data
- **Framework owns:** `Translator`, `LanguageManager`, `LanguageContext`, `FallbackChain`, entity storage, access control
- **NC owns:** dictionary search, OPD data, audio hosting
- **Never cross:** No Minoo logic in framework. No framework awareness of corrections/speakers/dialects.
- **Path repo override:** Used during development only. Pin to tagged framework release before merging each phase.
