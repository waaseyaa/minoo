<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Community\HasCommunityInterface;

#[CoversClass(Event::class)]
final class EventHasCommunityTest extends TestCase
{
    #[Test]
    public function implements_has_community_interface(): void
    {
        $this->assertInstanceOf(HasCommunityInterface::class, new Event());
    }

    #[Test]
    public function get_community_id_returns_null_when_unset(): void
    {
        $event = new Event();
        $this->assertNull($event->getCommunityId());
    }

    #[Test]
    public function get_community_id_returns_value_from_constructor(): void
    {
        $event = new Event(['community_id' => 'nc-uuid-123']);
        $this->assertSame('nc-uuid-123', $event->getCommunityId());
    }

    #[Test]
    public function set_community_id_updates_value(): void
    {
        $event = new Event();
        $event->setCommunityId('nc-uuid-456');
        $this->assertSame('nc-uuid-456', $event->getCommunityId());
    }
}
