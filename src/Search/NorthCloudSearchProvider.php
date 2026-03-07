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
        $url = rtrim($this->baseUrl, '/') . '/api/search';

        if ($this->httpClient !== null) {
            return ($this->httpClient)($url, ['body' => $body]);
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            error_log(sprintf('NorthCloud search request failed: %s', $url));
        }

        return $result;
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
                highlight: $this->extractHighlight($hit['highlight'] ?? ''),
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

    private function extractHighlight(mixed $highlight): string
    {
        if (is_string($highlight)) {
            return $highlight;
        }

        if (!is_array($highlight)) {
            return '';
        }

        // API returns {body: [...], raw_text: [...], title: [...]} — prefer body, then raw_text.
        foreach (['body', 'raw_text', 'title'] as $field) {
            if (isset($highlight[$field][0]) && is_string($highlight[$field][0])) {
                return $highlight[$field][0];
            }
        }

        return '';
    }
}
