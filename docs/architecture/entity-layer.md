# Entity layer: domain folders

Minoo content and config entity classes live under **`src/Entity/{Domain}/`**. The domain segment matches **`src/Access/{Domain}/`**, **`src/Domain/{Domain}/`** (bounded contexts), and the same convention used for **`src/Http/Controller/{Domain}/`** (see `docs/architecture/http-layer.md`).

## Grouping rule

**One PSR-4 namespace segment per domain:** `App\Entity\{Domain}\{ClassName}` — for example `App\Entity\Community\Community` (the `Community` entity in the `Community` domain; the repeated segment is normal PHP naming, not a nested folder mistake).

| Directory | Namespace | Examples |
|-----------|-----------|----------|
| `Community/` | `App\Entity\Community` | `Community`, `Contributor`, `Volunteer`, `Leader`, `ResourcePerson` |
| `Events/` | `App\Entity\Events` | `Event`, `EventType` |
| `Groups/` | `App\Entity\Groups` | `Group`, `GroupType`, `CulturalGroup` |
| `Teachings/` | `App\Entity\Teachings` | `Teaching`, `TeachingType`, `CulturalCollection` |
| `Language/` | `App\Entity\Language` | `DictionaryEntry`, `ExampleSentence`, `WordPart`, `Speaker`, `DialectRegion` |
| `Games/` | `App\Entity\Games` | `GameSession`, `CrosswordPuzzle`, `DailyChallenge` |
| `Feed/` | `App\Entity\Feed` | `Post` |
| `OralHistory/` | `App\Entity\OralHistory` | `OralHistory`, `OralHistoryType`, `OralHistoryCollection` |
| `Newsletter/` | `App\Entity\Newsletter` | `NewsletterEdition`, `NewsletterItem`, `NewsletterSubmission` |
| `ElderSupport/` | `App\Entity\ElderSupport` | `ElderSupportRequest` |
| `Ingestion/` | `App\Entity\Ingestion` | `IngestLog` |
| `Editorial/` | `App\Entity\Editorial` | `FeaturedItem` |

**Adding an entity:** create the PHP file under the domain directory above, set `namespace App\Entity\{Domain};`, register the `EntityType` in the appropriate `src/Provider/Entity/*Provider.php` (merged by `MinooEntityStackProvider`), add or extend an access policy under `src/Access/{Domain}/`, and add a unit test under `tests/App/Unit/Entity/{Domain}/`.

## Tests

Entity unit tests mirror the same folder and namespace: `tests/App/Unit/Entity/{Domain}/` with `namespace App\Tests\Unit\Entity\{Domain};`.
