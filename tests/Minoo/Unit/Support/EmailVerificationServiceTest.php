<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\EmailVerificationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailVerificationService::class)]
final class EmailVerificationServiceTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    #[Test]
    public function create_token_returns_64_char_hex(): void
    {
        $service = new EmailVerificationService($this->pdo);
        $token = $service->createToken(1);
        self::assertSame(64, strlen($token));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    #[Test]
    public function validate_token_returns_user_id(): void
    {
        $service = new EmailVerificationService($this->pdo);
        $token = $service->createToken(42);
        self::assertEquals(42, $service->validateToken($token));
    }

    #[Test]
    public function validate_token_returns_null_for_invalid_token(): void
    {
        $service = new EmailVerificationService($this->pdo);
        self::assertNull($service->validateToken('nonexistent'));
    }

    #[Test]
    public function create_token_invalidates_previous_token_for_same_user(): void
    {
        $service = new EmailVerificationService($this->pdo);
        $token1 = $service->createToken(1);
        $token2 = $service->createToken(1);
        self::assertNull($service->validateToken($token1));
        self::assertEquals(1, $service->validateToken($token2));
    }

    #[Test]
    public function consume_token_marks_it_as_used(): void
    {
        $service = new EmailVerificationService($this->pdo);
        $token = $service->createToken(1);
        $service->consumeToken($token);
        self::assertNull($service->validateToken($token));
    }

    #[Test]
    public function expired_token_returns_null(): void
    {
        $service = new EmailVerificationService($this->pdo);
        $service->createToken(1); // ensures table exists
        $this->pdo->exec('DELETE FROM email_verification_tokens');
        $stmt = $this->pdo->prepare(
            'INSERT INTO email_verification_tokens (token, user_id, expires_at, used_at) VALUES (:token, :uid, :expires, NULL)'
        );
        $stmt->execute(['token' => 'expired-token', 'uid' => 1, 'expires' => time() - 1]);
        self::assertNull($service->validateToken('expired-token'));
    }
}
