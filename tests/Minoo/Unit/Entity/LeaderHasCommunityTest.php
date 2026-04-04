<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Leader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Community\HasCommunityInterface;

#[CoversClass(Leader::class)]
final class LeaderHasCommunityTest extends TestCase
{
    #[Test]
    public function implements_has_community_interface(): void
    {
        $this->assertInstanceOf(HasCommunityInterface::class, new Leader());
    }

    #[Test]
    public function get_community_id_returns_null_when_unset(): void
    {
        $this->assertNull((new Leader())->getCommunityId());
    }

    #[Test]
    public function get_community_id_returns_value_from_constructor(): void
    {
        $leader = new Leader(['community_id' => 'nc-uuid-123']);
        $this->assertSame('nc-uuid-123', $leader->getCommunityId());
    }

    #[Test]
    public function set_community_id_updates_value(): void
    {
        $leader = new Leader();
        $leader->setCommunityId('nc-uuid-456');
        $this->assertSame('nc-uuid-456', $leader->getCommunityId());
    }
}
