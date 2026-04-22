<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Teaching;
use App\Provider\MinooEntityStackProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Teaching::class)]
final class TeachingTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $teaching = new Teaching([
            'title' => 'Seven Grandfather Teachings',
            'type' => 'culture',
            'content' => 'The Seven Grandfather Teachings...',
        ]);

        $this->assertSame('Seven Grandfather Teachings', $teaching->get('title'));
        $this->assertSame('culture', $teaching->bundle());
        $this->assertSame('teaching', $teaching->getEntityTypeId());
        $this->assertSame(1, $teaching->get('status'));
    }

    #[Test]
    public function it_supports_cultural_group_reference(): void
    {
        $teaching = new Teaching([
            'title' => 'Ojibwe Creation Story',
            'type' => 'history',
            'content' => 'Long ago...',
            'cultural_group_id' => 42,
        ]);

        $this->assertSame(42, $teaching->get('cultural_group_id'));
    }

    #[Test]
    public function it_defines_community_id_field(): void
    {
        $provider = new MinooEntityStackProvider();
        $provider->register();

        $types = $provider->getEntityTypes();
        $teachingType = array_values(array_filter($types, fn($t) => $t->id() === 'teaching'))[0];
        $fields = $teachingType->getFieldDefinitions();

        $this->assertArrayHasKey('community_id', $fields);
        $this->assertSame('entity_reference', $fields['community_id']['type']);
        $this->assertSame('community', $fields['community_id']['settings']['target_type']);
    }
}
