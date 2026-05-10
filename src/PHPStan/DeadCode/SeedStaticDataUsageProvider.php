<?php

declare(strict_types=1);

namespace App\PHPStan\DeadCode;

use ReflectionMethod;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;

/**
 * Seeders expose static data builders consumed by TaxonomySeeder / ConfigSeeder /
 * scripts and unit tests; ShipMonk cannot see those references from src/ alone.
 */
final class SeedStaticDataUsageProvider extends ReflectionBasedMemberUsageProvider
{
    protected function shouldMarkMethodAsUsed(ReflectionMethod $method): bool
    {
        if ($method->getDeclaringClass()->getNamespaceName() !== 'App\Seed') {
            return false;
        }

        return $method->isPublic() && $method->isStatic();
    }
}
