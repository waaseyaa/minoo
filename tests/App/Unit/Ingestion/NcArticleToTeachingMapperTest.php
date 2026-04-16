<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingestion;

use App\Ingestion\EntityMapper\NcArticleToTeachingMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\NorthCloud\Sync\NcHitToEntityMapperInterface;

#[CoversClass(NcArticleToTeachingMapper::class)]
final class NcArticleToTeachingMapperTest extends TestCase
{
    private NcArticleToTeachingMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new NcArticleToTeachingMapper();
    }

    #[Test]
    public function it_maps_a_complete_nc_hit_to_teaching_fields(): void
    {
        $hit = [
            'title' => 'Anishinaabe Star Knowledge',
            'snippet' => 'Traditional star maps guided navigation across the Great Lakes.',
            'url' => 'https://example.com/star-knowledge',
            'published_date' => '2026-03-15T10:00:00Z',
        ];

        $fields = $this->mapper->map($hit);

        $this->assertSame('Anishinaabe Star Knowledge', $fields['title']);
        $this->assertSame('anishinaabe-star-knowledge', $fields['slug']);
        $this->assertSame('culture', $fields['type']);
        $this->assertSame('Traditional star maps guided navigation across the Great Lakes.', $fields['content']);
        $this->assertSame('https://example.com/star-knowledge', $fields['source_url']);
        $this->assertSame('external_link', $fields['copyright_status']);
        $this->assertSame(1, $fields['status']);
        $this->assertSame(1, $fields['consent_public']);
        $this->assertSame(0, $fields['consent_ai_training']);
        $this->assertIsInt($fields['created_at']);
        $this->assertGreaterThan(0, $fields['created_at']);
    }

    #[Test]
    public function it_implements_the_northcloud_mapper_contract(): void
    {
        $this->assertInstanceOf(NcHitToEntityMapperInterface::class, $this->mapper);
        $this->assertSame('teaching', $this->mapper->entityType());
        $this->assertSame('source_url', $this->mapper->dedupField());
    }

    #[Test]
    public function it_supports_non_event_hits(): void
    {
        $this->assertTrue($this->mapper->supports([
            'content_type' => 'article',
            'topics' => ['indigenous', 'language'],
        ]));

        $this->assertFalse($this->mapper->supports([
            'content_type' => 'event',
            'topics' => ['indigenous', 'event'],
        ]));
    }

    #[Test]
    public function it_generates_slug_from_title(): void
    {
        $hit = ['title' => 'Seven Grandfather Teachings & More!', 'url' => 'https://example.com/seven'];

        $fields = $this->mapper->map($hit);

        $this->assertSame('seven-grandfather-teachings-more', $fields['slug']);
    }

    #[Test]
    public function it_requires_a_source_url(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing url');

        $this->mapper->map([]);
    }

    #[Test]
    public function it_prefers_body_over_snippet_when_snippet_is_missing(): void
    {
        $hit = [
            'title' => 'Test',
            'body' => 'Full body content here.',
            'url' => 'https://example.com/test',
        ];

        $fields = $this->mapper->map($hit);

        $this->assertSame('Full body content here.', $fields['content']);
    }

    #[Test]
    public function it_uses_current_time_for_invalid_date(): void
    {
        $before = time();
        $fields = $this->mapper->map([
            'title' => 'Test',
            'url' => 'https://example.com/test',
            'published_date' => 'not-a-date',
        ]);
        $after = time();

        $this->assertGreaterThanOrEqual($before, $fields['created_at']);
        $this->assertLessThanOrEqual($after, $fields['created_at']);
    }
}
