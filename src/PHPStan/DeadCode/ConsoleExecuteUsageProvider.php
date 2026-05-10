<?php

declare(strict_types=1);

namespace App\PHPStan\DeadCode;

use ReflectionMethod;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;

/**
 * Native CLI handlers in AppCommandServiceProvider are invoked via CommandDefinition
 * handler tuples, not direct PHP calls; mark their execute entrypoints as used.
 */
final class ConsoleExecuteUsageProvider extends ReflectionBasedMemberUsageProvider
{
    protected function shouldMarkMethodAsUsed(ReflectionMethod $method): bool
    {
        if ($method->getName() !== 'execute') {
            return false;
        }

        return $method->getDeclaringClass()->getNamespaceName() === 'App\Console';
    }
}
