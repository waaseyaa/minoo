<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\OralHistory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Community\HasCommunityInterface;

#[CoversClass(OralHistory::class)]
final class OralHistoryHasCommunityTest extends TestCase
{
    #[Test]
    public function implements_has_community_interface(): void
    {
        $this->assertInstanceOf(HasCommunityInterface::class, new OralHistory());
    }

    #[Test]
    public function get_community_id_returns_null_when_unset(): void
    {
        $this->assertNull((new OralHistory())->getCommunityId());
    }

    #[Test]
    public function get_community_id_returns_value_from_constructor(): void
    {
        $oh = new OralHistory(['community_id' => 'nc-uuid-123']);
        $this->assertSame('nc-uuid-123', $oh->getCommunityId());
    }

    #[Test]
    public function set_community_id_updates_value(): void
    {
        $oh = new OralHistory();
        $oh->setCommunityId('nc-uuid-456');
        $this->assertSame('nc-uuid-456', $oh->getCommunityId());
    }
}
