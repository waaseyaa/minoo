<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Teaching;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Community\HasCommunityInterface;

#[CoversClass(Teaching::class)]
final class TeachingHasCommunityTest extends TestCase
{
    #[Test]
    public function implements_has_community_interface(): void
    {
        $this->assertInstanceOf(HasCommunityInterface::class, new Teaching());
    }

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
