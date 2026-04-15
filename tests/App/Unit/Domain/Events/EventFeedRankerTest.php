<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Events;

use App\Domain\Events\Service\EventFeedRanker;
use App\Domain\Geo\ValueObject\LocationContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\ContentEntityBase;

#[CoversClass(EventFeedRanker::class)]
final class EventFeedRankerTest extends TestCase
{
    #[Test]
    public function zero_when_no_signals(): void
    {
        $event = $this->mockEvent(type: 'gathering', communityId: null);
        $ranker = new EventFeedRanker();
        $this->assertSame(0, $ranker->score($event, location: null, featuredEventIds: [], communityCoords: []));
    }

    #[Test]
    public function plus_three_when_featured(): void
    {
        $event = $this->mockEvent(id: 42, type: 'gathering');
        $ranker = new EventFeedRanker();
        $this->assertSame(3, $ranker->score($event, location: null, featuredEventIds: [42], communityCoords: []));
    }

    #[Test]
    public function plus_two_when_nearby(): void
    {
        $event = $this->mockEvent(communityId: 'c-1');
        $location = $this->locationAt(46.49, -80.99);
        $communityCoords = ['c-1' => [46.52, -81.00]];
        $ranker = new EventFeedRanker();
        $this->assertSame(2, $ranker->score($event, $location, [], $communityCoords));
    }

    #[Test]
    public function zero_distance_when_beyond_150km(): void
    {
        $event = $this->mockEvent(communityId: 'c-far');
        $location = $this->locationAt(46.49, -80.99);
        $communityCoords = ['c-far' => [43.65, -79.38]]; // Toronto, ~300km
        $ranker = new EventFeedRanker();
        $this->assertSame(0, $ranker->score($event, $location, [], $communityCoords));
    }

    #[Test]
    public function plus_one_for_ceremony_or_powwow(): void
    {
        $ranker = new EventFeedRanker();
        $this->assertSame(1, $ranker->score($this->mockEvent(type: 'ceremony'), null, [], []));
        $this->assertSame(1, $ranker->score($this->mockEvent(type: 'powwow'), null, [], []));
        $this->assertSame(0, $ranker->score($this->mockEvent(type: 'tournament'), null, [], []));
    }

    #[Test]
    public function scores_stack(): void
    {
        $event = $this->mockEvent(id: 7, type: 'ceremony', communityId: 'c-near');
        $location = $this->locationAt(46.49, -80.99);
        $communityCoords = ['c-near' => [46.50, -81.00]];
        $ranker = new EventFeedRanker();
        // featured(+3) + near(+2) + ceremony(+1) = 6
        $this->assertSame(6, $ranker->score($event, $location, [7], $communityCoords));
    }

    private function mockEvent(int $id = 1, string $type = 'gathering', ?string $communityId = null): ContentEntityBase
    {
        $mock = $this->createMock(ContentEntityBase::class);
        $mock->method('id')->willReturn($id);
        $mock->method('get')->willReturnMap([
            ['type', $type],
            ['community_id', $communityId],
        ]);
        return $mock;
    }

    private function locationAt(float $lat, float $lon): LocationContext
    {
        return new LocationContext(
            communityId: 1,
            communityName: 'Home',
            latitude: $lat,
            longitude: $lon,
            source: 'session',
        );
    }
}
