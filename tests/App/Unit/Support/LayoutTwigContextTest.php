<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\LayoutTwigContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(LayoutTwigContext::class)]
final class LayoutTwigContextTest extends TestCase
{
    #[Test]
    public function with_account_merges_account_and_preserves_context(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $ctx = LayoutTwigContext::withAccount($account, ['path' => '/x'], false);

        $this->assertSame($account, $ctx['account']);
        $this->assertSame('/x', $ctx['path']);
        $this->assertArrayNotHasKey('csrf_token', $ctx);
    }

    #[Test]
    public function with_account_includes_csrf_token_by_default(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $ctx = LayoutTwigContext::withAccount($account, []);

        $this->assertArrayHasKey('csrf_token', $ctx);
        $this->assertNotEmpty($ctx['csrf_token']);
    }

    #[Test]
    public function parameter_account_overrides_context_account_key(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $decoy = $this->createMock(AccountInterface::class);

        $ctx = LayoutTwigContext::withAccount($account, ['account' => $decoy, 'path' => '/y'], false);

        $this->assertSame($account, $ctx['account']);
        $this->assertNotSame($decoy, $ctx['account']);
        $this->assertSame('/y', $ctx['path']);
    }
}
