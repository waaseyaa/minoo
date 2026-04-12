<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\GroupType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GroupType::class)]
final class GroupTypeTest extends TestCase
{
    #[Test]
    public function it_creates_with_machine_name_and_label(): void
    {
        $type = new GroupType(['type' => 'online', 'name' => 'Online Community']);

        $this->assertSame('online', $type->id());
        $this->assertSame('Online Community', $type->label());
        $this->assertSame('group_type', $type->getEntityTypeId());
    }
}
