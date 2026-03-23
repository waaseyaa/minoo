<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\ElderIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\User\User;

#[CoversClass(ElderIdentity::class)]
final class ElderIdentityTest extends TestCase
{
    #[Test]
    public function isElder_defaults_false(): void
    {
        $user = new User(['uid' => 1]);
        $this->assertFalse(ElderIdentity::isElder($user));
    }

    #[Test]
    public function setElder_true(): void
    {
        $user = new User(['uid' => 1]);
        ElderIdentity::setElder($user, true);
        $this->assertTrue(ElderIdentity::isElder($user));
    }

    #[Test]
    public function setElder_false_after_true(): void
    {
        $user = new User(['uid' => 1]);
        ElderIdentity::setElder($user, true);
        ElderIdentity::setElder($user, false);
        $this->assertFalse(ElderIdentity::isElder($user));
    }

    #[Test]
    public function setElder_returns_user(): void
    {
        $user = new User(['uid' => 1]);
        $result = ElderIdentity::setElder($user, true);
        $this->assertSame($user, $result);
    }

    #[Test]
    public function isElder_via_constructor(): void
    {
        $user = new User(['uid' => 1, 'is_elder' => 1]);
        $this->assertTrue(ElderIdentity::isElder($user));
    }
}
