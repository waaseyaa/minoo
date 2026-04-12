<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Newsletter\Service;

use Minoo\Domain\Newsletter\Service\RenderTokenStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RenderTokenStore::class)]
final class RenderTokenStoreTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/newsletter-token-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->tmpDir . '/*') ?: []);
        @rmdir($this->tmpDir);
    }

    #[Test]
    public function issued_token_is_consumable_once(): void
    {
        $store = new RenderTokenStore($this->tmpDir, ttlSeconds: 60);

        $token = $store->issue(editionId: 5);

        $this->assertTrue($store->consume($token, editionId: 5));
        $this->assertFalse($store->consume($token, editionId: 5), 'Token must be single-use');
    }

    #[Test]
    public function token_for_other_edition_is_rejected(): void
    {
        $store = new RenderTokenStore($this->tmpDir, ttlSeconds: 60);
        $token = $store->issue(editionId: 5);

        $this->assertFalse($store->consume($token, editionId: 6));
    }

    #[Test]
    public function expired_token_is_rejected(): void
    {
        $store = new RenderTokenStore($this->tmpDir, ttlSeconds: -1);
        $token = $store->issue(editionId: 5);

        $this->assertFalse($store->consume($token, editionId: 5));
    }
}
