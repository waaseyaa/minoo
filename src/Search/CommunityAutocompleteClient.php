<?php

declare(strict_types=1);

namespace Minoo\Search;

final class CommunityAutocompleteClient
{
    /** @var \Closure|null */
    private readonly ?\Closure $httpClient;

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
        ?callable $httpClient = null,
    ) {
        $this->httpClient = $httpClient !== null ? $httpClient(...) : null;
    }

    /**
     * @return list<array{id: string, name: string, community_type: string, province: string}>
     */
    public function suggest(string $query, int $limit = 10): array
    {
        if (trim($query) === '') {
            return [];
        }

        $params = http_build_query([
            'q' => $query,
            'page_size' => (string) $limit,
        ]);

        $url = rtrim($this->baseUrl, '/') . '/api/communities/search?' . $params;
        $json = $this->doRequest($url);

        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['hits'])) {
            return [];
        }

        $results = [];
        foreach ($data['hits'] as $hit) {
            $results[] = [
                'id' => (string) ($hit['id'] ?? ''),
                'name' => (string) ($hit['name'] ?? ''),
                'community_type' => (string) ($hit['community_type'] ?? ''),
                'province' => (string) ($hit['province'] ?? ''),
            ];
        }

        return $results;
    }

    private function doRequest(string $url): string|false
    {
        if ($this->httpClient !== null) {
            return ($this->httpClient)($url);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            error_log(sprintf('NorthCloud community autocomplete request failed: %s', $url));
        }

        return $result;
    }
}
