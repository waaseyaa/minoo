---
name: minoo:ingestion
description: Use when working on Minoo ingestion pipeline, mappers, materializer, or ingest logs in src/Ingestion/ or tests relating to ingestion
---

# Minoo Ingestion Pipeline Specialist

## Scope

Files: `src/Ingestion/`, `src/Provider/IngestServiceProvider.php`
Tests: `tests/Minoo/Unit/Ingest/`, `tests/Minoo/Integration/IngestPipelineTest.php`
Fixtures: `tests/fixtures/ojibwe_lib/`

## Pipeline Flow

```
NorthCloud Envelope (JSON)
  → PayloadValidator.validate()      → ValidationResult (errors[])
  → EntityMapper.map()               → Value Object (typed fields)
  → IngestImporter.import()          → IngestLog (status: pending_review)
  --- human approval ---
  → IngestMaterializer.materialize() → MaterializationResult (created[], skipped[])
  → Entities persisted via EntityTypeManager
```

## Interface Signatures

**PayloadValidator**
```php
public function validate(array $envelope): ValidationResult;
// ValidationResult::isValid(): bool, ::getErrors(): string[]
```

**IngestImporter**
```php
public function import(array $envelope): IngestLog;
// Always returns IngestLog — failed validation → status='failed', error_message set
// Valid payload → status='pending_review', payload_raw + payload_parsed stored as JSON
```

**IngestMaterializer**
```php
public function materialize(IngestLog $log, bool $dryRun = false): MaterializationResult;
// dryRun=true: previews without persisting (primaryEntityId = null)
// Throws RuntimeException on JSON decode failure
```

**MaterializationResult**
```php
addCreated(string $type, array $fields, ?int $id = null): void;
addSkipped(string $type, string $key, string $reason): void;
addUpdated(string $type, int $id, array $fields): void;
setPrimaryEntityId(int $id): void;
getPrimaryEntityId(): ?int;
getCreated(): array; getSkipped(): array; getUpdated(): array;
```

**MaterializationContext** — deduplication within a single run:
```php
getSpeakerId(string $code): ?int;    setSpeakerId(string $code, int $id): void;
getWordPartId(string $form, string $type): ?int;  setWordPartId(string $form, string $type, int $id): void;
```

## Mapper Classes

All mappers return typed value objects with `toArray(): array` (snake_case keys).

**DictionaryEntryMapper** — `map(array $data, string $sourceUrl): DictionaryEntryFields`
- `lemma` → `word`, `definition` → `definition` (array joined with `"; "`), `part_of_speech` → `partOfSpeech`
- `stem` → `stem`, `language_code` → `languageCode` (default `'oj'`), `inflected_forms` → JSON encoded
- Auto-generates `slug` via SlugGenerator, sets `status: 0`, timestamps to `time()`

**SpeakerMapper** — `map(array $data): SpeakerFields`
- `name` → `name`, `code` → `code`, `bio` → `bio` (nullable)
- Sets `status: 1` (published), generates slug from name
- Static `fromCode(string $code): SpeakerFields` — minimal speaker for implicit creation

**WordPartMapper** — `map(array $data, string $sourceUrl): ?WordPartFields`
- Returns `null` if `morphological_role` not in `['initial', 'medial', 'final']`
- `form` → `form`, `morphological_role` → `type`, `definition` → `definition`

**ExampleSentenceMapper** — `map(array $data, int $dictionaryEntryId, ?int $speakerId, string $languageCode): ExampleSentenceFields`
- `ojibwe_text`, `english_text`, `audio_url`, `source_sentence_id` mapped directly
- Receives resolved `dictionaryEntryId` and `speakerId` as parameters

**CulturalCollectionMapper** — `map(array $data, string $sourceUrl): CulturalCollectionFields`
- Strips HTML from description: add space before closing block tags → `strip_tags()` → normalize whitespace
- `title`, `description`, `source_attribution` (nullable), `sourceUrl`

## Materialization Logic

`IngestMaterializer::materialize()` dispatches by `entity_type_target`:

**Dictionary entry materialization:**
1. Resolve speakers from nested `example_sentences` via `getOrCreateSpeaker()` — dedup by code
2. Resolve word parts from nested `word_parts` via `getOrCreateWordPart()` — dedup by `"$form|$type"`
3. Create dictionary entry entity
4. Create example sentences with resolved speaker IDs and dictionary entry ID

**Get-or-create pattern:**
```php
$existingId = $context->getSpeakerId($code);
if ($existingId !== null) return $existingId;
// Query storage for existing by code
// If not found: create new entity, save, cache in context
$context->setSpeakerId($code, $id);
```

## IngestLog Entity

- Type: `ingest_log`, keys: `['id' => 'ilid', 'uuid' => 'uuid', 'label' => 'title']`
- Status flow: `pending_review` → `approved` → (materialized) | `rejected` | `failed`
- Key fields: `source`, `entity_type_target`, `entity_id`, `payload_raw`, `payload_parsed`, `error_message`, `reviewed_by`, `reviewed_at`

## Testing Patterns

**Unit tests** — mapper output verification:
```php
$mapper = new DictionaryEntryMapper(new SlugGenerator());
$fields = $mapper->map($data, 'https://source.url');
$this->assertSame('expected_word', $fields->word);
```

**Materializer dry-run** — mock EntityTypeManager, verify `never()` calls:
```php
$storage->expects($this->never())->method('create');
$result = $materializer->materialize($log, dryRun: true);
$this->assertNull($result->getPrimaryEntityId());
```

**Integration test** — full pipeline with in-memory SQLite:
```php
putenv('WAASEYAA_DB=:memory:');
// Import from JSON fixture → materialize → load entity → assert fields
```

## Common Mistakes

- **Forgetting `dryRun` parameter**: Always test both paths — dry run returns null primaryEntityId
- **JSON symmetry**: `payload_raw` and `payload_parsed` both use `JSON_THROW_ON_ERROR` for encode/decode
- **Null WordPartMapper return**: Invalid `morphological_role` returns null — callers must handle
- **Speaker status**: Speakers default to `status: 1` (published), all other types default to `status: 0`
- **HTML in descriptions**: CulturalCollectionMapper strips HTML — don't strip twice
- **MaterializationContext scope**: Context is per-materialize-call, not shared across calls

## Related Specs

- `docs/specs/ingestion-pipeline.md` — full pipeline architecture, envelope format, field mappings
- `docs/specs/entity-model.md` — entity type definitions, field definitions
- Framework: `waaseyaa_get_spec entity-system` — EntityBase, storage, query builder
