<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Group;
use App\Provider\AppServiceProvider;
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

    #[Test]
    public function it_defines_community_id_field(): void
    {
        $provider = new AppServiceProvider();
        $provider->register();

        $types = $provider->getEntityTypes();
        $groupType = array_values(array_filter($types, fn($t) => $t->id() === 'group'))[0];
        $fields = $groupType->getFieldDefinitions();

        $this->assertArrayHasKey('community_id', $fields);
        $this->assertSame('entity_reference', $fields['community_id']['type']);
        $this->assertSame('community', $fields['community_id']['settings']['target_type']);
    }
}
