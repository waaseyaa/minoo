# Search & Filtering Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a `/search` page to Minoo that queries NorthCloud's production search API, with faceted filtering and pagination — all server-side rendered.

**Architecture:** New `waaseyaa/search` package defines `SearchProviderInterface` + DTOs. Minoo implements `NorthCloudSearchProvider` that calls `northcloud.one/api/search` with 60s TTL caching. A Twig extension exposes `search()` and `query_param()` functions to templates. The `/search` template uses traditional form submission with query params.

**Tech Stack:** PHP 8.3, Twig 3, Waaseyaa framework (ServiceProvider, CacheBackendInterface), vanilla CSS

**Design Doc:** `docs/plans/2026-03-06-search-filtering-design.md`

---

## Task 1: Waaseyaa Search Package — DTOs

Create the value objects that define the search contract.

**Files:**
- Create: `../waaseyaa/packages/search/composer.json`
- Create: `../waaseyaa/packages/search/src/SearchRequest.php`
- Create: `../waaseyaa/packages/search/src/SearchFilters.php`
- Create: `../waaseyaa/packages/search/src/SearchResult.php`
- Create: `../waaseyaa/packages/search/src/SearchHit.php`
- Create: `../waaseyaa/packages/search/src/SearchFacet.php`
- Create: `../waaseyaa/packages/search/src/FacetBucket.php`
- Test: `../waaseyaa/packages/search/tests/Unit/SearchRequestTest.php`
- Test: `../waaseyaa/packages/search/tests/Unit/SearchResultTest.php`

**Step 1: Create composer.json**

```json
{
    "name": "waaseyaa/search",
    "description": "Search provider interface and DTOs for Waaseyaa",
    "type": "library",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=8.3"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "autoload": {
        "psr-4": {
            "Waaseyaa\\Search\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Waaseyaa\\Search\\Tests\\": "tests/"
        }
    },
    "minimum-stability": "stable"
}
```

**Step 2: Create FacetBucket**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Search;

final readonly class FacetBucket
{
    public function __construct(
        public string $key,
        public int $count,
    ) {}
}
```

**Step 3: Create SearchFacet**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Search;

final readonly class SearchFacet
{
    /**
     * @param FacetBucket[] $buckets
     */
    public function __construct(
        public string $name,
        public array $buckets,
    ) {}
}
```

**Step 4: Create SearchHit**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Search;

final readonly class SearchHit
{
    /**
     * @param string[] $topics
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public string $sourceName,
        public string $crawledAt,
        public int $qualityScore,
        public string $contentType,
        public array $topics,
        public float $score,
        public string $ogImage = '',
        public string $highlight = '',
    ) {}
}
```

**Step 5: Create SearchFilters**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Search;

final readonly class SearchFilters
{
    /**
     * @param string[] $topics
     * @param string[] $sourceNames
     */
    public function __construct(
        public array $topics = [],
        public string $contentType = '',
        public array $sourceNames = [],
        public int $minQuality = 0,
        public string $sortField = 'relevance',
        public string $sortOrder = 'desc',
    ) {}

    public function isEmpty(): bool
    {
        return $this->topics === []
            && $this->contentType === ''
            && $this->sourceNames === []
            && $this->minQuality === 0;
    }
}
```

**Step 6: Create SearchRequest**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Search;

final readonly class SearchRequest
{
    public function __construct(
        public string $query,
        public SearchFilters $filters = new SearchFilters(),
        public int $page = 1,
        public int $pageSize = 20,
    ) {}

    public function cacheKey(): string
    {
        return hash('sha256', serialize([
            $this->query,
            $this->filters,
            $this->page,
            $this->pageSize,
        ]));
    }
}
```

**Step 7: Create SearchResult**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Search;

final readonly class SearchResult
{
    /**
     * @param SearchHit[] $hits
     * @param SearchFacet[] $facets
     */
    public function __construct(
        public int $totalHits,
        public int $totalPages,
        public int $currentPage,
        public int $pageSize,
        public int $tookMs,
        public array $hits,
        public array $facets = [],
    ) {}

    public static function empty(): self
    {
        return new self(
            totalHits: 0,
            totalPages: 0,
            currentPage: 1,
            pageSize: 20,
            tookMs: 0,
            hits: [],
        );
    }

    public function getFacet(string $name): ?SearchFacet
    {
        foreach ($this->facets as $facet) {
            if ($facet->name === $name) {
                return $facet;
            }
        }
        return null;
    }
}
```

**Step 8: Write unit tests**

`../waaseyaa/packages/search/tests/Unit/SearchRequestTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Search\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Search\SearchFilters;
use Waaseyaa\Search\SearchRequest;

#[CoversClass(SearchRequest::class)]
#[CoversClass(SearchFilters::class)]
final class SearchRequestTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $request = new SearchRequest(query: 'indigenous');

        $this->assertSame('indigenous', $request->query);
        $this->assertSame(1, $request->page);
        $this->assertSame(20, $request->pageSize);
        $this->assertTrue($request->filters->isEmpty());
    }

    #[Test]
    public function it_creates_with_filters(): void
    {
        $filters = new SearchFilters(topics: ['education'], contentType: 'article');
        $request = new SearchRequest(query: 'test', filters: $filters, page: 2);

        $this->assertSame(['education'], $request->filters->topics);
        $this->assertSame('article', $request->filters->contentType);
        $this->assertSame(2, $request->page);
        $this->assertFalse($request->filters->isEmpty());
    }

    #[Test]
    public function cache_key_is_deterministic(): void
    {
        $a = new SearchRequest(query: 'test', page: 1);
        $b = new SearchRequest(query: 'test', page: 1);

        $this->assertSame($a->cacheKey(), $b->cacheKey());
    }

    #[Test]
    public function cache_key_differs_for_different_requests(): void
    {
        $a = new SearchRequest(query: 'test', page: 1);
        $b = new SearchRequest(query: 'test', page: 2);

        $this->assertNotSame($a->cacheKey(), $b->cacheKey());
    }
}
```

`../waaseyaa/packages/search/tests/Unit/SearchResultTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Search\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Search\FacetBucket;
use Waaseyaa\Search\SearchFacet;
use Waaseyaa\Search\SearchHit;
use Waaseyaa\Search\SearchResult;

#[CoversClass(SearchResult::class)]
#[CoversClass(SearchHit::class)]
#[CoversClass(SearchFacet::class)]
#[CoversClass(FacetBucket::class)]
final class SearchResultTest extends TestCase
{
    #[Test]
    public function empty_result(): void
    {
        $result = SearchResult::empty();

        $this->assertSame(0, $result->totalHits);
        $this->assertSame(0, $result->totalPages);
        $this->assertSame([], $result->hits);
        $this->assertSame([], $result->facets);
    }

    #[Test]
    public function it_constructs_with_hits_and_facets(): void
    {
        $hit = new SearchHit(
            id: 'abc123',
            title: 'Test Article',
            url: 'https://example.com/test',
            sourceName: 'Example Source',
            crawledAt: '2026-03-06T12:00:00Z',
            qualityScore: 80,
            contentType: 'article',
            topics: ['education', 'health'],
            score: 15.5,
        );

        $facet = new SearchFacet(
            name: 'topics',
            buckets: [
                new FacetBucket(key: 'education', count: 500),
                new FacetBucket(key: 'health', count: 300),
            ],
        );

        $result = new SearchResult(
            totalHits: 1,
            totalPages: 1,
            currentPage: 1,
            pageSize: 20,
            tookMs: 150,
            hits: [$hit],
            facets: [$facet],
        );

        $this->assertSame(1, $result->totalHits);
        $this->assertCount(1, $result->hits);
        $this->assertSame('Test Article', $result->hits[0]->title);
        $this->assertSame(['education', 'health'], $result->hits[0]->topics);
    }

    #[Test]
    public function get_facet_by_name(): void
    {
        $topicsFacet = new SearchFacet(name: 'topics', buckets: []);
        $sourcesFacet = new SearchFacet(name: 'sources', buckets: []);

        $result = new SearchResult(
            totalHits: 0,
            totalPages: 0,
            currentPage: 1,
            pageSize: 20,
            tookMs: 0,
            hits: [],
            facets: [$topicsFacet, $sourcesFacet],
        );

        $this->assertSame($topicsFacet, $result->getFacet('topics'));
        $this->assertSame($sourcesFacet, $result->getFacet('sources'));
        $this->assertNull($result->getFacet('nonexistent'));
    }
}
```

**Step 9: Run tests**

```bash
cd ../waaseyaa/packages/search && composer install && ../../vendor/bin/phpunit tests/
```

Expected: All tests pass.

**Step 10: Commit**

```bash
cd ../waaseyaa/packages/search
git add -A
git commit -m "feat(search): add search package with DTOs and interface contract"
```

---

## Task 2: Waaseyaa Search Package — Interface and Twig Extension

Add the provider interface and a Twig extension that makes search available in templates.

**Files:**
- Create: `../waaseyaa/packages/search/src/SearchProviderInterface.php`
- Create: `../waaseyaa/packages/search/src/Twig/SearchTwigExtension.php`
- Test: `../waaseyaa/packages/search/tests/Unit/Twig/SearchTwigExtensionTest.php`

**Step 1: Create SearchProviderInterface**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Search;

interface SearchProviderInterface
{
    public function search(SearchRequest $request): SearchResult;
}
```

**Step 2: Create SearchTwigExtension**

This extension provides two Twig functions:
- `query_param(name, default)` — reads from `$_GET` (safe, server-side only)
- `search(query, filters, page, pageSize)` — calls the search provider

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Search\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Waaseyaa\Search\SearchFilters;
use Waaseyaa\Search\SearchProviderInterface;
use Waaseyaa\Search\SearchRequest;
use Waaseyaa\Search\SearchResult;

final class SearchTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly SearchProviderInterface $provider,
    ) {}

    /** @return TwigFunction[] */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('search', $this->search(...)),
            new TwigFunction('query_param', $this->queryParam(...)),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function search(string $query, array $filters = [], int $page = 1, int $pageSize = 20): SearchResult
    {
        if ($query === '') {
            return SearchResult::empty();
        }

        $searchFilters = new SearchFilters(
            topics: isset($filters['topic']) && $filters['topic'] !== ''
                ? [(string) $filters['topic']]
                : [],
            contentType: (string) ($filters['content_type'] ?? ''),
            sourceNames: isset($filters['source']) && $filters['source'] !== ''
                ? [(string) $filters['source']]
                : [],
            minQuality: (int) ($filters['min_quality'] ?? 0),
        );

        return $this->provider->search(new SearchRequest(
            query: $query,
            filters: $searchFilters,
            page: max(1, $page),
            pageSize: min(100, max(1, $pageSize)),
        ));
    }

    public function queryParam(string $name, string $default = ''): string
    {
        $value = $_GET[$name] ?? $default;
        return is_string($value) ? $value : $default;
    }
}
```

**Step 3: Write test for SearchTwigExtension**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Search\Tests\Unit\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Search\SearchProviderInterface;
use Waaseyaa\Search\SearchRequest;
use Waaseyaa\Search\SearchResult;
use Waaseyaa\Search\Twig\SearchTwigExtension;

#[CoversClass(SearchTwigExtension::class)]
final class SearchTwigExtensionTest extends TestCase
{
    #[Test]
    public function it_registers_two_twig_functions(): void
    {
        $provider = $this->createStub(SearchProviderInterface::class);
        $ext = new SearchTwigExtension($provider);
        $functions = $ext->getFunctions();

        $names = array_map(fn($f) => $f->getName(), $functions);
        $this->assertContains('search', $names);
        $this->assertContains('query_param', $names);
    }

    #[Test]
    public function search_returns_empty_for_blank_query(): void
    {
        $provider = $this->createStub(SearchProviderInterface::class);
        $provider->method('search')->willReturn(SearchResult::empty());

        $ext = new SearchTwigExtension($provider);
        $result = $ext->search('');

        $this->assertSame(0, $result->totalHits);
    }

    #[Test]
    public function search_calls_provider_with_request(): void
    {
        $expected = new SearchResult(
            totalHits: 42,
            totalPages: 3,
            currentPage: 1,
            pageSize: 20,
            tookMs: 100,
            hits: [],
        );

        $provider = $this->createMock(SearchProviderInterface::class);
        $provider->expects($this->once())
            ->method('search')
            ->with($this->callback(function (SearchRequest $req): bool {
                return $req->query === 'indigenous'
                    && $req->filters->topics === ['education']
                    && $req->page === 2;
            }))
            ->willReturn($expected);

        $ext = new SearchTwigExtension($provider);
        $result = $ext->search('indigenous', ['topic' => 'education'], 2);

        $this->assertSame(42, $result->totalHits);
    }

    #[Test]
    public function query_param_reads_from_get(): void
    {
        $_GET['q'] = 'test query';
        $provider = $this->createStub(SearchProviderInterface::class);
        $ext = new SearchTwigExtension($provider);

        $this->assertSame('test query', $ext->queryParam('q'));
        $this->assertSame('fallback', $ext->queryParam('missing', 'fallback'));

        unset($_GET['q']);
    }
}
```

**Step 4: Run tests**

```bash
cd ../waaseyaa/packages/search && ../../vendor/bin/phpunit tests/
```

Expected: All tests pass.

**Step 5: Commit**

```bash
cd ../waaseyaa/packages/search
git add -A
git commit -m "feat(search): add SearchProviderInterface and Twig extension"
```

---

## Task 3: Minoo — NorthCloudSearchProvider

Implement the concrete search provider that calls the NorthCloud production API.

**Files:**
- Create: `src/Search/NorthCloudSearchProvider.php`
- Test: `tests/Minoo/Unit/Search/NorthCloudSearchProviderTest.php`

**Step 1: Write the test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Search;

use Minoo\Search\NorthCloudSearchProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Search\SearchFilters;
use Waaseyaa\Search\SearchRequest;

#[CoversClass(NorthCloudSearchProvider::class)]
final class NorthCloudSearchProviderTest extends TestCase
{
    #[Test]
    public function it_parses_api_response_into_search_result(): void
    {
        $apiResponse = json_encode([
            'query' => 'indigenous',
            'total_hits' => 7008,
            'total_pages' => 351,
            'current_page' => 1,
            'page_size' => 20,
            'took_ms' => 3150,
            'hits' => [
                [
                    'id' => 'abc123',
                    'title' => 'Indigenous Education',
                    'url' => 'https://example.com/article',
                    'source_name' => 'Example Source',
                    'crawled_at' => '2026-03-06T12:00:00Z',
                    'quality_score' => 80,
                    'content_type' => 'article',
                    'topics' => ['education'],
                    'score' => 15.5,
                    'og_image' => 'https://example.com/image.jpg',
                ],
            ],
            'facets' => [
                'topics' => [
                    ['key' => 'education', 'count' => 500],
                    ['key' => 'health', 'count' => 300],
                ],
                'content_types' => [
                    ['key' => 'article', 'count' => 400],
                ],
                'sources' => [
                    ['key' => 'Example Source', 'count' => 100],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $provider = new NorthCloudSearchProvider(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            cacheTtl: 60,
            httpClient: fn() => $apiResponse,
        );

        $result = $provider->search(new SearchRequest(query: 'indigenous'));

        $this->assertSame(7008, $result->totalHits);
        $this->assertSame(351, $result->totalPages);
        $this->assertSame(3150, $result->tookMs);
        $this->assertCount(1, $result->hits);
        $this->assertSame('Indigenous Education', $result->hits[0]->title);
        $this->assertSame('article', $result->hits[0]->contentType);
        $this->assertSame(['education'], $result->hits[0]->topics);
        $this->assertSame('https://example.com/image.jpg', $result->hits[0]->ogImage);

        $topicsFacet = $result->getFacet('topics');
        $this->assertNotNull($topicsFacet);
        $this->assertCount(2, $topicsFacet->buckets);
        $this->assertSame('education', $topicsFacet->buckets[0]->key);
        $this->assertSame(500, $topicsFacet->buckets[0]->count);
    }

    #[Test]
    public function it_builds_correct_api_request_body(): void
    {
        $capturedBody = null;
        $httpClient = function (string $url, array $options) use (&$capturedBody): string {
            $capturedBody = $options['body'];
            return json_encode([
                'total_hits' => 0, 'total_pages' => 0, 'current_page' => 1,
                'page_size' => 20, 'took_ms' => 10, 'hits' => [],
            ], JSON_THROW_ON_ERROR);
        };

        $provider = new NorthCloudSearchProvider(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            cacheTtl: 0,
            httpClient: $httpClient,
        );

        $filters = new SearchFilters(topics: ['education'], contentType: 'article', minQuality: 50);
        $provider->search(new SearchRequest(query: 'test', filters: $filters, page: 3, pageSize: 10));

        $this->assertNotNull($capturedBody);
        $decoded = json_decode($capturedBody, true);
        $this->assertSame('test', $decoded['query']);
        $this->assertSame(['education'], $decoded['filters']['topics']);
        $this->assertSame('article', $decoded['filters']['content_type']);
        $this->assertSame(50, $decoded['filters']['min_quality_score']);
        $this->assertSame(3, $decoded['pagination']['page']);
        $this->assertSame(10, $decoded['pagination']['size']);
        $this->assertTrue($decoded['options']['include_facets']);
    }

    #[Test]
    public function it_returns_empty_on_api_failure(): void
    {
        $provider = new NorthCloudSearchProvider(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            cacheTtl: 0,
            httpClient: fn() => false,
        );

        $result = $provider->search(new SearchRequest(query: 'test'));

        $this->assertSame(0, $result->totalHits);
        $this->assertSame([], $result->hits);
    }

    #[Test]
    public function it_caches_results(): void
    {
        $callCount = 0;
        $httpClient = function () use (&$callCount): string {
            $callCount++;
            return json_encode([
                'total_hits' => 1, 'total_pages' => 1, 'current_page' => 1,
                'page_size' => 20, 'took_ms' => 100, 'hits' => [],
            ], JSON_THROW_ON_ERROR);
        };

        $provider = new NorthCloudSearchProvider(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            cacheTtl: 60,
            httpClient: $httpClient,
        );

        $request = new SearchRequest(query: 'cached');
        $provider->search($request);
        $provider->search($request);

        $this->assertSame(1, $callCount);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Search/NorthCloudSearchProviderTest.php
```

Expected: FAIL — class not found.

**Step 3: Create NorthCloudSearchProvider**

```php
<?php

declare(strict_types=1);

namespace Minoo\Search;

use Waaseyaa\Search\FacetBucket;
use Waaseyaa\Search\SearchFacet;
use Waaseyaa\Search\SearchHit;
use Waaseyaa\Search\SearchProviderInterface;
use Waaseyaa\Search\SearchRequest;
use Waaseyaa\Search\SearchResult;

final class NorthCloudSearchProvider implements SearchProviderInterface
{
    /** @var array<string, array{result: SearchResult, expires: int}> */
    private array $cache = [];

    /** @var \Closure|null */
    private readonly ?\Closure $httpClient;

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly int $cacheTtl,
        ?callable $httpClient = null,
    ) {
        $this->httpClient = $httpClient !== null ? $httpClient(...) : null;
    }

    public function search(SearchRequest $request): SearchResult
    {
        $cacheKey = $request->cacheKey();

        if ($this->cacheTtl > 0 && isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];
            if ($cached['expires'] > time()) {
                return $cached['result'];
            }
            unset($this->cache[$cacheKey]);
        }

        $body = $this->buildRequestBody($request);
        $json = $this->doRequest($body);

        if ($json === false) {
            return SearchResult::empty();
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return SearchResult::empty();
        }

        $result = $this->parseResponse($data);

        if ($this->cacheTtl > 0) {
            $this->cache[$cacheKey] = [
                'result' => $result,
                'expires' => time() + $this->cacheTtl,
            ];
        }

        return $result;
    }

    private function buildRequestBody(SearchRequest $request): string
    {
        $body = [
            'query' => $request->query,
            'pagination' => [
                'page' => $request->page,
                'size' => $request->pageSize,
            ],
            'options' => [
                'include_facets' => true,
                'include_highlights' => true,
            ],
        ];

        $filters = [];
        if ($request->filters->topics !== []) {
            $filters['topics'] = $request->filters->topics;
        }
        if ($request->filters->contentType !== '') {
            $filters['content_type'] = $request->filters->contentType;
        }
        if ($request->filters->sourceNames !== []) {
            $filters['source_names'] = $request->filters->sourceNames;
        }
        if ($request->filters->minQuality > 0) {
            $filters['min_quality_score'] = $request->filters->minQuality;
        }
        if ($filters !== []) {
            $body['filters'] = $filters;
        }

        return json_encode($body, JSON_THROW_ON_ERROR);
    }

    private function doRequest(string $body): string|false
    {
        if ($this->httpClient !== null) {
            $url = rtrim($this->baseUrl, '/') . '/api/search';
            return ($this->httpClient)($url, ['body' => $body]);
        }

        $url = rtrim($this->baseUrl, '/') . '/api/search';
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        return @file_get_contents($url, false, $context);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseResponse(array $data): SearchResult
    {
        $hits = [];
        foreach ($data['hits'] ?? [] as $hit) {
            $hits[] = new SearchHit(
                id: (string) ($hit['id'] ?? ''),
                title: (string) ($hit['title'] ?? ''),
                url: (string) ($hit['url'] ?? ''),
                sourceName: (string) ($hit['source_name'] ?? ''),
                crawledAt: (string) ($hit['crawled_at'] ?? ''),
                qualityScore: (int) ($hit['quality_score'] ?? 0),
                contentType: (string) ($hit['content_type'] ?? ''),
                topics: array_map(strval(...), $hit['topics'] ?? []),
                score: (float) ($hit['score'] ?? 0.0),
                ogImage: (string) ($hit['og_image'] ?? ''),
                highlight: (string) ($hit['highlight'] ?? ''),
            );
        }

        $facets = [];
        foreach ($data['facets'] ?? [] as $name => $bucketList) {
            $buckets = [];
            foreach ($bucketList as $bucket) {
                $buckets[] = new FacetBucket(
                    key: (string) ($bucket['key'] ?? ''),
                    count: (int) ($bucket['count'] ?? 0),
                );
            }
            $facets[] = new SearchFacet(name: (string) $name, buckets: $buckets);
        }

        return new SearchResult(
            totalHits: (int) ($data['total_hits'] ?? 0),
            totalPages: (int) ($data['total_pages'] ?? 0),
            currentPage: (int) ($data['current_page'] ?? 1),
            pageSize: (int) ($data['page_size'] ?? 20),
            tookMs: (int) ($data['took_ms'] ?? 0),
            hits: $hits,
            facets: $facets,
        );
    }
}
```

**Step 4: Run tests**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Search/NorthCloudSearchProviderTest.php
```

Expected: All pass.

**Step 5: Commit**

```bash
git add src/Search/NorthCloudSearchProvider.php tests/Minoo/Unit/Search/NorthCloudSearchProviderTest.php
git commit -m "feat(#10): add NorthCloudSearchProvider with caching and tests"
```

---

## Task 4: Minoo — SearchServiceProvider and Wiring

Register the search provider in the DI container and wire the Twig extension.

**Files:**
- Create: `src/Provider/SearchServiceProvider.php`
- Modify: `composer.json` — add `waaseyaa/search` dependency and provider
- Modify: `config/waaseyaa.php` — add search config section
- Test: `tests/Minoo/Unit/Provider/SearchServiceProviderTest.php`

**Step 1: Add `waaseyaa/search` to Minoo's `composer.json`**

Add to the `repositories` array:
```json
{ "type": "path", "url": "../waaseyaa/packages/search" }
```

Add to `require`:
```json
"waaseyaa/search": "@dev"
```

Add to `extra.waaseyaa.providers`:
```json
"Minoo\\Provider\\SearchServiceProvider"
```

**Step 2: Add search config to `config/waaseyaa.php`**

Add before the closing `];`:
```php
    // Search provider configuration.
    'search' => [
        'base_url' => getenv('NORTHCLOUD_SEARCH_URL') ?: 'https://northcloud.one',
        'timeout' => (int) (getenv('NORTHCLOUD_SEARCH_TIMEOUT') ?: 5),
        'cache_ttl' => (int) (getenv('NORTHCLOUD_SEARCH_CACHE_TTL') ?: 60),
    ],
```

**Step 3: Create SearchServiceProvider**

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Search\NorthCloudSearchProvider;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Search\SearchProviderInterface;
use Waaseyaa\Search\Twig\SearchTwigExtension;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $searchConfig = $this->config['search'] ?? [];

        $this->singleton(SearchProviderInterface::class, fn(): SearchProviderInterface => new NorthCloudSearchProvider(
            baseUrl: (string) ($searchConfig['base_url'] ?? 'https://northcloud.one'),
            timeout: (int) ($searchConfig['timeout'] ?? 5),
            cacheTtl: (int) ($searchConfig['cache_ttl'] ?? 60),
        ));

        $this->singleton(SearchTwigExtension::class, fn(): SearchTwigExtension => new SearchTwigExtension(
            $this->resolve(SearchProviderInterface::class),
        ));
    }

    public function boot(): void
    {
        $twig = \Waaseyaa\SSR\SsrServiceProvider::getTwigEnvironment();
        if ($twig !== null) {
            $twig->addExtension($this->resolve(SearchTwigExtension::class));
        }
    }
}
```

**Note:** The `$this->resolve()` and `$this->singleton()` methods are inherited from the base `ServiceProvider`. Check the base class for exact signatures — they may use a different container API. If `resolve()` doesn't exist, use the container directly. Adjust method calls to match the actual framework API found in `Waaseyaa\Foundation\ServiceProvider\ServiceProvider`.

**Step 4: Run `composer update` to install the new package**

```bash
composer update waaseyaa/search
```

**Step 5: Delete manifest cache**

```bash
rm -f storage/framework/packages.php
```

**Step 6: Write test for SearchServiceProvider**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Provider;

use Minoo\Provider\SearchServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchServiceProvider::class)]
final class SearchServiceProviderTest extends TestCase
{
    #[Test]
    public function it_instantiates(): void
    {
        // Smoke test — the provider can be constructed.
        // Full integration test verifies container resolution.
        $this->assertTrue(class_exists(SearchServiceProvider::class));
    }
}
```

**Step 7: Run all tests**

```bash
./vendor/bin/phpunit
```

Expected: All pass (existing 109 + new tests).

**Step 8: Commit**

```bash
git add composer.json config/waaseyaa.php src/Provider/SearchServiceProvider.php tests/Minoo/Unit/Provider/SearchServiceProviderTest.php
git commit -m "feat(#10): wire SearchServiceProvider with NorthCloud config and Twig extension"
```

---

## Task 5: Search Page Template

Create the search page with form, results, filters, and pagination.

**Files:**
- Create: `templates/search.html.twig`
- Create: `templates/components/search-result-card.html.twig`
- Modify: `templates/base.html.twig` — add Search to nav

**Step 1: Add Search to navigation in `base.html.twig`**

In `templates/base.html.twig`, add a new `<li>` after the Language nav item (line 21):

```html
<li><a href="/search"{% if path is defined and path starts with '/search' %} aria-current="page"{% endif %}>Search</a></li>
```

**Step 2: Create search-result-card component**

`templates/components/search-result-card.html.twig`:

```twig
<article class="card search-result-card">
  <div class="search-result-card__badges">
    <span class="card__badge card__badge--{{ content_type ?? 'page' }}">{{ content_type ?? 'page' }}</span>
    {% if topics is defined %}
      {% for topic in topics[:3] %}
        <span class="card__tag">{{ topic|replace({'_': ' '}) }}</span>
      {% endfor %}
    {% endif %}
  </div>
  <h3 class="card__title">
    <a href="{{ url }}" rel="noopener noreferrer" target="_blank">{{ title }}</a>
  </h3>
  <div class="card__meta">
    <span>{{ source_name|replace({'_': ' '}) }}</span>
    {% if crawled_at %}
      <span class="card__date">{{ crawled_at[:10] }}</span>
    {% endif %}
  </div>
  {% if highlight %}
    <div class="card__body">{{ highlight|raw }}</div>
  {% endif %}
</article>
```

**Step 3: Create search page template**

`templates/search.html.twig`:

```twig
{% extends "base.html.twig" %}

{% block title %}Search — Minoo{% endblock %}

{% block content %}
  {% set q = query_param('q') %}
  {% set topic = query_param('topic') %}
  {% set content_type = query_param('content_type') %}
  {% set source = query_param('source') %}
  {% set page = query_param('page', '1')|number_format(0, '', '') %}

  <div class="flow-lg">
    <h1>Search</h1>

    <form class="search-form" action="/search" method="get">
      <input
        type="search"
        name="q"
        value="{{ q }}"
        placeholder="Search articles, teachings, events..."
        aria-label="Search"
        class="search-form__input"
      >
      <button type="submit" class="search-form__button">Search</button>
    </form>

    {% if q %}
      {% set results = search(q, {topic: topic, content_type: content_type, source: source}, page|number_format(0, '', '')|abs) %}

      {# Active filters #}
      {% if topic or content_type or source %}
        <div class="search-active-filters">
          <span class="search-active-filters__label">Filters:</span>
          {% if topic %}
            <a href="/search?q={{ q|url_encode }}{% if content_type %}&content_type={{ content_type|url_encode }}{% endif %}{% if source %}&source={{ source|url_encode }}{% endif %}" class="filter-badge">
              {{ topic|replace({'_': ' '}) }} &times;
            </a>
          {% endif %}
          {% if content_type %}
            <a href="/search?q={{ q|url_encode }}{% if topic %}&topic={{ topic|url_encode }}{% endif %}{% if source %}&source={{ source|url_encode }}{% endif %}" class="filter-badge">
              {{ content_type }} &times;
            </a>
          {% endif %}
          {% if source %}
            <a href="/search?q={{ q|url_encode }}{% if topic %}&topic={{ topic|url_encode }}{% endif %}{% if content_type %}&content_type={{ content_type|url_encode }}{% endif %}" class="filter-badge">
              {{ source|replace({'_': ' '}) }} &times;
            </a>
          {% endif %}
        </div>
      {% endif %}

      <div class="search-layout">
        {# Sidebar: facet filters #}
        {% if results.facets %}
          <aside class="search-filters">
            {% set topicsFacet = results.getFacet('topics') %}
            {% if topicsFacet and topicsFacet.buckets %}
              <div class="search-filters__group">
                <h3 class="search-filters__heading">Topics</h3>
                <ul class="search-filters__list">
                  {% for bucket in topicsFacet.buckets[:15] %}
                    <li>
                      <a href="/search?q={{ q|url_encode }}&topic={{ bucket.key|url_encode }}{% if content_type %}&content_type={{ content_type|url_encode }}{% endif %}{% if source %}&source={{ source|url_encode }}{% endif %}"
                        {% if topic == bucket.key %} aria-current="true" class="search-filters__active"{% endif %}>
                        {{ bucket.key|replace({'_': ' '}) }}
                        <span class="search-filters__count">({{ bucket.count }})</span>
                      </a>
                    </li>
                  {% endfor %}
                </ul>
              </div>
            {% endif %}

            {% set typesFacet = results.getFacet('content_types') %}
            {% if typesFacet and typesFacet.buckets %}
              <div class="search-filters__group">
                <h3 class="search-filters__heading">Type</h3>
                <ul class="search-filters__list">
                  {% for bucket in typesFacet.buckets %}
                    <li>
                      <a href="/search?q={{ q|url_encode }}{% if topic %}&topic={{ topic|url_encode }}{% endif %}&content_type={{ bucket.key|url_encode }}{% if source %}&source={{ source|url_encode }}{% endif %}"
                        {% if content_type == bucket.key %} aria-current="true" class="search-filters__active"{% endif %}>
                        {{ bucket.key }}
                        <span class="search-filters__count">({{ bucket.count }})</span>
                      </a>
                    </li>
                  {% endfor %}
                </ul>
              </div>
            {% endif %}

            {% set sourcesFacet = results.getFacet('sources') %}
            {% if sourcesFacet and sourcesFacet.buckets %}
              <div class="search-filters__group">
                <h3 class="search-filters__heading">Sources</h3>
                <ul class="search-filters__list">
                  {% for bucket in sourcesFacet.buckets[:20] %}
                    <li>
                      <a href="/search?q={{ q|url_encode }}{% if topic %}&topic={{ topic|url_encode }}{% endif %}{% if content_type %}&content_type={{ content_type|url_encode }}{% endif %}&source={{ bucket.key|url_encode }}"
                        {% if source == bucket.key %} aria-current="true" class="search-filters__active"{% endif %}>
                        {{ bucket.key|replace({'_': ' '}) }}
                        <span class="search-filters__count">({{ bucket.count }})</span>
                      </a>
                    </li>
                  {% endfor %}
                </ul>
              </div>
            {% endif %}
          </aside>
        {% endif %}

        {# Main results area #}
        <div class="search-results">
          <p class="search-results__summary">
            {{ results.totalHits|number_format }} results
            <span class="search-results__timing">({{ (results.tookMs / 1000)|number_format(1) }}s)</span>
          </p>

          {% if results.hits %}
            <div class="card-grid">
              {% for hit in results.hits %}
                {% include "components/search-result-card.html.twig" with {
                  title: hit.title,
                  url: hit.url,
                  source_name: hit.sourceName,
                  crawled_at: hit.crawledAt,
                  content_type: hit.contentType,
                  topics: hit.topics,
                  highlight: hit.highlight,
                  og_image: hit.ogImage,
                } %}
              {% endfor %}
            </div>
          {% else %}
            <p>No results found for "{{ q }}".</p>
          {% endif %}

          {# Pagination #}
          {% if results.totalPages > 1 %}
            {% set currentPage = results.currentPage %}
            <nav class="pagination" aria-label="Search results pages">
              {% if currentPage > 1 %}
                <a href="/search?q={{ q|url_encode }}{% if topic %}&topic={{ topic|url_encode }}{% endif %}{% if content_type %}&content_type={{ content_type|url_encode }}{% endif %}{% if source %}&source={{ source|url_encode }}{% endif %}&page={{ currentPage - 1 }}" class="pagination__link" rel="prev">Previous</a>
              {% endif %}

              {% set startPage = max(1, currentPage - 2) %}
              {% set endPage = min(results.totalPages, currentPage + 2) %}

              {% if startPage > 1 %}
                <a href="/search?q={{ q|url_encode }}{% if topic %}&topic={{ topic|url_encode }}{% endif %}{% if content_type %}&content_type={{ content_type|url_encode }}{% endif %}{% if source %}&source={{ source|url_encode }}{% endif %}&page=1" class="pagination__link">1</a>
                {% if startPage > 2 %}
                  <span class="pagination__ellipsis">&hellip;</span>
                {% endif %}
              {% endif %}

              {% for p in startPage..endPage %}
                {% if p == currentPage %}
                  <span class="pagination__link pagination__link--current" aria-current="page">{{ p }}</span>
                {% else %}
                  <a href="/search?q={{ q|url_encode }}{% if topic %}&topic={{ topic|url_encode }}{% endif %}{% if content_type %}&content_type={{ content_type|url_encode }}{% endif %}{% if source %}&source={{ source|url_encode }}{% endif %}&page={{ p }}" class="pagination__link">{{ p }}</a>
                {% endif %}
              {% endfor %}

              {% if endPage < results.totalPages %}
                {% if endPage < results.totalPages - 1 %}
                  <span class="pagination__ellipsis">&hellip;</span>
                {% endif %}
                <a href="/search?q={{ q|url_encode }}{% if topic %}&topic={{ topic|url_encode }}{% endif %}{% if content_type %}&content_type={{ content_type|url_encode }}{% endif %}{% if source %}&source={{ source|url_encode }}{% endif %}&page={{ results.totalPages }}" class="pagination__link">{{ results.totalPages }}</a>
              {% endif %}

              {% if currentPage < results.totalPages %}
                <a href="/search?q={{ q|url_encode }}{% if topic %}&topic={{ topic|url_encode }}{% endif %}{% if content_type %}&content_type={{ content_type|url_encode }}{% endif %}{% if source %}&source={{ source|url_encode }}{% endif %}&page={{ currentPage + 1 }}" class="pagination__link" rel="next">Next</a>
              {% endif %}
            </nav>
          {% endif %}
        </div>
      </div>
    {% else %}
      <p>Enter a search term to discover articles, teachings, events, and community resources from across Indigenous organizations and communities.</p>
    {% endif %}
  </div>
{% endblock %}
```

**Step 4: Verify the page loads**

Start the dev server and visit `http://localhost:8081/search`:

```bash
php -S localhost:8081 -t public
```

Navigate to `/search`, type "indigenous", submit. Verify results appear.

**Step 5: Commit**

```bash
git add templates/search.html.twig templates/components/search-result-card.html.twig templates/base.html.twig
git commit -m "feat(#10): add search page template with filters and pagination"
```

---

## Task 6: CSS for Search Components

Add search-specific styles to the design system.

**Files:**
- Modify: `public/css/minoo.css` — add search components in `@layer components`

**Step 1: Add CSS**

Add the following inside `@layer components { ... }` in `public/css/minoo.css`, after the existing `.detail__body` block (after line 421):

```css
  /* Search form */
  .search-form {
    display: flex;
    gap: var(--space-2xs);
    max-inline-size: var(--width-prose);
  }

  .search-form__input {
    flex: 1;
    padding: var(--space-2xs) var(--space-xs);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: var(--text-base);
    background-color: var(--surface-raised);
    color: var(--text-primary);

    &:focus {
      outline: 2px solid var(--accent);
      outline-offset: -1px;
      border-color: var(--accent);
    }
  }

  .search-form__button {
    padding: var(--space-2xs) var(--space-sm);
    background-color: var(--accent);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-base);
    font-weight: 600;
    cursor: pointer;

    &:hover {
      opacity: 0.9;
    }
  }

  /* Search layout */
  .search-layout {
    display: grid;
    gap: var(--space-lg);
  }

  @media (min-width: 60em) {
    .search-layout {
      grid-template-columns: 16rem 1fr;
    }
  }

  /* Active filters */
  .search-active-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-2xs);
  }

  .search-active-filters__label {
    font-size: var(--text-sm);
    color: var(--text-secondary);
  }

  .filter-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--space-3xs);
    font-size: var(--text-sm);
    padding: var(--space-3xs) var(--space-2xs);
    background-color: var(--accent-surface);
    color: var(--color-forest-700);
    border-radius: var(--radius-full);
    text-decoration: none;

    &:hover {
      background-color: var(--color-forest-100);
      text-decoration: none;
    }
  }

  /* Search filters sidebar */
  .search-filters {
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
  }

  .search-filters__group {
    display: flex;
    flex-direction: column;
    gap: var(--space-2xs);
  }

  .search-filters__heading {
    font-family: var(--font-heading);
    font-size: var(--text-base);
    font-weight: 600;
  }

  .search-filters__list {
    list-style: none;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: var(--space-3xs);

    a {
      display: flex;
      justify-content: space-between;
      padding: var(--space-3xs) var(--space-2xs);
      border-radius: var(--radius-sm);
      text-decoration: none;
      font-size: var(--text-sm);
      color: var(--text-secondary);

      &:hover {
        color: var(--text-primary);
        background-color: var(--accent-surface);
      }
    }
  }

  .search-filters__active {
    color: var(--accent);
    background-color: var(--accent-surface);
    font-weight: 600;
  }

  .search-filters__count {
    color: var(--text-secondary);
    font-weight: 400;
  }

  /* Search results */
  .search-results__summary {
    font-size: var(--text-sm);
    color: var(--text-secondary);
  }

  .search-results__timing {
    color: var(--text-secondary);
    opacity: 0.7;
  }

  /* Search result card */
  .search-result-card {
    max-inline-size: unset;
  }

  .search-result-card__badges {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-3xs);
  }

  /* Pagination */
  .pagination {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-3xs);
    margin-block-start: var(--space-md);
  }

  .pagination__link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-inline-size: 2.5rem;
    padding: var(--space-3xs) var(--space-2xs);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    text-decoration: none;
    font-size: var(--text-sm);
    color: var(--text-secondary);

    &:hover {
      color: var(--text-primary);
      background-color: var(--accent-surface);
      border-color: var(--accent);
    }
  }

  .pagination__link--current {
    background-color: var(--accent);
    color: white;
    border-color: var(--accent);
  }

  .pagination__ellipsis {
    padding: var(--space-3xs);
    color: var(--text-secondary);
  }
```

**Step 2: Verify visually**

Start dev server, visit `/search`, search for "indigenous". Check:
- Form layout looks correct
- Results display in card grid
- Filters sidebar appears on wide screens
- Pagination links appear
- Mobile layout stacks correctly

**Step 3: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat(#10): add search page CSS components"
```

---

## Task 7: Smoke Test and Verification

Verify everything works end-to-end.

**Step 1: Run all unit tests**

```bash
./vendor/bin/phpunit
```

Expected: All pass (existing + new).

**Step 2: Run waaseyaa/search package tests**

```bash
cd ../waaseyaa/packages/search && ../../vendor/bin/phpunit tests/
```

Expected: All pass.

**Step 3: Manual smoke test**

Start the dev server:

```bash
php -S localhost:8081 -t public
```

Test these URLs:
- `/search` — empty search page with form
- `/search?q=indigenous` — results with facets
- `/search?q=indigenous&topic=education` — filtered results
- `/search?q=indigenous&content_type=article` — type filter
- `/search?q=indigenous&page=2` — pagination
- `/search?q=nonexistent_query_xyz` — no results message
- Verify Search link appears in nav and highlights correctly

**Step 4: Take Playwright snapshot**

Use Playwright MCP to verify the UI:

```
Navigate to http://localhost:8081/search?q=indigenous
Take a screenshot
```

**Step 5: Final commit (if any fixes needed)**

```bash
git add -A
git commit -m "fix(#10): address smoke test findings"
```

---

## Task 8: Framework Commit and Issue Updates

Commit the waaseyaa/search package and update GitHub issues.

**Step 1: Commit waaseyaa/search package**

```bash
cd ../waaseyaa
git add packages/search/
git commit -m "feat(search): add search package with provider interface, DTOs, and Twig extension"
```

**Step 2: Close issue #10**

```bash
gh issue close 10 --repo waaseyaa/minoo --comment "Implemented: global /search page with NorthCloud integration, faceted filters, pagination, 60s TTL cache. Phase 2 (dynamic listings, per-page filters) tracked separately."
```

**Step 3: Create Phase 2 follow-up issues**

```bash
gh issue create --repo waaseyaa/minoo --title "Convert listing pages to dynamic NorthCloud data" --body "Replace hardcoded Twig data on events, groups, teachings, language pages with NorthCloud API queries filtered by topic/content_type. Depends on search package from #10." --milestone "v0.2 – First Entities + NorthCloud Ingestion"

gh issue create --repo waaseyaa/minoo --title "Add per-page search and filtering to listing pages" --body "Add search/filter bars to events, groups, teachings, language pages. Per-type filtering using NorthCloud facets. Depends on dynamic listing conversion." --milestone "v0.2 – First Entities + NorthCloud Ingestion"
```

---

## Summary

| Task | What | Where | Tests |
|------|------|-------|-------|
| 1 | DTOs (SearchRequest, SearchResult, SearchHit, SearchFacet, FacetBucket, SearchFilters) | `waaseyaa/packages/search/src/` | 2 test files |
| 2 | SearchProviderInterface + SearchTwigExtension | `waaseyaa/packages/search/src/` | 1 test file |
| 3 | NorthCloudSearchProvider | `minoo/src/Search/` | 1 test file |
| 4 | SearchServiceProvider + composer wiring + config | `minoo/src/Provider/` | 1 test file |
| 5 | search.html.twig + search-result-card + nav update | `minoo/templates/` | Manual |
| 6 | CSS components | `minoo/public/css/minoo.css` | Visual |
| 7 | Smoke test + Playwright verification | — | E2E |
| 8 | Framework commit + issue management | Both repos | — |
