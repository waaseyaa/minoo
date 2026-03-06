# Minoo v0.2 — Foundation + First Entities + NorthCloud Ingestion

**Date:** 2026-03-06
**Status:** Approved
**Repo:** `waaseyaa/minoo`
**Milestone:** v0.2 – First Entities + NorthCloud Ingestion

## Vision

Minoo is the first production application built on the Waaseyaa framework. It serves Indigenous communities worldwide as a knowledge platform combining community content, cultural teachings, and structured language data. It is powered by NorthCloud's content pipeline for discovery and ingestion of external sources.

## Architecture Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Data model approach | Evolve from diidjaaheer | Use Waaseyaa's entity/field/taxonomy system instead of Laravel's hardcoded models |
| Design system | New identity for Minoo | Broader global scope than diidjaaheer's Anishinaabe-focused palette |
| ojibwe.lib.umn.edu strategy | Hybrid: NorthCloud discovery + Minoo-side structured import | Site is a language dictionary, not articles — needs custom parsing |
| Execution approach | Entity-first with parallel ingestion track | Validate framework with simple entities before tackling complex ones |
| ExampleSentence | Own entity | Enables granular sentence → audio → speaker relationships |
| cultural_collection.gallery | Taxonomy vocabulary | Consistent filtering, admin-managed, reusable |

## Entity Model

### Content Entities (9)

All content entities include `created_at` / `updated_at` timestamps (auto-managed, explicit in contract).

#### event (bundled by event_type)

| Field | Type | Required | Notes |
|---|---|---|---|
| eid | integer (auto) | key | Primary key |
| uuid | uuid | auto | |
| title | string | yes | Event name |
| type | string | yes | Bundle key → event_type |
| slug | string | auto | URL-safe, derived from title |
| description | text_long | no | Rich text body |
| location | string | no | Physical location or "online" |
| starts_at | datetime | yes | |
| ends_at | datetime | no | Null = open-ended |
| media_id | entity_reference → media | no | Featured image |
| status | boolean | yes | Default: 1 |
| created_at | datetime | auto | |
| updated_at | datetime | auto | |

#### group (bundled by group_type)

| Field | Type | Required | Notes |
|---|---|---|---|
| gid | integer (auto) | key | |
| uuid | uuid | auto | |
| name | string | yes | |
| type | string | yes | Bundle key → group_type |
| slug | string | auto | |
| description | text_long | no | |
| url | uri | no | External website |
| region | string | no | Geographic region |
| media_id | entity_reference → media | no | |
| status | boolean | yes | Default: 1 |
| created_at | datetime | auto | |
| updated_at | datetime | auto | |

#### cultural_group (hierarchical)

| Field | Type | Required | Notes |
|---|---|---|---|
| cgid | integer (auto) | key | |
| uuid | uuid | auto | |
| name | string | yes | e.g., "Anishinaabe", "Ojibwe", "Mille Lacs Band" |
| slug | string | auto | |
| parent_id | entity_reference → cultural_group | no | Self-referential tree |
| depth_label | string | no | Free-text depth descriptor (nation, tribe, band, clan) |
| description | text_long | no | |
| metadata | text | no | JSON blob for extensible properties |
| media_id | entity_reference → media | no | |
| sort_order | integer | no | Manual ordering within siblings |
| status | boolean | yes | Default: 1 |
| created_at | datetime | auto | |
| updated_at | datetime | auto | |

#### teaching (bundled by teaching_type)

| Field | Type | Required | Notes |
|---|---|---|---|
| tid | integer (auto) | key | |
| uuid | uuid | auto | |
| title | string | yes | |
| type | string | yes | Bundle key → teaching_type |
| slug | string | auto | |
| content | text_long | yes | Rich text body |
| cultural_group_id | entity_reference → cultural_group | no | |
| tags | entity_reference → taxonomy_term (multi) | no | Vocabulary: teaching_tags |
| media_id | entity_reference → media | no | |
| status | boolean | yes | Default: 1 |
| created_at | datetime | auto | |
| updated_at | datetime | auto | |

#### cultural_collection

| Field | Type | Required | Notes |
|---|---|---|---|
| ccid | integer (auto) | key | |
| uuid | uuid | auto | |
| title | string | yes | e.g., "Loon", "Bandolier Bag" |
| slug | string | auto | |
| description | text_long | no | Cultural context |
| gallery | entity_reference → taxonomy_term | no | Vocabulary: gallery |
| source_url | uri | no | Original URL from ojibwe.lib.umn.edu |
| source_attribution | string | no | e.g., "Copyright Minnesota DNR" |
| media_id | entity_reference → media | yes | Primary image |
| status | boolean | yes | Default: 1 |
| created_at | datetime | auto | |
| updated_at | datetime | auto | |

#### dictionary_entry

| Field | Type | Required | Notes |
|---|---|---|---|
| deid | integer (auto) | key | |
| uuid | uuid | auto | |
| word | string | yes | Ojibwe headword (e.g., "jiimaan") |
| slug | string | auto | |
| definition | string | yes | English definition |
| part_of_speech | string | yes | Code: ni, na, vai, vti, vta, vii, nad, nid, etc. |
| stem | string | no | Root stem (e.g., "/jiimaan-/") |
| inflected_forms | text | no | JSON: [{"form": "jiimaanan", "label": "pl"}, ...] |
| language_code | string | no | ISO-style code, default "oj". Supports dialect codes (oj-sw, oj-nw). Optional v0.2, required v0.3. |
| source_url | uri | no | Original ojibwe.lib.umn.edu URL |
| status | boolean | yes | Default: 1 |
| created_at | datetime | auto | |
| updated_at | datetime | auto | |

#### example_sentence

| Field | Type | Required | Notes |
|---|---|---|---|
| esid | integer (auto) | key | |
| uuid | uuid | auto | |
| ojibwe_text | string | yes | Sentence in Ojibwe |
| english_text | string | yes | English translation |
| dictionary_entry_id | entity_reference → dictionary_entry | yes | |
| speaker_id | entity_reference → speaker | no | |
| audio_url | uri | no | Link to audio recording |
| language_code | string | no | Inherits default from parent dictionary_entry if unset |
| status | boolean | yes | Default: 1 |
| created_at | datetime | auto | |
| updated_at | datetime | auto | |

#### word_part

| Field | Type | Required | Notes |
|---|---|---|---|
| wpid | integer (auto) | key | |
| uuid | uuid | auto | |
| form | string | yes | The morpheme (e.g., "minw-") |
| slug | string | auto | |
| type | string | yes | initial, medial, final |
| definition | string | no | Meaning of the morpheme |
| source_url | uri | no | |
| status | boolean | yes | Default: 1 |
| created_at | datetime | auto | |
| updated_at | datetime | auto | |

#### speaker

| Field | Type | Required | Notes |
|---|---|---|---|
| sid | integer (auto) | key | |
| uuid | uuid | auto | |
| name | string | yes | Full name (e.g., "Larry Smallwood") |
| slug | string | auto | |
| code | string | yes | Abbreviation used on source site (e.g., "es", "nj", "gh") |
| bio | text_long | no | |
| media_id | entity_reference → media | no | Photo |
| status | boolean | yes | Default: 1 |
| created_at | datetime | auto | |
| updated_at | datetime | auto | |

### Config Entities (3)

#### event_type

| Field | Type | Notes |
|---|---|---|
| type | string | Machine name key (powwow, gathering, ceremony) |
| name | string | Human label |
| description | text | |

#### group_type

| Field | Type | Notes |
|---|---|---|
| type | string | Machine name (online, offline, advocacy) |
| name | string | Human label |

#### teaching_type

| Field | Type | Notes |
|---|---|---|
| type | string | culture, history, language — extensible |
| name | string | Human label |

### Taxonomy Vocabularies (2)

#### gallery
Initial terms: fishing, sugaring, lodges, hidework, ricing, wintertravel

#### teaching_tags
Initial terms: ceremony, governance, land, kinship, language, history

### Built-in Entities (used as-is)

- **node** (bundled by node_type) — NorthCloud articles ingested as nodes of bundle type "article"
- **media** — Images, audio, video
- **user** — Authentication and admin accounts
- **taxonomy_term** / **vocabulary** — Used by gallery and teaching_tags

## NorthCloud Integration

### Discovery Phase (NorthCloud)
1. Configure ojibwe.lib.umn.edu as a source in NorthCloud's source-manager
2. Crawler discovers and indexes all pages (dictionary entries, collections, word parts, speakers)
3. Indigenous classifier tags content with categories (language, culture, anishinaabe)
4. Publisher routes to `content:indigenous` and `indigenous:category:*` Redis channels

### Structured Import Phase (Minoo)
1. Minoo subscribes to NorthCloud Redis channels for discovered URLs
2. Custom importer re-fetches pages and parses structured data using CSS selectors:
   - `/main-entry/*` → dictionary_entry + example_sentence entities
   - `/word-part/*` → word_part entities
   - `/speaker/*` → speaker entities
   - `/collection/*` → cultural_collection entities
   - `/category/galleries/*` → gallery taxonomy terms
   - `/category/dictionary/*` → (index pages, extract entry URLs for crawling)
3. Entity references are wired after all entities exist (speaker_id on example_sentence, etc.)
4. Deduplication by source_url prevents re-import of unchanged content

### Article Ingestion (Standard NorthCloud)
Standard NorthCloud article ingestion for Indigenous news content:
- Minoo subscribes to existing Indigenous Redis channels
- Articles create Node entities of bundle type "article"
- No custom parsing needed — uses standard NorthCloud article format

## Execution Strategy

### Phase 1: Repository + CI Foundation
- Create `waaseyaa/minoo` GitHub repo
- Initialize with Waaseyaa skeleton
- Set up CI (lint, tests, type checks)
- Create milestone and issue tracker

### Phase 2: Simple Entities (Events + Groups)
- Define event, event_type, group, group_type entities
- Implement access policies
- Verify admin SPA auto-discovery works
- Add SSR public pages
- **Purpose:** Validate Waaseyaa framework end-to-end with low-risk entity types

### Phase 3: Knowledge Entities (Cultural Groups + Teachings + Collections)
- Define cultural_group (hierarchical), teaching, teaching_type, cultural_collection
- Create gallery and teaching_tags taxonomy vocabularies
- Implement tree traversal for cultural_group hierarchy
- Add access policies
- **Purpose:** Tackle the complex hierarchical and taxonomy-based entities

### Phase 4: Language Entities (Dictionary + Sentences + Speakers)
- Define dictionary_entry, example_sentence, word_part, speaker
- Implement structured data field handling (inflected_forms JSON)
- Wire entity references (sentence → entry, sentence → speaker)
- **Purpose:** Build the linguistic data model for ojibwe.lib.umn.edu content

### Phase 5: NorthCloud Ingestion Pipeline
- Configure ojibwe.lib.umn.edu source in NorthCloud
- Build Minoo-side custom importer with CSS selectors per page type
- Wire standard article ingestion via Node entities
- Add ingestion diagnostics and admin review queue
- **Purpose:** Populate the site with real content

### Phase 6: Design System + Public UI
- Establish new Minoo visual identity (palette, typography, spacing)
- Build public SSR templates for all entity types
- Global navigation, search, filtering
- Responsive layouts
- **Purpose:** Ship the public-facing site

## GitHub Issues (v0.2 Milestone)

### A. Repository + Framework Setup
1. Initialize waaseyaa/minoo repo with skeleton, CI, and branch protection
2. Set up design system foundation (colors, typography, spacing tokens)

### B. Entity System — Events + Groups
3. Define event + event_type entity schemas and service providers
4. Define group + group_type entity schemas and service providers
5. Implement access policies for event and group
6. Add SSR public pages for events and groups

### C. Entity System — Cultural Knowledge
7. Define cultural_group entity with hierarchical parent_id tree
8. Define teaching + teaching_type entity schemas
9. Define cultural_collection entity with gallery taxonomy
10. Create gallery and teaching_tags taxonomy vocabularies with seed terms
11. Implement access policies for cultural_group, teaching, cultural_collection

### D. Entity System — Language
12. Define dictionary_entry entity with inflected_forms JSON field
13. Define example_sentence entity with speaker + dictionary_entry references
14. Define word_part and speaker entities
15. Implement access policies for all language entities

### E. NorthCloud Ingestion
16. Configure ojibwe.lib.umn.edu source in NorthCloud source-manager
17. Build Minoo-side structured importer (CSS selectors per page type)
18. Wire standard NorthCloud article ingestion via Node entities
19. Add ingestion diagnostics and admin review queue

### F. Integration + Public UI
20. Build global navigation and layout (SSR)
21. Add search and filtering across entity types
22. Create seeders for demo content
