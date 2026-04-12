<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search;

use App\Search\CommunityAutocompleteClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommunityAutocompleteClient::class)]
final class CommunityAutocompleteClientTest extends TestCase
{
    #[Test]
    public function it_returns_suggestions_from_api_response(): void
    {
        $apiResponse = json_encode([
            'hits' => [
                [
                    'id' => '1',
                    'name' => 'Sagamok Anishnawbek',
                    'community_type' => 'first_nation',
                    'province' => 'Ontario',
                ],
                [
                    'id' => '2',
                    'name' => 'Sault Ste. Marie',
                    'community_type' => 'municipality',
                    'province' => 'Ontario',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $client = new CommunityAutocompleteClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            cacheTtl: 0,
            httpClient: fn(string $url): string => $apiResponse,
        );

        $results = $client->suggest('sa');

        $this->assertCount(2, $results);
        $this->assertSame('Sagamok Anishnawbek', $results[0]['name']);
        $this->assertSame('first_nation', $results[0]['community_type']);
        $this->assertSame('Sault Ste. Marie', $results[1]['name']);
    }

    #[Test]
    public function it_returns_empty_for_blank_query(): void
    {
        $client = new CommunityAutocompleteClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            cacheTtl: 0,
            httpClient: fn(string $url): string => '{"hits":[]}',
        );

        $this->assertSame([], $client->suggest(''));
        $this->assertSame([], $client->suggest('   '));
    }

    #[Test]
    public function it_returns_empty_on_invalid_response(): void
    {
        $client = new CommunityAutocompleteClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            cacheTtl: 0,
            httpClient: fn(string $url): string => 'not json',
        );

        $this->assertSame([], $client->suggest('test'));
    }

    #[Test]
    public function it_returns_empty_on_failed_request(): void
    {
        $client = new CommunityAutocompleteClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            cacheTtl: 0,
            httpClient: fn(string $url): string|false => false,
        );

        $this->assertSame([], $client->suggest('test'));
    }

    #[Test]
    public function it_caches_results_within_ttl(): void
    {
        $callCount = 0;
        $client = new CommunityAutocompleteClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            cacheTtl: 300,
            httpClient: function (string $url) use (&$callCount): string {
                $callCount++;
                return json_encode(['hits' => [['id' => '1', 'name' => 'Sagamok', 'community_type' => 'first_nation', 'province' => 'ON']]], JSON_THROW_ON_ERROR);
            },
        );

        $client->suggest('sag');
        $client->suggest('sag');
        $client->suggest('sag');

        $this->assertSame(1, $callCount, 'HTTP client should only be called once; subsequent calls should hit cache');
    }

    #[Test]
    public function it_does_not_cache_when_ttl_is_zero(): void
    {
        $callCount = 0;
        $client = new CommunityAutocompleteClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            cacheTtl: 0,
            httpClient: function (string $url) use (&$callCount): string {
                $callCount++;
                return json_encode(['hits' => []], JSON_THROW_ON_ERROR);
            },
        );

        $client->suggest('sag');
        $client->suggest('sag');

        $this->assertSame(2, $callCount, 'HTTP client should be called each time when cacheTtl is 0');
    }

    #[Test]
    public function it_builds_correct_url(): void
    {
        $capturedUrl = '';
        $client = new CommunityAutocompleteClient(
            baseUrl: 'https://northcloud.one/',
            timeout: 5,
            cacheTtl: 0,
            httpClient: function (string $url) use (&$capturedUrl): string {
                $capturedUrl = $url;
                return json_encode(['hits' => []], JSON_THROW_ON_ERROR);
            },
        );

        $client->suggest('sag', 5);

        $this->assertStringContainsString('/api/communities/search?', $capturedUrl);
        $this->assertStringContainsString('q=sag', $capturedUrl);
        $this->assertStringContainsString('page_size=5', $capturedUrl);
    }
}
