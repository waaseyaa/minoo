<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\FeaturedItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeaturedItem::class)]
final class FeaturedItemTest extends TestCase
{
    #[Test]
    public function constructor_accepts_all_fields(): void
    {
        $item = new FeaturedItem([
            'fid' => 1,
            'entity_type' => 'event',
            'entity_id' => 13,
            'headline' => 'Little NHL 2026',
            'subheadline' => '271 teams — Markham, Ontario',
            'weight' => 100,
            'starts_at' => '2026-03-10',
            'ends_at' => '2026-03-21',
            'status' => 1,
        ]);

        $this->assertSame(1, $item->id());
        $this->assertSame('Little NHL 2026', $item->label());
        $this->assertSame('event', $item->get('entity_type'));
        $this->assertSame(13, $item->get('entity_id'));
        $this->assertSame('271 teams — Markham, Ontario', $item->get('subheadline'));
        $this->assertSame(100, $item->get('weight'));
        $this->assertSame('2026-03-10', $item->get('starts_at'));
        $this->assertSame('2026-03-21', $item->get('ends_at'));
    }
}
