# Minoo Platform Vision — Design Spec

**Date:** 2026-03-19
**Status:** Approved
**Scope:** Cross-project vision spanning Minoo, North Cloud, Waaseyaa, and two new repos

## 1. Vision

Transform Minoo from a community-focused Indigenous knowledge platform into a comprehensive, map-driven content discovery platform — starting Canada-wide, architected for global reach. Content flows through two pipelines: news via North Cloud's classification engine, and structured knowledge (language, events, communities, teachings) via Python harvesting tools directly into Minoo's ingestion layer.

The map — already proven on `/communities` with Leaflet, Alpine.js, marker clustering, and proximity grouping — becomes the universal discovery interface. Language content adapts dialect-by-dialect as users pan across regions.

## 2. Architectural Boundaries

Three layers with enforced separation:

| Layer | Owns | Does NOT own |
|-------|------|-------------|
| **Waaseyaa** (framework) | Entity system, storage, field types, ingestion envelope contract, GraphQL/REST API, access control, SSR rendering | Content classification, map UX, dialect logic, harvesting |
| **North Cloud** (pipeline) | Crawling, classification (rules + ML), enrichment, routing, Redis pub/sub, scheduled harvesting, source registry | Entity model, frontend rendering, language/dialect data |
| **Minoo** (application) | Entity types, map-driven UX, dialect-aware content, access policies, templates, CSS, community-specific features | Classification logic, crawl scheduling, framework internals |

### 2.1 Boundary Governance

Each repo's CLAUDE.md includes a boundary rules section documenting:
- What belongs in this repo vs. adjacent repos
- Import direction rules (Minoo imports from Waaseyaa, never reverse)
- Shared contracts consumed (taxonomy, ingestion envelope)

Automated drift checks:
- Minoo: `bin/check-milestones` includes boundary check (no NC classifier logic in Minoo, no Minoo entity types in Waaseyaa)
- NC: lint rule ensuring classifier imports taxonomy from shared package, not hardcoded slugs
- Waaseyaa: no Minoo-specific entity types in framework packages

## 3. Hybrid Content Pipeline

### 3.1 News Path (via North Cloud)

```
Indigenous news sources (APTN, Windspeaker, Nation Talk, band council news)
  → Python harvesters register sources in NC source-manager (source_type: crawled)
  → NC crawler extracts via CSS selectors
  → ES raw_content index
  → NC classifier: indigenous_rules.go (multilingual regex + 10-category keywords)
    + indigenous-ml sidecar (confidence scoring)
  → ES classified_content index
  → NC publisher L7 indigenous routing:
    - content:indigenous (catch-all)
    - indigenous:category:{slug} (per category from shared taxonomy)
    - indigenous:region:{slug} (per region from shared taxonomy)
  → Redis pub/sub
  → Minoo subscriber → news entity → SQLite → map display
```

**When to use this path:** Content that benefits from classification, confidence scoring, dedup, or enrichment. Primarily news articles and editorial content.

### 3.2 Structured Path (direct to Minoo)

```
Structured data sources (Ojibwe People's Dictionary, FPCC, CIRNAC, powwow feeds)
  → Python harvesters register sources in NC source-manager (source_type: structured)
  → Python tools emit waaseyaa-compliant ingestion envelopes:
    {source, type, payload, timestamp, trace_id, tenant_id, metadata}
  → Minoo ingestion pipeline:
    PayloadValidator → Entity Mapper → MaterializationContext → IngestMaterializer
  → SQLite storage → map display
```

**When to use this path:** Content with known schemas that doesn't need classification — language data, events, community profiles, cultural knowledge, teachings.

### 3.3 Source Registry

NC's source-manager is the single authoritative registry for all content sources. Extended with:

```
source_type: crawled | structured | api
```

- **crawled**: Existing NC sources with CSS selectors, rate limits, crawl schedules
- **structured**: Python harvester sources — metadata only (name, URL, format, update frequency, license, attribution). No crawl schedule; harvesters manage their own scheduling.
- **api**: Future API-based sources

Python harvesters call source-manager's API to register on first run. This answers "where does our data come from?" in one place without NC taking scheduling responsibility for non-crawled sources.

## 4. Shared Taxonomy Contract

### 4.1 Repository

`jonesrussell/indigenous-taxonomy` — standalone repo, single purpose.

### 4.2 Layout

```
indigenous-taxonomy/
├── schema/
│   ├── categories.yaml              # 10+ content categories
│   ├── regions.yaml                 # Hierarchical geographic regions
│   ├── dialect-codes.yaml           # Language families → dialects → regions
│   └── validation/                  # JSON Schema for each YAML file
├── generated/
│   ├── go/taxonomy/                 # Go module: constants + lookup maps
│   ├── php/src/                     # Composer package: PHP 8.4 backed enums
│   └── python/indigenous_taxonomy/  # PyPI package: str enums + dataclasses
├── scripts/
│   └── generate.py                  # Single generator: YAML → Go + PHP + Python
├── tests/                           # Schema validation, slug uniqueness, generated consistency
├── Taskfile.yml                     # task generate, task validate, task release
├── .github/workflows/
│   ├── validate.yml                 # On PR: validate, generate, test, diff
│   └── release.yml                  # On tag: publish Go module, Composer, PyPI
├── CLAUDE.md
└── CHANGELOG.md
```

### 4.3 Canonical YAML Schema

**categories.yaml:**

```yaml
version: 1

categories:
  - slug: culture
    name: Culture
    description: Cultural practices, ceremonies, traditions, art, music
    nc_routing: true

  - slug: language
    name: Language
    description: Language revitalization, dictionaries, dialects, immersion programs
    nc_routing: true

  - slug: land-rights
    name: Land Rights
    description: Treaty rights, land claims, territorial sovereignty
    nc_routing: true

  - slug: environment
    name: Environment
    description: Environmental stewardship, traditional ecological knowledge, climate
    nc_routing: true

  - slug: sovereignty
    name: Sovereignty
    description: Self-governance, nation-to-nation relations, self-determination
    nc_routing: true

  - slug: education
    name: Education
    description: Indigenous education, curriculum, schools, knowledge transmission
    nc_routing: true

  - slug: health
    name: Health
    description: Indigenous health, traditional medicine, wellness, mental health
    nc_routing: true

  - slug: justice
    name: Justice
    description: Legal rights, MMIWG, policing, incarceration, restorative justice
    nc_routing: true

  - slug: history
    name: History
    description: Historical events, residential schools, treaties, reconciliation
    nc_routing: true

  - slug: community
    name: Community
    description: Community news, events, leadership, services, economic development
    nc_routing: true
```

**regions.yaml:**

```yaml
version: 1

regions:
  - slug: canada
    name: Canada
    children:
      - slug: canada:british-columbia
        name: British Columbia
      - slug: canada:alberta
        name: Alberta
      - slug: canada:saskatchewan
        name: Saskatchewan
      - slug: canada:manitoba
        name: Manitoba
        children:
          - slug: canada:manitoba:southern
            name: Southern Manitoba
      - slug: canada:ontario
        name: Ontario
        children:
          - slug: canada:ontario:northern
            name: Northern Ontario
          - slug: canada:ontario:north-shore-huron
            name: North Shore of Lake Huron
          - slug: canada:ontario:southern
            name: Southern Ontario
      - slug: canada:quebec
        name: Quebec
      - slug: canada:atlantic
        name: Atlantic Canada
      - slug: canada:north
        name: Northern Canada
        children:
          - slug: canada:north:yukon
            name: Yukon
          - slug: canada:north:nwt
            name: Northwest Territories
          - slug: canada:north:nunavut
            name: Nunavut
```

**dialect-codes.yaml:**

```yaml
version: 1

language_families:
  - slug: algonquian
    name: Algonquian
    dialects:
      - code: oji-east
        name: Nishnaabemwin
        display_name: Eastern Ojibwe
        iso_639_3: ojg
        regions:
          - canada:ontario:north-shore-huron
          - canada:ontario:southern

      - code: oji-northwest
        name: Anishinaabemowin
        display_name: Northwestern Ojibwe
        iso_639_3: ojb
        regions:
          - canada:ontario:northern

      - code: oji-plains
        name: Nakawēmowin
        display_name: Saulteaux / Plains Ojibwe
        iso_639_3: ojs
        regions:
          - canada:manitoba:southern
          - canada:saskatchewan

      - code: oji-ottawa
        name: Odaawaa
        display_name: Ottawa / Odawa
        iso_639_3: otw
        regions:
          - canada:ontario:southern

      - code: cree-plains
        name: nēhiyawēwin
        display_name: Plains Cree
        iso_639_3: crk
        regions:
          - canada:saskatchewan
          - canada:alberta

      - code: cree-swampy
        name: Ininīmowin
        display_name: Swampy Cree
        iso_639_3: csw
        regions:
          - canada:manitoba
          - canada:ontario:northern

      - code: innu
        name: Innu-aimun
        display_name: Innu
        iso_639_3: moe
        regions:
          - canada:quebec
          - canada:atlantic

  - slug: eskimo-aleut
    name: Eskimo-Aleut
    dialects:
      - code: inuktitut
        name: ᐃᓄᒃᑎᑐᑦ
        display_name: Inuktitut
        iso_639_3: iku
        regions:
          - canada:north:nunavut
          - canada:quebec

      - code: inuvialuktun
        name: Inuvialuktun
        display_name: Inuvialuktun
        iso_639_3: ikt
        regions:
          - canada:north:nwt

  - slug: iroquoian
    name: Iroquoian
    dialects:
      - code: mohawk
        name: Kanien'kéha
        display_name: Mohawk
        iso_639_3: moh
        regions:
          - canada:ontario:southern
          - canada:quebec
```

### 4.4 Generated Code Shape

**Go** — NC classifier and publisher import these:
```go
package taxonomy

type Category string
const (
    CategoryCulture    Category = "culture"
    CategoryLanguage   Category = "language"
    // ...
)
func IsValidCategory(s string) bool
func RoutableCategories() []Category
```

**PHP** — Minoo seeders and entity validation import these:
```php
enum Category: string {
    case Culture = 'culture';
    case Language = 'language';
    // ...
    public static function routable(): array;
}
```

**Python** — Harvesters import these for tagging:
```python
class Category(str, Enum):
    CULTURE = "culture"
    LANGUAGE = "language"
```

Each package embeds `TAXONOMY_VERSION` and `SCHEMA_HASH` for drift detection.

### 4.5 Versioning

SemVer on the taxonomy:

| Change | Bump | Example |
|--------|------|---------|
| Description/docs only | Patch | Fix typo in category description |
| Add category/region/dialect | Minor | Add `food-sovereignty` category |
| Rename or remove slug | Major | Rename `land-rights` → `territorial-rights` |

**Deprecation protocol for breaking changes:**
1. Mark old slug `deprecated: true, replaced_by: new-slug` in YAML
2. Generated code includes both for one minor version (deprecation window)
3. Next major release removes deprecated entries

### 4.6 Consumer Validation & Propagation

1. **Dependency pinning**: Each consumer pins `^1.0`. Dependabot/Renovate opens PRs on new releases.
2. **CI version check**: Each repo's CI verifies taxonomy ≥ minimum required version. Minoo's `bin/check-milestones` includes taxonomy drift check.
3. **Runtime hash** (optional): Generated code embeds `SCHEMA_HASH`. NC classifier logs warning on mismatch.

**Safe propagation flow:**
1. PR to indigenous-taxonomy → CI validates + generates + tests
2. Merge + tag → CI publishes Go module, Composer package, PyPI package
3. Dependabot PRs in NC, Minoo, harvesters → each merges when tests pass
4. Breaking changes: deprecation window in minor release first, then major release removes

## 5. Dialect Region Config Entity

A Minoo config entity for map-driven dialect switching:

**Entity type:** `dialect_region`
**Base class:** `ConfigEntityBase` (seed-based, in-memory)
**Fields:**
- `code` — canonical dialect code from shared taxonomy (e.g., `oji-east`)
- `name` — Indigenous name (e.g., `Nishnaabemwin`)
- `display_name` — English display name (e.g., `Eastern Ojibwe`)
- `language_family` — parent family slug (e.g., `algonquian`)
- `iso_639_3` — ISO language code
- `regions` — array of region slugs from shared taxonomy
- `boundary_geojson` — GeoJSON polygon defining the dialect's geographic boundary

**Seeded via:** `TaxonomySeeder::dialectRegions()` — reads from shared taxonomy PHP package + bundled GeoJSON boundary files.

**Map integration:** Client-side JS checks which dialect region polygon contains the map viewport center. When the region changes, language content (dictionary entries, greetings, UI labels) re-queries filtered by that dialect code.

**Global-ready:** When Phase 4 adds Māori, Sámi, etc., new dialect regions are added to the shared taxonomy YAML, new GeoJSON boundaries are bundled, and the seeder picks them up automatically.

## 6. Data Policy

**Default:** Aggregate and link back to source. Content stays at the source; Minoo links to it.

**Where license allows:** Host content directly with attribution. Example: Ojibwe People's Dictionary allows hosting with footer attribution.

**Per-source tracking:** Each harvester records license type and attribution requirements in source-manager registration. Minoo renders appropriate attribution based on source metadata.

**Three tiers of sources:**
1. **Public institutional** — CIRNAC, StatsCan, provincial ministries, Canada Council
2. **Indigenous organizations** — AFN, ITK, MNC, tribal councils, band councils, Indigenous media, friendship centres
3. **Academic/archival** — university programs, museums, language archives (FPCC, Endangered Languages Project)

Community-contributed content (Tier 3 in the original discussion: Elders, Knowledge Keepers, community members) is Minoo's own contribution system — not part of the harvesting tools.

## 7. Milestone Roadmap

### Phase 0 — Foundations & Governance

No features ship without this. Establishes contracts everything else depends on.

| Milestone | Repo | Deliverables |
|-----------|------|-------------|
| M0.1 Shared Taxonomy Contract | new: indigenous-taxonomy | Canonical YAML (categories, regions, dialect codes), JSON Schema validation, CI-generated Go/PHP/Python packages, Taskfile, release workflow |
| M0.2 Source Registry Extension | north-cloud | `source_type` enum (crawled/structured/api) in source-manager, API for registering non-crawled sources, admin UI update |
| M0.3 Boundary Governance | all repos | CLAUDE.md boundary rules, automated drift checks (bin/check-milestones, lint rules), cross-repo import direction enforcement |
| M0.4 Dialect Region Entity | minoo | `dialect_region` config entity, TaxonomySeeder integration, GeoJSON boundary files for Canadian Ojibwe dialects, unit tests |

### Phase 1 — Python Harvesting Tools + First Content

Depends on M0.1 (taxonomy) and M0.2 (source registry).

| Milestone | Repo | Deliverables |
|-----------|------|-------------|
| M1.1 Harvester Scaffold | new: indigenous-harvesters | Python CLI framework, waaseyaa envelope emitter, NC source-manager registration client, taxonomy package import, license/attribution tracker, test harness |
| M1.2 Language Harvesters | indigenous-harvesters + minoo | Ojibwe People's Dictionary harvester (host + attribute), FPCC language map data, dialect-tagged envelopes, end-to-end structured path validation |
| M1.3 Community & Event Harvesters | indigenous-harvesters + minoo | CIRNAC community data expansion, powwow/event feed harvesting, band council website scraping, community profile enrichment |
| M1.4 News Path via North Cloud | north-cloud + minoo | Indigenous news sources registered in NC (APTN, Windspeaker, Nation Talk), Minoo Redis subscriber, news entity type in Minoo, end-to-end news path validation |

### Phase 2 — Map Generalization & Dialect UX

Depends on M0.4 (dialect entity) and Phase 1 content existing.

| Milestone | Repo | Deliverables |
|-----------|------|-------------|
| M2.1 Universal Atlas Component | minoo | Refactored atlas-discovery.js as reusable component, content-type layer toggles (events, groups, teachings, language, news), viewport-based content filtering API, replaces per-page proximity logic |
| M2.2 Dialect-Aware Map | minoo (+waaseyaa if GeoRegionField needed) | Viewport dialect region detection from GeoJSON polygons, language content switches to local dialect, dialect indicator in header, smooth region transitions |
| M2.3 Homepage Atlas Integration | minoo | Map-first homepage, current tabs become layer filters, "Nearby" replaced by actual map proximity, content cards update on pan/zoom, mobile-responsive map/list split |

### Phase 3 — Content Depth & NC Expansion

Depends on Phases 1-2 proving the pattern.

| Milestone | Repo | Deliverables |
|-----------|------|-------------|
| M3.1 Cultural Knowledge Harvesters | indigenous-harvesters | Teachings from public archives (with permission), traditional ecological knowledge, oral history transcripts, museum/gallery exhibitions, Canada Council arts events |
| M3.2 NC Indigenous Classifier v2 | north-cloud | ML sidecar retrained on expanded corpus, community-level region detection, taxonomy expansion from shared contract, confidence threshold tuning |
| M3.3 Content Quality & Freshness | all | Cross-pipeline dedup, staleness detection (past events, broken links), source health monitoring, content quality scoring, attribution audit dashboard |

### Phase 4 — Global-Ready Architecture

Depends on Phase 3 proving Canada-wide at scale.

| Milestone | Repo | Deliverables |
|-----------|------|-------------|
| M4.1 Multi-Language-Family Support | minoo + waaseyaa | Entity model supports multiple language families, dialect region mapping extends globally, framework i18n leveraged for UI localization, taxonomy adds global regions |
| M4.2 International Harvesters | indigenous-harvesters + NC | New Zealand (Māori), Australia (Aboriginal/Torres Strait), Scandinavia (Sámi), Americas — NC classifier patterns expanded with dedicated sources |
| M4.3 Public API & Federation | waaseyaa + minoo | GraphQL API for third-party consumers, federation protocol for multi-instance content sharing, API rate limiting and access control |

### Dependency Chain

```
Phase 0 (Foundations) → no dependencies, start immediately
Phase 1 (Harvesting) → depends on M0.1 + M0.2
Phase 2 (Map)        → depends on M0.4 + Phase 1 content
Phase 3 (Depth)      → depends on Phase 1 + Phase 2
Phase 4 (Global)     → depends on Phase 3 at scale
```

## 8. Key Design Decisions

| Decision | Rationale |
|----------|-----------|
| Hybrid pipeline (news via NC, structured via Minoo) | NC's classifier adds value for news; structured data has known schemas that don't need classification |
| Language/dialect data lives in Minoo, not NC | Dialect-specific dictionary entries are structured knowledge, not classified content. NC routes language-related news only. |
| Dialect region as Minoo config entity (Option A) | Lives where consumed, fast (in-memory), extensible to global regions. Extract to shared GeoJSON later if Python tools need it. |
| Source-manager as unified registry | Already has the model, API, and admin surface. Adding source_type is simpler than a new service. |
| Shared taxonomy as separate repo with generated packages | Single source of truth prevents slug drift across Go, PHP, Python. SemVer + Dependabot ensures safe propagation. |
| Canada-first, global-ready architecture | Build for the known scope (Canada), but don't make decisions that prevent global expansion. Region hierarchy, language family structure, and config entity patterns all support future internationalization. |

## 9. Open Questions for Future Phases

- **Community contribution system**: How do Elders and Knowledge Keepers submit content directly? This is Minoo's own feature, not part of harvesting tools. Needs its own design spec.
- **Content moderation**: As content volume grows, what moderation workflows are needed? Especially for community-contributed and harvested content.
- **Offline/mobile**: Should Minoo support offline access for remote communities with limited connectivity?
- **Sovereignty hosting**: Should communities be able to run their own Minoo instance with data staying on their infrastructure?
