<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Controls whether draft crisis incidents resolve for the current request (e.g. PHPUnit).
 */
final class CrisisResolveContext
{
    public function __construct(
        public readonly bool $includeDrafts = false,
    ) {}

    public static function publicWeb(): self
    {
        return new self(false);
    }

    public static function withDraftIncidents(): self
    {
        return new self(true);
    }
}
