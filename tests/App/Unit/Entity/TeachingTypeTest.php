<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\TeachingType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TeachingType::class)]
final class TeachingTypeTest extends TestCase
{
    #[Test]
    public function it_creates_with_machine_name_and_label(): void
    {
        $type = new TeachingType(['type' => 'culture', 'name' => 'Culture']);

        $this->assertSame('culture', $type->id());
        $this->assertSame('Culture', $type->label());
        $this->assertSame('teaching_type', $type->getEntityTypeId());
    }
}
