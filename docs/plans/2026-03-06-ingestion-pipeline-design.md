# Ingestion Pipeline Design — Issues #1, #2, #3

**Milestone:** v0.2 – First Entities + NorthCloud Ingestion
**Approach:** C — Pure PHP importer core with multiple frontends (CLI, queue, HTTP)

## Overview

End-to-end ingestion pipeline that receives NorthCloud snapshots, validates them, creates IngestLog entries for admin review, and materializes approved payloads into Minoo entities. Designed to work with test fixtures now and Redis pub/sub later.

## Architecture

```
                    ┌─ CLI command (fixtures/file)
                    │
Importer (pure PHP) ┼─ Queue handler (SyncQueue now, Redis later)
                    │
                    └─ HTTP endpoint (manual paste / webhook)
                    │
                    ↓
              IngestLog (pending_review)
                    │
              Admin reviews in dashboard
                    │
              ┌─────┼─────┐
              ↓     ↓     ↓
           Approve Reject  (Failed on error)
              │
              ↓
         Materializer
              │
         Creates entities
              │
         IngestLog(status=approved, entity_id=N)
```

### Core Classes

```
src/Ingest/
├── IngestImporter.php              # Envelope → IngestLog
├── IngestMaterializer.php          # IngestLog → entities (on approval)
├── MaterializationContext.php      # Shared state (resolved speakers, word parts)
├── MaterializationResult.php       # Preview/result of materialization
├── PayloadValidator.php            # Envelope + data shape validation
├── EntityMapper/
│   ├── DictionaryEntryMapper.php   # payload → dictionary_entry fields
│   ├── ExampleSentenceMapper.php   # payload → example_sentence fields
│   ├── WordPartMapper.php          # payload → word_part fields
│   ├── SpeakerMapper.php           # payload → speaker fields
│   └── CulturalCollectionMapper.php
└── Adapter/
    ├── IngestCliCommand.php        # bin/waaseyaa ingest:process
    ├── IngestMessageHandler.php    # Queue handler
    └── IngestHttpController.php    # POST /api/ingest (optional)
```

### Key Design Decisions

- **IngestImporter** is fast (validate + create IngestLog). **IngestMaterializer** is heavy (create entities). Separated so the hot path (receiving messages) stays fast.
- **Create on approval only** (Option Y). No orphaned entities. Rejected payloads never touch entity tables.
- **Dry-run mode** on materializer: `materialize($log, dryRun: true)` returns a `MaterializationResult` preview without persisting.
- **MaterializationContext** passes shared state (resolved speakers, word parts) across mapper calls to reduce DB round-trips.

## Payload Contract

### Envelope

```json
{
  "payload_id": "550e8400-e29b-41d4-a716-446655440000",
  "version": "1.0",
  "source": "ojibwe_lib",
  "snapshot_type": "full",
  "timestamp": "2026-03-06T14:30:00Z",
  "entity_type": "dictionary_entry",
  "source_url": "https://ojibwe.lib.umn.edu/main-entry/makwa-na",
  "data": { ... }
}
```

- `payload_id` — UUID for idempotency (dedup across re-emits)
- `version` — contract version for schema evolution
- `source` — machine name matching NorthCloud source config
- `snapshot_type` — `full`, `delta`, `delete` (only `full` for v0.2)
- `entity_type` — routing key: `dictionary_entry`, `speaker`, `cultural_collection`
- `source_url` — canonical URL of crawled page

### Data: dictionary_entry

```json
{
  "lemma": "makwa",
  "definition": "bear",
  "part_of_speech": "na",
  "stem": "/makw-/",
  "language_code": "oj",
  "inflected_forms": [
    {"form": "makwag", "label": "plural"},
    {"form": "makwan", "label": "obviative"}
  ],
  "tags": ["animate", "animal"],
  "example_sentences": [
    {
      "source_sentence_id": "makwa-es-001",
      "ojibwe_text": "Makwa agamiing dago.",
      "english_text": "The bear is by the lake.",
      "speaker_code": "es",
      "audio_url": "https://ojibwe.lib.umn.edu/audio/makwa-es.mp3"
    }
  ],
  "word_parts": [
    {"form": "makw-", "morphological_role": "initial", "definition": "bear"}
  ]
}
```

### Data: speaker

```json
{
  "name": "Eugene Stillday",
  "code": "es",
  "bio": "Ponemah, Minnesota. First-language speaker.",
  "photo_url": null,
  "region": "Ponemah, MN"
}
```

### Data: cultural_collection

```json
{
  "title": "The Bear Clan",
  "description": "Cultural significance of makwa in Anishinaabe tradition...",
  "source_attribution": "University of Minnesota Ojibwe Language Program",
  "topics": ["clans", "animals"]
}
```

## Importer Flow

### Phase 1: Envelope → IngestLog (IngestImporter)

1. **Validate** envelope: required fields, version check, entity_type in allowed list
2. **Dedup** by `payload_id`: query ingest_log, return existing if found
3. **Map** `data` to target entity fields (produces `payload_parsed`)
4. **Create** IngestLog:
   - `status` = `pending_review` (or `failed` if validation fails)
   - `payload_raw` = full envelope JSON
   - `payload_parsed` = mapped fields JSON (what the reviewer sees)
   - `source`, `entity_type_target`, `title` = auto-generated

### Phase 2: IngestLog → Entities (IngestMaterializer, on approval)

1. **Load** IngestLog and decode `payload_parsed`
2. **Build** MaterializationContext (cache of resolved speakers, word parts)
3. **Create entities** in dependency order:
   - speakers (get-or-create by `code`)
   - word_parts (get-or-create by `form` + `type`)
   - parent entity (dictionary_entry, cultural_collection)
   - example_sentences (linking to parent + speaker)
4. **Update** IngestLog: `status=approved`, `entity_id`, `reviewed_by`, `reviewed_at`
5. **On error**: `status=failed`, `error_message`

### Dry-Run Mode

`materialize($log, dryRun: true)` returns `MaterializationResult` containing:
- List of entities that would be created (type + fields)
- List of entities that would be updated (type + ID + changed fields)
- List of speakers/word_parts that would be get-or-created
- No database writes

## Entity Mapping Rules

### dictionary_entry

| Payload field | Entity field | Transform |
|---|---|---|
| `data.lemma` | `word` | Direct string |
| `data.definition` | `definition` | Join with `"; "` if array |
| `data.part_of_speech` | `part_of_speech` | Validate: `na, ni, nad, nid, vai, vti, vta, vii, pc, adv, pron, num` |
| `data.stem` | `stem` | Direct string, nullable |
| `data.inflected_forms` | `inflected_forms` | `json_encode()` |
| `data.language_code` | `language_code` | Default `oj` if missing |
| `envelope.source_url` | `source_url` | Direct URI |
| *(auto)* | `slug` | Framework slug service or `strtolower(preg_replace)` |
| *(auto)* | `status` | `0` (unpublished) |
| *(auto)* | `created_at` | `time()` |

**Dedup:** `word` + `language_code`. Update if exists, but do not overwrite manually edited fields (only update importer-owned fields: definition, POS, stem, inflected_forms, source_url).

**Tags:** Skipped for v0.2 (no `tags` field on dictionary_entry entity).

### example_sentence

| Payload field | Entity field | Transform |
|---|---|---|
| `ojibwe_text` | `ojibwe_text` | Direct string |
| `english_text` | `english_text` | Direct string |
| `speaker_code` | `speaker_id` | Resolve by `code` → entity ID (lazy create) |
| `audio_url` | `audio_url` | Direct URI, nullable |
| `source_sentence_id` | `source_sentence_id` | Direct string (requires field addition) |
| *(parent)* | `dictionary_entry_id` | Parent entity ID |
| *(inherit)* | `language_code` | From parent dictionary_entry |
| *(auto)* | `status` | `0` (unpublished) |

**Dedup:** `source_sentence_id`. Update if exists; create if absent or no ID.

**Field addition needed:** Add `source_sentence_id` (type: string) to example_sentence entity type.

### word_part

| Payload field | Entity field | Transform |
|---|---|---|
| `form` | `form` | Direct string |
| `morphological_role` | `type` | Validate: `initial`, `medial`, `final` |
| `definition` | `definition` | Direct string |
| `envelope.source_url` | `source_url` | Direct URI |
| *(auto)* | `slug` | From `form` |
| *(auto)* | `status` | `0` (unpublished) |

**Dedup:** `form` + `type`. Get-or-create. Shared across entries (not FK-attached).

### speaker

| Payload field | Entity field | Transform |
|---|---|---|
| `data.name` | `name` | Direct string |
| `data.code` | `code` | Direct string, unique natural key |
| `data.bio` | `bio` | Direct string, nullable |
| *(auto)* | `slug` | From `name` |
| *(auto)* | `status` | `1` (published — reference data) |

**Dedup:** `code`. Get-or-create. Update name/bio if changed.

**Lazy creation** (from sentence speaker_code): `{name: code, code: code, bio: null, status: 1}`. Full speaker payload updates later.

**Skipped for v0.2:** `photo_url` → media download, `region` → no field on entity.

### cultural_collection

| Payload field | Entity field | Transform |
|---|---|---|
| `data.title` | `title` | Direct string |
| `data.description` | `description` | Strip/normalize HTML |
| `data.source_attribution` | `source_attribution` | Direct string, nullable |
| `envelope.source_url` | `source_url` | Direct URI |
| *(auto)* | `slug` | From `title` |
| *(auto)* | `status` | `0` (unpublished) |

**Dedup:** `source_url`. Get-or-create.

**Skipped for v0.2:** `topics` → taxonomy mapping.

## Deduplication Strategy

| Entity | Natural key | Behavior |
|---|---|---|
| IngestLog | `payload_id` | Reject duplicate envelope |
| dictionary_entry | `word` + `language_code` | Update importer-owned fields only |
| example_sentence | `source_sentence_id` | Update if exists |
| word_part | `form` + `type` | Get-or-create (shared) |
| speaker | `code` | Get-or-create, update name/bio |
| cultural_collection | `source_url` | Get-or-create |

## v0.2 Scope

### In scope
- IngestImporter + PayloadValidator
- IngestMaterializer + dry-run mode + MaterializationContext
- All 5 entity mappers (dictionary_entry, example_sentence, word_part, speaker, cultural_collection)
- CLI adapter (process file/stdin)
- Queue adapter (IngestMessageHandler with SyncQueue)
- Test fixtures (realistic ojibwe.lib payloads)
- Field addition: `source_sentence_id` on example_sentence

### Out of scope (future)
- Redis transport (add when predis/phpredis is integrated)
- HTTP adapter (add when useful for debugging)
- Speaker photo download → media pipeline
- `region` field on speaker entity
- `tags` field on dictionary_entry entity
- `topics` → taxonomy mapping on cultural_collection
- `delta`/`delete` snapshot types
- Admin approval UI (approve/reject buttons) — uses existing edit form for now

## Testing

### Unit tests
- PayloadValidator: valid/invalid envelopes, missing fields, bad version
- Each EntityMapper: field transforms, defaults, validation
- IngestImporter: creates IngestLog, dedup by payload_id, handles failures
- IngestMaterializer: entity creation order, speaker resolution, dedup, dry-run mode

### Integration tests
- Full pipeline: fixture envelope → IngestLog → approve → verify entities created
- Dedup: same payload_id twice → single IngestLog
- Speaker lazy creation: sentence with unknown speaker_code → speaker created

### Fixtures
- `tests/fixtures/ojibwe_lib/dictionary_entry_makwa.json` — full dictionary entry with sentences and word parts
- `tests/fixtures/ojibwe_lib/speaker_es.json` — speaker profile
- `tests/fixtures/ojibwe_lib/cultural_collection_bear_clan.json` — cultural content
- `tests/fixtures/ojibwe_lib/invalid_envelope.json` — missing required fields

## Redis Integration (future)

When Redis transport is added:
1. Add `predis/predis` to composer.json
2. Add `redis` section to `config/waaseyaa.php`
3. Create `RedisSubscriber` adapter that listens to `snapshot.*` channels
4. Feed envelopes to existing `IngestImporter`
5. No changes to importer, materializer, or mappers
