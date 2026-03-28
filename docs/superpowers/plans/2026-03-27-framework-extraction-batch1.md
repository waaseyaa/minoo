# Framework Extraction — Batch 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extract 6 zero-coupling components from Minoo into the Waaseyaa framework, tag a new framework release, then swap Minoo imports.

**Architecture:** Each component moves from `Minoo\Support\` (or `Minoo\Service\`) into the appropriate Waaseyaa package with matching tests. A single framework PR covers all 6, tagged as one release. A single Minoo PR swaps imports and deletes the old files.

**Tech Stack:** PHP 8.4, PHPUnit 10.5, Waaseyaa framework packages, Composer path repositories

**Repos:**
- Framework: `/home/jones/dev/waaseyaa` (repo: `waaseyaa/framework`)
- Application: `/home/jones/dev/minoo` (repo: `waaseyaa/minoo`)

---

## File Map

### Framework side (waaseyaa/framework)

**New files:**
- `packages/foundation/src/SlugGenerator.php` — static slug utility
- `packages/foundation/tests/Unit/SlugGeneratorTest.php`
- `packages/geo/composer.json` — new package manifest
- `packages/geo/src/GeoDistance.php` — Haversine formula
- `packages/geo/src/GeoServiceProvider.php` — empty provider (establishes package)
- `packages/geo/tests/Unit/GeoDistanceTest.php`
- `packages/mercure/composer.json` — new package manifest
- `packages/mercure/src/MercurePublisher.php` — Mercure hub client
- `packages/mercure/src/MercureServiceProvider.php` — registers singleton from config
- `packages/mercure/tests/Unit/MercurePublisherTest.php`
- `packages/ssr/src/Flash/FlashMessageService.php` — session-backed flash storage
- `packages/ssr/src/Flash/Flash.php` — static facade
- `packages/ssr/src/Twig/FlashTwigExtension.php` — Twig `flash_messages()` function
- `packages/ssr/tests/Unit/Flash/FlashMessageServiceTest.php`
- `packages/ssr/tests/Unit/Flash/FlashTest.php`
- `packages/api/src/JsonResponseTrait.php` — `jsonBody()` + `json()` helpers
- `packages/api/tests/Unit/JsonResponseTraitTest.php`

**Modified files:**
- `packages/media/src/MediaServiceProvider.php` — register `UploadHandler` singleton
- `packages/media/src/UploadHandler.php` — parameterized upload validation + move
- `packages/media/tests/Unit/UploadHandlerTest.php`
- `packages/ssr/src/SsrServiceProvider.php` — wire flash Twig extension in `boot()`

### Minoo side (waaseyaa/minoo)

**Modified files (import swaps):**
- `src/Ingestion/EntityMapper/NcArticleToEventMapper.php` — SlugGenerator
- `src/Ingestion/EntityMapper/NcArticleToTeachingMapper.php` — SlugGenerator
- `src/Ingestion/EntityMapper/DictionaryEntryMapper.php` — SlugGenerator
- `src/Ingestion/EntityMapper/CulturalCollectionMapper.php` — SlugGenerator
- `src/Ingestion/EntityMapper/SpeakerMapper.php` — SlugGenerator
- `src/Ingestion/EntityMapper/WordPartMapper.php` — SlugGenerator
- `src/Feed/FeedAssembler.php` — GeoDistance
- `src/Feed/Scoring/AffinityCalculator.php` — GeoDistance
- `src/Feed/FeedItemFactory.php` — GeoDistance
- `src/Controller/CommunityController.php` — GeoDistance
- `src/Controller/FeedController.php` — GeoDistance
- `src/Domain/Geo/Service/VolunteerRanker.php` — GeoDistance
- `src/Domain/Geo/Service/CommunityFinder.php` — GeoDistance
- `src/Provider/MessagingServiceProvider.php` — MercurePublisher
- `src/Controller/MessagingController.php` — MercurePublisher, remove local `jsonBody()`/`json()`, use trait
- `src/Provider/EngagementServiceProvider.php` — UploadService → UploadHandler
- `src/Controller/EngagementController.php` — UploadService → UploadHandler, remove local `jsonBody()`/`json()`, use trait
- `src/Controller/AuthController.php` — Flash
- `src/Controller/RoleManagementController.php` — Flash
- `src/Controller/ElderSupportWorkflowController.php` — Flash
- `src/Controller/ElderSupportController.php` — Flash
- `src/Controller/AccountHomeController.php` — Flash
- `src/Controller/VolunteerDashboardController.php` — Flash
- `src/Controller/VolunteerController.php` — Flash
- `src/Controller/CoordinatorDashboardController.php` — Flash
- `src/Controller/ShkodaController.php` — uses GameControllerTrait (keep, but trait uses framework JSON helpers)
- `src/Controller/CrosswordController.php` — same
- `src/Controller/MatcherController.php` — same
- `src/Controller/BlockController.php` — remove local `jsonBody()`/`json()`, use trait
- `src/Controller/IngestionApiController.php` — remove local `jsonBody()`/`json()`, use trait
- `src/Controller/GameControllerTrait.php` — remove `jsonBody()`/`json()`, add `use JsonResponseTrait`
- `src/Provider/FlashServiceProvider.php` — update imports to framework Flash classes
- `tests/Minoo/Unit/Ingest/SlugGeneratorTest.php` — update import or delete (covered by framework test)
- `tests/Minoo/Unit/Geo/GeoDistanceTest.php` — update import or delete
- `tests/Minoo/Unit/Support/MercurePublisherTest.php` — update import or delete
- `tests/Minoo/Unit/Support/UploadServiceTest.php` — update import or delete
- `tests/Minoo/Unit/Support/FlashTest.php` — update import or delete
- `tests/Minoo/Unit/Twig/FlashTwigExtensionTest.php` — update imports
- `tests/Minoo/Unit/Service/FlashMessageServiceTest.php` — update import or delete
- `tests/Minoo/Unit/Controller/EngagementControllerTest.php` — UploadService → UploadHandler

**Deleted files:**
- `src/Support/SlugGenerator.php`
- `src/Support/GeoDistance.php`
- `src/Support/MercurePublisher.php`
- `src/Support/UploadService.php`
- `src/Support/Flash.php`
- `src/Service/FlashMessageService.php`
- `src/Twig/FlashTwigExtension.php`

---

## Task 1: SlugGenerator → waaseyaa/foundation

**Files:**
- Create: `packages/foundation/src/SlugGenerator.php`
- Create: `packages/foundation/tests/Unit/SlugGeneratorTest.php`

- [ ] **Step 1: Write the failing test**

Create `packages/foundation/tests/Unit/SlugGeneratorTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\SlugGenerator;

#[CoversClass(SlugGenerator::class)]
final class SlugGeneratorTest extends TestCase
{
    #[Test]
    public function generates_slug_from_simple_string(): void
    {
        $this->assertSame('hello-world', SlugGenerator::generate('Hello World'));
    }

    #[Test]
    public function strips_special_characters(): void
    {
        $this->assertSame('test-123', SlugGenerator::generate('Test @#$ 123'));
    }

    #[Test]
    public function trims_leading_and_trailing_hyphens(): void
    {
        $this->assertSame('hello', SlugGenerator::generate('---hello---'));
    }

    #[Test]
    public function collapses_multiple_hyphens(): void
    {
        $this->assertSame('a-b', SlugGenerator::generate('a   b'));
    }

    #[Test]
    public function handles_empty_string(): void
    {
        $this->assertSame('', SlugGenerator::generate(''));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/foundation/tests/Unit/SlugGeneratorTest.php`
Expected: FAIL — class `Waaseyaa\Foundation\SlugGenerator` not found

- [ ] **Step 3: Write the implementation**

Create `packages/foundation/src/SlugGenerator.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation;

final class SlugGenerator
{
    public static function generate(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/foundation/tests/Unit/SlugGeneratorTest.php`
Expected: OK (5 tests, 5 assertions)

- [ ] **Step 5: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/foundation/src/SlugGenerator.php packages/foundation/tests/Unit/SlugGeneratorTest.php
git commit -m "feat(foundation): extract SlugGenerator from Minoo

Refs: #692"
```

---

## Task 2: GeoDistance → new waaseyaa/geo package

**Files:**
- Create: `packages/geo/composer.json`
- Create: `packages/geo/src/GeoDistance.php`
- Create: `packages/geo/src/GeoServiceProvider.php`
- Create: `packages/geo/tests/Unit/GeoDistanceTest.php`

- [ ] **Step 1: Create package scaffold**

Create `packages/geo/composer.json`:

```json
{
    "name": "waaseyaa/geo",
    "description": "Geospatial utilities for Waaseyaa: distance calculations, coordinate helpers",
    "type": "library",
    "license": "GPL-2.0-or-later",
    "repositories": [
        {
            "type": "path",
            "url": "../foundation"
        }
    ],
    "require": {
        "php": ">=8.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "autoload": {
        "psr-4": {
            "Waaseyaa\\Geo\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Waaseyaa\\Geo\\Tests\\": "tests/"
        }
    },
    "extra": {
        "waaseyaa": {
            "providers": [
                "Waaseyaa\\Geo\\GeoServiceProvider"
            ]
        },
        "branch-alias": {
            "dev-main": "0.1.x-dev"
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

- [ ] **Step 2: Write the failing test**

Create `packages/geo/tests/Unit/GeoDistanceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Geo\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Geo\GeoDistance;

#[CoversClass(GeoDistance::class)]
final class GeoDistanceTest extends TestCase
{
    #[Test]
    public function calculates_distance_between_two_points(): void
    {
        // Toronto (43.6532, -79.3832) to Ottawa (45.4215, -75.6972)
        $distance = GeoDistance::haversine(43.6532, -79.3832, 45.4215, -75.6972);
        $this->assertEqualsWithDelta(353.0, $distance, 5.0);
    }

    #[Test]
    public function same_point_returns_zero(): void
    {
        $this->assertSame(0.0, GeoDistance::haversine(43.0, -79.0, 43.0, -79.0));
    }

    #[Test]
    public function handles_antipodal_points(): void
    {
        // North pole to south pole ≈ 20015 km
        $distance = GeoDistance::haversine(90.0, 0.0, -90.0, 0.0);
        $this->assertEqualsWithDelta(20015.0, $distance, 100.0);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/geo/tests/Unit/GeoDistanceTest.php`
Expected: FAIL — class `Waaseyaa\Geo\GeoDistance` not found

- [ ] **Step 4: Write GeoDistance**

Create `packages/geo/src/GeoDistance.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Geo;

final class GeoDistance
{
    private const float EARTH_RADIUS_KM = 6371.0;

    /**
     * Calculate the great-circle distance between two points using the Haversine formula.
     *
     * @return float Distance in kilometres
     */
    public static function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }
}
```

- [ ] **Step 5: Write GeoServiceProvider**

Create `packages/geo/src/GeoServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Geo;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class GeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No bindings yet — package provides static utilities.
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/geo/tests/Unit/GeoDistanceTest.php`
Expected: OK (3 tests, 3 assertions)

- [ ] **Step 7: Register package in root composer.json**

Add `waaseyaa/geo` as a path repository in the root `composer.json` and add it to the `require` section. Then run `composer dump-autoload`.

- [ ] **Step 8: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/geo/
git commit -m "feat(geo): new package with GeoDistance Haversine utility

Refs: #693"
```

---

## Task 3: MercurePublisher → new waaseyaa/mercure package

**Files:**
- Create: `packages/mercure/composer.json`
- Create: `packages/mercure/src/MercurePublisher.php`
- Create: `packages/mercure/src/MercureServiceProvider.php`
- Create: `packages/mercure/tests/Unit/MercurePublisherTest.php`

- [ ] **Step 1: Create package scaffold**

Create `packages/mercure/composer.json`:

```json
{
    "name": "waaseyaa/mercure",
    "description": "Mercure hub publisher for real-time SSE push in Waaseyaa",
    "type": "library",
    "license": "GPL-2.0-or-later",
    "repositories": [
        {
            "type": "path",
            "url": "../foundation"
        }
    ],
    "require": {
        "php": ">=8.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "autoload": {
        "psr-4": {
            "Waaseyaa\\Mercure\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Waaseyaa\\Mercure\\Tests\\": "tests/"
        }
    },
    "extra": {
        "waaseyaa": {
            "providers": [
                "Waaseyaa\\Mercure\\MercureServiceProvider"
            ]
        },
        "branch-alias": {
            "dev-main": "0.1.x-dev"
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

- [ ] **Step 2: Write the failing test**

Create `packages/mercure/tests/Unit/MercurePublisherTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Mercure\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Mercure\MercurePublisher;

#[CoversClass(MercurePublisher::class)]
final class MercurePublisherTest extends TestCase
{
    #[Test]
    public function is_not_configured_with_empty_hub_url(): void
    {
        $publisher = new MercurePublisher('', 'secret');
        $this->assertFalse($publisher->isConfigured());
    }

    #[Test]
    public function is_not_configured_with_empty_secret(): void
    {
        $publisher = new MercurePublisher('https://hub.example.com', '');
        $this->assertFalse($publisher->isConfigured());
    }

    #[Test]
    public function is_configured_with_both_values(): void
    {
        $publisher = new MercurePublisher('https://hub.example.com', 'secret');
        $this->assertTrue($publisher->isConfigured());
    }

    #[Test]
    public function publish_returns_false_when_not_configured(): void
    {
        $publisher = new MercurePublisher('', '');
        $this->assertFalse($publisher->publish('topic', ['data' => 'test']));
    }

    #[Test]
    public function generates_valid_jwt_structure(): void
    {
        $publisher = new MercurePublisher('https://hub.example.com', 'test-secret');
        $jwt = $publisher->generateJwt();

        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts, 'JWT must have 3 parts');

        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
        $this->assertSame('HS256', $header['alg']);
        $this->assertSame('JWT', $header['typ']);

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $this->assertSame(['publish' => ['*']], $payload['mercure']);
    }

    #[Test]
    public function builds_correct_post_body(): void
    {
        $publisher = new MercurePublisher('https://hub.example.com', 'secret');
        $body = $publisher->buildPostBody('my-topic', ['key' => 'value']);

        parse_str($body, $parsed);
        $this->assertSame('my-topic', $parsed['topic']);
        $this->assertSame('{"key":"value"}', $parsed['data']);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/mercure/tests/Unit/MercurePublisherTest.php`
Expected: FAIL — class `Waaseyaa\Mercure\MercurePublisher` not found

- [ ] **Step 4: Write MercurePublisher**

Create `packages/mercure/src/MercurePublisher.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Mercure;

final class MercurePublisher
{
    public function __construct(
        private readonly string $hubUrl,
        private readonly string $jwtSecret,
    ) {}

    public function publish(string $topic, array $data): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $ch = curl_init($this->hubUrl);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $this->buildPostBody($topic, $data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->generateJwt(),
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $result !== false && $httpCode >= 200 && $httpCode < 300;
    }

    public function isConfigured(): bool
    {
        return $this->hubUrl !== '' && $this->jwtSecret !== '';
    }

    public function generateJwt(): string
    {
        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = self::base64UrlEncode(json_encode(['mercure' => ['publish' => ['*']]], JSON_THROW_ON_ERROR));
        $signature = self::base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $this->jwtSecret, true));

        return "{$header}.{$payload}.{$signature}";
    }

    public function buildPostBody(string $topic, array $data): string
    {
        return http_build_query([
            'topic' => $topic,
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
        ]);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
```

- [ ] **Step 5: Write MercureServiceProvider**

Create `packages/mercure/src/MercureServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Mercure;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class MercureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(MercurePublisher::class, fn () => new MercurePublisher(
            hubUrl: $this->config['mercure']['hub_url'] ?? '',
            jwtSecret: $this->config['mercure']['jwt_secret'] ?? '',
        ));
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/mercure/tests/Unit/MercurePublisherTest.php`
Expected: OK (6 tests, 8 assertions)

- [ ] **Step 7: Register package in root composer.json**

Add `waaseyaa/mercure` as a path repository and require dependency. Run `composer dump-autoload`.

- [ ] **Step 8: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/mercure/
git commit -m "feat(mercure): new package with MercurePublisher for real-time SSE

Refs: #694"
```

---

## Task 4: UploadHandler → waaseyaa/media

**Files:**
- Create: `packages/media/src/UploadHandler.php`
- Create: `packages/media/tests/Unit/UploadHandlerTest.php`
- Modify: `packages/media/src/MediaServiceProvider.php`

- [ ] **Step 1: Write the failing test**

Create `packages/media/tests/Unit/UploadHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Media\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Media\UploadHandler;

#[CoversClass(UploadHandler::class)]
final class UploadHandlerTest extends TestCase
{
    #[Test]
    public function validates_successful_upload(): void
    {
        $handler = new UploadHandler(sys_get_temp_dir());
        $file = ['error' => UPLOAD_ERR_OK, 'size' => 1024, 'type' => 'image/jpeg'];
        $this->assertSame([], $handler->validate($file));
    }

    #[Test]
    public function rejects_upload_error(): void
    {
        $handler = new UploadHandler(sys_get_temp_dir());
        $file = ['error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'type' => ''];
        $errors = $handler->validate($file);
        $this->assertContains('Upload failed.', $errors);
    }

    #[Test]
    public function rejects_oversized_file(): void
    {
        $handler = new UploadHandler(sys_get_temp_dir());
        $file = ['error' => UPLOAD_ERR_OK, 'size' => 6_000_000, 'type' => 'image/jpeg'];
        $errors = $handler->validate($file);
        $this->assertNotEmpty($errors);
    }

    #[Test]
    public function rejects_disallowed_mime_type(): void
    {
        $handler = new UploadHandler(sys_get_temp_dir());
        $file = ['error' => UPLOAD_ERR_OK, 'size' => 1024, 'type' => 'application/pdf'];
        $errors = $handler->validate($file);
        $this->assertNotEmpty($errors);
    }

    #[Test]
    public function custom_allowed_types(): void
    {
        $handler = new UploadHandler(sys_get_temp_dir(), allowedMimeTypes: ['application/pdf']);
        $file = ['error' => UPLOAD_ERR_OK, 'size' => 1024, 'type' => 'application/pdf'];
        $this->assertSame([], $handler->validate($file));
    }

    #[Test]
    public function custom_max_size(): void
    {
        $handler = new UploadHandler(sys_get_temp_dir(), maxSizeBytes: 500);
        $file = ['error' => UPLOAD_ERR_OK, 'size' => 1024, 'type' => 'image/jpeg'];
        $errors = $handler->validate($file);
        $this->assertNotEmpty($errors);
    }

    #[Test]
    public function generates_safe_filename(): void
    {
        $handler = new UploadHandler(sys_get_temp_dir());
        $filename = $handler->generateSafeFilename('My Photo (1).jpeg');
        $this->assertMatchesRegularExpression('/^My_Photo__1__[a-f0-9]{8}\.jpeg$/', $filename);
    }

    #[Test]
    public function generates_fallback_for_empty_name(): void
    {
        $handler = new UploadHandler(sys_get_temp_dir());
        $filename = $handler->generateSafeFilename('!!!.png');
        $this->assertMatchesRegularExpression('/^upload_[a-f0-9]{8}\.png$/', $filename);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/media/tests/Unit/UploadHandlerTest.php`
Expected: FAIL — class `Waaseyaa\Media\UploadHandler` not found

- [ ] **Step 3: Write UploadHandler**

Create `packages/media/src/UploadHandler.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Media;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class UploadHandler
{
    private const int DEFAULT_MAX_SIZE = 5_242_880; // 5MB

    private const array DEFAULT_ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** @param string[] $allowedMimeTypes */
    public function __construct(
        private readonly string $basePath,
        private readonly array $allowedMimeTypes = self::DEFAULT_ALLOWED_TYPES,
        private readonly int $maxSizeBytes = self::DEFAULT_MAX_SIZE,
    ) {}

    /** @return string[] validation errors */
    public function validate(array $file): array
    {
        $errors = [];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed.';

            return $errors;
        }

        if (($file['size'] ?? 0) > $this->maxSizeBytes) {
            $maxMb = round($this->maxSizeBytes / 1_048_576);
            $errors[] = "File must be under {$maxMb}MB.";
        }

        if (!in_array($file['type'] ?? '', $this->allowedMimeTypes, true)) {
            $errors[] = 'File type not allowed.';
        }

        return $errors;
    }

    public function generateSafeFilename(string $original): string
    {
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $name = pathinfo($original, PATHINFO_FILENAME);
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $safe = trim($safe, '_');

        if ($safe === '') {
            $safe = 'upload';
        }

        return $safe . '_' . bin2hex(random_bytes(4)) . '.' . ($ext ?: 'bin');
    }

    /** @return string relative path from basePath */
    public function moveUpload(array $file, string $subdir): string
    {
        $targetDir = $this->basePath . '/' . $subdir;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filename = $this->generateSafeFilename($file['name'] ?? 'upload.bin');
        $targetPath = $targetDir . '/' . $filename;
        move_uploaded_file($file['tmp_name'], $targetPath);

        return $subdir . '/' . $filename;
    }

    public function deleteDirectory(string $subdir): void
    {
        $dir = $this->basePath . '/' . $subdir;

        if (!is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }
}
```

- [ ] **Step 4: Register in MediaServiceProvider**

Add to `packages/media/src/MediaServiceProvider.php` in the `register()` method:

```php
$this->singleton(UploadHandler::class, fn () => new UploadHandler(
    basePath: $this->config['media']['upload_path'] ?? 'public/uploads',
    allowedMimeTypes: $this->config['media']['allowed_types'] ?? UploadHandler::DEFAULT_ALLOWED_TYPES,
    maxSizeBytes: $this->config['media']['max_size'] ?? UploadHandler::DEFAULT_MAX_SIZE,
));
```

Note: `DEFAULT_ALLOWED_TYPES` and `DEFAULT_MAX_SIZE` must be changed from `private` to `public` constants for this to work.

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/media/tests/Unit/UploadHandlerTest.php`
Expected: OK (8 tests, 8 assertions)

- [ ] **Step 6: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/media/src/UploadHandler.php packages/media/tests/Unit/UploadHandlerTest.php packages/media/src/MediaServiceProvider.php
git commit -m "feat(media): add UploadHandler with configurable MIME types and size

Refs: #698"
```

---

## Task 5: Flash → waaseyaa/ssr

**Files:**
- Create: `packages/ssr/src/Flash/FlashMessageService.php`
- Create: `packages/ssr/src/Flash/Flash.php`
- Create: `packages/ssr/src/Twig/FlashTwigExtension.php`
- Create: `packages/ssr/tests/Unit/Flash/FlashMessageServiceTest.php`
- Create: `packages/ssr/tests/Unit/Flash/FlashTest.php`
- Modify: `packages/ssr/src/SsrServiceProvider.php`

- [ ] **Step 1: Write FlashMessageService test**

Create `packages/ssr/tests/Unit/Flash/FlashMessageServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Unit\Flash;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\SSR\Flash\FlashMessageService;

#[CoversClass(FlashMessageService::class)]
final class FlashMessageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    #[Test]
    public function adds_and_consumes_success_message(): void
    {
        $service = new FlashMessageService();
        $service->addSuccess('It worked');

        $messages = $service->consumeAll();
        $this->assertCount(1, $messages);
        $this->assertSame('success', $messages[0]['type']);
        $this->assertSame('It worked', $messages[0]['message']);
    }

    #[Test]
    public function adds_and_consumes_error_message(): void
    {
        $service = new FlashMessageService();
        $service->addError('Something broke');

        $messages = $service->consumeAll();
        $this->assertCount(1, $messages);
        $this->assertSame('error', $messages[0]['type']);
    }

    #[Test]
    public function adds_and_consumes_info_message(): void
    {
        $service = new FlashMessageService();
        $service->addInfo('FYI');

        $messages = $service->consumeAll();
        $this->assertCount(1, $messages);
        $this->assertSame('info', $messages[0]['type']);
    }

    #[Test]
    public function consume_clears_messages(): void
    {
        $service = new FlashMessageService();
        $service->addSuccess('First');
        $service->consumeAll();

        $this->assertSame([], $service->consumeAll());
    }

    #[Test]
    public function returns_empty_array_when_no_messages(): void
    {
        $service = new FlashMessageService();
        $this->assertSame([], $service->consumeAll());
    }

    #[Test]
    public function filters_invalid_session_data(): void
    {
        $_SESSION['flash_messages'] = [
            ['type' => 'success', 'message' => 'valid'],
            'not an array',
            ['type' => 'invalid_type', 'message' => 'bad type'],
            ['type' => 'success', 'message' => ''],
        ];

        $service = new FlashMessageService();
        $messages = $service->consumeAll();
        $this->assertCount(1, $messages);
        $this->assertSame('valid', $messages[0]['message']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/ssr/tests/Unit/Flash/FlashMessageServiceTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write FlashMessageService**

Create `packages/ssr/src/Flash/FlashMessageService.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Flash;

final class FlashMessageService
{
    private const string SESSION_KEY = 'flash_messages';

    public function addSuccess(string $message): void
    {
        $this->add('success', $message);
    }

    public function addError(string $message): void
    {
        $this->add('error', $message);
    }

    public function addInfo(string $message): void
    {
        $this->add('info', $message);
    }

    /**
     * @return list<array{type: string, message: string}>
     */
    public function consumeAll(): array
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            return [];
        }

        $messages = $_SESSION[self::SESSION_KEY];
        unset($_SESSION[self::SESSION_KEY]);

        $allowedTypes = ['success', 'error', 'info'];

        return array_values(array_filter($messages, static function (mixed $msg) use ($allowedTypes): bool {
            return is_array($msg)
                && isset($msg['type'], $msg['message'])
                && is_string($msg['type'])
                && is_string($msg['message'])
                && $msg['message'] !== ''
                && in_array($msg['type'], $allowedTypes, true);
        }));
    }

    private function add(string $type, string $message): void
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        $_SESSION[self::SESSION_KEY][] = ['type' => $type, 'message' => $message];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/ssr/tests/Unit/Flash/FlashMessageServiceTest.php`
Expected: OK (6 tests, 9 assertions)

- [ ] **Step 5: Write Flash facade test**

Create `packages/ssr/tests/Unit/Flash/FlashTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Unit\Flash;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\SSR\Flash\Flash;
use Waaseyaa\SSR\Flash\FlashMessageService;

#[CoversClass(Flash::class)]
final class FlashTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        Flash::setService(new FlashMessageService());
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        Flash::resetService();
    }

    #[Test]
    public function success_delegates_to_service(): void
    {
        Flash::success('Saved');
        $messages = (new FlashMessageService())->consumeAll();
        $this->assertCount(1, $messages);
        $this->assertSame('success', $messages[0]['type']);
    }

    #[Test]
    public function error_delegates_to_service(): void
    {
        Flash::error('Failed');
        $messages = (new FlashMessageService())->consumeAll();
        $this->assertCount(1, $messages);
        $this->assertSame('error', $messages[0]['type']);
    }

    #[Test]
    public function info_delegates_to_service(): void
    {
        Flash::info('Note');
        $messages = (new FlashMessageService())->consumeAll();
        $this->assertCount(1, $messages);
        $this->assertSame('info', $messages[0]['type']);
    }
}
```

- [ ] **Step 6: Write Flash facade**

Create `packages/ssr/src/Flash/Flash.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Flash;

final class Flash
{
    private static ?FlashMessageService $service = null;

    public static function setService(FlashMessageService $service): void
    {
        self::$service = $service;
    }

    /** Reset for test isolation. */
    public static function resetService(): void
    {
        self::$service = null;
    }

    public static function success(string $message): void
    {
        self::getService()->addSuccess($message);
    }

    public static function error(string $message): void
    {
        self::getService()->addError($message);
    }

    public static function info(string $message): void
    {
        self::getService()->addInfo($message);
    }

    private static function getService(): FlashMessageService
    {
        if (self::$service === null) {
            self::$service = new FlashMessageService();
        }

        return self::$service;
    }
}
```

- [ ] **Step 7: Write FlashTwigExtension**

Create `packages/ssr/src/Twig/FlashTwigExtension.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Waaseyaa\SSR\Flash\FlashMessageService;

final class FlashTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly FlashMessageService $flashService,
    ) {}

    /** @return TwigFunction[] */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('flash_messages', $this->flashMessages(...)),
        ];
    }

    /**
     * @return list<array{type: string, message: string}>
     */
    public function flashMessages(): array
    {
        return $this->flashService->consumeAll();
    }
}
```

- [ ] **Step 8: Wire flash into SsrServiceProvider boot()**

Add to `packages/ssr/src/SsrServiceProvider.php` in the `boot()` method:

```php
$flashService = new FlashMessageService();
Flash::setService($flashService);
$twig = ThemeServiceProvider::getTwigEnvironment();
if ($twig !== null) {
    $twig->addExtension(new FlashTwigExtension($flashService));
}
```

Add imports at top:
```php
use Waaseyaa\SSR\Flash\Flash;
use Waaseyaa\SSR\Flash\FlashMessageService;
use Waaseyaa\SSR\Twig\FlashTwigExtension;
```

- [ ] **Step 9: Run all flash tests**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/ssr/tests/Unit/Flash/`
Expected: OK (9 tests)

- [ ] **Step 10: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/ssr/src/Flash/ packages/ssr/src/Twig/FlashTwigExtension.php packages/ssr/tests/Unit/Flash/ packages/ssr/src/SsrServiceProvider.php
git commit -m "feat(ssr): add flash messaging with Twig extension

Refs: #697"
```

---

## Task 6: JsonResponseTrait → waaseyaa/api

**Files:**
- Create: `packages/api/src/JsonResponseTrait.php`
- Create: `packages/api/tests/Unit/JsonResponseTraitTest.php`

- [ ] **Step 1: Write the failing test**

Create `packages/api/tests/Unit/JsonResponseTraitTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Api\JsonResponseTrait;
use Waaseyaa\SSR\SsrResponse;

#[CoversClass(JsonResponseTrait::class)]
final class JsonResponseTraitTest extends TestCase
{
    use JsonResponseTrait;

    #[Test]
    public function json_body_decodes_valid_json(): void
    {
        $request = Request::create('/', 'POST', content: '{"key":"value"}');
        $this->assertSame(['key' => 'value'], $this->jsonBody($request));
    }

    #[Test]
    public function json_body_returns_empty_array_for_empty_content(): void
    {
        $request = Request::create('/', 'POST', content: '');
        $this->assertSame([], $this->jsonBody($request));
    }

    #[Test]
    public function json_body_returns_empty_array_for_invalid_json(): void
    {
        $request = Request::create('/', 'POST', content: '{broken');
        $this->assertSame([], $this->jsonBody($request));
    }

    #[Test]
    public function json_builds_response_with_defaults(): void
    {
        $response = $this->json(['ok' => true]);
        $this->assertInstanceOf(SsrResponse::class, $response);
        $this->assertSame('{"ok":true}', $response->content);
        $this->assertSame(200, $response->statusCode);
        $this->assertSame('application/json', $response->headers['Content-Type']);
    }

    #[Test]
    public function json_builds_response_with_custom_status(): void
    {
        $response = $this->json(['error' => 'not found'], 404);
        $this->assertSame(404, $response->statusCode);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/api/tests/Unit/JsonResponseTraitTest.php`
Expected: FAIL — trait `Waaseyaa\Api\JsonResponseTrait` not found

- [ ] **Step 3: Write JsonResponseTrait**

Create `packages/api/src/JsonResponseTrait.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Api;

use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\SSR\SsrResponse;

trait JsonResponseTrait
{
    /** @return array<string, mixed> */
    private function jsonBody(Request $request): array
    {
        $content = $request->getContent();
        if ($content === '') {
            return [];
        }

        try {
            return (array) json_decode((string) $content, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
    }

    /** @param array<string, mixed> $data */
    private function json(array $data, int $status = 200): SsrResponse
    {
        return new SsrResponse(
            content: json_encode($data, JSON_THROW_ON_ERROR),
            statusCode: $status,
            headers: ['Content-Type' => 'application/json'],
        );
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/api/tests/Unit/JsonResponseTraitTest.php`
Expected: OK (5 tests, 7 assertions)

- [ ] **Step 5: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/api/src/JsonResponseTrait.php packages/api/tests/Unit/JsonResponseTraitTest.php
git commit -m "feat(api): add JsonResponseTrait with jsonBody() and json() helpers

Refs: #700"
```

---

## Task 7: Tag framework release

- [ ] **Step 1: Run full framework test suite**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit`
Expected: All tests pass (existing + new)

- [ ] **Step 2: Create framework PR**

```bash
cd /home/jones/dev/waaseyaa
git push -u origin HEAD
gh pr create --title "feat: Batch 1 framework extractions from Minoo" --body "## Summary
- SlugGenerator → foundation (#692)
- GeoDistance → new geo package (#693)
- MercurePublisher → new mercure package (#694)
- UploadHandler → media (#698)
- Flash messaging → ssr (#697)
- JsonResponseTrait → api (#700)

## Test plan
- [ ] All new unit tests pass
- [ ] Existing framework tests unaffected
- [ ] Minoo import swap PR validates integration

Refs: #692, #693, #694, #697, #698, #700"
```

- [ ] **Step 3: After PR merges, tag new release**

```bash
cd /home/jones/dev/waaseyaa
git checkout main && git pull
git tag v0.1.0-alpha.XX  # increment from current latest tag
git push origin v0.1.0-alpha.XX
```

---

## Task 8: Minoo import swap — SlugGenerator

**Files:**
- Modify: 6 mapper files + 1 test file
- Delete: `src/Support/SlugGenerator.php`

- [ ] **Step 1: Update composer.json to require new framework version**

```bash
cd /home/jones/dev/minoo
composer update waaseyaa/framework
```

Verify `vendor/waaseyaa/foundation/src/SlugGenerator.php` exists.

- [ ] **Step 2: Swap imports in all 6 mappers**

In each of these files, replace `use Minoo\Support\SlugGenerator;` with `use Waaseyaa\Foundation\SlugGenerator;`:

- `src/Ingestion/EntityMapper/NcArticleToEventMapper.php`
- `src/Ingestion/EntityMapper/NcArticleToTeachingMapper.php`
- `src/Ingestion/EntityMapper/DictionaryEntryMapper.php`
- `src/Ingestion/EntityMapper/CulturalCollectionMapper.php`
- `src/Ingestion/EntityMapper/SpeakerMapper.php`
- `src/Ingestion/EntityMapper/WordPartMapper.php`

- [ ] **Step 3: Update or delete Minoo test**

Update `tests/Minoo/Unit/Ingest/SlugGeneratorTest.php` — change import to `use Waaseyaa\Foundation\SlugGenerator;` and update `#[CoversClass]`. Or delete the file since it's now covered by framework tests.

- [ ] **Step 4: Delete Minoo source**

```bash
rm src/Support/SlugGenerator.php
```

- [ ] **Step 5: Run tests**

Run: `cd /home/jones/dev/minoo && ./vendor/bin/phpunit`
Expected: All 759+ tests pass

- [ ] **Step 6: Commit**

```bash
cd /home/jones/dev/minoo
git add -A
git commit -m "refactor: use framework SlugGenerator instead of Minoo copy

Refs: waaseyaa/framework#692"
```

---

## Task 9: Minoo import swap — GeoDistance

**Files:**
- Modify: 7 source files + 1 test file
- Delete: `src/Support/GeoDistance.php`

- [ ] **Step 1: Swap imports in all files**

Replace `use Minoo\Support\GeoDistance;` with `use Waaseyaa\Geo\GeoDistance;` in:

- `src/Feed/FeedAssembler.php`
- `src/Feed/Scoring/AffinityCalculator.php`
- `src/Feed/FeedItemFactory.php`
- `src/Controller/CommunityController.php`
- `src/Controller/FeedController.php`
- `src/Domain/Geo/Service/VolunteerRanker.php`
- `src/Domain/Geo/Service/CommunityFinder.php`

- [ ] **Step 2: Update or delete Minoo test**

Update `tests/Minoo/Unit/Geo/GeoDistanceTest.php` — change import to `use Waaseyaa\Geo\GeoDistance;`. Or delete since covered by framework.

- [ ] **Step 3: Delete Minoo source**

```bash
rm src/Support/GeoDistance.php
```

- [ ] **Step 4: Run tests**

Run: `cd /home/jones/dev/minoo && ./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
cd /home/jones/dev/minoo
git add -A
git commit -m "refactor: use framework GeoDistance instead of Minoo copy

Refs: waaseyaa/framework#693"
```

---

## Task 10: Minoo import swap — MercurePublisher

**Files:**
- Modify: 2 source files + 1 test file
- Delete: `src/Support/MercurePublisher.php`

- [ ] **Step 1: Swap imports**

Replace `use Minoo\Support\MercurePublisher;` with `use Waaseyaa\Mercure\MercurePublisher;` in:

- `src/Provider/MessagingServiceProvider.php`
- `src/Controller/MessagingController.php`

- [ ] **Step 2: Update or delete Minoo test**

Update `tests/Minoo/Unit/Support/MercurePublisherTest.php` — change import. Or delete since covered by framework.

- [ ] **Step 3: Delete Minoo source**

```bash
rm src/Support/MercurePublisher.php
```

- [ ] **Step 4: Add Mercure config to Minoo**

Ensure Minoo's config has `mercure.hub_url` and `mercure.jwt_secret` keys (read from env). Check `config/` for existing Mercure config — if it already exists, no change needed. If the `MessagingServiceProvider` constructs `MercurePublisher` directly, update it to resolve from the container instead.

- [ ] **Step 5: Run tests**

Run: `cd /home/jones/dev/minoo && ./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 6: Commit**

```bash
cd /home/jones/dev/minoo
git add -A
git commit -m "refactor: use framework MercurePublisher instead of Minoo copy

Refs: waaseyaa/framework#694"
```

---

## Task 11: Minoo import swap — UploadService → UploadHandler

**Files:**
- Modify: 2 source files + 1 test file
- Delete: `src/Support/UploadService.php`

- [ ] **Step 1: Swap imports and class name**

In `src/Provider/EngagementServiceProvider.php`:
- Replace `use Minoo\Support\UploadService;` with `use Waaseyaa\Media\UploadHandler;`
- Update any references from `UploadService` to `UploadHandler`

In `src/Controller/EngagementController.php`:
- Replace `use Minoo\Support\UploadService;` with `use Waaseyaa\Media\UploadHandler;`
- Update constructor/property type from `UploadService` to `UploadHandler`
- Update method calls: `validateImage()` → `validate()` (the framework version uses the generic name)

- [ ] **Step 2: Update test**

In `tests/Minoo/Unit/Controller/EngagementControllerTest.php`:
- Replace `use Minoo\Support\UploadService;` with `use Waaseyaa\Media\UploadHandler;`
- Update mock creation from `UploadService` to `UploadHandler`

- [ ] **Step 3: Delete or update Minoo upload test**

Update `tests/Minoo/Unit/Support/UploadServiceTest.php` — either delete (covered by framework) or update import to `use Waaseyaa\Media\UploadHandler;` and rename test class.

- [ ] **Step 4: Delete Minoo source**

```bash
rm src/Support/UploadService.php
```

- [ ] **Step 5: Run tests**

Run: `cd /home/jones/dev/minoo && ./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 6: Commit**

```bash
cd /home/jones/dev/minoo
git add -A
git commit -m "refactor: use framework UploadHandler instead of Minoo UploadService

Refs: waaseyaa/framework#698"
```

---

## Task 12: Minoo import swap — Flash

**Files:**
- Modify: 8 controllers + FlashServiceProvider + 3 test files
- Delete: `src/Support/Flash.php`, `src/Service/FlashMessageService.php`, `src/Twig/FlashTwigExtension.php`

- [ ] **Step 1: Swap Flash imports in all 8 controllers**

Replace `use Minoo\Support\Flash;` with `use Waaseyaa\SSR\Flash\Flash;` in:

- `src/Controller/AuthController.php`
- `src/Controller/RoleManagementController.php`
- `src/Controller/ElderSupportWorkflowController.php`
- `src/Controller/ElderSupportController.php`
- `src/Controller/AccountHomeController.php`
- `src/Controller/VolunteerDashboardController.php`
- `src/Controller/VolunteerController.php`
- `src/Controller/CoordinatorDashboardController.php`

- [ ] **Step 2: Update FlashServiceProvider**

In `src/Provider/FlashServiceProvider.php`:
- Replace `use Minoo\Support\Flash;` with `use Waaseyaa\SSR\Flash\Flash;`
- Replace `use Minoo\Service\FlashMessageService;` with `use Waaseyaa\SSR\Flash\FlashMessageService;`
- Replace `use Minoo\Twig\FlashTwigExtension;` with `use Waaseyaa\SSR\Twig\FlashTwigExtension;`
- **Note:** If the framework `SsrServiceProvider` now wires flash automatically in `boot()`, the Minoo `FlashServiceProvider` may become redundant. Check whether the framework's boot already registers the flash Twig extension. If so, remove the Minoo provider and its registration in `composer.json` `extra.waaseyaa.providers`.
- The `AccountDisplayTwigExtension` and `DateTwigExtension` currently registered in `FlashServiceProvider` are Minoo-specific — they need a new home (e.g., a `MinooTwigServiceProvider` or move registrations to an existing Minoo provider).

- [ ] **Step 3: Update or delete Minoo tests**

- `tests/Minoo/Unit/Support/FlashTest.php` — delete or update imports
- `tests/Minoo/Unit/Service/FlashMessageServiceTest.php` — delete or update imports
- `tests/Minoo/Unit/Twig/FlashTwigExtensionTest.php` — update imports to framework classes

- [ ] **Step 4: Delete Minoo source files**

```bash
rm src/Support/Flash.php
rm src/Service/FlashMessageService.php
rm src/Twig/FlashTwigExtension.php
```

- [ ] **Step 5: Run tests**

Run: `cd /home/jones/dev/minoo && ./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 6: Commit**

```bash
cd /home/jones/dev/minoo
git add -A
git commit -m "refactor: use framework Flash messaging instead of Minoo copy

Refs: waaseyaa/framework#697"
```

---

## Task 13: Minoo import swap — JsonResponseTrait

**Files:**
- Modify: `src/Controller/GameControllerTrait.php`, `MessagingController.php`, `BlockController.php`, `EngagementController.php`, `IngestionApiController.php`

- [ ] **Step 1: Update GameControllerTrait**

In `src/Controller/GameControllerTrait.php`:
- Add `use Waaseyaa\Api\JsonResponseTrait;`
- Add `use JsonResponseTrait;` inside the trait body
- Remove the local `jsonBody()` and `json()` methods (lines 22–43)
- Keep `cleanDefinition()`, `loadSessionByToken()`, and the `getEntityTypeManager()` abstract

- [ ] **Step 2: Remove duplicate jsonBody/json from standalone controllers**

In each of these files, remove the local `private function jsonBody()` and `private function json()` methods and add `use Waaseyaa\Api\JsonResponseTrait;` import + `use JsonResponseTrait;` in the class body:

- `src/Controller/MessagingController.php` (lines ~672 and ~827)
- `src/Controller/BlockController.php` (lines ~112 and ~127)
- `src/Controller/EngagementController.php` (lines ~367 and ~388)
- `src/Controller/IngestionApiController.php` (lines ~158 and ~173)

- [ ] **Step 3: Run tests**

Run: `cd /home/jones/dev/minoo && ./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
cd /home/jones/dev/minoo
git add -A
git commit -m "refactor: use framework JsonResponseTrait, remove 5 duplicate copies

Refs: waaseyaa/framework#700"
```

---

## Task 14: Final validation and Minoo PR

- [ ] **Step 1: Delete stale manifest cache**

```bash
cd /home/jones/dev/minoo
rm -f storage/framework/packages.php
composer dump-autoload
```

- [ ] **Step 2: Run full Minoo test suite**

Run: `cd /home/jones/dev/minoo && ./vendor/bin/phpunit`
Expected: All 759+ tests pass, 0 errors, 0 failures

- [ ] **Step 3: Run PHPStan if configured**

Run: `cd /home/jones/dev/minoo && ./vendor/bin/phpstan analyse` (if applicable)
Fix any type errors from the import changes.

- [ ] **Step 4: Verify no leftover references**

```bash
grep -r 'Minoo\\Support\\SlugGenerator\|Minoo\\Support\\GeoDistance\|Minoo\\Support\\MercurePublisher\|Minoo\\Support\\UploadService\|Minoo\\Support\\Flash\b\|Minoo\\Service\\FlashMessageService\|Minoo\\Twig\\FlashTwigExtension' src/ tests/ --include='*.php'
```

Expected: No matches (docs/plans may still reference old paths — that's fine).

- [ ] **Step 5: Create Minoo PR**

```bash
cd /home/jones/dev/minoo
git push -u origin HEAD
gh pr create --title "refactor: swap Minoo utilities for framework packages (Batch 1)" --body "## Summary
- SlugGenerator → waaseyaa/foundation (6 mappers)
- GeoDistance → waaseyaa/geo (7 files)
- MercurePublisher → waaseyaa/mercure (2 files)
- UploadService → waaseyaa/media UploadHandler (2 files)
- Flash → waaseyaa/ssr flash (8 controllers + provider)
- JsonResponseTrait → waaseyaa/api (5 controllers)

Deletes 7 source files, removes 5 duplicate jsonBody/json implementations.

## Test plan
- [ ] Full Minoo test suite passes (759+ tests)
- [ ] No references to old Minoo\Support\* classes remain in src/
- [ ] PHPStan clean (if configured)

Refs: waaseyaa/framework#692, #693, #694, #697, #698, #700"
```
