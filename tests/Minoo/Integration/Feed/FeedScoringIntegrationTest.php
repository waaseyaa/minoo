<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration\Feed;

use Minoo\Feed\Scoring\AffinityCache;
use Minoo\Feed\Scoring\AffinityCalculator;
use Minoo\Feed\Scoring\DecayCalculator;
use Minoo\Feed\Scoring\DiversityReranker;
use Minoo\Feed\Scoring\EngagementCalculator;
use Minoo\Feed\Scoring\FeedScorer;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

#[CoversNothing]
final class FeedScoringIntegrationTest extends TestCase
{
    private static string $projectRoot;
    private static HttpKernel $kernel;

    public static function setUpBeforeClass(): void
    {
        // tests/Minoo/Integration/Feed/ → 4 levels up to project root.
        self::$projectRoot = dirname(__DIR__, 4);

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
    public function scoring_components_are_resolvable_from_container(): void
    {
        $container = self::$kernel->getContainer();

        $this->assertInstanceOf(DecayCalculator::class, $container->get(DecayCalculator::class));
        $this->assertInstanceOf(EngagementCalculator::class, $container->get(EngagementCalculator::class));
        $this->assertInstanceOf(AffinityCache::class, $container->get(AffinityCache::class));
        $this->assertInstanceOf(AffinityCalculator::class, $container->get(AffinityCalculator::class));
        $this->assertInstanceOf(DiversityReranker::class, $container->get(DiversityReranker::class));
        $this->assertInstanceOf(FeedScorer::class, $container->get(FeedScorer::class));
    }

    #[Test]
    public function feed_scorer_is_singleton(): void
    {
        $container = self::$kernel->getContainer();

        $scorer1 = $container->get(FeedScorer::class);
        $scorer2 = $container->get(FeedScorer::class);

        $this->assertSame($scorer1, $scorer2);
    }

    #[Test]
    public function affinity_cache_is_shared_singleton(): void
    {
        $container = self::$kernel->getContainer();

        $cache1 = $container->get(AffinityCache::class);
        $cache2 = $container->get(AffinityCache::class);

        $this->assertSame($cache1, $cache2);
    }
}
