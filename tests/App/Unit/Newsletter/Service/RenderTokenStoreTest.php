<?php

declare(strict_types=1);

namespace App\Tests\Unit\Newsletter\Service;

use App\Domain\Newsletter\Service\RenderTokenStore;
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

    #[Test]
    public function malformed_token_is_rejected(): void
    {
        $store = new RenderTokenStore($this->tmpDir, ttlSeconds: 60);

        $this->assertFalse($store->consume('../etc/passwd', editionId: 1));
        $this->assertFalse($store->consume('not-hex!@#$', editionId: 1));
        $this->assertFalse($store->consume('', editionId: 1));
    }

    #[Test]
    public function nonexistent_token_is_rejected(): void
    {
        $store = new RenderTokenStore($this->tmpDir, ttlSeconds: 60);

        $this->assertFalse($store->consume('deadbeef00000000deadbeef00000000', editionId: 1));
    }

    #[Test]
    public function token_is_deleted_from_disk_after_consume(): void
    {
        $store = new RenderTokenStore($this->tmpDir, ttlSeconds: 60);
        $token = $store->issue(editionId: 5);

        $tokenFile = $this->tmpDir . '/' . $token . '.json';
        $this->assertFileExists($tokenFile, 'Token file should exist before consume');

        $store->consume($token, editionId: 5);
        $this->assertFileDoesNotExist($tokenFile, 'Token file should be deleted after consume');
    }

    #[Test]
    public function token_is_deleted_even_when_edition_id_mismatch(): void
    {
        $store = new RenderTokenStore($this->tmpDir, ttlSeconds: 60);
        $token = $store->issue(editionId: 5);

        $tokenFile = $this->tmpDir . '/' . $token . '.json';

        // Consume with wrong edition ID — should fail but still delete the file
        $store->consume($token, editionId: 99);
        $this->assertFileDoesNotExist($tokenFile, 'Token file should be deleted even on mismatch');
    }

    #[Test]
    public function issued_token_is_32_hex_chars(): void
    {
        $store = new RenderTokenStore($this->tmpDir, ttlSeconds: 60);
        $token = $store->issue(editionId: 1);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $token);
    }

    #[Test]
    public function storage_dir_is_created_if_missing(): void
    {
        $nested = $this->tmpDir . '/nested/tokens';
        $this->assertDirectoryDoesNotExist($nested);

        new RenderTokenStore($nested, ttlSeconds: 60);
        $this->assertDirectoryExists($nested);

        @rmdir($nested);
        @rmdir($this->tmpDir . '/nested');
    }
}
