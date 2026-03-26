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
| `src/Entity/MessageThread.php` | Message thread entity |
| `src/Entity/ThreadParticipant.php` | Thread participant entity |
| `src/Entity/ThreadMessage.php` | Thread message entity |
| `src/Provider/MessagingServiceProvider.php` | Registers message thread entities + routes |
| `src/Access/MessagingAccessPolicy.php` | Access for messaging thread entities |
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

### Messaging Domain

**message_thread** (`ContentEntityBase`) — Primary key: `mtid`
Entity keys: `['id' => 'mtid', 'uuid' => 'uuid', 'label' => 'title']`

| Field | Widget/Type | Notes |
|-------|-------------|-------|
| mtid | (auto) | Auto-increment PK |
| uuid | (auto) | UUID v4 |
| title | text | Optional thread title |
| created_by | integer | Thread creator (account uid) |
| created_at | timestamp | Created |
| updated_at | timestamp | Updated on new messages |

**thread_participant** (`ContentEntityBase`) — Primary key: `tpid`
Entity keys: `['id' => 'tpid', 'uuid' => 'uuid', 'label' => 'role']`

| Field | Widget/Type | Notes |
|-------|-------------|-------|
| tpid | (auto) | Auto-increment PK |
| uuid | (auto) | UUID v4 |
| thread_id | integer | Parent thread (message_thread) id |
| user_id | integer | Participant account uid |
| thread_creator_id | integer | Denormalized `message_thread.created_by` for access checks |
| role | string | `owner` or `member` |
| joined_at | timestamp | When participant joined |
| last_read_at | timestamp | Optional read tracking |

**thread_message** (`ContentEntityBase`) — Primary key: `tmid`
Entity keys: `['id' => 'tmid', 'uuid' => 'uuid', 'label' => 'body']`

| Field | Widget/Type | Notes |
|-------|-------------|-------|
| tmid | (auto) | Auto-increment PK |
| uuid | (auto) | UUID v4 |
| thread_id | integer | Parent thread (message_thread) id |
| sender_id | integer | Sending account uid |
| body | text_long | Message body |
| status | boolean | Optional moderation state |
| created_at | timestamp | Message created |

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
- `MessagingAccessPolicy` → `message_thread`, `thread_participant`, `thread_message`

Config entity types (`event_type`, `group_type`, `teaching_type`) inherit framework default access.

## Consent Field Scope

Two consent fields — `consent_public` and `consent_ai_training` — exist on entity types that hold individually contributed cultural knowledge. Community-level public information intentionally omits these fields.

| Entity Type | `consent_public` | `consent_ai_training` | Rationale |
|-------------|:-:|:-:|-----------|
| `teaching` | Yes (default: 1) | Yes (default: 0) | Individually contributed cultural knowledge requires explicit consent |
| `dictionary_entry` | Yes (default: 1) | Yes (default: 0) | Language data contributed by individual speakers and scholars |
| `event` | No | No | Community-level public information (gatherings, powwows, ceremonies) |
| `group` | No | No | Community-level public information (organizations, advocacy groups) |
| `community` | No | No | Public First Nations registry data from CIRNAC |
| `cultural_group` | No | No | Hierarchical cultural classification, not individually contributed |
| `cultural_collection` | No | No | Curated gallery collections, not individually contributed |
| `example_sentence` | No | No | Inherits consent scope from parent `dictionary_entry` |
| `word_part` | No | No | Linguistic morpheme data, not individually contributed |
| `speaker` | No | No | Speaker profile metadata, not cultural content itself |

**Design rationale:** Consent fields gate two distinct concerns: (1) whether content appears on public pages (`consent_public`, checked via `->condition('consent_public', 1)` in controllers), and (2) whether content may be used for AI/ML training (`consent_ai_training`, default off). These controls apply to content where an individual Knowledge Keeper or contributor has a personal stake in how their knowledge is shared. Community-level entities like events and groups represent publicly announced information that does not require per-record consent gating.

**Future extension:** If consent controls become necessary for additional entity types, follow the pattern established in `TeachingServiceProvider` and `LanguageServiceProvider`: add `consent_public` (boolean, default 1) and `consent_ai_training` (boolean, default 0) to the field definitions, and add `->condition('consent_public', 1)` to the corresponding controller queries.

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
