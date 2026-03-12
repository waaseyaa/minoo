<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\PasswordResetService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordResetService::class)]
final class PasswordResetServiceTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    #[Test]
    public function createToken_returns_64_char_hex_string(): void
    {
        $service = new PasswordResetService($this->pdo);
        $token = $service->createToken(1);
        self::assertSame(64, strlen($token));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    #[Test]
    public function validateToken_returns_user_id_for_valid_token(): void
    {
        $service = new PasswordResetService($this->pdo);
        $token = $service->createToken(42);
        self::assertEquals(42, $service->validateToken($token));
    }

    #[Test]
    public function validateToken_returns_null_for_nonexistent_token(): void
    {
        $service = new PasswordResetService($this->pdo);
        self::assertNull($service->validateToken('nonexistent'));
    }

    #[Test]
    public function consumeToken_makes_token_invalid(): void
    {
        $service = new PasswordResetService($this->pdo);
        $token = $service->createToken(1);
        $service->consumeToken($token);
        self::assertNull($service->validateToken($token));
    }

    #[Test]
    public function createToken_invalidates_previous_token_for_same_user(): void
    {
        $service = new PasswordResetService($this->pdo);
        $token1 = $service->createToken(1);
        $token2 = $service->createToken(1);
        self::assertNull($service->validateToken($token1));
        self::assertEquals(1, $service->validateToken($token2));
    }

    #[Test]
    public function expired_token_returns_null(): void
    {
        // Create service, manually insert an expired token
        $service = new PasswordResetService($this->pdo);
        $service->createToken(1); // ensures table exists
        $this->pdo->exec('DELETE FROM password_reset_tokens');
        $stmt = $this->pdo->prepare(
            'INSERT INTO password_reset_tokens (token, user_id, expires_at, used_at) VALUES (:token, :uid, :expires, NULL)'
        );
        $stmt->execute(['token' => 'expired-token', 'uid' => 1, 'expires' => time() - 1]);
        self::assertNull($service->validateToken('expired-token'));
    }
}
