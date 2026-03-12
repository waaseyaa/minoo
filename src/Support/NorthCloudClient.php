<?php

declare(strict_types=1);

namespace Minoo\Support;

final class NorthCloudClient
{
    /** @var \Closure|null */
    private readonly ?\Closure $httpClient;

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 5,
        ?callable $httpClient = null,
        private readonly ?NorthCloudCache $cache = null,
    ) {
        $this->httpClient = $httpClient !== null ? $httpClient(...) : null;
    }

    /**
     * Fetch current leadership for a community.
     *
     * @return list<array{id: string, name: string, role: string, role_title?: string, email?: string, phone?: string, verified: bool}>|null
     */
    public function getPeople(string $ncId): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/api/v1/communities/' . urlencode($ncId) . '/people?current_only=true';
        $json = $this->doRequest($url);

        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['people']) || !is_array($data['people'])) {
            error_log(sprintf('NorthCloud people response malformed for community %s', $ncId));
            return null;
        }

        return $data['people'];
    }

    /**
     * Fetch band office contact info for a community.
     *
     * @return array{address_line1?: string, address_line2?: string, city?: string, province?: string, postal_code?: string, phone?: string, fax?: string, email?: string, toll_free?: string, office_hours?: string, verified: bool}|null
     */
    public function getBandOffice(string $ncId): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/api/v1/communities/' . urlencode($ncId) . '/band-office';
        $json = $this->doRequest($url);

        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['band_office']) || !is_array($data['band_office'])) {
            return null;
        }

        return $data['band_office'];
    }

    private function doRequest(string $url): ?string
    {
        if ($this->cache !== null) {
            $cached = $this->cache->get($url);
            if ($cached !== null) {
                return $cached;
            }
        }

        if ($this->httpClient !== null) {
            $result = ($this->httpClient)($url);
            $result = $result === false ? null : $result;
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => $this->timeout,
                    'ignore_errors' => true,
                ],
            ]);

            $result = @file_get_contents($url, false, $context);
            if ($result === false) {
                error_log(sprintf('NorthCloud API request failed: %s', $url));
                $result = null;
            }
        }

        if ($result !== null && $this->cache !== null) {
            $this->cache->set($url, $result);
        }

        return $result;
    }
}
