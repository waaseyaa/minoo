<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\CulturalGroup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CulturalGroup::class)]
final class CulturalGroupTest extends TestCase
{
    #[Test]
    public function it_creates_root_group(): void
    {
        $group = new CulturalGroup(['name' => 'Anishinaabe']);

        $this->assertSame('Anishinaabe', $group->get('name'));
        $this->assertNull($group->get('parent_id'));
        $this->assertSame('cultural_group', $group->getEntityTypeId());
        $this->assertSame(1, $group->get('status'));
    }

    #[Test]
    public function it_creates_child_group_with_parent(): void
    {
        $group = new CulturalGroup([
            'name' => 'Ojibwe',
            'parent_id' => 1,
            'depth_label' => 'tribe',
        ]);

        $this->assertSame(1, $group->get('parent_id'));
        $this->assertSame('tribe', $group->get('depth_label'));
    }

    #[Test]
    public function it_defaults_sort_order_to_zero(): void
    {
        $group = new CulturalGroup(['name' => 'Test']);

        $this->assertSame(0, $group->get('sort_order'));
    }
}
