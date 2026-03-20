# Phase 1: Python Harvesting Tools + First Content — Design Spec

> **Depends on:** Phase 0 (complete) — indigenous-taxonomy, NC structured source types, dialect_region entity, boundary governance

## 1. Purpose

Build a Python harvester framework that fetches Indigenous content from public data sources, wraps it in Waaseyaa ingestion envelopes, and delivers it through North Cloud's pipeline to Minoo. Phase 1 delivers the first real content: OPD dictionary entries, FPCC language data, expanded community data, powwow/event feeds, and Indigenous news articles.

## 2. Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Content delivery path | NC-mediated (all content flows through North Cloud) | Single pipeline, NC remains content authority, enables classification enrichment |
| Execution model | CLI commands + external cron scheduling | Simplest, most debuggable, stateless runs, easy manual invocation |
| Framework pattern | Plugin-based CLI with shared core library | Standard data pipeline pattern (Singer/Airbyte style), isolates domain from infrastructure |
| OPD harvester vs Go importer | Python harvester replaces Go importer | One canonical path, adds taxonomy tagging and source registration |
| News path | NC crawler (not harvesters) | News sources are crawled, not structured — reuse existing NC pipeline |

## 3. Architecture

```
                                   indigenous-harvesters (Python)
                                   ┌─────────────────────────────┐
Public data sources ──fetch()──►   │  OPD / FPCC / CIRNAC / etc. │
                                   │         (harvesters)         │
                                   └──────────┬──────────────────┘
                                              │ Waaseyaa envelopes
                                              ▼
                                   ┌──────────────────────┐
                                   │   North Cloud         │
                                   │  POST /api/v1/ingest  │
                                   │  (validate + classify  │
                                   │   + Redis pub/sub)     │
                                   └──────────┬───────────┘
                                              │ Redis channels
                                              ▼
                                   ┌──────────────────────┐
                                   │   Minoo               │
                                   │  RedisSubscriber →    │
                                   │  IngestImporter →     │
                                   │  EntityMapper →       │
                                   │  Entity storage       │
                                   └──────────────────────┘
```

**News path (M1.4 only):**
```
Indigenous news sites ──NC crawler──► classify ──► Redis pub/sub ──► Minoo
```

## 4. Repository Structure — indigenous-harvesters

```
indigenous-harvesters/
├── src/harvest/
│   ├── __init__.py
│   ├── cli.py                    # Click CLI: harvest run <name> [--dry-run]
│   ├── core/
│   │   ├── __init__.py
│   │   ├── harvester.py          # Harvester protocol + base class
│   │   ├── envelope.py           # Waaseyaa envelope builder
│   │   ├── nc_client.py          # NC source-manager API client
│   │   ├── nc_publisher.py       # NC ingest endpoint delivery
│   │   ├── registry.py           # Harvester plugin registry
│   │   ├── runner.py             # register → fetch → transform → deliver
│   │   └── license_tracker.py    # Per-source license/attribution tracking
│   ├── harvesters/
│   │   ├── __init__.py
│   │   ├── opd.py                # Ojibwe People's Dictionary
│   │   ├── fpcc.py               # First Peoples' Cultural Council
│   │   ├── cirnac.py             # CIRNAC community data expansion
│   │   └── powwow.py             # Powwow/event feeds
│   └── config/
│       └── sources.yaml          # Source definitions (URL, type, license, schedule)
├── tests/
│   ├── test_envelope.py
│   ├── test_runner.py
│   ├── test_opd.py
│   ├── test_fpcc.py
│   ├── test_cirnac.py
│   ├── test_powwow.py
│   └── fixtures/                 # Sample API/scrape responses for offline testing
├── Taskfile.yml
├── pyproject.toml
├── .github/workflows/test.yml
├── CLAUDE.md
└── CHANGELOG.md
```

## 5. Core Abstractions

### 5.1 Harvester Protocol

```python
class Harvester(Protocol):
    name: str                              # e.g., "opd"
    source_type: str                       # "structured" or "api"

    def source_registration(self) -> dict  # NC source-manager fields
    def fetch(self) -> Iterator[dict]      # Yield raw records from source
    def transform(self, raw: dict) -> list[dict]  # Raw → envelope payloads
```

Each harvester implements three concerns:
- **Registration:** What to tell NC source-manager about this source
- **Fetching:** How to get raw data (HTTP API, JSONL file, RSS feed, HTML scrape)
- **Transformation:** How to map raw records to Waaseyaa envelope payloads

### 5.2 Runner Pipeline

What `harvest run opd` executes:

1. Load harvester by name from plugin registry
2. Register/update source in NC source-manager (`POST /api/v1/sources`)
3. Call `harvester.fetch()` — yields raw records
4. For each record, call `harvester.transform()` — returns envelope payloads
5. Wrap each payload in a Waaseyaa envelope via `EnvelopeBuilder`
6. Deliver batch to NC ingest endpoint (`POST /api/v1/ingest`)
7. Log results (accepted/rejected counts, errors, trace IDs)

### 5.3 Envelope Builder

Constructs envelopes matching **Minoo's actual ingestion contract** (defined in `PayloadValidator::REQUIRED_ENVELOPE_FIELDS`):

```json
{
    "payload_id": "opd-makwa-20260320",
    "version": "1.0",
    "source": "opd",
    "snapshot_type": "full",
    "timestamp": "2026-03-20T12:00:00+00:00",
    "entity_type": "dictionary_entry",
    "source_url": "https://ojibwe.lib.umn.edu/main-entry/makwa-na",
    "data": {
        "word": "makwa",
        "definition": "bear",
        "part_of_speech": "na",
        "language_code": "oj",
        "status": 1
    },
    "metadata": {
        "taxonomy_category": "language",
        "taxonomy_region": "canada:ontario:north-shore-huron",
        "dialect_code": "oji-east",
        "license_type": "cc-by",
        "attribution_text": "Ojibwe People's Dictionary, University of Minnesota"
    }
}
```

**Required envelope fields** (validated by `PayloadValidator`):

| Field | Type | Description |
|-------|------|-------------|
| `payload_id` | string | Unique ID for dedup (e.g., `opd-{word}-{date}`) |
| `version` | string | Envelope version, currently `"1.0"` |
| `source` | string | Harvester name (e.g., `"opd"`) |
| `snapshot_type` | string | `"full"` (complete record) or `"partial"` (delta update) |
| `timestamp` | string | ISO 8601 when data was captured |
| `entity_type` | string | Target Minoo entity type (e.g., `"dictionary_entry"`) |
| `source_url` | string | URL of the original record |
| `data` | object | Entity-specific fields — passed to the entity mapper |

**Optional fields** (not validated, passed through):

| Field | Type | Description |
|-------|------|-------------|
| `metadata` | object | Taxonomy tags, license/attribution data |

> **Note:** This is Minoo's ingestion envelope, not the Waaseyaa framework's canonical `Envelope` DTO. The Waaseyaa framework envelope (source, type, payload, timestamp, trace_id) is used for framework-level ingestion. Minoo's `PayloadValidator` enforces a content-pipeline-specific contract with `payload_id`, `version`, `snapshot_type`, `entity_type`, `source_url`, and `data`. The `EnvelopeBuilder` must produce Minoo's format.

### 5.4 Source Configuration

`config/sources.yaml` defines each source declaratively:

```yaml
sources:
  opd:
    name: Ojibwe People's Dictionary
    url: https://ojibwe.lib.umn.edu
    source_type: structured
    data_format: json
    update_frequency: weekly
    license_type: cc-by
    attribution_text: "Ojibwe People's Dictionary, University of Minnesota"
    taxonomy:
      category: language
      region: canada:ontario:north-shore-huron
      dialect_code: oji-east

  fpcc:
    name: First Peoples' Cultural Council
    url: https://fpcc.ca
    source_type: api
    data_format: json
    update_frequency: monthly
    license_type: open
    attribution_text: "First Peoples' Cultural Council"
    taxonomy:
      category: language
      region: canada:british-columbia

  cirnac:
    name: Crown-Indigenous Relations
    url: https://fnp-ppn.aadnc-aandc.gc.ca
    source_type: api
    data_format: json
    update_frequency: monthly
    license_type: open
    attribution_text: "Government of Canada, CIRNAC"
    taxonomy:
      category: community

  powwow:
    name: Powwow Event Feeds
    url: https://www.powwows.com
    source_type: structured
    data_format: rss
    update_frequency: daily
    license_type: restricted
    attribution_text: "Powwows.com"
    taxonomy:
      category: culture
```

## 6. Content Type Mapping

| Harvester | Source Data | `entity_type` value | Minoo Entity | Mapper | Pipeline Status |
|-----------|-----------|---------------------|--------------|--------|-----------------|
| `opd` | OPD JSONL/API | `dictionary_entry` | DictionaryEntry | `DictionaryEntryMapper` | Mapper + IngestImporter wired |
| `opd` | OPD JSONL/API | `example_sentence` | ExampleSentence | `ExampleSentenceMapper` | Mapper exists, **needs IngestImporter + Validator wiring** |
| `opd` | OPD JSONL/API | `speaker` | Speaker | `SpeakerMapper` | Mapper + IngestImporter wired |
| `opd` | OPD JSONL/API | `word_part` | WordPart | `WordPartMapper` | Mapper exists, **needs IngestImporter + Validator wiring** |
| `fpcc` | FPCC language map | `dictionary_entry` | DictionaryEntry | `DictionaryEntryMapper` | Wired |
| `cirnac` | CIRNAC API | `community` | Community | **New:** `CommunityMapper` | **Needs mapper + wiring** |
| `powwow` | RSS/scrape | `event` | Event | **New:** `EventMapper` | **Needs mapper + wiring** |
| (M1.4) news | NC crawler | `news_article` | NewsArticle (new) | **New:** `NewsArticleMapper` | **Needs entity + mapper + wiring** |

## 7. NC Changes

### 7.1 New Ingest Endpoint

`POST /api/v1/ingest` — accepts Waaseyaa envelopes from external producers.

**Request:** Array of envelopes or single envelope.
**Validation:** Checks envelope structure (source, type, payload, timestamp required).
**Processing:**
1. Validate envelope shape
2. Optionally enrich via classifier (add/verify taxonomy tags)
3. Publish to Redis channel based on envelope type: `indigenous:type:{envelope.type}`
4. Return accepted/rejected counts with trace IDs

**Authentication:** JWT required (same as other protected endpoints).

### 7.2 Ingest Endpoint Contract

**Request:**
```
POST /api/v1/ingest
Authorization: Bearer <JWT>
Content-Type: application/json

{
    "envelopes": [
        {
            "payload_id": "opd-makwa-20260320",
            "version": "1.0",
            "source": "opd",
            "snapshot_type": "full",
            "timestamp": "2026-03-20T12:00:00+00:00",
            "entity_type": "dictionary_entry",
            "source_url": "https://ojibwe.lib.umn.edu/main-entry/makwa-na",
            "data": { ... },
            "metadata": { ... }
        }
    ]
}
```

**Response (200 OK):**
```json
{
    "accepted": 47,
    "rejected": 3,
    "results": [
        { "payload_id": "opd-makwa-20260320", "status": "accepted", "trace_id": "uuid" },
        { "payload_id": "opd-bad-entry", "status": "rejected", "errors": ["entity_type 'unknown' not supported"] }
    ]
}
```

**Authentication:** Uses the same JWT secret as other protected NC endpoints (`AUTH_JWT_SECRET`). The harvester's `NCClient` acquires a token via the existing NC auth flow.

**Rate limiting:** 100 envelopes per request, 10 requests/minute per source. Configurable via NC config.

**Size limit:** 5MB per request body.

### 7.3 Redis Channel Convention

Envelopes published to channels by content type:
- `indigenous:type:minoo.dictionary_entry`
- `indigenous:type:minoo.community`
- `indigenous:type:minoo.event`
- `indigenous:type:minoo.news_article`

Minoo subscribes to `indigenous:type:minoo.*` pattern.

## 8. Minoo Changes

### 8.1 Redis Subscriber

New `RedisSubscriberServiceProvider` that:
- Subscribes to `indigenous:type:minoo.*` Redis channels
- Deserializes envelopes
- Routes to `IngestImporter` which dispatches to the appropriate entity mapper
- Runs as a queue worker: `php bin/waaseyaa queue:work redis`

### 8.2 New Entity Mappers + Pipeline Wiring

New mapper classes:
- `CommunityMapper` — maps CIRNAC envelope payloads to Community entities
- `EventMapper` — maps powwow/event payloads to Event entities

**Required pipeline changes** (without these, new entity types throw `LogicException`):

1. **`PayloadValidator::SUPPORTED_ENTITY_TYPES`** — add `community`, `event`, `example_sentence`, `word_part` (the last two have mapper classes but were never wired into the validator)
2. **`IngestImporter` match block** — add cases for `community`, `event`, `example_sentence`, `word_part` routing to their respective mappers
3. **`IngestImporter` use statements** — add imports for `ExampleSentenceMapper`, `WordPartMapper`, `CommunityMapper`, `EventMapper`

### 8.3 New Entity Type (M1.4)

`news_article` — ContentEntityBase with fields: title, body, source_url, source_name, published_at, category, region, image_url, author, status.

New: `NewsArticleMapper`, `NewsArticleServiceProvider`, `NewsArticleAccessPolicy`.

### 8.4 Attribution Display

Templates render attribution based on source metadata:
- Footer line: "Source: {attribution_text}" with link to source URL
- License badge where applicable

## 9. Data Policy Enforcement

Each harvester declares license_type and attribution_text in source config. The runner:
1. Validates license_type is one of: open, cc-by, cc-by-sa, restricted, unknown
2. Rejects harvest if license_type is unknown (requires manual review)
3. Embeds attribution in every envelope's metadata
4. NC stores attribution in source-manager for audit

**Three source tiers (from vision spec):**
1. Public institutional — CIRNAC, StatsCan (open license, no attribution required)
2. Indigenous organizations — FPCC, OPD (varies, attribution usually required)
3. Academic/archival — museums, language archives (restricted, explicit permission needed)

Community-contributed content is NOT part of the harvesting tools — that's Minoo's own contribution system.

## 10. Milestone Breakdown

### M1.1 — Harvester Scaffold
- New `indigenous-harvesters` repo with full structure
- Core library: Harvester protocol, EnvelopeBuilder, NCClient, NCPublisher, Runner
- CLI: `harvest run <name>` with `--dry-run` flag
- License tracker
- Test harness with fixtures
- CI workflow
- CLAUDE.md

### M1.2 — Language Harvesters
- OPD harvester (replaces Go importer): dictionary entries, example sentences, speakers, word parts
- FPCC harvester: language map data, dialect-tagged entries
- NC ingest endpoint (required for delivery)
- End-to-end validation: harvester → NC → Redis → Minoo entity storage

### M1.3 — Community & Event Harvesters
- CIRNAC harvester: expanded community data (enriches existing 637 seeded communities)
- Powwow/event harvester: RSS feeds + scraping
- New Minoo mappers: CommunityMapper, EventMapper
- Attribution display in templates

### M1.4 — News Path via NC
- Register Indigenous news sources in NC (APTN, Windspeaker, Nation Talk)
- NC classifier uses taxonomy categories from shared contract
- Minoo Redis subscriber for news channels
- New `news_article` entity type + mapper + service provider + access policy
- News display templates

## 11. Testing Strategy

### Harvester Tests (Python)
- **Unit:** Mock HTTP responses via `responses` library, assert envelope output shape
- **Fixture-based:** Store sample API responses in `tests/fixtures/`, test transform logic offline
- **Integration:** End-to-end with mock NC endpoint — register source, deliver envelopes, verify acceptance
- **CI:** pytest + mypy + ruff

### NC Tests (Go)
- Unit test for ingest endpoint handler
- Test envelope validation
- Test Redis publishing

### Minoo Tests (PHP)
- Unit tests for new entity mappers (CommunityMapper, EventMapper, NewsArticleMapper)
- Integration test: feed envelope through IngestImporter, verify entity created
- Existing mapper tests continue to pass

## 12. Dependencies

```
M1.1 (Scaffold)     → no dependencies, start immediately
M1.2 (Language)      → M1.1 + NC ingest endpoint
M1.3 (Community)     → M1.1 + new Minoo mappers
M1.4 (News)          → M1.1 + NC classifier taxonomy integration + new Minoo entity
```

M1.1 is the critical path. M1.2 and M1.3 can parallelize after M1.1 ships. M1.4 depends on NC classifier work.
