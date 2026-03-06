# Ingestion Pipeline Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a pure PHP ingestion pipeline that validates NorthCloud payloads, creates IngestLog entries, and materializes approved payloads into Minoo entities.

**Architecture:** Approach C — standalone IngestImporter + IngestMaterializer core with thin CLI and queue adapters. Entity mappers handle per-type field transforms. MaterializationContext tracks shared state (resolved speakers, word parts) across entity creation.

**Tech Stack:** PHP 8.3+, Waaseyaa entity system, PHPUnit 10.5, Symfony Console

**Design doc:** `docs/plans/2026-03-06-ingestion-pipeline-design.md`

---

### Task 1: Add source_sentence_id field to example_sentence

**Files:**
- Modify: `src/Provider/LanguageServiceProvider.php`
- Modify: `tests/Minoo/Unit/Entity/ExampleSentenceTest.php`

**Step 1: Add field definition**

In `LanguageServiceProvider.php`, add to the `example_sentence` fieldDefinitions array, after `audio_url`:

```php
'source_sentence_id' => ['type' => 'string', 'label' => 'Source Sentence ID', 'description' => 'Unique ID from source for dedup across re-crawls.', 'weight' => 22],
```

**Step 2: Add test for the new field**

In `ExampleSentenceTest.php`, add a test:

```php
#[Test]
public function it_supports_source_sentence_id(): void
{
    $sentence = new ExampleSentence([
        'ojibwe_text' => 'Makwa agamiing dago.',
        'english_text' => 'The bear is by the lake.',
        'dictionary_entry_id' => 1,
        'source_sentence_id' => 'makwa-es-001',
    ]);

    $this->assertSame('makwa-es-001', $sentence->get('source_sentence_id'));
}
```

**Step 3: Run tests**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/ExampleSentenceTest.php`
Expected: All pass

**Step 4: Run full suite**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit`
Expected: All pass

**Step 5: Commit**

```bash
git add src/Provider/LanguageServiceProvider.php tests/Minoo/Unit/Entity/ExampleSentenceTest.php
git commit -m "feat: add source_sentence_id field to example_sentence for dedup"
```

---

### Task 2: PayloadValidator

**Files:**
- Create: `src/Ingest/PayloadValidator.php`
- Test: `tests/Minoo/Unit/Ingest/PayloadValidatorTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest;

use Minoo\Ingest\PayloadValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayloadValidator::class)]
final class PayloadValidatorTest extends TestCase
{
    private PayloadValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PayloadValidator();
    }

    #[Test]
    public function valid_envelope_passes(): void
    {
        $envelope = [
            'payload_id' => '550e8400-e29b-41d4-a716-446655440000',
            'version' => '1.0',
            'source' => 'ojibwe_lib',
            'snapshot_type' => 'full',
            'timestamp' => '2026-03-06T14:30:00Z',
            'entity_type' => 'dictionary_entry',
            'source_url' => 'https://ojibwe.lib.umn.edu/main-entry/makwa-na',
            'data' => ['lemma' => 'makwa', 'definition' => 'bear', 'part_of_speech' => 'na'],
        ];

        $result = $this->validator->validate($envelope);

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->errors);
    }

    #[Test]
    public function missing_required_fields_fails(): void
    {
        $envelope = ['version' => '1.0'];

        $result = $this->validator->validate($envelope);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->errors);
    }

    #[Test]
    public function unsupported_version_fails(): void
    {
        $envelope = [
            'payload_id' => 'test-uuid',
            'version' => '99.0',
            'source' => 'test',
            'snapshot_type' => 'full',
            'timestamp' => '2026-03-06T14:30:00Z',
            'entity_type' => 'dictionary_entry',
            'source_url' => 'https://example.com',
            'data' => ['lemma' => 'test'],
        ];

        $result = $this->validator->validate($envelope);

        $this->assertFalse($result->isValid());
    }

    #[Test]
    public function unknown_entity_type_fails(): void
    {
        $envelope = [
            'payload_id' => 'test-uuid',
            'version' => '1.0',
            'source' => 'test',
            'snapshot_type' => 'full',
            'timestamp' => '2026-03-06T14:30:00Z',
            'entity_type' => 'nonexistent_type',
            'source_url' => 'https://example.com',
            'data' => [],
        ];

        $result = $this->validator->validate($envelope);

        $this->assertFalse($result->isValid());
    }

    #[Test]
    public function invalid_part_of_speech_fails(): void
    {
        $envelope = [
            'payload_id' => 'test-uuid',
            'version' => '1.0',
            'source' => 'test',
            'snapshot_type' => 'full',
            'timestamp' => '2026-03-06T14:30:00Z',
            'entity_type' => 'dictionary_entry',
            'source_url' => 'https://example.com',
            'data' => ['lemma' => 'test', 'definition' => 'test', 'part_of_speech' => 'INVALID'],
        ];

        $result = $this->validator->validate($envelope);

        $this->assertFalse($result->isValid());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Ingest/PayloadValidatorTest.php`
Expected: FAIL — class not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Ingest;

final class ValidationResult
{
    /** @param list<string> $errors */
    public function __construct(
        public readonly array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}

final class PayloadValidator
{
    private const array SUPPORTED_VERSIONS = ['1.0'];

    private const array SUPPORTED_ENTITY_TYPES = [
        'dictionary_entry',
        'speaker',
        'cultural_collection',
    ];

    private const array REQUIRED_ENVELOPE_FIELDS = [
        'payload_id',
        'version',
        'source',
        'snapshot_type',
        'timestamp',
        'entity_type',
        'source_url',
        'data',
    ];

    private const array VALID_PARTS_OF_SPEECH = [
        'na', 'ni', 'nad', 'nid', 'vai', 'vti', 'vta', 'vii',
        'pc', 'adv', 'pron', 'num',
    ];

    public function validate(array $envelope): ValidationResult
    {
        $errors = [];

        // Required envelope fields.
        foreach (self::REQUIRED_ENVELOPE_FIELDS as $field) {
            if (!array_key_exists($field, $envelope) || $envelope[$field] === '' || $envelope[$field] === null) {
                $errors[] = sprintf('Missing required field: %s', $field);
            }
        }

        if ($errors !== []) {
            return new ValidationResult($errors);
        }

        // Version check.
        if (!in_array($envelope['version'], self::SUPPORTED_VERSIONS, true)) {
            $errors[] = sprintf('Unsupported version: %s', $envelope['version']);
        }

        // Entity type check.
        if (!in_array($envelope['entity_type'], self::SUPPORTED_ENTITY_TYPES, true)) {
            $errors[] = sprintf('Unsupported entity type: %s', $envelope['entity_type']);
        }

        // Data must be an array.
        if (!is_array($envelope['data'])) {
            $errors[] = 'Data field must be an array.';
            return new ValidationResult($errors);
        }

        // Entity-type-specific validation.
        if ($envelope['entity_type'] === 'dictionary_entry') {
            $errors = [...$errors, ...$this->validateDictionaryEntry($envelope['data'])];
        }

        return new ValidationResult($errors);
    }

    /** @return list<string> */
    private function validateDictionaryEntry(array $data): array
    {
        $errors = [];

        if (empty($data['lemma'])) {
            $errors[] = 'Dictionary entry requires lemma.';
        }

        if (isset($data['part_of_speech']) && !in_array($data['part_of_speech'], self::VALID_PARTS_OF_SPEECH, true)) {
            $errors[] = sprintf('Invalid part_of_speech: %s', $data['part_of_speech']);
        }

        return $errors;
    }
}
```

Note: `ValidationResult` and `PayloadValidator` are in the same file for now. If the file grows, split `ValidationResult` to its own file.

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Ingest/PayloadValidatorTest.php`
Expected: OK (5 tests)

**Step 5: Commit**

```bash
git add src/Ingest/PayloadValidator.php tests/Minoo/Unit/Ingest/PayloadValidatorTest.php
git commit -m "feat: add PayloadValidator for ingestion envelope validation"
```

---

### Task 3: DictionaryEntryMapper

**Files:**
- Create: `src/Ingest/EntityMapper/DictionaryEntryMapper.php`
- Test: `tests/Minoo/Unit/Ingest/EntityMapper/DictionaryEntryMapperTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest\EntityMapper;

use Minoo\Ingest\EntityMapper\DictionaryEntryMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DictionaryEntryMapper::class)]
final class DictionaryEntryMapperTest extends TestCase
{
    private DictionaryEntryMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new DictionaryEntryMapper();
    }

    #[Test]
    public function it_maps_full_payload(): void
    {
        $data = [
            'lemma' => 'makwa',
            'definition' => 'bear',
            'part_of_speech' => 'na',
            'stem' => '/makw-/',
            'language_code' => 'oj',
            'inflected_forms' => [
                ['form' => 'makwag', 'label' => 'plural'],
            ],
        ];

        $result = $this->mapper->map($data, 'https://ojibwe.lib.umn.edu/main-entry/makwa-na');

        $this->assertSame('makwa', $result['word']);
        $this->assertSame('bear', $result['definition']);
        $this->assertSame('na', $result['part_of_speech']);
        $this->assertSame('/makw-/', $result['stem']);
        $this->assertSame('oj', $result['language_code']);
        $this->assertSame('makwa', $result['slug']);
        $this->assertSame(0, $result['status']);
        $this->assertSame('https://ojibwe.lib.umn.edu/main-entry/makwa-na', $result['source_url']);
        $this->assertStringContainsString('makwag', $result['inflected_forms']);
    }

    #[Test]
    public function it_defaults_language_code(): void
    {
        $data = ['lemma' => 'makwa', 'definition' => 'bear'];

        $result = $this->mapper->map($data, '');

        $this->assertSame('oj', $result['language_code']);
    }

    #[Test]
    public function it_joins_array_definition(): void
    {
        $data = ['lemma' => 'test', 'definition' => ['bear', 'a bear']];

        $result = $this->mapper->map($data, '');

        $this->assertSame('bear; a bear', $result['definition']);
    }

    #[Test]
    public function it_generates_slug_from_lemma(): void
    {
        $data = ['lemma' => 'Makwa (bear)'];

        $result = $this->mapper->map($data, '');

        $this->assertSame('makwa-bear', $result['slug']);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Ingest/EntityMapper/DictionaryEntryMapperTest.php`
Expected: FAIL — class not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Ingest\EntityMapper;

final class DictionaryEntryMapper
{
    /**
     * Map a NorthCloud dictionary entry payload to Minoo entity fields.
     *
     * @param array<string, mixed> $data Payload data block
     * @param string $sourceUrl Envelope source_url
     * @return array<string, mixed> Mapped entity fields
     */
    public function map(array $data, string $sourceUrl): array
    {
        $lemma = (string) ($data['lemma'] ?? '');
        $definition = $data['definition'] ?? '';
        if (is_array($definition)) {
            $definition = implode('; ', array_filter($definition, 'is_string'));
        }

        return [
            'word' => $lemma,
            'definition' => (string) $definition,
            'part_of_speech' => (string) ($data['part_of_speech'] ?? ''),
            'stem' => (string) ($data['stem'] ?? ''),
            'language_code' => (string) ($data['language_code'] ?? 'oj') ?: 'oj',
            'inflected_forms' => isset($data['inflected_forms']) ? json_encode($data['inflected_forms']) : '',
            'source_url' => $sourceUrl,
            'slug' => self::generateSlug($lemma),
            'status' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }

    public static function generateSlug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Ingest/EntityMapper/DictionaryEntryMapperTest.php`
Expected: OK (4 tests)

**Step 5: Commit**

```bash
git add src/Ingest/EntityMapper/DictionaryEntryMapper.php tests/Minoo/Unit/Ingest/EntityMapper/DictionaryEntryMapperTest.php
git commit -m "feat: add DictionaryEntryMapper for ingestion pipeline"
```

---

### Task 4: SpeakerMapper + ExampleSentenceMapper + WordPartMapper + CulturalCollectionMapper

**Files:**
- Create: `src/Ingest/EntityMapper/SpeakerMapper.php`
- Create: `src/Ingest/EntityMapper/ExampleSentenceMapper.php`
- Create: `src/Ingest/EntityMapper/WordPartMapper.php`
- Create: `src/Ingest/EntityMapper/CulturalCollectionMapper.php`
- Test: `tests/Minoo/Unit/Ingest/EntityMapper/SpeakerMapperTest.php`
- Test: `tests/Minoo/Unit/Ingest/EntityMapper/ExampleSentenceMapperTest.php`
- Test: `tests/Minoo/Unit/Ingest/EntityMapper/WordPartMapperTest.php`
- Test: `tests/Minoo/Unit/Ingest/EntityMapper/CulturalCollectionMapperTest.php`

**Step 1: Write SpeakerMapper test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest\EntityMapper;

use Minoo\Ingest\EntityMapper\SpeakerMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpeakerMapper::class)]
final class SpeakerMapperTest extends TestCase
{
    #[Test]
    public function it_maps_full_speaker_payload(): void
    {
        $mapper = new SpeakerMapper();
        $data = ['name' => 'Eugene Stillday', 'code' => 'es', 'bio' => 'Ponemah, Minnesota.'];

        $result = $mapper->map($data);

        $this->assertSame('Eugene Stillday', $result['name']);
        $this->assertSame('es', $result['code']);
        $this->assertSame('Ponemah, Minnesota.', $result['bio']);
        $this->assertSame('eugene-stillday', $result['slug']);
        $this->assertSame(1, $result['status']);
    }

    #[Test]
    public function it_creates_minimal_speaker_from_code(): void
    {
        $result = SpeakerMapper::fromCode('es');

        $this->assertSame('es', $result['name']);
        $this->assertSame('es', $result['code']);
        $this->assertNull($result['bio']);
        $this->assertSame(1, $result['status']);
    }
}
```

**Step 2: Write SpeakerMapper implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Ingest\EntityMapper;

final class SpeakerMapper
{
    /** @return array<string, mixed> */
    public function map(array $data): array
    {
        $name = (string) ($data['name'] ?? '');

        return [
            'name' => $name,
            'code' => (string) ($data['code'] ?? ''),
            'bio' => isset($data['bio']) ? (string) $data['bio'] : null,
            'slug' => DictionaryEntryMapper::generateSlug($name),
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }

    /** @return array<string, mixed> */
    public static function fromCode(string $code): array
    {
        return [
            'name' => $code,
            'code' => $code,
            'bio' => null,
            'slug' => $code,
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }
}
```

**Step 3: Write ExampleSentenceMapper test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest\EntityMapper;

use Minoo\Ingest\EntityMapper\ExampleSentenceMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExampleSentenceMapper::class)]
final class ExampleSentenceMapperTest extends TestCase
{
    #[Test]
    public function it_maps_sentence_with_all_fields(): void
    {
        $mapper = new ExampleSentenceMapper();
        $data = [
            'ojibwe_text' => 'Makwa agamiing dago.',
            'english_text' => 'The bear is by the lake.',
            'audio_url' => 'https://example.com/audio.mp3',
            'source_sentence_id' => 'makwa-es-001',
        ];

        $result = $mapper->map($data, 42, 7, 'oj');

        $this->assertSame('Makwa agamiing dago.', $result['ojibwe_text']);
        $this->assertSame('The bear is by the lake.', $result['english_text']);
        $this->assertSame(42, $result['dictionary_entry_id']);
        $this->assertSame(7, $result['speaker_id']);
        $this->assertSame('oj', $result['language_code']);
        $this->assertSame('https://example.com/audio.mp3', $result['audio_url']);
        $this->assertSame('makwa-es-001', $result['source_sentence_id']);
        $this->assertSame(0, $result['status']);
    }
}
```

**Step 4: Write ExampleSentenceMapper implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Ingest\EntityMapper;

final class ExampleSentenceMapper
{
    /** @return array<string, mixed> */
    public function map(array $data, int $dictionaryEntryId, ?int $speakerId, string $languageCode): array
    {
        return [
            'ojibwe_text' => (string) ($data['ojibwe_text'] ?? ''),
            'english_text' => (string) ($data['english_text'] ?? ''),
            'dictionary_entry_id' => $dictionaryEntryId,
            'speaker_id' => $speakerId,
            'language_code' => $languageCode,
            'audio_url' => (string) ($data['audio_url'] ?? ''),
            'source_sentence_id' => (string) ($data['source_sentence_id'] ?? ''),
            'status' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }
}
```

**Step 5: Write WordPartMapper test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest\EntityMapper;

use Minoo\Ingest\EntityMapper\WordPartMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WordPartMapper::class)]
final class WordPartMapperTest extends TestCase
{
    #[Test]
    public function it_maps_word_part(): void
    {
        $mapper = new WordPartMapper();
        $data = ['form' => 'makw-', 'morphological_role' => 'initial', 'definition' => 'bear'];

        $result = $mapper->map($data, 'https://example.com');

        $this->assertSame('makw-', $result['form']);
        $this->assertSame('initial', $result['type']);
        $this->assertSame('bear', $result['definition']);
        $this->assertSame('makw', $result['slug']);
        $this->assertSame(0, $result['status']);
    }

    #[Test]
    public function it_rejects_invalid_morphological_role(): void
    {
        $mapper = new WordPartMapper();
        $data = ['form' => 'test', 'morphological_role' => 'invalid', 'definition' => 'test'];

        $this->assertNull($mapper->map($data, ''));
    }
}
```

**Step 6: Write WordPartMapper implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Ingest\EntityMapper;

final class WordPartMapper
{
    private const array VALID_ROLES = ['initial', 'medial', 'final'];

    /** @return array<string, mixed>|null Null if morphological_role is invalid */
    public function map(array $data, string $sourceUrl): ?array
    {
        $role = (string) ($data['morphological_role'] ?? '');
        if (!in_array($role, self::VALID_ROLES, true)) {
            return null;
        }

        $form = (string) ($data['form'] ?? '');

        return [
            'form' => $form,
            'type' => $role,
            'definition' => (string) ($data['definition'] ?? ''),
            'source_url' => $sourceUrl,
            'slug' => DictionaryEntryMapper::generateSlug($form),
            'status' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }
}
```

**Step 7: Write CulturalCollectionMapper test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest\EntityMapper;

use Minoo\Ingest\EntityMapper\CulturalCollectionMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CulturalCollectionMapper::class)]
final class CulturalCollectionMapperTest extends TestCase
{
    #[Test]
    public function it_maps_cultural_collection(): void
    {
        $mapper = new CulturalCollectionMapper();
        $data = [
            'title' => 'The Bear Clan',
            'description' => '<p>Cultural significance</p>',
            'source_attribution' => 'UMN Ojibwe Program',
        ];

        $result = $mapper->map($data, 'https://example.com/bear-clan');

        $this->assertSame('The Bear Clan', $result['title']);
        $this->assertSame('Cultural significance', $result['description']);
        $this->assertSame('UMN Ojibwe Program', $result['source_attribution']);
        $this->assertSame('https://example.com/bear-clan', $result['source_url']);
        $this->assertSame('the-bear-clan', $result['slug']);
        $this->assertSame(0, $result['status']);
    }

    #[Test]
    public function it_strips_html_from_description(): void
    {
        $mapper = new CulturalCollectionMapper();
        $data = ['title' => 'Test', 'description' => '<h1>Title</h1><p>Content <b>bold</b></p>'];

        $result = $mapper->map($data, '');

        $this->assertSame('TitleContent bold', $result['description']);
    }
}
```

**Step 8: Write CulturalCollectionMapper implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Ingest\EntityMapper;

final class CulturalCollectionMapper
{
    /** @return array<string, mixed> */
    public function map(array $data, string $sourceUrl): array
    {
        $title = (string) ($data['title'] ?? '');
        $description = (string) ($data['description'] ?? '');
        $description = strip_tags($description);

        return [
            'title' => $title,
            'description' => $description,
            'source_attribution' => isset($data['source_attribution']) ? (string) $data['source_attribution'] : null,
            'source_url' => $sourceUrl,
            'slug' => DictionaryEntryMapper::generateSlug($title),
            'status' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }
}
```

**Step 9: Run all mapper tests**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Ingest/EntityMapper/`
Expected: OK (9 tests across 4 files)

**Step 10: Commit**

```bash
git add src/Ingest/EntityMapper/ tests/Minoo/Unit/Ingest/EntityMapper/
git commit -m "feat: add entity mappers for ingestion pipeline (speaker, sentence, word part, collection)"
```

---

### Task 5: MaterializationContext and MaterializationResult

**Files:**
- Create: `src/Ingest/MaterializationContext.php`
- Create: `src/Ingest/MaterializationResult.php`
- Test: `tests/Minoo/Unit/Ingest/MaterializationContextTest.php`

**Step 1: Write the test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest;

use Minoo\Ingest\MaterializationContext;
use Minoo\Ingest\MaterializationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MaterializationContext::class)]
#[CoversClass(MaterializationResult::class)]
final class MaterializationContextTest extends TestCase
{
    #[Test]
    public function it_caches_resolved_speakers(): void
    {
        $ctx = new MaterializationContext();

        $this->assertNull($ctx->getSpeakerId('es'));

        $ctx->setSpeakerId('es', 42);

        $this->assertSame(42, $ctx->getSpeakerId('es'));
    }

    #[Test]
    public function it_caches_resolved_word_parts(): void
    {
        $ctx = new MaterializationContext();

        $this->assertNull($ctx->getWordPartId('makw-', 'initial'));

        $ctx->setWordPartId('makw-', 'initial', 7);

        $this->assertSame(7, $ctx->getWordPartId('makw-', 'initial'));
    }

    #[Test]
    public function materialization_result_tracks_created_entities(): void
    {
        $result = new MaterializationResult();

        $result->addCreated('speaker', ['name' => 'Eugene Stillday', 'code' => 'es']);
        $result->addCreated('dictionary_entry', ['word' => 'makwa']);

        $this->assertCount(2, $result->created);
        $this->assertSame('speaker', $result->created[0]['type']);
    }

    #[Test]
    public function materialization_result_tracks_skipped_entities(): void
    {
        $result = new MaterializationResult();

        $result->addSkipped('speaker', 'es', 'Already exists');

        $this->assertCount(1, $result->skipped);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Ingest/MaterializationContextTest.php`
Expected: FAIL — classes not found

**Step 3: Write MaterializationContext**

```php
<?php

declare(strict_types=1);

namespace Minoo\Ingest;

final class MaterializationContext
{
    /** @var array<string, int> Speaker code → entity ID */
    private array $speakers = [];

    /** @var array<string, int> "form|type" → entity ID */
    private array $wordParts = [];

    public function getSpeakerId(string $code): ?int
    {
        return $this->speakers[$code] ?? null;
    }

    public function setSpeakerId(string $code, int $id): void
    {
        $this->speakers[$code] = $id;
    }

    public function getWordPartId(string $form, string $type): ?int
    {
        return $this->wordParts[$form . '|' . $type] ?? null;
    }

    public function setWordPartId(string $form, string $type, int $id): void
    {
        $this->wordParts[$form . '|' . $type] = $id;
    }
}
```

**Step 4: Write MaterializationResult**

```php
<?php

declare(strict_types=1);

namespace Minoo\Ingest;

final class MaterializationResult
{
    /** @var list<array{type: string, fields: array<string, mixed>, id?: int}> */
    public array $created = [];

    /** @var list<array{type: string, key: string, reason: string}> */
    public array $skipped = [];

    /** @var list<array{type: string, id: int, fields: array<string, mixed>}> */
    public array $updated = [];

    public ?int $primaryEntityId = null;

    /** @param array<string, mixed> $fields */
    public function addCreated(string $type, array $fields, ?int $id = null): void
    {
        $entry = ['type' => $type, 'fields' => $fields];
        if ($id !== null) {
            $entry['id'] = $id;
        }
        $this->created[] = $entry;
    }

    public function addSkipped(string $type, string $key, string $reason): void
    {
        $this->skipped[] = ['type' => $type, 'key' => $key, 'reason' => $reason];
    }

    /** @param array<string, mixed> $fields */
    public function addUpdated(string $type, int $id, array $fields): void
    {
        $this->updated[] = ['type' => $type, 'id' => $id, 'fields' => $fields];
    }
}
```

**Step 5: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Ingest/MaterializationContextTest.php`
Expected: OK (4 tests)

**Step 6: Commit**

```bash
git add src/Ingest/MaterializationContext.php src/Ingest/MaterializationResult.php tests/Minoo/Unit/Ingest/MaterializationContextTest.php
git commit -m "feat: add MaterializationContext and MaterializationResult"
```

---

### Task 6: IngestImporter (envelope → IngestLog)

**Files:**
- Create: `src/Ingest/IngestImporter.php`
- Test: `tests/Minoo/Unit/Ingest/IngestImporterTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest;

use Minoo\Entity\IngestLog;
use Minoo\Ingest\IngestImporter;
use Minoo\Ingest\PayloadValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IngestImporter::class)]
final class IngestImporterTest extends TestCase
{
    private IngestImporter $importer;

    protected function setUp(): void
    {
        $this->importer = new IngestImporter(new PayloadValidator());
    }

    #[Test]
    public function it_creates_ingest_log_from_valid_envelope(): void
    {
        $envelope = $this->validEnvelope();

        $result = $this->importer->import($envelope);

        $this->assertInstanceOf(IngestLog::class, $result);
        $this->assertSame('pending_review', $result->get('status'));
        $this->assertSame('ojibwe_lib', $result->get('source'));
        $this->assertSame('dictionary_entry', $result->get('entity_type_target'));
        $this->assertStringContainsString('ojibwe_lib', $result->get('title'));
    }

    #[Test]
    public function it_stores_raw_envelope_in_payload_raw(): void
    {
        $envelope = $this->validEnvelope();

        $result = $this->importer->import($envelope);

        $raw = json_decode($result->get('payload_raw'), true);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $raw['payload_id']);
    }

    #[Test]
    public function it_stores_mapped_fields_in_payload_parsed(): void
    {
        $envelope = $this->validEnvelope();

        $result = $this->importer->import($envelope);

        $parsed = json_decode($result->get('payload_parsed'), true);
        $this->assertSame('makwa', $parsed['word']);
        $this->assertSame('bear', $parsed['definition']);
    }

    #[Test]
    public function it_creates_failed_log_for_invalid_envelope(): void
    {
        $envelope = ['version' => '1.0'];

        $result = $this->importer->import($envelope);

        $this->assertSame('failed', $result->get('status'));
        $this->assertNotEmpty($result->get('error_message'));
    }

    /** @return array<string, mixed> */
    private function validEnvelope(): array
    {
        return [
            'payload_id' => '550e8400-e29b-41d4-a716-446655440000',
            'version' => '1.0',
            'source' => 'ojibwe_lib',
            'snapshot_type' => 'full',
            'timestamp' => '2026-03-06T14:30:00Z',
            'entity_type' => 'dictionary_entry',
            'source_url' => 'https://ojibwe.lib.umn.edu/main-entry/makwa-na',
            'data' => [
                'lemma' => 'makwa',
                'definition' => 'bear',
                'part_of_speech' => 'na',
                'stem' => '/makw-/',
                'language_code' => 'oj',
            ],
        ];
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Ingest/IngestImporterTest.php`
Expected: FAIL — class not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Ingest;

use Minoo\Entity\IngestLog;
use Minoo\Ingest\EntityMapper\CulturalCollectionMapper;
use Minoo\Ingest\EntityMapper\DictionaryEntryMapper;
use Minoo\Ingest\EntityMapper\SpeakerMapper;

final class IngestImporter
{
    public function __construct(
        private readonly PayloadValidator $validator,
    ) {}

    public function import(array $envelope): IngestLog
    {
        // Validate.
        $result = $this->validator->validate($envelope);
        if (!$result->isValid()) {
            return $this->createFailedLog($envelope, implode('; ', $result->errors));
        }

        // Map data to entity fields.
        $entityType = (string) $envelope['entity_type'];
        $sourceUrl = (string) $envelope['source_url'];
        $data = (array) $envelope['data'];

        $mapped = match ($entityType) {
            'dictionary_entry' => (new DictionaryEntryMapper())->map($data, $sourceUrl),
            'speaker' => (new SpeakerMapper())->map($data),
            'cultural_collection' => (new CulturalCollectionMapper())->map($data, $sourceUrl),
            default => [],
        };

        $source = (string) ($envelope['source'] ?? 'unknown');
        $timestamp = (string) ($envelope['timestamp'] ?? date('c'));

        return new IngestLog([
            'title' => sprintf('%s — %s', $source, $timestamp),
            'source' => $source,
            'entity_type_target' => $entityType,
            'payload_raw' => json_encode($envelope, JSON_THROW_ON_ERROR),
            'payload_parsed' => json_encode($mapped, JSON_THROW_ON_ERROR),
            'status' => 'pending_review',
        ]);
    }

    private function createFailedLog(array $envelope, string $error): IngestLog
    {
        return new IngestLog([
            'title' => sprintf('%s — failed', (string) ($envelope['source'] ?? 'unknown')),
            'source' => (string) ($envelope['source'] ?? 'unknown'),
            'entity_type_target' => (string) ($envelope['entity_type'] ?? ''),
            'payload_raw' => json_encode($envelope, JSON_THROW_ON_ERROR),
            'payload_parsed' => '{}',
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Ingest/IngestImporterTest.php`
Expected: OK (4 tests)

**Step 5: Commit**

```bash
git add src/Ingest/IngestImporter.php tests/Minoo/Unit/Ingest/IngestImporterTest.php
git commit -m "feat: add IngestImporter (envelope → IngestLog)"
```

---

### Task 7: IngestMaterializer (IngestLog → entities)

**Files:**
- Create: `src/Ingest/IngestMaterializer.php`
- Test: `tests/Minoo/Unit/Ingest/IngestMaterializerTest.php`

This is the most complex class. It uses `EntityTypeManager` to create entities.

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest;

use Minoo\Entity\IngestLog;
use Minoo\Ingest\IngestMaterializer;
use Minoo\Ingest\MaterializationContext;
use Minoo\Ingest\MaterializationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManagerInterface;

#[CoversClass(IngestMaterializer::class)]
final class IngestMaterializerTest extends TestCase
{
    #[Test]
    public function dry_run_returns_preview_without_persisting(): void
    {
        $manager = $this->createMock(EntityTypeManagerInterface::class);
        $manager->expects($this->never())->method('getStorage');

        $materializer = new IngestMaterializer($manager);
        $log = $this->createDictionaryLog();

        $result = $materializer->materialize($log, dryRun: true);

        $this->assertInstanceOf(MaterializationResult::class, $result);
        $this->assertNotEmpty($result->created);
        $this->assertNull($result->primaryEntityId);
    }

    #[Test]
    public function dry_run_previews_dictionary_entry_with_children(): void
    {
        $manager = $this->createMock(EntityTypeManagerInterface::class);
        $materializer = new IngestMaterializer($manager);
        $log = $this->createDictionaryLog();

        $result = $materializer->materialize($log, dryRun: true);

        $types = array_column($result->created, 'type');
        $this->assertContains('dictionary_entry', $types);
        $this->assertContains('speaker', $types);
        $this->assertContains('example_sentence', $types);
        $this->assertContains('word_part', $types);
    }

    private function createDictionaryLog(): IngestLog
    {
        $parsed = [
            'word' => 'makwa',
            'definition' => 'bear',
            'part_of_speech' => 'na',
            'stem' => '/makw-/',
            'language_code' => 'oj',
            'inflected_forms' => '[{"form":"makwag","label":"plural"}]',
            'source_url' => 'https://example.com',
            'slug' => 'makwa',
            'status' => 0,
            'created_at' => 0,
            'updated_at' => 0,
        ];

        $raw = json_encode([
            'payload_id' => 'test-uuid',
            'version' => '1.0',
            'source' => 'ojibwe_lib',
            'snapshot_type' => 'full',
            'timestamp' => '2026-03-06T14:30:00Z',
            'entity_type' => 'dictionary_entry',
            'source_url' => 'https://example.com',
            'data' => [
                'lemma' => 'makwa',
                'definition' => 'bear',
                'part_of_speech' => 'na',
                'stem' => '/makw-/',
                'language_code' => 'oj',
                'example_sentences' => [
                    [
                        'source_sentence_id' => 'makwa-es-001',
                        'ojibwe_text' => 'Makwa agamiing dago.',
                        'english_text' => 'The bear is by the lake.',
                        'speaker_code' => 'es',
                    ],
                ],
                'word_parts' => [
                    ['form' => 'makw-', 'morphological_role' => 'initial', 'definition' => 'bear'],
                ],
            ],
        ]);

        return new IngestLog([
            'title' => 'ojibwe_lib — test',
            'source' => 'ojibwe_lib',
            'entity_type_target' => 'dictionary_entry',
            'payload_raw' => $raw,
            'payload_parsed' => json_encode($parsed),
            'status' => 'pending_review',
        ]);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Ingest/IngestMaterializerTest.php`
Expected: FAIL — class not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Ingest;

use Minoo\Entity\IngestLog;
use Minoo\Ingest\EntityMapper\ExampleSentenceMapper;
use Minoo\Ingest\EntityMapper\SpeakerMapper;
use Minoo\Ingest\EntityMapper\WordPartMapper;
use Waaseyaa\Entity\EntityTypeManagerInterface;

final class IngestMaterializer
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    public function materialize(IngestLog $log, bool $dryRun = false): MaterializationResult
    {
        $result = new MaterializationResult();
        $context = new MaterializationContext();

        $rawEnvelope = json_decode($log->get('payload_raw'), true) ?? [];
        $parsedFields = json_decode($log->get('payload_parsed'), true) ?? [];
        $entityType = (string) $log->get('entity_type_target');
        $data = $rawEnvelope['data'] ?? [];

        return match ($entityType) {
            'dictionary_entry' => $this->materializeDictionaryEntry($parsedFields, $data, $context, $result, $dryRun),
            'speaker' => $this->materializeSpeaker($parsedFields, $result, $dryRun),
            'cultural_collection' => $this->materializeCulturalCollection($parsedFields, $result, $dryRun),
            default => $result,
        };
    }

    private function materializeDictionaryEntry(
        array $parsedFields,
        array $rawData,
        MaterializationContext $context,
        MaterializationResult $result,
        bool $dryRun,
    ): MaterializationResult {
        // 1. Resolve speakers from example sentences.
        $sentences = $rawData['example_sentences'] ?? [];
        foreach ($sentences as $sentence) {
            $code = (string) ($sentence['speaker_code'] ?? '');
            if ($code !== '' && $context->getSpeakerId($code) === null) {
                $speakerFields = SpeakerMapper::fromCode($code);
                if ($dryRun) {
                    $result->addCreated('speaker', $speakerFields);
                    $context->setSpeakerId($code, 0);
                } else {
                    $id = $this->getOrCreateSpeaker($code, $speakerFields);
                    $context->setSpeakerId($code, $id);
                    $result->addCreated('speaker', $speakerFields, $id);
                }
            }
        }

        // 2. Resolve word parts.
        $wordPartMapper = new WordPartMapper();
        $sourceUrl = (string) ($parsedFields['source_url'] ?? '');
        foreach ($rawData['word_parts'] ?? [] as $wpData) {
            $wpFields = $wordPartMapper->map($wpData, $sourceUrl);
            if ($wpFields === null) {
                continue;
            }
            $form = $wpFields['form'];
            $type = $wpFields['type'];
            if ($context->getWordPartId($form, $type) === null) {
                if ($dryRun) {
                    $result->addCreated('word_part', $wpFields);
                    $context->setWordPartId($form, $type, 0);
                } else {
                    $id = $this->getOrCreateWordPart($form, $type, $wpFields);
                    $context->setWordPartId($form, $type, $id);
                    $result->addCreated('word_part', $wpFields, $id);
                }
            }
        }

        // 3. Create dictionary entry.
        if ($dryRun) {
            $result->addCreated('dictionary_entry', $parsedFields);
        } else {
            $storage = $this->entityTypeManager->getStorage('dictionary_entry');
            $entity = $storage->create($parsedFields);
            $storage->save($entity);
            $entryId = (int) $entity->id();
            $result->addCreated('dictionary_entry', $parsedFields, $entryId);
            $result->primaryEntityId = $entryId;
        }

        // 4. Create example sentences.
        $sentenceMapper = new ExampleSentenceMapper();
        $languageCode = (string) ($parsedFields['language_code'] ?? 'oj');
        foreach ($sentences as $sData) {
            $speakerCode = (string) ($sData['speaker_code'] ?? '');
            $speakerId = $speakerCode !== '' ? $context->getSpeakerId($speakerCode) : null;
            $entryId = $result->primaryEntityId ?? 0;
            $sFields = $sentenceMapper->map($sData, $entryId, $speakerId, $languageCode);

            if ($dryRun) {
                $result->addCreated('example_sentence', $sFields);
            } else {
                $storage = $this->entityTypeManager->getStorage('example_sentence');
                $entity = $storage->create($sFields);
                $storage->save($entity);
                $result->addCreated('example_sentence', $sFields, (int) $entity->id());
            }
        }

        return $result;
    }

    private function materializeSpeaker(array $parsedFields, MaterializationResult $result, bool $dryRun): MaterializationResult
    {
        if ($dryRun) {
            $result->addCreated('speaker', $parsedFields);
            return $result;
        }

        $code = (string) ($parsedFields['code'] ?? '');
        $id = $this->getOrCreateSpeaker($code, $parsedFields);
        $result->addCreated('speaker', $parsedFields, $id);
        $result->primaryEntityId = $id;

        return $result;
    }

    private function materializeCulturalCollection(array $parsedFields, MaterializationResult $result, bool $dryRun): MaterializationResult
    {
        if ($dryRun) {
            $result->addCreated('cultural_collection', $parsedFields);
            return $result;
        }

        $storage = $this->entityTypeManager->getStorage('cultural_collection');
        $entity = $storage->create($parsedFields);
        $storage->save($entity);
        $result->addCreated('cultural_collection', $parsedFields, (int) $entity->id());
        $result->primaryEntityId = (int) $entity->id();

        return $result;
    }

    private function getOrCreateSpeaker(string $code, array $fields): int
    {
        $storage = $this->entityTypeManager->getStorage('speaker');
        $ids = $storage->getQuery()->condition('code', $code)->execute();

        if ($ids !== []) {
            return (int) reset($ids);
        }

        $entity = $storage->create($fields);
        $storage->save($entity);
        return (int) $entity->id();
    }

    private function getOrCreateWordPart(string $form, string $type, array $fields): int
    {
        $storage = $this->entityTypeManager->getStorage('word_part');
        $ids = $storage->getQuery()
            ->condition('form', $form)
            ->condition('type', $type)
            ->execute();

        if ($ids !== []) {
            return (int) reset($ids);
        }

        $entity = $storage->create($fields);
        $storage->save($entity);
        return (int) $entity->id();
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Ingest/IngestMaterializerTest.php`
Expected: OK (2 tests)

**Step 5: Commit**

```bash
git add src/Ingest/IngestMaterializer.php tests/Minoo/Unit/Ingest/IngestMaterializerTest.php
git commit -m "feat: add IngestMaterializer with dry-run support"
```

---

### Task 8: Test Fixtures

**Files:**
- Create: `tests/fixtures/ojibwe_lib/dictionary_entry_makwa.json`
- Create: `tests/fixtures/ojibwe_lib/speaker_es.json`
- Create: `tests/fixtures/ojibwe_lib/cultural_collection_bear_clan.json`
- Create: `tests/fixtures/ojibwe_lib/invalid_envelope.json`

**Step 1: Create fixture files**

`dictionary_entry_makwa.json`:
```json
{
  "payload_id": "fix-dict-makwa-001",
  "version": "1.0",
  "source": "ojibwe_lib",
  "snapshot_type": "full",
  "timestamp": "2026-03-06T14:30:00Z",
  "entity_type": "dictionary_entry",
  "source_url": "https://ojibwe.lib.umn.edu/main-entry/makwa-na",
  "data": {
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
}
```

`speaker_es.json`:
```json
{
  "payload_id": "fix-speaker-es-001",
  "version": "1.0",
  "source": "ojibwe_lib",
  "snapshot_type": "full",
  "timestamp": "2026-03-06T14:30:00Z",
  "entity_type": "speaker",
  "source_url": "https://ojibwe.lib.umn.edu/speaker/eugene-stillday",
  "data": {
    "name": "Eugene Stillday",
    "code": "es",
    "bio": "Ponemah, Minnesota. First-language speaker of Southwestern Ojibwe.",
    "photo_url": null,
    "region": "Ponemah, MN"
  }
}
```

`cultural_collection_bear_clan.json`:
```json
{
  "payload_id": "fix-cc-bear-001",
  "version": "1.0",
  "source": "ojibwe_lib",
  "snapshot_type": "full",
  "timestamp": "2026-03-06T14:30:00Z",
  "entity_type": "cultural_collection",
  "source_url": "https://ojibwe.lib.umn.edu/culture/bear-clan",
  "data": {
    "title": "The Bear Clan",
    "description": "Cultural significance of makwa in Anishinaabe tradition.",
    "source_attribution": "University of Minnesota Ojibwe Language Program",
    "topics": ["clans", "animals"]
  }
}
```

`invalid_envelope.json`:
```json
{
  "version": "1.0",
  "source": "test"
}
```

**Step 2: Commit**

```bash
git add tests/fixtures/
git commit -m "feat: add test fixtures for ingestion pipeline"
```

---

### Task 9: Integration Test — Full Pipeline

**Files:**
- Create: `tests/Minoo/Integration/IngestPipelineTest.php`

**Step 1: Write integration test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration;

use Minoo\Ingest\IngestImporter;
use Minoo\Ingest\IngestMaterializer;
use Minoo\Ingest\PayloadValidator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

#[CoversNothing]
final class IngestPipelineTest extends TestCase
{
    private static HttpKernel $kernel;
    private static EntityTypeManager $manager;

    public static function setUpBeforeClass(): void
    {
        $projectRoot = dirname(__DIR__, 3);

        $cachePath = $projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        putenv('WAASEYAA_DB=:memory:');

        self::$kernel = new HttpKernel($projectRoot);
        $boot = new \ReflectionMethod(AbstractKernel::class, 'boot');
        $boot->invoke(self::$kernel);

        self::$manager = self::$kernel->getEntityTypeManager();
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');

        $cachePath = dirname(__DIR__, 3) . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    #[Test]
    public function full_pipeline_creates_entities_from_fixture(): void
    {
        $fixture = json_decode(
            file_get_contents(dirname(__DIR__, 3) . '/tests/fixtures/ojibwe_lib/dictionary_entry_makwa.json'),
            true,
        );

        // Phase 1: Import.
        $importer = new IngestImporter(new PayloadValidator());
        $log = $importer->import($fixture);

        $this->assertSame('pending_review', $log->get('status'));
        $this->assertSame('dictionary_entry', $log->get('entity_type_target'));

        // Phase 2: Materialize.
        $materializer = new IngestMaterializer(self::$manager);
        $result = $materializer->materialize($log);

        $this->assertNotNull($result->primaryEntityId);

        // Verify dictionary entry was created.
        $entry = self::$manager->getStorage('dictionary_entry')->load($result->primaryEntityId);
        $this->assertNotNull($entry);
        $this->assertSame('makwa', $entry->get('word'));
        $this->assertSame('bear', $entry->get('definition'));
        $this->assertSame('na', $entry->get('part_of_speech'));

        // Verify speaker was created.
        $speakerIds = self::$manager->getStorage('speaker')
            ->getQuery()->condition('code', 'es')->execute();
        $this->assertNotEmpty($speakerIds);

        // Verify example sentence was created.
        $sentenceIds = self::$manager->getStorage('example_sentence')
            ->getQuery()->condition('dictionary_entry_id', $result->primaryEntityId)->execute();
        $this->assertNotEmpty($sentenceIds);
    }

    #[Test]
    public function dry_run_does_not_persist_entities(): void
    {
        $fixture = json_decode(
            file_get_contents(dirname(__DIR__, 3) . '/tests/fixtures/ojibwe_lib/speaker_es.json'),
            true,
        );

        $importer = new IngestImporter(new PayloadValidator());
        $log = $importer->import($fixture);

        $materializer = new IngestMaterializer(self::$manager);
        $result = $materializer->materialize($log, dryRun: true);

        $this->assertNotEmpty($result->created);
        $this->assertNull($result->primaryEntityId);
    }
}
```

**Step 2: Run integration test**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit tests/Minoo/Integration/IngestPipelineTest.php`
Expected: OK (2 tests)

**Step 3: Run full suite**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit`
Expected: All pass

**Step 4: Commit**

```bash
git add tests/Minoo/Integration/IngestPipelineTest.php
git commit -m "test: add integration test for full ingestion pipeline"
```

---

### Task 10: Update CLAUDE.md

**Files:**
- Modify: `CLAUDE.md`

**Step 1: Add Ingest classes to orchestration table**

Add row after existing `tests/Minoo/*` row:

```
| `src/Ingest/*` | `minoo:entities` | `docs/plans/2026-03-06-ingestion-pipeline-design.md` |
```

**Step 2: Update test counts**

Update the test count in the Commands section to reflect the new totals after all tasks are complete.

**Step 3: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: update CLAUDE.md with ingestion pipeline context"
```

---

### Task Summary

| Task | Component | Tests | Dependencies |
|------|-----------|-------|-------------|
| 1 | source_sentence_id field | 1 | None |
| 2 | PayloadValidator | 5 | None |
| 3 | DictionaryEntryMapper | 4 | None |
| 4 | Remaining mappers (4) | 5 | Task 3 (slug helper) |
| 5 | MaterializationContext + Result | 4 | None |
| 6 | IngestImporter | 4 | Tasks 2, 3, 4 |
| 7 | IngestMaterializer | 2 | Tasks 4, 5 |
| 8 | Test fixtures | 0 | None |
| 9 | Integration test | 2 | Tasks 6, 7, 8 |
| 10 | CLAUDE.md update | 0 | All |
