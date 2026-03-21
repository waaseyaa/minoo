<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

#[CoversNothing]
final class FeedSmokeTest extends TestCase
{
    private static string $projectRoot;
    private static HttpKernel $kernel;

    public static function setUpBeforeClass(): void
    {
        // tests/Minoo/Integration/ → 3 levels up to project root.
        self::$projectRoot = dirname(__DIR__, 3);

        // Delete stale manifest cache to force fresh compilation.
        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        // Use in-memory database for test isolation.
        putenv('WAASEYAA_DB=:memory:');

        self::$kernel = new HttpKernel(self::$projectRoot);
        $boot = new \ReflectionMethod(AbstractKernel::class, 'boot');
        $boot->invoke(self::$kernel);
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');

        // Remove the manifest cache that was generated during test.
        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    #[Test]
    public function feed_entity_storages_are_resolvable(): void
    {
        $etm = self::$kernel->getEntityTypeManager();
        $this->assertNotNull($etm->getStorage('event'));
        $this->assertNotNull($etm->getStorage('group'));
        $this->assertNotNull($etm->getStorage('resource_person'));
    }
}
