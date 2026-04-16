<?php

declare(strict_types=1);

namespace App\Contract;

/**
 * Narrow app-facing seam for NorthCloud community + dictionary operations.
 *
 * This hides the underlying HTTP/package client from controllers and tests.
 */
interface NorthCloudCommunityDictionaryClientInterface
{
    /**
     * Fetch paginated dictionary entries from NorthCloud.
     *
     * @return array{entries: list<array<string, mixed>>, total: int, attribution: string}|null
     */
    public function getDictionaryEntries(int $page = 1, int $limit = 50): ?array;

    /**
     * Search dictionary entries via NorthCloud full-text search.
     *
     * @return array{entries: list<array<string, mixed>>, total: int, attribution: string}|null
     */
    public function searchDictionary(string $query): ?array;

    /**
     * Fetch current leadership for a community.
     *
     * @return list<array{id: string, name: string, role: string, role_title?: string, email?: string, phone?: string, verified: bool}>|null
     */
    public function getPeople(string $ncId): ?array;

    /**
     * Fetch band office contact info for a community.
     *
     * @return array{address_line1?: string, address_line2?: string, city?: string, province?: string, postal_code?: string, phone?: string, fax?: string, email?: string, toll_free?: string, office_hours?: string, verified: bool}|null
     */
    public function getBandOffice(string $ncId): ?array;
}

