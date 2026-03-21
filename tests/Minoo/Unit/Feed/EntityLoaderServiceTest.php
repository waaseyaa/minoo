<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed;

use Minoo\Feed\EntityLoaderService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;

#[CoversClass(EntityLoaderService::class)]
final class EntityLoaderServiceTest extends TestCase
{
    #[Test]
    public function it_returns_empty_arrays_when_no_entities_exist(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $storage = $this->createMock(\Waaseyaa\Entity\Storage\EntityStorageInterface::class);
        $query = $this->createMock(\Waaseyaa\Entity\Storage\EntityQueryInterface::class);

        $query->method('condition')->willReturnSelf();
        $query->method('sort')->willReturnSelf();
        $query->method('range')->willReturnSelf();
        $query->method('execute')->willReturn([]);
        $storage->method('getQuery')->willReturn($query);
        $etm->method('getStorage')->willReturn($storage);

        $loader = new EntityLoaderService($etm);

        $this->assertSame([], $loader->loadUpcomingEvents(6));
        $this->assertSame([], $loader->loadGroups(6));
        $this->assertSame([], $loader->loadBusinesses(6));
        $this->assertSame([], $loader->loadPublicPeople(6));
    }
}
