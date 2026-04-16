<?php

declare(strict_types=1);

namespace App\Support;

use App\Contract\NorthCloudCommunityDictionaryClientInterface;
use Waaseyaa\NorthCloud\Client\NorthCloudClient;

/**
 * Adapter that exposes only the community/dictionary surface Minoo uses,
 * backed by the shared waaseyaa/northcloud client.
 */
final class NorthCloudCommunityDictionaryClient implements NorthCloudCommunityDictionaryClientInterface
{
    public function __construct(
        private readonly NorthCloudClient $client,
    ) {
    }

    public function getDictionaryEntries(int $page = 1, int $limit = 50): ?array
    {
        return $this->client->getDictionaryEntries($page, $limit);
    }

    public function searchDictionary(string $query): ?array
    {
        return $this->client->searchDictionary($query);
    }

    public function getPeople(string $ncId): ?array
    {
        return $this->client->getPeople($ncId);
    }

    public function getBandOffice(string $ncId): ?array
    {
        return $this->client->getBandOffice($ncId);
    }
}

