<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\AccountDisplay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\User\AnonymousUser;
use Waaseyaa\User\User;

#[CoversClass(AccountDisplay::class)]
final class AccountDisplayTest extends TestCase
{
    #[Test]
    public function anonymous_account_has_empty_display_fields(): void
    {
        $anon = new AnonymousUser();
        $this->assertSame('', AccountDisplay::initial($anon));
        $this->assertSame('', AccountDisplay::name($anon));
        $this->assertSame('', AccountDisplay::email($anon));
    }

    #[Test]
    public function user_display_uses_name_mail_and_initial(): void
    {
        $user = new User([
            'uid' => 1,
            'name' => 'Aki',
            'mail' => 'aki@example.test',
        ]);

        $this->assertSame('A', AccountDisplay::initial($user));
        $this->assertSame('Aki', AccountDisplay::name($user));
        $this->assertSame('aki@example.test', AccountDisplay::email($user));
    }
}
