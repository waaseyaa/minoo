<?php

declare(strict_types=1);

namespace App\PHPStan\DeadCode;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use ShipMonk\PHPStan\DeadCode\Graph\ClassMethodRef;
use ShipMonk\PHPStan\DeadCode\Graph\ClassMethodUsage;
use ShipMonk\PHPStan\DeadCode\Provider\MemberUsageProvider;

/**
 * Marks HTTP controller actions as used when they appear in route definitions
 * as controller strings (Waaseyaa RouteBuilder ->controller('Class::method')).
 *
 * Two shapes are recognized:
 *   - a plain string literal: ->controller('App\Http\Controller\X::method')
 *   - a constant-foldable concatenation: $ctrl . '::method' where $ctrl holds
 *     a controller class-string (SocialApiRouteProvider style) — resolved via
 *     PHPStan's constant-string type inference on the Concat expression.
 */
final class RoutingControllerStringUsageProvider implements MemberUsageProvider
{
    private const string CONTROLLER_PREFIX = 'App\\Http\\Controller\\';

    public function getUsages(Node $node, Scope $scope): array
    {
        $value = $this->resolveControllerString($node, $scope);
        if ($value === null) {
            return [];
        }

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

    private function resolveControllerString(Node $node, Scope $scope): ?string
    {
        if ($node instanceof String_) {
            return $node->value;
        }

        if ($node instanceof Concat) {
            $constantStrings = $scope->getType($node)->getConstantStrings();
            if (count($constantStrings) === 1) {
                return $constantStrings[0]->getValue();
            }
        }

        return null;
    }
}
