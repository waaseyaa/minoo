# Minoo Entity Model Specification

## File Map

| File | Purpose |
|------|---------|
| `src/Entity/Event.php` | Event entity — community gatherings, powwows, ceremonies |
| `src/Entity/EventType.php` | Event type config entity (powwow, gathering, ceremony) |
| `src/Entity/Group.php` | Community group entity |
| `src/Entity/GroupType.php` | Group type config entity (online, offline, advocacy) |
| `src/Entity/CulturalGroup.php` | Hierarchical cultural group with `parent_id` self-reference |
| `src/Entity/Teaching.php` | Teaching entity with tags taxonomy reference |
| `src/Entity/TeachingType.php` | Teaching type config entity (culture, history, language) |
| `src/Entity/CulturalCollection.php` | Cultural collection with gallery taxonomy + media |
| `src/Entity/DictionaryEntry.php` | Ojibwe dictionary entry with linguistic metadata |
| `src/Entity/ExampleSentence.php` | Example sentence referencing dictionary entry + speaker |
| `src/Entity/WordPart.php` | Word component (initial, medial, final morpheme) |
| `src/Entity/Speaker.php` | Language speaker profile with community and dialect |
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

## Entity Type Definitions

### Events Domain

**event** — Primary key: `eid`
| Field | Type | Widget | Notes |
|-------|------|--------|-------|
| eid | integer | hidden | Auto-increment PK |
| uuid | string (uuid) | hidden | UUID v4 |
| title | string | text | Event name |
| type | string | hidden | Event type reference (config entity) |
| slug | string | text | URL-safe identifier |
| description | string | richtext | Event details |
| location | string | text | Venue or area |
| starts_at | string (date-time) | datetime | Event start |
| ends_at | string (date-time) | datetime | Event end |
| media_id | string | entity_autocomplete | Primary image (→ media) |
| status | boolean | boolean | Published (default: true) |
| created_at | string (date-time) | datetime | Created timestamp |
| updated_at | string (date-time) | datetime | Updated timestamp |

**event_type** — Primary key: `etid`
| Field | Type | Widget |
|-------|------|--------|
| etid | string | hidden |
| title | string | text |

### Groups Domain

**group** — Primary key: `gid`
| Field | Type | Widget | Notes |
|-------|------|--------|-------|
| gid | integer | hidden | Auto-increment PK |
| uuid | string (uuid) | hidden | |
| title | string | text | Group name |
| type | string | hidden | Group type reference |
| slug | string | text | URL slug |
| description | string | richtext | Group description |
| website | string | url | Group website |
| contact_email | string | text | Contact email |
| media_id | string | entity_autocomplete | Group image (→ media) |
| status | boolean | boolean | Published |
| created_at | string (date-time) | datetime | |
| updated_at | string (date-time) | datetime | |

**group_type** — Primary key: `gtid`
| Field | Type | Widget |
|-------|------|--------|
| gtid | string | hidden |
| title | string | text |

**cultural_group** — Primary key: `cgid`
| Field | Type | Widget | Notes |
|-------|------|--------|-------|
| cgid | integer | hidden | Auto-increment PK |
| uuid | string (uuid) | hidden | |
| title | string | text | Cultural group name |
| slug | string | text | URL slug |
| description | string | richtext | |
| parent_id | string | entity_autocomplete | Self-reference for hierarchy (→ cultural_group) |
| region | string | text | Geographic region |
| media_id | string | entity_autocomplete | (→ media) |
| status | boolean | boolean | |
| created_at | string (date-time) | datetime | |
| updated_at | string (date-time) | datetime | |

### Teachings Domain

**teaching** — Primary key: `tid`
| Field | Type | Widget | Notes |
|-------|------|--------|-------|
| tid | integer | hidden | Auto-increment PK |
| uuid | string (uuid) | hidden | |
| title | string | text | Teaching title |
| type | string | hidden | Teaching type reference |
| slug | string | text | URL slug |
| body | string | richtext | Teaching content |
| tags | string | entity_autocomplete | Tags (→ taxonomy_term, teaching_tags vocabulary) |
| source_attribution | string | text | Credit/source |
| media_id | string | entity_autocomplete | (→ media) |
| status | boolean | boolean | |
| created_at | string (date-time) | datetime | |
| updated_at | string (date-time) | datetime | |

**teaching_type** — Primary key: `ttid`
| Field | Type | Widget |
|-------|------|--------|
| ttid | string | hidden |
| title | string | text |

**cultural_collection** — Primary key: `ccid`
| Field | Type | Widget | Notes |
|-------|------|--------|-------|
| ccid | integer | hidden | Auto-increment PK |
| uuid | string (uuid) | hidden | |
| title | string | text | Collection name |
| slug | string | text | URL slug |
| description | string | richtext | Cultural context |
| gallery | string | entity_autocomplete | Gallery category (→ taxonomy_term) |
| source_url | string (uri) | url | Original URL from ojibwe.lib.umn.edu |
| source_attribution | string | text | Attribution text |
| media_id | string | entity_autocomplete | Primary image (→ media) |
| status | boolean | boolean | |
| created_at | string (date-time) | datetime | |
| updated_at | string (date-time) | datetime | |

### Language Domain

**dictionary_entry** — Primary key: `deid`
| Field | Type | Widget | Notes |
|-------|------|--------|-------|
| deid | integer | hidden | Auto-increment PK |
| uuid | string (uuid) | hidden | |
| word | string | text | Ojibwe word (aliased as `title` in label) |
| slug | string | text | URL slug |
| definition | string | text | English definition |
| part_of_speech | string | text | Code: ni, na, vai, vti, vta, vii, nad, nid |
| stem | string | text | Root stem (e.g., /jiimaan-/) |
| inflected_forms | string | textarea | JSON array of form/label pairs |
| language_code | string | text | ISO code, default: `oj` |
| source_url | string (uri) | url | Source URL |
| status | boolean | boolean | |
| created_at | string (date-time) | datetime | |
| updated_at | string (date-time) | datetime | |

**example_sentence** — Primary key: `esid`
| Field | Type | Widget | Notes |
|-------|------|--------|-------|
| esid | integer | hidden | |
| uuid | string (uuid) | hidden | |
| title | string | text | Ojibwe sentence text |
| translation | string | text | English translation |
| dictionary_entry_id | string | entity_autocomplete | (→ dictionary_entry) |
| speaker_id | string | entity_autocomplete | (→ speaker) |
| audio_url | string (uri) | url | Audio recording URL |
| language_code | string | text | Default: `oj` |
| status | boolean | boolean | |
| created_at | string (date-time) | datetime | |
| updated_at | string (date-time) | datetime | |

**word_part** — Primary key: `wpid`
| Field | Type | Widget | Notes |
|-------|------|--------|-------|
| wpid | integer | hidden | |
| uuid | string (uuid) | hidden | |
| title | string | text | Morpheme text |
| slug | string | text | URL slug |
| type | string | text | Position: initial, medial, or final |
| definition | string | text | Meaning of this morpheme |
| source_url | string (uri) | url | |
| status | boolean | boolean | |
| created_at | string (date-time) | datetime | |
| updated_at | string (date-time) | datetime | |

**speaker** — Primary key: `spid`
| Field | Type | Widget | Notes |
|-------|------|--------|-------|
| spid | integer | hidden | |
| uuid | string (uuid) | hidden | |
| title | string | text | Speaker name |
| slug | string | text | URL slug |
| community | string | text | Home community |
| dialect | string | text | Regional dialect |
| bio | string | richtext | Biography |
| media_id | string | entity_autocomplete | Photo (→ media) |
| source_url | string (uri) | url | Source page URL |
| status | boolean | boolean | |
| created_at | string (date-time) | datetime | |
| updated_at | string (date-time) | datetime | |

## Access Control

All policies use identical logic:

| Actor | Operation | Result |
|-------|-----------|--------|
| Authenticated + `administer content` | any | `AccessResult::allowed()` |
| Anonymous | `view` + `status == 1` | `AccessResult::allowed()` |
| Anonymous | `view` + `status != 1` | `AccessResult::neutral()` |
| Anonymous | `create`, `update`, `delete` | `AccessResult::neutral()` |

Policy-to-type mapping:
- `EventAccessPolicy` → `event`
- `GroupAccessPolicy` → `group`
- `CulturalGroupAccessPolicy` → `cultural_group`
- `TeachingAccessPolicy` → `teaching`
- `CulturalCollectionAccessPolicy` → `cultural_collection`
- `LanguageAccessPolicy` → `dictionary_entry`, `example_sentence`, `word_part`, `speaker`

Config entity types (`event_type`, `group_type`, `teaching_type`) inherit framework default access — no custom policy needed.

## Seed Data

### Taxonomy Vocabularies

**gallery** (vid: `gallery`)
| tid | name | description |
|-----|------|-------------|
| fishing | Fishing | Fishing traditions and practices |
| sugaring | Sugaring | Maple sugar harvesting |
| lodges | Lodges | Lodge construction and ceremonies |
| hidework | Hidework | Hide preparation and tanning |
| ricing | Wild Ricing | Wild rice harvesting |
| wintertravel | Winter Travel | Winter travel methods and snowshoes |

**teaching_tags** (vid: `teaching_tags`)
| tid | name | description |
|-----|------|-------------|
| ceremony | Ceremony | Ceremonial practices and protocols |
| governance | Governance | Traditional governance systems |
| land | Land | Land-based knowledge and stewardship |
| kinship | Kinship | Kinship systems and clan structures |
| language | Language | Language preservation and revitalization |
| history | History | Oral history and historical events |

### Config Types

**Event Types:** powwow, gathering, ceremony
**Group Types:** online, offline, advocacy
**Teaching Types:** culture, history, language

## Entity Relationships Diagram

```
event ──type──→ event_type
group ──type──→ group_type
cultural_group ──parent_id──→ cultural_group (self-referential tree)
teaching ──type──→ teaching_type
teaching ──tags──→ taxonomy_term (teaching_tags vocabulary)
cultural_collection ──gallery──→ taxonomy_term (gallery vocabulary)
example_sentence ──dictionary_entry_id──→ dictionary_entry
example_sentence ──speaker_id──→ speaker
event, group, cultural_group, teaching, cultural_collection, speaker ──media_id──→ media
```

## Testing Infrastructure

**Test suites** (phpunit.xml.dist):
- `MinooUnit` → `tests/Minoo/Unit/` — 38 tests
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

**CRUD round-trip test pattern:**
```php
$storage = $etm->getStorage('dictionary_entry');
$entry = $storage->create(['word' => 'makwa', 'definition' => 'bear']);
$entry->enforceIsNew();
$storage->save($entry);
$loaded = $storage->load($entry->id());
$this->assertSame('makwa', $loaded->get('word'));
```
