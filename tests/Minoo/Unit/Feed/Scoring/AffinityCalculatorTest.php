<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed\Scoring;

use Minoo\Feed\Scoring\AffinityCache;
use Minoo\Feed\Scoring\AffinityCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Cache\Backend\MemoryBackend;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(AffinityCalculator::class)]
final class AffinityCalculatorTest extends TestCase
{
    #[Test]
    public function anonymous_user_returns_null(): void
    {
        $calc = $this->makeCalculator();
        self::assertNull($calc->computeBatch(null, ['user:99'], null, null));
    }

    #[Test]
    public function unknown_source_gets_base_affinity(): void
    {
        $calc = $this->makeCalculator();
        $result = $calc->computeBatch(1, ['user:99'], null, null);

        self::assertNotNull($result);
        self::assertSame(1.0, $result['user:99']);
    }

    #[Test]
    public function follow_adds_points(): void
    {
        $follow = $this->createMock(ContentEntityBase::class);
        $follow->method('get')->willReturnCallback(fn(string $f) => match ($f) {
            'target_type' => 'user',
            'target_id' => 10,
            default => null,
        });

        $calc = $this->makeCalculator(follows: [$follow]);
        $result = $calc->computeBatch(1, ['user:10', 'user:20'], null, null);

        self::assertSame(5.0, $result['user:10']); // base(1) + follow(4)
        self::assertSame(1.0, $result['user:20']); // base only
    }

    #[Test]
    public function same_community_adds_points(): void
    {
        $calc = $this->makeCalculator();
        $sourceLocations = [
            'community:10' => ['lat' => 46.0, 'lon' => -81.0, 'community_id' => 10],
            'community:20' => ['lat' => 46.0, 'lon' => -81.0, 'community_id' => 20],
        ];

        $result = $calc->computeBatch(1, ['community:10', 'community:20'], 10, null, $sourceLocations);

        self::assertSame(4.0, $result['community:10']); // base(1) + sameCommunity(3)
        self::assertSame(1.0, $result['community:20']); // base only
    }

    #[Test]
    public function geo_proximity_close_adds_points(): void
    {
        $calc = $this->makeCalculator();
        $result = $calc->computeBatch(
            1,
            ['community:1'],
            null,
            ['lat' => 46.0, 'lon' => -81.0],
            ['community:1' => ['lat' => 46.1, 'lon' => -81.0]],
        );

        self::assertSame(3.0, $result['community:1']); // base(1) + geoClose(2)
    }

    #[Test]
    public function geo_proximity_mid_adds_points(): void
    {
        $calc = $this->makeCalculator();
        $result = $calc->computeBatch(
            1,
            ['community:1'],
            null,
            ['lat' => 46.0, 'lon' => -81.0],
            ['community:1' => ['lat' => 47.0, 'lon' => -81.0]],
        );

        self::assertSame(2.0, $result['community:1']); // base(1) + geoMid(1)
    }

    #[Test]
    public function geo_beyond_mid_gets_no_bonus(): void
    {
        $calc = $this->makeCalculator();
        $result = $calc->computeBatch(
            1,
            ['community:1'],
            null,
            ['lat' => 46.0, 'lon' => -81.0],
            ['community:1' => ['lat' => 51.0, 'lon' => -81.0]],
        );

        self::assertSame(1.0, $result['community:1']); // base only
    }

    #[Test]
    public function cache_is_used_on_second_call(): void
    {
        $cache = new AffinityCache(new MemoryBackend());
        $calc = $this->makeCalculator(cache: $cache);

        $result1 = $calc->computeBatch(1, ['user:10'], null, null);
        // Second call should hit cache even though ETM would return different data
        $result2 = $calc->computeBatch(1, ['user:10'], null, null);

        self::assertSame($result1['user:10'], $result2['user:10']);
    }

    #[Test]
    public function empty_source_keys_returns_empty(): void
    {
        $calc = $this->makeCalculator();
        self::assertSame([], $calc->computeBatch(1, [], null, null));
    }

    /**
     * @param ContentEntityBase[] $follows
     * @param ContentEntityBase[] $reactions
     * @param ContentEntityBase[] $comments
     */
    private function makeCalculator(
        array $follows = [],
        array $reactions = [],
        array $comments = [],
        ?AffinityCache $cache = null,
    ): AffinityCalculator {
        $etm = $this->createMock(EntityTypeManager::class);

        $etm->method('getStorage')->willReturnCallback(
            function (string $type) use ($follows, $reactions, $comments) {
                $entities = match ($type) {
                    'follow' => $follows,
                    'reaction' => $reactions,
                    'comment' => $comments,
                    default => [],
                };

                $ids = array_keys($entities) ?: [];

                $storage = $this->createMock(EntityStorageInterface::class);
                $query = $this->createMock(EntityQueryInterface::class);
                $query->method('condition')->willReturnSelf();
                $query->method('execute')->willReturn($ids);
                $storage->method('getQuery')->willReturn($query);
                $storage->method('loadMultiple')->willReturn($entities);

                return $storage;
            }
        );

        return new AffinityCalculator(
            $etm,
            $cache ?? new AffinityCache(new MemoryBackend()),
        );
    }
}
