<?php

declare(strict_types=1);

namespace App\PHPStan\DeadCode;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use ShipMonk\PHPStan\DeadCode\Graph\ClassMethodRef;
use ShipMonk\PHPStan\DeadCode\Graph\ClassMethodUsage;
use ShipMonk\PHPStan\DeadCode\Provider\MemberUsageProvider;

/**
 * Marks HTTP controller actions as used when they appear in route definitions
 * as string literals (Waaseyaa RouteBuilder ->controller('Class::method')).
 */
final class RoutingControllerStringUsageProvider implements MemberUsageProvider
{
    private const string CONTROLLER_PREFIX = 'App\\Http\\Controller\\';

    public function getUsages(Node $node, Scope $unusedScope): array
    {
        if (!$node instanceof String_) {
            return [];
        }

        $value = $node->value;
        if (!str_starts_with($value, self::CONTROLLER_PREFIX) || !str_contains($value, '::')) {
            return [];
        }

        $parts = explode('::', $value, 2);
        if (count($parts) !== 2) {
            return [];
        }

        [$className, $methodName] = $parts;
        if ($className === '' || $methodName === '' || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $methodName)) {
            return [];
        }

        return [
            new ClassMethodUsage(
                null,
                new ClassMethodRef($className, $methodName, false),
            ),
        ];
    }
}
