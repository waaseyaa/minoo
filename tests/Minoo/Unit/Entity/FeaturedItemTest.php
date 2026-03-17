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
    public function constructor_sets_defaults(): void
    {
        $item = new FeaturedItem(['fid' => 1, 'headline' => 'Little NHL 2026']);
        $values = $item->toArray();
        $this->assertSame(0, $values['weight']);
        $this->assertSame(1, $values['status']);
    }

    #[Test]
    public function constructor_accepts_all_fields(): void
    {
        $item = new FeaturedItem([
            'fid' => 1, 'entity_type' => 'event', 'entity_id' => 13,
            'headline' => 'Little NHL 2026', 'subheadline' => '271 teams — Markham, Ontario',
            'weight' => 100, 'starts_at' => '2026-03-10', 'ends_at' => '2026-03-21', 'status' => 1,
        ]);
        $values = $item->toArray();
        $this->assertSame(1, $item->id());
        $this->assertSame('Little NHL 2026', $item->label());
        $this->assertSame('event', $values['entity_type']);
        $this->assertSame(13, $values['entity_id']);
        $this->assertSame(100, $values['weight']);
    }

    #[Test]
    public function weight_defaults_to_zero(): void
    {
        $item = new FeaturedItem(['fid' => 1, 'headline' => 'Test']);
        $this->assertSame(0, $item->toArray()['weight']);
    }

    #[Test]
    public function status_defaults_to_published(): void
    {
        $item = new FeaturedItem(['fid' => 1, 'headline' => 'Test']);
        $this->assertTrue($item->status());
    }
}
