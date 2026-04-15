<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Events;

use App\Domain\Events\Service\EventFeedBuilder;
use App\Domain\Events\Service\EventFeedRanker;
use App\Domain\Events\ValueObject\EventFilters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(EventFeedBuilder::class)]
final class EventFeedBuilderTest extends TestCase
{
    #[Test]
    public function happening_now_section_contains_only_active_events(): void
    {
        $now = strtotime('2026-04-14 12:00:00');
        $events = [
            $this->event(1, type: 'powwow',    starts: $now - 3600, ends: $now + 3600),  // happening
            $this->event(2, type: 'ceremony',  starts: $now + 7200, ends: $now + 10800), // this week
            $this->event(3, type: 'gathering', starts: $now - 86400, ends: $now - 3600), // past
        ];
        $builder = $this->buildWith($events, $now);
        $result = $builder->build(EventFilters::fromRequest(Request::create('/events')), null);

        $this->assertCount(1, $result->happeningNow);
        $this->assertSame(1, $result->happeningNow[0]->id());
    }

    #[Test]
    public function this_week_section_contains_events_in_next_7_days(): void
    {
        $now = strtotime('2026-04-14 12:00:00');
        $events = [
            $this->event(10, starts: $now + 86400),       // +1d
            $this->event(11, starts: $now + 6 * 86400),   // +6d
            $this->event(12, starts: $now + 9 * 86400),   // +9d — should NOT be in thisWeek
        ];
        $builder = $this->buildWith($events, $now);
        $result = $builder->build(EventFilters::fromRequest(Request::create('/events')), null);

        $ids = array_map(fn ($e) => $e->id(), $result->thisWeek);
        $this->assertSame([10, 11], $ids);
    }

    /** @param list<ContentEntityBase> $events */
    private function buildWith(array $events, int $now): EventFeedBuilder
    {
        $byId = [];
        foreach ($events as $e) {
            $byId[$e->id()] = $e;
        }

        $queryStub = new class ($events) implements EntityQueryInterface {
            /** @param list<ContentEntityBase> $events */
            public function __construct(private array $events) {}
            public function condition(string $field, mixed $value, string $operator = '='): static { return $this; }
            public function exists(string $field): static { return $this; }
            public function notExists(string $field): static { return $this; }
            public function sort(string $field, string $direction = 'ASC'): static { return $this; }
            public function range(int $offset, int $limit): static { return $this; }
            public function count(): static { return $this; }
            public function accessCheck(bool $check = true): static { return $this; }
            public function execute(): array
            {
                return array_map(fn ($e) => $e->id(), $this->events);
            }
        };

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($queryStub);
        $storage->method('loadMultiple')->willReturnCallback(
            fn (array $ids = []) => array_combine(
                $ids,
                array_values($this->eventsById($events, $ids))
            )
        );

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($storage);

        return new EventFeedBuilder($etm, new EventFeedRanker(), clock: fn () => $now);
    }

    /**
     * @param list<ContentEntityBase> $events
     * @param list<int> $ids
     * @return list<ContentEntityBase>
     */
    private function eventsById(array $events, array $ids): array
    {
        $byId = [];
        foreach ($events as $e) {
            $byId[$e->id()] = $e;
        }
        return array_values(array_filter(array_map(fn ($id) => $byId[$id] ?? null, $ids)));
    }

    private function event(int $id, string $type = 'gathering', ?string $communityId = null, int $starts = 0, ?int $ends = null): ContentEntityBase
    {
        $ends ??= $starts + 3600;
        $mock = $this->createMock(ContentEntityBase::class);
        $mock->method('id')->willReturn($id);
        $mock->method('get')->willReturnMap([
            ['type', $type],
            ['community_id', $communityId],
            ['starts_at', $starts],
            ['ends_at', $ends],
            ['status', 1],
            ['slug', 'e-' . $id],
            ['title', 'Event ' . $id],
            ['description', ''],
            ['location', ''],
        ]);
        return $mock;
    }
}
