<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\FeedController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedController::class)]
final class FeedControllerFilterTest extends TestCase
{
    #[Test]
    public function filterQueryParamPassesToFeedContext(): void
    {
        $resolved = FeedController::resolveFilter('event');

        $this->assertSame('event', $resolved['filterParam']);
        $this->assertSame('event', $resolved['filter']);
    }

    #[Test]
    public function invalidFilterDefaultsToAll(): void
    {
        $resolved = FeedController::resolveFilter('invalid');

        $this->assertSame('all', $resolved['filterParam']);
        $this->assertSame('all', $resolved['filter']);
    }

    #[Test]
    public function peopleFilterMapsToResourcePerson(): void
    {
        $resolved = FeedController::resolveFilter('people');

        $this->assertSame('people', $resolved['filterParam']);
        $this->assertSame('resource_person', $resolved['filter']);
    }

    #[Test]
    public function personFilterAlsoMapsToResourcePerson(): void
    {
        $resolved = FeedController::resolveFilter('person');

        $this->assertSame('person', $resolved['filterParam']);
        $this->assertSame('resource_person', $resolved['filter']);
    }

    #[Test]
    public function businessFilterMapsCorrectly(): void
    {
        $resolved = FeedController::resolveFilter('business');

        $this->assertSame('business', $resolved['filterParam']);
        $this->assertSame('business', $resolved['filter']);
    }

    #[Test]
    public function groupFilterMapsCorrectly(): void
    {
        $resolved = FeedController::resolveFilter('group');

        $this->assertSame('group', $resolved['filterParam']);
        $this->assertSame('group', $resolved['filter']);
    }

    #[Test]
    public function allFilterMapsCorrectly(): void
    {
        $resolved = FeedController::resolveFilter('all');

        $this->assertSame('all', $resolved['filterParam']);
        $this->assertSame('all', $resolved['filter']);
    }
}
