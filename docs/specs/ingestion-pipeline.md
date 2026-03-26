# Ingestion Pipeline Specification

## File Map

| File | Purpose |
|------|---------|
| `src/Ingestion/PayloadValidator.php` | Validates NorthCloud envelope structure and entity-specific data |
| `src/Ingestion/ValidationResult.php` | Immutable result: `errors` list + `isValid()` |
| `src/Ingestion/IngestImporter.php` | Validates envelope, maps to entity fields, returns `IngestLog` |
| `src/Ingestion/IngestMaterializer.php` | Creates real entities from approved `IngestLog` records |
| `src/Ingestion/IngestStatus.php` | Enum: `PendingReview`, `Approved`, `Failed` |
| `src/Ingestion/MaterializationContext.php` | Tracks speaker/word-part IDs during materialization (dedup) |
| `src/Ingestion/MaterializationResult.php` | Collects created/skipped/updated entities from materialization |
| `src/Ingestion/ValueObject/DictionaryEntryFields.php` | Readonly VO for dictionary entry entity fields |
| `src/Ingestion/ValueObject/SpeakerFields.php` | Readonly VO for speaker entity fields |
| `src/Ingestion/ValueObject/WordPartFields.php` | Readonly VO for word part entity fields |
| `src/Ingestion/ValueObject/ExampleSentenceFields.php` | Readonly VO for example sentence entity fields |
| `src/Ingestion/ValueObject/CulturalCollectionFields.php` | Readonly VO for cultural collection entity fields |
| `src/Ingestion/EntityMapper/DictionaryEntryMapper.php` | Maps NorthCloud dictionary payload to `DictionaryEntryFields` |
| `src/Ingestion/EntityMapper/SpeakerMapper.php` | Maps speaker payload to `SpeakerFields` + `fromCode()` factory |
| `src/Ingestion/EntityMapper/WordPartMapper.php` | Maps word part payload to `WordPartFields` (null if invalid role) |
| `src/Ingestion/EntityMapper/ExampleSentenceMapper.php` | Maps sentence payload to `ExampleSentenceFields` |
| `src/Ingestion/EntityMapper/CulturalCollectionMapper.php` | Maps cultural collection payload to `CulturalCollectionFields` |
| `src/Provider/IngestServiceProvider.php` | Registers `ingest_log` entity type |
| `src/Ingestion/NcContentSyncService.php` | Pulls NC Search API content and creates `teaching`/`event` entities |
| `src/Ingestion/NcSyncWorkerLoop.php` | Runs recurring sync and writes `storage/nc-sync-status.json` |
| `scripts/nc-sync-worker.php` | Worker entrypoint for systemd-managed NC sync |
| `src/Entity/IngestLog.php` | IngestLog entity — stores raw + parsed payloads |

## Supported Entity Types

The pipeline currently handles three entity types from NorthCloud:
- `dictionary_entry` — Ojibwe dictionary words with example sentences, speakers, and word parts
- `speaker` — Language speaker profiles
- `cultural_collection` — Cultural artifact collections from ojibwe.lib.umn.edu

## Dual North Cloud Paths

Minoo currently has two North Cloud ingestion paths:

1. **Envelope pipeline** (this spec's primary flow): envelope payloads are validated and stored as `ingest_log`, then reviewed/materialized into language/cultural entities.
2. **NC Search sync pipeline**: `NcContentSyncService` fetches North Cloud Search API content and creates `teaching`/`event` entities directly.

The NC Search worker writes status to `storage/nc-sync-status.json` with keys:
`last_sync`, `created`, `skipped`, `failed`, `fetch_failed`, `cycles`.

## Data Flow

### Phase 1: Import (Validation + Mapping)

```
NorthCloud Envelope → PayloadValidator::validate() → ValidationResult
                    → EntityMapper::map()           → ValueObject (VO)
                    → IngestImporter::import()       → IngestLog (status: pending_review | failed)
```

1. `IngestImporter::import(array $envelope): IngestLog`
2. Validates envelope via `PayloadValidator::validate()` — returns `ValidationResult`
3. If invalid: creates `IngestLog` with `IngestStatus::Failed` and error message
4. If valid: selects mapper by `entity_type`, maps payload to value object
5. Creates `IngestLog` with raw envelope JSON + parsed fields JSON, status `PendingReview`

### Phase 2: Materialization (Entity Creation)

```
IngestLog (approved) → IngestMaterializer::materialize() → MaterializationResult
                     → Resolves dependencies (speakers, word parts)
                     → Creates entities via EntityTypeManager
```

1. `IngestMaterializer::materialize(IngestLog $log, bool $dryRun = false): MaterializationResult`
2. Decodes `payload_raw` and `payload_parsed` from the log
3. Dispatches by entity type to type-specific method
4. For `dictionary_entry`:
   - Resolves speakers from example sentences (get-or-create by code)
   - Resolves word parts (get-or-create by form+type)
   - Creates dictionary entry
   - Creates example sentences with resolved entry ID and speaker IDs
5. For `speaker`: get-or-create by code
6. For `cultural_collection`: create directly
7. Uses `MaterializationContext` for deduplication within a single run
8. `dryRun` mode: records what would be created without persisting

## Envelope Format (v1.0)

```json
{
  "payload_id": "uuid",
  "version": "1.0",
  "source": "ojibwe.lib.umn.edu",
  "snapshot_type": "full",
  "timestamp": "2026-01-15T10:00:00Z",
  "entity_type": "dictionary_entry",
  "source_url": "https://ojibwe.lib.umn.edu/main-entry/jiimaan-ni",
  "data": { ... }
}
```

Required envelope fields: `payload_id`, `version`, `source`, `snapshot_type`, `timestamp`, `entity_type`, `source_url`, `data`

## Validation Rules

### Envelope-level
- All 8 required fields must be present and non-empty
- `version` must be in `['1.0']`
- `entity_type` must be in `['dictionary_entry', 'speaker', 'cultural_collection']`
- `data` must be an array

### Entity-specific
- `dictionary_entry`: requires `lemma`; `part_of_speech` if present must be valid code
- `speaker`: requires `name` and `code`
- `cultural_collection`: requires `title`

### Valid parts of speech
`na`, `ni`, `nad`, `nid`, `vai`, `vti`, `vta`, `vii`, `pc`, `adv`, `pron`, `num`

## Value Object Signatures

All VOs are `final readonly class` with `toArray(): array<string, mixed>`.

| VO | Constructor Fields |
|----|--------------------|
| `DictionaryEntryFields` | `word`, `definition`, `partOfSpeech`, `stem`, `languageCode`, `inflectedForms`, `sourceUrl`, `slug`, `status` (0), `createdAt`, `updatedAt` |
| `SpeakerFields` | `name`, `code`, `?bio`, `slug`, `status` (1), `createdAt`, `updatedAt` |
| `WordPartFields` | `form`, `type`, `definition`, `sourceUrl`, `slug`, `status` (0), `createdAt`, `updatedAt` |
| `ExampleSentenceFields` | `ojibweText`, `englishText`, `dictionaryEntryId`, `?speakerId`, `languageCode`, `audioUrl`, `sourceSentenceId`, `status` (0), `createdAt`, `updatedAt` |
| `CulturalCollectionFields` | `title`, `description`, `?sourceAttribution`, `sourceUrl`, `slug`, `status` (0), `createdAt`, `updatedAt` |

Note: Dictionary entries and word parts are created with `status: 0` (unpublished). Speakers are created with `status: 1` (published).

## Entity Mapper Signatures

| Mapper | Method | Returns |
|--------|--------|---------|
| `DictionaryEntryMapper` | `map(array $data, string $sourceUrl): DictionaryEntryFields` | Always returns VO |
| `SpeakerMapper` | `map(array $data): SpeakerFields` | Always returns VO |
| `SpeakerMapper` | `static fromCode(string $code): SpeakerFields` | Minimal VO from code only |
| `WordPartMapper` | `map(array $data, string $sourceUrl): ?WordPartFields` | Null if `morphological_role` not in `[initial, medial, final]` |
| `ExampleSentenceMapper` | `map(array $data, int $dictionaryEntryId, ?int $speakerId, string $languageCode): ExampleSentenceFields` | Always returns VO |
| `CulturalCollectionMapper` | `map(array $data, string $sourceUrl): CulturalCollectionFields` | Strips HTML from description |

## Materialization Context

Deduplication registry for a single materialization run:

```php
MaterializationContext
  getSpeakerId(string $code): ?int
  setSpeakerId(string $code, int $id): void
  getWordPartId(string $form, string $type): ?int
  setWordPartId(string $form, string $type, int $id): void
```

Internal keys: speakers by `$code`, word parts by `"$form|$type"`.

## Materialization Result

```php
MaterializationResult
  addCreated(string $type, array $fields, ?int $id = null): void
  addSkipped(string $type, string $key, string $reason): void
  addUpdated(string $type, int $id, array $fields): void
  setPrimaryEntityId(int $id): void
  getPrimaryEntityId(): ?int
  getCreated(): list<array{type, fields, id?}>
  getSkipped(): list<array{type, key, reason}>
  getUpdated(): list<array{type, id, fields}>
```

## Edge Cases

- `DictionaryEntryMapper` handles `definition` as string or array (joins with `; `)
- `CulturalCollectionMapper` strips HTML from description (inserts spaces before closing block tags, then `strip_tags`)
- `WordPartMapper` returns null for invalid `morphological_role` — materializer records these as skipped
- `getOrCreateSpeaker`/`getOrCreateWordPart` catch `PDOException` for "no such column" (handles early schema where columns don't exist yet)
- `IngestStatus` is a string-backed enum — stored as string in `IngestLog.status`
- JSON decode failures in `materialize()` throw `RuntimeException`

## Testing Patterns

- Unit tests mock `EntityTypeManagerInterface` for materializer tests
- `PayloadValidator` tests use static envelope arrays — test each validation path
- Entity mapper tests assert VO field values from known input data
- `SlugGenerator` tests verify edge cases: Unicode, special chars, whitespace
- Dry-run mode validates materialization logic without persistence
