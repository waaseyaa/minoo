<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\HomeCommunityIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\User\User;

#[CoversClass(HomeCommunityIdentity::class)]
final class HomeCommunityIdentityTest extends TestCase
{
    #[Test]
    public function defaults_to_null(): void
    {
        $this->assertNull(HomeCommunityIdentity::getHomeCommunity(new User()));
    }

    #[Test]
    public function set_and_get_round_trip(): void
    {
        $user = new User();
        HomeCommunityIdentity::setHomeCommunity($user, 42);
        $this->assertSame(42, HomeCommunityIdentity::getHomeCommunity($user));
    }

    #[Test]
    public function null_clears_the_home_community(): void
    {
        $user = new User();
        HomeCommunityIdentity::setHomeCommunity($user, 42);
        HomeCommunityIdentity::setHomeCommunity($user, null);
        $this->assertNull(HomeCommunityIdentity::getHomeCommunity($user));
    }

    #[Test]
    public function zero_is_treated_as_unset(): void
    {
        $user = new User();
        HomeCommunityIdentity::setHomeCommunity($user, 0);
        $this->assertNull(HomeCommunityIdentity::getHomeCommunity($user));
    }

    #[Test]
    public function reads_value_from_constructor(): void
    {
        $this->assertSame(7, HomeCommunityIdentity::getHomeCommunity(new User(['home_community_id' => 7])));
    }

    #[Test]
    public function set_returns_the_user(): void
    {
        $user = new User();
        $this->assertSame($user, HomeCommunityIdentity::setHomeCommunity($user, 5));
    }
}
