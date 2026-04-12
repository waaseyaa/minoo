<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\NorthCloudClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NorthCloudClient::class)]
final class NorthCloudClientTest extends TestCase
{
    #[Test]
    public function get_people_returns_array_on_success(): void
    {
        $responseJson = json_encode([
            'people' => [
                [
                    'id' => 'p1',
                    'name' => 'Chief Example',
                    'role' => 'chief',
                    'role_title' => 'Chief',
                    'email' => 'chief@example.com',
                    'phone' => '705-555-0001',
                    'is_current' => true,
                    'verified' => false,
                    'updated_at' => '2026-01-15T00:00:00Z',
                ],
                [
                    'id' => 'p2',
                    'name' => 'Councillor One',
                    'role' => 'councillor',
                    'role_title' => 'Councillor',
                    'is_current' => true,
                    'verified' => false,
                    'updated_at' => '2026-01-15T00:00:00Z',
                ],
            ],
            'total' => 2,
        ], JSON_THROW_ON_ERROR);

        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => $responseJson,
        );

        $result = $client->getPeople('nc-uuid-123');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('Chief Example', $result[0]['name']);
        $this->assertSame('chief', $result[0]['role']);
    }

    #[Test]
    public function get_people_returns_null_on_http_failure(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => false,
        );

        $this->assertNull($client->getPeople('nc-uuid-123'));
    }

    #[Test]
    public function get_people_returns_null_on_malformed_json(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => '<html>not json</html>',
        );

        $this->assertNull($client->getPeople('nc-uuid-123'));
    }

    #[Test]
    public function get_band_office_returns_array_on_success(): void
    {
        $responseJson = json_encode([
            'band_office' => [
                'id' => 'bo1',
                'address_line1' => '100 Main St',
                'city' => 'Sagamok',
                'province' => 'ON',
                'postal_code' => 'P0P 1X0',
                'phone' => '705-555-0002',
                'fax' => '705-555-0003',
                'email' => 'office@sagamok.ca',
                'toll_free' => '1-800-555-0004',
                'office_hours' => 'Mon-Fri 8:30am-4:30pm',
                'verified' => false,
                'updated_at' => '2026-01-15T00:00:00Z',
            ],
        ], JSON_THROW_ON_ERROR);

        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => $responseJson,
        );

        $result = $client->getBandOffice('nc-uuid-123');

        $this->assertIsArray($result);
        $this->assertSame('100 Main St', $result['address_line1']);
        $this->assertSame('705-555-0002', $result['phone']);
    }

    #[Test]
    public function get_band_office_returns_null_on_http_failure(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => false,
        );

        $this->assertNull($client->getBandOffice('nc-uuid-123'));
    }

    #[Test]
    public function get_band_office_returns_null_on_404(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => json_encode(['band_office' => null]),
        );

        $this->assertNull($client->getBandOffice('nc-uuid-123'));
    }

    #[Test]
    public function get_people_builds_correct_url(): void
    {
        $capturedUrl = '';
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: function (string $url) use (&$capturedUrl): string|false {
                $capturedUrl = $url;
                return json_encode(['people' => [], 'total' => 0]);
            },
        );

        $client->getPeople('abc-123');

        $this->assertSame(
            'https://northcloud.one/api/v1/communities/abc-123/people?current_only=true',
            $capturedUrl,
        );
    }

    #[Test]
    public function get_band_office_builds_correct_url(): void
    {
        $capturedUrl = '';
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: function (string $url) use (&$capturedUrl): string|false {
                $capturedUrl = $url;
                return json_encode(['band_office' => null]);
            },
        );

        $client->getBandOffice('abc-123');

        $this->assertSame(
            'https://northcloud.one/api/v1/communities/abc-123/band-office',
            $capturedUrl,
        );
    }

    #[Test]
    public function get_dictionary_entries_returns_entries_on_success(): void
    {
        $responseJson = json_encode([
            'entries' => [
                ['id' => 'e1', 'lemma' => 'makwa', 'definitions' => 'bear'],
                ['id' => 'e2', 'lemma' => 'miigwech', 'definitions' => 'thank you'],
            ],
            'total' => 2,
            'limit' => 50,
            'offset' => 0,
        ], JSON_THROW_ON_ERROR);

        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => $responseJson,
        );

        $result = $client->getDictionaryEntries();

        $this->assertIsArray($result);
        $this->assertCount(2, $result['entries']);
        $this->assertSame(2, $result['total']);
        $this->assertSame('makwa', $result['entries'][0]['lemma']);
        $this->assertSame(NorthCloudClient::DICTIONARY_ATTRIBUTION, $result['attribution']);
    }

    #[Test]
    public function get_dictionary_entries_builds_correct_url_with_pagination(): void
    {
        $capturedUrl = '';
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: function (string $url) use (&$capturedUrl): string|false {
                $capturedUrl = $url;
                return json_encode(['entries' => [], 'total' => 0]);
            },
        );

        $client->getDictionaryEntries(page: 3, limit: 100);

        $this->assertSame(
            'https://northcloud.one/api/v1/dictionary/entries?limit=100&offset=200',
            $capturedUrl,
        );
    }

    #[Test]
    public function get_dictionary_entries_returns_null_on_http_failure(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => false,
        );

        $this->assertNull($client->getDictionaryEntries());
    }

    #[Test]
    public function get_dictionary_entries_returns_null_on_malformed_json(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => '<html>error</html>',
        );

        $this->assertNull($client->getDictionaryEntries());
    }

    #[Test]
    public function search_dictionary_returns_entries_on_success(): void
    {
        $responseJson = json_encode([
            'entries' => [
                ['id' => 'e1', 'lemma' => 'makwa', 'definitions' => 'bear'],
            ],
            'total' => 1,
            'query' => 'bear',
        ], JSON_THROW_ON_ERROR);

        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => $responseJson,
        );

        $result = $client->searchDictionary('bear');

        $this->assertIsArray($result);
        $this->assertCount(1, $result['entries']);
        $this->assertSame(1, $result['total']);
        $this->assertSame(NorthCloudClient::DICTIONARY_ATTRIBUTION, $result['attribution']);
    }

    #[Test]
    public function search_dictionary_builds_correct_url(): void
    {
        $capturedUrl = '';
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: function (string $url) use (&$capturedUrl): string|false {
                $capturedUrl = $url;
                return json_encode(['entries' => [], 'total' => 0]);
            },
        );

        $client->searchDictionary('makwa');

        $this->assertSame(
            'https://northcloud.one/api/v1/dictionary/search?q=makwa',
            $capturedUrl,
        );
    }

    #[Test]
    public function search_dictionary_returns_null_on_http_failure(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => false,
        );

        $this->assertNull($client->searchDictionary('test'));
    }

    #[Test]
    public function link_sources_returns_result_on_success(): void
    {
        $responseJson = json_encode([
            'linked' => 3,
            'skipped' => 1,
            'details' => [
                ['community_name' => 'Sagamok', 'status' => 'linked'],
            ],
        ], JSON_THROW_ON_ERROR);

        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url, string $method = 'GET', ?string $body = null): string|false => $responseJson,
            apiToken: 'test-token',
        );

        $result = $client->linkSources(dryRun: true);

        $this->assertIsArray($result);
        $this->assertSame(3, $result['linked']);
        $this->assertSame(1, $result['skipped']);
    }

    #[Test]
    public function link_sources_returns_null_on_http_failure(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url, string $method = 'GET', ?string $body = null): string|false => false,
            apiToken: 'test-token',
        );

        $this->assertNull($client->linkSources());
    }

    #[Test]
    public function create_leadership_scrape_job_returns_result_on_success(): void
    {
        $responseJson = json_encode([
            'id' => 'job-uuid-123',
            'community_id' => 'nc-uuid-456',
            'job_type' => 'leadership_scrape',
            'status' => 'pending',
        ], JSON_THROW_ON_ERROR);

        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url, string $method = 'GET', ?string $body = null): string|false => $responseJson,
            apiToken: 'test-token',
        );

        $result = $client->createLeadershipScrapeJob('nc-uuid-456');

        $this->assertIsArray($result);
        $this->assertSame('job-uuid-123', $result['id']);
        $this->assertSame('leadership_scrape', $result['job_type']);
    }

    #[Test]
    public function create_leadership_scrape_job_sends_correct_body(): void
    {
        $capturedBody = null;
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: function (string $url, string $method = 'GET', ?string $body = null) use (&$capturedBody): string|false {
                $capturedBody = $body;
                return json_encode(['id' => 'job-1']);
            },
            apiToken: 'test-token',
        );

        $client->createLeadershipScrapeJob('nc-uuid-789');

        $this->assertNotNull($capturedBody);
        $decoded = json_decode($capturedBody, true);
        $this->assertSame('nc-uuid-789', $decoded['community_id']);
        $this->assertSame('leadership_scrape', $decoded['job_type']);
    }

    #[Test]
    public function create_leadership_scrape_job_returns_null_on_http_failure(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url, string $method = 'GET', ?string $body = null): string|false => false,
            apiToken: 'test-token',
        );

        $this->assertNull($client->createLeadershipScrapeJob('nc-uuid-123'));
    }

}
