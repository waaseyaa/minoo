<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class BusinessControllerConsentTest extends TestCase
{
    private string $controllerSource;

    protected function setUp(): void
    {
        $path = dirname(__DIR__, 4) . '/src/Controller/BusinessController.php';
        $contents = file_get_contents($path);
        self::assertIsString($contents, "Controller file not found at {$path}");
        $this->controllerSource = $contents;
    }

    #[Test]
    public function consentConditionExistsInController(): void
    {
        self::assertStringContainsString(
            "condition('consent_public', 1)",
            $this->controllerSource,
            'BusinessController must filter resource_person by consent_public',
        );
    }

    #[Test]
    public function consentConditionAppliedWithStatusCondition(): void
    {
        self::assertStringContainsString(
            "condition('status', 1)",
            $this->controllerSource,
            'BusinessController must filter resource_person by status',
        );
        self::assertStringContainsString(
            "condition('consent_public', 1)",
            $this->controllerSource,
            'BusinessController must filter resource_person by consent_public',
        );
    }
}
