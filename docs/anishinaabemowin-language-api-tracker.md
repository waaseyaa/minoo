# Anishinaabemowin Language API tracker

Status: recon complete, nothing built. This file was created by the 2026-06-24 recon pass
(see Log). It is the single home for the language-API initiative: vision, decided items,
governance gate, phasing, the recon attachment map, and the draft specs that the build
prompts will draw from. No application code, endpoints, or migrations exist yet.

Constraints that govern every entry here: sovereign stack, no em dashes anywhere, do not
touch rhtcircle / oiatc / fnpi, deploy is out of scope. Report before building.

Companion doc: [anokii-parity-and-language-module.md](anokii-parity-and-language-module.md)
owns how this language work plugs into Anokii. Read it for the module seam, the parity plan,
and the build-sequence issues. The short version of its reframe: Anokii is an opt-in library
(the `waaseyaa/anokii` package has no auto-registered provider), not a single canonical admin.
fnprocure (fnpi-waaseyaa) is its fullest consumer and hand-codes FNPI business modules that
minoo must NOT copy. The package ships a clean module seam (the `Anokii\Admin\AdminModules`
catalog plus the config-gated module-provider pattern in
`Anokii\Provider\CoIntelligenceServiceProvider`) that nobody fully uses yet. The locked plan is
to adopt that seam and make language the FIRST proper module on it: minoo's existing corpus
pipeline becomes the admin face of the language module, and this tracker's translation memory,
`/api/lang`, gap-log, and gated ASR hookup are that same module's data model, public API, and
services. The `/api/lang` surface mounts as a peer to the package `/api/chat` (per-module
`/api/*`, no single gateway), and the `DialectCodeProvider` seam decided in section A.3 is a
service bound by that module provider.

## Vision

A first-party Anishinaabemowin language service that lives inside minoo.live and is exposed
under a clean `/api/lang/...` surface, kept separate from the page controllers so it can be
extracted into its own service later if needed. The service answers English-to-Anishinaabemowin
lookups from an in-app translation memory, logs the gaps it cannot fill so speakers can close
them, and is fed over time by a speaker-reviewed corpus and a separate ASR worker.

## Decided items (treat as fixed)

These were handed down with the recon brief and are not up for re-litigation here.

1. Build into minoo.live. Not a standalone app.
2. Expose under `/api`, specifically `/api/lang/...`.
3. Translation memory (TM) is an in-app table, with lookup order: exact match first, then
   fuzzy match, then write to a gap log on a miss.
4. ASR (speech-to-text) runs as a separate Python/GPU worker that minoo.live calls. The Pi
   serves inference only. Training needs a GPU host that is not the Pi.
5. Dialect and consent are tagged on every record.

## Language tags (single ecosystem contract)

This is the canonical language-tag contract for the whole ecosystem: Minoo, Anokii,
rhtcircle, and the CMS all consume it. It is BCP 47, canonical everywhere.

- **Three layers, with fallback.** The language is `oj`, whose autonym is
  "Anishinaabemowin" (never an ISO exonym). A tag may carry an optional dialect
  middle code (an ISO 639-3 subtag, e.g. `oj-ojg`), and an optional community
  provenance as a BCP 47 private-use subtag `-x-<community>` (e.g. `oj-x-sagamok`).
  Fallback runs `oj-x-sagamok -> oj -> en`.
- **The TM retains community granularity.** Translation-memory rows key on the
  full tag (`oj-x-sagamok`), never on a dialect-only code. The lookup fallback is
  exact tag, then any row in the same DERIVED dialect grouping, then the
  tag-agnostic row (`oj` or empty). A dialect grouping is never stored or keyed
  on.
- **Dialect groupings are derived, not stored.** A grouping such as Nishnaabemwin
  (which straddles otw and ojg) is computed from the tag at read time, never
  written in its place. Sagamok derives Nishnaabemwin / Eastern Ojibwe, with the
  human label "Nishnaabemwin (Sagamok)".
- **Sagamok corpus is tagged `oj-x-sagamok`** wherever provenance is set
  (example_sentence, translation memory).
- **Implementation seam (app-side, Minoo).** `App\Language\LanguageTag` parses and
  validates a tag; `App\Language\DialectCodeProvider` validates, derives the
  grouping (`dialectFor`/`dialectCodeForTag`), and produces labels, backed by
  `App\Seed\ConfigSeeder::dialectRegions()` (groupings) and
  `communityDialects()` (community membership). `/api/lang` accepts and returns
  tags. No framework change is required: the entity `langcode` stays `oj` (the
  framework already falls back `oj -> en`), and the community layer lives in
  Minoo's own `language_tag` field resolved by Minoo's own lookup. See
  `docs/anokii-parity-and-language-module.md` for the module seam.
- **Private-use length note.** BCP 47 private-use subtags are at most 8 characters
  each, so long community slugs (e.g. mississauga, thessalon) need an assigned
  short community code before they can be tagged; that is an ecosystem data
  decision, deferred. `sagamok` fits as-is.

## Governance gate (Phase 0)

Nothing derived from Steven Bennett's paired audio corpus may be published, and no model
weights or transcripts from it may leave the sovereign stack, until a Phase 0 consent
agreement exists. The crawler in Section B is public-web English only and must never touch
that corpus. Consent and dialect tags are mandatory on every corpus and TM record so the gate
is enforceable at the data layer (the framework already gates reads on `consent_public`, see
Section A.2).

## Phasing (working shape)

- Phase 0: consent agreement for the Bennett corpus (gate for all ASR and corpus publication).
- Phase 1: English seed crawler, TM table + gap log, `/api/lang` surface, dialect contract
  consolidation. Public-web and English only. No corpus dependency.
- Phase 2: speaker-review workflow over the gap log, fuzzy-match tuning.
- Phase 3 (gated on Phase 0): ASR data alignment and a first fine-tune, inference on the Pi.

---

# A. Attachment map (what exists today, and the recommended seam)

Repo: `C:\Users\jones\Local Sites\minoo`. Framework: Waaseyaa, synced to alpha.248. All paths
are relative to the repo root.

## A.1 API surface

- **Routing entry point:** `src/Provider/MinooRoutingStackProvider.php`. Signature:
  `routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void`. It does
  not register a global `api` prefix. It composes one child route provider per domain and calls
  each child's `routes()`:
  ```
  PublicContentRouteProvider, PublicAccountRouteProvider, PublicHomeFeedRouteProvider,
  AuthApiRouteProvider, GamesApiRouteProvider, LessonRouteProvider, StaticPagesRouteProvider,
  AnokiiRouteProvider, AdminRouteProvider, SocialApiRouteProvider
  ```
- **Where API routes are declared:** `src/Provider/Routing/*.php`, one provider class per domain,
  using the `RouteBuilder` fluent API. Each provider owns its own `/api/*` prefixes inline. Real
  examples:
  - `GamesApiRouteProvider.php`: `RouteBuilder::create('/api/games/shkoda/daily')->controller('App\\Http\\Controller\\Games\\ShkodaController::daily')->allowAll()->methods('GET')->build()`
  - `SocialApiRouteProvider.php`: `RouteBuilder::create('/api/engagement/react')->controller($ctrl . '::react')->requireAuthentication()->methods('POST')->build()`
- **Controller convention:** namespace `App\Http\Controller\{Domain}`, directory
  `src/Http/Controller/{Domain}/`. Controllers are `final class`, no mandatory base class, DI by
  constructor (auto-injected by `SsrPageHandler::resolveControllerInstance()`). JSON controllers
  mix in `JsonResponseTrait`.
- **JSON responses:** confirmed both forms are in use and both are blessed by CLAUDE.md
  ("`new Response($json, 200, ['Content-Type' => 'application/json'])` or a `$this->json()` helper").
  The preferred path is the trait:
  - `src/Http/Controller/Concerns/JsonResponseTrait.php`: `private function json(array $data, int $status = 200, array $headers = []): JsonResponse`. This is a local shim that replaces the
    framework's removed `Waaseyaa\Api\JsonResponseTrait` (alpha.171 swapped it for a JSON:API
    variant with a different signature). Example caller: `EngagementController::react()`.
  - Direct form example: `FeedController::api()` builds `json_encode([...], JSON_THROW_ON_ERROR)`
    then `new Response($json, 200, ['Content-Type' => 'application/json'])`.
- **Confirmed:** no `/api/lang` route or controller exists today (grep clean). Greenfield seam.
- **Recommended seam:** add a new `src/Provider/Routing/LanguageApiRouteProvider.php` and register
  it in the `MinooRoutingStackProvider` child list (after `AuthApiRouteProvider`, before
  `AdminRouteProvider`). Put the JSON endpoints in a dedicated
  `App\Http\Controller\Language\LanguageApiController` (separate from the existing page-serving
  `LanguageController`), using `JsonResponseTrait`. This keeps the API physically separate from the
  `/language*` page routes (currently in `PublicContentRouteProvider.php`) and makes a later
  extraction a matter of removing one child provider plus one controller namespace. Note: the five
  composer-facing providers are asserted by `ComposerProviderParityTest`, but adding a child route
  provider inside `MinooRoutingStackProvider` does not change that top-level list, so no parity
  change is needed.

## A.2 Language domain

Entities registered via `App\Provider\MinooEntityStackProvider`, which composes the
`src/Provider/Entity/*Provider.php` classes. Language types are split across
`EntityFoundationProvider` (content types) and `EntityCommunityProvider` (the `dialect_region`
config entity). All fields live in the `_data` JSON blob (see A.5).

| Entity | Class / file | Keys | Consent / attribution fields today |
|---|---|---|---|
| `dictionary_entry` | `src/Entity/Language/DictionaryEntry.php` | id `deid`, label `word` | `consent_public` (default 1), `consent_ai_training` (default 0), `attribution_source`, `attribution_url`, `source_url` |
| `example_sentence` | `src/Entity/Language/ExampleSentence.php` | id `esid`, label `ojibwe_text` | `consent_public`, `consent_ai_training`, `contributor_id`, `speaker_id`, `provenance`, `source_url`, `source_sentence_id`, `pipeline_status` |
| `word_part` | `src/Entity/Language/WordPart.php` | id `wpid`, label `form` | none (no consent fields) |
| `speaker` | `src/Entity/Language/Speaker.php` | id `spid`, label `name` | `consent_public_display` (note the different field name), `consent_ai_training`, `dialect_region_id`, `community` (free text) |
| `dialect_region` | `src/Entity/Language/DialectRegion.php` (config entity) | id `code`, label `name` | n/a |

- **How dialect is modeled:** `dialect_region` is a config entity keyed by `code`. The only entity
  that references it is `speaker.dialect_region_id` (entity reference). `dictionary_entry` and
  `example_sentence` carry a free-string `language_code` (default `oj`), not a dialect reference.
  So today, dialect is reliably attached only through the speaker, not on the lexical records
  themselves. The new TM table should carry an explicit `dialect_code` referencing
  `dialect_region.code` so dialect is a first-class filter on every TM row.
- **Consent already exists** on `dictionary_entry`, `example_sentence`, and `speaker`. Reuse those
  field names and defaults for the TM table so the existing access policy logic transfers.
- **LanguageAccessPolicy:** `src/Access/Language/LanguageAccessPolicy.php`, implements
  `Waaseyaa\Access\AccessPolicyInterface`, registered with
  `#[PolicyAttribute(entityType: ['dictionary_entry', 'example_sentence', 'word_part', 'dialect_region', 'speaker'])]`.
  Methods: `appliesTo()`, `access()`, `createAccess()`. Rules: admins (`administer content`) bypass;
  view is allowed only if `status == 1` and consent is public (`hasPublicConsent()` checks
  `consent_public_display` for speakers, `consent_public` otherwise, treating null as viewable for
  backward compat); create/update/delete return neutral (deny) for non-admins. A new TM entity must
  be added to this attribute array (or get a sibling policy) so reads are consent-gated by default.
- **Definition JSON-wrap gotcha:** the `dictionary_entry.definition` field is stored as a
  JSON-encoded array, for example `["bear"]` or `["go...","go over there..."]` or `[]`. Raw reads
  return the JSON string. Display must decode it. The canonical helper is
  `src/Http/Twig/LanguageTwigExtension.php::cleanDefinition()` (mirrored in
  `GameControllerTrait::cleanDefinition`), which json_decodes, joins with `; `, and expands Ojibwe
  People's Dictionary abbreviations (`h/self` to `himself/herself`, `s.t.` to `something`, etc.).
  Implication for the API: do not echo `definition` raw in JSON responses, and decide deliberately
  whether the TM `translation` field is stored plain (recommended, single value) or JSON-wrapped.
  This tracker recommends plain text for `translation` to avoid inheriting the gotcha.
- **Consent footgun to respect in the API read path:** `SqlEntityStorage::loadMultiple([])` treats
  an empty array as "load all rows" (CLAUDE.md gotcha, incident #788, a real consent leak that
  dumped 27 gated sentences). Any TM or gap-log read path must guard `if ($ids === []) return [];`
  before `loadMultiple($ids)`. Also, since alpha.181 every `getQuery()` must `->setAccount($account)`
  or explicitly `->accessCheck(false)` with an audit row, or it throws
  `MissingQueryAccountException`.

## A.3 Dialect codes (important correction)

- **The brief's premise needs correcting.** There is no `jonesrussell/indigenous-taxonomy` package
  installed. `composer.json` has no such requirement and there is no `vendor/jonesrussell/`
  directory. The minoo CLAUDE.md "Architectural Boundaries" section still names
  `jonesrussell/indigenous-taxonomy` as the intended "shared taxonomy contract ... categories,
  regions, dialect codes (PHP package)", but that is aspirational and stale, not the installed
  reality.
- **What is actually installed:** `waaseyaa/taxonomy: ^0.1.0-alpha.248`, a generic Term / Vocabulary
  framework (`vendor/waaseyaa/taxonomy/src/{Term,Vocabulary,TermAccessPolicy,TaxonomyServiceProvider}.php`).
  It defines no dialect or region constants, and minoo `src/` does not reference it.
- **The de facto dialect contract today** is `src/Seed/ConfigSeeder.php::dialectRegions()`, which
  seeds the `dialect_region` config entity. The codes (use these as the `/api/lang` `dialect`
  parameter enum, do not invent new ones):

  | code | display_name | iso_639_3 |
  |---|---|---|
  | `oji-east` | Eastern Ojibwe (Nishnaabemwin) | ojg |
  | `oji-northwest` | Northwestern Ojibwe | ojb |
  | `oji-plains` | Saulteaux / Plains Ojibwe | ojs |
  | `oji-ottawa` | Ottawa / Odawa | otw |
  | `cree-plains` | Plains Cree | crk |
  | `cree-swampy` | Swampy Cree | csw |
  | `innu` | Innu | moe |
  | `inuktitut` | Inuktitut | iku |
  | `inuvialuktun` | Inuvialuktun | ikt |
  | `mohawk` | Mohawk | moh |

  For the RHT nations the relevant codes are `oji-east` (north shore of Lake Huron, the bulk of the
  21) and `oji-ottawa` (Manitoulin / Odawa communities). Each row also carries `language_family`,
  `iso_639_3`, and a `regions` list.
- **Decision (2026-06-24):** make `dialect_region.code` / `ConfigSeeder::dialectRegions()` the
  canonical source of truth now, and correct the stale CLAUDE.md claim. Isolate dialect-code access
  behind a single seam (a `DialectCodeProvider` or equivalent, one place that returns the valid codes
  and validates a requested code), so the backing can move to `jonesrussell/indigenous-taxonomy` later
  without rewriting callers. This is the same "extract only if reality forces it" rule used for the API
  seam. Defer publishing the package until real multi-nation federation demands it.
- **NorthCloud cross-check (done 2026-06-24):** the NorthCloud Go repo
  (`C:\Users\jones\Projects\NorthWay\north-cloud`, 1065 Go files) defines no dialect codes (zero
  `dialect` matches, no language-code enum). Its indigenous importer carries only a free-text
  `Language string json:"language"` field (`source-manager/internal/importer/indigenous.go:20`). So
  there is no competing contract to reconcile; `dialect_region.code` can be canonical. Implication:
  NorthCloud's free-text `Language` value is not a dialect code and must be mapped to a
  `dialect_region.code` at ingestion, not assumed equal. The PHP `vendor/waaseyaa/northcloud` client
  has no dialect references either.

## A.4 Gap-logging precedent (ingest_log)

- **Entity:** `App\Entity\Ingestion\IngestLog`, `src/Entity/Ingestion/IngestLog.php`, registered in
  `EntityFoundationProvider` (id `ilid`, label `title`). Policy: `IngestAccessPolicy`.
- **Fields:** `ilid, uuid, title, status (pending_review|approved|rejected|failed), source,
  entity_type_target, entity_id, payload_raw, payload_parsed, error_message, reviewed_by,
  reviewed_at, created_at, updated_at`.
- **Who writes it, and when:** created by `src/Ingestion/IngestImporter.php::import()` (returns a new
  `IngestLog` with `status = PendingReview`), then persisted and lifecycled by
  `src/Http/Controller/Ingestion/IngestionApiController.php` at three points: `ingestEnvelope()`
  (create + save), `updateStatus()` (approve/reject, sets `reviewed_by` / `reviewed_at`),
  `materialize()` (sets `entity_id` after the target entity is created). The pattern is: a service
  builds the log entity, a controller saves it and drives a status lifecycle.
- **Reuse vs sibling:** add a **sibling** log entity, `tm_gap_log`, rather than overloading
  `ingest_log`. `ingest_log` is materialization-scoped (it carries `payload_raw` / `payload_parsed`
  / `entity_type_target` and exists to stage inbound NorthCloud envelopes into entities). The TM gap
  log is lookup-miss-scoped (a request for an English string that the TM could not satisfy). Reuse
  the *shape and write pattern* of `ingest_log` (a service writes it, a controller or worker drives a
  status lifecycle), not the table itself. Schema in Section C.

## A.5 Migration and schema reality

- **`_data` CLOB rule (hard):** content-entity tables are
  `{id} INTEGER PRIMARY KEY AUTOINCREMENT, uuid CLOB, bundle CLOB, {label} CLOB, langcode CLOB, _data CLOB`.
  Config-entity tables are `{id} TEXT PRIMARY KEY, bundle CLOB, langcode CLOB, _data CLOB`. All field
  values live in `_data`. Do NOT create per-field columns. `SqlEntityStorage` errors with
  "no column named _data" if the schema is wrong. Canonical example to copy:
  `migrations/20260612_160000_create_language_domain_tables.php` (creates `speaker`, `word_part`,
  `contributor`, `dialect_region`).
- **Flow:** add fields to the entity's `fieldDefinitions`, then `bin/waaseyaa schema:check` to detect
  drift (it does not ALTER existing tables automatically), then
  `bin/waaseyaa make:migration create_translation_memory_tables` (or `add_<col>_to_<table>`), edit the
  generated file to follow the `_data` CLOB shape, then `bin/waaseyaa migrate`. Migration files live in
  `migrations/` as PHP returning a `Migration` instance (`Waaseyaa\Foundation\Migration\Migration` +
  `SchemaBuilder`), with `up()` and `down()`. Precedents:
  `20260315_110500_add_consent_fields.php`, `20260616_010000_publish_consented_corpus.php`.
- **Save-time validation (alpha.215+):** `EntityRepository::save()` rejects values that violate field
  definitions before writing (`EntityValidationException`). New TM fields must have correct types or a
  cast, or opt out per call with `save($e, validate: false)`.

---

# B. Crawler spec (draft)

Purpose: build a ranked English seed list of terms worth translating, drawn only from public web
pages, so the TM and the speaker-review queue start from real community vocabulary rather than a
blank table. Public-web English only. Never touches the Bennett corpus, the database, or any
consent-gated content.

## B.1 Site list

Core set: the 21 RHT nation official sites. Sourced from
`C:\Users\jones\Projects\RHT\nations\*.md` frontmatter (`website:` field), cross-checked
2026-06-22 against the RHT Litigation Fund list, CIRNAC/ISC, and each nation's own site. Twenty of
twenty-one have a confirmed official site; Zhiibaahaasing has none confirmed and is represented via
UCCMM / Anishinabek Nation.

| Nation | URL |
|---|---|
| Atikameksheng Anishnawbek | https://atikamekshenganishnawbek.ca/ |
| Aundeck Omni Kaning | https://aokfn.com/ |
| Batchewana First Nation | https://batchewana.ca/ |
| Dokis First Nation | https://dokis.ca/ |
| Garden River First Nation | https://www.gardenriver.org/site/ |
| Henvey Inlet First Nation | https://www.hifn.ca/ |
| Magnetawan First Nation | https://www.magfn.com/ |
| M'Chigeeng First Nation | https://mchigeeng.ca/ |
| Mississauga First Nation | https://www.mississaugi.com/ |
| Nipissing First Nation | https://nfn.ca/ |
| Sagamok Anishnawbek | https://www.sagamokanishnawbek.com/ |
| Serpent River First Nation | https://serpentriverfn.com/ |
| Shawanaga First Nation | https://shawanagafirstnation.ca/ |
| Sheguiandah First Nation | https://sheguiandahfn.ca/ |
| Sheshegwaning First Nation | https://www.sheshegwaning.org/ |
| Thessalon First Nation | https://www.thessalonfirstnation.ca/ |
| Wahnapitae First Nation | https://www.wahnapitaefirstnation.com/ |
| Wasauksing First Nation | https://wasauksing.ca/ |
| Whitefish River First Nation | https://www.whitefishriver.ca/ |
| Wiikwemkoong Unceded Territory | https://www.wiikwemkoong.ca/ |
| Zhiibaahaasing First Nation | none confirmed (UCCMM / Anishinabek Nation) |

Russell's own projects: of `minoo.live, Anokii, rhtcircle, oiatc, fnpi`, recommend crawling only
**minoo.live** in Phase 1 (own public English nav, program names, and section labels; it is the
host app and safe to read). Recommend **excluding** rhtcircle, oiatc, and fnpi from the automated
crawl: the brief's constraints say do not touch them, their English vocabulary overlaps heavily with
the nation sites, and adding them is a per-site decision for Russell, not a default. Anokii is a
platform / distribution rather than a public content site, so there is no useful public English
surface to crawl. If Russell later opts any of these in, add them to the site list explicitly with a
note in the Log.

## B.2 What it extracts

Per page, public English text only: nav and menu labels, headings (h1 to h3), program and service
names, common recurring terms (governance, community, services, events, education, health), place
names, and any explicitly published greetings or short English glosses. It does not extract
Anishinaabemowin strings, body prose, personal names, contact details, or anything behind a login.

## B.3 Dedupe and ranking

Normalize each candidate (trim, lowercase for the match key, collapse whitespace, strip trailing
punctuation; keep an original display form). Dedupe by normalized key. Count frequency as the number
of distinct source pages a string appears on (page frequency, not raw token count, so one busy page
cannot inflate a term). Drop stopwords and single-character tokens. Rank by page frequency descending,
then by number of distinct nation domains the term appears across (cross-site spread) as a tiebreak.

## B.4 Output format

One ranked seed list, JSONL (one object per line), every string carrying its sources and frequency:

```
{"english": "Community", "key": "community", "category": "nav|heading|program|place|term|greeting",
 "page_frequency": 18, "domain_spread": 14, "source_urls": ["https://nfn.ca/...", "..."],
 "first_seen": "2026-06-25"}
```

Ranked by `page_frequency` then `domain_spread`. This file is the input to the speaker-review queue
and the TM seeding step. It contains no translations; translation is a human, speaker-reviewed step.

---

# C. Translation-memory schema proposal (draft, not built)

Two new content entities, both `_data` CLOB, both consent-gated, both reusing the Language domain
and the `dialect_region` code contract. Field values shown are the `_data` payload; the physical
table is the standard CLOB shape from A.5.

## C.1 `translation_memory` (id `tmid`, label `source_en`)

| Field | Type | Notes |
|---|---|---|
| `source_en` | string (label) | English source string, normalized |
| `source_hash` | string | hash of the normalized source for O(1) exact lookup |
| `dialect_code` | string | references `dialect_region.code` (reuse the contract); null = dialect-agnostic |
| `translation` | string | Anishinaabemowin translation, stored plain (not JSON-wrapped, to avoid the definition gotcha) |
| `confidence` | integer | 0 to 100 |
| `needs_speaker_review` | boolean | default 1 |
| `match_origin` | string | how this row was created: `seed`, `imported`, `speaker`, `fuzzy_promoted` |
| `speaker_id` | entity_reference | to `speaker` (attribution) |
| `contributor_id` | entity_reference | to `contributor` (attribution) |
| `attribution_source` | string | mirrors `dictionary_entry` |
| `attribution_url` | uri | mirrors `dictionary_entry` |
| `source_url` | uri | where the English seed came from |
| `provenance` | text | JSON, mirrors `example_sentence.provenance` |
| `language_code` | string | default `oj` |
| `consent_public` | boolean | default 1, gated by `LanguageAccessPolicy` |
| `consent_ai_training` | boolean | default 0 |
| `status` | boolean | published, default 1 |
| `created_at`, `updated_at` | timestamp | |

Lookup order (decided item 3), all read paths consent-gated and `loadMultiple([])`-guarded:
1. **Exact:** match `source_hash` plus `dialect_code` (fall back to dialect-agnostic rows if no
   dialect-specific row exists).
2. **Fuzzy:** trigram or Levenshtein similarity over `source_en` within the requested dialect, above
   a configurable threshold; return ranked candidates flagged as fuzzy.
3. **Gap log:** on a miss (no exact, no fuzzy above threshold), write or increment a `tm_gap_log` row.

## C.2 `tm_gap_log` (id `glid`, label `source_en`), sibling of `ingest_log`

Mirrors the `ingest_log` write pattern (a service builds it, a controller or worker drives a status
lifecycle), but scoped to lookup misses, not materialization.

| Field | Type | Notes |
|---|---|---|
| `source_en` | string (label) | the requested English string, normalized |
| `source_hash` | string | dedupe key |
| `dialect_code` | string | requested dialect (references `dialect_region.code`), nullable |
| `lookup_type` | string | `exact_miss` or `fuzzy_below_threshold` |
| `best_fuzzy_score` | integer | best similarity seen, nullable |
| `request_count` | integer | incremented on repeat misses (this is the gap frequency) |
| `last_requested_at` | timestamp | |
| `status` | string | `open`, `queued_for_speaker`, `resolved` (mirrors ingest_log's lifecycle) |
| `resolved_tm_id` | integer | the `translation_memory` row that closed the gap, nullable |
| `created_at`, `updated_at` | timestamp | |

Writer (proposed, not built): a `TranslationMemoryService::lookup(string $english, ?string $dialect)`
that runs the three-step order above and, on miss, upserts the gap-log row (increment `request_count`,
bump `last_requested_at`), exactly as `IngestImporter` builds an `ingest_log` and the controller saves
it. A future speaker-review surface reads `tm_gap_log` ordered by `request_count` descending.

Access: add both entities to `LanguageAccessPolicy`'s attribute array (or give `tm_gap_log` its own
policy if gap data should be staff-only) so consent gating and admin bypass apply without new logic.

---

# D. ASR doc-track outline (gated, no code, no public output until Phase 0)

This is a planning track only. Nothing here runs or publishes until the Phase 0 consent agreement
for Steven Bennett's paired audio exists. The Pi serves inference only; training requires a separate
GPU host. The ASR worker is a separate Python/GPU service that minoo.live calls (decided item 4).

## D.1 Data alignment plan for the Bennett paired audio

1. **Inventory.** Catalog every audio file and every transcript: filename, duration, format, sample
   rate, speaker, dialect (map to `dialect_region.code`), recording context, and consent status per
   file. Produce a manifest. Do not move or transform audio yet.
2. **Transcript pairing.** Pair each audio file with its transcript. Where transcripts are already
   utterance-level, pair directly. Where only running text exists, flag for forced alignment.
3. **Segmentation.** Voice-activity detection plus forced alignment (for example Montreal Forced
   Aligner, or whisperX-style alignment) to produce utterance-level
   `(audio_clip, transcript, start, end)` pairs.
4. **Dialect and consent tagging.** Every produced record carries `dialect_code`, `consent_public`,
   and `consent_ai_training`, matching the corpus consent model so the governance gate is enforceable
   at the data layer.

## D.2 First fine-tune approach (decision deferred to inventory sizing)

| Candidate | For | Against |
|---|---|---|
| Whisper (small/medium, fine-tuned) | robust, strong tooling, good with even modest fine-tune data | Ojibwe not well covered out of the box; larger models need more GPU |
| Meta MMS (wav2vec2-based, adapters) | built for low-resource, 1000+ languages incl. some Indigenous North American, small per-language adapters | newer tooling, fewer worked examples |
| wav2vec2 (self-supervised + CTC) | strong low-resource ceiling | needs the most data and engineering to reach it |

Recommendation: pick after the inventory tells us hours-of-audio and utterance count. For a small
corpus, start with an MMS adapter or a Whisper small/medium fine-tune as a low-resource baseline.
Explicit gate: no model weights, no transcripts, and no derived corpus leave the sovereign stack
until Phase 0 consent exists. GPU is required for training; the Pi is inference only.

---

# E. Issue / milestone plan (proposed, no code)

Per the repo's working agreements, Spec Kitty is the canonical execution layer; missions and work
packages carry the work, not GitHub issue numbers as a hard gate. Proposed shape:

**Milestone:** Anishinaabemowin Language API, Phase 1 (TM + `/api/lang`, public-web English only).

Proposed mission / work-package titles:

1. Recon and tracker for the Anishinaabemowin language API (this pass, done).
2. English seed crawler over the 21 RHT nation sites plus minoo.live, producing the ranked JSONL seed
   list (Section B). Public-web English only.
3. `translation_memory` and `tm_gap_log` entities plus their `_data` CLOB migration (Section C, A.5).
4. `TranslationMemoryService` with the exact then fuzzy then gap-log lookup order, plus
   `LanguageAccessPolicy` coverage for the two new entities.
5. `/api/lang` surface: `LanguageApiRouteProvider` plus `LanguageApiController` returning JSON via
   `JsonResponseTrait`, registered in `MinooRoutingStackProvider` (Section A.1).
6. Dialect contract consolidation (decided direction): introduce a single `DialectCodeProvider` seam
   backed by `dialect_region.code` / `ConfigSeeder::dialectRegions()`, correct the stale CLAUDE.md
   claim, and map NorthCloud's free-text `Language` to a code at ingestion (Section A.3). Package
   publication deferred.

**Separate gated doc-track (no code):** ASR data-alignment plan for the Bennett corpus (Section D).
Its gate is the Phase 0 consent agreement; it does not enter the Phase 1 milestone.

---

# Log

## 2026-06-24: Recon pass (report before building)

Recon only, no code written. Findings:

- **Tracker did not exist before today**; this file was created as the recon deliverable and the home
  for the initiative. The minoo MCP spec tools (`minoo_get_spec`, `minoo_search_specs`) were not
  connected in this session, so specs and code were read directly from the repo.
- **API surface:** `MinooRoutingStackProvider` composes one child route provider per domain; there is
  no global `/api` prefix and no `/api/lang` today. JSON via `JsonResponseTrait::json()` (preferred)
  or `new Response($json, 200, [...])`. Recommended seam: a new `LanguageApiRouteProvider` plus a
  dedicated `LanguageApiController`, kept separate from the `/language*` page routes.
- **Language domain:** five entities exist with consent fields already present on `dictionary_entry`,
  `example_sentence`, and `speaker`. Dialect is modeled as a `dialect_region` config entity referenced
  only by `speaker.dialect_region_id`. `LanguageAccessPolicy` gates view on status plus consent. The
  `definition` JSON-wrap gotcha and the `loadMultiple([])` consent footgun (#788) both apply to the API
  read path.
- **Dialect codes (correction + decision):** `jonesrussell/indigenous-taxonomy` is NOT installed; the
  installed `waaseyaa/taxonomy` is a generic Term/Vocabulary framework with no dialect codes. The real
  dialect contract is `ConfigSeeder::dialectRegions()` (10 codes; `oji-east` and `oji-ottawa` cover the
  RHT nations). The CLAUDE.md "shared contract" claim is stale. Decided this pass: make
  `dialect_region.code` canonical now behind a single `DialectCodeProvider` seam (so the package can
  back it later without rewriting callers), correct the CLAUDE.md claim, defer publishing the package.
  NorthCloud cross-checked and defines no dialect codes (free-text `Language` only), so there is no
  competing contract; NC's `Language` must be mapped to a code at ingestion.
- **Gap log:** `ingest_log` is the pattern to mirror (service builds it, controller drives a status
  lifecycle), but the TM gap log should be a sibling entity `tm_gap_log`, lookup-miss-scoped, not an
  overload of the materialization-scoped `ingest_log`.
- **Migration reality:** `_data` CLOB schema is mandatory (no per-field columns); flow is fieldDefs
  then `schema:check` then `make:migration` then `migrate`. Copy
  `migrations/20260612_160000_create_language_domain_tables.php`.
- **RHT site list located, not re-derived:** `Projects/RHT/nations/*.md`, 21 profiles, 20 with official
  URLs, Zhiibaahaasing has none confirmed (UCCMM / Anishinabek Nation). Recommend crawling the 21 plus
  minoo.live only; exclude rhtcircle / oiatc / fnpi per the constraints (and Anokii has no public
  content surface).
- Draft specs for the crawler (B), the TM and gap-log schema (C), the ASR doc-track (D), and the
  mission plan (E) are recorded above. Nothing built.

**Decisions made (2026-06-24, by Russell):**
- Dialect contract: `dialect_region.code` / `ConfigSeeder::dialectRegions()` is canonical now, behind a
  single `DialectCodeProvider` seam so `jonesrussell/indigenous-taxonomy` can back it later without
  rewriting callers; package publication deferred until multi-nation federation demands it. NorthCloud
  checked: no dialect codes there, so no conflict (map NC's free-text `Language` to a code at ingest).
- Crawl set: the 21 nation sites plus minoo.live, excluding rhtcircle / oiatc / fnpi.

## 2026-06-24: Anokii reframe and module decisions

Recon only (read-only across minoo and fnpi-waaseyaa), then docs. Full detail in
[anokii-parity-and-language-module.md](anokii-parity-and-language-module.md). Summary:

- **Reframe:** Anokii is an opt-in library, not a single canonical admin. The `waaseyaa/anokii`
  package has no `extra.waaseyaa.providers` block, so it auto-mounts nothing; each app's own
  `App\Provider\AnokiiServiceProvider` composes it. fnprocure (fnpi-waaseyaa) is the fullest consumer
  and hand-codes FNPI business modules (ventures, documents, drive, pages, identity pillars, inbox,
  analytics) that minoo must NOT copy. The package ships a clean module seam nobody fully uses yet:
  the `Anokii\Admin\AdminModules` catalog plus the config-gated module-provider pattern in
  `Anokii\Provider\CoIntelligenceServiceProvider`. minoo's `/admin/anokii` today is a working language
  corpus pipeline that reuses only the package shell template (`@anokii` namespace).
- **Locked decisions** (each with its code seam, full text in the companion doc): D1 full model parity
  first (catalog shell + minimal `config/anokii.yaml` + `DistributionConfig`/`TenancyMode` + the
  module-provider seam, re-home the corpus pipeline, then build the module). D2 seam is
  module-as-ServiceProvider, config-gated (the `CoIntelligenceServiceProvider` pattern), not
  fnprocure's hardcoded routes. D3 do NOT adopt the Anokii graph entities (`community`, `place`, ...);
  keep minoo's own `community`; Co-Intelligence over the language graph is out of scope. D4 keep
  minoo's role model (`admin`, `elder_coordinator`); do not adopt the single-admin
  `WorkspaceLoginController`. D5 per-module `/api/*`: `/api/lang` is a peer to `/api/chat`, no single
  gateway. D6 catalog ownership local now (`AdminModules::resolve(..., $extra)`), upstream a
  `waaseyaa/anokii-language` package later, mirroring the dialect-package deferral. D7 one language
  module, not two: the corpus pipeline is the module's admin tile at `/admin/anokii/language`, and the
  TM / `/api/lang` / gap-log / gated ASR are that same module's data, API, and services. D8 ASR stays
  consent-gated behind an `AsrClient` binding; no public ASR surface until Phase 0.
- **CLAUDE.md drift still open:** minoo's own `CLAUDE.md` "Architectural Boundaries" section still
  wrongly claims `jonesrussell/indigenous-taxonomy` is an installed shared contract. This is already
  owned by mission 6 (dialect contract consolidation); cross-referenced here, not separately tracked.
- A proposed milestone (`Anokii parity and the language module`) and an ordered 7-issue build sequence
  are recorded in the companion doc.

**Next action:** two parallel tracks. (1) Independent and ready now: the English seed crawler (mission
2), no Anokii / dialect / consent dependency, produces the ranked JSONL seed list from the 21 nation
sites plus minoo.live. (2) The `Anokii parity and the language module` milestone (companion doc), whose
first issue is introducing `config/anokii.yaml` + `DistributionConfig` wiring, then the catalog-driven
shell, then re-homing the pipeline, then the `LanguageModuleServiceProvider` carrying the TM, the
`DialectCodeProvider` seam, `/api/lang`, and the gated `AsrClient` stub. ASR stays blocked on the Phase
0 consent agreement. Holding for the go-ahead on a build prompt; reporting the milestone and issue plan
first as instructed.
