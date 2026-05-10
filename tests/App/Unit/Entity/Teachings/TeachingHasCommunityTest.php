<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Teachings;

use App\Entity\Teachings\Teaching;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Teaching::class)]
final class TeachingHasCommunityTest extends TestCase
{
    #[Test]
    public function get_community_id_returns_null_when_unset(): void
    {
        $this->assertNull((new Teaching())->getCommunityId());
    }

    #[Test]
    public function get_community_id_returns_value_from_constructor(): void
    {
        $teaching = new Teaching(['community_id' => 'nc-uuid-123']);
        $this->assertSame('nc-uuid-123', $teaching->getCommunityId());
    }

    #[Test]
    public function set_community_id_updates_value(): void
    {
        $teaching = new Teaching();
        $teaching->setCommunityId('nc-uuid-456');
        $this->assertSame('nc-uuid-456', $teaching->getCommunityId());
    }
}
