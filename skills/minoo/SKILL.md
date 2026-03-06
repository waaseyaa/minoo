---
name: minoo:entities
description: Use when working on Minoo entity types, access policies, service providers, or seed data in src/ or tests/Minoo/
---

# Minoo Entity System

## Scope

Packages: `src/Entity/`, `src/Provider/`, `src/Access/`, `src/Seed/`
Tests: `tests/Minoo/Unit/`, `tests/Minoo/Integration/`

## Entity Class Pattern

Content entities extend `ContentEntityBase`, config entities extend `ConfigEntityBase`.
Both hardcode `entityTypeId` and `entityKeys`, with defaults merged in the constructor:

```php
// Content entity (has UUID, field definitions, status)
final class Event extends ContentEntityBase
{
    protected string $entityTypeId = 'event';
    protected array $entityKeys = ['id' => 'eid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type'];

    public function __construct(array $values = [])
    {
        $values += ['status' => 1, 'created_at' => 0, 'updated_at' => 0];
        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}

// Config entity (machine-name keyed, no UUID)
final class EventType extends ConfigEntityBase
{
    protected string $entityTypeId = 'event_type';
    protected array $entityKeys = ['id' => 'type', 'label' => 'name'];

    public function __construct(array $values = [])
    {
        $values += ['description' => ''];
        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

Content entity key naming — unique primary key per type:
- `eid` (event), `gid` (group), `cgid` (cultural_group), `tid` (teaching)
- `ccid` (cultural_collection), `deid` (dictionary_entry)
- `esid` (example_sentence), `wpid` (word_part), `sid` (speaker)

Config entity keys always use `type` for id and `name` for label.

## Service Provider Pattern

Each provider registers `EntityType` definitions using `$this->entityType()`:

```php
final class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'event',
            label: 'Event',
            entityClass: Event::class,
            entityKeys: ['id' => 'eid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type'],
            fieldDefinitions: [
                'slug' => FieldDefinition::create('string')->setWidget('text')->setLabel('URL Slug'),
                'description' => FieldDefinition::create('string')->setWidget('richtext')->setLabel('Description'),
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

All 6 policies follow identical logic using `match`:

```php
#[PolicyAttribute(entityType: 'event')]
final class EventAccessPolicy implements AccessPolicyInterface
{
    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }
        return match ($operation) {
            'view' => (int) $entity->get('status') === 1 && $account->hasPermission('access content')
                ? AccessResult::allowed('Published and user has access content.')
                : AccessResult::neutral('Cannot view unpublished.'),
            default => AccessResult::neutral('Non-admin cannot modify.'),
        };
    }
    // createAccess() follows same admin-check pattern
}
```

`LanguageAccessPolicy` covers 4 types via array:
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
  group (has type → group_type, has media_id, has url, region)
  group_type (standalone config entity)
  cultural_group (has parent_id → self, hierarchical tree)

Teachings domain:
  teaching (has type → teaching_type, has cultural_group_id, has tags → taxonomy_term)
  teaching_type (standalone config entity)
  cultural_collection (has gallery → taxonomy_term, has source_url, source_attribution, media_id)

Language domain:
  dictionary_entry (has word, definition, part_of_speech, stem, inflected_forms)
  example_sentence (refs → dictionary_entry, refs → speaker, has audio_url)
  word_part (has form, type: initial/medial/final, definition)
  speaker (has name, code, bio, media_id)
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
    public function hasPermission(string $permission): bool
    {
        return $permission === 'access content';
    }
    public function getRoles(): array { return ['anonymous']; }
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
- **Wrong base class**: Content entities use `ContentEntityBase`, config entities use `ConfigEntityBase` — not plain `EntityBase`
- **Wrong constructor**: Must pass `$this->entityTypeId` and `$this->entityKeys` to parent constructor

## Related Specs

- `docs/specs/entity-model.md` — full entity model, field definitions, access patterns, seed data
- Framework: `waaseyaa_get_spec entity-system` — EntityBase, EntityType, storage
- Framework: `waaseyaa_get_spec access-control` — AccessPolicyInterface, Gate, PolicyAttribute
- Framework: `waaseyaa_get_spec infrastructure` — kernel boot, PackageManifestCompiler
