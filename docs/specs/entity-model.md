# Minoo Entity Model Specification

## File Map

| File | Purpose |
|------|---------|
| `src/Entity/Event.php` | Event content entity — community gatherings, powwows, ceremonies |
| `src/Entity/EventType.php` | Event type config entity (powwow, gathering, ceremony) |
| `src/Entity/Group.php` | Community group content entity |
| `src/Entity/GroupType.php` | Group type config entity (online, offline, advocacy) |
| `src/Entity/CulturalGroup.php` | Hierarchical cultural group with `parent_id` self-reference |
| `src/Entity/Teaching.php` | Teaching content entity with taxonomy tags |
| `src/Entity/TeachingType.php` | Teaching type config entity (culture, history, language) |
| `src/Entity/CulturalCollection.php` | Cultural collection with gallery taxonomy + media |
| `src/Entity/DictionaryEntry.php` | Ojibwe dictionary entry with linguistic metadata |
| `src/Entity/ExampleSentence.php` | Example sentence referencing dictionary entry + speaker |
| `src/Entity/WordPart.php` | Word component (initial, medial, final morpheme) |
| `src/Entity/Speaker.php` | Language speaker profile |
| `src/Provider/EventServiceProvider.php` | Registers event + event_type entity types |
| `src/Provider/GroupServiceProvider.php` | Registers group + group_type entity types |
| `src/Provider/CulturalGroupServiceProvider.php` | Registers cultural_group entity type |
| `src/Provider/TeachingServiceProvider.php` | Registers teaching + teaching_type entity types |
| `src/Provider/CulturalCollectionServiceProvider.php` | Registers cultural_collection entity type |
| `src/Provider/LanguageServiceProvider.php` | Registers dictionary_entry, example_sentence, word_part, speaker |
| `src/Access/EventAccessPolicy.php` | Access for `event` type |
| `src/Access/GroupAccessPolicy.php` | Access for `group` type |
| `src/Access/CulturalGroupAccessPolicy.php` | Access for `cultural_group` type |
| `src/Access/TeachingAccessPolicy.php` | Access for `teaching` type |
| `src/Access/CulturalCollectionAccessPolicy.php` | Access for `cultural_collection` type |
| `src/Access/LanguageAccessPolicy.php` | Access for all 4 language types |
| `src/Seed/TaxonomySeeder.php` | Gallery + teaching_tags vocabulary definitions |
| `src/Seed/ConfigSeeder.php` | Event, group, teaching type definitions |

## Base Classes

Content entities extend `ContentEntityBase` (UUID auto-generation, field definitions support).
Config entities extend `ConfigEntityBase` (machine-name keyed, no UUID).

Constructor pattern for all entities:
```php
public function __construct(array $values = [])
{
    $values += ['status' => 1, 'created_at' => 0, 'updated_at' => 0]; // defaults
    parent::__construct($values, $this->entityTypeId, $this->entityKeys);
}
```

## Entity Type Definitions

### Events Domain

**event** (`ContentEntityBase`) — Primary key: `eid`
Entity keys: `['id' => 'eid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type']`

| Field | Widget/Type | Notes |
|-------|-------------|-------|
| eid | (auto) | Auto-increment PK |
| uuid | (auto) | UUID v4 |
| title | (entity key label) | Event name |
| type | (entity key bundle) | Event type reference |
| slug | text | URL-safe identifier |
| description | richtext | Event details |
| location | text | Venue or area |
| starts_at | datetime | Event start |
| ends_at | datetime | Event end |
| media_id | entity_reference (→ media) | Primary image |
| status | boolean (default: 1) | Published |
| created_at | timestamp | Created |
| updated_at | timestamp | Updated |

**event_type** (`ConfigEntityBase`) — Primary key: `type`
Entity keys: `['id' => 'type', 'label' => 'name']`
Default values: `description => ''`
No field definitions (config entity uses entity keys only).

### Groups Domain

**group** (`ContentEntityBase`) — Primary key: `gid`
Entity keys: `['id' => 'gid', 'uuid' => 'uuid', 'label' => 'name', 'bundle' => 'type']`

| Field | Widget/Type | Notes |
|-------|-------------|-------|
| gid | (auto) | Auto-increment PK |
| uuid | (auto) | |
| name | (entity key label) | Group name |
| type | (entity key bundle) | Group type reference |
| slug | text | URL slug |
| description | richtext | Group description |
| url | url | Group website |
| region | text | Geographic region |
| media_id | entity_reference (→ media) | Group image |
| status | boolean (default: 1) | Published |
| created_at | timestamp | |
| updated_at | timestamp | |

**group_type** (`ConfigEntityBase`) — Primary key: `type`
Entity keys: `['id' => 'type', 'label' => 'name']`

**cultural_group** (`ContentEntityBase`) — Primary key: `cgid`
Entity keys: `['id' => 'cgid', 'uuid' => 'uuid', 'label' => 'name']`

| Field | Widget/Type | Notes |
|-------|-------------|-------|
| cgid | (auto) | Auto-increment PK |
| uuid | (auto) | |
| name | (entity key label) | Cultural group name |
| slug | text | URL slug |
| parent_id | entity_reference (→ cultural_group) | Self-reference for hierarchy |
| depth_label | text | Hierarchy level label |
| description | richtext | |
| metadata | textarea | JSON metadata |
| media_id | entity_reference (→ media) | |
| sort_order | integer (default: 0) | Display order |
| status | boolean (default: 1) | |
| created_at | timestamp | |
| updated_at | timestamp | |

### Teachings Domain

**teaching** (`ContentEntityBase`) — Primary key: `tid`
Entity keys: `['id' => 'tid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type']`

| Field | Widget/Type | Notes |
|-------|-------------|-------|
| tid | (auto) | Auto-increment PK |
| uuid | (auto) | |
| title | (entity key label) | Teaching title |
| type | (entity key bundle) | Teaching type reference |
| slug | text | URL slug |
| content | richtext | Teaching content |
| cultural_group_id | entity_reference (→ cultural_group) | Associated cultural group |
| tags | entity_reference (→ taxonomy_term) | Teaching tags vocabulary |
| media_id | entity_reference (→ media) | |
| status | boolean (default: 1) | |
| created_at | timestamp | |
| updated_at | timestamp | |

**teaching_type** (`ConfigEntityBase`) — Primary key: `type`
Entity keys: `['id' => 'type', 'label' => 'name']`

**cultural_collection** (`ContentEntityBase`) — Primary key: `ccid`
Entity keys: `['id' => 'ccid', 'uuid' => 'uuid', 'label' => 'title']`

| Field | Widget/Type | Notes |
|-------|-------------|-------|
| ccid | (auto) | Auto-increment PK |
| uuid | (auto) | |
| title | (entity key label) | Collection name |
| slug | text | URL slug |
| description | richtext | Cultural context |
| gallery | entity_reference (→ taxonomy_term) | Gallery vocabulary |
| source_url | url | Original URL from ojibwe.lib.umn.edu |
| source_attribution | text | Attribution text |
| media_id | entity_reference (→ media) | Primary image |
| status | boolean (default: 1) | |
| created_at | timestamp | |
| updated_at | timestamp | |

### Language Domain

**dictionary_entry** (`ContentEntityBase`) — Primary key: `deid`
Entity keys: `['id' => 'deid', 'uuid' => 'uuid', 'label' => 'word']`
Default values: `language_code => 'oj'`

| Field | Widget/Type | Notes |
|-------|-------------|-------|
| deid | (auto) | Auto-increment PK |
| uuid | (auto) | |
| word | (entity key label) | Ojibwe word |
| slug | text | URL slug |
| definition | text | English definition |
| part_of_speech | text | Code: ni, na, vai, vti, vta, vii, nad, nid |
| stem | text | Root stem (e.g., /jiimaan-/) |
| inflected_forms | textarea | JSON array of form/label pairs |
| language_code | text (default: oj) | ISO code |
| source_url | url | Source URL |
| status | boolean (default: 1) | |
| created_at | timestamp | |
| updated_at | timestamp | |

**example_sentence** (`ContentEntityBase`) — Primary key: `esid`
Entity keys: `['id' => 'esid', 'uuid' => 'uuid', 'label' => 'ojibwe_text']`
Default values: `language_code => 'oj'`

| Field | Widget/Type | Notes |
|-------|-------------|-------|
| esid | (auto) | |
| uuid | (auto) | |
| ojibwe_text | (entity key label) | Ojibwe sentence text |
| english_text | text | English translation |
| dictionary_entry_id | entity_reference (→ dictionary_entry) | Parent entry |
| speaker_id | entity_reference (→ speaker) | Speaker |
| audio_url | url | Audio recording URL |
| language_code | text (default: oj) | |
| status | boolean (default: 1) | |
| created_at | timestamp | |
| updated_at | timestamp | |

**word_part** (`ContentEntityBase`) — Primary key: `wpid`
Entity keys: `['id' => 'wpid', 'uuid' => 'uuid', 'label' => 'form']`

| Field | Widget/Type | Notes |
|-------|-------------|-------|
| wpid | (auto) | |
| uuid | (auto) | |
| form | (entity key label) | Morpheme text |
| slug | text | URL slug |
| type | text | Position: initial, medial, or final |
| definition | text | Meaning of this morpheme |
| source_url | url | |
| status | boolean (default: 1) | |
| created_at | timestamp | |
| updated_at | timestamp | |

**speaker** (`ContentEntityBase`) — Primary key: `sid`
Entity keys: `['id' => 'sid', 'uuid' => 'uuid', 'label' => 'name']`

| Field | Widget/Type | Notes |
|-------|-------------|-------|
| sid | (auto) | |
| uuid | (auto) | |
| name | (entity key label) | Speaker name |
| slug | text | URL slug |
| code | text | Speaker identifier code |
| bio | richtext | Biography |
| media_id | entity_reference (→ media) | Photo |
| status | boolean (default: 1) | |
| created_at | timestamp | |
| updated_at | timestamp | |

## Access Control

All 6 policies use identical logic:

| Actor | Operation | Result |
|-------|-----------|--------|
| User with `administer content` | any | `AccessResult::allowed()` |
| User with `access content` | `view` + `status == 1` | `AccessResult::allowed()` |
| User with `access content` | `view` + `status != 1` | `AccessResult::neutral()` |
| Any | `create`, `update`, `delete` (without admin perm) | `AccessResult::neutral()` |

Policy-to-type mapping:
- `EventAccessPolicy` → `event`
- `GroupAccessPolicy` → `group`
- `CulturalGroupAccessPolicy` → `cultural_group`
- `TeachingAccessPolicy` → `teaching`
- `CulturalCollectionAccessPolicy` → `cultural_collection`
- `LanguageAccessPolicy` → `dictionary_entry`, `example_sentence`, `word_part`, `speaker`

Config entity types (`event_type`, `group_type`, `teaching_type`) inherit framework default access.

## Seed Data

### Taxonomy Vocabularies

**gallery** (vid: `gallery`)
Terms: fishing, sugaring, lodges, hidework, ricing, wintertravel
Structure: `['vocabulary' => [...], 'terms' => [['name' => '...', 'vid' => 'gallery'], ...]]`

**teaching_tags** (vid: `teaching_tags`)
Terms: ceremony, governance, land, kinship, language, history
Same structure as gallery.

### Config Types

**Event Types:** `[{type, name, description}]` — powwow, gathering, ceremony
**Group Types:** `[{type, name}]` — online, offline, advocacy (no description)
**Teaching Types:** `[{type, name}]` — culture, history, language (no description)

## Entity Relationships

```
event ──type──→ event_type
group ──type──→ group_type
cultural_group ──parent_id──→ cultural_group (self-referential tree)
teaching ──type──→ teaching_type
teaching ──cultural_group_id──→ cultural_group
teaching ──tags──→ taxonomy_term (teaching_tags vocabulary)
cultural_collection ──gallery──→ taxonomy_term (gallery vocabulary)
example_sentence ──dictionary_entry_id──→ dictionary_entry
example_sentence ──speaker_id──→ speaker
event, group, cultural_group, teaching, cultural_collection, speaker ──media_id──→ media
```

## Service Provider Registration

All providers use `$this->entityType(new EntityType(...))`:

```php
$this->entityType(new EntityType(
    id: 'event',
    label: 'Event',
    entityClass: Event::class,
    entityKeys: ['id' => 'eid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type'],
    fieldDefinitions: [
        'slug' => FieldDefinition::create('string')->setWidget('text')->setLabel('URL Slug'),
        // ...
    ],
));
```

## Testing Infrastructure

**Test suites** (phpunit.xml.dist):
- `MinooUnit` → `tests/Minoo/Unit/` — 54 tests
- `MinooIntegration` → `tests/Minoo/Integration/` — 4 tests

**Integration test kernel boot:**
```php
putenv('WAASEYAA_DB=:memory:');
$manifestCache = $projectRoot . '/storage/framework/packages.php';
@unlink($manifestCache);
$kernel = new HttpKernel($projectRoot);
(new \ReflectionMethod(AbstractKernel::class, 'boot'))->invoke($kernel);
$etm = $kernel->getEntityTypeManager();
```

Verifies: 3 built-in types (node, taxonomy_term, user) + 12 Minoo types = 15 total.
