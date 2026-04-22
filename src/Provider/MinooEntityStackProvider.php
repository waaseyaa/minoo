<?php

declare(strict_types=1);

namespace App\Provider;

use App\Provider\Entity\EntityCommunityProvider;
use App\Provider\Entity\EntityContentProvider;
use App\Provider\Entity\EntityFeedProvider;
use App\Provider\Entity\EntityFoundationProvider;
use App\Provider\Entity\EntityNewsletterProvider;
use App\Provider\Entity\NewsletterEntityDefinitionsProvider;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

/**
 * Delegates to focused entity providers (single composer entry).
 */
final class MinooEntityStackProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeChildProvider(new EntityFoundationProvider());
        $this->mergeChildProvider(new EntityCommunityProvider());
        $this->mergeChildProvider(new EntityContentProvider());
        $this->mergeChildProvider(new EntityFeedProvider());
        $this->mergeChildProvider(new NewsletterEntityDefinitionsProvider());
        $this->mergeChildProvider(new EntityNewsletterProvider());
    }
}
