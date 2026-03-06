<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Group::class)]
final class GroupTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $group = new Group(['name' => 'Ojibwe Language Table', 'type' => 'online']);

        $this->assertSame('Ojibwe Language Table', $group->get('name'));
        $this->assertSame('online', $group->bundle());
        $this->assertSame(1, $group->get('status'));
        $this->assertSame('group', $group->getEntityTypeId());
    }

    #[Test]
    public function it_supports_optional_fields(): void
    {
        $group = new Group([
            'name' => 'Mille Lacs Band',
            'type' => 'offline',
            'url' => 'https://millelacsband.com',
            'region' => 'Minnesota',
            'description' => 'Tribal community group.',
        ]);

        $this->assertSame('https://millelacsband.com', $group->get('url'));
        $this->assertSame('Minnesota', $group->get('region'));
    }
}
