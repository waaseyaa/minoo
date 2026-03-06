<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\EventType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventType::class)]
final class EventTypeTest extends TestCase
{
    #[Test]
    public function it_creates_with_machine_name_and_label(): void
    {
        $type = new EventType(['type' => 'powwow', 'name' => 'Powwow']);

        $this->assertSame('powwow', $type->id());
        $this->assertSame('Powwow', $type->label());
        $this->assertSame('event_type', $type->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_description_to_empty(): void
    {
        $type = new EventType(['type' => 'gathering', 'name' => 'Gathering']);

        $this->assertSame('', $type->toArray()['description']);
    }
}
