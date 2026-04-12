<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Contributor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Community\HasCommunityInterface;

#[CoversClass(Contributor::class)]
final class ContributorHasCommunityTest extends TestCase
{
    #[Test]
    public function implements_has_community_interface(): void
    {
        $this->assertInstanceOf(HasCommunityInterface::class, new Contributor());
    }

    #[Test]
    public function get_community_id_returns_null_when_unset(): void
    {
        $this->assertNull((new Contributor())->getCommunityId());
    }

    #[Test]
    public function get_community_id_returns_value_from_constructor(): void
    {
        $c = new Contributor(['community_id' => 'nc-uuid-123']);
        $this->assertSame('nc-uuid-123', $c->getCommunityId());
    }

    #[Test]
    public function set_community_id_updates_value(): void
    {
        $c = new Contributor();
        $c->setCommunityId('nc-uuid-456');
        $this->assertSame('nc-uuid-456', $c->getCommunityId());
    }
}
