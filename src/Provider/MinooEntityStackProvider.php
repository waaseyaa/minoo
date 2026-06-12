<?php

declare(strict_types=1);

namespace App\Provider;

use App\Provider\Entity\EntityCommunityProvider;
use App\Provider\Entity\EntityContentProvider;
use App\Provider\Entity\EntityFoundationProvider;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

/**
 * Delegates to focused entity providers (single composer entry).
 *
 * Language-platform slimming (2026-06): feed and newsletter providers
 * de-registered; their tables remain dormant in the database by design.
 */
final class MinooEntityStackProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeChildProvider(new EntityFoundationProvider());
        $this->mergeChildProvider(new EntityCommunityProvider());
        $this->mergeChildProvider(new EntityContentProvider());
    }
}
