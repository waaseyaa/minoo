---
name: minoo:entities
description: Use when working on Minoo entity types, access policies, service providers, or seed data in src/ or tests/Minoo/
---

# Minoo Entity System

## Scope

Packages: `src/Entity/`, `src/Provider/`, `src/Access/`, `src/Seed/`
Tests: `tests/Minoo/Unit/`, `tests/Minoo/Integration/`

## Entity Class Pattern

All 12 entities follow identical structure — extend `EntityBase`, hardcode type ID and keys:

```php
final class Event extends EntityBase
{
    protected string $entityTypeId = 'event';
    protected array $entityKeys = ['eid' => 'eid'];

    public function __construct(array $values)
    {
        parent::__construct($values);
    }
}
```

Entity key naming convention: first letter(s) of type + `id`:
- `eid` (event), `etid` (event_type), `gid` (group), `gtid` (group_type)
- `cgid` (cultural_group), `tid` (teaching), `ttid` (teaching_type)
- `ccid` (cultural_collection), `deid` (dictionary_entry)
- `esid` (example_sentence), `wpid` (word_part), `spid` (speaker)

## Service Provider Pattern

Each provider registers `EntityType` definitions with field definitions in `register()`:

```php
final class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $manager = $this->getEntityTypeManager();
        $manager->addDefinition(new EntityType(
            id: 'event',
            label: 'Event',
            entityClass: Event::class,
            entityKeys: ['eid' => 'eid'],
            fieldDefinitions: [
                'title' => FieldDefinition::create('string')->setLabel('Title')->setDescription('...'),
                'slug' => FieldDefinition::create('string')->setWidget('text')->setLabel('URL Slug'),
                // ...
            ],
        ));
    }
}
```

Key field types and widgets:
- `string` with `text` widget — plain text input
- `string` with `richtext` widget — rich text editor (NOTE: admin SPA richtext widget has a bug, see framework#181)
- `string` with `datetime` widget + `date-time` format — date/time picker
- `string` with `url` widget — URL input
- `string` with `textarea` widget — multiline text
- `boolean` with `boolean` widget — checkbox (default: `true` for `status` fields)
- `string` with `entity_autocomplete` widget + `x-target-type` — entity reference autocomplete
- `string` with `hidden` widget — not shown in forms (used for IDs, UUIDs)

## Entity Reference Fields

Cross-entity references use `entity_autocomplete` widget with `x-target-type`:

```php
'dictionary_entry_id' => FieldDefinition::create('string')
    ->setWidget('entity_autocomplete')
    ->setLabel('Dictionary Entry')
    ->setSetting('x-target-type', 'dictionary_entry'),
```

Used by: `example_sentence` → `dictionary_entry`, `example_sentence` → `speaker`

## Access Policy Pattern

All 6 policies follow identical logic:

```php
#[PolicyAttribute(entityType: 'event')]
final class EventAccessPolicy implements AccessPolicyInterface
{
    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->isAuthenticated() && $account->hasPermission('administer content')) {
            return AccessResult::allowed();
        }
        if ($operation === 'view' && $entity->get('status') == 1) {
            return AccessResult::allowed();
        }
        return AccessResult::neutral();
    }
    // createAccess() and appliesTo() follow same pattern
}
```

`LanguageAccessPolicy` is the exception — covers 4 types via array:
```php
#[PolicyAttribute(entityType: ['dictionary_entry', 'example_sentence', 'word_part', 'speaker'])]
```

## Seed Data Pattern

Static methods returning structured arrays (not entities):

```php
// TaxonomySeeder — vocabulary definitions with terms
TaxonomySeeder::galleryVocabulary()      // vid='gallery', 6 terms
TaxonomySeeder::teachingTagsVocabulary() // vid='teaching_tags', 6 terms

// ConfigSeeder — type configuration arrays
ConfigSeeder::eventTypes()    // 3 types: powwow, gathering, ceremony
ConfigSeeder::groupTypes()    // 3 types: online, offline, advocacy
ConfigSeeder::teachingTypes() // 3 types: culture, history, language
```

## Domain Relationships

```
Events domain:
  event (has type → event_type, has media_id)
  event_type (standalone config entity)

Groups domain:
  group (has type → group_type, has media_id)
  group_type (standalone config entity)
  cultural_group (has parent_id → self, hierarchical tree)

Teachings domain:
  teaching (has type → teaching_type, has tags → taxonomy_term)
  teaching_type (standalone config entity)
  cultural_collection (has gallery → taxonomy_term, has media_id)

Language domain:
  dictionary_entry (has word, definition, part_of_speech, stem, inflected_forms)
  example_sentence (refs → dictionary_entry, refs → speaker, has audio_url)
  word_part (has type: initial/medial/final)
  speaker (has community, dialect, bio)
```

## Testing Patterns

**Unit tests** — verify entity construction and field access:
```php
$event = new Event(['title' => 'Test', 'status' => 1]);
$this->assertSame('event', $event->getEntityTypeId());
$this->assertSame('Test', $event->get('title'));
```

**Access policy tests** — use anonymous classes for AccountInterface:
```php
$anonymous = new class implements AccountInterface {
    public function id(): int { return 0; }
    public function hasPermission(string $permission): bool { return false; }
    public function getRoles(): array { return []; }
    public function isAuthenticated(): bool { return false; }
};
```

**Integration test** — boot kernel with in-memory SQLite:
```php
putenv('WAASEYAA_DB=:memory:');
$manifestCache = dirname(__DIR__, 3) . '/storage/framework/packages.php';
@unlink($manifestCache); // clear stale cache
$kernel = new HttpKernel(dirname(__DIR__, 3));
(new \ReflectionMethod(AbstractKernel::class, 'boot'))->invoke($kernel);
```

## Common Mistakes

- **Wrong dirname depth**: `tests/Minoo/Integration/` is 3 levels from project root, not 2
- **Forgetting to clear manifest cache**: New providers/policies won't be discovered until `storage/framework/packages.php` is deleted
- **Using `createMock()` for AccountInterface**: PHPUnit can't mock the interface reliably — use anonymous classes
- **Duplicate entity keys**: Each entity type must have a unique primary key name — don't reuse `id`
- **Missing `enforceIsNew()`**: When creating entities with pre-set IDs in tests, call `$entity->enforceIsNew()` before `save()`

## Related Specs

- `docs/specs/entity-model.md` — full entity model, field definitions, access patterns, seed data
- Framework: `waaseyaa_get_spec entity-system` — EntityBase, EntityType, storage
- Framework: `waaseyaa_get_spec access-control` — AccessPolicyInterface, Gate, PolicyAttribute
- Framework: `waaseyaa_get_spec infrastructure` — kernel boot, PackageManifestCompiler
