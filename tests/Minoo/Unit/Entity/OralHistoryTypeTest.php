<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\OralHistoryType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OralHistoryType::class)]
final class OralHistoryTypeTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $type = new OralHistoryType([
            'type' => 'prophecy',
            'name' => 'Prophecy',
        ]);

        $this->assertSame('prophecy', $type->id());
        $this->assertSame('Prophecy', $type->label());
        $this->assertSame('oral_history_type', $type->getEntityTypeId());
    }

    #[Test]
    public function it_sets_default_description(): void
    {
        $type = new OralHistoryType([
            'type' => 'creation',
            'name' => 'Creation Story',
        ]);

        $values = $type->toArray();
        $this->assertSame('', $values['description']);
    }

    #[Test]
    public function it_accepts_custom_description(): void
    {
        $type = new OralHistoryType([
            'type' => 'ceremony',
            'name' => 'Ceremony',
            'description' => 'Stories about ceremonial practices.',
        ]);

        $values = $type->toArray();
        $this->assertSame('Stories about ceremonial practices.', $values['description']);
    }
}
