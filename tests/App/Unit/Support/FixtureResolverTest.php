<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\FixtureResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\Entity\Storage\EntityQueryInterface;

#[CoversClass(FixtureResolver::class)]
final class FixtureResolverTest extends TestCase
{
    #[Test]
    public function resolvesCommunityByName(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([42]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        $etm = $this->createMock(EntityTypeManagerInterface::class);
        $etm->method('getStorage')->with('community')->willReturn($storage);

        $resolver = new FixtureResolver($etm);
        $result = $resolver->resolveCommunity('Sagamok Anishnawbek');

        $this->assertSame(42, $result);
    }

    #[Test]
    public function returnsNullForUnknownCommunity(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        $etm = $this->createMock(EntityTypeManagerInterface::class);
        $etm->method('getStorage')->with('community')->willReturn($storage);

        $resolver = new FixtureResolver($etm);
        $result = $resolver->resolveCommunity('Nonexistent Town');

        $this->assertNull($result);
    }

    #[Test]
    public function resolvesCommunityByCaseInsensitiveFallback(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturnOnConsecutiveCalls([], [5]);

        $entity = $this->createMock(ContentEntityBase::class);
        $entity->method('get')->with('name')->willReturn('sagamok anishnawbek');

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);
        $storage->method('load')->with(5)->willReturn($entity);

        $etm = $this->createMock(EntityTypeManagerInterface::class);
        $etm->method('getStorage')->with('community')->willReturn($storage);

        $resolver = new FixtureResolver($etm);
        $result = $resolver->resolveCommunity('Sagamok Anishnawbek');

        $this->assertSame(5, $result);
    }

    #[Test]
    public function resolvesGroupSlugToId(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([7]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        $etm = $this->createMock(EntityTypeManagerInterface::class);
        $etm->method('getStorage')->with('group')->willReturn($storage);

        $resolver = new FixtureResolver($etm);
        $result = $resolver->resolveGroupSlug('nginaajiiw-salon-spa');

        $this->assertSame(7, $result);
    }

    #[Test]
    public function resolvesTaxonomyTermsByName(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturnOnConsecutiveCalls([101], [102], []);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        $etm = $this->createMock(EntityTypeManagerInterface::class);
        $etm->method('getStorage')->with('taxonomy_term')->willReturn($storage);

        $resolver = new FixtureResolver($etm);
        $warnings = [];
        $result = $resolver->resolveTaxonomyTerms(['Artist', 'Crafter', 'Unknown'], 'person_roles', $warnings);

        $this->assertSame([101, 102], $result);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('Unknown', $warnings[0]);
    }
}
