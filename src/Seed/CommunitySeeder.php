<?php

declare(strict_types=1);

namespace Minoo\Seed;

final class CommunitySeeder
{
    /** @return list<array{name: string, community_type: string}> */
    public static function northShoreCommunities(): array
    {
        return [
            ['name' => 'Sagamok Anishnawbek', 'community_type' => 'first_nation'],
            ['name' => 'Serpent River First Nation', 'community_type' => 'first_nation'],
            ['name' => 'Mississauga First Nation', 'community_type' => 'first_nation'],
            ['name' => 'Thessalon First Nation', 'community_type' => 'first_nation'],
            ['name' => 'Garden River First Nation', 'community_type' => 'first_nation'],
            ['name' => 'Batchewana First Nation', 'community_type' => 'first_nation'],
            ['name' => 'Elliot Lake', 'community_type' => 'town'],
            ['name' => 'Blind River', 'community_type' => 'town'],
            ['name' => 'Thessalon', 'community_type' => 'town'],
            ['name' => 'North Shore', 'community_type' => 'region'],
        ];
    }
}
