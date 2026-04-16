<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingestion;

use App\Ingestion\EntityMapper\NcArticleToEventMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\NorthCloud\Sync\NcHitToEntityMapperInterface;

#[CoversClass(NcArticleToEventMapper::class)]
final class NcArticleToEventMapperTest extends TestCase
{
    private NcArticleToEventMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new NcArticleToEventMapper();
    }

    #[Test]
    public function it_maps_a_complete_nc_hit_to_event_fields(): void
    {
        $hit = [
            'title' => 'National Indigenous Peoples Day Celebration',
            'snippet' => 'Join us for ceremony, food, and music at Chiefswood Park.',
            'url' => 'https://example.com/nipd-2026',
            'published_date' => '2026-06-21T09:00:00Z',
        ];

        $fields = $this->mapper->map($hit);

        $this->assertSame('National Indigenous Peoples Day Celebration', $fields['title']);
        $this->assertSame('national-indigenous-peoples-day-celebration', $fields['slug']);
        $this->assertSame('gathering', $fields['type']);
        $this->assertSame('Join us for ceremony, food, and music at Chiefswood Park.', $fields['description']);
        $this->assertSame('https://example.com/nipd-2026', $fields['source_url']);
        $this->assertSame('external_link', $fields['copyright_status']);
        $this->assertSame(1, $fields['status']);
    }

    #[Test]
    public function it_requires_a_source_url(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing url');

        $this->mapper->map([]);
    }

    #[Test]
    public function it_parses_valid_published_date(): void
    {
        $hit = [
            'title' => 'Test Event',
            'url' => 'https://example.com/test-event',
            'published_date' => '2026-06-21T09:00:00Z',
        ];

        $fields = $this->mapper->map($hit);

        $expected = strtotime('2026-06-21T09:00:00Z');
        $this->assertSame($expected, $fields['created_at']);
    }

    #[Test]
    public function it_implements_the_northcloud_mapper_contract(): void
    {
        $this->assertInstanceOf(NcHitToEntityMapperInterface::class, $this->mapper);
        $this->assertSame('event', $this->mapper->entityType());
        $this->assertSame('source_url', $this->mapper->dedupField());
    }

    #[Test]
    public function it_only_supports_event_hits(): void
    {
        $this->assertTrue($this->mapper->supports([
            'content_type' => 'article',
            'topics' => ['indigenous', 'event'],
        ]));

        $this->assertTrue($this->mapper->supports([
            'content_type' => 'event',
            'topics' => ['indigenous'],
        ]));

        $this->assertFalse($this->mapper->supports([
            'content_type' => 'article',
            'topics' => ['indigenous', 'language'],
        ]));
    }
}
