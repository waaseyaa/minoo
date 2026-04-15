<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Events;

use App\Domain\Events\ValueObject\EventFilters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(EventFilters::class)]
final class EventFiltersTest extends TestCase
{
    #[Test]
    public function defaults_when_query_is_empty(): void
    {
        $f = EventFilters::fromRequest(Request::create('/events'));
        $this->assertSame([], $f->types);
        $this->assertNull($f->communityId);
        $this->assertSame('all', $f->when);
        $this->assertFalse($f->near);
        $this->assertNull($f->q);
        $this->assertSame('feed', $f->view);
        $this->assertNull($f->month);
        $this->assertSame('soonest', $f->sort);
        $this->assertSame(1, $f->page);
        $this->assertFalse($f->isActive());
    }

    #[Test]
    public function parses_and_whitelists_values(): void
    {
        $r = Request::create('/events', 'GET', [
            'type' => ['powwow', 'gathering', 'hacked'],
            'community_id' => 'abc-123',
            'when' => 'week',
            'near' => '1',
            'q' => '  Sunrise  ',
            'view' => 'calendar',
            'month' => '2026-05',
            'sort' => 'latest',
            'page' => '3',
        ]);
        $f = EventFilters::fromRequest($r);
        $this->assertSame(['powwow', 'gathering'], $f->types);
        $this->assertSame('abc-123', $f->communityId);
        $this->assertSame('week', $f->when);
        $this->assertTrue($f->near);
        $this->assertSame('Sunrise', $f->q);
        $this->assertSame('calendar', $f->view);
        $this->assertSame('2026-05', $f->month);
        $this->assertSame('latest', $f->sort);
        $this->assertSame(3, $f->page);
        $this->assertTrue($f->isActive());
    }

    #[Test]
    public function rejects_invalid_whens_views_sorts_and_months(): void
    {
        $r = Request::create('/events', 'GET', [
            'when' => 'eternity',
            'view' => 'grid',
            'sort' => 'random',
            'month' => '2026/05',
            'page' => '-4',
        ]);
        $f = EventFilters::fromRequest($r);
        $this->assertSame('all', $f->when);
        $this->assertSame('feed', $f->view);
        $this->assertSame('soonest', $f->sort);
        $this->assertNull($f->month);
        $this->assertSame(1, $f->page);
    }

    #[Test]
    public function is_active_only_when_narrowing_filter_set(): void
    {
        $only_view = EventFilters::fromRequest(Request::create('/events', 'GET', ['view' => 'list']));
        $this->assertFalse($only_view->isActive(), 'view change alone is not a narrowing filter');

        $with_type = EventFilters::fromRequest(Request::create('/events', 'GET', ['type' => ['ceremony']]));
        $this->assertTrue($with_type->isActive());
    }

    #[Test]
    public function without_drops_one_param_and_preserves_others(): void
    {
        $r = Request::create('/events', 'GET', ['type' => ['powwow'], 'when' => 'month']);
        $f = EventFilters::fromRequest($r);
        $w = $f->without('type', 'powwow');
        $this->assertSame([], $w->types);
        $this->assertSame('month', $w->when);
    }

    #[Test]
    public function to_query_string_empty_on_defaults(): void
    {
        $f = EventFilters::fromRequest(Request::create('/events'));
        $this->assertSame('', $f->toQueryString());
    }

    #[Test]
    public function to_query_string_emits_active_filters(): void
    {
        $r = Request::create('/events', 'GET', [
            'type' => ['powwow', 'ceremony'],
            'when' => 'week',
            'q' => 'sunrise',
            'view' => 'list',
            'sort' => 'latest',
        ]);
        $f = EventFilters::fromRequest($r);
        $qs = $f->toQueryString();
        $this->assertStringStartsWith('?', $qs);
        parse_str(ltrim($qs, '?'), $parsed);
        $this->assertSame('sunrise', $parsed['q']);
        $this->assertSame('week', $parsed['when']);
        $this->assertSame(['powwow', 'ceremony'], $parsed['type']);
        $this->assertSame('list', $parsed['view']);
        $this->assertSame('latest', $parsed['sort']);
    }

    #[Test]
    public function to_query_string_round_trips_through_without(): void
    {
        $r = Request::create('/events', 'GET', ['type' => ['powwow', 'ceremony'], 'when' => 'month']);
        $f = EventFilters::fromRequest($r);
        $qs = $f->without('type', 'powwow')->toQueryString();
        parse_str(ltrim($qs, '?'), $parsed);
        $this->assertSame(['ceremony'], $parsed['type']);
        $this->assertSame('month', $parsed['when']);
    }
}
