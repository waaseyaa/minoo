<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Community\HasCommunityInterface;

#[CoversClass(Group::class)]
final class GroupHasCommunityTest extends TestCase
{
    #[Test]
    public function implements_has_community_interface(): void
    {
        $this->assertInstanceOf(HasCommunityInterface::class, new Group());
    }

    #[Test]
    public function get_community_id_returns_null_when_unset(): void
    {
        $this->assertNull((new Group())->getCommunityId());
    }

    #[Test]
    public function get_community_id_returns_value_from_constructor(): void
    {
        $group = new Group(['community_id' => 'nc-uuid-123']);
        $this->assertSame('nc-uuid-123', $group->getCommunityId());
    }

    #[Test]
    public function set_community_id_updates_value(): void
    {
        $group = new Group();
        $group->setCommunityId('nc-uuid-456');
        $this->assertSame('nc-uuid-456', $group->getCommunityId());
    }
}
