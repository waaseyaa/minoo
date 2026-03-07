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
                    'highlight' => [
                        'body' => ['A source for <em>Indigenous</em> education content'],
                        'raw_text' => ['A source for Indigenous education content'],
                        'title' => ['<em>Indigenous</em> Education'],
                    ],
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
        $this->assertSame('A source for <em>Indigenous</em> education content', $result->hits[0]->highlight);

        $topicsFacet = $result->getFacet('topics');
        $this->assertNotNull($topicsFacet);
        $this->assertCount(2, $topicsFacet->buckets);
        $this->assertSame('education', $topicsFacet->buckets[0]->key);
        $this->assertSame(500, $topicsFacet->buckets[0]->count);
    }

    #[Test]
    public function it_builds_correct_query_url(): void
    {
        $capturedUrl = null;
        $httpClient = function (string $url) use (&$capturedUrl): string {
            $capturedUrl = $url;
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

        $this->assertNotNull($capturedUrl);
        $parsed = parse_url($capturedUrl);
        parse_str($parsed['query'] ?? '', $params);
        $this->assertSame('test', $params['q']);
        $this->assertSame('article', $params['content_type']);
        $this->assertSame('50', $params['min_quality_score']);
        $this->assertSame('3', $params['page']);
        $this->assertSame('10', $params['page_size']);
        $this->assertSame('1', $params['include_facets']);
        $this->assertStringContainsString('topics%5B%5D=education', $capturedUrl);
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
