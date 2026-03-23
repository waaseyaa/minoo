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
    public function defaultsToFalse(): void
    {
        $user = new User();
        $this->assertFalse(ElderIdentity::isElder($user));
    }

    #[Test]
    public function setElderTrue(): void
    {
        $user = new User();
        ElderIdentity::setElder($user, true);
        $this->assertTrue(ElderIdentity::isElder($user));
    }

    #[Test]
    public function setElderFalseAfterTrue(): void
    {
        $user = new User();
        ElderIdentity::setElder($user, true);
        ElderIdentity::setElder($user, false);
        $this->assertFalse(ElderIdentity::isElder($user));
    }

    #[Test]
    public function setElderReturnsUser(): void
    {
        $user = new User();
        $result = ElderIdentity::setElder($user, true);
        $this->assertSame($user, $result);
    }

    #[Test]
    public function isElderViaConstructor(): void
    {
        $user = new User(['is_elder' => 1]);
        $this->assertTrue(ElderIdentity::isElder($user));
    }
}
